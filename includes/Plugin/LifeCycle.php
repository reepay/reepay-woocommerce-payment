<?php
/**
 * @package Reepay\Checkout\Plugin
 */

namespace Reepay\Checkout\Plugin;

use UpdateDB;

defined( 'ABSPATH' ) || exit();

/**
 * Class PluginLifeCycle
 *
 * @package Reepay\Checkout\Plugin
 */
class LifeCycle {
	/**
	 * PluginLifeCycle constructor.
	 *
	 * @param string $plugin_path Path to main plugin file. Use __FILE__ const in main file to get it.
	 */
	public function __construct( $plugin_path ) {
		register_activation_hook( $plugin_path, array( $this, 'activation_hook' ) );
		register_deactivation_hook( $plugin_path, array( $this, 'deactivation_hook' ) );
	}

	/**
	 * Plugin activated hook
	 */
	public static function activation_hook() {
		if ( ! get_option( 'woocommerce_reepay_version' ) ) {
			add_option( 'woocommerce_reepay_version', UpdateDB::DB_VERSION );
		}
	}

	/**
	 * Plugin deactivated hook
	 */
	public static function deactivation_hook() {
	}
}
