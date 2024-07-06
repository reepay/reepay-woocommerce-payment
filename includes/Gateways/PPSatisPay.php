<?php
/**
 * PP SatisPay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPSatisPay
 *
 * @package Reepay\Checkout\Gateways
 */
class PPSatisPay extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_SATISPAY;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'satispay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_satispay',
	);

	/**
	 * PPSatisPay constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Satisfy', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
