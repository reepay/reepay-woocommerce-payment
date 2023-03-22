<?php

defined( 'ABSPATH' ) || exit();

class WC_Reepay_Instant_Settle {
	use WC_Reepay_Log;

	/**
	 * @var string
	 */
	private $logging_source = 'reepay-instant-checkout';

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
	 * Maybe settle instantly.
	 *
	 * @param WC_Order $order
	 */
	public function maybe_settle_instantly( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! reepay()->is_reepay_payment_method( $order->get_payment_method() ) ) {
			return;
		}

		$this->process_instant_settle( $order );
	}

	/**
	 * Settle a payment instantly.
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function process_instant_settle( $order ) {
		$WC_Reepay_Order_Capture = self::get_order_capture();

		if ( ! empty( $order->get_meta( '_is_instant_settled' ) ) ) {
			return;
		}

		$settle_items = self::get_items_to_settle( $order );

		if ( ! empty( $settle_items ) ) {
			foreach ( $settle_items as $item ) {
				$WC_Reepay_Order_Capture->settle_item( $item, $order );
			}
			$order->add_meta_data( '_is_instant_settled', '1' );
			$order->save_meta_data();
		}
	}


	public static function get_instant_items( $order ) {

		/** @var array $settle */
		$settle     = rp_get_payment_method( $order )->settle;
		$items_data = array();

		// Now walk through the order-lines and check per order if it is virtual, downloadable, recurring or physical
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $order_item */
			/** @var WC_Product $product */
			$product = $item->get_product();

			if ( in_array( self::SETTLE_PHYSICAL, $settle, true ) &&
				 ( ! wcs_is_subscription_product( $product ) &&
				   $product->needs_shipping() &&
				   ! $product->is_downloadable() )
			) {
				$items_data[] = $item->get_id();
				continue;
			} elseif ( in_array( self::SETTLE_VIRTUAL, $settle, true ) &&
					   ( ! wcs_is_subscription_product( $product ) &&
						 ( $product->is_virtual() || $product->is_downloadable() ) )
			) {
				$items_data[] = $item->get_id();
				continue;
			} elseif ( in_array( self::SETTLE_RECURRING, $settle, true ) &&
					   wcs_is_subscription_product( $product )
			) {
				$items_data[] = $item->get_id();
			}
		}

		return $items_data;
	}

	public static function get_items_to_settle( $order ) {
		$settle = rp_get_payment_method( $order )->settle;

		$items_data = array();

		// Now walk through the order-lines and check per order if it is virtual, downloadable, recurring or physical
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $order_item */
			/** @var WC_Product $product */
			$product = $item->get_product();

			if ( in_array( self::SETTLE_PHYSICAL, $settle, true ) &&
				 ( ! wcs_is_subscription_product( $product ) &&
				   $product->needs_shipping() &&
				   ! $product->is_downloadable() )
			) {
				$items_data[] = $item;

				continue;
			} elseif ( in_array( self::SETTLE_VIRTUAL, $settle, true ) &&
					   ( ! wcs_is_subscription_product( $product ) &&
						 ( $product->is_virtual() || $product->is_downloadable() ) )
			) {
				$items_data[] = $item;

				continue;
			} elseif ( in_array( self::SETTLE_RECURRING, $settle, true ) &&
					   wcs_is_subscription_product( $product )
			) {
				$items_data[] = $item;
			}
		}

		if ( in_array( self::SETTLE_FEE, $settle ) ) {
			foreach ( $order->get_fees() as $i => $order_fee ) {
				/** @var WC_Order_Item_Fee $order_fee */
				$items_data[ $i ] = $order_fee;
			}
		}

		// Add Shipping Total
		if ( in_array( self::SETTLE_PHYSICAL, $settle ) ) {
			foreach ( $order->get_items( 'shipping' ) as $i => $item_shipping ) {
				$items_data[ $i ] = $item_shipping;
			}
		}

		return $items_data;
	}

	/**
	 * Calculate the amount to be settled instantly based by the order items.
	 *
	 * @param WC_Order $order - is the WooCommerce order object
	 *
	 * @return stdClass
	 */
	public static function calculate_instant_settle( $order ) {
		$WC_Reepay_Order_Capture = self::get_order_capture();

		$online_virtual = false;
		$recurring      = false;
		$physical       = false;
		$total          = 0;
		$debug          = array();
		$items_data     = array();

		/** @var array $settle */
		$settle = rp_get_payment_method( $order )->settle;

		// Now walk through the order-lines and check per order if it is virtual, downloadable, recurring or physical
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			/** @var WC_Product $product */
			$product        = $item->get_product();
			$price_incl_tax = $order->get_line_subtotal( $item, true, false );

			if ( in_array( self::SETTLE_PHYSICAL, $settle, true ) &&
				 ( ! wcs_is_subscription_product( $product ) &&
				   $product->needs_shipping() &&
				   ! $product->is_downloadable() )
			) {
				$debug[] = array(
					'product' => $product->get_id(),
					'name'    => $item->get_name(),
					'price'   => $price_incl_tax,
					'type'    => self::SETTLE_PHYSICAL,
				);

				$physical     = true;
				$total       += $price_incl_tax;
				$items_data[] = $WC_Reepay_Order_Capture->get_item_data( $item, $order );

				continue;
			} elseif ( in_array( self::SETTLE_VIRTUAL, $settle, true ) &&
					   ( ! wcs_is_subscription_product( $product ) &&
						 ( $product->is_virtual() || $product->is_downloadable() ) )
			) {
				$debug[] = array(
					'product' => $product->get_id(),
					'name'    => $item->get_name(),
					'price'   => $price_incl_tax,
					'type'    => self::SETTLE_VIRTUAL,
				);

				$online_virtual = true;
				$total         += $price_incl_tax;
				$items_data[]   = $WC_Reepay_Order_Capture->get_item_data( $item, $order );

				continue;
			} elseif ( in_array( self::SETTLE_RECURRING, $settle, true ) &&
					   wcs_is_subscription_product( $product )
			) {
				$debug[] = array(
					'product' => $product->get_id(),
					'name'    => $item->get_name(),
					'price'   => $price_incl_tax,
					'type'    => self::SETTLE_RECURRING,
				);

				$recurring    = true;
				$total       += $price_incl_tax;
				$items_data[] = $WC_Reepay_Order_Capture->get_item_data( $item, $order );
			}
		}

		// Add Shipping Total
		if ( in_array( self::SETTLE_PHYSICAL, $settle ) ) {
			if ( (float) $order->get_shipping_total() > 0 ) {
				$shipping = (float) $order->get_shipping_total();
				$tax      = (float) $order->get_shipping_tax();
				$total   += ( $shipping + $tax );

				$debug[] = array(
					'product' => $order->get_shipping_method(),
					'price'   => ( $shipping + $tax ),
					'type'    => self::SETTLE_PHYSICAL,
				);

				$physical = true;
			}
		}

		// Add fees
		if ( in_array( self::SETTLE_FEE, $settle ) ) {
			foreach ( $order->get_fees() as $order_fee ) {
				/** @var WC_Order_Item_Fee $order_fee */
				$fee    = (float) $order_fee->get_total();
				$tax    = (float) $order_fee->get_total_tax();
				$total += ( $fee + $tax );

				$debug[] = array(
					'product' => $order_fee->get_name(),
					'price'   => ( $fee + $tax ),
					'type'    => self::SETTLE_FEE,
				);
			}
		}

		// Add discounts
		if ( $order->get_total_discount( false ) > 0 ) {
			$discountWithTax = $order->get_total_discount( false );
			$total          -= $discountWithTax;

			$debug[] = array(
				'product' => 'discount',
				'price'   => - 1 * $discountWithTax,
				'type'    => 'discount',
			);

			if ( $total < 0 ) {
				$total = 0;
			}
		}

		$result                    = new stdClass();
		$result->is_instant_settle = $online_virtual || $physical || $recurring;
		$result->settle_amount     = $total;
		$result->items             = $items_data;
		$result->debug             = $debug;
		$result->settings          = $settle;

		return $result;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array<array>
	 */
	public static function get_settled_items( $order ) {
		$WC_Reepay_Order_Capture = self::get_order_capture();

		$settled = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! empty( $item->get_meta( 'settled' ) ) ) {
				$settled[] = $WC_Reepay_Order_Capture->get_item_data( $item, $order );
			}
		}

		return $settled;
	}

	/**
	 * @return WC_Reepay_Order_Capture
	 */
	public static function get_order_capture() {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new WC_Reepay_Order_Capture();
		}

		return $instance;
	}
}

new WC_Reepay_Instant_Settle();
