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
	 * Logo Height
	 * @var string
	 */
	public $logo_height = '';

	/**
	 * Skip order lines to Reepay and use order totals instead
	 */
	public $skip_order_lines = 'no';

	/**
	 * If automatically cancel inpaid orders should be ignored
	 */
	public $enable_order_autocancel = 'no';

	/**
	 * Email address for notification about failed webhooks
	 * @var string
	 */
	public $failed_webhooks_email = '';

	/**
	 * If webhooks have been configured
	 * @var string
	 */
	public $is_webhook_configured = 'no';

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = null;

    public $handle_failover = 'yes';

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
		$this->enable_order_autocancel  = isset( $this->settings['enable_order_autocancel'] ) ? $this->settings['enable_order_autocancel'] : $this->enable_order_autocancel;
        $this->handle_failover          = isset( $this->settings['handle_failover'] ) ? $this->settings['handle_failover'] : $this->handle_failover;

		if (!is_array($this->settle)) {
			$this->settle = array();
		}

		add_action( 'admin_notices', array( $this, 'admin_notice_warning'));

        add_action('admin_notices', array($this, 'notice_reepay_api_action'));

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
     * add notifictions in admin for reepay api actions
     */
    function notice_reepay_api_action() {
        if ($this->enabled === 'yes') {
            $error = get_transient('reepay_api_action_error');
            $success = get_transient( 'reepay_api_action_success');

            if(!empty( $error )) {
                echo "<div class='error notice is-dismissible'>
                    <p> {$error}</p>
                  </div>";
            }

            if(!empty( $success )) {
                echo "<div class='notice notice-success is-dismissible'>
                    <p> {$success}</p>
                  </div>";
            }
        }
        set_transient('reepay_api_action_error', '', 1);
        set_transient( 'reepay_api_action_success', '', 1);
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
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$html = '';
		$logos = array_filter( (array) $this->logos, 'strlen' );
		if ( count( $logos ) > 0 ) {
			$html = '<ul class="reepay-logos">';
			foreach ( $logos as $logo ) {
				$html .= '<li class="reepay-logo">';
				$html .= '<img src="' . esc_url( plugins_url( '/assets/images/' . $logo . '.png', dirname( __FILE__ ) . '/../../../' ) ) . '" alt="' . esc_attr( sprintf( __( 'Pay with %s on Reepay', 'woocommerce-gateway-reepay-checkout' ), $this->get_title() ) ). '" />';
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
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
		wc_get_template(
			'checkout/payment-fields.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../../templates/'
		);
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
		$token_id        = isset( $_POST['wc-' . $this->id . '-payment-token'] ) ? wc_clean( $_POST['wc-' . $this->id . '-payment-token'] ) : 'new';
		$maybe_save_card = isset( $_POST['wc-' . $this->id . '-new-payment-method'] ) && (bool) $_POST['wc-' . $this->id . '-new-payment-method'];

		if ( 'yes' !== $this->save_cc ) {
			$token_id = 'new';
			$maybe_save_card = false;
		}

    	// Switch of Payment Method
		if ( self::wcs_is_payment_change() ) {

			$customer_handle = $this->get_customer_handle_order( $order_id );

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
		$customer_handle = $this->get_customer_handle_order( $order->get_id() );

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
			'recurring' => $maybe_save_card || self::order_contains_subscription( $order ) || self::wcs_is_payment_change(),
			'order' => [
				'handle' => $this->get_order_handle( $order ),
				'generate_handle' => false,
                'amount' => $this->skip_order_lines === 'yes' ? $this->prepare_amount($order->get_total(), $order->get_currency()) : null,
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
		];

		// skip order lines if calculated amount not equal to total order amount
		if($this->get_calculated_amount( $order ) != $this->prepare_amount($order->get_total(), $order->get_currency())) {
  		    $params['order']['amount'] = $this->prepare_amount( $order->get_total(), $order->get_currency() );
            $params['order']['order_lines'] = null;
	    }

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
	            'country' => !empty($order->get_shipping_country()) ?
                    $order->get_shipping_country() :
                    $order->get_billing_country(),
    			'phone' => $order->get_billing_phone(),
				'company' => $order->get_shipping_company(),
				'vat' => '',
				'first_name' => $order->get_shipping_first_name(),
				'last_name' => $order->get_shipping_last_name(),
				'postal_code' => $order->get_shipping_postcode(),
				'state_or_province' => $order->get_shipping_state()
			];

//			if (!strlen($params['order']['shipping_address'])) {
//				$params['order']['shipping_address'] = $params['order']['billing_address'];
//			}
		}

        try {
            $result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/charge', $params);
        } catch(Exception $ex) {
            if('yes' == $this->handle_failover) {
                $error = $this->extract_api_error($ex->getMessage());

                // invoice with handle $params['order']['handle'] already exists and authorized/settled
                // try to create another invoice with unique handle in format order-id-time()
                if (400 == $error['http_status'] && in_array($error['code'], [105, 79, 29, 99, 72])) {
                    $handle = $this->get_order_handle($order, true);
                    $params['order']['handle'] = $handle;

                    $result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/charge', $params);
                }
            }
        }

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
			'cancel_url'         => home_url() . '/index.php/checkout/reepay_cancel?id='. $order->get_id()
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

		$this->log( sprintf( 'accept_url: Incoming data: %s', var_export($_GET, true) ) );

		// Save Payment Method
		$maybe_save_card = get_post_meta( $order->get_id(), '_reepay_maybe_save_card', true );

		if ( ! empty( $_GET['payment_method'] ) && ( $maybe_save_card || self::order_contains_subscription( $order ) ) ) {
		    $this->reepay_save_token( $order, wc_clean( $_GET['payment_method'] ) );
		}

		// Complete payment if zero amount
		if ( abs( $order->get_total() ) < 0.01 ) {
			$order->payment_complete();
		}

		// Update the order status if webhook wasn't configured
		if ( 'no' === $this->is_webhook_configured ) {
			if ( ! empty( $_GET['invoice'] ) ) {
				$this->process_order_confirmation( wc_clean( $_GET['invoice'] ) );
			}
		}
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

			$this->process_webhook( $data );

			http_response_code(200);
		} catch (Exception $e) {
			$this->log( sprintf(  'WebHook: Error: %s', $e->getMessage() ) );
			http_response_code(400);
		}
	}

    /**
     * Process the order confirmation using accept_url.
     *
     * @param string $invoice_id
     *
     * @return void
     * @throws Exception
     */
	public function process_order_confirmation( $invoice_id ) {

		// Update order status
		$this->log( sprintf( 'accept_url: Processing status update %s', $invoice_id ) );
		try {
			$result = $this->get_invoice_by_handle( $invoice_id );
		} catch ( Exception $e ) {
			return;
		}

  		// Get order
		$order = $this->get_order_by_handle( $invoice_id );

		$this->log( sprintf( 'accept_url: invoice state: %s. Invoice ID: %s ', $result['state'], $invoice_id ) );

  		switch ( $result['state'] ) {
			case 'authorized':
				// Check if the order has been marked as authorized before
				if ( $order->get_status() === REEPAY_STATUS_AUTHORIZED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been authorized before', $order->get_id() ) );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				WC_Reepay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s.', 'woocommerce-gateway-reepay-checkout' ),
						wc_price($this->make_initial_amount($result['amount'], $result['currency']))
					)
				);

				// Settle an authorized payment instantly if possible
				$this->process_instant_settle( $order );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as authorized', $order->get_id() ) );
				break;
			case 'settled':
				// Check if the order has been marked as settled before
				if ( $order->get_status() === REEPAY_STATUS_SETTLED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been settled before', $order->get_id() ) );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				WC_Reepay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s.', 'woocommerce-gateway-reepay-checkout' ),
						wc_price( $this->make_initial_amount($result['amount'], $result['currency']))
					)
				);

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as settled', $order->get_id() ) );

				break;
			case 'cancelled':
				$order->update_status( 'cancelled', __( 'Cancelled.', 'woocommerce-gateway-reepay-checkout' ) );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as cancelled', $order->get_id() ) );

				break;
			case 'failed':
				$order->update_status( 'failed', __( 'Failed.', 'woocommerce-gateway-reepay-checkout' ) );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as failed', $order->get_id() ) );

				break;
			default:
				// no break
		}
	}

	/**
	 * Process WebHook.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function process_webhook( $data ) {
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

				// Wait to be unlocked
				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				// Check if the order has been marked as authorized before
				if ( $order->get_status() === REEPAY_STATUS_AUTHORIZED ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. But the order had status early: %s',
						$data['event_type'],
						$order->get_status()
					) );

					http_response_code( 200 );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				// Add transaction ID
				$order->set_transaction_id( $data['transaction'] );
				$order->save();

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

                        wc_price( $this->make_initial_amount($invoice_data['amount'], $order->get_currency())),

						$data['transaction']
					),
					$data['transaction']
				);

				// Settle an authorized payment instantly if possible
				$this->process_instant_settle( $order );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_settled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = $this->get_order_by_handle( $data['invoice'] );

				// Wait to be unlocked
				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				// Check transaction is applied
				if ( $order->get_transaction_id() === $data['transaction'] ) {
					$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
					return;
				}

				// Check if the order has been marked as settled before
				if ( $order->get_status() === REEPAY_STATUS_SETTLED ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. But the order had status early: %s',
						$data['event_type'],
						$order->get_status()
					) );

					http_response_code( 200 );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

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
						wc_price( $this->make_initial_amount($invoice_data['amount'], $order->get_currency())),
						$data['transaction']
					),
					$data['transaction']
				);

				update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );

				// Unlock the order
				self::unlock_order( $order->get_id() );

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
					$amount = $this->make_initial_amount($credit_note['amount'], $order->get_currency());
					$reason = sprintf( __( 'Credit Note Id #%s.', 'woocommerce-gateway-reepay-checkout' ), $credit_note_id );

					// Create Refund
					$refund = wc_create_refund( array(
						'amount'   => $amount,
						'reason'   => '', // don't add Credit note to refund line
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
	}

	/**
	 * Enqueue the webhook processing.
	 *
	 * @param $raw_body
	 *
	 * @return void
	 */
	public function enqueue_webhook_processing( $raw_body )
	{
		$data = @json_decode( $raw_body, true );

		// Create Background Process Task
		$background_process = new WC_Background_Reepay_Queue();
		$background_process->push_to_queue(
			array(
				'payment_method_id' => $this->id,
				'webhook_data'      => $raw_body,
			)
		);
		$background_process->save();

		$this->log(
			sprintf( 'WebHook: Task enqueued. ID: %s',
				$data['id']
			)
		);
	}

	/**
	 * Lock the order.
	 *
	 * @see wait_for_unlock()
	 * @param mixed $order_id
	 *
	 * @return void
	 */
	public static function lock_order( $order_id ) {
		update_post_meta( $order_id, '_reepay_locked', '1' );
	}

	/**
	 * Unlock the order.
	 *
	 * @see wait_for_unlock()
	 * @param mixed $order_id
	 *
	 * @return void
	 */
	public static function unlock_order( $order_id ) {
		delete_post_meta( $order_id, '_reepay_locked' );
	}

	/**
	 * Wait for unlock.
	 *
	 * @param $order_id
	 *
	 * @return bool
	 */
	public static function wait_for_unlock( $order_id ) {
		@set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );

		$is_locked = (bool) get_post_meta( $order_id, '_reepay_locked', true );
		$needs_reload = false;
		$attempts = 0;
		while ( $is_locked ) {
			usleep( 500 );
			$attempts++;
			if ( $attempts > 30 ) {
				break;
			}

			wp_cache_delete( $order_id, 'post_meta' );
			$is_locked = (bool) get_post_meta( $order_id, '_reepay_locked', true );
			if ( $is_locked ) {
				$needs_reload = true;
				clean_post_cache( $order_id );
			}
		}

		return $needs_reload;
	}
}
