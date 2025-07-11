<?php
/**
 * Test script for Analytics Fix
 * 
 * This file can be used to test if the analytics fix is working properly.
 * Run this via WP-CLI or include it in a test page.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test Analytics Fix
 */
function test_reepay_analytics_fix() {
	echo "<h2>Reepay Analytics Fix Test</h2>";
	
	// Enable SQL debugging
	global $wpdb;
	$wpdb->show_errors();
	
	// Check if SAVEQUERIES is enabled
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		echo "<div style='background: #ffcccc; padding: 10px; margin: 10px 0; border: 1px solid #ff0000;'>";
		echo "<strong>Warning:</strong> SAVEQUERIES is not enabled. Add <code>define('SAVEQUERIES', true);</code> to wp-config.php to see SQL queries.";
		echo "</div>";
	}

	// Store original queries for debugging
	$original_queries = $wpdb->queries;
	$wpdb->queries = array();

	// Show the parameters being used
	echo "<h3>wc_get_orders() Parameters:</h3>";
	$params = array(
		'limit' => 10,
		'meta_key' => '_payment_method',
		'meta_value' => 'reepay_checkout',
		'status' => array( 'processing', 'completed' ),
	);
	echo "<pre style='background: #f9f9f9; padding: 10px;'>";
	print_r( $params );
	echo "</pre>";

	// Get recent Reepay orders
	echo "<h3>Executing wc_get_orders()...</h3>";
	$start_time = microtime( true );
	$orders = wc_get_orders( $params );
	$end_time = microtime( true );
	$execution_time = $end_time - $start_time;

	echo "<p><strong>Results:</strong> Found " . count( $orders ) . " orders in " . round( $execution_time, 4 ) . " seconds</p>";

	// Debug: Show the SQL queries that were executed
	echo "<h3>SQL Queries Debug:</h3>";
	echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; white-space: pre-wrap;'>";

	if ( ! empty( $wpdb->queries ) ) {
		foreach ( $wpdb->queries as $i => $query ) {
			echo "<strong>Query " . ($i + 1) . ":</strong>\n";
			echo htmlspecialchars( $query[0] ) . "\n";
			echo "<em>Execution time: " . $query[1] . "s</em>\n\n";
		}
	} else {
		echo "No queries captured. Make sure SAVEQUERIES is defined as true in wp-config.php";
	}

	echo "</div>";

	// Alternative direct SQL query for comparison
	echo "<h3>Alternative Direct SQL Query:</h3>";
	$direct_sql = "
		SELECT p.ID, p.post_status, p.post_date, pm.meta_value as payment_method
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'shop_order'
		AND p.post_status IN ('wc-processing', 'wc-completed')
		AND pm.meta_key = '_payment_method'
		AND pm.meta_value = 'reepay_checkout'
		ORDER BY p.post_date DESC
		LIMIT 10
	";
	
	echo "<pre style='background: #f9f9f9; padding: 10px; overflow-x: auto;'>" . esc_html( $direct_sql ) . "</pre>";
	
	$direct_start = microtime( true );
	$direct_results = $wpdb->get_results( $direct_sql );
	$direct_end = microtime( true );
	$direct_time = $direct_end - $direct_start;
	
	echo "<p><strong>Direct SQL Results:</strong> Found " . count( $direct_results ) . " orders in " . round( $direct_time, 4 ) . " seconds</p>";

	// Show comparison
	echo "<h3>Comparison:</h3>";
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Method</th><th>Results</th><th>Execution Time</th></tr>";
	echo "<tr><td>wc_get_orders()</td><td>" . count( $orders ) . "</td><td>" . round( $execution_time, 4 ) . "s</td></tr>";
	echo "<tr><td>Direct SQL</td><td>" . count( $direct_results ) . "</td><td>" . round( $direct_time, 4 ) . "s</td></tr>";
	echo "</table>";

	// Restore original queries
	$wpdb->queries = $original_queries;
	
	if ( empty( $orders ) ) {
		echo "<p>No Reepay orders found with processing or completed status.</p>";
		return;
	}
	
	echo "<h3>Found " . count( $orders ) . " Reepay orders:</h3>";
	echo "<table border='1' cellpadding='5'>";
	echo "<tr><th>Order ID</th><th>Status</th><th>Date Paid</th><th>In Analytics</th><th>Actions</th></tr>";
	
	foreach ( $orders as $order ) {
		$order_id = $order->get_id();
		$status = $order->get_status();
		$date_paid = $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : 'Not set';
		
		// Check if order is in analytics
		$in_analytics = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
			$order_id
		) ) > 0;
		
		echo "<tr>";
		echo "<td>#{$order_id}</td>";
		echo "<td>{$status}</td>";
		echo "<td>{$date_paid}</td>";
		echo "<td>" . ( $in_analytics ? 'Yes' : '<strong style="color:red;">No</strong>' ) . "</td>";
		echo "<td>";
		
		if ( ! $in_analytics ) {
			echo "<button onclick='forceSync({$order_id})'>Force Sync</button>";
		}
		
		echo "</td>";
		echo "</tr>";
	}
	
	echo "</table>";
	
	// Show analytics table stats
	echo "<h3>Analytics Table Statistics:</h3>";
	$total_orders = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats" );
	$processing_orders = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE status = 'wc-processing'" );
	$reepay_orders = $wpdb->get_var( "
		SELECT COUNT(*) 
		FROM {$wpdb->prefix}wc_order_stats os
		INNER JOIN {$wpdb->postmeta} pm ON os.order_id = pm.post_id 
		WHERE pm.meta_key = '_payment_method' AND pm.meta_value LIKE 'reepay%'
	" );
	
	echo "<ul>";
	echo "<li>Total orders in analytics: {$total_orders}</li>";
	echo "<li>Processing orders in analytics: {$processing_orders}</li>";
	echo "<li>Reepay orders in analytics: {$reepay_orders}</li>";
	echo "</ul>";
	
	?>
	<script>
	function forceSync(orderId) {
		if (confirm('Force sync order #' + orderId + ' to analytics?')) {
			fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: 'action=test_force_sync&order_id=' + orderId + '&_wpnonce=<?php echo wp_create_nonce( 'test_force_sync' ); ?>'
			})
			.then(response => response.json())
			.then(data => {
				alert('Sync result: ' + JSON.stringify(data));
				location.reload();
			})
			.catch(error => {
				alert('Error: ' + error);
			});
		}
	}
	</script>
	<?php
}

// AJAX handler for force sync
add_action( 'wp_ajax_test_force_sync', function() {
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'test_force_sync' ) ) {
		wp_die( json_encode( array( 'error' => 'Security check failed' ) ) );
	}
	
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( json_encode( array( 'error' => 'Insufficient permissions' ) ) );
	}
	
	$order_id = intval( $_POST['order_id'] );
	
	// Force sync
	$result = array( 'order_id' => $order_id );
	
	// Method 1: Direct sync
	if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
		$sync_result = \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order_id );
		$result['direct_sync'] = $sync_result;
	}
	
	// Method 2: Trigger hook
	do_action( 'woocommerce_update_order', $order_id );
	$result['hook_triggered'] = true;
	
	// Method 3: Schedule import
	if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler' ) ) {
		$schedule_result = \Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::possibly_schedule_import( $order_id );
		$result['schedule_import'] = $schedule_result;
	}
	
	// Check if now in analytics
	global $wpdb;
	$in_analytics = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
		$order_id
	) ) > 0;
	
	$result['now_in_analytics'] = $in_analytics;
	
	wp_die( json_encode( $result ) );
} );

// Add admin page for testing
add_action( 'admin_menu', function() {
	add_submenu_page(
		'woocommerce',
		'Test Reepay Analytics Fix',
		'Test Analytics Fix',
		'manage_woocommerce',
		'test-reepay-analytics',
		'test_reepay_analytics_fix'
	);
} );
