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
	public const PAYMENT_METHODS = array(
		Gateways\ReepayCheckout::ID,

		Gateways\Anyday::ID,
		Gateways\ApplePay::ID,
		Gateways\Googlepay::ID,
		Gateways\KlarnaPayLater::ID,
		Gateways\KlarnaPayNow::ID,
		Gateways\KlarnaSliceIt::ID,
		Gateways\Mobilepay::ID,
		Gateways\MobilepaySubscriptions::ID,
		Gateways\Paypal::ID,
		Gateways\Resurs::ID,
		Gateways\Swish::ID,
		Gateways\Viabill::ID,
		Gateways\Vipps::ID,
		Gateways\VippsRecurring::ID,
		Gateways\PPIdeal::ID,
		Gateways\PPSepa::ID,
		Gateways\KlarnaDBT::ID,
		Gateways\KlarnaDD::ID,
		Gateways\PPBancontact::ID,
		Gateways\PPBlik::ID,
		Gateways\PPEps::ID,
		Gateways\PPEstonianBanks::ID,
		Gateways\PPGiroPay::ID,
		Gateways\PPLatvianBanks::ID,
		Gateways\PPLithuanianBanks::ID,
		Gateways\PPMBWay::ID,
		Gateways\PPMultibanco::ID,
		Gateways\PPMybank::ID,
		Gateways\PPPr24::ID,
		Gateways\PPPaycoinq::ID,
		Gateways\PPPaySafeCard::ID,
		Gateways\PPPaysera::ID,
		Gateways\PPPostFinance::ID,
		Gateways\PPSatisPay::ID,
		Gateways\PPTrustly::ID,
		Gateways\PPVerkkoPankki::ID,
		Gateways\PPWeChatPay::ID,
		Gateways\PESantander::ID,
		Gateways\OfflineTransfer::ID,
		Gateways\OfflineCash::ID,
		Gateways\Other::ID,
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
		Gateways\VippsRecurring::class,
		Gateways\PPIdeal::class,
		Gateways\PPSepa::class,
		Gateways\KlarnaDBT::class,
		Gateways\KlarnaDD::class,
		Gateways\PPBancontact::class,
		Gateways\PPBlik::class,
		Gateways\PPEps::class,
		Gateways\PPEstonianBanks::class,
		Gateways\PPGiroPay::class,
		Gateways\PPLatvianBanks::class,
		Gateways\PPLithuanianBanks::class,
		Gateways\PPMBWay::class,
		Gateways\PPMultibanco::class,
		Gateways\PPMybank::class,
		Gateways\PPPr24::class,
		Gateways\PPPaycoinq::class,
		Gateways\PPPaySafeCard::class,
		Gateways\PPPaysera::class,
		Gateways\PPPostFinance::class,
		Gateways\PPSatisPay::class,
		Gateways\PPTrustly::class,
		Gateways\PPVerkkoPankki::class,
		Gateways\PPWeChatPay::class,
		Gateways\PESantander::class,
		Gateways\OfflineTransfer::class,
		Gateways\OfflineCash::class,
		Gateways\Other::class,
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
		return $this->gateways[ Gateways\ReepayCheckout::ID ] ?? null;
	}
}
