<?php
/**
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class MobilepaySubscriptions
 *
 * @package Reepay\Checkout\Gateways
 */
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
	 * MobilepaySubscriptions constructor.
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

		$this->apply_parent_settings();
	}

	/**
	 * This payment method works only for subscription products
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available()
			   && ( ( is_checkout() && wcs_cart_have_subscription() )
					|| is_add_payment_method_page() );
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
				'description' => $this->get_description(),
			)
		);

		// The "Save card or use existed" form should be appeared when active or when the cart has a subscription.
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
