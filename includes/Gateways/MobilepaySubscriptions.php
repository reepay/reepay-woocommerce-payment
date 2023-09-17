<?php
/**
 * MobilepaySubscriptions gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Api;

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
	public array $logos = array(
		'mobilepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'mobilepay_subscriptions',
	);

	/**
	 * MobilepaySubscriptions constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_mobilepay_subscriptions';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Mobilepay Subscriptions', 'reepay-checkout-gateway' );

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

		add_action( 'wp_ajax_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_nopriv_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
	}

	/**
	 * This payment method works only for subscription products
	 *
	 * @return bool
	 */
	public function is_available(): bool {
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
		parent::payment_fields();

		$this->tokenization_script();
		$this->saved_payment_methods();
		$this->save_payment_method_checkbox();
	}
}
