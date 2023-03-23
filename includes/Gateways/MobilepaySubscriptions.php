<?php

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

class MobilepaySubscriptions extends ReepayGateway {
	/**
	 * Logos
	 *
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
		'mobilepay_subscriptions',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
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
			'multiple_subscriptions',
		);

		$this->logos = array( 'mobilepay' );

		parent::__construct();

		// Load setting from parent method
		$this->apply_parent_settings();
	}

	/**
	 * Initialise Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
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
				'default'     => __( 'Reepay - Mobilepay Subscriptions', 'reepay-checkout-gateway' ),
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
	 *
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
	 *
	 * @return void
	 */
	public function payment_fields() {
		reepay()->get_template(
			'checkout/payment-fields.php',
			array(
				'gateway' => $this,
			)
		);

		// The "Save card or use existed" form should be appeared when active or when the cart has a subscription
		if ( ! is_add_payment_method_page()
			 || wcs_cart_have_subscription()
			 || wcs_is_payment_change()
		) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}
	}
}
