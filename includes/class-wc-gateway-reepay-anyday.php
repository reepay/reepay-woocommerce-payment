<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Reepay_Anyday extends WC_Gateway_Reepay {
	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'anyday',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'anyday'
	);

	public function __construct() {
		$this->id           = 'reepay_anyday';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Anyday', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'anyday' );

		parent::__construct();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled     = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';

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

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_available_payment_gateways' ) );
	}

	public function filter_available_payment_gateways( $available_gateways ) {
		if ( ! empty( WC()->cart ) ) {
			if ( floatval( WC()->cart->total ) < 300 && get_option( 'woocommerce_currency' ) != 'DKK' ) {
				unset( $available_gateways[ $this->id ] );
			}
		}

		return $available_gateways;
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in reepay Admin', 'reepay-checkout-gateway' ),
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
				'default'     => __( 'Reepay - Anyday', 'reepay-checkout-gateway' )
			),
			'description'          => array(
				'title'       => __( 'Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Anyday', 'reepay-checkout-gateway' ),
			),
		);
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( parent::is_available() ) {
			return true;
		}
	}
}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Anyday' );