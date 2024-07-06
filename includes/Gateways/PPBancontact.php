<?php
/**
 * PPBancontact gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPBancontact
 *
 * @package Reepay\Checkout\Gateways
 */
class PPBancontact extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_BANCONTACT;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'bancontact',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_bancontact',
	);

	/**
	 * PPBancontact constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Bancontact', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
