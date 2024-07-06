<?php
/**
 * PP Blik gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPBlik
 *
 * @package Reepay\Checkout\Gateways
 */
class PPBlik extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_BLIK_OC;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'blik',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_blik_oc',
	);

	/**
	 * PPBlik constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - BLIK', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
