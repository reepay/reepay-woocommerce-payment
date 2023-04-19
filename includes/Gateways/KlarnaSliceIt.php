<?php
/**
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class KlarnaSliceIt
 *
 * @package Reepay\Checkout\Gateways
 */
class KlarnaSliceIt extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public $logos = array(
		'klarna',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'klarna_slice_it',
	);

	/**
	 * KlarnaSliceIt constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_klarna_slice_it';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Klarna Slice It', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();
	}
}
