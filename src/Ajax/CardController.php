<?php

namespace S4D\Thawani\Ajax;

use S4D\Thawani\Gateway;

defined( 'ABSPATH' ) || exit;

final class CardController {

	public function register(): void {
		add_action( 'wp_ajax_thawaniDeleteCard', array( $this, 'delete_card' ) );
		add_action( 'wp_ajax_nopriv_thawaniDeleteCard', array( $this, 'delete_card' ) );
	}

	public function delete_card(): void {
		check_ajax_referer( 'thawani_cards', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'woocommerce-gateway-thawani' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$index   = isset( $_REQUEST['id'] ) ? (int) wp_unslash( $_REQUEST['id'] ) : -1;

		$cards = (array) get_user_meta( $user_id, Gateway::USER_CARDS_CACHE, true );
		if ( ! isset( $cards[ $index ]['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Card not found.', 'woocommerce-gateway-thawani' ) ), 404 );
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ Gateway::GATEWAY_ID ] ?? null;
		if ( ! $gateway instanceof Gateway ) {
			wp_send_json_error( array( 'message' => __( 'Gateway unavailable.', 'woocommerce-gateway-thawani' ) ), 503 );
		}

		$gateway->api()->delete_payment_method( (string) $cards[ $index ]['id'] );

		unset( $cards[ $index ] );
		update_user_meta( $user_id, Gateway::USER_CARDS_CACHE, $cards );

		wp_send_json_success( array( 'type' => 'success' ) );
	}
}
