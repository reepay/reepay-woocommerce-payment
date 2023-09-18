<?php
/**
 * Swish gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Swish
 *
 * @package Reepay\Checkout\Gateways
 */
class Swish extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'swish',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'swish',
	);

	/**
	 * Swish constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_swish';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Swish', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'swish' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
