<?php
/**
 * Class ReepayCheckoutTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * ReepayCheckoutTest.
 *
 * @covers \Reepay\Checkout\Gateways\ReepayCheckout
 * @group gateways_checkout
 */
class ReepayCheckoutTest extends Reepay_UnitTestCase {
	/**
	 * ReepayCheckout
	 *
	 * @var ReepayCheckout
	 */
	private static ReepayCheckout $gateway;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$gateway = new ReepayCheckout();
	}

	// -----------------------------------------------------------------------
	// get_localize_script_data()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayCheckout::get_localize_script_data returns array with required keys.
	 *
	 * @group gateways_checkout
	 */
	public function test_get_localize_script_data_has_required_keys() {
		$data = self::$gateway->get_localize_script_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'payment_type', $data );
		$this->assertArrayHasKey( 'cancel_text', $data );
		$this->assertArrayHasKey( 'error_text', $data );
		$this->assertArrayHasKey( 'guest_checkout_disabled', $data );
		$this->assertArrayHasKey( 'registration_enabled', $data );
		$this->assertArrayHasKey( 'is_user_logged_in', $data );
	}

	/**
	 * Test @see ReepayCheckout::get_localize_script_data is_user_logged_in reflects real state.
	 *
	 * @group gateways_checkout
	 */
	public function test_get_localize_script_data_not_logged_in() {
		wp_set_current_user( 0 );

		$data = self::$gateway->get_localize_script_data();

		$this->assertFalse( $data['is_user_logged_in'] );
	}

	/**
	 * Test @see ReepayCheckout::get_localize_script_data is_user_logged_in reflects real state.
	 *
	 * @group gateways_checkout
	 */
	public function test_get_localize_script_data_logged_in() {
		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );

		$data = self::$gateway->get_localize_script_data();

		$this->assertTrue( $data['is_user_logged_in'] );

		wp_set_current_user( 0 );
	}

	// -----------------------------------------------------------------------
	// notice_message_* output
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayCheckout::notice_message_live_key_changed produces non-empty output.
	 *
	 * @group gateways_checkout
	 */
	public function test_notice_message_live_key_changed_produces_output() {
		ob_start();
		self::$gateway->notice_message_live_key_changed();
		$output = ob_get_clean();

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	/**
	 * Test @see ReepayCheckout::notice_message_test_mode_enabled produces non-empty output.
	 *
	 * @group gateways_checkout
	 */
	public function test_notice_message_test_mode_enabled_produces_output() {
		ob_start();
		self::$gateway->notice_message_test_mode_enabled();
		$output = ob_get_clean();

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	/**
	 * Test @see ReepayCheckout::notice_message_test_mode_disabled produces non-empty output.
	 *
	 * @group gateways_checkout
	 */
	public function test_notice_message_test_mode_disabled_produces_output() {
		ob_start();
		self::$gateway->notice_message_test_mode_disabled();
		$output = ob_get_clean();

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	// -----------------------------------------------------------------------
	// process_payment() — basic guards
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayCheckout::process_payment returns false for non-existent order ID.
	 *
	 * @group gateways_checkout
	 */
	public function test_process_payment_returns_false_for_missing_order() {
		$result = self::$gateway->process_payment( 999999999 );

		$this->assertFalse( $result );
	}

	/**
	 * Test @see ReepayCheckout::process_payment delegates to process_session_charge
	 * and returns success array when API call succeeds.
	 *
	 * @group gateways_checkout
	 */
	public function test_process_payment_returns_success_array() {
		wp_set_current_user( $this->factory()->user->create() );

		$this->order_generator->set_prop( 'payment_method', self::$gateway->id );
		$this->order_generator->add_product( 'simple' );
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();

		// Mock the API recurring/charge call to return a valid session.
		$this->api_mock->method( 'request' )->willReturn(
			array(
				'id'  => 'session_abc123',
				'url' => 'https://checkout.reepay.com/pay/session_abc123',
			)
		);

		$this->api_mock->method( 'get_customer_handle_by_order' )->willReturn( 'customer-1' );

		$result = self::$gateway->process_payment( $order_id );

		$this->assertIsArray( $result );
		$this->assertSame( 'success', $result['result'] );

		wp_set_current_user( 0 );
	}

	/**
	 * Test @see ReepayCheckout::process_payment returns false when API returns WP_Error.
	 *
	 * @group gateways_checkout
	 */
	public function test_process_payment_returns_failure_on_api_error() {
		wp_set_current_user( $this->factory()->user->create() );

		$this->order_generator->set_prop( 'payment_method', self::$gateway->id );
		$this->order_generator->add_product( 'simple' );
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();

		$this->api_mock->method( 'request' )->willReturn( new WP_Error( '100', 'API error' ) );
		$this->api_mock->method( 'get_customer_handle_by_order' )->willReturn( 'customer-1' );

		$result = self::$gateway->process_payment( $order_id );

		// When session charge fails the method returns a failure array or false.
		$this->assertTrue(
			false === $result || ( is_array( $result ) && 'failure' === ( $result['result'] ?? '' ) ),
			'Expected false or result=failure on API error'
		);

		wp_set_current_user( 0 );
	}

	// -----------------------------------------------------------------------
	// Gateway identity
	// -----------------------------------------------------------------------

	/**
	 * Test that gateway ID is set correctly.
	 *
	 * @group gateways_checkout
	 */
	public function test_gateway_id_is_reepay_checkout() {
		$this->assertSame( 'reepay_checkout', self::$gateway->id );
	}

	/**
	 * Test that gateway supports expected features.
	 *
	 * @group gateways_checkout
	 */
	public function test_gateway_supports_products_and_refunds() {
		$this->assertContains( 'products', self::$gateway->supports );
		$this->assertContains( 'refunds', self::$gateway->supports );
		$this->assertContains( 'tokenization', self::$gateway->supports );
	}
}
