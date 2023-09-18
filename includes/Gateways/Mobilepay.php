<?php
/**
 * Mobilepay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Mobilepay
 *
 * @package Reepay\Checkout\Gateways
 */
class Mobilepay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'mobilepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'mobilepay',
	);

	/**
	 * Mobilepay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_mobilepay';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Mobilepay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'mobilepay' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
