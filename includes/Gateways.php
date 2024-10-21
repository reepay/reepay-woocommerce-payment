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
		'reepay_swish',
		'reepay_viabill',
		'reepay_vipps',
		'reepay_vipps_epayment',
		'reepay_vipps_recurring',
		'reepay_pp_ideal',
		'reepay_pp_sepa',
		'reepay_klarna_direct_bank_transfer',
		'reepay_klarna_direct_debit',
		'reepay_pp_bancontact',
		'reepay_pp_blik_oc',
		'reepay_pp_eps',
		'reepay_pp_estonia_banks',
		'reepay_pp_giropay',
		'reepay_pp_latvia_banks',
		'reepay_pp_lithuania_banks',
		'reepay_pp_mb_way',
		'reepay_pp_multibanco',
		'reepay_pp_mybank',
		'reepay_pp_p24',
		'reepay_pp_paycoinq',
		'reepay_pp_paysafecard',
		'reepay_pp_paysera',
		'reepay_pp_postfinance',
		'reepay_pp_satispay',
		'reepay_pp_trustly',
		'reepay_pp_verkkopankki',
		'reepay_pp_wechatpay',
		'reepay_pe_santander',
		'reepay_offline_bank_transfer',
		'reepay_offline_cash',
		'reepay_offline_other',
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
		Gateways\Swish::class,
		Gateways\Viabill::class,
		Gateways\Vipps::class,
		Gateways\VippsMobilepay::class,
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
		return $this->gateways['reepay_checkout'] ?? null;
	}
}
