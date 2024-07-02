<?php
/**
 * PP PostFinance gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPPostFinance
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPostFinance extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'postfinance',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_postfinance',
	);

	/**
	 * PPPostFinance constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_postfinance';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - PostFinance', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
