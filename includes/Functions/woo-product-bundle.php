<?php
/**
 * WPC Product Bundles for WooCommerce integration functions
 *
 * @package Reepay\Checkout\Functions
 */

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'is_active_plugin_woo_product_bundle' ) ) {
	/**
	 * Check if WPC Product Bundles for WooCommerce active.
	 *
	 * @return bool
	 */
	function is_active_plugin_woo_product_bundle(): bool {
		$plugins = get_plugins();

		return array_key_exists( 'woo-product-bundle-premium/wpc-product-bundles.php', $plugins ) || array_key_exists( 'woo-product-bundle/wpc-product-bundles.php', $plugins );
	}
}

if ( ! function_exists( 'is_product_woosb' ) ) {
	/**
	 * Check if product is product woosb
	 *
	 * @param WC_Product $product product woocommerce.
	 *
	 * @return bool
	 */
	function is_product_woosb( WC_Product $product ): bool {
		if ( ! class_exists( 'WC_Product_Woosb' ) ) {
			return false;
		}

		return is_a( $product, 'WC_Product_Woosb' );
	}
}
