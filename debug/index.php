<?php
/**
 * Debug Tools Index
 * 
 * This file loads all debug tools for Reepay Analytics
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load debug tools
require_once __DIR__ . '/enable-debug.php';
require_once __DIR__ . '/sql-debug.php';
require_once __DIR__ . '/analytics-debug.php';
require_once __DIR__ . '/test-analytics-fix.php';

/**
 * Debug Tools Overview Page
 */
function reepay_debug_tools_overview() {
	echo "<h2>Reepay Debug Tools Overview</h2>";
	
	echo "<div style='background: #f0f0f0; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa;'>";
	echo "<h3>Available Debug Tools</h3>";
	echo "<p>These tools help diagnose and fix issues with WooCommerce Analytics and Reepay orders.</p>";
	echo "</div>";
	
	// Tool cards
	$tools = array(
		array(
			'title' => 'Debug Dashboard',
			'description' => 'Main dashboard with debug status, live query monitoring, and quick actions.',
			'url' => admin_url( 'admin.php?page=reepay-debug-dashboard' ),
			'icon' => '📊'
		),
		array(
			'title' => 'SQL Debug',
			'description' => 'Analyze SQL queries, table structures, and database operations.',
			'url' => admin_url( 'admin.php?page=debug-reepay-sql' ),
			'icon' => '🔍'
		),
		array(
			'title' => 'Analytics Debug',
			'description' => 'Debug WooCommerce Analytics hooks, classes, and data synchronization.',
			'url' => admin_url( 'admin.php?page=debug-woocommerce-analytics' ),
			'icon' => '📈'
		),
		array(
			'title' => 'Test Analytics Fix',
			'description' => 'Test the analytics fix implementation and force sync orders.',
			'url' => admin_url( 'admin.php?page=test-reepay-analytics' ),
			'icon' => '🧪'
		)
	);
	
	echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;'>";
	
	foreach ( $tools as $tool ) {
		echo "<div style='background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
		echo "<h3 style='margin-top: 0;'>{$tool['icon']} {$tool['title']}</h3>";
		echo "<p>{$tool['description']}</p>";
		echo "<a href='{$tool['url']}' class='button button-primary'>Open Tool</a>";
		echo "</div>";
	}
	
	echo "</div>";
	
	// Quick status check
	echo "<h3>Quick Status Check</h3>";
	
	global $wpdb;
	
	// Check debug settings
	$debug_status = array(
		'WP_DEBUG' => defined( 'WP_DEBUG' ) && WP_DEBUG,
		'SAVEQUERIES' => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		'Analytics Enabled' => get_option( 'woocommerce_analytics_enabled', 'yes' ) === 'yes',
		'Analytics Table Exists' => $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix . 'wc_order_stats' ) ) !== null,
	);
	
	echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
	echo "<tr><th>Setting</th><th>Status</th></tr>";
	
	foreach ( $debug_status as $setting => $status ) {
		$color = $status ? 'green' : 'red';
		$text = $status ? 'OK' : 'ISSUE';
		echo "<tr><td>{$setting}</td><td style='color: {$color}; font-weight: bold;'>{$text}</td></tr>";
	}
	
	echo "</table>";
	
	// Recent Reepay orders
	echo "<h3>Recent Reepay Orders</h3>";
	
	$recent_orders = $wpdb->get_results( "
		SELECT p.ID, p.post_status, p.post_date, pm.meta_value as payment_method
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'shop_order'
		AND pm.meta_key = '_payment_method'
		AND pm.meta_value LIKE 'reepay%'
		ORDER BY p.post_date DESC
		LIMIT 5
	" );
	
	if ( ! empty( $recent_orders ) ) {
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
		echo "<tr><th>Order ID</th><th>Status</th><th>Date</th><th>In Analytics</th></tr>";
		
		foreach ( $recent_orders as $order ) {
			$in_analytics = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
				$order->ID
			) ) > 0;
			
			echo "<tr>";
			echo "<td>#{$order->ID}</td>";
			echo "<td>{$order->post_status}</td>";
			echo "<td>{$order->post_date}</td>";
			echo "<td style='color: " . ( $in_analytics ? 'green' : 'red' ) . ";'>" . ( $in_analytics ? 'YES' : 'NO' ) . "</td>";
			echo "</tr>";
		}
		
		echo "</table>";
	} else {
		echo "<p>No recent Reepay orders found.</p>";
	}
	
	// Instructions
	echo "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border: 1px solid #ffeaa7; border-radius: 5px;'>";
	echo "<h3>🚀 Getting Started</h3>";
	echo "<ol>";
	echo "<li><strong>Enable Debug Mode:</strong> Add <code>define('WP_DEBUG', true);</code> and <code>define('SAVEQUERIES', true);</code> to wp-config.php</li>";
	echo "<li><strong>Start with Dashboard:</strong> Use the Debug Dashboard for real-time monitoring</li>";
	echo "<li><strong>Check SQL Queries:</strong> Use SQL Debug to see what queries are being generated</li>";
	echo "<li><strong>Test Analytics:</strong> Use Test Analytics Fix to verify the fix is working</li>";
	echo "<li><strong>Monitor Hooks:</strong> Use Analytics Debug to check if hooks are properly registered</li>";
	echo "</ol>";
	echo "</div>";
}

// Add main debug tools page
add_action( 'admin_menu', function() {
	add_submenu_page(
		'woocommerce',
		'Reepay Debug Tools',
		'🔧 Debug Tools',
		'manage_woocommerce',
		'reepay-debug-tools',
		'reepay_debug_tools_overview'
	);
} );
