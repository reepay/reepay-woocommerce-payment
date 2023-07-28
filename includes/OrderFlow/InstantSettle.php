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
use WC_Order_Item;
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
	 * OrderCapture instance
	 *
	 * @var OrderCapture
	 */
	private static OrderCapture $order_capture;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'reepay_instant_settle', array( $this, 'maybe_settle_instantly' ), 10, 1 );
	}

	/**
	 * Set order capture instance
	 *
	 * @TODO remove this static method and replace other static methods with non static methods
	 *
	 * @param OrderCapture $order_capture order capture class instance.
	 */
	public static function set_order_capture( OrderCapture $order_capture ) {
		self::$order_capture = $order_capture;
	}

	/**
	 * Maybe settle instantly. Hooked to reepay_instant_settle.
	 *
	 * @param WC_Order $order order to settle payment.
	 */
	public function maybe_settle_instantly( WC_Order $order ) {
		if ( rp_is_order_paid_via_reepay( $order ) ) {
			$this->process_instant_settle( $order );
		}
	}

	/**
	 * Settle a payment instantly.
	 *
	 * @param WC_Order $order order to settle payment.
	 *
	 * @return void
	 */
	public function process_instant_settle( WC_Order $order ) {
		if ( ! empty( $order->get_meta( '_is_instant_settled' ) ) ) {
			return;
		}

		$settle_items = self::get_instant_settle_items( $order );

		$items_data = array();
		$total_all  = 0;

		if ( ! empty( $settle_items ) ) {
			foreach ( $settle_items as $item ) {
				if ( empty( $item->get_meta( 'settled' ) ) ) {
					$item_data = self::$order_capture->get_item_data( $item, $order );
					$total     = $item_data['amount'] * $item_data['quantity'];
					if ( $total <= 0 && method_exists( $item, 'get_product' ) && wcs_is_subscription_product( $item->get_product() ) ) {
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					} elseif ( $total > 0 && self::$order_capture->check_capture_allowed( $order ) ) {
						$items_data[] = $item_data;
						$total_all   += $total;
					}
				}
			}

			self::$order_capture->settle_items( $order, $items_data, $total_all, $settle_items );
			$order->add_meta_data( '_is_instant_settled', '1' );
			$order->save_meta_data();
		}
	}

	/**
	 * Check if product can be settled instantly.
	 *
	 * @param WC_Product|bool $product      if need check the product.
	 *
	 * @return bool
	 * @see ReepayGateway::$settle
	 */
	public static function can_product_be_settled_instantly( $product ): bool {
		if ( empty( $product ) ) {
			return false;
		}

		$settle_types = reepay()->get_setting( 'settle' ) ?: array();

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
	 * @return WC_Order_Item[]
	 */
	public static function get_instant_settle_items( WC_Order $order ): array {
		$settle_types = reepay()->get_setting( 'settle' ) ?: array();
		$items_data   = array();

		// Walk through the order lines and check if order item is virtual, downloadable, recurring or physical.
		foreach ( $order->get_items() as $order_item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $order_item
			 */
			$product = $order_item->get_product();

			if ( self::can_product_be_settled_instantly( $product ) ) {
				$items_data[] = $order_item;
			}
		}

		if ( in_array( self::SETTLE_FEE, $settle_types, true ) ) {
			foreach ( $order->get_fees() as $i => $order_fee ) {
				$items_data[ $i ] = $order_fee;
			}
		}

		if ( in_array( self::SETTLE_PHYSICAL, $settle_types, true ) ) {
			foreach ( $order->get_shipping_methods() as $i => $item_shipping ) {
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
	 * @return array
	 */
	public static function calculate_instant_settle( WC_Order $order ): array {
		$total      = 0;
		$items_data = array();

		$settle_types = reepay()->get_setting( 'settle' ) ?: array();

		// Walk through the order lines and check if order item is virtual, downloadable, recurring or physical.
		foreach ( $order->get_items() as $key => $item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $item
			 */
			$product        = $item->get_product();
			$price_incl_tax = $order->get_line_subtotal( $item, true, false );

			if ( self::can_product_be_settled_instantly( $product ) ) {
				$total       += $price_incl_tax;
				$items_data[] = self::$order_capture->get_item_data( $item, $order );
			}
		}

		// Add Shipping Total.
		if ( in_array( self::SETTLE_PHYSICAL, $settle_types, true ) ) {
			if ( (float) $order->get_shipping_total() > 0 ) {
				$shipping = (float) $order->get_shipping_total();
				$tax      = (float) $order->get_shipping_tax();
				$total   += ( $shipping + $tax );
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
			$total -= $order->get_total_discount( false );

			if ( $total < 0 ) {
				$total = 0;
			}
		}

		return array(
			'settle_amount' => $total,
			'items'         => $items_data,
		);
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
	public static function get_settled_items( WC_Order $order ): array {
		$settled = array();

		foreach ( $order->get_items() as $key => $item ) {
			if ( ! empty( $item->get_meta( 'settled' ) ) ) {
				$settled[ $key ] = self::$order_capture->get_item_data( $item, $order );
			}
		}

		return $settled;
	}
}
