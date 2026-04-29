<?php

namespace S4D\Thawani\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use S4D\Thawani\Gateway;

defined( 'ABSPATH' ) || exit;

final class PaymentMethodIntegration extends AbstractPaymentMethodType {

	protected $name = Gateway::GATEWAY_ID;

	public function initialize() {
		// Same option key the legacy plugin used.
		$this->settings = (array) get_option( 'woocommerce_thawani_settings', array() );
	}

	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	public function get_payment_method_script_handles() {
		$handle    = 'wc-thawani-blocks';
		$asset_path = THAWANI_PATH . 'assets/js/blocks.asset.php';
		$asset      = is_readable( $asset_path ) ? require $asset_path : array(
			'dependencies' => array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			'version'      => THAWANI_VERSION,
		);

		wp_register_script(
			$handle,
			THAWANI_URL . 'assets/js/blocks.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'thawani-payment-gateway-for-woocommerce', THAWANI_PATH . 'languages' );
		}

		return array( $handle );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'Visa or MasterCard', 'thawani-payment-gateway-for-woocommerce' ) ),
			'description' => $this->get_setting( 'description', __( 'Pay using Visa or MasterCard via Thawani Gateway', 'thawani-payment-gateway-for-woocommerce' ) ),
			'supports'    => $this->get_supported_features(),
			'testmode'    => filter_var( $this->get_setting( 'testmode', false ), FILTER_VALIDATE_BOOLEAN ),
		);
	}

	public function get_supported_features() {
		return array( 'products' );
	}
}
