<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Reepay_Order {

	public function __construct() {
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'handle_custom_query_var' ), 10, 2 );
		add_filter( 'reepay_order_handle', array( $this, 'get_order_handle' ), 10, 3 );
	}

	/**
	 * Handle a custom '_reepay_order' query var to get orders with the '_reepay_order' meta.
	 *
	 * @see https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function handle_custom_query_var( $query, $query_vars ) {
		if ( ! empty( $query_vars['_reepay_order'] ) ) {
			$query['meta_query'][] = array(
				'key'   => '_reepay_order',
				'value' => esc_attr( $query_vars['_reepay_order'] ),
			);
		}

		return $query;
	}

	/**
	 * Get Reepay Order Handle.
	 *
	 * @param string   $handle
	 * @param mixed    $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function get_order_handle( $handle, $order_id, $order ) {
		$handle = get_post_meta( $order->get_id(), '_reepay_order', true );
		if ( empty( $handle ) ) {
			$handle = 'order-' . $order->get_id();
			update_post_meta( $order->get_id(), '_reepay_order', $handle );
		}

		return $handle;
	}
}

new WC_Reepay_Order();

