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
	return OrderUtil::custom_orders_table_usage_is_enabled();
}

/**
 * Check if current page is admin order page
 *
 * @return bool
 */
function rp_hpos_is_order_page(): bool {
	return is_admin() && rp_hpos_enabled() && isset( $_GET['id'] ) && 'shop_order' === get_post_type( $_GET['id'] ) && wc_get_order( $_GET['id'] );
}