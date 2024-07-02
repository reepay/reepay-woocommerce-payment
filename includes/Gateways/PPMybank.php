<?php
/**
 * PP Mybank gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPMybank
 *
 * @package Reepay\Checkout\Gateways
 */
class PPMybank extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'mybank',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_mybank',
	);

	/**
	 * PPMybank constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_mybank';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - MBank', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
