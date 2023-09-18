<?php
/**
 * KlarnaPayNow gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class KlarnaPayNow
 *
 * @package Reepay\Checkout\Gateways
 */
class KlarnaPayNow extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'klarna',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'klarna_pay_now',
	);

	/**
	 * KlarnaPayNow constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_klarna_pay_now';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Klarna Pay Now', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
