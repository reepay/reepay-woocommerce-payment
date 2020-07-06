<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Reepay_Checkout extends WC_Gateway_Reepay {
	/**
	 * Save CC
	 * @var string
	 */
	public $save_cc = 'yes';

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
		$this->id           = 'reepay_checkout';
		$this->has_fields   = TRUE;
		$this->method_title = __( 'Reepay Checkout', 'woocommerce-gateway-reepay-checkout' );
		//$this->icon         = apply_filters( 'woocommerce_reepay_checkout_icon', plugins_url( '/assets/images/reepay.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
			'add_payment_method',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		parent::__construct();

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
		$this->payment_methods          = isset( $this->settings['payment_methods'] ) ? $this->settings['payment_methods'] : $this->payment_methods;
		$this->skip_order_lines         = isset( $this->settings['skip_order_lines'] ) ? $this->settings['skip_order_lines'] : $this->skip_order_lines;
		$this->disable_order_autocancel = isset( $this->settings['disable_order_autocancel'] ) ? $this->settings['disable_order_autocancel'] : $this->disable_order_autocancel;

		// Disable "Add payment method" if the CC saving is disabled
		if ( $this->save_cc !== 'yes' && ($key = array_search('add_payment_method', $this->supports)) !== false ) {
			unset($this->supports[$key]);
		}

		if (!is_array($this->settle)) {
			$this->settle = array();
		}

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Subscriptions
		add_action( 'woocommerce_payment_token_added_to_order', array( $this, 'add_payment_token_id' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'add_subscription_card_id' ), 10, 1 );

		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
			$this,
			'update_failing_payment_method'
		), 10, 2 );

		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10, 1 );
		add_filter( 'wcs_renewal_order_created', array( $this, 'renewal_order_created' ), 10, 2 );

		// Allow store managers to manually set card id as the payment method on a subscription
		add_filter( 'woocommerce_subscription_payment_meta', array(
			$this,
			'add_subscription_payment_meta'
		), 10, 2 );

		add_filter( 'woocommerce_subscription_validate_payment_meta', array(
			$this,
			'validate_subscription_payment_meta'
		), 10, 3 );

		add_action( 'wcs_save_other_payment_meta', array( $this, 'save_subscription_payment_meta' ), 10, 4 );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
			$this,
			'scheduled_subscription_payment'
		), 10, 2 );

		// Display the credit card used for a subscription in the "My Subscriptions" table
		add_filter( 'woocommerce_my_subscriptions_payment_method', array(
			$this,
			'maybe_render_subscription_payment_method'
		), 10, 2 );

		// Lock "Save card" if needs
		add_filter(
			'woocommerce_payment_gateway_save_new_payment_method_option_html',
			array(
				$this,
				'save_new_payment_method_option_html',
			),
			10,
			2
		);

		// Action for "Add Payment Method"
		add_action( 'wp_ajax_reepay_card_store', array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_nopriv_reepay_card_store', array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_reepay_finalize', array( $this, 'reepay_finalize' ) );
		add_action( 'wp_ajax_nopriv_reepay_finalize', array( $this, 'reepay_finalize' ) );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-reepay-checkout' ),
				'default' => 'no'
			),
			'title'          => array(
				'title'       => __( 'Title', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => __( 'Reepay Checkout', 'woocommerce-gateway-reepay-checkout' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => __( 'Reepay Checkout', 'woocommerce-gateway-reepay-checkout' ),
			),
			'private_key' => array(
				'title'       => __( 'Live Private Key', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Insert your private key from your live account', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => $this->private_key
			),
			'private_key_test' => array(
				'title'       => __( 'Test Private Key', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Insert your private key from your Reepay test account', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => $this->private_key_test
			),
			'test_mode'       => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Test Mode', 'woocommerce-gateway-reepay-checkout' ),
				'default' => $this->test_mode
			),
			'payment_type' => array(
				'title'       => __( 'Payment Window Display', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'Choose between a redirect window or a overlay window', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'select',
				'options'     => array(
					self::METHOD_WINDOW  => 'Window',
					self::METHOD_OVERLAY => 'Overlay',
				),
				'default'     => $this->payment_type
			),
			'payment_methods' => array(
				'title'       => __( 'Payment Methods', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'Payment Methods', 'woocommerce-gateway-reepay-checkout' ),
				'type'           => 'multiselect',
				'css'            => 'height: 250px',
				'options'     => array(
					'cart'  => 'All available debit / credit cards',
					'dankort' => 'Dankort',
					'visa' => 'VISA',
					'visa_dk' => 'VISA/Dankort',
					'visa_elec' => 'VISA Electron',
					'mc' => 'MasterCard',
					'amex' => 'American Express',
					'mobilepay' => 'MobilePay',
					'viabill' => 'ViaBill',
					'klarna_pay_later' => 'Klarna Pay Later',
					'klarna_pay_now' => 'Klarna Pay Now',
					'resurs' => 'Resurs Bank',
					'swish' => 'Swish',
					'diners' => 'Diners Club',
					'maestro' => 'Maestro',
					'laser' => 'Laser',
					'discover' => 'Discover',
					'jcb' => 'JCB',
					'china_union_pay' => 'China Union Pay',
					'ffk' => 'Forbrugsforeningen',
					'paypal' => 'PayPal',
					'applepay' => 'Apple Pay',
				),
				'default'     => $this->payment_methods
            ),
			'settle'             => array(
				'title'          => __( 'Instant Settle', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'Instant Settle will charge your customers right away', 'woocommerce-gateway-reepay-checkout' ),
				'type'           => 'multiselect',
				'css'            => 'height: 150px',
				'options'        => array(
					self::SETTLE_VIRTUAL   => __( 'Instant Settle online / virtualproducts', 'woocommerce-gateway-reepay-checkout' ),
					self::SETTLE_PHYSICAL  => __( 'Instant Settle physical  products', 'woocommerce-gateway-reepay-checkout' ),
					self::SETTLE_RECURRING => __( 'Instant Settle recurring (subscription) products', 'woocommerce-gateway-reepay-checkout' ),
					self::SETTLE_FEE => __( 'Instant Settle fees', 'woocommerce-gateway-reepay-checkout' ),
				),
				'select_buttons' => TRUE,
				'default'     => $this->settle
			),
			'language'     => array(
				'title'       => __( 'Language In Payment Window', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'select',
				'options'     => array(
					''       => __( 'Detect Automatically', 'woocommerce-gateway-reepay-checkout' ),
					'en_US'  => __( 'English', 'woocommerce-gateway-reepay-checkout' ),
					'da_DK'  => __( 'Danish', 'woocommerce-gateway-reepay-checkout' ),
					'sv_SE'  => __( 'Swedish', 'woocommerce-gateway-reepay-checkout' ),
					'no_NO'  => __( 'Norwegian', 'woocommerce-gateway-reepay-checkout' ),
					'de_DE'  => __( 'German', 'woocommerce-gateway-reepay-checkout' ),
					'es_ES'  => __( 'Spanish', 'woocommerce-gateway-reepay-checkout' ),
					'fr_FR'  => __( 'French', 'woocommerce-gateway-reepay-checkout' ),
					'it_IT'  => __( 'Italian', 'woocommerce-gateway-reepay-checkout' ),
					'nl_NL'  => __( 'Netherlands', 'woocommerce-gateway-reepay-checkout' ),
				),
				'default'     => $this->language
			),
			'save_cc'        => array(
				'title'   => __( 'Allow Credit Card saving', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Save CC feature', 'woocommerce-gateway-reepay-checkout' ),
				'default' => $this->save_cc
			),
			'debug'          => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-reepay-checkout' ),
				'default' => $this->debug
			),
			'logos'             => array(
				'title'          => __( 'Payment Logos', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'Choose the logos you would like to show in WooCommerce checkout. Make sure that they are enabled in Reepay Dashboard', 'woocommerce-gateway-reepay-checkout' ),
				'type'           => 'multiselect',
				'css'            => 'height: 250px',
				'options'        => array(
					'dankort' => __( 'Dankort', 'woocommerce-gateway-reepay-checkout' ),
					'visa'       => __( 'Visa', 'woocommerce-gateway-reepay-checkout' ),
					'mastercard' => __( 'MasterCard', 'woocommerce-gateway-reepay-checkout' ),
					'visa-electron' => __( 'Visa Electron', 'woocommerce-gateway-reepay-checkout' ),
					'maestro' => __( 'Maestro', 'woocommerce-gateway-reepay-checkout' ),
					'paypal_logo' => __( 'Paypal', 'woocommerce-gateway-reepay-checkout' ),
					'mobilepay' => __( 'MobilePay Online', 'woocommerce-gateway-reepay-checkout' ),
					'applepay' => __( 'ApplePay', 'woocommerce-gateway-reepay-checkout' ),
					'klarna-pay-now' => __( 'KlarnaPayNow', 'woocommerce-gateway-reepay-checkout' ),
					'klarna-pay-later' => __( 'KlarnaPayLater', 'woocommerce-gateway-reepay-checkout' ),
					'klarna' => __( 'Klarna', 'woocommerce-gateway-reepay-checkout' ),
					'viabill' => __( 'Viabill', 'woocommerce-gateway-reepay-checkout' ),
					'resursbank' => __( 'Resurs Bank', 'woocommerce-gateway-reepay-checkout' ),
					'forbrugsforeningen' => __( 'Forbrugsforeningen', 'woocommerce-gateway-reepay-checkout' ),
					'amex' => __( 'AMEX', 'woocommerce-gateway-reepay-checkout' ),
					'jcb' => __( 'JCB', 'woocommerce-gateway-reepay-checkout' ),
					'diners' => __( 'Diners Club', 'woocommerce-gateway-reepay-checkout' ),
					'unionpay' => __( 'Unionpay', 'woocommerce-gateway-reepay-checkout' ),
					'discover' => __( 'Discover', 'woocommerce-gateway-reepay-checkout' ),
				),
				'select_buttons' => TRUE,
			),
			'logo_height'          => array(
				'title'       => __( 'Logo Height', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Set the Logo height in pixels', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => ''
			),
			'skip_order_lines' => array(
				'title'       => __( 'Skip order lines', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'Select if order lines should not be send to Reepay', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'select',
				'options'     => array(
					'no'   => 'Include order lines',
					'yes'  => 'Skip order lines'
				),
				'default'     => $this->skip_order_lines
			),
			'disable_order_autocancel' => array(
				'title'       => __( 'Disable order auto-cancel', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'If the automatic woocommerce unpaid order auto-cancel should be ignored' ),
				'type'        => 'select',
				'options'     => array(
					'yes' => 'Enable auto-cancel',
					'no'  => 'Ignore / disable auto-cancel'
				),
				'default'     => $this->disable_order_autocancel
			)
		);
	}

	/**
	 * Output the gateway settings screen
	 * @return void
	 */
	public function admin_options() {
		// Check that WebHook was installed
		$token = $this->test_mode ? md5( $this->private_key_test ) : md5( $this->private_key );

		wc_get_template(
			'admin/admin-options.php',
			array(
				'gateway' => $this,
				'webhook_installed' => get_option( 'woocommerce_reepay_webhook_' . $token ) === 'installed'
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$result = parent::process_admin_options();

		// Reload settings
		$this->init_settings();
		$this->private_key      = isset( $this->settings['private_key'] ) ? $this->settings['private_key'] : $this->private_key;
		$this->private_key_test = isset( $this->settings['private_key_test'] ) ? $this->settings['private_key_test'] : $this->private_key_test;
		$this->test_mode        = isset( $this->settings['test_mode'] ) ? $this->settings['test_mode'] : $this->test_mode;

		// Check that WebHook was installed
		$token = $this->test_mode ? md5( $this->private_key_test ) : md5( $this->private_key );

		// Install WebHook
		if ( get_option( 'woocommerce_reepay_webhook_' . $token ) !== 'installed' ) {
			try {
				$data = array(
					'urls' => array( WC()->api_request_url( get_class( $this ) ) ),
					'disabled' => false,
				);
				$result = $this->request('PUT', 'https://api.reepay.com/v1/account/webhook_settings', $data);
				$this->log( sprintf( 'WebHook is successfully created: %s', var_export( $result, true ) ) );
				update_option( 'woocommerce_reepay_webhook_' . $token, 'installed' );
				WC_Admin_Settings::add_message( __( 'Reepay: WebHook is successfully created', 'woocommerce-gateway-reepay-checkout' ) );
			} catch ( Exception $e ) {
				$this->log( sprintf( 'WebHook creation is failed: %s', var_export( $result, true ) ) );
				WC_Admin_Settings::add_error( sprintf( __( 'Reepay: WebHook creation is failed: %s', 'woocommerce-gateway-reepay-checkout' ), var_export( $result, true ) ) );
			}
		}

		return $result;
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
			dirname( __FILE__ ) . '/../templates/'
		);

		// The "Save card or use existed" form should appears when active or when the cart has a subscription
		if ( ( $this->save_cc === 'yes' && ! is_add_payment_method_page() ) ||
			 ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() )
		) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
        }
	}

	/**
	 * Add Payment Method.
	 *
	 * @return array
	 */
	public function add_payment_method() {
		$user            = get_userdata( get_current_user_id() );
		$customer_handle = get_user_meta( $user->ID, 'reepay_customer_id', true );

		if ( empty ( $customer_handle ) ) {
			// Create reepay customer
			$customer_handle = $this->get_customer_handle( $user->ID );
			$location = wc_get_base_location();

			$params = [
				'locale'> $this->get_language(),
				'button_text' => __( 'Add card', 'woocommerce-gateway-reepay-checkout' ),
				'create_customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $customer_handle,
					'email' => $user->user_email,
					'address' => '',
					'address2' => '',
					'city' => '',
					'country' => $location['country'],
					'phone' => '',
					'company' => '',
					'vat' => '',
					'first_name' => $user->first_name,
					'last_name' => $user->last_name,
					'postal_code' => ''
				],
				'accept_url' => add_query_arg( 'action', 'reepay_card_store', admin_url( 'admin-ajax.php' ) ),
				'cancel_url' => wc_get_account_endpoint_url( 'payment-methods' )
			];
		} else {
			// Use customer who exists
			$params = [
				'locale'> $this->get_language(),
				'button_text' => __( 'Add card', 'woocommerce-gateway-reepay-checkout' ),
				'customer' => $customer_handle,
				'accept_url' => add_query_arg( 'action', 'reepay_card_store', admin_url( 'admin-ajax.php' ) ),
				'cancel_url' => wc_get_account_endpoint_url( 'payment-methods' )
			];
		}

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		$result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params);
		$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

		wp_redirect( $result['url'] );
		exit();
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		// Add Subscription card id
		$this->add_subscription_card_id( $order_id );
	}





	/**
	 * Update the card meta for a subscription
	 * to complete a payment to make up for an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription  The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$subscription->update_meta_data( '_reepay_token', $renewal_order->get_meta( '_reepay_token', true ) );
		$subscription->update_meta_data( '_reepay_token_id', $renewal_order->get_meta( '_reepay_token_id', true ) );
	}

	/**
	 * Don't transfer customer meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		if ( $resubscribe_order->get_payment_method() === $this->id ) {
			// Delete tokens
			delete_post_meta( $resubscribe_order->get_id(), '_payment_tokens' );
			delete_post_meta( $resubscribe_order->get_id(), '_reepay_token' );
			delete_post_meta( $resubscribe_order->get_id(), '_reepay_token_id' );
			delete_post_meta( $resubscribe_order->get_id(), '_reepay_order' );
		}
	}

	/**
	 * Create a renewal order to record a scheduled subscription payment.
	 *
	 * @param WC_Order|int $renewal_order
	 * @param WC_Subscription|int $subscription
	 *
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public function renewal_order_created( $renewal_order, $subscription ) {
		if ( ! is_object( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		if ( ! is_object( $renewal_order ) ) {
			$renewal_order = wc_get_order( $renewal_order );
		}

		if ( $renewal_order->get_payment_method() === $this->id ) {
			// Remove Reepay order handler from renewal order
			delete_post_meta( $renewal_order->get_id(), '_reepay_order' );
		}

		return $renewal_order;
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$reepay_token = get_post_meta( $subscription->get_id(), '_reepay_token', true );

		// If token wasn't stored in Subscription
		if ( empty( $reepay_token ) ) {
			$order = $subscription->get_parent();
		    if ( $order ) {
			    $reepay_token = get_post_meta( $order->get_id(), '_reepay_token', true );
		    }
		}

		$payment_meta[$this->id] = array(
			'post_meta' => array(
				'_reepay_token' => array(
					'value' => $reepay_token,
					'label' => 'Reepay Token',
				)
			)
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array  $payment_meta      associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription
	 *
	 * @throws Exception
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $payment_method_id === $this->id ) {
			if ( empty( $payment_meta['post_meta']['_reepay_token']['value'] ) ) {
				throw new Exception( 'A "Reepay Token" value is required.' );
			}

			$tokens = explode( ',', $payment_meta['post_meta']['_reepay_token']['value'] );
			if ( count( $tokens ) > 1 ) {
				throw new Exception( 'Only one "Reepay Token" is allowed.' );
			}

			$token = self::get_payment_token( $tokens[0] );
			if ( ! $token ) {
				throw new Exception( 'This "Reepay Token" value not found.' );
			}

			if ( $token->get_gateway_id() !== $this->id ) {
				throw new Exception( 'This "Reepay Token" value should related to Reepay.' );
			}

			if ( $token->get_user_id() !== $subscription->get_user_id() ) {
				throw new Exception( 'Access denied for this "Reepay Token" value.' );
			}
		}
	}

	/**
	 * Save payment method meta data for the Subscription
	 *
	 * @param WC_Subscription $subscription
	 * @param string $meta_table
	 * @param string $meta_key
	 * @param string $meta_value
	 */
	public function save_subscription_payment_meta( $subscription, $meta_table, $meta_key, $meta_value ) {
		if ( $subscription->get_payment_method() === $this->id ) {
			if ( $meta_table === 'post_meta' && $meta_key === '_reepay_token' ) {
				// Add tokens
				$tokens = explode( ',', $meta_value );
				foreach ( $tokens as $reepay_token ) {
					// Get Token ID
					$token = self::get_payment_token( $reepay_token );
					if ( ! $token ) {
						// Create Payment Token
						$token = $this->add_payment_token( $subscription, $reepay_token );
					}

					self::assign_payment_token( $subscription, $token );
				}
			}
		}
	}

	/**
	 * Add Token ID.
	 *
	 * @param int $order_id
	 * @param int $token_id
	 * @param WC_Payment_Token_Reepay $token
	 * @param array $token_ids
	 *
	 * @return void
	 */
	public function add_payment_token_id( $order_id, $token_id, $token, $token_ids ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_payment_method() === $this->id ) {
			update_post_meta( $order->get_id(), '_reepay_token_id', $token_id );
			update_post_meta( $order->get_id(), '_reepay_token', $token->get_token() );
		}
	}

	/**
	 * Clone Card ID when Subscription created
	 *
	 * @param $order_id
	 */
	public function add_subscription_card_id( $order_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		// Get subscriptions
		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
		foreach ( $subscriptions as $subscription ) {
			/** @var WC_Subscription $subscription */
			$token = self::get_payment_token_order( $subscription );
			if ( ! $token ) {
				// Copy tokens from parent order
				$order = wc_get_order( $order_id );
				$token = self::get_payment_token_order( $order );

				if ( $token ) {
					self::assign_payment_token( $subscription, $token );
				}
			}
		}
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param          $amount_to_charge
	 * @param WC_Order $renewal_order
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		// Lookup token
		try {
			$token = self::get_payment_token_order( $renewal_order );

			// Try to find token in parent orders
			if ( ! $token ) {
				// Get Subscriptions
				$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
				foreach ( $subscriptions as $subscription ) {
					/** @var WC_Subscription $subscription */
					$token = self::get_payment_token_order( $subscription );
					if ( ! $token ) {
						$token = self::get_payment_token_order( $subscription->get_parent() );
					}
				}
			}

			// Failback: If token doesn't exist, but reepay token is here
			// We need that to provide woocommerce_subscription_payment_meta support
			// See https://github.com/Prospress/woocommerce-subscriptions-importer-exporter#importing-payment-gateway-meta-data
			if ( ! $token ) {
				$reepay_token = get_post_meta( $renewal_order->get_id(), '_reepay_token', true );

				// Try to find token in parent orders
				if ( empty( $reepay_token ) ) {
					// Get Subscriptions
					$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
					foreach ( $subscriptions as $subscription ) {
						/** @var WC_Subscription $subscription */
						$reepay_token = get_post_meta( $subscription->get_id(), '_reepay_token', true );
						if ( empty( $reepay_token ) ) {
						    if ( $order = $subscription->get_parent() ) {
							    $reepay_token = get_post_meta( $order->get_id(), '_reepay_token', true );
						    }
						}
					}
				}

				// Save token
				if ( ! empty( $reepay_token ) ) {
					if ( $token = $this->add_payment_token( $renewal_order, $reepay_token ) ) {
						self::assign_payment_token( $renewal_order, $token );
					}
				}
			}

			if ( ! $token ) {
				throw new Exception( 'Payment token isn\'t exists' );
			}

			// Validate
			if ( empty( $token->get_token() ) ) {
				throw new Exception( 'Payment token is empty' );
			}

			// Fix the reepay order value to prevent "Invoice already settled"
			$currently = get_post_meta( $renewal_order->get_id(), '_reepay_order', true );
			$shouldBe = 'order-' . $renewal_order->get_id();
			if ( $currently !== $shouldBe ) {
				update_post_meta( $renewal_order->get_id(), '_reepay_order', $shouldBe );
			}

			// Charge payment
			if ( true !== ( $result = $this->reepay_charge( $renewal_order, $token->get_token(), $amount_to_charge ) ) ) {
			    throw new Exception( $result );
			}

			// Instant settle
			$this->process_instant_settle( $renewal_order );
		} catch (Exception $e) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note(
				sprintf( __( 'Error: "%s". %s.', 'woocommerce-gateway-reepay-checkout' ),
					wc_price( $amount_to_charge ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription              the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		if ( $this->id !== $subscription->get_payment_method() || ! $subscription->get_user_id() ) {
			return $payment_method_to_display;
		}

		$tokens = $subscription->get_payment_tokens();
		foreach ($tokens as $token_id) {
			$token = new WC_Payment_Token_Reepay( $token_id );
			if ( $token->get_gateway_id() !== $this->id ) {
				continue;
			}

			return sprintf( __( 'Via %s card ending in %s/%s', 'woocommerce-gateway-reepay-checkout' ),
				$token->get_masked_card(),
				$token->get_expiry_month(),
				$token->get_expiry_year()
			);
		}

		return $payment_method_to_display;
	}

	/**
	 * Modify "Save to account" to lock that if needs.
	 *
	 * @param string $html
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return string
	 */
	public function save_new_payment_method_option_html( $html, $gateway ) {
		if ( $gateway->id !== $this->id ) {
			return $html;
		}

		// Lock "Save to Account" for Recurring Payments / Payment Change
		if ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() ) {
			// Load XML
			libxml_use_internal_errors( true );
			$doc = new \DOMDocument();
			$status = @$doc->loadXML( $html );
			if ( false !== $status ) {
				$item = $doc->getElementsByTagName('input')->item( 0 );
				$item->setAttribute('checked','checked' );
				$item->setAttribute('disabled','disabled' );

				$html = $doc->saveHTML($doc->documentElement);
			}
		}

		return $html;
	}

	/**
	 * Ajax: Add Payment Method
	 * @return void
	 */
	public function reepay_card_store()
	{
		$id = wc_clean( $_GET['id'] );
		$customer_handle = wc_clean( $_GET['customer'] );
		$reepay_token = wc_clean( $_GET['payment_method'] );

		try {
			// Create Payment Token
			//$customer_handle = $this->get_customer_handle( get_current_user_id() );
			$source = $this->get_reepay_cards( $customer_handle, $reepay_token );
			$expiryDate = explode( '-', $source['exp_date'] );

			$token = new WC_Payment_Token_Reepay();
			$token->set_gateway_id( $this->id );
			$token->set_token( $reepay_token );
			$token->set_last4( substr( $source['masked_card'], -4 ) );
			$token->set_expiry_year( 2000 + $expiryDate[1] );
			$token->set_expiry_month( $expiryDate[0] );
			$token->set_card_type( $source['card_type'] );
			$token->set_user_id( get_current_user_id() );
			$token->set_masked_card( $source['masked_card'] );

			// Save Credit Card
			$token->save();
			if ( ! $token->get_id() ) {
				throw new Exception( __( 'There was a problem adding the card.', 'woocommerce-gateway-reepay-checkout' ) );
			}

			wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-gateway-reepay-checkout' ) );
			wp_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
			exit();
		} catch (Exception $e) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'add-payment-method' ) );
			exit();
		}
	}

	/**
	 * Ajax: Finalize Payment
	 *
	 * @throws Exception
	 */
	public function reepay_finalize()
	{
		$id = wc_clean( $_GET['id'] );
		$customer_handle = wc_clean( $_GET['customer'] );
		$reepay_token = wc_clean( $_GET['payment_method'] );

		try {
			if ( empty( $_GET['key'] ) ) {
				throw new Exception('Order key is undefined' );
			}

			if ( ! $order_id = wc_get_order_id_by_order_key( $_GET['key'] ) ) {
				throw new Exception('Can not get order' );
			}

			if ( ! $order = wc_get_order( $order_id ) ) {
				throw new Exception('Can not get order' );
			}

			if ( $order->get_payment_method() !== $this->id ) {
				throw new Exception('Unable to use this order' );
			}

			$this->log( sprintf( '%s::%s Incoming data %s', __CLASS__, __METHOD__, var_export($_GET, true) ) );

			// Save Token
			$token = $this->reepay_save_token( $order, $reepay_token );

			// Add note
			$order->add_order_note( sprintf( __( 'Payment method changed to "%s"', 'woocommerce-gateway-reepay-checkout' ), $token->get_display_name() ) );

			// Complete payment if zero amount
			if ( abs( $order->get_total() ) < 0.01 ) {
				$order->payment_complete();
			}

			// @todo Transaction ID should applied via WebHook
			if ( ! empty( $_GET['invoice'] ) && $order->get_id() === $this->get_orderid_by_handle( wc_clean( $_GET['invoice'] ) ) ) {
				$result = $this->get_invoice_by_handle( wc_clean( $_GET['invoice'] ) );
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
						break;
					default:
						// @todo Order failed?
				}
			}

			wp_redirect( $this->get_return_url( $order ) );
		} catch (Exception $e) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( $this->get_return_url() );
		}

		exit();
	}
	
}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Checkout' );
