<?php
/**
 * PP Eps gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPEps
 *
 * @package Reepay\Checkout\Gateways
 */
class PPEps extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_EPS;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'eps',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_eps',
	);

	/**
	 * PPEps constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - EPS', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
