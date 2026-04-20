<?php
/**
 * Checkout actions
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Actions;

/**
 * Class Admin
 *
 * @package Reepay\Checkout
 */
class Admin {
	/**
	 * Admin constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notice_api_action' ) );
		add_action( 'admin_notices', array( $this, 'billwerk_pay_hpos_check' ) );
		add_action( 'admin_notices', array( $this, 'debug_plugin_status' ) );
	}

	/**
	 * Add notifications in admin for api actions.
	 */
	public function admin_notice_api_action() {
		$error   = get_transient( 'reepay_api_action_error' );
		$success = get_transient( 'reepay_api_action_success' );

		if ( ! empty( $error ) ) :
			?>
			<div class="error notice is-dismissible">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
			<?php
			set_transient( 'reepay_api_action_error', null, 1 );
		endif;

		if ( ! empty( $success ) ) :
			?>
			<div class="notice-success notice is-dismissible">
				<p><?php echo esc_html( $success ); ?></p>
			</div>
			<?php
			set_transient( 'reepay_api_action_success', null, 1 );
		endif;
	}

	/**
	 * Check if HPOS is available and display an admin warning if not.
	 */
	public function billwerk_pay_hpos_check() {
		// Only run in the admin dashboard.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Ensure WooCommerce is available.
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		// Check WooCommerce version.
		if ( version_compare( WC()->version, '7.1', '<' ) ) {
			echo '<div class="notice notice-error"><p>' .
				__( 'Frisbii works best with High Performance Order Storage. You must update WooCommerce to at least version 7.1 to have this feature.', 'reepay-checkout-gateway' ) .
				'</p></div>';
			return;
		}

		// Check if HPOS is enabled.
		$hpos_enabled = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		if ( ! $hpos_enabled ) {
			echo '<div class="notice notice-error"><p>' .
				__( 'Frisbii works best with High Performance Order Storage. You can activate it in the WooCommerce settings under the "Advanced" tab, "Features" sub-tab.', 'reepay-checkout-gateway' ) .
				'</p></div>';
		}
	}

	/**
	 * Display a temporary full plugin status debug notice on the plugins page.
	 * Covers all classes in the Plugin namespace: WoocommerceExists, WoocommerceHPOS,
	 * LifeCycle, UpdateDB, and Statistics.
	 * Enable with /wp-admin/plugins.php?reepay_hpos_debug=1
	 */
	public function debug_plugin_status() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) || ! function_exists( 'reepay' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['reepay_hpos_debug'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['reepay_hpos_debug'] ) ) ) {
			return;
		}

		$rows = array();

		// --- Plugin Info ---
		$plugin_version   = (string) reepay()->get_setting( 'plugin_version' );
		$plugin_basename  = (string) reepay()->get_setting( 'plugin_basename' );
		$test_mode        = reepay()->get_setting( 'test_mode' );
		$private_key      = reepay()->get_setting( 'private_key' );
		$private_key_test = reepay()->get_setting( 'private_key_test' );

		$rows[] = '<strong>' . esc_html__( 'Plugin Info', 'reepay-checkout-gateway' ) . '</strong>';
		$rows[] = esc_html( 'version: ' . $plugin_version );
		$rows[] = esc_html( 'basename: ' . $plugin_basename );
		$rows[] = esc_html( 'test mode: ' . ( 'yes' === $test_mode ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'private key (live) configured: ' . ( ! empty( $private_key ) ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'private key (test) configured: ' . ( ! empty( $private_key_test ) ? 'yes' : 'no' ) );

		// --- WoocommerceExists ---
		$woo_class_exists   = class_exists( 'WooCommerce', false );
		$wc_abspath_defined = defined( 'WC_ABSPATH' );
		$woo_version        = ( $woo_class_exists && function_exists( 'WC' ) && WC() ) ? (string) WC()->version : 'n/a';

		$rows[] = '';
		$rows[] = '<strong>' . esc_html__( 'WooCommerce (WoocommerceExists)', 'reepay-checkout-gateway' ) . '</strong>';
		$rows[] = esc_html( 'WooCommerce class exists: ' . ( $woo_class_exists ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'WC_ABSPATH defined: ' . ( $wc_abspath_defined ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'WooCommerce activated: ' . ( $woo_class_exists && $wc_abspath_defined ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'WooCommerce version: ' . $woo_version );

		// --- WoocommerceHPOS + FeaturesUtil ---
		$plugin_file          = (string) reepay()->get_setting( 'plugin_file' );
		$hpos_enabled         = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		$features_util_exists = class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' );
		$order_util_exists    = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' );

		$rows[] = '';
		$rows[] = '<strong>' . esc_html__( 'HPOS / FeaturesUtil (WoocommerceHPOS + OrderTable + hpos.php)', 'reepay-checkout-gateway' ) . '</strong>';
		$rows[] = esc_html( 'plugin_file (used in declare_compatibility): ' . $plugin_file );
		$rows[] = esc_html( 'plugin_basename (used in get_compatible_features): ' . $plugin_basename );
		$rows[] = esc_html( 'hpos enabled (DB option): ' . ( $hpos_enabled ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'FeaturesUtil available: ' . ( $features_util_exists ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'OrderUtil available: ' . ( $order_util_exists ? 'yes' : 'no' ) );

		if ( $order_util_exists ) {
			$hpos_actually_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			$rows[]                = esc_html( 'OrderUtil::custom_orders_table_usage_is_enabled(): ' . ( $hpos_actually_enabled ? 'yes' : 'no' ) );
		}

		if ( $features_util_exists ) {
			$compatibility = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin( $plugin_basename );
			$compatible    = $compatibility['compatible'] ?? array();
			$incompatible  = $compatibility['incompatible'] ?? array();
			$uncertain     = $compatibility['uncertain'] ?? array();
			$rows[]        = esc_html( 'custom_order_tables compatible: ' . ( in_array( 'custom_order_tables', $compatible, true ) ? 'yes' : 'no' ) );
			$rows[]        = esc_html( 'custom_order_tables incompatible: ' . ( in_array( 'custom_order_tables', $incompatible, true ) ? 'yes' : 'no' ) );
			$rows[]        = esc_html( 'custom_order_tables uncertain: ' . ( in_array( 'custom_order_tables', $uncertain, true ) ? 'yes' : 'no' ) );
		}

		// --- WooCommerce Blocks / cart_checkout_blocks (WooBlocksIntegration) ---
		// NOTE: The plugin registers payment methods with WooCommerce Blocks but does NOT
		// call FeaturesUtil::declare_compatibility('cart_checkout_blocks', ...) anywhere.
		// WooCommerce will therefore flag this plugin as NOT declared for cart_checkout_blocks.
		$blocks_package_exists   = class_exists( '\Automattic\WooCommerce\Blocks\Package' );
		$blocks_registry_exists  = class_exists( '\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' );

		$rows[] = '';
		$rows[] = '<strong>' . esc_html__( 'WooCommerce Blocks (WooBlocksIntegration)', 'reepay-checkout-gateway' ) . '</strong>';
		$rows[] = esc_html( 'Blocks\Package available: ' . ( $blocks_package_exists ? 'yes' : 'no' ) );
		$rows[] = esc_html( 'Blocks\PaymentMethodRegistry available: ' . ( $blocks_registry_exists ? 'yes' : 'no' ) );

		if ( $features_util_exists ) {
			// Reuse $compatibility already fetched above.
			$rows[] = esc_html( 'cart_checkout_blocks compatible: ' . ( in_array( 'cart_checkout_blocks', $compatible, true ) ? 'yes' : 'no' ) );
			$rows[] = esc_html( 'cart_checkout_blocks incompatible: ' . ( in_array( 'cart_checkout_blocks', $incompatible, true ) ? 'yes' : 'no' ) );
			$rows[] = esc_html( 'cart_checkout_blocks uncertain: ' . ( in_array( 'cart_checkout_blocks', $uncertain, true ) ? 'yes' : 'no' ) );
			$rows[] = '<strong style="color:#d63638">' . esc_html__( 'WARNING: cart_checkout_blocks is never declared — plugin uses WooCommerce Blocks but is missing declare_compatibility()', 'reepay-checkout-gateway' ) . '</strong>';
		}

		// --- UpdateDB / LifeCycle ---
		$stored_db_version = (string) get_option( 'woocommerce_reepay_version', 'not set' );
		$latest_db_version = \Reepay\Checkout\Plugin\UpdateDB::DB_VERSION;
		$db_update_needed  = ( 'not set' !== $stored_db_version && version_compare( $stored_db_version, $latest_db_version, '<' ) ) ? 'yes' : 'no';

		$rows[] = '';
		$rows[] = '<strong>' . esc_html__( 'Database / LifeCycle (UpdateDB)', 'reepay-checkout-gateway' ) . '</strong>';
		$rows[] = esc_html( 'stored db version: ' . $stored_db_version );
		$rows[] = esc_html( 'latest db version: ' . $latest_db_version );
		$rows[] = esc_html( 'db update needed: ' . $db_update_needed );

		// --- Statistics ---
		$stats_active = \Reepay\Checkout\Plugin\Statistics::get_instance() instanceof \Reepay\Checkout\Plugin\Statistics;

		$rows[] = '';
		$rows[] = '<strong>' . esc_html__( 'Statistics', 'reepay-checkout-gateway' ) . '</strong>';
		$rows[] = esc_html( 'Statistics instance active: ' . ( $stats_active ? 'yes' : 'no' ) );

		echo '<div class="notice notice-info"><p>' .
			implode( '<br>', $rows ) .
			'</p></div>';
	}
}
