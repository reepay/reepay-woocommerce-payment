<?php
/**
 * KlarnaPayLater gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class KlarnaPayLater
 *
 * @package Reepay\Checkout\Gateways
 */
class KlarnaPayLater extends ReepayGateway {
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
		'klarna_pay_later',
	);

	/**
	 * KlarnaPayLater constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_klarna_pay_later';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Klarna Pay Later', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
