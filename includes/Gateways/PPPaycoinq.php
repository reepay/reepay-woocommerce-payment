<?php
/**
 * PP Paycoinq gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPP24
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPaycoinq extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'paycoinq',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_paycoinq',
	);

	/**
	 * PPPaycoinq constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_paycoinq';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Paycoinq', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
