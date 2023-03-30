<?php
/**
 * @package Reepay\Checkout\Functions
 */

use Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_get_order_handle' ) ) {
	/**
	 * Get Reepay Order Handle.
	 *
	 * @param WC_Order $order
	 * @param bool     $unique
	 *
	 * @return string
	 */
	function rp_get_order_handle( WC_Order $order, $unique = false ) {
		$handle = $order->get_meta( '_reepay_order' );

		if ( $unique ) {
			$handle = null;
			$order->delete_meta_data( '_reepay_order' );
		}

		if ( empty( $handle ) ) {
			$handle = $unique ? 'order-' . $order->get_order_number() . '-' . time() : 'order-' . $order->get_order_number();

			$order->add_meta_data( '_reepay_order', $handle );
		}

		$order->save_meta_data();

		return $handle;
	}
}

if ( ! function_exists( 'rp_get_order_by_handle' ) ) {
	/**
	 * Get Order By Reepay Order Handle.
	 *
	 * @param string $handle
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_handle( $handle ) {
		global $wpdb;

		$query    = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql      = $wpdb->prepare( $query, '_reepay_order', $handle );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			return false;
		}

		// Get Order
		clean_post_cache( $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return $order;
	}
}

if ( ! function_exists( 'rp_get_order_by_session' ) ) {
	/**
	 * Get Order By Reepay Order Session.
	 *
	 * @param  string $session_id
	 *
	 * @return false|WC_Order
	 * @throws Exception
	 */
	function rp_get_order_by_session( $session_id ) {
		global $wpdb;

		$query    = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql      = $wpdb->prepare( $query, 'reepay_session_id', $session_id );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			throw new Exception( sprintf( __( 'Session #%s isn\'t exists in store.', 'reepay-checkout-gateway' ), $session_id ) );
		}

		// Get Order
		clean_post_cache( $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return $order;
	}
}

if ( ! function_exists( 'rp_is_order_paid_via_reepay' ) ) {
	/**
	 * Check if payment method is reepay payment method
	 *
	 * @param  WC_Order $order
	 */
	function rp_is_order_paid_via_reepay( $order ) {
		return in_array( $order->get_payment_method(), Gateways::PAYMENT_METHODS );
	}
}
