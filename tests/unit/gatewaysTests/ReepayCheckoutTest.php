<?php
/**
 * Class ReepayCheckoutTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * AnydayTest.
 */
class ReepayCheckoutTest extends Reepay_UnitTestCase {
	/**
	 * ReepayCheckout
	 *
	 * @var ReepayCheckout
	 */
	private static ReepayCheckout $gateway;

	/**
	 * WC_Order instance
	 *
	 * @var WP_User
	 */
	private WP_User $user;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$gateway = new ReepayCheckout();
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$user_id    = wc_create_new_customer( 'test@test.com', 'test_case_user', 'test_case_user' );
		$this->user = get_user_by( 'id', $user_id );
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->user->delete( true );
	}

	/**
	 * Test function test_rp_finalize
	 *
	 * @param string $token card token.
	 * @param string $exp_date card expiry date.
	 * @param string $masked_card card number.
	 * @param string $card_type card type.
	 *
	 * @testWith
	 * ["ca_f73e13e5784a5dff32f2f93be7a8130f", "05-43", "411111XXXXXX1111", "Visa"]
	 */
	public function test_rp_finalize( string $token, string $exp_date, string $masked_card, string $card_type ) {
		$_GET['payment_method'] = $token;

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$this->api_mock->method( 'get_reepay_cards' )->willReturn(
			array(
				'id'          => $token,
				'exp_date'    => $exp_date,
				'masked_card' => $masked_card,
				'card_type'   => $card_type,
			)
		);

		$_GET['key'] = $this->order_generator->order()->get_order_key();

		$this->expectError();

		self::$gateway->reepay_finalize();
	}

	/**
	 * Test function rp_check_is_active
	 *
	 * Test @param string $gateway gateway id.
	 *
	 * @testWith
	 * ["anyday"]
	 * ["applepay"]
	 * ["googlepay"]
	 * ["klarna_pay_later"]
	 * ["klarna_pay_now"]
	 * ["klarna_slice_it"]
	 * ["mobilepay"]
	 * ["mobilepay_subscriptions"]
	 * ["paypal"]
	 * ["checkout"]
	 * ["resurs"]
	 * ["swish"]
	 * ["viabill"]
	 * ["vipps"]
	 * @see ReepayGateway::check_is_active
	 */
	public function test_rp_check_is_active( string $gateway ) {
		self::$gateway->id = 'reepay_' . $gateway;

		$this->api_mock->method( 'request' )->willReturn(
			array(
				array(
					'type' => $gateway,
				),
			)
		);

		$this->assertTrue( self::$gateway->check_is_active() );
	}

	/**
	 * Test function is_gateway_settings_page
	 *
	 * Test @param string $gateway gateway id.
	 *
	 * @testWith
	 * ["anyday"]
	 * ["applepay"]
	 * ["googlepay"]
	 * ["klarna_pay_later"]
	 * ["klarna_pay_now"]
	 * ["klarna_slice_it"]
	 * ["mobilepay"]
	 * ["mobilepay_subscriptions"]
	 * ["paypal"]
	 * ["checkout"]
	 * ["resurs"]
	 * ["swish"]
	 * ["viabill"]
	 * ["vipps"]
	 */
	public function test_rp_is_gateway_settings_page( string $gateway ) {
		$_GET['tab']       = 'checkout';
		$_GET['section']   = $gateway;
		self::$gateway->id = $gateway;

		$this->assertTrue( self::$gateway->is_gateway_settings_page() );
	}

	/**
	 * Test function rp_get_account_info
	 *
	 * Test @param bool $is_test use test or live reepay api keys.
	 * Test @param string $handle handle of account.
	 *
	 * @testWith
	 * [true, "test-account-handle"]
	 * [false, "live-account-handle"]
	 * @see ReepayGateway::get_account_info
	 */
	public function test_rp_get_account_info( bool $is_test, string $handle ) {
		$_GET['tab']       = 'checkout';
		$_GET['section']   = 'checkout';
		self::$gateway->id = 'checkout';

		$result = array(
			'handle' => $handle,
		);

		$this->api_mock->method( 'request' )->willReturn(
			$result
		);

		$this->assertSame(
			$result,
			self::$gateway->get_account_info( $is_test )
		);
	}
}