<?php
/**
 * Enable Debug Features for Reepay Analytics
 * 
 * This file enables various debug features to help troubleshoot analytics issues
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable SQL Query Logging
 */
if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

/**
 * Custom SQL Logger for Analytics Debug
 */
class ReepayAnalyticsLogger {
	
	private static $queries = array();
	private static $enabled = false;
	
	/**
	 * Start logging queries
	 */
	public static function start_logging() {
		if ( self::$enabled ) {
			return;
		}
		
		self::$enabled = true;
		self::$queries = array();
		
		add_filter( 'query', array( __CLASS__, 'log_query' ), 999 );
	}
	
	/**
	 * Stop logging queries
	 */
	public static function stop_logging() {
		self::$enabled = false;
		remove_filter( 'query', array( __CLASS__, 'log_query' ), 999 );
	}
	
	/**
	 * Log a query
	 */
	public static function log_query( $query ) {
		if ( ! self::$enabled ) {
			return $query;
		}
		
		// Only log queries related to orders or analytics
		if ( strpos( $query, 'shop_order' ) !== false || 
			 strpos( $query, 'wc_order_stats' ) !== false ||
			 strpos( $query, 'postmeta' ) !== false ||
			 strpos( $query, '_payment_method' ) !== false ) {
			
			self::$queries[] = array(
				'query' => $query,
				'time' => microtime( true ),
				'backtrace' => wp_debug_backtrace_summary( null, 0, false )
			);
		}
		
		return $query;
	}
	
	/**
	 * Get logged queries
	 */
	public static function get_queries() {
		return self::$queries;
	}
	
	/**
	 * Clear logged queries
	 */
	public static function clear_queries() {
		self::$queries = array();
	}
}

/**
 * Analytics Debug Dashboard
 */
function reepay_analytics_debug_dashboard() {
	echo "<h2>Reepay Analytics Debug Dashboard</h2>";
	
	// Check debug status
	echo "<h3>Debug Status</h3>";
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Setting</th><th>Status</th><th>Description</th></tr>";
	
	$debug_settings = array(
		'WP_DEBUG' => array(
			'value' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'description' => 'WordPress debug mode'
		),
		'SAVEQUERIES' => array(
			'value' => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
			'description' => 'SQL query logging'
		),
		'WP_DEBUG_LOG' => array(
			'value' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'description' => 'Debug log file'
		),
		'Analytics Enabled' => array(
			'value' => get_option( 'woocommerce_analytics_enabled', 'yes' ) === 'yes',
			'description' => 'WooCommerce Analytics'
		)
	);
	
	foreach ( $debug_settings as $setting => $data ) {
		echo "<tr>";
		echo "<td>{$setting}</td>";
		echo "<td style='color: " . ( $data['value'] ? 'green' : 'red' ) . ";'><strong>" . ( $data['value'] ? 'ENABLED' : 'DISABLED' ) . "</strong></td>";
		echo "<td>{$data['description']}</td>";
		echo "</tr>";
	}
	echo "</table>";
	
	// Quick actions
	echo "<h3>Quick Actions</h3>";
	echo "<p>";
	echo "<a href='" . admin_url( 'admin.php?page=debug-reepay-sql' ) . "' class='button'>Debug SQL Queries</a> ";
	echo "<a href='" . admin_url( 'admin.php?page=debug-woocommerce-analytics' ) . "' class='button'>Debug WC Analytics</a> ";
	echo "<a href='" . admin_url( 'admin.php?page=test-reepay-analytics' ) . "' class='button'>Test Analytics Fix</a> ";
	echo "</p>";
	
	// Live query monitoring
	echo "<h3>Live Query Monitor</h3>";
	echo "<p>Click the button below to start monitoring SQL queries in real-time:</p>";
	echo "<button id='start-monitoring' class='button button-primary'>Start Monitoring</button> ";
	echo "<button id='stop-monitoring' class='button'>Stop Monitoring</button> ";
	echo "<button id='clear-queries' class='button'>Clear Queries</button>";
	
	echo "<div id='query-monitor' style='background: #f0f0f0; padding: 10px; margin: 10px 0; max-height: 400px; overflow-y: auto; display: none;'>";
	echo "<h4>Monitored Queries:</h4>";
	echo "<div id='query-list'></div>";
	echo "</div>";
	
	// JavaScript for live monitoring
	?>
	<script>
	jQuery(document).ready(function($) {
		var monitoring = false;
		var queryCount = 0;
		
		$('#start-monitoring').click(function() {
			monitoring = true;
			queryCount = 0;
			$('#query-monitor').show();
			$('#query-list').html('<p>Monitoring started... Execute some WooCommerce operations to see queries.</p>');
			
			// Start AJAX polling for queries
			pollQueries();
		});
		
		$('#stop-monitoring').click(function() {
			monitoring = false;
			$('#query-list').append('<p><strong>Monitoring stopped.</strong></p>');
		});
		
		$('#clear-queries').click(function() {
			$('#query-list').html('');
			queryCount = 0;
		});
		
		function pollQueries() {
			if (!monitoring) return;
			
			$.post(ajaxurl, {
				action: 'get_monitored_queries',
				_wpnonce: '<?php echo wp_create_nonce( 'monitor_queries' ); ?>'
			}, function(response) {
				if (response.success && response.data.length > queryCount) {
					var newQueries = response.data.slice(queryCount);
					newQueries.forEach(function(query, index) {
						var queryHtml = '<div style="border-bottom: 1px solid #ccc; padding: 5px; margin: 5px 0;">';
						queryHtml += '<strong>Query ' + (queryCount + index + 1) + ':</strong><br>';
						queryHtml += '<code style="background: white; padding: 2px;">' + query.query + '</code><br>';
						queryHtml += '<small>Backtrace: ' + query.backtrace + '</small>';
						queryHtml += '</div>';
						$('#query-list').append(queryHtml);
					});
					queryCount = response.data.length;
					
					// Auto-scroll to bottom
					$('#query-monitor').scrollTop($('#query-monitor')[0].scrollHeight);
				}
				
				// Continue polling
				setTimeout(pollQueries, 2000);
			});
		}
	});
	</script>
	<?php
	
	// Recent analytics activity
	echo "<h3>Recent Analytics Activity</h3>";
	global $wpdb;
	
	$recent_analytics = $wpdb->get_results( "
		SELECT order_id, status, date_created, total_sales
		FROM {$wpdb->prefix}wc_order_stats
		ORDER BY date_created DESC
		LIMIT 10
	" );
	
	if ( ! empty( $recent_analytics ) ) {
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
		echo "<tr><th>Order ID</th><th>Status</th><th>Date Created</th><th>Total Sales</th></tr>";
		foreach ( $recent_analytics as $entry ) {
			echo "<tr>";
			echo "<td>#{$entry->order_id}</td>";
			echo "<td>{$entry->status}</td>";
			echo "<td>{$entry->date_created}</td>";
			echo "<td>{$entry->total_sales}</td>";
			echo "</tr>";
		}
		echo "</table>";
	} else {
		echo "<p>No recent analytics data found.</p>";
	}
}

// AJAX handler for query monitoring
add_action( 'wp_ajax_get_monitored_queries', function() {
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'monitor_queries' ) ) {
		wp_die( json_encode( array( 'success' => false, 'error' => 'Security check failed' ) ) );
	}
	
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( json_encode( array( 'success' => false, 'error' => 'Insufficient permissions' ) ) );
	}
	
	$queries = ReepayAnalyticsLogger::get_queries();
	wp_die( json_encode( array( 'success' => true, 'data' => $queries ) ) );
} );

// Add admin page
add_action( 'admin_menu', function() {
	add_submenu_page(
		'woocommerce',
		'Reepay Debug Dashboard',
		'Debug Dashboard',
		'manage_woocommerce',
		'reepay-debug-dashboard',
		'reepay_analytics_debug_dashboard'
	);
} );

// Auto-start query logging if debug is enabled
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	add_action( 'init', function() {
		ReepayAnalyticsLogger::start_logging();
	} );
}
