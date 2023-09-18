<?php
/**
 * Paypal gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Paypal
 *
 * @package Reepay\Checkout\Gateways
 */
class Paypal extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'paypal',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'paypal',
	);

	/**
	 * Paypal constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_paypal';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - PayPal', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'paypal' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
