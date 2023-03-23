<?php

namespace Reepay\Checkout;

use WC_ReepayCheckout;

defined( 'ABSPATH' ) || exit();

/**
 * Class PluginLifeCycle
 *
 * @package Reepay\Checkout
 */
class PluginLifeCycle {
	/**
	 * PluginLifeCycle constructor.
	 *
	 * @param  string $plugin_path  Path to main plugin file. Use __FILE__ const in main file to get it
	 */
	public function __construct( $plugin_path ) {
		register_activation_hook( $plugin_path, array( $this, 'activation_hook' ) );
		register_deactivation_hook( $plugin_path, array( $this, 'deactivation_hook' ) );
		// register_uninstall_hook( $plugin_path, array( $this, 'uninstall_hook' ) ); //It's better to use the uninstall.php file at root of plugin
	}

	/**
	 * Plugin activated hook
	 */
	public static function activation_hook() {
		if ( ! get_option( 'woocommerce_reepay_version' ) ) {
			add_option( 'woocommerce_reepay_version', WC_ReepayCheckout::DB_VERSION );
		}
	}

	/**
	 * Plugin deactivated hook
	 */
	public static function deactivation_hook() {
	}
}
