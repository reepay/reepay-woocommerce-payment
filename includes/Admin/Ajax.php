<?php
/**
 * Admin ajax actions
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Exception;
use WC_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Ajax
 *
 * @package Reepay\Checkout\Admin
 */
class Ajax {
	/**
	 * Actions prefix
	 *
	 * @var string
	 */
	const PREFIX = 'reepay';

	/**
	 * Actions list
	 *
	 * @var array
	 */
	const ACTIONS = array(
		'capture',
		'cancel',
		'refund',
		'capture_partly',
		'refund_partly',
		'set_complete_settle_transient',
	);

	/**
	 * Ajax constructor.
	 */
	public function __construct() {
		foreach ( static::ACTIONS as $action ) {
			add_action( 'wp_ajax_' . static::PREFIX . '_' . $action, array( $this, $action ), 10, 0 );
		}
	}

	/**
	 * Exit if wrong nonce or insufficient permissions
	 *
	 * @param string $action action to verify.
	 *
	 * @return bool
	 */
	private function verify_nonce( $action = 'nonce' ): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', $action ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		return true;
	}

	/**
	 * Action for Capture
	 */
	public function capture() {
		$this->verify_nonce();

		if ( empty( $_REQUEST['order_id'] ) ) {
			wp_send_json_error( __( 'Order id not specified', 'reepay-checkout-gateway' ) );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

		// Security: Validate order exists and belongs to Reepay.
		if ( ! $order || ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Invalid order', 'reepay-checkout-gateway' ) );
		}

		// Security: Verify order is paid via Reepay.
		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			wp_send_json_error( __( 'Order not paid via Reepay', 'reepay-checkout-gateway' ) );
		}

		try {
			$gateway = rp_get_payment_method( $order );
			$gateway->capture_payment( $order, $order->get_total() );
			wp_send_json_success( __( 'Capture success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function cancel() {
		$this->verify_nonce();

		if ( empty( $_REQUEST['order_id'] ) ) {
			wp_send_json_error( __( 'Order id not specified', 'reepay-checkout-gateway' ) );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

		// Security: Validate order exists and belongs to Reepay.
		if ( ! $order || ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Invalid order', 'reepay-checkout-gateway' ) );
		}

		// Security: Verify order is paid via Reepay.
		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			wp_send_json_error( __( 'Order not paid via Reepay', 'reepay-checkout-gateway' ) );
		}

		// Check if the order is already cancelled.
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			wp_send_json_success( __( 'Order already cancelled.', 'reepay-checkout-gateway' ) );

			return;
		}

		try {
			$gateway = rp_get_payment_method( $order );

			if ( $gateway->can_cancel( $order ) ) {
				$gateway->cancel_payment( $order );
			}

			$order->update_meta_data( '_reepay_order_cancelled', 1 );
			$order->save_meta_data();

			wp_send_json_success( __( 'Cancel success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function refund() {
		$this->verify_nonce();

		if ( empty( $_REQUEST['order_id'] ) ) {
			wp_send_json_error( __( 'Order id not specified', 'reepay-checkout-gateway' ) );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

		// Security: Validate order exists and belongs to Reepay.
		if ( ! $order || ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Invalid order', 'reepay-checkout-gateway' ) );
		}

		// Security: Verify order is paid via Reepay.
		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			wp_send_json_error( __( 'Order not paid via Reepay', 'reepay-checkout-gateway' ) );
		}

		$amount = ! empty( $_REQUEST['amount'] ) ? $this->get_amount_or_exit( $order ) : false;

		try {
			$gateway = rp_get_payment_method( $order );

			if ( empty( $gateway ) ) {
				wp_send_json_error( __( 'Payment method not found at the order', 'reepay-checkout-gateway' ) );
			}

			$gateway->refund_payment( $order, $amount );

			wp_send_json_success( __( 'Refund success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Get the order from the request or exit with a JSON error.
	 *
	 * @return WC_Order
	 */
	private function get_reepay_order_or_exit(): WC_Order {
		if ( empty( $_REQUEST['order_id'] ) ) {
			wp_send_json_error( __( 'Order id not specified', 'reepay-checkout-gateway' ) );
		}

		$order = wc_get_order( (int) wc_clean( $_REQUEST['order_id'] ) );

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Invalid order', 'reepay-checkout-gateway' ) );
		}

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			wp_send_json_error( __( 'Order not paid via Reepay', 'reepay-checkout-gateway' ) );
		}

		return $order;
	}

	/**
	 * Parse the amount from the request in the order currency or exit with a JSON error.
	 *
	 * @param WC_Order $order order the amount belongs to.
	 *
	 * @return float
	 */
	private function get_amount_or_exit( WC_Order $order ): float {
		$raw = isset( $_REQUEST['amount'] ) ? wc_clean( wp_unslash( $_REQUEST['amount'] ) ) : '';

		if ( ! is_string( $raw ) || ! is_numeric( wc_format_decimal( $raw ) ) ) {
			wp_send_json_error( __( 'Invalid amount', 'reepay-checkout-gateway' ) );
		}

		$decimals = (int) log10( rp_get_currency_multiplier( $order->get_currency() ) );
		$amount   = round( (float) wc_format_decimal( $raw ), $decimals );

		if ( $amount <= 0 ) {
			wp_send_json_error( __( 'Amount must be greater than 0.', 'reepay-checkout-gateway' ) );
		}

		return $amount;
	}

	/**
	 * Action for Capture partly
	 */
	public function capture_partly() {
		$this->verify_nonce();

		$order  = $this->get_reepay_order_or_exit();
		$amount = $this->get_amount_or_exit( $order );

		try {
			$gateway = rp_get_payment_method( $order );

			if ( empty( $gateway ) ) {
				wp_send_json_error( __( 'Payment method not found at the order', 'reepay-checkout-gateway' ) );
			}

			$gateway->capture_payment( $order, $amount );

			wp_send_json_success( __( 'Capture partly success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}

	/**
	 * Action for Refund partly
	 */
	public function refund_partly() {
		$this->verify_nonce();

		$order  = $this->get_reepay_order_or_exit();
		$amount = $this->get_amount_or_exit( $order );

		try {
			$gateway = rp_get_payment_method( $order );

			if ( empty( $gateway ) ) {
				wp_send_json_error( __( 'Payment method not found at the order', 'reepay-checkout-gateway' ) );
			}

			$gateway->refund_payment( $order, $amount );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		// The money is already refunded at the gateway; only the WooCommerce record is created here.
		$refund = wc_create_refund(
			array(
				'amount'         => $amount,
				'order_id'       => $order->get_id(),
				'refund_payment' => false,
				'restock_items'  => true,
			)
		);

		if ( is_wp_error( $refund ) ) {
			$order->add_order_note(
				sprintf(
					// translators: %1$s refund amount, %2$s error message.
					__( 'Reepay refund of %1$s succeeded, but the WooCommerce refund could not be created: %2$s', 'reepay-checkout-gateway' ),
					wc_price( $amount, array( 'currency' => $order->get_currency() ) ),
					$refund->get_error_message()
				)
			);

			wp_send_json_error(
				sprintf(
					// translators: %s error message.
					__( 'Refunded at Reepay, but the WooCommerce refund could not be created: %s', 'reepay-checkout-gateway' ),
					$refund->get_error_message()
				)
			);
		}

		wp_send_json_success( __( 'Refund partly success.', 'reepay-checkout-gateway' ) );
	}

	/**
	 * Action to set complete settle to transient option
	 */
	public function set_complete_settle_transient() {
		$this->verify_nonce( 'reepay' );

		if ( empty( $_POST['order_id'] ) || ! isset( $_POST['settle_order'] ) ) {
			wp_send_json_error( __( 'Order id or settle order not specified', 'reepay-checkout-gateway' ) );
		}

		$order_id     = wc_clean( $_POST['order_id'] );
		$settle_order = wc_clean( $_POST['settle_order'] );

		set_transient( 'reepay_order_complete_should_settle_' . $order_id, $settle_order, 60 );
		wp_send_json_success( 'success' );
	}
}
