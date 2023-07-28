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
	 * Test function test_rp_card_store
	 *
	 * @param string $token card token from callback.
	 *
	 * @testWith
	 * ["ca_f73e13e5784a5dff32f2f93be7a8130f"]
	 */
	public function test_rp_card_store( string $token ) {
		// Выяснить как делать запросы к апи в тестах или обойти их
		$this->expectException( Exception::class );
		self::$gateway->add_payment_token_to_customer( $this->user->ID, $token );
	}

	/**
	 * Test function test_rp_finalize
	 *
	 * @param string $token card token from callback.
	 *
	 * @testWith
	 * ["ca_f73e13e5784a5dff32f2f93be7a8130f"]
	 */
	public function test_rp_finalize( string $token ) { // Выяснить как делать запросы к апи в тестах или обойти их
		$_GET['payment_method'] = $token;

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$_GET['key'] = $this->order_generator->order()->get_order_key();
		self::$gateway->reepay_finalize();
	}



}
