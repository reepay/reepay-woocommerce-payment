<?php
/**
 * Vipps gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Vipps
 *
 * @package Reepay\Checkout\Gateways
 */
class Vipps extends ReepayGateway {
	public const ID = 'reepay_vipps';

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'vipps',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'vipps',
	);

	/**
	 * Vipps constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Vipps', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'vipps' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
