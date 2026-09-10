<?php
/**
 * Sends the daily unsettled-orders email.
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

defined( 'ABSPATH' ) || exit();

/**
 * Class UnsettledOrdersMailer
 *
 * @package Reepay\Checkout\OrderFlow
 */
class UnsettledOrdersMailer {
	/**
	 * Send the unsettled-orders notification email.
	 * Lists every order in $result['order_ids']
	 *
	 * @param array{order_ids: int[], capped: bool} $result detection result.
	 *
	 * @return bool
	 */
	public function send( array $result ): bool {
		if ( empty( $result['order_ids'] ) ) {
			return false;
		}

		// reepay()->get_setting() has its own hardcoded whitelist of keys and does not include
		// 'failed_webhooks_email' — it would always return null here. The gateway's own
		// WC_Settings_API::get_option() reads the live $this->settings array instead, which is
		// what actually reflects saved changes.
		$to = reepay()->gateways()->checkout()->get_option( 'failed_webhooks_email' );

		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$subject = __( 'Some completed orders in your webshop are unpaid', 'reepay-checkout-gateway' );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $to, $subject, $this->build_body( $result ), $headers );
	}

	/**
	 * Build the HTML email body listing every affected order, each linking to its order edit screen.
	 *
	 * @param array{order_ids: int[], capped: bool} $result detection result.
	 *
	 * @return string
	 */
	private function build_body( array $result ): string {
		$intro = sprintf(
			/* translators: %s: webshop (site) name. */
			__( 'The following orders on %s for payment via the Frisbii gateway have status as Completed but have no payment registered:', 'reepay-checkout-gateway' ),
			get_bloginfo( 'name' )
		);

		$items = array();

		foreach ( $result['order_ids'] as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$items[] = sprintf(
				'<li><a href="%1$s">#%2$s</a></li>',
				esc_url( $order->get_edit_order_url() ),
				esc_html( $order->get_order_number() )
			);
		}

		$more = '';

		if ( $result['capped'] ) {
			$more = '<p>' . esc_html__( 'and 500+ more.', 'reepay-checkout-gateway' ) . '</p>';
		}

		return sprintf(
			'<p>%1$s</p><ul>%2$s</ul>%3$s',
			esc_html( $intro ),
			implode( '', $items ),
			$more
		);
	}
}
