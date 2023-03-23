<?php

namespace Reepay\Checkout;

defined( 'ABSPATH' ) || exit();

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

	public function __construct() {
		foreach ( self::PAYMENT_CLASSES as $class ) {
			$this->register_gateway( $class );
		}
	}

	/**
	 * Register payment gateway
	 *
	 * @param string $class_name
	 */
	private function register_gateway( $class_name ) {
		global $gateways;

		if ( ! $gateways ) {
			$gateways = array();
		}

		if ( ! isset( $gateways[ $class_name ] ) ) {
			// Initialize instance
			if ( $gateway = new $class_name() ) {
				$gateways[] = $class_name;

				// Register gateway instance
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
}

