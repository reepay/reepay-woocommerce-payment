<?php
/**
 * Order functions
 *
 * @package Reepay\Checkout\Functions
 */

use Reepay\Checkout\Utils\TimeKeeper;
use WC_Reepay_Renewals as WCRR;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_get_order_handle' ) ) {
	/**
	 * Get from meta or create reepay order handle.
	 *
	 * @param WC_Order $order  order to get handle.
	 * @param bool     $unique if true create new handle with timestamp.
	 *
	 * @return string
	 */
	function rp_get_order_handle( WC_Order $order, bool $unique = false ): ?string {
		$current_time = TimeKeeper::get();
		if ( $unique ) {
			$handle = null;
			$order->delete_meta_data( '_reepay_order' );
		} else {
			$handle = $order->get_meta( '_reepay_order' );
		}

		if ( empty( $handle ) ) {
			$prefix = reepay()->get_setting( 'order_handle_prefix' );

			if ( empty( $prefix ) ) {
				$prefix = '';
			}

			if ( $unique ) {
				$handle = $prefix . $order->get_order_number() . '-' . $current_time;
			} else {
				$handle = $prefix . $order->get_order_number();
			}

			$order->add_meta_data( '_reepay_order', $handle );

			$order->save_meta_data();
		}

		return $handle;
	}
}

if ( ! function_exists( 'rp_get_order_by_handle' ) ) {
	/**
	 * Get order by reepay order handle.
	 *
	 * @param string $handle reepay order handle.
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_handle( string $handle ) {

		if ( rp_hpos_enabled() ) {
			$orders = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_reepay_order',
							'value' => $handle,
						),

					),
				)
			);
		} else {
			$orders = wc_get_orders(
				array(
					'limit'        => 1,
					'meta_key'     => '_reepay_order', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $handle, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_compare' => '=',
				)
			);
		}

		if ( ! empty( $orders ) ) {
			$order_id = reset( $orders )->get_id();
			clean_post_cache( $order_id );

			return wc_get_order( $order_id );
		}

		return false;
	}
}

if ( ! function_exists( 'rp_get_not_subs_order_by_handle' ) ) {
	/**
	 * Get not subscription order by reepay order handle.
	 *
	 * @param string $handle reepay order handle.
	 *
	 * @return false|WC_Order
	 */
	function rp_get_not_subs_order_by_handle( string $handle ) {

		if ( rp_hpos_enabled() ) {
			$orders = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_reepay_order',
							'value' => $handle,
						),
						array(
							'key'     => '_reepay_is_subscription',
							'compare' => 'NOT EXISTS',
						),

					),
				)
			);
		} else {
			$orders = wc_get_orders(
				array(
					'limit'        => 1,
					'meta_key'     => '_reepay_order', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $handle, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_compare' => '=',
					'meta_query'   => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_reepay_is_subscription',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
		}

		if ( ! empty( $orders ) ) {
			$order_id = reset( $orders )->get_id();
			clean_post_cache( $order_id );

			return wc_get_order( $order_id );
		}

		return false;
	}
}

if ( ! function_exists( 'rp_get_order_by_session' ) ) {
	/**
	 * Get order by reepay order session.
	 *
	 * @param string $session_id reepay order session.
	 * @param string $handle reepay order handle.
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_session( ?string $session_id = null, ?string $handle = null ) {
		if ( ! is_null( $session_id ) ) {
			if ( rp_hpos_enabled() ) {
				$orders = wc_get_orders(
					array(
						'limit'      => 1,
						'meta_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => 'reepay_session_id',
								'value' => $session_id,
							),

						),
					)
				);
			} else {
				$orders = wc_get_orders(
					array(
						'limit'        => 1,
						'meta_key'     => 'reepay_session_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'   => $session_id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'meta_compare' => '=',
					)
				);
			}

			if ( ! empty( $orders ) ) {
				$order_id = reset( $orders )->get_id();
				clean_post_cache( $order_id );
				return wc_get_order( $order_id );
			} elseif ( ! is_null( $handle ) ) {
				$order = rp_get_order_by_customer( $handle );
				return $order;
			}

			return false;
		} elseif ( ! is_null( $handle ) ) {
			$order = rp_get_order_by_customer( $handle );
			return $order;
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'rp_get_order_by_customer' ) ) {
	/**
	 * Get order by reepay order customer.
	 *
	 * @param string $customer_id reepay order customer.
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_customer( string $customer_id ) {
		$reepay_list_invoice = reepay()->api( 'list-invoice' )->request( 'GET', "https://api.reepay.com/v1/list/invoice?customer=$customer_id" );

		if ( is_wp_error( $reepay_list_invoice ) || empty( $reepay_list_invoice['content'] ) ) {
			return '';
		}
		$subscription_order = null;
		foreach ( $reepay_list_invoice['content'] as $content ) {
			$search_string = strpos( $content['handle'], 'order-' );
			if ( false !== $search_string ) {
				$handle = $content['handle'];
				if ( rp_hpos_enabled() ) {
					$orders = wc_get_orders(
						array(
							'limit'      => 1,
							'meta_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
								array(
									'key'   => '_reepay_order',
									'value' => $handle,
								),

							),
						)
					);
				} else {
					$orders = wc_get_orders(
						array(
							'limit'        => 1,
							'meta_key'     => '_reepay_order', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value'   => $handle, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value	
							'meta_compare' => '=',
						)
					);
				}

				if ( ! empty( $orders ) ) {
					$order_id = reset( $orders )->get_id();
					$order    = wc_get_order( $order_id );
					if ( class_exists( WCRR::class ) && WCRR::is_order_contain_subscription( $order ) || order_contains_subscription( $order ) ) {
						$subscription_order = $order;
					}
				}
			}
		}
		if ( null !== $subscription_order ) {
			return $subscription_order;
		} else {
			return '';
		}
	}
}

if ( ! function_exists( 'rp_is_order_paid_via_reepay' ) ) {
	/**
	 * Check if payment method is reepay payment method
	 *
	 * @param WC_Order $order order to check.
	 */
	function rp_is_order_paid_via_reepay( WC_Order $order ): bool {
		return rp_is_reepay_payment_method( $order->get_payment_method() );
	}
}

if ( ! function_exists( 'rp_get_order_vat_number' ) ) {
	/**
	 * Get the customer VAT/CVR number stored on the order by a supported VAT plugin.
	 *
	 * Checks `_billing_eu_vat_number` (EU/UK VAT Validation Manager for WooCommerce)
	 * first, then falls back to `_billing_vat_number` (legacy EU VAT Number for
	 * WooCommerce). Returns an empty string if neither meta is set.
	 *
	 * @param WC_Order $order order to read VAT meta from.
	 *
	 * @return string
	 */
	function rp_get_order_vat_number( WC_Order $order ): string {
		$vat = $order->get_meta( '_billing_eu_vat_number' );

		if ( empty( $vat ) ) {
			$vat = $order->get_meta( '_billing_vat_number' );
		}

		return $vat ? sanitize_text_field( trim( $vat ) ) : '';
	}
}

if ( ! function_exists( 'rp_get_discount_tax_percent' ) ) {
	/**
	 * Get the VAT percent to send to Reepay/Frisbii for an order's synthetic "Discount" line.
	 *
	 * WooCommerce only exposes the discount total excl. tax and incl. tax already
	 * rounded to the order currency's minor unit (e.g. 22.90 / 28.62). Reconstructing
	 * the VAT rate as `tax / discount * 100` from those rounded figures amplifies
	 * their rounding error (a 0.005 kr tax rounding can shift the recovered rate by
	 * several hundredths of a percent), which in turn makes Reepay/Frisbii settle the
	 * discount line for a different amount than WooCommerce's own total. Prefer the
	 * order's actual configured tax rate when it is unambiguous (exactly one distinct
	 * rate applied), falling back to the historical ratio-based estimate otherwise
	 * (e.g. orders mixing multiple tax classes).
	 *
	 * @param WC_Order $order             order the discount belongs to.
	 * @param float    $discount          discount total excl. tax.
	 * @param float    $discount_with_tax discount total incl. tax.
	 *
	 * @return float VAT percent, e.g. 25 for 25%.
	 */
	function rp_get_discount_tax_percent( WC_Order $order, float $discount, float $discount_with_tax ): float {
		$rates = array();

		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			/**
			 * Order tax line item.
			 *
			 * @var WC_Order_Item_Tax $tax_item
			 */
			$rate = $tax_item->get_rate_percent();

			if ( '' !== $rate && null !== $rate ) {
				$rates[ (string) $rate ] = true;
			}
		}

		if ( 1 === count( $rates ) ) {
			return (float) key( $rates );
		}

		$tax = $discount_with_tax - $discount;

		return ( $tax > 0 && $discount > 0 ) ? round( 100 / ( $discount / $tax ), 2 ) : 0;
	}
}

if ( ! function_exists( 'rp_reconcile_discount_amount' ) ) {
	/**
	 * Nudge the "Discount" line amount (minor currency units) to whatever makes the
	 * itemized lines sum to WooCommerce's own order total, but only when that nudge is
	 * exactly the ±1 minor unit a half-minor-unit tax rounding boundary can introduce
	 * (e.g. a 10% discount at 25% VAT: 22.90 * 0.25 = 5.725 kr, an amount with no single
	 * "correct" rounding). WooCommerce's own `get_total_discount()` /
	 * `get_total_discount( false )` round that case independently of how the order's
	 * grand total was originally computed, so the directly-calculated discount amount
	 * can end up 1 minor unit off from what's needed to reconcile.
	 *
	 * A larger mismatch means `$items_so_far` doesn't represent the whole order (e.g. a
	 * partial settle, or items excluded by settle-type settings) and `get_total()` is
	 * not a safe reconciliation target — in that case the direct calculation is kept
	 * unchanged.
	 *
	 * @param WC_Order $order                  order being charged/settled.
	 * @param array    $items_so_far           order lines already built for this
	 *                                         charge/settle call, each with 'amount',
	 *                                         'quantity', 'vat' and 'amount_incl_vat'.
	 * @param int      $direct_discount_amount the discount amount computed directly
	 *                                         from WC's discount accessors.
	 *
	 * @return int minor-unit amount (incl. tax) for the discount line.
	 */
	function rp_reconcile_discount_amount( WC_Order $order, array $items_so_far, int $direct_discount_amount ): int {
		$sum_incl_vat = 0;

		foreach ( $items_so_far as $item ) {
			$line_amount = (float) $item['amount'] * (int) ( $item['quantity'] ?? 1 );

			if ( ! empty( $item['vat'] ) && empty( $item['amount_incl_vat'] ) ) {
				$line_amount *= ( 1 + $item['vat'] );
			}

			$sum_incl_vat += round( $line_amount );
		}

		$target                    = rp_prepare_amount( $order->get_total(), $order->get_currency() );
		$remainder_discount_amount = (int) ( $target - $sum_incl_vat );

		if ( abs( $remainder_discount_amount - $direct_discount_amount ) <= 1 ) {
			return $remainder_discount_amount;
		}

		return $direct_discount_amount;
	}
}
