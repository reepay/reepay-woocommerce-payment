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
	 * User meta key storing the hash of the affected-order set the current admin dismissed.
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
	 * result the only cap is the underlying
	 * UnsettledOrdersFinder::MAX_RESULTS (500) safety limit reflected in $result['capped'], not a
	 * separate display truncation.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$result = UnsettledOrdersMonitor::get_last_result();

		if ( empty( $result['order_ids'] ) ) {
			return;
		}

		$hash = $this->hash( $result['order_ids'] );

		if ( $this->is_dismissed( $hash ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible" id="reepay-unsettled-orders-notice" data-nonce="%1$s" data-hash="%2$s"><p>%3$s</p><ul>%4$s</ul>%5$s</div>',
			esc_attr( wp_create_nonce( 'reepay_dismiss_unsettled_orders_notice' ) ),
			esc_attr( $hash ),
			esc_html__( 'The following orders have status as Completed but have no payment registered:', 'reepay-checkout-gateway' ),
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
	 * Hash an order-ID set for dismissal comparison.
	 *
	 * @param int[] $order_ids order ids.
	 *
	 * @return string
	 */
	private function hash( array $order_ids ): string {
		sort( $order_ids );

		return md5( (string) wp_json_encode( $order_ids ) );
	}

	/**
	 * Whether the current user already dismissed this exact affected-order set.
	 *
	 * @param string $hash hash of the current affected-order set.
	 *
	 * @return bool
	 */
	private function is_dismissed( string $hash ): bool {
		$dismissed_hash = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );

		return $hash === $dismissed_hash;
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
						nonce: $notice.data( 'nonce' ),
						hash: $notice.data( 'hash' )
					} );
				} );
			} );
JS;

		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * AJAX handler: persist dismissal of the current affected-order set for the current admin.
	 */
	public function dismiss() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}

		check_ajax_referer( 'reepay_dismiss_unsettled_orders_notice', 'nonce' );

		$hash = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';

		update_user_meta( get_current_user_id(), self::DISMISSED_META, $hash );

		wp_send_json_success();
	}
}
