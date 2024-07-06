<?php
/**
 * PP Santander gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PESantander
 *
 * @package Reepay\Checkout\Gateways
 */
class PESantander extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PE_SANTANDER;
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'santander',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pe_santander',
	);

	/**
	 * PESantander constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Santander', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
