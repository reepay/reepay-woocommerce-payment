<?php
/**
 * PPBancontact gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPBancontact
 *
 * @package Reepay\Checkout\Gateways
 */
class PPBancontact extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'bancontact',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_bancontact',
	);

	/**
	 * PPBancontact constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_bancontact';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Bancontact', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'bancontact' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
