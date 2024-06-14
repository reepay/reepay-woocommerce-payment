<?php
/**
 * PP Santander gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPSantander
 *
 * @package Reepay\Checkout\Gateways
 */
class PPSantander extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'santander',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pe_santander',
	);

	/**
	 * PPSantander constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pe_santander';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Santander', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
