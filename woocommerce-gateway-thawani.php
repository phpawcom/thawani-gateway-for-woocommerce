<?php
/**
 * Plugin Name:       WooCommerce Thawani Payment Gateway
 * Plugin URI:        https://www.s4d.om/store/software
 * Description:       Accept payments via Thawani (Visa / MasterCard) using API v2.
 * Version:           2.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Tested up to:      6.8
 * Author:            The Source for Development
 * Author URI:        https://www.s4d.om
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-gateway-thawani
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.5
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WC_GATEWAY_THAWANI_FILE' ) ) {
	return;
}

define( 'WC_GATEWAY_THAWANI_FILE', __FILE__ );
define( 'WC_GATEWAY_THAWANI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GATEWAY_THAWANI_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_GATEWAY_THAWANI_VERSION', '2.0.0' );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'S4D\\Thawani\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = WC_GATEWAY_THAWANI_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		\S4D\Thawani\Installer::activate();
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="error"><p>%s</p></div>',
						esc_html__( 'Thawani Payment Gateway requires WooCommerce to be installed and active.', 'woocommerce-gateway-thawani' )
					);
				}
			);
			return;
		}

		\S4D\Thawani\Plugin::instance()->boot();
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);
