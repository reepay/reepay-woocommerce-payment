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
}
