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
		add_action( 'admin_notices', array( $this, 'admin_notice_mobilepay_subscriptions_active' ) );
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
	 * Add notifications in admin when active Mobilepay Subscriptions.
	 */
	public function admin_notice_mobilepay_subscriptions_active() {
		$gateways                      = WC()->payment_gateways()->payment_gateways();
		$mobilepay_subscription_active = false;
		if ( $gateways ) {
			foreach ( $gateways as $gateway_key => $gateway ) {
				if ( 'yes' === $gateway->enabled && 'reepay_mobilepay_subscriptions' === $gateway_key ) {
					$mobilepay_subscription_active = true;
				}
			}
		}
		if ( $mobilepay_subscription_active ) {
			?>
			<div class="woo-connect-notice notice notice-error">
				<p>
					<?php _e( 'MobilePay Subscription has been discontinued following the merger of MobilePay and Vipps. Please switch to using Vipps MobilePay Recurring instead.', 'reepay-checkout-gateway' ); ?>
				</p>
			</div>
			<?php
		}
	}
}
