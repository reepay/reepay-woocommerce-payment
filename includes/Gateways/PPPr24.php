<?php
/**
 * PP Przelewy24 gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPPr24
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPr24 extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'p24',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_p24',
	);

	/**
	 * PPPr24 constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_p24';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Przelewy24', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
