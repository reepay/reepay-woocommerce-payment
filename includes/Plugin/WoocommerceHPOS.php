<?php
/**
 * Class with enable High-Performance order storage support
 *
 * @package Reepay\Checkout\Plugin
 */

namespace Reepay\Checkout\Plugin;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WooCommerce;

defined( 'ABSPATH' ) || exit();

/**
 * Class WoocommerceHPOS
 *
 * @package Reepay\Checkout\Plugin
 */
class WoocommerceHPOS {
	/**
	 * WoocommerceHPOS constructor
	 */
	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'add_support' ), 0 );
	}

	/**
	 * Add notices
	 */
	public function add_support() {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', reepay()->get_setting( 'plugin_file' ), true );
		}
	}
}
