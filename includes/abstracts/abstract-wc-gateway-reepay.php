<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class WC_Gateway_Reepay extends WC_Payment_Gateway_Reepay {
	const METHOD_WINDOW = 'WINDOW';
	const METHOD_OVERLAY = 'OVERLAY';

	/**
	 * Test Mode
	 * @var string
	 */
	public $test_mode = 'yes';

	/**
	 * @var string
	 */
	public $private_key;

	/**
	 * @var
	 */
	public $private_key_test;

	/**
	 * @var string
	 */

	public $public_key;

	/**
	 * Settle
	 * @var string
	 */
	public $settle = array(
		self::SETTLE_VIRTUAL,
		self::SETTLE_PHYSICAL,
		self::SETTLE_RECURRING,
		self::SETTLE_FEE
	);

	/**
	 * Debug Mode
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * Language
	 * @var string
	 */
	public $language = 'en_US';

	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'dankort',
		'visa',
		'mastercard',
		'visa-electron',
		'maestro',
		'mobilepay',
		'viabill',
		'applepay',
		'paypal_logo',
		'klarna-pay-later',
		'klarna-pay-now',
		'klarna',
		'resursbank'
	);

	/**
	 * Payment Type
	 * @var string
	 */
	public $payment_type = 'OVERLAY';

	/**
	 * Save CC
	 * @var string
	 */
	public $save_cc = 'yes';

	/**
	 * Skip order lines to Reepay and use order totals instead
	 */
	public $skip_order_lines = 'no';

	/**
	 * If automatically cancel inpaid orders should be ignored
	 */
	public $disable_order_autocancel = 'no';

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = null;

	/**
	 * Init
	 */
	public function __construct() {
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description              = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->private_key              = isset( $this->settings['private_key'] ) ? $this->settings['private_key'] : $this->private_key;
		$this->private_key_test         = isset( $this->settings['private_key_test'] ) ? $this->settings['private_key_test'] : $this->private_key_test;
		$this->test_mode                = isset( $this->settings['test_mode'] ) ? $this->settings['test_mode'] : $this->test_mode;
		$this->settle                   = isset( $this->settings['settle'] ) ? $this->settings['settle'] : $this->settle;
		$this->language                 = isset( $this->settings['language'] ) ? $this->settings['language'] : $this->language;
		$this->save_cc                  = isset( $this->settings['save_cc'] ) ? $this->settings['save_cc'] : $this->save_cc;
		$this->debug                    = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->logos                    = isset( $this->settings['logos'] ) ? $this->settings['logos'] : $this->logos;
		$this->payment_type             = isset( $this->settings['payment_type'] ) ? $this->settings['payment_type'] : $this->payment_type;
		$this->skip_order_lines         = isset( $this->settings['skip_order_lines'] ) ? $this->settings['skip_order_lines'] : $this->skip_order_lines;
		$this->disable_order_autocancel = isset( $this->settings['disable_order_autocancel'] ) ? $this->settings['disable_order_autocancel'] : $this->disable_order_autocancel;

		if (!is_array($this->settle)) {
			$this->settle = array();
		}

		add_action( 'admin_notices', array( $this, 'admin_notice_warning' ) );

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );
	}

	/**
	 * Admin notice warning
	 */
	public function admin_notice_warning() {
		if ( $this->enabled === 'yes' && ! is_ssl() ) {
			$message = __( 'Reepay is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid', 'woocommerce-gateway-reepay-checkout' );
			$message_href = __( 'SSL certificate', 'woocommerce-gateway-reepay-checkout' );
			$url = 'https://en.wikipedia.org/wiki/Transport_Layer_Security';
			printf( '<div class="notice notice-warning is-dismissible"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
				esc_html( $message ),
				esc_html( $url ),
				esc_html( $message_href )
			);
		}
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		if ( is_order_received_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'reepay-checkout', 'https://checkout.reepay.com/checkout.js', array(), false, false );
		wp_register_script( 'wc-gateway-reepay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/js/checkout' . $suffix . '.js', array(
			'jquery',
			'wc-checkout',
			'reepay-checkout',
		), false, true );

		// Localize the script with new data
		$translation_array = array(
			'payment_type' => $this->payment_type,
			'public_key' => $this->public_key,
			'language' => substr( $this->get_language(), 0, 2 ),
			'buttonText' => __( 'Pay', 'woocommerce-gateway-reepay-checkout' ),
			'recurring' => true,
			'nonce' => wp_create_nonce( 'reepay' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),

		);
		wp_localize_script( 'wc-gateway-reepay-checkout', 'WC_Gateway_Reepay_Checkout', $translation_array );

		// Enqueued script with localized data.
		wp_enqueue_script( 'wc-gateway-reepay-checkout' );
	}

	/**
	 * If There are no payment fields show the description if set.
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		return true;
	}


	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order           = wc_get_order( $order_id );
		$token_id        = 'new';
		$maybe_save_card = false;

		if ( $this->save_cc === 'yes' ) {
			$token_id        = isset( $_POST['wc-' . $this->id . '-payment-token'] ) ? wc_clean( $_POST['wc-' . $this->id . '-payment-token'] ) : 'new';
			$maybe_save_card = isset( $_POST['wc-' . $this->id . '-new-payment-method'] ) && (bool) $_POST['wc-' . $this->id . '-new-payment-method'];
		}

		// Switch of Payment Method
		if ( self::wcs_is_payment_change() ) {
			$user            = get_userdata( get_current_user_id() );
			$customer_handle = $this->get_customer_handle( $user->ID );

			if ( absint( $token_id ) > 0 ) {
				$token = new WC_Payment_Token_Reepay( $token_id );
				if ( ! $token->get_id() ) {
					wc_add_notice( __( 'Failed to load token.', 'woocommerce-gateway-reepay-checkout' ), 'error' );

					return false;
				}

				// Check access
				if ( $token->get_user_id() !== $order->get_user_id() ) {
					wc_add_notice( __( 'Access denied.', 'woocommerce-gateway-reepay-checkout' ), 'error' );

					return false;
				}

				// Replace token
				try {
					self::assign_payment_token( $order, $token );
				} catch ( Exception $e ) {
					$order->add_order_note( $e->getMessage() );

					return array(
						'result'   => 'failure',
						'message' => $e->getMessage()
					);
				}

				// Add note
				$order->add_order_note( sprintf( __( 'Payment method changed to "%s"', 'woocommerce-gateway-reepay-checkout' ), $token->get_display_name() ) );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} else {
				// Add new Card
				$params = [
					'locale'> $this->get_language(),
					'button_text' => __( 'Add card', 'woocommerce-gateway-reepay-checkout' ),
					'create_customer' => [
						'test' => $this->test_mode === 'yes',
						'handle' => $customer_handle,
						'email' => $order->get_billing_email(),
						'address' => $order->get_billing_address_1(),
						'address2' => $order->get_billing_address_2(),
						'city' => $order->get_billing_city(),
						'country' => $order->get_billing_country(),
						'phone' => $order->get_billing_phone(),
						'company' => $order->get_billing_company(),
						'vat' => '',
						'first_name' => $order->get_billing_first_name(),
						'last_name' => $order->get_billing_last_name(),
						'postal_code' => $order->get_billing_postcode()
					],
					'accept_url' => add_query_arg(
						array(
							'action' => 'reepay_finalize',
							'key' => $order->get_order_key()
						),
						admin_url( 'admin-ajax.php' )
					),
					'cancel_url' => $order->get_cancel_order_url()
				];

				if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
					$params['payment_methods'] = $this->payment_methods;
				}

				$result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params);
				$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

				return array(
					'result'   => 'success',
					'redirect' => $result['url']
				);
			}
		}

		// Try to charge with saved token
		if ( $token_id !== 'new' ) {
			$token = new WC_Payment_Token_Reepay( $token_id );
			if ( ! $token->get_id() ) {
				wc_add_notice( __( 'Failed to load token.', 'woocommerce-gateway-reepay-checkout' ), 'error' );

				return false;
			}

			// Check access
			if ( $token->get_user_id() !== $order->get_user_id() ) {
				wc_add_notice( __( 'Access denied.', 'woocommerce-gateway-reepay-checkout' ), 'error' );

				return false;
			}

			if ( abs( $order->get_total() ) < 0.01 ) {
				// Don't charge payment if zero amount
				$order->payment_complete();
			} else {
				// Charge payment
				if ( true !== ( $result = $this->reepay_charge( $order, $token->get_token(), $order->get_total() ) ) ) {
					wc_add_notice( $result, 'error' );

					return false;
				}

				// Settle the charge
				$this->process_instant_settle( $order );
			}

			try {
				self::assign_payment_token( $order, $token->get_id() );
			} catch ( Exception $e ) {
				$order->add_order_note( $e->getMessage() );
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// "Save Card" flag
		update_post_meta( $order->get_id(), '_reepay_maybe_save_card', $maybe_save_card );

		// Get Customer reference
		$customer_handle = $this->get_customer_handle( $order->get_user_id() );

		// If here's Subscription or zero payment
		if ( abs( $order->get_total() ) < 0.01 ) {
			$params = [
				'locale'> $this->get_language(),
				'button_text' => __( 'Pay', 'woocommerce-gateway-reepay-checkout' ),
				'create_customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $customer_handle,
					'email' => $order->get_billing_email(),
					'address' => $order->get_billing_address_1(),
					'address2' => $order->get_billing_address_2(),
					'city' => $order->get_billing_city(),
					'country' => $order->get_billing_country(),
					'phone' => $order->get_billing_phone(),
					'company' => $order->get_billing_company(),
					'vat' => '',
					'first_name' => $order->get_billing_first_name(),
					'last_name' => $order->get_billing_last_name(),
					'postal_code' => $order->get_billing_postcode()
				],
				'accept_url' => $this->get_return_url( $order ),
				'cancel_url' => $order->get_cancel_order_url()
			];

			if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
				$params['payment_methods'] = $this->payment_methods;
			}

			$result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params);
			$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

			return array(
				'result'             => 'success',
				'redirect'           => '#!reepay-checkout',
				'is_reepay_checkout' => true,
				'reepay'             => $result,
				'accept_url'         => $this->get_return_url( $order ),
				'cancel_url'         => $order->get_cancel_order_url()
			);
		}

		// Initialize Payment
		$params = [
			'locale' => $this->get_language(),
			//'settle' => $settleInstant,
			'recurring' => $maybe_save_card || self::order_contains_subscription( $order ) || self::wcs_is_payment_change(),
			'order' => [
				'handle' => $this->get_order_handle( $order ),
				'generate_handle' => false,
				'amount' => $this->skip_order_lines === 'yes' ? round(100 * $order->get_total()) : null,
				'order_lines' => $this->skip_order_lines === 'no' ? $this->get_order_items( $order ) : null,
				'currency' => $order->get_currency(),
				'customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $customer_handle,
					'email' => $order->get_billing_email(),
					'address' => $order->get_billing_address_1(),
					'address2' => $order->get_billing_address_2(),
					'city' => $order->get_billing_city(),
					'country' => $order->get_billing_country(),
					'phone' => $order->get_billing_phone(),
					'company' => $order->get_billing_company(),
					'vat' => '',
					'first_name' => $order->get_billing_first_name(),
					'last_name' => $order->get_billing_last_name(),
					'postal_code' => $order->get_billing_postcode()
				],
				'billing_address' => [
					'attention' => '',
					'email' => $order->get_billing_email(),
					'address' => $order->get_billing_address_1(),
					'address2' => $order->get_billing_address_2(),
					'city' => $order->get_billing_city(),
					'country' => $order->get_billing_country(),
					'phone' => $order->get_billing_phone(),
					'company' => $order->get_billing_company(),
					'vat' => '',
					'first_name' => $order->get_billing_first_name(),
					'last_name' => $order->get_billing_last_name(),
					'postal_code' => $order->get_billing_postcode(),
					'state_or_province' => $order->get_billing_state()
				],
			],
			'accept_url' => $this->get_return_url( $order ),
			'cancel_url' => $order->get_cancel_order_url(),
			'payment_methods' => $this->payment_methods
		];

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		if ($order->needs_shipping_address()) {
			$params['order']['shipping_address'] = [
				'attention' => '',
				'email' => $order->get_billing_email(),
				'address' => $order->get_shipping_address_1(),
				'address2' => $order->get_shipping_address_2(),
				'city' => $order->get_shipping_city(),
				'country' => $order->get_shipping_country(),
				'phone' => $order->get_billing_phone(),
				'company' => $order->get_shipping_company(),
				'vat' => '',
				'first_name' => $order->get_shipping_first_name(),
				'last_name' => $order->get_shipping_last_name(),
				'postal_code' => $order->get_shipping_postcode(),
				'state_or_province' => $order->get_shipping_state()
			];

			if (!strlen($params['order']['shipping_address'])) {
				$params['order']['shipping_address'] = $params['order']['billing_address'];
			}
		}

		$result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/charge', $params);
		$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

		if ( is_checkout_pay_page() ) {
			if ( $this->payment_type === self::METHOD_OVERLAY ) {
				return array(
					'result'             => 'success',
					'redirect'           => sprintf( '#!reepay-pay?rid=%s&accept_url=%s&cancel_url=%s',
						$result['id'],
						html_entity_decode( $this->get_return_url( $order ) ),
						html_entity_decode( $order->get_cancel_order_url() )
					),
				);
			} else {
				return array(
					'result'             => 'success',
					'redirect'           => $result['url'],
				);
			}

		}

		return array(
			'result'             => 'success',
			'redirect'           => '#!reepay-checkout',
			'is_reepay_checkout' => true,
			'reepay'             => $result,
			'accept_url'         => $this->get_return_url( $order ),
			'cancel_url'         => $order->get_cancel_order_url()
		);
	}

	/**
	 * Payment confirm action
	 * @return void
	 */
	public function payment_confirm() {
		if ( ! ( is_wc_endpoint_url( 'order-received' ) || is_account_page() ) ) {
			return;
		}

		if ( empty( $_GET['id'] ) ) {
			return;
		}

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		if ( ! $order_id = wc_get_order_id_by_order_key( $_GET['key'] ) ) {
			return;
		}

		if ( ! $order = wc_get_order( $order_id ) ) {
			return;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$confirmed = get_post_meta( $order->get_id(), '_reepay_payment_confirmed', true );
		if ( ! empty ( $confirmed ) ) {
			return;
		}

		$this->log( sprintf( '%s::%s Incoming data %s', __CLASS__, __METHOD__, var_export($_GET, true) ) );

		// Save Payment Method
		$maybe_save_card = get_post_meta( $order->get_id(), '_reepay_maybe_save_card', true );

		if ( ! empty( $_GET['payment_method'] ) && ( $maybe_save_card || self::order_contains_subscription( $order ) ) ) {
			$this->reepay_save_token( $order, wc_clean( $_GET['payment_method'] ) );
		}

		// Complete payment if zero amount
		if ( abs( $order->get_total() ) < 0.01 ) {
			$order->payment_complete();
		}

		// @todo Transaction ID should applied via WebHook
		if ( ! empty( $_GET['invoice'] ) && $order_id === $this->get_orderid_by_handle( wc_clean( $_GET['invoice'] ) ) ) {
			// Wait updates by WebHook
			//@set_time_limit( 0 );
			//@ini_set( 'max_execution_time', '0' );

			$attempts = 0;
			$status_failback = false;
			do {
				usleep( 500 );
				$attempts++;
				if ( $attempts > 30 ) {
					$status_failback = true;
					break;
				}

				clean_post_cache( $order_id );
				$order = wc_get_order( $order_id );
			} while ( $order->has_status( apply_filters( 'woocommerce_default_order_status', 'pending' ) ) );

			// Update order status
			if ( $status_failback ) {
				$this->log( sprintf( '%s::%s Processing status_fallback ', __CLASS__, __METHOD__ ) );
				try {
					$result = $this->get_invoice_by_handle( wc_clean( $_GET['invoice'] ) );
				} catch (Exception $e) {
					return;
				}

				$this->log( sprintf( '%s::%s status_fallback state %s ', __CLASS__, __METHOD__, $result['state'] ) );
				switch ($result['state']) {
					case 'authorized':
						WC_Reepay_Order_Statuses::set_authorized_status(
							$order,
							sprintf(
								__( 'Payment has been authorized. Amount: %s.', 'woocommerce-gateway-reepay-checkout' ),
								wc_price( $result['amount'] / 100 )
							)
						);

						// Settle an authorized payment instantly if possible
						$this->process_instant_settle( $order );
						break;
					case 'settled':
						WC_Reepay_Order_Statuses::set_settled_status(
							$order,
							sprintf(
								__( 'Payment has been settled. Amount: %s.', 'woocommerce-gateway-reepay-checkout' ),
								wc_price( $result['amount'] / 100 )
							)
						);

						//update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );
						break;
					case 'cancelled':
						$order->update_status( 'cancelled', __( 'Cancelled.', 'woocommerce-gateway-reepay-checkout' ) );
						break;
					case 'failed':
						$order->update_status( 'failed', __( 'Failed.', 'woocommerce-gateway-reepay-checkout' ) );
						break;
					default:
						// @todo Order failed?
				}
			}
		}

		// Lock payment confirmation
		//update_post_meta( $order->get_id(), '_reepay_payment_confirmed', 1 );
	}

	/**
	 * WebHook Callback
	 * @return void
	 */
	public function return_handler() {
		try {
			$raw_body = file_get_contents( 'php://input' );
			$this->log( sprintf( 'WebHook: Initialized %s from %s', $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'] ) );
			$this->log( sprintf( 'WebHook: Post data: %s', var_export( $raw_body, true ) ) );
			$data = @json_decode( $raw_body, true );
			if ( ! $data ) {
				throw new Exception( 'Missing parameters' );
			}

			// Get Secret
			if ( ! ( $secret = get_transient( 'reepay_webhook_settings_secret' ) ) ) {
				$result = $this->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
				$secret = $result['secret'];

				set_transient( 'reepay_webhook_settings_secret', $secret, HOUR_IN_SECONDS );
			}

			// Verify secret
			$check = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], $secret, true ) );
			if ( $check !== $data['signature'] ) {
				throw new Exception( 'Signature verification failed' );
			}

			// Check invoice state
			switch ( $data['event_type'] ) {
				case 'invoice_authorized':
					if ( ! isset( $data['invoice'] ) ) {
						throw new Exception( 'Missing Invoice parameter' );
					}

					// Get Order by handle
					$order = $this->get_order_by_handle( $data['invoice'] );

					// Check transaction is applied
					if ( $order->get_transaction_id() === $data['transaction'] ) {
						$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
						return;
					}

					// Add transaction ID
					$order->set_transaction_id( $data['transaction'] );
					$order->save();

					// Check if the order has been marked as authorized before
					if ( $order->get_status() === REEPAY_STATUS_AUTHORIZED ) {
						$this->log( sprintf( 'WebHook: Event type: %s success. Order status: %s', $data['event_type'], $order->get_status() ) );
						http_response_code( 200 );
						return;
					}

					// Fetch the Invoice data at the moment
					try {
						$invoice_data = $this->get_invoice_by_handle( $data['invoice'] );
					} catch ( Exception $e ) {
						$invoice_data = array();
					}

					$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) );

					// set order as authorized
					WC_Reepay_Order_Statuses::set_authorized_status(
						$order,
						sprintf(
							__( 'Payment has been authorized. Amount: %s. Transaction: %s', 'woocommerce-gateway-reepay-checkout' ),
							wc_price( $invoice_data['amount'] / 100 ),
							$data['transaction']
						),
						$data['transaction']
					);

					// Settle an authorized payment instantly if possible
					$this->process_instant_settle( $order );

					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_settled':
					if ( ! isset( $data['invoice'] ) ) {
						throw new Exception( 'Missing Invoice parameter' );
					}

					// Get Order by handle
					$order = $this->get_order_by_handle( $data['invoice'] );

					// Check transaction is applied
					if ( $order->get_transaction_id() === $data['transaction'] ) {
						$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
						return;
					}

					// Check if the order has been marked as settled before
					if ( $order->get_status() === REEPAY_STATUS_SETTLED ) {
						$this->log( sprintf( 'WebHook: Event type: %s success. Order status: %s', $data['event_type'], $order->get_status() ) );
						http_response_code( 200 );
						return;
					}

					// Fetch the Invoice data at the moment
					try {
						$invoice_data = $this->get_invoice_by_handle( $data['invoice'] );
					} catch ( Exception $e ) {
						$invoice_data = array();
					}

					$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) );

					WC_Reepay_Order_Statuses::set_settled_status(
						$order,
						sprintf(
							__( 'Payment has been settled. Amount: %s. Transaction: %s', 'woocommerce-gateway-reepay-checkout' ),
							wc_price( $invoice_data['amount'] / 100 ),
							$data['transaction']
						),
						$data['transaction']
					);

					update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );
					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_cancelled':
					if ( ! isset( $data['invoice'] ) ) {
						throw new Exception( 'Missing Invoice parameter' );
					}

					// Get Order by handle
					$order = $this->get_order_by_handle( $data['invoice'] );

					// Check transaction is applied
					if ( $order->get_transaction_id() === $data['transaction'] ) {
						$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
						return;
					}

					// Add transaction ID
					$order->set_transaction_id( $data['transaction'] );
					$order->save();

					if ( $order->has_status( 'cancelled' ) ) {
						$this->log( sprintf( 'WebHook: Event type: %s success. Order status: %s', $data['event_type'], $order->get_status() ) );
						http_response_code( 200 );
						return;
					}

					$order->update_status( 'cancelled', __( 'Cancelled by WebHook.', 'woocommerce-gateway-reepay-checkout' ) );
					update_post_meta( $order->get_id(), '_reepay_cancel_transaction', $data['transaction'] );
					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_refund':
					if ( ! isset( $data['invoice'] ) ) {
						throw new Exception( 'Missing Invoice parameter' );
					}

					// Get Order by handle
					$order = $this->get_order_by_handle( $data['invoice'] );

					// Get Invoice data
					try {
						$invoice_data = $this->get_invoice_by_handle( $data['invoice'] );
					} catch ( Exception $e ) {
						$invoice_data = array();
					}

					$credit_notes = $invoice_data['credit_notes'];
					foreach ($credit_notes as $credit_note) {
						// Get registered credit notes
						$credit_note_ids = get_post_meta( $order->get_id(), '_reepay_credit_note_ids', TRUE );
						if ( ! is_array( $credit_note_ids ) ) {
							$credit_note_ids = array();
						}

						// Check is refund already registered
						if ( in_array( $credit_note['id'], $credit_note_ids ) ) {
							continue;
						}

						$credit_note_id = $credit_note['id'];
						$amount = $credit_note['amount'] / 100;
						$reason = sprintf( __( 'Credit Note Id #%s.', 'woocommerce-gateway-reepay-checkout' ), $credit_note_id );

						// Create Refund
						$refund = wc_create_refund( array(
							'amount'   => $amount,
							'reason'   => $reason,
							'order_id' => $order->get_id()
						) );

						if ( $refund ) {
							// Save Credit Note ID
							$credit_note_ids = array_merge( $credit_note_ids, $credit_note_id );
							update_post_meta( $order->get_id(), '_reepay_credit_note_ids', $credit_note_ids );

							$order->add_order_note(
								sprintf( __( 'Refunded: %s. Reason: %s', 'woocommerce-gateway-reepay-checkout' ),
									wc_price( $amount ),
									$reason
								)
							);
						}
					}

					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_created':
					if ( ! isset( $data['invoice'] ) ) {
						throw new Exception( 'Missing Invoice parameter' );
					}

					$this->log( sprintf( 'WebHook: Invoice created: %s', var_export( $data['invoice'], true ) ) );

					try {
						// Get Order by handle
						$order = $this->get_order_by_handle( $data['invoice'] );
					} catch ( Exception $e ) {
						$this->log( sprintf( 'WebHook: %s', $e->getMessage() ) );
					}

					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'customer_created':
					$customer = $data['customer'];
					$user_id = $this->get_userid_by_handle( $customer );
					if ( ! $user_id ) {
						if ( strpos( $customer, 'customer-' ) !== false ) {
							$user_id = (int) str_replace( 'customer-', '', $customer );
							if ( $user_id > 0 ) {
								update_user_meta( $user_id, 'reepay_customer_id', $customer );
								$this->log( sprintf( 'WebHook: Customer created: %s', var_export( $customer, true ) ) );
							}
						}

						if ( ! $user_id ) {
							$this->log( sprintf( 'WebHook: Customer doesn\'t exists: %s', var_export( $customer, true ) ) );
						}
					}

					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'customer_payment_method_added':
					// @todo
					$this->log( sprintf( 'WebHook: TODO: customer_payment_method_added: %s', var_export( $data, true ) ) );
					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				default:
					$this->log( sprintf( 'WebHook: Unknown event type: %s', $data['event_type'] ) );
					throw new Exception( sprintf( 'Unknown event type: %s', $data['event_type'] ) );
			}

			http_response_code(200);
		} catch (Exception $e) {
			$this->log( sprintf(  'WebHook: Error: %s', $e->getMessage() ) );
			http_response_code(400);
		}
	}
}
