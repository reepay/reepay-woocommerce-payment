<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Reepay_Admin {
	public function __construct() {
		// Add meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Add action buttons
		//add_action( 'woocommerce_order_item_add_action_buttons', __CLASS__ . '::add_action_buttons', 10, 1 );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Admin Backend Actions
		add_action( 'wp_ajax_reepay_capture', array(
			$this,
			'ajax_reepay_capture'
		) );

		add_action( 'wp_ajax_reepay_cancel', array(
			$this,
			'ajax_reepay_cancel'
		) );

		add_action( 'wp_ajax_reepay_refund', array(
			$this,
			'ajax_reepay_refund'
		) );

		add_action( 'wp_ajax_reepay_capture_partly', array(
			$this,
			'ajax_reepay_capture_partly'
		) );

		add_action( 'wp_ajax_reepay_refund_partly', array(
			$this,
			'ajax_reepay_refund_partly'
		) );

		add_action( 'wp_ajax_reepay_set_complete_settle_transient', array(
			$this,
			'ajax_reepay_set_complete_settle_transient'
		) );

		// Status Change Actions
		add_action( 'woocommerce_order_status_changed', __CLASS__ . '::order_status_changed', 10, 4 );
	}

	/**
	 * Add meta boxes in admin
	 * @return void
	 */
	public function add_meta_boxes() {
		global $post;

		$screen     = get_current_screen();
		$post_types = array( 'shop_order', 'shop_subscription' );

		if ( in_array( $screen->id, $post_types, true ) && in_array( $post->post_type, $post_types, true ) ) {
			if ( $order = wc_get_order( $post->ID ) ) {
				$payment_method = $order->get_payment_method();
				if ( in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) && apply_filters( 'reepay_checkout_product_show_meta_box', true ) ) {
					add_meta_box(
						'reepay-payment-actions',
						__( 'Reepay Payment', 'reepay-checkout-gateway' ),
						array(
							$this,
							'meta_box_payment',
						),
						'shop_order',
						'side',
						'high'
					);
				}
			}
		}
	}

	/**
	 * Inserts the content of the API actions into the meta box
	 */
	public function meta_box_payment() {
		global $post;
		global $post_id;

		$order = wc_get_order( $post->ID );

		if ( $order ) {
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) && apply_filters( 'show_reepay_metabox', true, $order ) ) {

				do_action( 'woocommerce_reepay_meta_box_payment_before_content', $order );

				$order      = wc_get_order( $post_id );
				$gateway    = rp_get_payment_method( $order );
				$order_data = $gateway->api->get_invoice_data( $order );
				if ( is_wp_error( $order_data ) ) {
					return;
				}

				wc_get_template(
					'admin/metabox-order.php',
					array(
						'gateway'    => $gateway,
						'order'      => $order,
						'order_id'   => $order->get_order_number(),
						'order_data' => $order_data
					),
					'',
					dirname( __FILE__ ) . '/../templates/'
				);
			}
		}
	}

	/**
	 * @param WC_Order $order
	 */
	public static function add_action_buttons( $order ) {
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			$gateway = rp_get_payment_method( $order );

			wc_get_template(
				'admin/action-buttons.php',
				array(
					'gateway' => $gateway,
					'order'   => $order
				),
				'',
				dirname( __FILE__ ) . '/../templates/'
			);
		}
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script(
				'reepay-js-input-mask',
				plugin_dir_url( __FILE__ ) . '../assets/dist/js/jquery.inputmask' . $suffix . '.js',
				array( 'jquery' ),
				'5.0.3'
			);

			wp_register_script(
				'reepay-admin-js',
				plugin_dir_url( __FILE__ ) . '../assets/dist/js/admin' . $suffix . '.js',
				array(
					'jquery',
					'reepay-js-input-mask'
				)
			);

			wp_enqueue_style( 'wc-gateway-reepay-checkout', plugins_url( '/../assets/dist/css/style' . $suffix . '.css', __FILE__ ), array(), false, 'all' );

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'reepay-checkout-gateway' ),
				'nonce'     => wp_create_nonce( 'reepay' ),
			);
			wp_localize_script( 'reepay-admin-js', 'Reepay_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'reepay-admin-js' );
		}
	}

	/**
	 * Action for Capture
	 */
	public function ajax_reepay_capture() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
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
	public function ajax_reepay_cancel() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

		// Check if the order is already cancelled
		// ensure no more actions are made
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			wp_send_json_success( __( 'Order already cancelled.', 'reepay-checkout-gateway' ) );

			return;
		}

		try {
			$gateway = rp_get_payment_method( $order );

			// Check if the payment can be cancelled
			if ( $gateway->can_cancel( $order ) ) {
				$gateway->cancel_payment( $order );
			}

			// Mark the order as cancelled - no more communication to reepay is done!
			$order->update_meta_data( '_reepay_order_cancelled', 1 );
			$order->save_meta_data();

			// Return success
			wp_send_json_success( __( 'Cancel success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_reepay_refund() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}
		$amount   = (int) wc_clean( $_REQUEST['amount'] );
		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );

		try {
			$gateway = rp_get_payment_method( $order );
			$gateway->refund_payment( $order, $amount );

			wp_send_json_success( __( 'Refund success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_reepay_capture_partly() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );
		$amount   = str_replace( array( ',', '.' ), '', wc_clean( $_REQUEST['amount'] ) );

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
	public function ajax_reepay_refund_partly() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) wc_clean( $_REQUEST['order_id'] );
		$order    = wc_get_order( $order_id );
		$amount   = str_replace( array( ',', '.' ), '', wc_clean( $_REQUEST['amount'] ) );

		try {
			$gateway = rp_get_payment_method( $order );
			$gateway->refund_payment( $order, (float) ( $amount / 100 ) );
			$this->woocommerce_refund_add( $order, $_REQUEST['amount'] );
			wp_send_json_success( __( 'Refund partly success.', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_reepay_set_complete_settle_transient() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id     = wc_clean( $_POST['order_id'] );
		$settle_order = wc_clean( $_POST['settle_order'] );

		set_transient( 'reepay_order_complete_should_settle_' . $order_id, $settle_order, 60 );
		wp_send_json_success( 'success' );
	}

	/**
	 * Order Status Change: Capture/Cancel
	 *
	 * @param $order_id
	 * @param $from
	 * @param $to
	 * @param WC_Order $order
	 */
	public static function order_status_changed( $order_id, $from, $to, $order ) {
		$payment_method = $order->get_payment_method();

		if ( ! in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			return;
		}

		if ( order_contains_subscription( $order ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );
		if ( ! $gateway ) {
			return;
		}

		switch ( $to ) {
			case 'cancelled':
				// Cancel payment
				if ( $gateway->can_cancel( $order ) ) {
					try {
						$gateway->cancel_payment( $order );
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error( $message );

						// Rollback
						$order->update_status( $from, sprintf( __( 'Order status rollback. %s', 'reepay-checkout-gateway' ), $message ) );
					}
				}
				break;
			case REEPAY_STATUS_SETTLED:
				// Capture payment
				$value = get_transient( 'reepay_order_complete_should_settle_' . $order->get_id() );
				if ( ( 1 == $value || false === $value ) && $gateway->can_capture( $order ) ) {
					try {
						$order_data = $gateway->api->get_invoice_data( $order );
						if ( is_wp_error( $order_data ) ) {
							throw new Exception( $order_data->get_error_message() );
						}

						$amount_to_capture = rp_make_initial_amount( $order_data['authorized_amount'] - $order_data['settled_amount'], $order->get_currency() );
						$items_to_capture  = WC_Reepay_Instant_Settle::calculate_instant_settle( $order )->items;

						if ( ! empty( $items_to_capture ) && $amount_to_capture > 0 ) {
							$gateway->capture_payment( $order, $amount_to_capture );
						}
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error(
							sprintf(
								__( 'Error with order <a href="%s">#%u</a>. View order notes for more info', 'reepay-checkout-gateway' ),
								$order->get_edit_order_url(),
								$order_id
							)
						);

						// Rollback
						$order->update_status(
							$from,
							sprintf( __( 'Order status rollback. %s', 'reepay-checkout-gateway' ), $message )
						);
					}
				}
				break;
			default:
				// no break
		}
	}

	private function woocommerce_refund_add( $order, $amount ) {
		$_POST['order_id']               = $order->get_id();
		$_POST['refund_amount']          = $amount;
		$_POST['refunded_amount']        = $order->get_total_refunded();
		$_POST['line_item_qtys']         = array();
		$_POST['line_item_totals']       = array();
		$_POST['line_item_tax_totals']   = array();
		$_POST['api_refund']             = 'true';
		$_POST['restock_refunded_items'] = 'true';
		$_REQUEST['security']            = wp_create_nonce( 'order-item' );

		WC_AJAX::refund_line_items();
	}
}

new WC_Reepay_Admin();
