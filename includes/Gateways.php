<?php
/**
 * Gateways registration
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout;

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Gateways\ReepayGateway;

defined( 'ABSPATH' ) || exit();

/**
 * Class Gateways
 *
 * @package Reepay\Checkout
 */
class Gateways {
	/**
	 * List of payment method ids
	 *
	 * @var array
	 */
	const PAYMENT_METHODS = array(
		'reepay_checkout',

		'reepay_anyday',
		'reepay_applepay',
		'reepay_googlepay',
		'reepay_klarna_pay_later',
		'reepay_klarna_pay_now',
		'reepay_klarna_slice_it',
		'reepay_mobilepay',
		'reepay_mobilepay_subscriptions',
		'reepay_paypal',
		'reepay_resurs',
		'reepay_swish',
		'reepay_viabill',
		'reepay_vipps',
	);

	/**
	 * List of payment classes
	 *
	 * @var array
	 */
	const PAYMENT_CLASSES = array(
		Gateways\ReepayCheckout::class,

		Gateways\Anyday::class,
		Gateways\ApplePay::class,
		Gateways\Googlepay::class,
		Gateways\KlarnaPayLater::class,
		Gateways\KlarnaPayNow::class,
		Gateways\KlarnaSliceIt::class,
		Gateways\Mobilepay::class,
		Gateways\MobilepaySubscriptions::class,
		Gateways\Paypal::class,
		Gateways\Resurs::class,
		Gateways\Swish::class,
		Gateways\Viabill::class,
		Gateways\Vipps::class,
	);

	/**
	 * Gateway instances
	 *
	 * @var array
	 */
	private array $gateways = array();

	/**
	 * Gateways constructor.
	 */
	public function __construct() {
		foreach ( self::PAYMENT_CLASSES as $class ) {
			$this->register_gateway( $class );
		}
	}

	/**
	 * Register payment gateway
	 *
	 * @param string $class_name gateway class name to register.
	 */
	private function register_gateway( string $class_name ) {
		if ( ! isset( $gateways[ $class_name ] ) && class_exists( $class_name, true ) ) {
			$gateway = new $class_name();

			if ( ! empty( $gateway ) ) {
				/**
				 * Reepay gateway class instance
				 *
				 * @var ReepayGateway $gateway
				 */
				$this->gateways[ $gateway->id ] = $gateway;

				// Register gateway instance.
				add_filter(
					'woocommerce_payment_gateways',
					function ( $methods ) use ( $gateway ) {
						$methods[] = $gateway;

						return $methods;
					}
				);
			}
		}
	}

	/**
	 * Get gateway instance by id
	 *
	 * @param string $id gateway id.
	 *
	 * @return ReepayGateway|null
	 */
	public function get_gateway( string $id ): ?ReepayGateway {
		return $this->gateways[ $id ] ?? null;
	}

	/**
	 * Shortcut for reepay_checkout gateway
	 *
	 * @return ReepayCheckout
	 */
	public function checkout(): ?ReepayCheckout {
		return $this->gateways['reepay_checkout'] ?? null;
	}
}

