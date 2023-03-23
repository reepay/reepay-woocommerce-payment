<?php

defined( 'ABSPATH' ) || exit();

class WC_Gateway_Reepay_Anyday extends WC_Gateway_Reepay {
	/**
	 * Logos
	 *
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
		'anyday',
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
		$this->enabled     = $this->settings['enabled'] ?? 'no';
		$this->title       = $this->settings['title'] ?? 'no';
		$this->description = $this->settings['description'] ?? 'no';

		// Load setting from parent method
		$this->apply_parent_settings();
	}

	public function is_available() {
		if ( ! empty( WC()->cart ) ) {
			if ( WC()->cart->get_total( '' ) < 300 && get_option( 'woocommerce_currency' ) != 'DKK' ) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Initialise Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in reepay Admin', 'reepay-checkout-gateway' ),
				'type'    => 'gateway_status',
				'label'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'default' => $this->test_mode,
			),
			'enabled'              => array(
				'title'    => __( 'Enable/Disable', 'reepay-checkout-gateway' ),
				'type'     => 'checkbox',
				'label'    => __( 'Enable plugin', 'reepay-checkout-gateway' ),
				'default'  => 'no',
				'disabled' => ! $this->is_configured(),
			),
			'title'                => array(
				'title'       => __( 'Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Anyday', 'reepay-checkout-gateway' ),
			),
			'description'          => array(
				'title'       => __( 'Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Anyday', 'reepay-checkout-gateway' ),
			),
		);
	}
}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Anyday' );
