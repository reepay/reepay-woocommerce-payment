<?php
/**
 * PP SatisPay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPSatisPay
 *
 * @package Reepay\Checkout\Gateways
 */
class PPSatisPay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'satispay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_satispay',
	);

	/**
	 * PPSatisPay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_satispay';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Satisfy', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
