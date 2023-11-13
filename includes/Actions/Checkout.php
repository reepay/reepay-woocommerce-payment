<?php
/**
 * Checkout actions
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Actions;

use WC_Order;
use WC_Order_Item_Product;

class Checkout {
	public function __construct() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'action_checkout_create_order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_cart_needs_payment', array( $this, 'check_need_payment' ), 10 );
	}

	/**
	 * Count line item discount
	 *
	 * @param bool $need_payment need payment marker.
	 *
	 * @see WC_Cart::needs_payment
	 *
	 * @return bool
	 */
	public function check_need_payment( bool $need_payment ): bool {
		if ( wcs_cart_have_subscription() ) {
			return true;
		}

		return $need_payment;
	}

	/**
	 * Count line item discount
	 *
	 * @param WC_Order_Item_Product $item          created order item.
	 * @param string                $cart_item_key order item key in cart.
	 * @param array                 $values        values from cart item.
	 * @param WC_Order              $order         new order.
	 *
	 * @see WC_Checkout::create_order_line_items
	 */
	public function action_checkout_create_order_line_item( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ) {
		$line_discount     = $values['line_subtotal'] - $values['line_total'];
		$line_discount_tax = $values['line_subtotal_tax'] - $values['line_tax'];

		$item->update_meta_data( '_line_discount', $line_discount + $line_discount_tax );
	}
}