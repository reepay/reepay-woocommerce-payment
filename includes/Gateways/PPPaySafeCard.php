<?php
/**
 * PP  PaySafeCard gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPPaySafeCard
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPaySafeCard extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'paysafecard',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_paysafecard',
	);

	/**
	 * PPPaySafeCard constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_paysafecard';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Paysafecard', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
