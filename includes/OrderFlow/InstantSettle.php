<?php
/**
 * Instant settle logic
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Reepay\Checkout\Gateways\ReepayGateway;
use stdClass;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Product;

defined( 'ABSPATH' ) || exit();

/**
 * Class InstantSettle
 *
 * @package Reepay\Checkout\OrderFlow
 */
class InstantSettle {

	/**
	 * Settle Type options
	 */
	const SETTLE_VIRTUAL   = 'online_virtual';
	const SETTLE_PHYSICAL  = 'physical';
	const SETTLE_RECURRING = 'recurring';
	const SETTLE_FEE       = 'fee';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'reepay_instant_settle', array( $this, 'maybe_settle_instantly' ), 10, 1 );
	}

	/**
	 * Maybe settle instantly. Hooked to reepay_instant_settle.
	 *
	 * @param WC_Order $order order to settle payment.
	 */
	public function maybe_settle_instantly( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$this->process_instant_settle( $order );
	}

	/**
	 * Settle a payment instantly.
	 *
	 * @param WC_Order $order order to settle payment.
	 *
	 * @return void
	 */
	public function process_instant_settle( $order ) {
		$order_capture = OrderCapture::get_instance();

		if ( ! empty( $order->get_meta( '_is_instant_settled' ) ) ) {
			return;
		}

		$settle_items = self::get_instant_settle_items( $order );

		if ( ! empty( $settle_items ) ) {
			foreach ( $settle_items as $item ) {
				$order_capture->settle_item( $item, $order );
			}
			$order->add_meta_data( '_is_instant_settled', '1' );
			$order->save_meta_data();
		}
	}

	/**
	 * Check if product can be settled instantly.
	 *
	 * @param WC_Product $product if need check the product.
	 * @param string[]   $settle_types settle types.
	 *
	 * @see ReepayGateway::$settle
	 *
	 * @return bool
	 */
	public static function can_product_be_settled_instantly( $product, $settle_types ) {
		if ( in_array( self::SETTLE_PHYSICAL, $settle_types, true ) &&
			 ( ! wcs_is_subscription_product( $product ) &&
			   $product->needs_shipping() &&
			   ! $product->is_downloadable() )
		) {
			return true;
		} elseif ( in_array( self::SETTLE_VIRTUAL, $settle_types, true ) &&
				   ( ! wcs_is_subscription_product( $product ) &&
					 ( $product->is_virtual() || $product->is_downloadable() ) )
		) {
			return true;
		} elseif ( in_array( self::SETTLE_RECURRING, $settle_types, true ) &&
				   wcs_is_subscription_product( $product )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Get items from order which can be captured instantly.
	 *
	 * @param WC_Order $order order to get items.
	 *
	 * @return array
	 */
	public static function get_instant_settle_items( $order ) {
		$settle_types = rp_get_payment_method( $order )->settle ?: array();
		$items_data   = array();

		// Walk through the order lines and check if order item is virtual, downloadable, recurring or physical.
		foreach ( $order->get_items() as $order_item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $order_item
			 */
			$product = $order_item->get_product();

			if ( self::can_product_be_settled_instantly( $product, $settle_types ) ) {
				$items_data[] = $order_item;
			}
		}

		if ( in_array( self::SETTLE_FEE, $settle_types, true ) ) {
			foreach ( $order->get_fees() as $i => $order_fee ) {
				$items_data[ $i ] = $order_fee;
			}
		}

		if ( in_array( self::SETTLE_PHYSICAL, $settle_types, true ) ) {
			foreach ( $order->get_items( 'shipping' ) as $i => $item_shipping ) {
				$items_data[ $i ] = $item_shipping;
			}
		}

		return $items_data;
	}

	/**
	 * Calculate the amount to be settled instantly based by the order items.
	 *
	 * @param WC_Order $order order to calculate settle amount.
	 *
	 * @return stdClass
	 */
	public static function calculate_instant_settle( $order ) {
		$is_instant_settle = false;
		$delivery          = false;
		$total             = 0;
		$items_data        = array();

		$settle_types = rp_get_payment_method( $order )->settle ?: array();

		// Walk through the order lines and check if order item is virtual, downloadable, recurring or physical.
		foreach ( $order->get_items() as $item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $item
			 */
			$product        = $item->get_product();
			$price_incl_tax = $order->get_line_subtotal( $item, true, false );

			if ( self::can_product_be_settled_instantly( $product, $settle_types ) ) {
				$is_instant_settle = true;
				$total            += $price_incl_tax;
				$items_data[]      = OrderCapture::get_instance()->get_item_data( $item, $order );
			}
		}

		// Add Shipping Total.
		if ( in_array( self::SETTLE_PHYSICAL, $settle_types, true ) ) {
			if ( (float) $order->get_shipping_total() > 0 ) {
				$shipping = (float) $order->get_shipping_total();
				$tax      = (float) $order->get_shipping_tax();
				$total   += ( $shipping + $tax );
				$delivery = true;
			}
		}

		// Add fees.
		if ( in_array( self::SETTLE_FEE, $settle_types, true ) ) {
			foreach ( $order->get_fees() as $order_fee ) {
				$fee    = (float) $order_fee->get_total();
				$tax    = (float) $order_fee->get_total_tax();
				$total += ( $fee + $tax );
			}
		}

		// Add discounts.
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount_with_tax = $order->get_total_discount( false );
			$total            -= $discount_with_tax;

			if ( $total < 0 ) {
				$total = 0;
			}
		}

		$result                    = new stdClass();
		$result->is_instant_settle = $is_instant_settle || $delivery;
		$result->settle_amount     = $total;
		$result->items             = $items_data;
		$result->settings          = $settle_types;

		return $result;
	}

	/**
	 * Get already settled order items.
	 *
	 * @param WC_Order $order order to check settled order items.
	 *
	 * @return array<array>
	 *
	 * @see OrderCapture::get_item_data
	 */
	public static function get_settled_items( $order ) {
		$settled = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! empty( $item->get_meta( 'settled' ) ) ) {
				$settled[] = OrderCapture::get_instance()->get_item_data( $item, $order );
			}
		}

		return $settled;
	}
}
