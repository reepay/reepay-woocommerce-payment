<?php

class WC_Reepay_Thankyou {
	use WC_Reepay_Log;

	/**
	 * @var string
	 */
	private $logging_source = 'reepay-thankyou';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );
		add_action( 'woocommerce_before_thankyou', array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_order_has_status', array( $this, 'order_has_status' ), 10, 3 );

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'thankyou_scripts' ) );

		// Actions for "Check payment"
		add_action( 'wp_ajax_reepay_check_payment', array( $this, 'ajax_check_payment' ) );
		add_action( 'wp_ajax_nopriv_reepay_check_payment', array( $this, 'ajax_check_payment' ) );
	}

	/**
	 * Override "checkout/thankyou.php" template
	 *
	 * @param $located
	 * @param $template_name
	 * @param $args
	 * @param $template_path
	 * @param $default_path
	 *
	 * @return string
	 */
	public function override_template( $located, $template_name, $args, $template_path, $default_path ) {
		if ( strpos( $located, 'checkout/thankyou.php' ) !== false ) {
			if ( ! isset( $args['order'] ) ) {
				return $located;
			}

			$order = wc_get_order( $args['order'] );
			if ( ! $order ) {
				return $located;
			}

			if ( ! in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
				return $located;
			}

			$located = wc_locate_template(
				'checkout/thankyou.php',
				$template_path,
				dirname( __FILE__ ) . '/../templates/'
			);
		}

		return $located;
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.ElseExpression)
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( ! in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );

		// Complete payment if zero amount
		if ( abs( $order->get_total() ) < 0.01 ) {
			$order->payment_complete();
		}

		// Update the order status if webhook wasn't configured
		if ( 'no' === $gateway->is_webhook_configured ) {
			if ( ! empty( $_GET['invoice'] ) ) {
				$this->process_order_confirmation( wc_clean( $_GET['invoice'] ) );
			}
		}
	}

	/**
	 * Workaround to use actual order status in `woocommerce_before_thankyou`
	 *
	 * @param bool $has_status
	 * @param WC_Order $order
	 * @param string $status
	 *
	 * @return bool
	 */
	public function order_has_status( $has_status, $order, $status ) {
		if ( ! is_wc_endpoint_url( 'order-received' ) ) {
			return $has_status;
		}

		if ( 'failed' === $status ) {
			$order = wc_get_order( $order->get_id() );

			return $status === $order->get_status();
		}

		return $has_status;
	}

	/**
	 * thankyou_scripts function.
	 *
	 * Outputs scripts used for "thankyou" page
	 *
	 * @return void
	 */
	public function thankyou_scripts() {
		if ( ! is_order_received_page() ) {
			return;
		}

		global $wp;

		$order_id  = absint( $wp->query_vars['order-received'] );
		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		$order = wc_get_order( $order_id );
		if ( ! $order->get_id() || ! $order->key_is_valid( $order_key ) ) {
			return;
		}

		if ( ! in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'wc-gateway-reepay-thankyou',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/dist/js/thankyou' . $suffix . '.js',
			array(
				'jquery',
				'jquery-blockui'
			),
			false,
			true
		);

		// Localize the script with new data
		wp_localize_script(
			'wc-gateway-reepay-thankyou',
			'WC_Reepay_Thankyou',
			array(
				'order_id'      => $order_id,
				'order_key'     => $order_key,
				'nonce'         => wp_create_nonce( 'reepay' ),
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'check_message' => __(
					'Please wait. We\'re checking the payment status.',
					'reepay-checkout-gateway'
				)
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

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $item->get_product() ) ) {
				if ( $order->get_total() == 0 ) {
					$ret = array(
						'state'   => 'paid',
						'message' => 'Subscription is activated in trial'
					);
					
					wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
				}
			}
		}


		$ret = array();

		$gateway = rp_get_payment_method( $order );

		$result = $gateway->api->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			// No any information
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
					'message' => 'Order has been paid'
				);

				break;
			case 'cancelled':
				$ret = array(
					'state'   => 'failed',
					'message' => 'Order has been cancelled'
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
					'message' => $message
				);

				break;
		}

		wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
	}

	/**
	 * Process the order confirmation using accept_url.
	 *
	 * @param string $invoice_id
	 *
	 * @return void
	 * @throws Exception
	 */
	private function process_order_confirmation( $invoice_id ) {
		// Update order status
		$this->log( sprintf( 'accept_url: Processing status update %s', $invoice_id ) );

		// Get order
		$order = rp_get_order_by_handle( $invoice_id );
		if ( ! $order ) {
			$this->log( sprintf( 'accept_url: Order is not found. Invoice: %s', $invoice_id ) );

			return;
		}

		// Get Payment Method
		$gateway = rp_get_payment_method( $order );

		// Get Invoice
		$result = $gateway->api->get_invoice_by_handle( $invoice_id );
		if ( is_wp_error( $result ) ) {
			return;
		}

		$this->log( sprintf( 'accept_url: invoice state: %s. Invoice ID: %s ', $result['state'], $invoice_id ) );

		switch ( $result['state'] ) {
			case 'pending':
				WC_Reepay_Order_Statuses::update_order_status(
					$order,
					'pending',
					sprintf(
						__( 'Transaction is pending. Amount: %s. Transaction: %s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $result['currency'] ) ),
						$result['transaction']
					),
					$result['transaction']
				);
				break;
			case 'authorized':
				// Check if the order has been marked as authorized before
				if ( $order->get_status() === REEPAY_STATUS_AUTHORIZED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been authorized before', $order->get_id() ) );

					return;
				}

				// Lock the order
				//self::lock_order( $order->get_id() );

				WC_Reepay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s.', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $result['currency'] ) )
					),
					null
				);

				// Settle an authorized payment instantly if possible
				do_action( 'reepay_instant_settle', $order );

				// Unlock the order
				//self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as authorized', $order->get_id() ) );
				break;
			case 'settled':
				// Check if the order has been marked as settled before
				if ( $order->get_status() === REEPAY_STATUS_SETTLED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been settled before', $order->get_id() ) );

					return;
				}

				// Lock the order
				//self::lock_order( $order->get_id() );

				WC_Reepay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s.', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $result['currency'] ) )
					),
					null
				);

				// Unlock the order
				//self::unlock_order( $order->get_id() );

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
			default:
				// no break
		}
	}
}

new WC_Reepay_Thankyou();
