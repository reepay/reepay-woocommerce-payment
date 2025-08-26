<?php
/**
 * Analytics Sync Helper
 *
 * @package Reepay\Checkout\Analytics
 */

namespace Reepay\Checkout\Analytics;

defined( 'ABSPATH' ) || exit();

/**
 * Class AnalyticsSync
 *
 * @package Reepay\Checkout\Analytics
 */
class AnalyticsSync {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook into Reepay order status changes to ensure analytics sync.
		add_action( 'reepay_webhook_invoice_authorized', array( $this, 'sync_order_analytics' ) );
		add_action( 'reepay_webhook_invoice_settled', array( $this, 'sync_order_analytics' ) );

		// Hook into order status changes for Reepay orders.
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 20, 4 );
	}

	/**
	 * Sync order analytics data
	 *
	 * @param array $data Webhook data containing order_id.
	 */
	public function sync_order_analytics( $data ) {
		if ( empty( $data['order_id'] ) ) {
			return;
		}

		$order_id = $data['order_id'];
		$this->force_analytics_sync( $order_id );
	}

	/**
	 * Handle order status changes for Reepay orders
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order object.
	 */
	public function handle_order_status_change( $order_id, $from, $to, $order ) {
		// Only handle Reepay orders.
		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		// Force analytics sync for status changes that affect reporting.
		$reporting_statuses = array( 'processing', 'completed', 'on-hold', 'cancelled', 'refunded' );
		if ( in_array( $to, $reporting_statuses, true ) || in_array( $from, $reporting_statuses, true ) ) {
			$this->force_analytics_sync( $order_id );
		}
	}

	/**
	 * Force analytics sync for an order
	 *
	 * @param int $order_id Order ID.
	 */
	private function force_analytics_sync( $order_id ) {
		// Trigger the WooCommerce update hook.
		do_action( 'woocommerce_update_order', $order_id );
		//do_action( 'woocommerce_analytics_update_order_stats', $order_id );
    
		// Trigger order save to ensure analytics are updated
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->save();
		}
	}

	/**
	 * Debug method to check if order is in analytics
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function is_order_in_analytics( $order_id ) {
		// Check cache first.
		$cache_key     = 'reepay_order_in_analytics_' . $order_id;
		$cached_result = wp_cache_get( $cache_key, 'reepay_analytics' );

		if ( false !== $cached_result ) {
			return (bool) $cached_result;
		}

		global $wpdb;

		// Use direct query only as last resort for debug purposes.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$result = $count > 0;

		// Cache the result for 5 minutes.
		wp_cache_set( $cache_key, $result, 'reepay_analytics', 300 );

		return $result;
	}

	/**
	 * Debug method to get order analytics data
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function get_order_analytics_data( $order_id ) {
		// Check cache first.
		$cache_key     = 'reepay_order_analytics_data_' . $order_id;
		$cached_result = wp_cache_get( $cache_key, 'reepay_analytics' );

		if ( false !== $cached_result ) {
			return $cached_result;
		}

		global $wpdb;

		// Use direct query only as last resort for debug purposes.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
				$order_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Cache the result for 5 minutes.
		wp_cache_set( $cache_key, $result, 'reepay_analytics', 300 );

		return $result;
	}
}
