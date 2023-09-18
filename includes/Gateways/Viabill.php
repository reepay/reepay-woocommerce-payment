<?php
/**
 * Viabill gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Viabill
 *
 * @package Reepay\Checkout\Gateways
 */
class Viabill extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'viabill',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'viabill',
	);

	/**
	 * Viabill constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_viabill';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - ViaBill', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'viabill' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
