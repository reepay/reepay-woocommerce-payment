<?php
/**
 * Viabill gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class Viabill
 *
 * @package Reepay\Checkout\Gateways
 */
class Viabill extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::VIABILL;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'viabill',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'viabill',
	);

	/**
	 * Viabill constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - ViaBill', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
