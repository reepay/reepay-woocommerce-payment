<?php
/**
 * PP Latvian Banks gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPLatvianBanks
 *
 * @package Reepay\Checkout\Gateways
 */
class PPLatvianBanks extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_LATVIA_BANKS;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'latvia_banks',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_latvia_banks',
	);

	/**
	 * PPLatvianBanks constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Latvian Banks', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
