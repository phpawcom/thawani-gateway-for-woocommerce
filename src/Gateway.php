<?php

namespace S4D\Thawani;

use S4D\Thawani\Api\Client;
use WC_Order;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

class Gateway extends WC_Payment_Gateway {

	public const GATEWAY_ID = 'thawani';

	public const META_SESSION_ID  = 'thawani_session_id';
	public const META_INTENT_ID   = 'intent_id';
	public const USER_CUSTOMER_ID = '_thawani_customer_id';
	public const USER_CARDS_CACHE = 'cards';

	private ?Client $api = null;

	public function __construct() {
		$this->id                 = self::GATEWAY_ID;
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'Thawani Gateway', 'thawani' );
		$this->method_description = __( 'Accept Visa or MasterCard payments via Thawani.', 'thawani' );
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );
	}

	public function is_test_mode(): bool {
		return 'yes' === $this->get_option( 'testmode' );
	}

	public function is_debug_mode(): bool {
		return 'yes' === $this->get_option( 'debug_mode' );
	}

	public function should_use_order_id(): bool {
		return 'yes' === $this->get_option( 'use_order_id' );
	}

	public function save_cards_enabled(): bool {
		return 'yes' === $this->get_option( 's4d_save_credit_cards' );
	}

	public function publishable_key(): string {
		return $this->is_test_mode()
			? (string) $this->get_option( 'test_publishable_key' )
			: (string) $this->get_option( 'publishable_key' );
	}

	public function secret_key(): string {
		return $this->is_test_mode()
			? (string) $this->get_option( 'test_private_key' )
			: (string) $this->get_option( 'private_key' );
	}

	public function api(): Client {
		if ( null === $this->api ) {
			$this->api = new Client( $this->secret_key(), $this->publishable_key(), $this->is_test_mode() );
		}
		return $this->api;
	}

	public function init_form_fields(): void {
		// Field keys are kept identical to the legacy plugin so existing settings
		// (option key: woocommerce_thawani_settings) load without any migration.
		$this->form_fields = array(
			'enabled'              => array(
				'title'   => __( 'Enable/Disable', 'thawani' ),
				'label'   => __( 'Enable Thawani Gateway', 'thawani' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'                => array(
				'title'       => __( 'Title', 'thawani' ),
				'type'        => 'text',
				'description' => __( 'Title shown to the customer at checkout.', 'thawani' ),
				'default'     => __( 'Visa or MasterCard', 'thawani' ),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'thawani' ),
				'type'        => 'textarea',
				'description' => __( 'Description shown to the customer at checkout.', 'thawani' ),
				'default'     => __( 'Pay using Visa or MasterCard via Thawani Gateway', 'thawani' ),
			),
			'testmode'             => array(
				'title'       => __( 'Test mode', 'thawani' ),
				'label'       => __( 'Enable Test Mode', 'thawani' ),
				'type'        => 'checkbox',
				'description' => __( 'Use Thawani UAT credentials.', 'thawani' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'debug_mode'           => array(
				'title'       => __( 'Debugging', 'thawani' ),
				'label'       => __( 'Log gateway activity', 'thawani' ),
				'type'        => 'checkbox',
				'description' => __( 'Logs requests/responses to the WooCommerce logger (source: thawani).', 'thawani' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'test_publishable_key' => array(
				'title'   => __( 'UAT Publishable Key', 'thawani' ),
				'type'    => 'text',
				'default' => 'HGvTMLDssJghr9tlN9gr4DVYt0qyBy',
			),
			'test_private_key'     => array(
				'title'   => __( 'UAT Secret Key', 'thawani' ),
				'type'    => 'text',
				'default' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et',
			),
			'publishable_key'      => array(
				'title' => __( 'Production Publishable Key', 'thawani' ),
				'type'  => 'text',
			),
			'private_key'          => array(
				'title' => __( 'Production Secret Key', 'thawani' ),
				'type'  => 'text',
			),
			'use_order_id'         => array(
				'title'       => __( 'Show Order ID at checkout', 'thawani' ),
				'label'       => __( 'Show "Order #XXXXXX" instead of items', 'thawani' ),
				'type'        => 'checkbox',
				'description' => __( 'Avoids per-item rounding issues. Automatically enabled when an item amount drops below 100 baisa.', 'thawani' ),
				'default'     => 'no',
			),
			's4d_save_credit_cards' => array(
				'title'       => __( 'Allow saving cards', 'thawani' ),
				'description' => __( 'Logged-in customers can save and reuse cards (stored on Thawani).', 'thawani' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
			),
		);
	}

	public function payment_fields(): void {
		$description = wpautop( wp_kses_post( (string) $this->description ) );
		echo $description;

		if ( ! $this->save_cards_enabled() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id     = get_current_user_id();
		$customer_id = (string) get_user_meta( $user_id, self::USER_CUSTOMER_ID, true );
		if ( $customer_id === '' ) {
			return;
		}

		$response = $this->api()->get_payment_methods( $customer_id );
		$cards    = array();
		if ( ! empty( $response['body']['data'] ) && is_array( $response['body']['data'] ) ) {
			$cards = $response['body']['data'];
		}

		// Cache as user meta for the duration of checkout (legacy key).
		update_user_meta( $user_id, self::USER_CARDS_CACHE, $cards );

		$nonce = wp_create_nonce( 'thawani_cards' );
		echo '<div id="thawani-cards"><ul id="thawani_payment_card" class="thawani-payment-methods" style="list-style-type:none;" data-nonce="' . esc_attr( $nonce ) . '">';

		printf(
			'<li class="woocommerce-notice"><label><input type="radio" name="thawani_payment_option" value="-1" class="thawani-payment-method"%s> %s</label></li>',
			empty( $cards ) ? ' checked="checked"' : '',
			esc_html__( 'Use new/different card', 'thawani' )
		);

		foreach ( $cards as $i => $card ) {
			$last4 = substr( (string) ( $card['masked_card'] ?? '' ), -4 );
			$nick  = (string) ( $card['nickname'] ?? '' );
			$exp   = sprintf(
				'%s/%s',
				(string) ( $card['expiry_month'] ?? '' ),
				(string) ( $card['expiry_year'] ?? '' )
			);
			printf(
				'<li class="woocommerce-notice"><label><input type="radio" name="thawani_payment_option" value="%1$d" class="thawani-payment-method"%2$s> %3$s %4$s (%5$s)</label> <a href="#" class="thawani-payment-delete" data-id="%1$d" data-card="%6$s">%7$s</a></li>',
				(int) $i,
				$i === 0 ? ' checked="checked"' : '',
				esc_html( $nick ),
				esc_html( $last4 ),
				esc_html( $exp ),
				esc_attr( $nick ),
				esc_html__( 'Delete', 'thawani' )
			);
		}

		echo '</ul></div>';
	}

	public function enqueue_checkout_scripts(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( 'no' === $this->enabled ) {
			return;
		}

		wp_enqueue_script(
			'thawani-checkout',
			WC_GATEWAY_THAWANI_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			WC_GATEWAY_THAWANI_VERSION,
			true
		);

		wp_localize_script(
			'thawani-checkout',
			'thawani',
			array(
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'_delete'         => __( 'Delete', 'thawani' ),
				'_delete_confirm' => __( 'Are you sure you want to delete this card [%s]?', 'thawani' ),
				'_confirm'        => __( 'Confirm', 'thawani' ),
				'_cancel'         => __( 'Cancel', 'thawani' ),
			)
		);
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wc_add_notice( __( 'Order not found.', 'thawani' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$currency       = strtoupper( $order->get_currency() );
		$has_conversion = has_filter( 'thawani_convert_to_omr' );
		if ( $currency !== 'OMR' && ! $has_conversion ) {
			wc_add_notice( __( 'Only Omani Rial is supported.', 'thawani' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$logger = $this->is_debug_mode() ? wc_get_logger() : null;

		$total_baisa  = (int) round( $this->convert_amount( (float) $order->get_total(), $currency ) * 1000 );
		$discount     = (float) $order->get_discount_total();
		$force_single = $this->should_use_order_id() || $discount > 0;
		$items        = $this->build_items( $order, $currency, $total_baisa, $force_single );

		$client_reference_id = wp_rand( 1000, 9999 ) . $order_id;
		$success_url         = add_query_arg(
			array(
				'id'   => $order_id,
				'hash' => md5( (string) $order_id ),
			),
			home_url( '/wc-api/thawani' )
		);

		$customer_id = '';
		if ( $this->save_cards_enabled() ) {
			$customer_id = $this->ensure_customer_id( $order->get_user_id(), $order->get_billing_email() );
		}

		$payload = array(
			'client_reference_id' => $client_reference_id,
			'products'            => $items,
			'success_url'         => $success_url,
			'cancel_url'          => $order->get_checkout_payment_url(),
			'metadata'            => array(
				'order_id'      => $order_id,
				'cart_hash'     => $order->get_cart_hash(),
				'orderKey'      => $order->get_order_key(),
				'customerName'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'customerPhone' => $order->get_billing_phone(),
				'customerEmail' => $this->resolve_email( $order ),
			),
		);

		$payload['metadata'] = array_merge( $payload['metadata'], $this->collect_vendor_meta( $order ) );

		if ( $customer_id !== '' ) {
			$payload['customer_id'] = $customer_id;
		}

		$selected_card = isset( $_POST['thawani_payment_option'] )
			? (int) wc_clean( wp_unslash( $_POST['thawani_payment_option'] ) )
			: -1;

		$pay_with_saved_card = $customer_id !== '' && $selected_card >= 0;

		if ( $this->is_debug_mode() && $logger ) {
			$logger->debug( 'Payment payload: ' . wp_json_encode( $payload ), array( 'source' => 'thawani' ) );
		}

		if ( $pay_with_saved_card ) {
			return $this->process_with_saved_card( $order, $payload, $selected_card, $total_baisa );
		}

		$response = $this->api()->create_checkout_session( $payload );

		if ( ! $response['success'] ) {
			$this->handle_session_error( $order, $response );
			return array( 'result' => 'failure' );
		}

		$session_id = $response['body']['data']['session_id'] ?? '';
		if ( $session_id === '' ) {
			wc_add_notice( __( 'Thawani did not return a session id.', 'thawani' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( self::META_SESSION_ID, $session_id );
		$order->save();

		$this->record_invoice_map( $order, $client_reference_id, $session_id );

		return array(
			'result'   => 'success',
			'redirect' => $this->api()->pay_base_url() . $session_id . '?key=' . $this->publishable_key(),
		);
	}

	private function handle_session_error( WC_Order $order, array $response ): void {
		$body = $response['body'];
		$code = (string) ( $body['code'] ?? '' );

		if ( $code === '4003' && stripos( (string) ( $body['description'] ?? '' ), 'not found' ) !== false ) {
			delete_user_meta( $order->get_user_id(), self::USER_CUSTOMER_ID );
			wc_add_notice( __( 'Saved customer was invalid. Please try again.', 'thawani' ), 'error' );
			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: error message */
				__( 'Payment gateway error: %s', 'thawani' ),
				wp_json_encode( $body )
			),
			'error'
		);
	}

	private function process_with_saved_card( WC_Order $order, array $payload, int $selected_card, int $total_baisa ): array {
		$cards = (array) get_user_meta( $order->get_user_id(), self::USER_CARDS_CACHE, true );
		if ( ! isset( $cards[ $selected_card ]['id'] ) ) {
			wc_add_notice( __( 'Selected card is no longer available.', 'thawani' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$intent_payload = array(
			'client_reference_id' => $payload['client_reference_id'],
			'return_url'          => add_query_arg( 'intent', '1', $payload['success_url'] ),
			'metadata'            => $payload['metadata'],
			'payment_method_id'   => $cards[ $selected_card ]['id'],
			'amount'              => $total_baisa,
		);

		$response = $this->api()->create_payment_intent( $intent_payload );
		if ( ! $response['success'] ) {
			wc_add_notice( __( 'Could not create payment intent.', 'thawani' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$intent_id = $response['body']['data']['id'] ?? '';
		if ( $intent_id === '' ) {
			wc_add_notice( __( 'Thawani did not return an intent id.', 'thawani' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( self::META_INTENT_ID, $intent_id );
		$order->save();

		$confirm = $this->api()->confirm_payment_intent( $intent_id );
		$next    = $confirm['body']['data']['next_action']['url'] ?? '';
		if ( $next === '' ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: error message */
					__( 'Could not confirm payment: %s', 'thawani' ),
					wp_json_encode( $confirm['body'] )
				),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		return array(
			'result'   => 'success',
			'redirect' => $next,
		);
	}

	private function ensure_customer_id( int $user_id, string $fallback_email ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$customer_id = (string) get_user_meta( $user_id, self::USER_CUSTOMER_ID, true );
		if ( $customer_id !== '' ) {
			return $customer_id;
		}

		$user = get_userdata( $user_id );
		$ref  = $user && $user->user_email ? $user->user_email : $fallback_email;
		if ( $ref === '' ) {
			return '';
		}

		$response = $this->api()->create_customer( $ref );
		$id       = (string) ( $response['body']['data']['id'] ?? '' );
		if ( $id !== '' ) {
			update_user_meta( $user_id, self::USER_CUSTOMER_ID, $id );
		}
		return $id;
	}

	private function build_items( WC_Order $order, string $currency, int $total_baisa, bool $force_single ): array {
		if ( $force_single ) {
			return array(
				array(
					'name'        => Client::trim_product_name( 'Order ' . $order->get_id() ),
					'unit_amount' => $total_baisa,
					'quantity'    => 1,
				),
			);
		}

		$items     = array();
		$summed    = 0;
		$has_small = false;

		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$unit_price  = (float) $product->get_price();
			$converted   = $this->convert_amount( $unit_price, $currency );
			$unit_baisa  = (int) ceil( $converted * 1000 );
			$quantity    = (int) $item->get_quantity();

			if ( $unit_baisa < 100 ) {
				$has_small = true;
				break;
			}

			$items[] = array(
				'name'        => Client::trim_product_name( $item->get_name() ),
				'unit_amount' => $unit_baisa,
				'quantity'    => $quantity,
			);
			$summed += $unit_baisa * $quantity;
		}

		if ( $has_small ) {
			return $this->build_items( $order, $currency, $total_baisa, true );
		}

		$remaining = $total_baisa - $summed;
		if ( $remaining > 0 ) {
			if ( $remaining < 100 && ! empty( $items ) ) {
				$last = count( $items ) - 1;
				$items[ $last ]['unit_amount'] += (int) ceil( $remaining / max( 1, $items[ $last ]['quantity'] ) );
			} else {
				$items[] = array(
					'name'        => Client::trim_product_name( __( 'Other', 'thawani' ) ),
					'unit_amount' => $remaining,
					'quantity'    => 1,
				);
			}
		}

		// Validate totals match — fall back to single line item if not.
		$verify = 0;
		foreach ( $items as $item ) {
			$verify += $item['unit_amount'] * $item['quantity'];
		}
		if ( $verify !== $total_baisa ) {
			return $this->build_items( $order, $currency, $total_baisa, true );
		}

		return $items;
	}

	private function convert_amount( float $amount, string $currency ): float {
		if ( has_filter( 'thawani_convert_to_omr' ) ) {
			return (float) apply_filters( 'thawani_convert_to_omr', $amount, $currency );
		}
		return $amount;
	}

	private function resolve_email( WC_Order $order ): string {
		$email = trim( (string) $order->get_billing_email() );
		if ( ! is_email( $email ) ) {
			$host  = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : 'example.com';
			$email = 'noemail@' . $host;
		}
		return $email;
	}

	private function collect_vendor_meta( WC_Order $order ): array {
		$meta = array(
			'vendorID'    => '',
			'vendorName'  => '',
			'vendorOwner' => '',
			'vendorPhone' => '',
		);

		if ( ! function_exists( 'dokan_get_seller_id_by_order' ) ) {
			return $meta;
		}

		$vendor_id = (int) dokan_get_seller_id_by_order( $order->get_id() );
		if ( $vendor_id <= 0 ) {
			return $meta;
		}

		$meta['vendorID']    = (string) $vendor_id;
		$meta['vendorName']  = (string) get_user_meta( $vendor_id, 'dokan_store_name', true );
		$first               = (string) get_user_meta( $vendor_id, 'first_name', true );
		$last                = (string) get_user_meta( $vendor_id, 'last_name', true );
		$meta['vendorOwner'] = trim( $first . ' ' . $last );

		$profile = get_user_meta( $vendor_id, 'dokan_profile_settings', true );
		if ( is_array( $profile ) && isset( $profile['phone'] ) ) {
			$meta['vendorPhone'] = (string) $profile['phone'];
		}

		return $meta;
	}

	private function record_invoice_map( WC_Order $order, string $client_reference_id, string $session_id ): void {
		global $wpdb;

		$wpdb->insert(
			Installer::table_name(),
			array(
				'invoiceid'          => $order->get_id(),
				'thawani_invoiceid'  => (int) $client_reference_id,
				'thawani_payment_id' => $session_id,
				'thawani_amount'     => (float) $order->get_total(),
				'key'                => $order->get_order_key(),
			),
			array( '%d', '%d', '%s', '%f', '%s' )
		);
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return new \WP_Error( 'thawani_refund', __( 'Order not found.', 'thawani' ) );
		}

		$session_id = (string) $order->get_meta( self::META_SESSION_ID );
		if ( $session_id === '' ) {
			return new \WP_Error( 'thawani_refund', __( 'No Thawani session id stored on this order.', 'thawani' ) );
		}

		$response = $this->api()->refund( $session_id, (string) $reason );
		if ( ! $response['success'] ) {
			return new \WP_Error(
				'thawani_refund',
				$response['body']['description'] ?? __( 'Refund failed.', 'thawani' )
			);
		}

		return true;
	}
}
