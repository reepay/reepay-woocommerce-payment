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
	// Security: Validate GET parameter before use.
	if ( ! is_admin() || ! rp_hpos_enabled() || ! isset( $_GET['id'] ) ) {
		return false;
	}

	// Security: Sanitize and validate order ID.
	$order_id = absint( $_GET['id'] );

	if ( $order_id <= 0 ) {
		return false;
	}

	return (bool) wc_get_order( $order_id );
}
