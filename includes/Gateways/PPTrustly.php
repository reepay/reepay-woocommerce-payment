<?php
/**
 * PP Trustly gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPTrustly
 *
 * @package Reepay\Checkout\Gateways
 */
class PPTrustly extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_TRUSTLY;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'trustly',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_trustly',
	);

	/**
	 * PPTrustly constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Trustly', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
