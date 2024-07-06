<?php
/**
 * PP Paysera gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPPaysera
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPaysera extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_PAYSERA;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'paysera',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_paysera',
	);

	/**
	 * PPPaysera constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Paysera', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
