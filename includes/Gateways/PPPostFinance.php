<?php
/**
 * PP PostFinance gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPPostFinance
 *
 * @package Reepay\Checkout\Gateways
 */
class PPPostFinance extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_POSTFINANCE;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'postfinance',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_postfinance',
	);

	/**
	 * PPPostFinance constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - PostFinance', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
