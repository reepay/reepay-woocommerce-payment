<?php
/**
 * Klarna Direct Debit gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class KlarnaDD
 *
 * @package Reepay\Checkout\Gateways
 */
class KlarnaDD extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'klarna',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'klarna_direct_debit',
	);

	/**
	 * KlarnaDD constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_klarna_direct_debit';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Klarna Direct Debit', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
