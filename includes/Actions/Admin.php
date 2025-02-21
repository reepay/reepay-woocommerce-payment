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
		if ( ! is_admin() ) {
			return;
		}

		// Ensure WooCommerce is available.
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}

		// Check WooCommerce version.
		if ( version_compare( WC()->version, '7.1', '<' ) ) {
			echo '<div class="notice notice-error"><p>' .
				__( 'Critical Error: Billwerk+ require High Performance Order Storage. You must update WooCommerce to at least version 7.1 to have this feature.', 'reepay-checkout-gateway' ) .
				'</p></div>';
			return;
		}

		// Check if HPOS is enabled. (Assumes an option exists; adjust as needed.).
		if ( ! rp_hpos_enabled() ) {
			echo '<div class="notice notice-error"><p>' .
				__( 'Critical Error: Billwerk+ require High Performance Order Storage. You must activate it in the WooCommerce settings under the "Advanced" tab, "Features" sub-tab.', 'reepay-checkout-gateway' ) .
				'</p></div>';
		}
	}
}
