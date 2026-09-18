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
		// Explicit price ensures order total > 0 so the subscription-only path is skipped.
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();

		$session_response = array(
			'id'  => 'session_abc123',
			'url' => 'https://checkout.reepay.com/pay/session_abc123',
		);

		// Mock both session_charge (request) and recurring paths.
		$this->api_mock->method( 'request' )->willReturn( $session_response );
		$this->api_mock->method( 'recurring' )->willReturn( $session_response );
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
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();

		$api_error = new WP_Error( '100', 'API error' );

		// Mock both paths to return a WP_Error.
		$this->api_mock->method( 'request' )->willReturn( $api_error );
		$this->api_mock->method( 'recurring' )->willReturn( $api_error );
		$this->api_mock->method( 'get_customer_handle_by_order' )->willReturn( 'customer-1' );

		$threw  = false;
		$result = null;
		try {
			$result = self::$gateway->process_payment( $order_id );
		} catch ( \Throwable $e ) {
			// process_payment may throw on API error in some paths.
			$threw = true;
		}

		// When session/recurring call fails the gateway must NOT return a success array.
		if ( ! $threw ) {
			$this->assertTrue(
				false === $result || ( is_array( $result ) && 'success' !== ( $result['result'] ?? 'success' ) ),
				'Expected non-success result or exception on API error'
			);
		}

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

	// -----------------------------------------------------------------------
	// Settings ingestion — handle_failover / enable_order_autocancel
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayCheckout::__construct keeps handle_failover as the literal
	 * saved setting string, not a boolean-derived "1"/"" (BWPM-275).
	 *
	 * @group gateways_checkout
	 */
	public function test_handle_failover_reflects_saved_yes_setting() {
		update_option(
			'woocommerce_reepay_checkout_settings',
			array_merge( self::$gateway->settings, array( 'handle_failover' => 'yes' ) )
		);

		$gateway = new ReepayCheckout();

		$this->assertSame( 'yes', $gateway->handle_failover );
	}

	/**
	 * Test @see ReepayCheckout::__construct keeps handle_failover as the literal
	 * saved setting string when explicitly disabled (BWPM-275).
	 *
	 * @group gateways_checkout
	 */
	public function test_handle_failover_reflects_saved_no_setting() {
		update_option(
			'woocommerce_reepay_checkout_settings',
			array_merge( self::$gateway->settings, array( 'handle_failover' => 'no' ) )
		);

		$gateway = new ReepayCheckout();

		$this->assertSame( 'no', $gateway->handle_failover );
	}

	/**
	 * Test @see ReepayCheckout::__construct keeps enable_order_autocancel as the
	 * literal saved setting string, not a boolean-derived "1"/"" (BWPM-275).
	 *
	 * @group gateways_checkout
	 */
	public function test_enable_order_autocancel_reflects_saved_yes_setting() {
		update_option(
			'woocommerce_reepay_checkout_settings',
			array_merge( self::$gateway->settings, array( 'enable_order_autocancel' => 'yes' ) )
		);

		$gateway = new ReepayCheckout();

		$this->assertSame( 'yes', $gateway->enable_order_autocancel );
	}

	// -----------------------------------------------------------------------
	// "Payment window configuration" setting (BWPM-279)
	// -----------------------------------------------------------------------

	/**
	 * Resets $_GET after each test in this group so is_gateway_settings_page()
	 * doesn't leak into unrelated tests.
	 */
	public function tear_down() {
		unset( $_GET['tab'], $_GET['section'] );

		parent::tear_down();
	}

	/**
	 * Test @see ReepayCheckout::init_form_fields registers the "payment_window_configuration"
	 * select field.
	 *
	 * @group gateways_checkout
	 */
	public function test_init_form_fields_has_payment_window_configuration_field() {
		unset( $_GET['tab'], $_GET['section'] );

		self::$gateway->init_form_fields();

		$this->assertArrayHasKey( 'payment_window_configuration', self::$gateway->form_fields );
		$this->assertSame( 'select', self::$gateway->form_fields['payment_window_configuration']['type'] );
	}

	/**
	 * Test @see ReepayCheckout::get_payment_window_configuration_options fetches
	 * the configuration list from the API when on the gateway settings page.
	 *
	 * @group gateways_checkout
	 */
	public function test_payment_window_configuration_options_fetched_on_settings_page() {
		$_GET['tab']     = 'checkout';
		$_GET['section'] = self::$gateway->id;

		$this->api_mock->method( 'get_configurations' )->willReturn(
			array(
				array(
					'name'   => 'Boyd test',
					'handle' => 'boyd-test',
				),
			)
		);

		self::$gateway->init_form_fields();

		$this->assertArrayHasKey(
			'boyd-test',
			self::$gateway->form_fields['payment_window_configuration']['options']
		);
	}

	/**
	 * Test @see ReepayCheckout::get_payment_window_configuration_options does NOT call
	 * the API outside the gateway settings page, to avoid an extra HTTP request on
	 * every checkout/cart/admin page load.
	 *
	 * @group gateways_checkout
	 */
	public function test_payment_window_configuration_not_fetched_outside_settings_page() {
		unset( $_GET['tab'], $_GET['section'] );

		$this->api_mock->expects( $this->never() )->method( 'get_configurations' );

		self::$gateway->init_form_fields();

		$this->assertArrayHasKey( 'payment_window_configuration', self::$gateway->form_fields );
	}

	/**
	 * Test @see ReepayCheckout::get_payment_window_configuration_options keeps a
	 * previously saved handle in the options list even if the API no longer returns
	 * it, so WooCommerce's validate_select_field() doesn't silently reset the setting.
	 *
	 * @group gateways_checkout
	 */
	public function test_payment_window_configuration_preserves_saved_handle_when_api_returns_empty() {
		self::$options->set_option( 'payment_window_configuration', 'legacy-handle' );
		// self::$gateway is a separate WC_Settings_API instance from the singleton
		// OptionsController writes through, so its settings cache must be refreshed.
		self::$gateway->init_settings();

		$_GET['tab']     = 'checkout';
		$_GET['section'] = self::$gateway->id;

		$this->api_mock->method( 'get_configurations' )->willReturn( array() );

		self::$gateway->init_form_fields();

		$this->assertArrayHasKey(
			'legacy-handle',
			self::$gateway->form_fields['payment_window_configuration']['options']
		);
	}
}
