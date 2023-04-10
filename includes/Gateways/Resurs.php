<?php
/**
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Resurs
 *
 * @package Reepay\Checkout\Gateways
 */
class Resurs extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public $logos = array(
		'resurs',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'resurs',
	);

	/**
	 * Resurs constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_resurs';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Resurs Bank', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'resurs' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
