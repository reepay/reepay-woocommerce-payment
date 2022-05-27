<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Reepay_Order
{
	public function __construct() {
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'handle_custom_query_var' ), 10, 2 );
		add_filter( 'reepay_order_handle' , array( $this, 'get_order_handle' ), 10, 3 );
		add_filter( 'reepay_get_order' , array( $this, 'get_orderid_by_handle' ), 10, 2 );
	}

	/**
	 * Handle a custom '_reepay_order' query var to get orders with the '_reepay_order' meta.
	 * @see https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function handle_custom_query_var( $query, $query_vars ) {
		if ( ! empty( $query_vars[ '_reepay_order' ] ) ) {
			$query[ 'meta_query' ][] = [
				'key' => '_reepay_order',
				'value' => esc_attr( $query_vars['_reepay_order'] ),
			];
		}

		return $query;
	}

	/**
	 * Get Reepay Order Handle.
	 *
	 * @param string $handle
	 * @param mixed $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function get_order_handle( $handle, $order_id, $order ) {
		$handle = get_post_meta( $order->get_id(), '_reepay_order', TRUE );
		if ( empty( $handle ) ) {
			$handle = 'order-' . $order->get_id();
			update_post_meta( $order->get_id(), '_reepay_order', $handle );
		}

		return $handle;
	}

	/**
	 * Get Order Id by Reepay Order Handle.
	 *
	 * @param int|null $order_id
	 * @param string $handle
	 *
	 * @return bool|mixed
	 */
	public function get_orderid_by_handle( $order_id, $handle ) {
		global $wpdb;

		$query = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql = $wpdb->prepare( $query, '_reepay_order', $handle );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			return false;
		}

		return $order_id;
	}
}

new WC_Reepay_Order();

