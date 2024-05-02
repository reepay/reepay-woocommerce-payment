<?php
/**
 * Integration with PW WooCommerce Gift Cards plugin https://wordpress.org/plugins/pw-woocommerce-gift-cards/
 *
 * @package Reepay\Checkout\Integrations
 */

namespace Reepay\Checkout\Integrations;

use WC_Order;
use WC_Order_Item;
use WC_Order_Item_PW_Gift_Card;

/**
 * Class integration
 *
 * @package Reepay\Checkout\Integrations
 */
class PWGiftCardsIntegration {
	public const KEY_PW_GIFT_ITEMS = 'pw_gift_card';

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Check gift cards in your order
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	public static function check_exist_gift_cards_in_order( WC_Order $order ): bool {
		return count( $order->get_items( self::KEY_PW_GIFT_ITEMS ) ) > 0;
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
		if ( ! class_exists( 'WC_Order_Item_PW_Gift_Card' ) ) {
			return $items;
		}

		/**
		 * Class from PW WooCommerce Gift Cards plugin
		 *
		 * @var WC_Order_Item_PW_Gift_Card $line
		 */
		foreach ( $order->get_items( self::KEY_PW_GIFT_ITEMS ) as $line ) {
			$amount = self::get_negative_amount_from_order_item( $order, $line );

			$items[] = array(
				// translators: gift card code.
				'ordertext'       => sprintf( __( 'PW gift card (%s)', 'reepay-checkout-gateway' ), $line->get_card_number() ),
				'quantity'        => 1,
				'amount'          => rp_prepare_amount( $amount, $order->get_currency() ),
				'vat'             => 0,
				'amount_incl_vat' => $including_vat,
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
		 * Class from PW WooCommerce Gift Cards plugin
		 *
		 * @var WC_Order_Item_PW_Gift_Card $line
		 */
		foreach ( $order->get_items( self::KEY_PW_GIFT_ITEMS ) as $line ) {
			$amount += self::get_amount_from_order_item( $order, $line );
		}

		return $amount;
	}

	/**
	 * Receives the amount if the class WC_Order_Item_PW_Gift_Card is passed
	 *
	 * @param WC_Order                                 $order      Order.
	 * @param WC_Order_Item_PW_Gift_Card|WC_Order_Item $order_item Order item.
	 *
	 * @return float|int
	 */
	public static function get_amount_from_order_item( WC_Order $order, $order_item ) {
		if ( class_exists( 'WC_Order_Item_PW_Gift_Card' ) && is_a( $order_item, 'WC_Order_Item_PW_Gift_Card' ) ) {
			return apply_filters( 'pwgc_to_order_currency', floatval( $order_item->get_amount() ), $order );
		} else {
			return 0;
		}
	}

	/**
	 * Receives the negative amount if the class WC_Order_Item_PW_Gift_Card is passed
	 *
	 * @param WC_Order                                 $order      Order.
	 * @param WC_Order_Item_PW_Gift_Card|WC_Order_Item $order_item Order item.
	 *
	 * @return float|int
	 */
	public static function get_negative_amount_from_order_item( WC_Order $order, $order_item ) {
		return self::get_amount_from_order_item( $order, $order_item ) * - 1;
	}
}
