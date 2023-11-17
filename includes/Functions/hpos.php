<?php
/**
 * WooCommerce High-Performance Order Storage integration functions
 *
 * @package Reepay\Checkout\Functions
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit();

/**
 * Check if HPOS enabled.
 *
 * @return bool
 */
function rp_hpos_enabled(): bool {
	if ( class_exists( OrderUtil::class ) ) {
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}
	return false;
}

/**
 * Check if current page is admin order page
 *
 * @return bool
 */
function rp_hpos_is_order_page(): bool {
	return is_admin() && rp_hpos_enabled() && isset( $_GET['id'] ) && wc_get_order( $_GET['id'] );
}
