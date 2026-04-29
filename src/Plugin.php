<?php

namespace S4D\Thawani;

use S4D\Thawani\Ajax\CardController;
use S4D\Thawani\Blocks\PaymentMethodIntegration;
use S4D\Thawani\Webhook\EventController;
use S4D\Thawani\Webhook\WebhookController;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Installer::maybe_upgrade();

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( THAWANI_FILE ),
			array( $this, 'plugin_action_links' )
		);

		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_integration' ) );

		( new CardController() )->register();
		( new WebhookController() )->register();
		( new EventController() )->register();
	}

	public function register_gateway( array $gateways ): array {
		$gateways[] = Gateway::class;
		return $gateways;
	}

	public function plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=thawani' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'thawani-payment-gateway-for-woocommerce' ) . '</a>'
		);
		return $links;
	}

	public function register_blocks_integration(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new PaymentMethodIntegration() );
			}
		);
	}
}
