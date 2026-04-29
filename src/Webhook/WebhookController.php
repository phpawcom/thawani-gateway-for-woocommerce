<?php

namespace S4D\Thawani\Webhook;

use S4D\Thawani\Gateway;
use WC_Order;

defined( 'ABSPATH' ) || exit;

final class WebhookController {

	public function register(): void {
		add_action( 'woocommerce_api_thawani', array( $this, 'handle' ) );
	}

	public function handle(): void {
		$order_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		if ( $order_id <= 0 ) {
			status_header( 400 );
			exit;
		}

		$hash = isset( $_GET['hash'] ) ? sanitize_text_field( wp_unslash( $_GET['hash'] ) ) : '';
		if ( ! hash_equals( md5( (string) $order_id ), $hash ) ) {
			status_header( 403 );
			exit;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			status_header( 404 );
			exit;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ Gateway::GATEWAY_ID ] ?? null;
		if ( ! $gateway instanceof Gateway ) {
			status_header( 503 );
			exit;
		}

		$paid = false;
		if ( isset( $_GET['intent'] ) ) {
			$intent_id = (string) $order->get_meta( Gateway::META_INTENT_ID );
			if ( $intent_id !== '' ) {
				$response = $gateway->api()->get_payment_intent( $intent_id );
				$status   = strtolower( (string) ( $response['body']['data']['status'] ?? '' ) );
				$paid     = $status === 'succeeded';
			}
		} else {
			$session_id = (string) $order->get_meta( Gateway::META_SESSION_ID );
			if ( $session_id !== '' ) {
				$response = $gateway->api()->get_checkout_session( $session_id );
				$data     = $response['body']['data'] ?? array();
				if ( isset( $data[0] ) ) {
					$data = $data[0];
				}
				$paid = strtolower( (string) ( $data['payment_status'] ?? '' ) ) === 'paid';
			}
		}

		if ( $paid ) {
			$order->payment_complete();
			wc_reduce_stock_levels( $order->get_id() );
			$redirect = $order->get_checkout_order_received_url();
		} else {
			$redirect = $order->get_checkout_payment_url( true );
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
