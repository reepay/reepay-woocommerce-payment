<?php
/**
 * Class ReepayCheckoutTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

class ReepayGatewayTestChild extends ReepayGateway {

}

/**
 * AnydayTest.
 */
class ReepayGatewayTest extends Reepay_UnitTestCase {

	public static ReepayGatewayTestChild $gateway;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$gateway = new ReepayGatewayTestChild();
	}

	/**
	 * @param bool $include_tax
	 * @param bool $only_not_settled
	 *
	 * @testWith
	 * [false, false]
	 * [false, true]
	 * [true, false]
	 * [true, true]
	 */
	public function test_get_order_items( bool $include_tax, bool $only_not_settled ) {
//		$this->order_generator->add_product('simple', )
	}

	/**
	 * @param $card_type
	 * @param $result
	 *
	 * @testWith
	 * ["visa", "visa"]
	 * ["mc", "mastercard"]
	 * ["dankort", "dankort"]
	 * ["visa_dk", "dankort"]
	 * ["ffk", "forbrugsforeningen"]
	 * ["visa_elec", "visa-electron"]
	 * ["maestro", "maestro"]
	 * ["amex", "american-express"]
	 * ["diners", "diners"]
	 * ["discover", "discover"]
	 * ["jcb", "jcb"]
	 * ["mobilepay", "mobilepay"]
	 * ["ms_subscripiton", "mobilepay"]
	 * ["viabill", "viabill"]
	 * ["klarna_pay_later", "klarna"]
	 * ["klarna_pay_now", "klarna"]
	 * ["resurs", "resurs"]
	 * ["china_union_pay", "cup"]
	 * ["paypal", "paypal"]
	 * ["applepay", "applepay"]
	 * ["googlepay", "googlepay"]
	 * ["vipps", "vipps"]
	 */
	public function test_logo( $card_type, $result ) {
		$this->assertSame(
			reepay()->get_setting( 'images_url' ) . 'svg/' . $result . '.logo.svg',
			self::$gateway->get_logo( $card_type )
		);
	}

	public function test_logo_default() {
		$card_type = 'custom';

		$default_card_type = reset( self::$gateway->logos );

		$this->assertSame(
			reepay()->get_setting( 'images_url' ) . $default_card_type . '.png',
			self::$gateway->get_logo( $card_type )
		);
	}
}
