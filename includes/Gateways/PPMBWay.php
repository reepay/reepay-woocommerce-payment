<?php
/**
 * PP MB Way gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPMBWay
 *
 * @package Reepay\Checkout\Gateways
 */
class PPMBWay extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_MB_WAY;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'mbway',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_mb_way',
	);

	/**
	 * PPMBWay constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - MB Way', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
