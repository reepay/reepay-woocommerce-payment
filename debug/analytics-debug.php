<?php
/**
 * WooCommerce Analytics Debug Tool
 * 
 * This file helps debug WooCommerce Analytics specifically for Reepay orders
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug WooCommerce Analytics
 */
function debug_woocommerce_analytics() {
	global $wpdb;
	
	echo "<h2>WooCommerce Analytics Debug</h2>";
	
	// Check WooCommerce Analytics status
	echo "<h3>1. WooCommerce Analytics Status</h3>";
	
	$analytics_enabled = get_option( 'woocommerce_analytics_enabled', 'yes' );
	echo "<p><strong>Analytics Enabled:</strong> {$analytics_enabled}</p>";
	
	// Check if Analytics classes exist
	$classes_status = array(
		'OrdersScheduler' => class_exists( '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler' ),
		'OrdersStatsDataStore' => class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ),
		'ReportsCache' => class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Cache' ),
	);
	
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Class</th><th>Status</th></tr>";
	foreach ( $classes_status as $class => $exists ) {
		echo "<tr><td>{$class}</td><td style='color: " . ( $exists ? 'green' : 'red' ) . ";'>" . ( $exists ? 'EXISTS' : 'NOT FOUND' ) . "</td></tr>";
	}
	echo "</table>";
	
	echo "<h3>2. Analytics Tables Status</h3>";
	
	$analytics_tables = array(
		'wc_order_stats',
		'wc_order_product_lookup',
		'wc_order_tax_lookup',
		'wc_order_coupon_lookup',
		'wc_customer_lookup',
	);
	
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Table</th><th>Exists</th><th>Row Count</th></tr>";
	
	foreach ( $analytics_tables as $table ) {
		$full_table_name = $wpdb->prefix . $table;
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_table_name ) );
		$count = $exists ? $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" ) : 0;
		
		echo "<tr>";
		echo "<td>{$table}</td>";
		echo "<td style='color: " . ( $exists ? 'green' : 'red' ) . ";'>" . ( $exists ? 'YES' : 'NO' ) . "</td>";
		echo "<td>{$count}</td>";
		echo "</tr>";
	}
	echo "</table>";
	
	echo "<h3>3. Analytics Hooks Status</h3>";
	
	// Check if analytics hooks are registered
	global $wp_filter;
	
	$important_hooks = array(
		'woocommerce_update_order',
		'woocommerce_order_status_changed',
		'woocommerce_payment_complete',
		'woocommerce_analytics_update_order_stats',
	);
	
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Hook</th><th>Callbacks</th><th>Details</th></tr>";
	
	foreach ( $important_hooks as $hook ) {
		$callbacks = isset( $wp_filter[$hook] ) ? count( $wp_filter[$hook]->callbacks ) : 0;
		$details = '';
		
		if ( isset( $wp_filter[$hook] ) ) {
			$callback_list = array();
			foreach ( $wp_filter[$hook]->callbacks as $priority => $callbacks_array ) {
				foreach ( $callbacks_array as $callback ) {
					if ( is_array( $callback['function'] ) ) {
						if ( is_object( $callback['function'][0] ) ) {
							$callback_list[] = get_class( $callback['function'][0] ) . '::' . $callback['function'][1] . " (priority: {$priority})";
						} else {
							$callback_list[] = $callback['function'][0] . '::' . $callback['function'][1] . " (priority: {$priority})";
						}
					} else {
						$callback_list[] = $callback['function'] . " (priority: {$priority})";
					}
				}
			}
			$details = implode( '<br>', array_slice( $callback_list, 0, 5 ) );
			if ( count( $callback_list ) > 5 ) {
				$details .= '<br>... and ' . ( count( $callback_list ) - 5 ) . ' more';
			}
		}
		
		echo "<tr>";
		echo "<td>{$hook}</td>";
		echo "<td>{$callbacks}</td>";
		echo "<td style='font-size: 11px;'>{$details}</td>";
		echo "</tr>";
	}
	echo "</table>";
	
	echo "<h3>4. Reepay Orders Analytics Status</h3>";
	
	// Get Reepay orders and check their analytics status
	$reepay_orders = $wpdb->get_results( "
		SELECT p.ID, p.post_status, p.post_date, pm.meta_value as payment_method
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'shop_order'
		AND pm.meta_key = '_payment_method'
		AND pm.meta_value LIKE 'reepay%'
		AND p.post_status IN ('wc-processing', 'wc-completed')
		ORDER BY p.post_date DESC
		LIMIT 10
	" );
	
	if ( ! empty( $reepay_orders ) ) {
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
		echo "<tr><th>Order ID</th><th>Status</th><th>Date</th><th>In Analytics</th><th>Date Paid</th><th>Actions</th></tr>";
		
		foreach ( $reepay_orders as $order_data ) {
			$order = wc_get_order( $order_data->ID );
			if ( ! $order ) continue;
			
			// Check if in analytics
			$in_analytics = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
				$order_data->ID
			) ) > 0;
			
			$date_paid = $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : 'Not set';
			
			echo "<tr>";
			echo "<td>#{$order_data->ID}</td>";
			echo "<td>{$order_data->post_status}</td>";
			echo "<td>{$order_data->post_date}</td>";
			echo "<td style='color: " . ( $in_analytics ? 'green' : 'red' ) . ";'><strong>" . ( $in_analytics ? 'YES' : 'NO' ) . "</strong></td>";
			echo "<td>{$date_paid}</td>";
			echo "<td>";
			
			if ( ! $in_analytics ) {
				$sync_url = wp_nonce_url( 
					admin_url( 'admin-ajax.php?action=debug_force_analytics_sync&order_id=' . $order_data->ID ),
					'debug_force_sync'
				);
				echo "<a href='{$sync_url}' class='button button-small'>Force Sync</a>";
			}
			
			echo "</td>";
			echo "</tr>";
		}
		echo "</table>";
	} else {
		echo "<p>No Reepay orders with processing/completed status found.</p>";
	}
	
	echo "<h3>5. Analytics Data Comparison</h3>";
	
	// Compare order counts
	$total_orders = $wpdb->get_var( "
		SELECT COUNT(*) 
		FROM {$wpdb->posts} 
		WHERE post_type = 'shop_order' 
		AND post_status IN ('wc-processing', 'wc-completed')
	" );
	
	$analytics_orders = $wpdb->get_var( "
		SELECT COUNT(*) 
		FROM {$wpdb->prefix}wc_order_stats 
		WHERE status IN ('wc-processing', 'wc-completed')
	" );
	
	$reepay_total = $wpdb->get_var( "
		SELECT COUNT(*) 
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'shop_order'
		AND p.post_status IN ('wc-processing', 'wc-completed')
		AND pm.meta_key = '_payment_method'
		AND pm.meta_value LIKE 'reepay%'
	" );
	
	$reepay_analytics = $wpdb->get_var( "
		SELECT COUNT(*) 
		FROM {$wpdb->prefix}wc_order_stats os
		INNER JOIN {$wpdb->postmeta} pm ON os.order_id = pm.post_id
		WHERE os.status IN ('wc-processing', 'wc-completed')
		AND pm.meta_key = '_payment_method'
		AND pm.meta_value LIKE 'reepay%'
	" );
	
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Metric</th><th>Orders Table</th><th>Analytics Table</th><th>Difference</th></tr>";
	echo "<tr><td>Total Processing/Completed Orders</td><td>{$total_orders}</td><td>{$analytics_orders}</td><td>" . ($total_orders - $analytics_orders) . "</td></tr>";
	echo "<tr><td>Reepay Processing/Completed Orders</td><td>{$reepay_total}</td><td>{$reepay_analytics}</td><td>" . ($reepay_total - $reepay_analytics) . "</td></tr>";
	echo "</table>";
	
	if ( $total_orders != $analytics_orders ) {
		echo "<div style='background: #ffcccc; padding: 10px; margin: 10px 0; border: 1px solid #ff0000;'>";
		echo "<strong>Warning:</strong> There's a mismatch between orders and analytics data. Some orders may not be synced properly.";
		echo "</div>";
	}
	
	echo "<h3>6. Test Analytics Sync</h3>";
	
	if ( ! empty( $reepay_orders ) ) {
		$test_order_id = $reepay_orders[0]->ID;
		echo "<p>Testing analytics sync for Order #{$test_order_id}</p>";
		
		// Test different sync methods
		echo "<h4>Sync Methods Test:</h4>";
		echo "<ul>";
		
		// Method 1: Direct DataStore sync
		if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
			$result1 = \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $test_order_id );
			echo "<li><strong>Direct DataStore sync:</strong> " . ( $result1 ? 'Success' : 'Failed' ) . "</li>";
		}
		
		// Method 2: Trigger update hook
		do_action( 'woocommerce_update_order', $test_order_id );
		echo "<li><strong>woocommerce_update_order hook:</strong> Triggered</li>";
		
		// Method 3: Schedule import
		if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler' ) ) {
			$result3 = \Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::possibly_schedule_import( $test_order_id );
			echo "<li><strong>Schedule import:</strong> " . ( $result3 ? 'Scheduled' : 'Failed' ) . "</li>";
		}
		
		echo "</ul>";
	}
}

// AJAX handler for force sync
add_action( 'wp_ajax_debug_force_analytics_sync', function() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'debug_force_sync' ) ) {
		wp_die( 'Security check failed' );
	}
	
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	
	$order_id = intval( $_GET['order_id'] );
	
	// Force sync using multiple methods
	$results = array();
	
	if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
		$results[] = \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order_id );
	}
	
	do_action( 'woocommerce_update_order', $order_id );
	
	if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler' ) ) {
		\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::possibly_schedule_import( $order_id );
	}
	
	wp_redirect( admin_url( 'admin.php?page=debug-woocommerce-analytics&synced=' . $order_id ) );
	exit;
} );

// Add admin page
add_action( 'admin_menu', function() {
	add_submenu_page(
		'woocommerce',
		'Debug WC Analytics',
		'Debug Analytics',
		'manage_woocommerce',
		'debug-woocommerce-analytics',
		'debug_woocommerce_analytics'
	);
} );
