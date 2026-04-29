<?php

namespace S4D\Thawani\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Client {

	public const PRODUCTION_BASE = 'https://checkout.thawani.om/api/v1/';
	public const UAT_BASE        = 'https://uatcheckout.thawani.om/api/v1/';

	public const PRODUCTION_PAY = 'https://checkout.thawani.om/pay/';
	public const UAT_PAY        = 'https://uatcheckout.thawani.om/pay/';

	private string $secret_key;

	private string $publishable_key;

	private bool $test_mode;

	private int $timeout = 30;

	public function __construct( string $secret_key, string $publishable_key, bool $test_mode = false ) {
		$this->secret_key      = $secret_key;
		$this->publishable_key = $publishable_key;
		$this->test_mode       = $test_mode;
	}

	public function is_test_mode(): bool {
		return $this->test_mode;
	}

	public function publishable_key(): string {
		return $this->publishable_key;
	}

	public function pay_base_url(): string {
		return $this->test_mode ? self::UAT_PAY : self::PRODUCTION_PAY;
	}

	public function api_base_url(): string {
		return $this->test_mode ? self::UAT_BASE : self::PRODUCTION_BASE;
	}

	public function create_checkout_session( array $payload ) {
		return $this->request( 'POST', 'checkout/session', $payload );
	}

	public function get_checkout_session( string $session_id ) {
		return $this->request( 'GET', 'checkout/session/' . rawurlencode( $session_id ) );
	}

	public function create_customer( string $client_customer_id ) {
		return $this->request( 'POST', 'customers', array( 'client_customer_id' => $client_customer_id ) );
	}

	public function get_payment_methods( string $customer_id ) {
		return $this->request(
			'GET',
			'payment_methods',
			array( 'customerId' => $customer_id )
		);
	}

	public function delete_payment_method( string $token ) {
		return $this->request( 'DELETE', 'payment_methods/' . rawurlencode( $token ) );
	}

	public function create_payment_intent( array $payload ) {
		return $this->request( 'POST', 'payment_intents', $payload );
	}

	public function confirm_payment_intent( string $intent_id ) {
		return $this->request( 'POST', 'payment_intents/' . rawurlencode( $intent_id ) . '/confirm' );
	}

	public function get_payment_intent( string $intent_id ) {
		return $this->request( 'GET', 'payment_intents/' . rawurlencode( $intent_id ) );
	}

	public function list_payments( array $params ) {
		$query = array_filter(
			$params,
			static function ( $v ) {
				return $v !== '' && $v !== null;
			}
		);
		if ( ! isset( $query['limit'] ) ) {
			$query['limit'] = 10;
		}
		if ( ! isset( $query['skip'] ) ) {
			$query['skip'] = 0;
		}
		return $this->request( 'GET', 'payments', $query );
	}

	public function refund( string $payment_id, string $reason = '', array $metadata = array() ) {
		$payload = array(
			'payment_id' => $payment_id,
			'reason'     => $reason !== '' ? $reason : 'Unspecified',
		);
		if ( ! empty( $metadata ) ) {
			$payload['metadata'] = $metadata;
		}
		return $this->request( 'POST', 'refunds', $payload );
	}

	/**
	 * @return array{success: bool, code: int, body: array, raw: array|WP_Error}
	 */
	private function request( string $method, string $path, ?array $payload = null ): array {
		$url = $this->api_base_url() . ltrim( $path, '/' );

		$args = array(
			'method'  => $method,
			'timeout' => $this->timeout,
			'headers' => array(
				'Thawani-Api-Key' => $this->secret_key,
				'Content-Type'    => 'application/json',
				'Accept'          => 'application/json',
			),
		);

		if ( null !== $payload ) {
			if ( $method === 'GET' ) {
				$url = add_query_arg( $payload, $url );
			} else {
				$args['body'] = wp_json_encode( $payload );
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'code'    => 0,
				'body'    => array(),
				'raw'     => $response,
				'error'   => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		return array(
			'success' => $status >= 200 && $status < 300 && ! empty( $body['success'] ),
			'code'    => $status,
			'body'    => $body,
			'raw'     => $response,
		);
	}

	public static function trim_product_name( string $name ): string {
		$name = trim( $name );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $name, 'UTF-8' ) > 40 ) {
			return mb_substr( $name, 0, 37, 'UTF-8' ) . '...';
		}
		return strlen( $name ) > 40 ? substr( $name, 0, 37 ) . '...' : $name;
	}
}
