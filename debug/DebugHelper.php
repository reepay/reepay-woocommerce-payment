<?php
/**
 * Analytics Debug Helper
 *
 * @package Reepay\Checkout\Analytics
 */

namespace Reepay\Checkout\Analytics;

defined( 'ABSPATH' ) || exit();

/**
 * Class DebugHelper
 *
 * @package Reepay\Checkout\Analytics
 */
class DebugHelper {

	/**
	 * Debug order analytics status
	 *
	 * @param int $order_id Order ID
	 * @return array Debug information
	 */
	public static function debug_order_analytics( $order_id ) {
		global $wpdb;
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'error' => 'Order not found' );
		}

		$debug_info = array(
			'order_id' => $order_id,
			'order_status' => $order->get_status(),
			'payment_method' => $order->get_payment_method(),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : null,
			'date_completed' => $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d H:i:s' ) : null,
			'total' => $order->get_total(),
			'is_reepay_order' => rp_is_order_paid_via_reepay( $order ),
		);

		// Check if order exists in analytics table
		$analytics_data = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
			$order_id
		), ARRAY_A );

		$debug_info['in_analytics_table'] = ! empty( $analytics_data );
		$debug_info['analytics_data'] = $analytics_data;

		// Check excluded statuses
		$excluded_statuses = \WC_Admin_Settings::get_option( 'woocommerce_excluded_report_order_statuses', array( 'pending', 'failed', 'cancelled' ) );
		$excluded_statuses = array_merge( array( 'auto-draft', 'trash' ), $excluded_statuses );
		$debug_info['excluded_statuses'] = $excluded_statuses;
		$debug_info['is_status_excluded'] = in_array( $order->get_status(), $excluded_statuses );

		// Check Reepay specific meta
		$debug_info['reepay_meta'] = array(
			'_reepay_state_authorized' => $order->get_meta( '_reepay_state_authorized' ),
			'_reepay_state_settled' => $order->get_meta( '_reepay_state_settled' ),
			'_reepay_order' => $order->get_meta( '_reepay_order' ),
			'_transaction_id' => $order->get_transaction_id(),
		);

		return $debug_info;
	}

	/**
	 * Force sync order to analytics
	 *
	 * @param int $order_id Order ID
	 * @return array Result information
	 */
	public static function force_sync_order( $order_id ) {
		$result = array(
			'order_id' => $order_id,
			'sync_attempts' => array(),
		);

		// Method 1: Direct DataStore sync
		if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
			$sync_result = \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order_id );
			$result['sync_attempts']['direct_datastore'] = $sync_result;
		}

		// Method 2: Trigger update hook
		do_action( 'woocommerce_update_order', $order_id );
		$result['sync_attempts']['update_hook'] = 'triggered';

		// Method 3: Schedule import
		if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler' ) ) {
			$schedule_result = \Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::possibly_schedule_import( $order_id );
			$result['sync_attempts']['schedule_import'] = $schedule_result;
		}

		// Method 4: Clear cache
		if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Cache' ) ) {
			\Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();
			$result['sync_attempts']['cache_cleared'] = true;
		}

		return $result;
	}

	/**
	 * Get analytics table status
	 *
	 * @return array Table information
	 */
	public static function get_analytics_table_info() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_order_stats';
		
		// Check if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table_name
		) ) === $table_name;

		if ( ! $table_exists ) {
			return array( 'error' => 'Analytics table does not exist' );
		}

		// Get table info
		$total_orders = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$processing_orders = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'wc-processing'" );
		$reepay_orders = $wpdb->get_var( "
			SELECT COUNT(*) 
			FROM {$table_name} os
			INNER JOIN {$wpdb->postmeta} pm ON os.order_id = pm.post_id 
			WHERE pm.meta_key = '_payment_method' AND pm.meta_value LIKE 'reepay%'
		" );

		return array(
			'table_exists' => true,
			'total_orders' => $total_orders,
			'processing_orders' => $processing_orders,
			'reepay_orders' => $reepay_orders,
		);
	}

	/**
	 * Add debug information to order admin page
	 *
	 * @param WC_Order $order Order object
	 */
	public static function add_order_debug_info( $order ) {
		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$debug_info = self::debug_order_analytics( $order->get_id() );
		
		echo '<div class="reepay-debug-info" style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">';
		echo '<h4>Reepay Analytics Debug Info</h4>';
		echo '<p><strong>In Analytics Table:</strong> ' . ( $debug_info['in_analytics_table'] ? 'Yes' : 'No' ) . '</p>';
		echo '<p><strong>Status Excluded:</strong> ' . ( $debug_info['is_status_excluded'] ? 'Yes' : 'No' ) . '</p>';
		echo '<p><strong>Date Paid:</strong> ' . ( $debug_info['date_paid'] ?: 'Not set' ) . '</p>';
		
		if ( ! $debug_info['in_analytics_table'] ) {
			$sync_url = wp_nonce_url( 
				admin_url( 'admin-ajax.php?action=reepay_force_sync&order_id=' . $order->get_id() ),
				'reepay_force_sync'
			);
			echo '<p><a href="' . esc_url( $sync_url ) . '" class="button">Force Sync to Analytics</a></p>';
		}
		echo '</div>';
	}
}

// Add AJAX handler for force sync
add_action( 'wp_ajax_reepay_force_sync', function() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'reepay_force_sync' ) ) {
		wp_die( 'Security check failed' );
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Insufficient permissions' );
	}

	$order_id = intval( $_GET['order_id'] );
	$result = DebugHelper::force_sync_order( $order_id );
	
	wp_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&reepay_sync=1' ) );
	exit;
} );

// Add debug info to order admin page
add_action( 'woocommerce_admin_order_data_after_order_details', array( DebugHelper::class, 'add_order_debug_info' ) );
