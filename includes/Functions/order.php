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
			$handle = $unique ?
				'order-' . $order->get_order_number() . '-' . $current_time :
				'order-' . $order->get_order_number();

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
	function rp_get_order_by_session( string $session_id = null, string $handle = null ) {
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
