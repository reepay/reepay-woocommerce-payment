<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Reepay_Checkout extends WC_Payment_Gateway_Reepay {
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
	 * @var string
	 */

	public $public_key_test;

	/**
	 * Settle
	 * @var string
	 */
	public $settle = 'yes';

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
		'dankort', 'visa', 'mastercard', 'visa-electron', 'maestro', 'mobilepay', 'viabill'
	);

	/**
	 * Payment Type
	 * @var string
	 */
	public $payment_type = 'OVERLAY';

	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'reepay_checkout';
		$this->has_fields   = TRUE;
		$this->method_title = __( 'Reepay Checkout', 'woocommerce-gateway-payex-psp' );
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

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled          = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title            = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description      = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->private_key      = isset( $this->settings['private_key'] ) ? $this->settings['private_key'] : $this->private_key;
		$this->private_key_test = isset( $this->settings['private_key_test'] ) ? $this->settings['private_key_test'] : $this->private_key_test;
		$this->test_mode        = isset( $this->settings['test_mode'] ) ? $this->settings['test_mode'] : $this->test_mode;
		$this->settle           = isset( $this->settings['settle'] ) ? $this->settings['settle'] : $this->settle;
		$this->language         = isset( $this->settings['language'] ) ? $this->settings['language'] : $this->language;
		$this->debug            = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->logos            = isset( $this->settings['logos'] ) ? $this->settings['logos'] : $this->logos;
		$this->payment_type     = isset( $this->settings['payment_type'] ) ? $this->settings['payment_type'] : $this->payment_type;

		add_action( 'admin_notices', array( $this, 'admin_notice_warning' ) );

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

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

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Authorized Status
		add_filter( 'reepay_authorized_status', array(
			$this,
			'reepay_authorized_status'
		), 10, 2 );

		// Subscriptions
		add_action( 'woocommerce_payment_complete', array( $this, 'add_subscription_card_id' ), 10, 1 );

		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
			$this,
			'update_failing_payment_method'
		), 10, 2 );

		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );

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
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => __( 'Reepay Checkout', 'woocommerce-gateway-reepay-checkout' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => __( 'Reepay Checkout', 'woocommerce-gateway-reepay-checkout' ),
			),
			'private_key' => array(
				'title'       => __( 'Private key', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Private key', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => $this->private_key
			),
			'private_key_test' => array(
				'title'       => __( 'Private key Test', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Private key Test', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => $this->private_key_test
			),
			'payment_type' => array(
				'title'       => __( 'Payment Type', 'woocommerce-gateway-payex-psp' ),
				'description'    => __( 'Payment Type', 'woocommerce-gateway-payex-psp' ),
				'type'        => 'select',
				'options'     => array(
					self::METHOD_WINDOW  => 'Window',
					self::METHOD_OVERLAY => 'Overlay',
				),
				'default'     => $this->payment_type
			),
			'test_mode'       => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Test Mode', 'woocommerce-gateway-reepay-checkout' ),
				'default' => $this->test_mode
			),
			'settle'       => array(
				'title'   => __( 'Capture automatically', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Capture automatically', 'woocommerce-gateway-reepay-checkout' ),
				'default' => $this->settle
			),
			'language'     => array(
				'title'       => __( 'Language', 'woocommerce-gateway-reepay-checkout' ),
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
			'debug'          => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-reepay-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-reepay-checkout' ),
				'default' => $this->debug
			),
			'logos'             => array(
				'title'          => __( 'Payment logos', 'woocommerce-gateway-reepay-checkout' ),
				'description'    => __( 'Payment logos on checkout', 'woocommerce-gateway-reepay-checkout' ),
				'type'           => 'multiselect',
				'css'            => 'height: 250px',
				'options'        => array(
					'dankort' => __( 'Dankort', 'woocommerce-gateway-reepay-checkout' ),
					'visa'       => __( 'Visa', 'woocommerce-gateway-reepay-checkout' ),
					'mastercard' => __( 'MasterCard', 'woocommerce-gateway-reepay-checkout' ),
					'visa-electron' => __( 'Visa Electron', 'woocommerce-gateway-reepay-checkout' ),
					'maestro' => __( 'Maestro', 'woocommerce-gateway-reepay-checkout' ),
					'mobilepay' => __( 'MobilePay Online', 'woocommerce-gateway-reepay-checkout' ),
					'viabill' => __( 'Viabill', 'woocommerce-gateway-reepay-checkout' ),
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
				'description' => __( 'Set Logo height. For example, 25px', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => ''
			),
		);
	}

	/**
	 * Output the gateway settings screen
	 * @return void
	 */
	public function admin_options() {
		wc_get_template(
			'admin/admin-options.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
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
		wp_enqueue_script( 'reepay-checkout', 'https://checkout.reepay.com/checkout.js', array(), FALSE, FALSE );
		wp_register_script( 'wc-gateway-reepay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout' . $suffix . '.js', array(
			'jquery',
			'wc-checkout',
			'reepay-checkout',
		), FALSE, TRUE );

		// Localize the script with new data
		$translation_array = array(
			'payment_type' => $this->payment_type,
			'public_key' => $this->public_key,
			'language' => substr($this->get_language(), 0, 2),
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
			dirname( __FILE__ ) . '/../templates/'
		);

		if ( ! is_add_payment_method_page() ):
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();

			// Lock "Save to Account" for Recurring Payments / Payment Change
			if ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() ):
				?>
                <script type="application/javascript">
                    ( function ($) {
                        $( document ).ready( function () {
                            $( 'input[name="wc-reepay_checkout-new-payment-method"]' ).prop( {
                                'checked' : true,
                                'disabled': true
                            } );
                        } );

                        $( document ).on( 'updated_checkout', function () {
                            $( 'input[name="wc-reepay_checkout-new-payment-method"]' ).prop( {
                                'checked' : true,
                                'disabled': true
                            } );
                        } ) ;
                    } ( jQuery ) );
                </script>
			<?php
			endif;
		endif;
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		return TRUE;
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
					'country' => 'SE',
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
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order           = wc_get_order( $order_id );
		$token_id        = isset( $_POST['wc-reepay_checkout-payment-token'] ) ? wc_clean( $_POST['wc-reepay_checkout-payment-token'] ) : 'new';
		$maybe_save_card = isset( $_POST['wc-reepay_checkout-new-payment-method'] ) && (bool) $_POST['wc-reepay_checkout-new-payment-method'];

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
		        WC_Gateway_Reepay_Checkout::add_payment_token( $order, $token->get_id() );

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

		        $result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params);
		        $this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

		        return array(
			        'result'   => 'success',
			        'redirect' => $result['url']
		        );
            }
        }

		// Try to load saved token
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

			$this->reepay_charge( $order, $token, $order->get_total() );
			WC_Gateway_Reepay_Checkout::add_payment_token( $order, $token->get_id() );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// Get Customer reference
		$customer_handle = $this->get_customer_handle( $order->get_user_id() );

		// Initialize Payment. Subscription or zero payment
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
			'settle' => $this->settle === 'yes',
			'recurring' => self::order_contains_subscription( $order ) || self::wcs_is_payment_change(),
			'order' => [
				'handle' => $this->get_order_handle( $order ),
				'generate_handle' => false,
				//'amount' => round(100 * $order->get_total()),
				'order_lines' => $this->get_order_items( $order ),
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
			'cancel_url' => $order->get_cancel_order_url()
		];

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
		}

		$result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/charge', $params);
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

	/**
	 * Payment confirm action
	 * @return void
	 */
	public function payment_confirm() {
		if ( ! ( is_wc_endpoint_url( 'order-received' ) || is_account_page() ) ) {
			return;
		}

		if ( ! empty( $_GET['id'] ) ) {
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

		$this->log( sprintf( '%s::%s Incoming data %s', __CLASS__, __METHOD__, var_export($_GET, true) ) );

		// Save PaymentMethod for WC Subscriptions
		if ( ! empty( $_GET['payment_method'] ) && self::order_contains_subscription( $order ) ) {
			$this->reepay_save_token( $order, wc_clean( $_GET['payment_method'] ) );
		}

		// Complete payment if zero amount
		if ( abs( $order->get_total() ) < 0.01 ) {
			$order->payment_complete();
		}

		// @todo Transaction ID should applied via WebHook
		if ( ! empty( $_GET['invoice'] ) && $order_id === $this->get_orderid_by_handle( wc_clean( $_GET['invoice'] ) ) ) {
			$result = $this->request( 'GET', 'https://api.reepay.com/v1/invoice/' . wc_clean( $_GET['invoice'] ) );
			$this->log( sprintf( '%s::%s Invoice data %s', __CLASS__, __METHOD__, var_export($result, true) ) );
			switch ($result['state']) {
				case 'authorized':
					$this->set_authorized_status( $order );
					break;
				case 'settled':
					$order->payment_complete();
					break;
				default:
					// @todo Order failed?
			}
		}

	}

	/**
	 * WebHook Callback
	 * @return void
	 */
	public function return_handler() {
		try {
			// @todo Check 52.18.114.235
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

			if ( ! isset( $data['invoice'] ) ) {
				throw new Exception( 'Missing Invoice parameter' );
			}

			$invoice = $data['invoice'];

			// Get Order by handle
			$order_id = $this->get_orderid_by_handle( $invoice );
			if ( ! $order_id ) {
				throw new Exception( sprintf( 'Invoice #%s isn\'t exists in store.', $invoice ) );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception( sprintf( 'Order #%s isn\'t exists in store.', $order_id ) );
			}

			// Check transaction is applied
			if ( $order->get_transaction_id() === $data['transaction'] ) {
				$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
				return;
			}

			switch ( $data['event_type'] ) {
				case 'invoice_authorized':
					// Reduce stock
					$order_stock_reduced = $order->get_meta( '_order_stock_reduced' );
					if ( ! $order_stock_reduced ) {
						wc_reduce_stock_levels( $order->get_id() );
					}

					$order->set_transaction_id( $data['transaction'] );
					$order->save();
					$this->set_authorized_status( $order );

					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_settled':
					$order->set_transaction_id( $data['transaction'] );
					$order->save();
					$order->payment_complete( $data['transaction'] );
					$order->add_order_note( __( 'Transaction settled.', 'woocommerce-gateway-reepay-checkout' ) );

					update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );
					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_cancelled':
					$order->update_status( 'cancelled', __( 'Cancelled by WebHook.', 'woocommerce-gateway-reepay-checkout' ) );
					update_post_meta( $order->get_id(), '_reepay_cancel_transaction', $data['transaction'] );
					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				case 'invoice_refund':
					$result = $this->request( 'GET', 'https://api.reepay.com/v1/invoice/' . $invoice );
					$credit_notes = $result['credit_notes'];
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
							'order_id' => $order_id
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
					// @todo
					$this->log( sprintf( 'WebHook: Invoice created: %s', var_export( $invoice, true ) ) );
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
					}

					$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
					break;
				default:
					$this->log( sprintf( 'WebHook: Unknown event type: %s', $data['event_type'] ) );
			}

			http_response_code(200);
		} catch (Exception $e) {
			$this->log( sprintf(  'WebHook: Error: %s', $e->getMessage() ) );
			http_response_code(400);
		}
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
		// Delete tokens
		delete_post_meta( $subscription->get_id(), '_payment_tokens' );
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
		// Delete tokens
		delete_post_meta( $resubscribe_order->get_id(), '_payment_tokens' );
	}

	/**
	 * Create a renewal order to record a scheduled subscription payment.
	 * Remove Reepay order handler from renewal order.
	 *
	 * @param $renewal_order
	 * @param $subscription
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
		$payment_meta[$this->id] = array(
			'reepay_meta' => array(
				'token_id' => array(
					'value' => implode( ',', $subscription->get_payment_tokens() ),
					'label' => 'Card Token ID',
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
			if ( empty( $payment_meta['reepay_meta']['token_id']['value'] ) ) {
				throw new Exception('A "Card Token ID" value is required.');
			}

			$tokens = explode( ',', $payment_meta['reepay_meta']['token_id']['value'] );
			if ( count( $tokens ) > 1 ) {
				throw new Exception('Only one "Card Token ID" is allowed.');
			}

			$token = new WC_Payment_Token_Reepay( $tokens[0] );
			if ( ! $token->get_id() ) {
				throw new Exception('This "Card Token ID" value not found.');
			}

			if ( $token->get_gateway_id() !== $this->id ) {
				throw new Exception('This "Card Token ID" value should related to Reepay.');
			}

			if ( $token->get_user_id() !== $subscription->get_user_id() ) {
				throw new Exception('Access denied for this "Card Token ID" value.');
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
	public function save_subscription_payment_meta($subscription, $meta_table, $meta_key, $meta_value) {
		if ( $subscription->get_payment_method() === $this->id ) {
			if ( $meta_table === 'reepay_meta' && $meta_key === 'token_id' ) {
				// Delete tokens
				delete_post_meta( $subscription->get_id(), '_payment_tokens' );

				// Add tokens
				$tokens = explode( ',', $meta_value );
				foreach ($tokens as $token_id) {
					WC_Gateway_Reepay_Checkout::add_payment_token( $subscription, $token_id );
				}
			}
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
			// Try to search assigned token
			/** @var WC_Subscription $subscription */

			$tokens = get_post_meta( $subscription->get_id(), '_payment_tokens', true );
			if ( empty( $tokens ) ) {
				// Copy tokens from parent order
				$order = wc_get_order( $order_id );
				$tokens = $order->get_payment_tokens();

				// Add tokens
				foreach ($tokens as $token_id) {
					WC_Gateway_Reepay_Checkout::add_payment_token( $subscription, $token_id );
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
			$token = WC_Gateway_Reepay_Checkout::get_payment_token( $renewal_order );
			if ( ! $token ) {
				// Get subscriptions
				$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
				foreach ( $subscriptions as $subscription ) {
					/** @var WC_Subscription $subscription */
					$token = WC_Gateway_Reepay_Checkout::get_payment_token( $subscription );
					if ( $token ) {
						break;
					} else {
						$token = WC_Gateway_Reepay_Checkout::get_payment_token( $subscription->get_parent() );
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
		} catch (Exception $e) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note(
				sprintf( __( 'Error: "%s". %s.', 'woocommerce-gateway-reepay-checkout' ),
					wc_price( $amount_to_charge ),
					$e->getMessage()
				)
			);

			return;
		}

		// Charge payment
        $this->reepay_charge( $renewal_order, $token, $amount_to_charge );
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
     * Save Payment Token
     *
	 * @param WC_Order $order
	 * @param string $reepay_token
     *
     * @return void
	 *
	 * @throws Exception
	 */
	protected function reepay_save_token( $order, $reepay_token )
    {
	    // Check if token is exists in WooCommerce
	    $token = null;
	    $tokens = WC_Payment_Tokens::get_tokens( array(
		    'gateway_id' => $this->id,
	    ) );

	    foreach ($tokens as $token1) {
		    if ( $token1->get_token() === $reepay_token ) {
			    $token = $token1;
			    break;
		    }
	    }

	    if ( ! $token ) {
		    // Create Payment Token
		    $customer_handle = $this->get_customer_handle( $order->get_user_id() );
		    $source = $this->get_reepay_cards( $customer_handle, $reepay_token );
		    $expiryDate = explode( '-', $source['exp_date'] );

		    $token = new WC_Payment_Token_Reepay();
		    $token->set_gateway_id( $this->id );
		    $token->set_token( $reepay_token );
		    $token->set_last4( substr( $source['masked_card'], -4 ) );
		    $token->set_expiry_year( 2000 + $expiryDate[1] );
		    $token->set_expiry_month( $expiryDate[0] );
		    $token->set_card_type( $source['card_type'] );
		    $token->set_user_id( $order->get_customer_id() );
		    $token->set_masked_card( $source['masked_card'] );

		    // Save Credit Card
		    $token->save();
		    if ( ! $token->get_id() ) {
			    //throw new Exception( __( 'There was a problem adding the card.', 'woocommerce-gateway-reepay-checkout' ) );
		    }

		    update_post_meta( $order->get_id(), '_reepay_source', $source );
		    $this->log( sprintf( '%s::%s Payment token #%s created for %s',
			    __CLASS__,
			    __METHOD__,
			    $token->get_id(),
			    $source['masked_card']
		    ) );
	    }

	    // Add token
	    WC_Gateway_Reepay_Checkout::add_payment_token( $order, $token->get_id() );
    }

	/**
     * Charge payment.
     *
	 * @param WC_Order $order
	 * @param WC_Payment_Token_Reepay $token
	 * @param float|null $amount
     *
     * @return void
	 */
	protected function reepay_charge( $order, $token, $amount = null )
    {
        // @todo Use order lines instead of amount
	    try {
		    $params = [
			    'handle' => $this->get_order_handle( $order ),
			    'amount' => round(100 * $amount),
			    'currency' => $order->get_currency(),
			    'source' => $token->get_token(),
			    'settle' => $this->settle === 'yes',
			    'recurring' => $this->order_contains_subscription( $order ),
			    'customer' => [
				    'test' => $this->test_mode === 'yes',
				    'handle' => $this->get_customer_handle( $order->get_user_id() ),
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
			    //'order_lines' => $this->get_order_items( $order ),
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
		    ];

		    if ($order->needs_shipping_address()) {
			    $params['shipping_address'] = [
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
		    }
		    $result = $this->request('POST', 'https://api.reepay.com/v1/charge', $params);
		    $this->log( sprintf( '%s::%s Charge: %s', __CLASS__, __METHOD__, var_export( $result, true) ) );

		    // Check results
		    switch ( $result['state'] ) {
			    case 'authorized':
				    // Reduce stock
				    $order_stock_reduced = $order->get_meta( '_order_stock_reduced' );
				    if ( ! $order_stock_reduced ) {
					    wc_reduce_stock_levels( $order->get_id() );
				    }

				    $order->set_transaction_id( $result['transaction'] );
				    $order->update_status( 'on-hold', __( 'Payment authorized.', 'woocommerce-gateway-reepay-checkout' ) );
				    update_post_meta( $order->get_id(), 'reepay_charge', '0' );
				    break;
			    case 'settled':
				    $order->payment_complete( $result['transaction'] );
				    $order->add_order_note( __( 'Transaction settled.', 'woocommerce-gateway-reepay-checkout' ) );
				    update_post_meta( $order->get_id(), 'reepay_charge', '1' );
				    break;
			    default:
				    throw new Exception( 'Generic error' );
		    }
	    } catch (Exception $e) {
		    if ( mb_strpos($e->getMessage(), 'Invoice already settled', 0, 'UTF-8') !== false ) {
			    $order->payment_complete();
			    $order->add_order_note( __( 'Transaction already settled.', 'woocommerce-gateway-reepay-checkout' ) );
			    return;
		    }

		    $order->update_status( 'failed' );
		    $order->add_order_note(
			    sprintf( __( 'Failed to charge "%s". Error: %s. Token ID: %s', 'woocommerce-gateway-reepay-checkout' ),
				    wc_price( $amount ),
				    $e->getMessage(),
				    $token->get_id()
			    )
		    );
	    }
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

			wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-gateway-payex-psp' ) );
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
		    $this->reepay_save_token( $order, $reepay_token );

		    // Complete payment if zero amount
		    if ( abs( $order->get_total() ) < 0.01 ) {
			    $order->payment_complete();
		    }

		    // @todo Transaction ID should applied via WebHook
		    if ( ! empty( $_GET['invoice'] ) && $order->get_id() === $this->get_orderid_by_handle( wc_clean( $_GET['invoice'] ) ) ) {
			    $result = $this->request( 'GET', 'https://api.reepay.com/v1/invoice/' . wc_clean( $_GET['invoice'] ) );
			    $this->log( sprintf( '%s::%s Invoice data %s', __CLASS__, __METHOD__, var_export($result, true) ) );
			    switch ($result['state']) {
				    case 'authorized':
					    $this->set_authorized_status( $order );
					    break;
				    case 'settled':
					    $order->payment_complete();
					    break;
				    default:
					    // @todo Order failed?
			    }
		    }

		    wp_redirect( $this->get_return_url( $order ) );
		    exit();
        } catch (Exception $e) {
		    wc_add_notice( $e->getMessage(), 'error' );
		    wp_redirect( $this->get_return_url() );
		    exit();
        }
    }
}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Checkout' );