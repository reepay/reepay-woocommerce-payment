<?php
/**
 * PP Przelewy24 gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPPr24
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPr24 extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_P24;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'p24',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_p24',
	);

	/**
	 * PPPr24 constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Przelewy24', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
