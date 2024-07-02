<?php
/**
 * PP MB Way gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPMBWay
 *
 * @package Reepay\Checkout\Gateways
 */
class PPMBWay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'mbway',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_mb_way',
	);

	/**
	 * PPMBWay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_mb_way';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - MB Way', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
