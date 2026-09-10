<?php
/**
 * Dismissible admin notice for completed-but-unsettled orders.
 *
 * @package Reepay\Checkout\Actions
 */

namespace Reepay\Checkout\Actions;

use Reepay\Checkout\OrderFlow\UnsettledOrdersMonitor;

defined( 'ABSPATH' ) || exit();

/**
 * Class UnsettledOrdersNotice
 *
 * @package Reepay\Checkout\Actions
 */
class UnsettledOrdersNotice {
	/**
	 * User meta key storing the UnsettledOrdersMonitor::GENERATION_OPTION value that was current
	 * the last time this admin dismissed the notice.
	 *
	 * @var string
	 */
	public const DISMISSED_META = 'reepay_unsettled_orders_dismissed';

	/**
	 * Notice constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_reepay_dismiss_unsettled_orders_notice', array( $this, 'dismiss' ) );
	}

	/**
	 * Render the dismissible notice on all wp-admin pages. Lists every order in the current
	 * result — the only cap is the underlying UnsettledOrdersFinder::MAX_RESULTS (500) safety
	 * limit reflected in $result['capped'], not a separate display truncation.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$result = UnsettledOrdersMonitor::get_last_result();

		if ( empty( $result['order_ids'] ) ) {
			return;
		}

		if ( $this->is_dismissed() ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible" id="reepay-unsettled-orders-notice" data-nonce="%1$s"><p>%2$s</p><ul>%3$s</ul>%4$s</div>',
			esc_attr( wp_create_nonce( 'reepay_dismiss_unsettled_orders_notice' ) ),
			esc_html__( 'The following orders for payment via the Frisbii gateway have status as Completed but have no payment registered:', 'reepay-checkout-gateway' ),
			$this->order_list_items( $result['order_ids'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url()/esc_html() in order_list_items().
			$result['capped'] ? '<p>' . esc_html__( 'and 500+ more.', 'reepay-checkout-gateway' ) . '</p>' : ''
		);
	}

	/**
	 * Build the `<li>` list of every affected order, each linking to its order edit screen.
	 *
	 * @param int[] $order_ids affected order ids.
	 *
	 * @return string
	 */
	private function order_list_items( array $order_ids ): string {
		$items = array();

		foreach ( $order_ids as $order_id ) {
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

		return implode( '', $items );
	}

	/**
	 * Whether the current user already dismissed the notice as of the current generation (i.e.
	 * no genuinely new order has appeared since they last dismissed it).
	 *
	 * @return bool
	 */
	private function is_dismissed(): bool {
		$dismissed_generation = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );

		// Reject anything that isn't a plain non-negative integer string — this covers both a
		// user who never dismissed (empty string) and, importantly, any site that already had a
		// leftover value from the old hash-based dismissal design (a hex string, e.g.
		// "5d41402abc4b...").
		if ( ! ctype_digit( (string) $dismissed_generation ) ) {
			return false;
		}

		return (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 ) === (int) $dismissed_generation;
	}

	/**
	 * Enqueue the inline dismiss-click handler.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'jquery' );

		$script = <<<'JS'
			jQuery( function ( $ ) {
				$( document ).on( 'click', '#reepay-unsettled-orders-notice .notice-dismiss', function () {
					var $notice = $( '#reepay-unsettled-orders-notice' );
					$.post( ajaxurl, {
						action: 'reepay_dismiss_unsettled_orders_notice',
						nonce: $notice.data( 'nonce' )
					} );
				} );
			} );
JS;

		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * AJAX handler: persist dismissal of the current generation for the current admin.
	 */
	public function dismiss() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}

		check_ajax_referer( 'reepay_dismiss_unsettled_orders_notice', 'nonce' );

		update_user_meta( get_current_user_id(), self::DISMISSED_META, (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 ) );

		wp_send_json_success();
	}
}
