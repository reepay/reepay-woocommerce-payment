<?php
/**
 * Klarna Direct Bank Transfer gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class KlarnaDBT
 *
 * @package Reepay\Checkout\Gateways
 */
class KlarnaDBT extends ReepayGateway {
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
		'klarna_direct_bank_transfer',
	);

	/**
	 * KlarnaDBT constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_klarna_direct_bank_transfer';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Klarna Direct Bank Transfer', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
