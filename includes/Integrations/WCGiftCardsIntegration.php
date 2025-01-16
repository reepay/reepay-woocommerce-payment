<?php
/**
 * Integration with Gift Cards for WooCommerce plugin https://woocommerce.com/products/gift-cards/
 *
 * @package Reepay\Checkout\Integrations
 */

namespace Reepay\Checkout\Integrations;

use WC_Order;
use WC_Order_Item;

/**
 * Class integration
 *
 * @package Reepay\Checkout\Integrations
 */
class WCGiftCardsIntegration {
	public const KEY_WC_GIFT_ITEMS = 'gift_card';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_gc_is_redeeming_enabled', array( $this, 'check_is_redeeming_enabled_reepaysubscription' ), 10, 1 );
		add_filter( 'woocommerce_gc_disable_ui', array( $this, 'check_disable_ui_reepaysubscription' ), 10, 1 );
	}

	/**
	 * Hide checkbox for redeeming gift card if we have subscription in cart
	 *
	 * @param bool $enabled         enabled.
	 *
	 * @return bool
	 */
	public function check_is_redeeming_enabled_reepaysubscription( $enabled ) {
		if ( ! class_exists( 'WooCommerce_Reepay_Subscriptions' ) ) {
			return $enabled;
		}
		if ( wcs_cart_have_subscription() ) {
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 * Hide UI for redeeming gift card if we have subscription in cart
	 *
	 * @param bool $disable_ui      disable UI.
	 *
	 * @return bool
	 */
	public function check_disable_ui_reepaysubscription( $disable_ui ) {
		if ( ! class_exists( 'WooCommerce_Reepay_Subscriptions' ) ) {
			return $disable_ui;
		}
		if ( wcs_cart_have_subscription() ) {
			$disable_ui = true;
		}
		return $disable_ui;
	}

	/**
	 * Create items for Reepay payment
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $including_vat If optional custom plan price this parameter tells whether the amount is including VAT.
	 *
	 * @return array
	 */
	public static function get_order_lines_for_reepay( WC_Order $order, bool $including_vat ): array {
		$items = array();
		if ( ! class_exists( 'WC_Gift_Cards' ) ) {
			return $items;
		}

		/**
		 * Class from WooCommerce Gift Cards plugin
		 *
		 * @var WC_GC_Gift_Card_Data $line
		 */
		foreach ( $order->get_items( self::KEY_WC_GIFT_ITEMS ) as $line ) {
			$amount = $line->get_amount() * -1;

			$items[] = array(
				// translators: gift card code.
				'ordertext'       => self::get_name_from_order_item( $order, $line ),
				'quantity'        => 1,
				'amount'          => rp_prepare_amount( $amount, $order->get_currency() ),
				'vat'             => 0,
				'amount_incl_vat' => false,
			);
		}

		return $items;
	}

	/**
	 * Get full amount of gift cards from order
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return int
	 */
	public static function get_amount_gift_cards_from_order( WC_Order $order ): int {
		$amount = 0;

		/**
		 * Class from WooCommerce Gift Cards plugin
		 *
		 * @var WC_GC_Gift_Card_Data $line
		 */
		foreach ( $order->get_items( self::KEY_WC_GIFT_ITEMS ) as $line ) {
			$amount += $line->get_amount();
		}

		return $amount;
	}

	/**
	 * Receives the negative amount
	 *
	 * @param WC_Order                           $order      Order.
	 * @param WC_GC_Gift_Card_Data|WC_Order_Item $order_item Order item.
	 *
	 * @return float|int
	 */
	public static function get_negative_amount_from_order_item( WC_Order $order, $order_item ) {
		return $order_item->get_amount() * - 1;
	}

	/**
	 * Receives the name
	 *
	 * @param WC_Order                           $order      Order.
	 * @param WC_GC_Gift_Card_Data|WC_Order_Item $order_item Order item.
	 *
	 * @return string
	 */
	public static function get_name_from_order_item( WC_Order $order, $order_item ) {
		// translators: %s - gift card code.
		return sprintf( __( 'Gift card (%s)', 'reepay-checkout-gateway' ), $order_item->get_code() );
	}

	/**
	 * Check order have WC giftcard
	 *
	 * @param WC_Order $order      Order.
	 *
	 * @return bool
	 */
	public static function check_order_have_wc_giftcard( WC_Order $order ) {
		$order_have_wc_giftcard = false;
		foreach ( $order->get_items( self::KEY_WC_GIFT_ITEMS ) as $line ) {
			$order_have_wc_giftcard = true;
		}
		return $order_have_wc_giftcard;
	}
}
