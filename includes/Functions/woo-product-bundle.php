<?php
/**
 * WPC Product Bundles for WooCommerce integration functions
 *
 * @package Reepay\Checkout\Functions
 */

defined( 'ABSPATH' ) || exit();

/**
 * Check if WPC Product Bundles for WooCommerce active.
 *
 * @return bool
 */
function is_active_plugin_woo_product_bundle(): bool {
	$plugins = get_plugins();

	return array_key_exists( 'woo-product-bundle-premium/wpc-product-bundles.php', $plugins ) || array_key_exists( 'woo-product-bundle/wpc-product-bundles.php', $plugins );
}
