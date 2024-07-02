<?php
/**
 * KlarnaSliceIt gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class KlarnaSliceIt
 *
 * @package Reepay\Checkout\Gateways
 */
class KlarnaSliceIt extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::KLARNA_SLICE_IT;

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
		'klarna_slice_it',
	);

	/**
	 * KlarnaSliceIt constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Klarna Slice It', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
