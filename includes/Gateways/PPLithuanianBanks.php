<?php
/**
 * PP Lithuanian Banks gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPLithuanianBanks
 *
 * @package Reepay\Checkout\Gateways
 */
class PPLithuanianBanks extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_LITHUANIA_BANKS;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'lithuania_banks',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_lithuania_banks',
	);

	/**
	 * PPLithuanianBanks constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Lithuanian Banks', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
