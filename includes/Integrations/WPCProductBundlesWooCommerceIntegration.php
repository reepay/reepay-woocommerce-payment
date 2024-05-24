<?php
/**
 * Integration with WPC Product Bundles for WooCommerce plugin https://wordpress.org/plugins/woo-product-bundle/
 *
 * @package Reepay\Checkout\Integrations
 */

namespace Reepay\Checkout\Integrations;

use WC_Order_Item;
use WC_Product;

/**
 * Class integration
 *
 * @package Reepay\Checkout\Integrations
 */
class WPCProductBundlesWooCommerceIntegration {
	public const PRODUCT_CLASS = 'WC_Product_Woosb';

	public const KEY_META_IDS = '_woosb_ids';

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Check if the plugin is activated
	 *
	 * @return bool
	 */
	public static function is_active_plugin(): bool {
		$plugins = get_plugins();

		return array_key_exists( 'woo-product-bundle-premium/wpc-product-bundles.php', $plugins )
				|| array_key_exists( 'woo-product-bundle/wpc-product-bundles.php', $plugins );
	}

	/**
	 * Check if product is product woosb
	 *
	 * @param WC_Product $product product woocommerce.
	 *
	 * @return bool
	 */
	public static function is_product_woosb( WC_Product $product ): bool {
		if ( ! class_exists( self::PRODUCT_CLASS ) ) {
			return false;
		}

		return is_a( $product, self::PRODUCT_CLASS );
	}

	/**
	 * Check if order item is bundle
	 *
	 * @param WC_Order_Item $order_item order item woocommerce.
	 *
	 * @return bool
	 */
	public static function is_order_item_bundle( WC_Order_Item $order_item ): bool {
		return $order_item->meta_exists( self::KEY_META_IDS );
	}
}
