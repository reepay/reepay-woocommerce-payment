<?php
/**
 * PP VerkkoPankki gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPVerkkoPankki
 *
 * @package Reepay\Checkout\Gateways
 */
class PPVerkkoPankki extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'verkkopankki',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_verkkopankki',
	);

	/**
	 * PPVerkkoPankki constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_verkkopankki';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Finland Banks', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
