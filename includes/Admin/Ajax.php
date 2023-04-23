<?php
/**
 * Admin ajax actions
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Exception;
use WC_AJAX;

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
	 * Exit if wrong nonce
	 *
	 * @param string $action action to verify.
	 *
	 * @return bool
	 */
	private function verify_nonce( $action = 'nonce' ): bool {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', $action ) ) {
			exit( 'No naughty business' );
		}

		return true;
	}

	/**
	 * Action for Capture
	 */
	public function capture() {
		$this->verify_nonce();

		if ( empty( $_REQUEST['order_id'] ) ) {
			wp_send_json_error( __( 'Order id not specified' ) );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

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
			wp_send_json_error( __( 'Order id not specified' ) );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

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
			wp_send_json_error( __( 'Order id not specified' ) );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

		$amount = ! empty( $_REQUEST['amount'] ) ? (int) wc_clean( $_REQUEST['amount'] ) : false;

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
	 * Action for Cancel
	 */
	public function capture_partly() {
		$this->verify_nonce();

		$order_id = isset( $_REQUEST['order_id'] ) ? (int) wc_clean( $_REQUEST['order_id'] ) : 0;
		$amount   = isset( $_REQUEST['amount'] ) ? wc_clean( $_REQUEST['amount'] ) : 0;

		$order  = wc_get_order( $order_id );
		$amount = str_replace( array( ',', '.' ), '', $amount );

		try {
			$gateway = rp_get_payment_method( $order );
			$gateway->capture_payment( $order, (float) ( $amount / 100 ) );

			wp_send_json_success( __( 'Capture partly success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function refund_partly() {
		$this->verify_nonce();

		$order_id = isset( $_REQUEST['order_id'] ) ? (int) wc_clean( $_REQUEST['order_id'] ) : 0;
		$amount   = isset( $_REQUEST['amount'] ) ? wc_clean( $_REQUEST['amount'] ) : 0;

		$order  = wc_get_order( $order_id );
		$amount = str_replace( array( ',', '.' ), '', $amount );

		try {
			$gateway = rp_get_payment_method( $order );
			$gateway->refund_payment( $order, (float) ( $amount / 100 ) );

			$_POST['order_id']               = $order->get_id();
			$_POST['refund_amount']          = $_REQUEST['amount'];
			$_POST['refunded_amount']        = $order->get_total_refunded();
			$_POST['line_item_qtys']         = array();
			$_POST['line_item_totals']       = array();
			$_POST['line_item_tax_totals']   = array();
			$_POST['api_refund']             = 'true';
			$_POST['restock_refunded_items'] = 'true';
			$_REQUEST['security']            = wp_create_nonce( 'order-item' );

			WC_AJAX::refund_line_items();

			wp_send_json_success( __( 'Refund partly success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Action to set complete settle to transient option
	 */
	public function set_complete_settle_transient() {
		$this->verify_nonce( 'reepay' );

		if ( empty( $_POST['order_id'] ) || empty( $_POST['settle_order'] ) ) {
			wp_send_json_error( __( 'Order id or settle order not specified' ) );
		}

		$order_id     = wc_clean( $_POST['order_id'] );
		$settle_order = wc_clean( $_POST['settle_order'] );

		set_transient( 'reepay_order_complete_should_settle_' . $order_id, $settle_order, 60 );
		wp_send_json_success( 'success' );
	}
}
