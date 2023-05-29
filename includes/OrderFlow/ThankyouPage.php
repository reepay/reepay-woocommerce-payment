<?php
/**
 * Status processing in reepay after payment on the thankyou page
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Reepay\Checkout\LoggingTrait;
use WC_Order;
use WC_Order_Item_Product;
use WC_Subscriptions_Product;

defined( 'ABSPATH' ) || exit();

/**
 * Class ThankyouPage
 *
 * @package Reepay\Checkout\OrderFlow
 */
class ThankyouPage {
	use LoggingTrait;

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private $logging_source = 'reepay-thankyou';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );
		add_action( 'woocommerce_before_thankyou', array( $this, 'thankyou_page' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'thankyou_scripts' ) );

		add_action( 'wp_ajax_reepay_check_payment', array( $this, 'ajax_check_payment' ) );
		add_action( 'wp_ajax_nopriv_reepay_check_payment', array( $this, 'ajax_check_payment' ) );
	}

	/**
	 * Override "checkout/thankyou.php" template
	 *
	 * @param string $located       path for inclusion.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string
	 */
	public function override_template( string $located, string $template_name, $args, string $template_path, string $default_path ): string {
		/**
		 * ToDo return type array to $args after fix here https://wordpress.org/support/topic/wrong-default-params-on-wc_get_template/
		 */
		if ( ! is_array( $args ) ) {
			return $located;
		}

		if ( strpos( $located, 'checkout/thankyou.php' ) !== false ) {
			if ( ! isset( $args['order'] ) ) {
				return $located;
			}

			$order = wc_get_order( $args['order'] );
			if ( ! $order ) {
				return $located;
			}

			if ( ! rp_is_order_paid_via_reepay( $order ) ) {
				return $located;
			}

			$located = wc_locate_template(
				'checkout/thankyou.php',
				$template_path,
				reepay()->get_setting( 'templates_path' )
			);
		}

		return $located;
	}

	/**
	 * Thank you page
	 *
	 * @param int $order_id current order id.
	 *
	 * @return void
	 * @throws Exception If error with order confirmation.
	 */
	public function thankyou_page( int $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ||  ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );

		if ( abs( $order->get_total() ) < 0.01 ) {
			$order->payment_complete();
		}

		// Update the order status if webhook wasn't configured.
		if ( 'no' === $gateway->is_webhook_configured
			 && ! empty( $_GET['invoice'] )
		) {
			$this->process_order_confirmation( wc_clean( $_GET['invoice'] ) );
		}
	}

	/**
	 * Outputs scripts used for "thankyou" page
	 *
	 * @return void
	 */
	public function thankyou_scripts() {
		if ( ! is_order_received_page() ) {
			return;
		}

		global $wp;

		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		$order = wc_get_order( absint( $wp->query_vars['order-received'] ) );

		if ( empty( $order )
			 || ! $order->key_is_valid( $order_key )
			 || ! rp_is_order_paid_via_reepay( $order )
		) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'wc-gateway-reepay-thankyou',
			reepay()->get_setting( 'js_url' ) . 'thankyou' . $suffix . '.js',
			array(
				'jquery',
				'jquery-blockui',
			),
			reepay()->get_setting( 'plugin_version' ),
			true
		);

		wp_localize_script(
			'wc-gateway-reepay-thankyou',
			'WC_Reepay_Thankyou',
			array(
				'order_id'      => $order->get_id(),
				'order_key'     => $order_key,
				'nonce'         => wp_create_nonce( 'reepay' ),
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'check_message' => __(
					'Please wait. We\'re checking the payment status.',
					'reepay-checkout-gateway'
				),
			)
		);

		wp_enqueue_script( 'wc-gateway-reepay-thankyou' );
	}

	/**
	 * Ajax: Check the payment
	 */
	public function ajax_check_payment() {
		check_ajax_referer( 'reepay', 'nonce' );

		$order_id  = isset( $_POST['order_id'] ) ? wc_clean( $_POST['order_id'] ) : '';
		$order_key = isset( $_POST['order_key'] ) ? wc_clean( $_POST['order_key'] ) : '';

		$order = wc_get_order( $order_id );
		if ( ! $order->get_id() || ! $order->key_is_valid( $order_key ) ) {
			wp_send_json_error( 'Invalid order' );

			return;
		}

		foreach ( $order->get_items() as $item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $item
			 */
			if ( class_exists( WC_Subscriptions_Product::class ) && WC_Subscriptions_Product::is_subscription( $item->get_product() ) ) {
				if ( .0 === $order->get_total() ) {
					$ret = array(
						'state'   => 'paid',
						'message' => 'Subscription is activated in trial',
					);

					wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
				}
			}
		}

		$ret = array();

		$gateway = rp_get_payment_method( $order );

		$result = reepay()->api( $gateway )->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			// No information.
			$ret = array(
				'state' => 'unknown',
			);

			wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
		}

		switch ( $result['state'] ) {
			case 'pending':
				$ret = array(
					'state' => 'pending',
				);
				break;
			case 'authorized':
			case 'settled':
				$ret = array(
					'state'   => 'paid',
					'message' => 'Order has been paid',
				);

				break;
			case 'cancelled':
				$ret = array(
					'state'   => 'failed',
					'message' => 'Order has been cancelled',
				);

				break;
			case 'failed':
				$message = 'Order has been failed';

				if ( count( $result['transactions'] ) > 0 &&
					 isset( $result['transactions'][0]['card_transaction']['acquirer_message'] )
				) {
					$message = $result['transactions'][0]['card_transaction']['acquirer_message'];
				}

				$ret = array(
					'state'   => 'failed',
					'message' => $message,
				);

				break;
		}

		wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
	}

	/**
	 * Process the order confirmation using accept_url.
	 *
	 * @param string $invoice_id invoice id.
	 *
	 * @return void
	 * @throws Exception If status update failed.
	 */
	private function process_order_confirmation( string $invoice_id ) {
		$this->log( sprintf( 'accept_url: Processing status update %s', $invoice_id ) );

		$order = rp_get_order_by_handle( $invoice_id );
		if ( ! $order ) {
			$this->log( sprintf( 'accept_url: Order is not found. Invoice: %s', $invoice_id ) );

			return;
		}

		$gateway = rp_get_payment_method( $order );

		$result = reepay()->api( $gateway )->get_invoice_by_handle( $invoice_id );
		if ( is_wp_error( $result ) ) {
			return;
		}

		$this->log( sprintf( 'accept_url: invoice state: %s. Invoice ID: %s ', $result['state'], $invoice_id ) );

		switch ( $result['state'] ) {
			case 'pending':
				OrderStatuses::update_order_status(
					$order,
					'pending',
					sprintf(
					// translators: %1$s order amount, %2$s transaction id.
						__( 'Transaction is pending. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $result['currency'] ) ),
						$result['transaction']
					),
					$result['transaction']
				);
				break;
			case 'authorized':
				if ( $order->get_status() === REEPAY_STATUS_AUTHORIZED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been authorized before', $order->get_id() ) );

					return;
				}

				OrderStatuses::set_authorized_status(
					$order,
					sprintf(
					// translators: %s order amount.
						__( 'Payment has been authorized. Amount: %s.', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $result['currency'] ) )
					),
					null
				);

				do_action( 'reepay_instant_settle', $order );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as authorized', $order->get_id() ) );
				break;
			case 'settled':
				if ( $order->get_status() === REEPAY_STATUS_SETTLED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been settled before', $order->get_id() ) );

					return;
				}

				OrderStatuses::set_settled_status(
					$order,
					sprintf(
					// translators: %s order amount.
						__( 'Payment has been settled. Amount: %s.', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $result['currency'] ) )
					),
					null
				);

				$this->log( sprintf( 'accept_url: Order #%s has been marked as settled', $order->get_id() ) );

				break;
			case 'cancelled':
				$order->update_status( 'cancelled', __( 'Cancelled.', 'reepay-checkout-gateway' ) );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as cancelled', $order->get_id() ) );

				break;
			case 'failed':
				$order->update_status( 'failed', __( 'Failed.', 'reepay-checkout-gateway' ) );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as failed', $order->get_id() ) );

				break;
		}
	}
}
