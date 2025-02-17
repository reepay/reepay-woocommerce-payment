<?php
/**
 * Migration data from MobilePay Subscription to Vipps MobilePay Recurring
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Reepay\Checkout\Utils\LoggingTrait;

defined( 'ABSPATH' ) || exit();

/**
 * Class MigrationMobilepayToVipps
 *
 * @package Reepay\Checkout\Admin
 */
class MigrationMobilepayToVipps {
	use LoggingTrait;

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private string $logging_source = 'reepay-migration-mobilepay-to-vipps';

	/**
	 * Logging data
	 *
	 * @var array
	 */
	private array $logging_data = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'create_submenu' ) );
		add_action( 'wp_ajax_reepay_migration_upload_csv', array( $this, 'upload_csv' ) );
		add_action( 'wp_ajax_reepay_migration_process_batch', array( $this, 'process_batch' ) );
	}

	/**
	 * Register style and scription to admin
	 */
	public function admin_enqueue_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style(
			'migration-mobilepay-vippsmobilepay',
			reepay()->get_setting( 'css_url' ) . 'migration-mobilepay-vippsmobilepay' . $suffix . '.css',
			array(),
			reepay()->get_setting( 'plugin_version' )
		);
		wp_register_script(
			'migration-mobilepay-vippsmobilepay',
			reepay()->get_setting( 'js_url' ) . 'migration-mobilepay-vippsmobilepay' . $suffix . '.js',
			array( 'jquery' ),
			reepay()->get_setting( 'plugin_version' ),
			true
		);
	}

	/**
	 * Register submenu in tools menu
	 */
	public function create_submenu() {
		add_submenu_page(
			'tools.php',
			__( 'Billwerk+ Migration', 'reepay-checkout-gateway' ),
			__( 'Billwerk+ Migration', 'reepay-checkout-gateway' ),
			'manage_options',
			'billwerk-migration',
			array( $this, 'migration_page' ),
			0
		);
	}

	/**
	 * Migration page.
	 */
	public function migration_page() {
		wp_enqueue_style( 'migration-mobilepay-vippsmobilepay' );
		wp_enqueue_script( 'migration-mobilepay-vippsmobilepay' );
		// Localize script to pass data to JavaScript.
		wp_localize_script(
			'migration-mobilepay-vippsmobilepay',
			'migrationData',
			array(
				'upload_csv'        => __( 'Only upload .csv', 'reepay-checkout-gateway' ),
				'confirm_migration' => __( 'Are you sure you want to start the migration?', 'reepay-checkout-gateway' ),
				'choose_file'       => __( 'Choose file before upload', 'reepay-checkout-gateway' ),
				'failed_upload'     => __( 'Failed to upload CSV file.', 'reepay-checkout-gateway' ),
				'processed_success' => __( 'All records processed.', 'reepay-checkout-gateway' ),
				'processed_failed'  => __( 'Failed to process batch.', 'reepay-checkout-gateway' ),
			)
		);
		?>
		<div class="wrap">
			<h1><?php _e( 'Update payment method tokens', 'reepay-checkout-gateway' ); ?></h1>
			<p><?php _e( 'Please ensure to back up your database before proceeding.', 'reepay-checkout-gateway' ); ?></p>
			<p><a href="<?php echo reepay()->get_setting( 'assets_url' ); ?>/download/Update-payment-method-token-sample.csv"><?php _e( 'Sample file' ); ?></a></p>
			<div class="migration-mobilepay-to-vippsmobile-wrap">
				<div class="form">
					<input type="file" name="migration_file" id="migration_file">
					<button type="button" name="migration_button" id="migration_button" class="start-migration button-secondary"><?php _e( 'Start', 'reepay-checkout-gateway' ); ?></button>
				</div>
				<div class="spinners_warp">
					<div class="spinners">
						<img src="<?php echo esc_url( includes_url() . 'js/tinymce/skins/lightgray/img//loader.gif' ); ?>" /> 
						<div class="lable">
							<?php _e( 'Processing...', 'reepay-checkout-gateway' ); ?> <span class="processing-percentage">0%</span>, 
							<?php _e( 'Please wait until it is completed.', 'reepay-checkout-gateway' ); ?>
						</div>
					</div>
				</div>
				<div class="result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Ajax upload csv to database.
	 */
	public function upload_csv() {
		if ( ! empty( $_FILES['migration_file']['tmp_name'] ) ) {

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$csv_file = $_FILES['migration_file']['tmp_name'];

			if ( $wp_filesystem->exists( $csv_file ) ) {
				$file_content = $wp_filesystem->get_contents( $csv_file );
				$lines        = explode( "\n", $file_content );
				$first_line   = $lines[0]; // Get the first line.
				if ( strpos( $first_line, ';' ) !== false ) {
					$delimiter = ';'; // set delimiter to semicolon.
				} else {
					$delimiter = ','; // set delimiter to comma.
				}

				$csv_data = array_map(
					function ( $row ) use ( $delimiter ) {
						return str_getcsv( $row, $delimiter );
					},
					$lines
				);
				update_option( 'reepay_csv_data_migration_mobilepay_to_vipps', $csv_data );
				$total_records = count( $csv_data );
				wp_send_json_success( array( 'total_records' => $total_records ) );
			} else {
				wp_send_json_error();
			}
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Ajax process update data.
	 */
	public function process_batch() {
		global $wpdb;

		$offset   = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$csv_data = get_option( 'reepay_csv_data_migration_mobilepay_to_vipps', array() );

		if ( empty( $csv_data ) ) {
			wp_send_json_error();
		}

		$total_records = count( $csv_data );
		$batch         = array_slice( $csv_data, $offset, 10 );
		foreach ( $batch as $item ) {

			$customer_handle = $item[0];
			if ( empty( $customer_handle ) || 'customer_handle' === $customer_handle ) {
				continue; // Skip if customer handle is empty or header name customer_handle.
			}

			$old_mps_payment_method             = $item[1];
			$new_vipps_recurring_payment_method = $item[3];

			// Query wp_users table.
			$user_query = new \WP_User_Query(
				array(
					'meta_key'   => 'reepay_customer_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $customer_handle, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 1, // Limit to 1 result.
				)
			);

			if ( empty( $user_query->get_results() ) ) {
				continue; // Skip if user not found.
			}

			$user    = $user_query->get_results()[0];
			$user_id = $user->ID;

			// Query wp_woocommerce_payment_tokens table.
			$token_query = $wpdb->get_results( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE user_id = %d AND token = %s",
					$user_id,
					$old_mps_payment_method
				)
			);

			if ( empty( $token_query ) ) {
				continue; // Skip if token not found.
			}

			// Update the token with the new value.
			foreach ( $token_query as $token ) {
				$wpdb->update( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					"{$wpdb->prefix}woocommerce_payment_tokens",
					array(
						'token'      => $new_vipps_recurring_payment_method,
						'gateway_id' => 'reepay_vipps_recurring',
						'type'       => 'Reepay_VR',
					),
					array(
						'user_id'  => $user_id,
						'token_id' => $token->token_id,
					),
					array( '%s', '%s', '%s' ),
					array( '%d', '%d' )
				);

				// Add log update token.
				$this->logging_data[] = array(
					'msg'       => 'Update wp_woocommerce_payment_tokens table',
					'user_id'   => $user_id,
					'token_id'  => $token->token_id,
					'old_token' => $old_mps_payment_method,
					'new_token' => $new_vipps_recurring_payment_method,
				);
			}

			// Update wp_postmeta table for reepay_token.
			$postmeta_updated = $wpdb->update( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}postmeta",
				array( 'meta_value' => $new_vipps_recurring_payment_method ), //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				array(
					'meta_key'   => 'reepay_token', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $old_mps_payment_method, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				),
				array( '%s' ),
				array( '%s', '%s' )
			);

			// Log the update postmeta.
			if ( false !== $postmeta_updated ) {
				$postmeta_id          = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'reepay_token' AND meta_value = %s;",
						$new_vipps_recurring_payment_method
					)
				);
				$this->logging_data[] = array(
					'msg'              => 'Update wp_postmeta table for reepay_token',
					'meta_id'          => $postmeta_id,
					'old_reepay_token' => $old_mps_payment_method,
					'new_reepay_token' => $new_vipps_recurring_payment_method,
				);
			}

			// Update wp_postmeta table for _reepay_token.
			$postmeta_updated = $wpdb->update( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}postmeta",
				array( 'meta_value' => $new_vipps_recurring_payment_method ), //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				array(
					'meta_key'   => '_reepay_token', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $old_mps_payment_method, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				),
				array( '%s' ),
				array( '%s', '%s' )
			);

			// Log the update postmeta.
			if ( false !== $postmeta_updated ) {
				$postmeta_id          = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_reepay_token' AND meta_value = %s;",
						$new_vipps_recurring_payment_method
					)
				);
				$this->logging_data[] = array(
					'msg'              => 'Update wp_postmeta table for _reepay_token',
					'meta_id'          => $postmeta_id,
					'old_reepay_token' => $old_mps_payment_method,
					'new_reepay_token' => $new_vipps_recurring_payment_method,
				);
			}

			// Update wp_wc_orders_meta table for reepay_token.
			$wc_ordermeta_updated = $wpdb->update( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}wc_orders_meta",
				array( 'meta_value' => $new_vipps_recurring_payment_method ), //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				array(
					'meta_key'   => 'reepay_token', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $old_mps_payment_method, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				),
				array( '%s' ),
				array( '%s', '%s' )
			);

			// Log the update wc_orders_meta.
			if ( false !== $wc_ordermeta_updated ) {
				$ordermeta_id         = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = 'reepay_token' AND meta_value = %s;",
						$new_vipps_recurring_payment_method
					)
				);
				$this->logging_data[] = array(
					'msg'              => 'Update wc_orders_meta table for reepay_token',
					'id'               => $ordermeta_id,
					'old_reepay_token' => $old_mps_payment_method,
					'new_reepay_token' => $new_vipps_recurring_payment_method,
				);
			}

			// Update wp_wc_orders_meta table for _reepay_token.
			$wc_ordermeta_updated = $wpdb->update( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}wc_orders_meta",
				array( 'meta_value' => $new_vipps_recurring_payment_method ), //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				array(
					'meta_key'   => '_reepay_token', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $old_mps_payment_method, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				),
				array( '%s' ),
				array( '%s', '%s' )
			);

			// Log the update wc_orders_meta.
			if ( false !== $wc_ordermeta_updated ) {
				$ordermeta_id         = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_reepay_token' AND meta_value = %s;",
						$new_vipps_recurring_payment_method
					)
				);
				$this->logging_data[] = array(
					'msg'              => 'Update wc_orders_meta table for _reepay_token',
					'id'               => $ordermeta_id,
					'old_reepay_token' => $old_mps_payment_method,
					'new_reepay_token' => $new_vipps_recurring_payment_method,
				);
			}
		}

		$this->log( var_export( $this->logging_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

		$has_more = ( $offset + 10 ) < $total_records;
		wp_send_json_success(
			array(
				'processed'     => $offset + count( $batch ),
				'has_more'      => $has_more,
				'total_records' => $total_records,
			)
		);
	}
}
?>
