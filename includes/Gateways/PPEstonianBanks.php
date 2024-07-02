<?php
/**
 * PP Estonian Banks gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPEstonianBanks
 *
 * @package Reepay\Checkout\Gateways
 */
class PPEstonianBanks extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_ESTONIA_BANKS;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'estonia_banks',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_estonia_banks',
	);

	/**
	 * PPEstonianBanks constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Estonian Banks', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
