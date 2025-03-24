<?php
/**
 * PHP_ROUND_HALF_DOWN for discount order tax calculation.
 *
 * @package Reepay\Checkout\Frontend
 */

namespace Reepay\Checkout\Frontend;

use WC_Order;
use WC_Order_Item_Tax;

defined( 'ABSPATH' ) || exit();

/**
 * Class RoundHalfDownDiscount
 *
 * @package Reepay\Checkout\Frontend
 */
class RoundHalfDownDiscount {
	/**
	 * RoundHalfDownDiscount constructor.
	 */
	public function __construct() {
		// Add filters for cart tax calculations
		add_filter( 'woocommerce_tax_round', array( $this, 'custom_tax_round' ), 10, 2 );
		add_filter( 'woocommerce_calculated_total', array( $this, 'custom_calculated_total' ), 10, 2 );
		add_filter( 'woocommerce_get_tax_total', array( $this, 'custom_tax_total' ), 10, 2 );
		add_filter( 'woocommerce_cart_tax_totals', array( $this, 'custom_cart_tax_totals' ), 10, 2 );
		add_filter( 'woocommerce_cart_calculate_fees', array( $this, 'custom_cart_calculate_fees' ), 10, 1 );

		// Add filters for order tax calculations
		add_filter( 'woocommerce_order_get_tax_totals', array( $this, 'custom_order_tax_totals' ), 10, 2 );
		add_filter( 'woocommerce_order_amount_tax_total', array( $this, 'custom_order_tax_amount' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_tax_item', array( $this, 'custom_checkout_create_order_tax_item' ), 10, 3 );
		add_filter( 'woocommerce_order_get_total_tax', array( $this, 'custom_order_total_tax' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'custom_checkout_create_order' ), 10, 2 );
	}

	public function has_discount() {
		return isset( WC()->cart ) && WC()->cart->get_discount_total() > 0;
	}

	public function custom_tax_round( $value, $in_cents = true ) {
		if ( $this->has_discount() ) {
			if ( $in_cents ) {
				return round( $value, 0, PHP_ROUND_HALF_DOWN );
			}
			return round( $value, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
		}
		return $value;
	}

	public function custom_calculated_total( $total, $cart ) {
		if ( $this->has_discount() ) {
			// Round down the total including tax
			return round( $total, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
		}
		return $total;
	}

	public function custom_tax_total( $tax_amount, $tax_rate_id ) {
		if ( $this->has_discount() ) {
			// Round down individual tax amounts
			return round( $tax_amount, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
		}
		return $tax_amount;
	}

	public function custom_cart_tax_totals( $tax_totals, $cart ) {
		if ( $this->has_discount() ) {
			foreach ( $tax_totals as &$tax_total ) {
				// Round down tax totals
				$tax_total->amount           = round( $tax_total->amount, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
				$tax_total->formatted_amount = wc_price( $tax_total->amount );
			}
		}
		return $tax_totals;
	}

	public function has_order_discount( $order ) {
		return $order && $order->get_discount_total() > 0;
	}

	public function custom_order_tax_totals( $tax_totals, $order ) {
		if ( $this->has_order_discount( $order ) ) {
			foreach ( $tax_totals as &$tax_total ) {
				$tax_total->amount           = round( $tax_total->amount, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
				$tax_total->formatted_amount = wc_price( $tax_total->amount );
			}
		}
		return $tax_totals;
	}

	public function custom_order_tax_amount( $tax_total, $order ) {
		if ( $this->has_order_discount( $order ) ) {
			return round( $tax_total, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
		}
		return $tax_total;
	}

	/**
	 * Handle tax rounding when creating order tax items
	 */
	public function custom_checkout_create_order_tax_item( $item, $tax_rate_id, $order ) {
		if ( $this->has_order_discount( $order ) ) {
			$tax_amount     = $item->get_tax_total();
			$rounded_amount = round( $tax_amount, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
			$item->set_tax_total( $rounded_amount );

			// Handle shipping tax if exists
			$shipping_tax_amount = $item->get_shipping_tax_total();
			if ( $shipping_tax_amount > 0 ) {
				$rounded_shipping_tax = round( $shipping_tax_amount, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
				$item->set_shipping_tax_total( $rounded_shipping_tax );
			}
		}
	}

	/**
	 * Handle total tax amount for order
	 */
	public function custom_order_total_tax( $tax_total, $order ) {
		if ( $this->has_order_discount( $order ) ) {
			return round( $tax_total, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
		}
		return $tax_total;
	}

	/**
	 * Recalculate order totals after tax items are created and rounded.
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $data  Posted data.
	 */
	public function custom_checkout_create_order( $order, $data ) {
		if ( $this->has_order_discount( $order ) ) {
			// Recalculate totals with rounded values
			$order->calculate_totals();
		}
	}

	/**
	 * Apply discount tax.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function custom_cart_calculate_fees( $cart ) {
		if ( $this->has_discount() ) {
			$discount_total = $cart->get_discount_total();
			$discount_tax   = 0;

			if ( $discount_total > 0 ) {
				$tax_rates = \WC_Tax::find_rates(
					array(
						'country'   => WC()->customer->get_billing_country(),
						'state'     => WC()->customer->get_billing_state(),
						'postcode'  => WC()->customer->get_billing_postcode(),
						'city'      => WC()->customer->get_billing_city(),
						'tax_class' => '',
					)
				);

				$discount_tax_amounts = \WC_Tax::calc_tax( $discount_total, $tax_rates, false );
				$discount_tax         = array_sum( $discount_tax_amounts );
				$discount_tax         = round( $discount_tax, wc_get_price_decimals(), PHP_ROUND_HALF_DOWN );
			}

			// Add discount tax to cart fees
			if ( $discount_tax > 0 ) {
				$cart->add_fee( 'Discount Tax', $discount_tax * -1, true, '' );
			}
		}
	}
}
