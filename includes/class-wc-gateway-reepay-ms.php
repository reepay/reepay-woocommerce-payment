<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Reepay_Mobilepay_Subscriptions extends WC_Gateway_Reepay {
	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'mobilepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'mobilepay_subscriptions'
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->id           = 'reepay_mobilepay_subscriptions';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Mobilepay Subscriptions', 'reepay-checkout-gateway' );

		$this->supports = array(
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
			'multiple_subscriptions'
		);

		$this->logos = array( 'mobilepay' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled     = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';

		$this->payment_methods = array(
			'mobilepay_subscriptions'
		);

		// Load setting from parent method
		$settings = $this->get_parent_settings();

		$this->private_key             = $settings['private_key'];
		$this->private_key_test        = $settings['private_key_test'];
		$this->test_mode               = $settings['test_mode'];
		$this->settle                  = $settings['settle'];
		$this->language                = $settings['language'];
		$this->debug                   = $settings['debug'];
		$this->payment_type            = $settings['payment_type'];
		$this->skip_order_lines        = $settings['skip_order_lines'];
		$this->enable_order_autocancel = $settings['enable_order_autocancel'];
		$this->is_webhook_configured   = $settings['is_webhook_configured'];

		if ( ! is_array( $this->settle ) ) {
			$this->settle = array();
		}

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'type'    => 'gateway_status',
				'label'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'default' => $this->test_mode
			),
			'enabled'              => array(
				'title'    => __( 'Enable/Disable', 'reepay-checkout-gateway' ),
				'type'     => 'checkbox',
				'label'    => __( 'Enable plugin', 'reepay-checkout-gateway' ),
				'default'  => 'no',
				'disabled' => ! $this->is_configured()
			),
			'title'                => array(
				'title'       => __( 'Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Mobilepay Subscriptions', 'reepay-checkout-gateway' )
			),
			'description'          => array(
				'title'       => __( 'Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Mobilepay Subscriptions', 'reepay-checkout-gateway' ),
			),
		);
	}

	/**
	 * This payment method works only for subscription products
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		// need for cron to available
		if ( ! is_checkout() ) {
			return true;
		}

		if ( is_checkout() && wcs_cart_have_subscription() ) {
			return true;
		}

		if ( is_add_payment_method_page() ) {
			return true;
		}

		return false;
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

		// The "Save card or use existed" form should be appeared when active or when the cart has a subscription
		if ( ( true /*$this->save_cc === 'yes'*/ && ! is_add_payment_method_page() ) ||
		     ( wcs_cart_have_subscription() || wcs_is_payment_change() )
		) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}
	}
}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Mobilepay_Subscriptions' );
