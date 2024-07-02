<?php
/**
 * PP Multibanco gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPMultibanco
 *
 * @package Reepay\Checkout\Gateways
 */
class PPMultibanco extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'multibanco',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_multibanco',
	);

	/**
	 * PPMultibanco constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_multibanco';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Multibanco', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
