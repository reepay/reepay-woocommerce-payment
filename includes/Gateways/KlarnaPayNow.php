<?php
/**
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
	public $logos = array(
		'klarna',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'klarna_pay_now',
	);

	/**
	 * KlarnaPayNow constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_klarna_pay_now';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Klarna Pay Now', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
