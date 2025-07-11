<?php
/**
 * SQL Debug Tool for Reepay Analytics
 * 
 * This file helps debug SQL queries related to WooCommerce Analytics and Reepay orders
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug SQL Queries
 */
function debug_reepay_sql() {
	global $wpdb;
	
	echo "<h2>Reepay SQL Debug Tool</h2>";
	
	// Check if SAVEQUERIES is enabled
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		echo "<div style='background: #ffcccc; padding: 10px; margin: 10px 0; border: 1px solid #ff0000;'>";
		echo "<strong>Warning:</strong> SAVEQUERIES is not enabled. Add <code>define('SAVEQUERIES', true);</code> to wp-config.php to see detailed SQL queries.";
		echo "</div>";
	}
	
	// Enable error reporting
	$wpdb->show_errors();
	
	echo "<h3>1. Database Table Information</h3>";
	
	// Check if analytics table exists
	$analytics_table = $wpdb->prefix . 'wc_order_stats';
	$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $analytics_table ) );
	
	echo "<p><strong>Analytics Table ({$analytics_table}):</strong> " . ( $table_exists ? 'EXISTS' : 'NOT FOUND' ) . "</p>";
	
	if ( $table_exists ) {
		// Get table structure
		$columns = $wpdb->get_results( "DESCRIBE {$analytics_table}" );
		echo "<h4>Table Structure:</h4>";
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
		echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
		foreach ( $columns as $column ) {
			echo "<tr>";
			echo "<td>{$column->Field}</td>";
			echo "<td>{$column->Type}</td>";
			echo "<td>{$column->Null}</td>";
			echo "<td>{$column->Key}</td>";
			echo "<td>{$column->Default}</td>";
			echo "</tr>";
		}
		echo "</table>";
	}
	
	echo "<h3>2. Order Counts by Status</h3>";
	
	// Get order counts by status
	$status_counts = $wpdb->get_results( "
		SELECT post_status, COUNT(*) as count 
		FROM {$wpdb->posts} 
		WHERE post_type = 'shop_order' 
		GROUP BY post_status 
		ORDER BY count DESC
	" );
	
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Status</th><th>Count</th></tr>";
	foreach ( $status_counts as $status ) {
		echo "<tr><td>{$status->post_status}</td><td>{$status->count}</td></tr>";
	}
	echo "</table>";
	
	echo "<h3>3. Reepay Orders Analysis</h3>";
	
	// Find Reepay orders
	$reepay_orders_sql = "
		SELECT p.ID, p.post_status, p.post_date, pm.meta_value as payment_method
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'shop_order'
		AND pm.meta_key = '_payment_method'
		AND pm.meta_value LIKE 'reepay%'
		ORDER BY p.post_date DESC
		LIMIT 20
	";
	
	echo "<h4>SQL Query for Reepay Orders:</h4>";
	echo "<pre style='background: #f9f9f9; padding: 10px; overflow-x: auto;'>" . esc_html( $reepay_orders_sql ) . "</pre>";
	
	$reepay_orders = $wpdb->get_results( $reepay_orders_sql );
	
	echo "<h4>Recent Reepay Orders (Last 20):</h4>";
	if ( ! empty( $reepay_orders ) ) {
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
		echo "<tr><th>Order ID</th><th>Status</th><th>Date</th><th>Payment Method</th><th>In Analytics</th></tr>";
		
		foreach ( $reepay_orders as $order ) {
			// Check if order is in analytics
			$in_analytics = 'N/A';
			if ( $table_exists ) {
				$analytics_count = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$analytics_table} WHERE order_id = %d",
					$order->ID
				) );
				$in_analytics = $analytics_count > 0 ? 'YES' : 'NO';
			}
			
			echo "<tr>";
			echo "<td>#{$order->ID}</td>";
			echo "<td>{$order->post_status}</td>";
			echo "<td>{$order->post_date}</td>";
			echo "<td>{$order->payment_method}</td>";
			echo "<td style='color: " . ( $in_analytics === 'YES' ? 'green' : 'red' ) . ";'><strong>{$in_analytics}</strong></td>";
			echo "</tr>";
		}
		echo "</table>";
	} else {
		echo "<p>No Reepay orders found.</p>";
	}
	
	echo "<h3>4. Analytics Table Analysis</h3>";
	
	if ( $table_exists ) {
		// Get analytics counts
		$total_analytics = $wpdb->get_var( "SELECT COUNT(*) FROM {$analytics_table}" );
		$processing_analytics = $wpdb->get_var( "SELECT COUNT(*) FROM {$analytics_table} WHERE status = 'wc-processing'" );
		$completed_analytics = $wpdb->get_var( "SELECT COUNT(*) FROM {$analytics_table} WHERE status = 'wc-completed'" );
		
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
		echo "<tr><th>Metric</th><th>Count</th></tr>";
		echo "<tr><td>Total orders in analytics</td><td>{$total_analytics}</td></tr>";
		echo "<tr><td>Processing orders in analytics</td><td>{$processing_analytics}</td></tr>";
		echo "<tr><td>Completed orders in analytics</td><td>{$completed_analytics}</td></tr>";
		echo "</table>";
		
		// Get recent analytics entries
		echo "<h4>Recent Analytics Entries (Last 10):</h4>";
		$recent_analytics = $wpdb->get_results( "
			SELECT order_id, status, date_created, date_paid, total_sales, net_total
			FROM {$analytics_table}
			ORDER BY date_created DESC
			LIMIT 10
		" );
		
		if ( ! empty( $recent_analytics ) ) {
			echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
			echo "<tr><th>Order ID</th><th>Status</th><th>Date Created</th><th>Date Paid</th><th>Total Sales</th><th>Net Total</th></tr>";
			foreach ( $recent_analytics as $entry ) {
				echo "<tr>";
				echo "<td>#{$entry->order_id}</td>";
				echo "<td>{$entry->status}</td>";
				echo "<td>{$entry->date_created}</td>";
				echo "<td>" . ( $entry->date_paid ?: 'NULL' ) . "</td>";
				echo "<td>{$entry->total_sales}</td>";
				echo "<td>{$entry->net_total}</td>";
				echo "</tr>";
			}
			echo "</table>";
		}
	}
	
	echo "<h3>5. WooCommerce Analytics Settings</h3>";
	
	// Get excluded statuses
	$excluded_statuses = get_option( 'woocommerce_excluded_report_order_statuses', array( 'pending', 'failed', 'cancelled' ) );
	echo "<p><strong>Excluded Order Statuses:</strong> " . implode( ', ', $excluded_statuses ) . "</p>";
	
	// Check if analytics is enabled
	$analytics_enabled = get_option( 'woocommerce_analytics_enabled', 'yes' );
	echo "<p><strong>Analytics Enabled:</strong> {$analytics_enabled}</p>";
	
	echo "<h3>6. Test wc_get_orders() with SQL Capture</h3>";
	
	// Capture queries
	$original_queries = $wpdb->queries;
	$wpdb->queries = array();
	
	// Test wc_get_orders
	$test_orders = wc_get_orders( array(
		'limit' => 5,
		'meta_key' => '_payment_method',
		'meta_value' => 'reepay_checkout',
		'status' => array( 'processing', 'completed' ),
	) );
	
	echo "<p><strong>wc_get_orders() found:</strong> " . count( $test_orders ) . " orders</p>";
	
	if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $wpdb->queries ) ) {
		echo "<h4>Captured SQL Queries:</h4>";
		foreach ( $wpdb->queries as $i => $query ) {
			echo "<h5>Query " . ($i + 1) . " (Time: {$query[1]}s):</h5>";
			echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>" . esc_html( $query[0] ) . "</pre>";
		}
	} else {
		echo "<p><em>No queries captured. Enable SAVEQUERIES in wp-config.php to see SQL queries.</em></p>";
	}
	
	// Restore queries
	$wpdb->queries = $original_queries;
	
	echo "<h3>7. Manual Analytics Sync Test</h3>";
	
	if ( ! empty( $test_orders ) ) {
		$test_order = $test_orders[0];
		$order_id = $test_order->get_id();
		
		echo "<p>Testing manual sync for Order #{$order_id}</p>";
		
		// Check current analytics status
		$before_sync = $table_exists ? $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$analytics_table} WHERE order_id = %d",
			$order_id
		) ) : 0;
		
		echo "<p><strong>Before sync:</strong> " . ( $before_sync > 0 ? 'In analytics' : 'Not in analytics' ) . "</p>";
		
		// Force sync
		if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
			$sync_result = \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order_id );
			echo "<p><strong>Sync result:</strong> " . ( $sync_result ? 'Success' : 'Failed' ) . "</p>";
			
			// Check after sync
			$after_sync = $table_exists ? $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$analytics_table} WHERE order_id = %d",
				$order_id
			) ) : 0;
			
			echo "<p><strong>After sync:</strong> " . ( $after_sync > 0 ? 'In analytics' : 'Not in analytics' ) . "</p>";
		}
	}
}

// Add admin page for SQL debugging
add_action( 'admin_menu', function() {
	add_submenu_page(
		'woocommerce',
		'Debug Reepay SQL',
		'Debug SQL',
		'manage_woocommerce',
		'debug-reepay-sql',
		'debug_reepay_sql'
	);
} );
