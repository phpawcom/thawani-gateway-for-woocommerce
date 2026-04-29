<?php

namespace S4D\Thawani\Webhook;

use S4D\Thawani\Gateway;
use WC_Order;

defined( 'ABSPATH' ) || exit;

final class EventController {

	public function register(): void {
		add_action( 'woocommerce_api_thawani_webhook', array( $this, 'handle' ) );
	}

	public function handle(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
			status_header( 405 );
			exit;
		}

		$raw     = file_get_contents( 'php://input' );
		$payload = json_decode( (string) $raw, true );
		if ( ! is_array( $payload ) ) {
			status_header( 400 );
			exit;
		}

		$gateway = $this->gateway();
		if ( ! $gateway instanceof Gateway ) {
			status_header( 503 );
			exit;
		}

		$logger = $gateway->is_debug_mode() ? wc_get_logger() : null;
		if ( $logger ) {
			$logger->debug( 'Webhook received: ' . wp_json_encode( $payload ), array( 'source' => 'thawani' ) );
		}

		$secret = $gateway->webhook_secret();
		if ( $secret !== '' && ! $this->verify_signature( (string) $raw, $secret ) ) {
			if ( $logger ) {
				$logger->warning( 'Webhook signature mismatch.', array( 'source' => 'thawani' ) );
			}
			status_header( 401 );
			exit;
		}

		$type = strtolower( (string) ( $payload['type'] ?? $payload['event_type'] ?? $payload['event'] ?? '' ) );
		$data = (array) ( $payload['data'] ?? array() );
		if ( isset( $data[0] ) && is_array( $data[0] ) ) {
			$data = $data[0];
		}

		$order = $this->locate_order( $data );
		if ( ! $order instanceof WC_Order ) {
			if ( $logger ) {
				$logger->warning( 'Webhook could not locate order.', array( 'source' => 'thawani' ) );
			}
			status_header( 200 );
			exit;
		}

		$payment_id = (string) ( $data['payment_id'] ?? $data['id'] ?? '' );
		if ( $payment_id === '' && ! empty( $data['payments'][0] ) && is_array( $data['payments'][0] ) ) {
			$payment_id = (string) ( $data['payments'][0]['payment_id'] ?? $data['payments'][0]['id'] ?? '' );
		}

		if ( $payment_id !== '' ) {
			$order->update_meta_data( Gateway::META_PAYMENT_ID, $payment_id );
			$order->save();
		}

		if ( $this->is_paid_event( $type, $data ) && ! $order->is_paid() ) {
			$order->payment_complete( $payment_id );
			wc_reduce_stock_levels( $order->get_id() );
		}

		status_header( 200 );
		exit;
	}

	private function is_paid_event( string $type, array $data ): bool {
		if ( strtolower( (string) ( $data['payment_status'] ?? '' ) ) === 'paid' ) {
			return true;
		}
		if ( strtolower( (string) ( $data['status'] ?? '' ) ) === 'succeeded' ) {
			return true;
		}
		foreach ( array( 'paid', 'completed', 'success', 'succeeded' ) as $needle ) {
			if ( strpos( $type, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private function locate_order( array $data ): ?WC_Order {
		$order_id = (int) ( $data['metadata']['order_id'] ?? 0 );
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		$session_id = (string) ( $data['session_id'] ?? '' );
		if ( $session_id !== '' ) {
			$found = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_key'   => Gateway::META_SESSION_ID,
					'meta_value' => $session_id,
				)
			);
			if ( ! empty( $found ) && $found[0] instanceof WC_Order ) {
				return $found[0];
			}
		}

		$intent_id = (string) ( $data['payment_intent_id'] ?? $data['intent_id'] ?? '' );
		if ( $intent_id !== '' ) {
			$found = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_key'   => Gateway::META_INTENT_ID,
					'meta_value' => $intent_id,
				)
			);
			if ( ! empty( $found ) && $found[0] instanceof WC_Order ) {
				return $found[0];
			}
		}

		return null;
	}

	private function verify_signature( string $raw, string $secret ): bool {
		$header = $_SERVER['HTTP_THAWANI_SIGNATURE'] ?? $_SERVER['HTTP_X_THAWANI_SIGNATURE'] ?? '';
		if ( $header === '' ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $raw, $secret );
		return hash_equals( $expected, (string) $header );
	}

	private function gateway(): ?Gateway {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ Gateway::GATEWAY_ID ] ?? null;
		return $gateway instanceof Gateway ? $gateway : null;
	}
}
