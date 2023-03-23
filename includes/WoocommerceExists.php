<?php

namespace Reepay\Checkout;

defined( 'ABSPATH' ) || exit();

/**
 * Class PluginLifeCycle
 *
 * @package Reepay\Checkout
 */
class WoocommerceExists {
	/**
	 * WoocommerceExists constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_deactivate' ), 0 );
	}

	/**
	 * Add notices
	 */
	public function maybe_deactivate() {
		if ( ! self::woo_activated() ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			deactivate_plugins( reepay()->get_setting( 'plugin_basename' ), true );
		}
	}

	/**
	 * Show notice in admin
	 */
	public function missing_woocommerce_notice() {
		reepay()->get_template(
			'admin/notices/woocommerce-missed.php',
			array()
		);
	}

	/**
	 * Check if Woo activated
	 *
	 * @return bool
	 */
	public static function woo_activated() {
		return class_exists( 'WooCommerce', false ) && defined( 'WC_ABSPATH' );
	}
}
