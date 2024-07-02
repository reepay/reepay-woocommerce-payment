<?php
/**
 * PP GiroPay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPGiroPay
 *
 * @package Reepay\Checkout\Gateways
 */
class PPGiroPay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'giropay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_giropay',
	);

	/**
	 * PPGiroPay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_giropay';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - GiroPay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
