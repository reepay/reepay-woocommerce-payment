<?php
/**
 * Order functions
 *
 * @package Reepay\Checkout\Functions
 */

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
	function rp_get_order_handle( WC_Order $order, $unique = false ): ?string {
		if ( $unique ) {
			$handle = null;
			$order->delete_meta_data( '_reepay_order' );
		} else {
			$handle = $order->get_meta( '_reepay_order' );
		}

		if ( empty( $handle ) ) {
			$handle = $unique ?
				'order-' . $order->get_order_number() . '-' . time() :
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
		global $wpdb;

		$order_id = wp_cache_get( $handle, 'reepay_order_by_handle' );

		if ( empty( $order_id ) ) {
			$order_id = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"
					SELECT post_id FROM {$wpdb->prefix}postmeta 
					LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
					WHERE meta_key = %s AND meta_value = %s;",
					'_reepay_order',
					$handle
				)
			);

			if ( $order_id ) {
				wp_cache_set( $handle, $order_id, 'reepay_order_by_handle' );
			} else {
				return false;
			}
		}

		clean_post_cache( $order_id );
		return wc_get_order( $order_id );
	}
}

if ( ! function_exists( 'rp_get_order_by_session' ) ) {
	/**
	 * Get order by reepay order session.
	 *
	 * @param string $session_id reepay order session.
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_session( string $session_id ) {
		global $wpdb;

		$order_id = wp_cache_get( $session_id, 'reepay_order_by_session' );

		if ( empty( $order_id ) ) {
			$order_id = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"
					SELECT post_id FROM {$wpdb->prefix}postmeta 
					LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
					WHERE meta_key = %s AND meta_value = %s;",
					'reepay_session_id',
					$session_id
				)
			);

			if ( $order_id ) {
				wp_cache_set( $session_id, $order_id, 'reepay_order_by_session' );
			} else {
				return false;
			}
		}

		clean_post_cache( $order_id );
		return $order_id ? wc_get_order( $order_id ) : false;
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
