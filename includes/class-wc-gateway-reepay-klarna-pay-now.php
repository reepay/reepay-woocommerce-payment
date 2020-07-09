<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Reepay_Klarna_Pay_Now extends WC_Gateway_Reepay {
	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'klarna',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'klarna_pay_now'
	);

	public function __construct() {
		$this->id           = 'reepay_klarna_pay_now';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Klarna Pay Now', 'woocommerce-gateway-reepay-checkout' );
		//$this->icon         = apply_filters( 'woocommerce_reepay_klarna_pn_icon', plugins_url( '/assets/images/klarna-pay-now.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description              = isset( $this->settings['description'] ) ? $this->settings['description'] : '';

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

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );
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
				'default'     => __( 'Reepay - Klarna Pay Now', 'woocommerce-gateway-reepay-checkout' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'woocommerce-gateway-reepay-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'woocommerce-gateway-reepay-checkout' ),
				'default'     => __( 'Reepay - Klarna Pay Now', 'woocommerce-gateway-reepay-checkout' ),
			),
		);
	}
}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Klarna_Pay_Now' );
