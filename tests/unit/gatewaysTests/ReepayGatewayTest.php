<?php
/**
 * Class ReepayCheckoutTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\Tests\Helpers\OrderItemsGenerator;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

class ReepayGatewayTestChild extends ReepayGateway {

}

/**
 * ReepayGatewayTest.
 */
class ReepayGatewayTest extends Reepay_UnitTestCase {

	public static ReepayGatewayTestChild $gateway;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$gateway = new ReepayGatewayTestChild();
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		update_option( 'woocommerce_calc_taxes', 'no' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
	}

	/**
	 * @param string $gateway gateway id.
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
	 * ["swish"]
	 * ["viabill"]
	 * ["vipps"]
	 *
	 */
	public function test_check_is_active( string $gateway ) {
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
	 * @param string $gateway gateway id.
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
	 * ["swish"]
	 * ["viabill"]
	 * ["vipps"]
	 */
	public function test_is_gateway_settings_page( string $gateway ) {
		$_GET['tab']       = 'checkout';
		$_GET['section']   = $gateway;
		self::$gateway->id = $gateway;

		$this->assertTrue( self::$gateway->is_gateway_settings_page() );
	}

	/**
	 * @param bool $is_test use test or live reepay api keys.
	 *
	 * @testWith
	 * [true]
	 * [false]
	 */
	public function test_get_account_info_not_on_settings_page( bool $is_test ) {
		$this->assertFalse( self::$gateway->is_gateway_settings_page() );
		$this->assertEmpty( self::$gateway->get_account_info( $is_test ) );
	}

	/**
	 * @param bool $is_test use test or live reepay api keys.
	 *
	 * @testWith
	 * [true]
	 * [false]
	 */
	public function test_get_account_info( bool $is_test ) {
		$_GET['tab']       = 'checkout';
		$_GET['section']   = 'checkout';
		self::$gateway->id = 'checkout';

		$result = array(
			'handle' => 'test_1234',
		);

		$this->api_mock->method( 'request' )->willReturn( $result );
		$this->assertSame(
			$result,
			self::$gateway->get_account_info( $is_test )
		);

		$this->api_mock->method( 'request' )->willReturn( 'unused' );
		$this->assertSame(
			$result,
			self::$gateway->get_account_info( $is_test ),
			'transient cache error'
		);
	}

	/**
	 * @testWith
	 * [true]
	 * [false]
	 */
	public function test_can_capture( bool $result ) {
		$this->api_mock->method( 'can_capture' )->willReturn( $result );

		$this->assertSame(
			$result,
			self::$gateway->can_capture( $this->order_generator->order() )
		);
	}

	/**
	 * @testWith
	 * [true]
	 * [false]
	 */
	public function test_can_cancel( bool $result ) {
		$this->api_mock->method( 'can_cancel' )->willReturn( $result );

		$this->assertSame(
			$result,
			self::$gateway->can_cancel( $this->order_generator->order() )
		);
	}

	public function test_capture_payment_with_cancelled_order() {
		$this->order_generator->set_meta( '_reepay_order_cancelled', '1' );

		$this->expectException(Exception::class);

		self::$gateway->capture_payment( $this->order_generator->order() );
	}

	public function test_capture_payment_with_api_error() {
		$this->api_mock->method( 'capture_payment' )->willReturn( new WP_Error() );

		$this->expectException(Exception::class);

		self::$gateway->capture_payment( $this->order_generator->order() );
	}

	public function test_cancel_payment_with_cancelled_order() {
		$this->order_generator->set_meta( '_reepay_order_cancelled', '1' );

		$this->expectException(Exception::class);

		self::$gateway->cancel_payment( $this->order_generator->order() );
	}

	public function test_cancel_payment_with_api_error() {
		$this->api_mock->method( 'cancel_payment' )->willReturn( new WP_Error() );

		$this->expectException(Exception::class);

		self::$gateway->cancel_payment( $this->order_generator->order() );
	}

	public function test_refund_payment_with_cancelled_order() {
		$this->order_generator->set_meta( '_reepay_order_cancelled', '1' );

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Order is already canceled');

		self::$gateway->refund_payment( $this->order_generator->order() );
	}

	public function test_refund_payment_with_impossible_to_cancel_order() {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage(esc_html('Payment can\'t be refunded.'));

		self::$gateway->refund_payment( $this->order_generator->order() );
	}

	/**
	 * @param $amount
	 *
	 * @testWith
	 * [ -100.1, true ]
	 * [ -100.0, true ]
	 * [ -100, true ]
	 * [ -0, true ]
	 * [ 0, true ]
	 * [ 100, false ]
	 * [ 100.0, false ]
	 * [ 100.1, false ]
	 * [ null, false ]
	 */
	public function test_refund_payment_with_different_amounts( $amount, bool $expect_error ) {
		$this->api_mock->method( 'can_refund' )->willReturn( true );

		if ( $expect_error ) {
			$this->expectException(Exception::class);
			$this->expectExceptionMessage('Refund amount must be greater than 0.');
		} else {
			$this->expectNotToPerformAssertions();
		}

		self::$gateway->refund_payment( $this->order_generator->order(), $amount );
	}

	public function test_refund_payment_with_api_error() {
		$error_message = 'refund api error';

		$this->api_mock->method( 'can_refund' )->willReturn( true );
		$this->api_mock->method( 'refund' )->willReturn( new WP_Error( 10, $error_message ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $error_message );

		self::$gateway->refund_payment( $this->order_generator->order() );
	}

	/**
	 * @testWith
	 * [true]
	 * [false]
	 */
	public function test_can_refund( bool $result ) {
		$this->api_mock->method( 'can_refund' )->willReturn( $result );

		$this->assertSame(
			$result,
			self::$gateway->can_refund( $this->order_generator->order() )
		);
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
	public function test_get_order_items_line_items_simple( bool $include_tax, bool $only_not_settled ) {
		$order_items_generator = new OrderItemsGenerator(
			$this->order_generator,
			array(
				'include_tax' => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item();
		$order_items_generator->generate_line_item( array(
			'order_item_meta' => array(
				'settled' => true
			)
		) );

		$this->assertSame(
			$order_items_generator->get_order_items(),
			self::$gateway->get_order_items( $this->order_generator->order(), $only_not_settled )
		);
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
	public function test_get_order_items_line_items_reepay_subscription( bool $include_tax, bool $only_not_settled ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order_items_generator = new OrderItemsGenerator(
			$this->order_generator,
			array(
				'include_tax' => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(array(
			'type' => 'rp_sub'
		));
		$order_items_generator->generate_line_item( array(
			'type' => 'rp_sub',
			'order_item_meta' => array(
				'settled' => true
			)
		) );
		$order_items_generator->generate_line_item(array(
			'type' => 'rp_sub',
			'product_meta' => array(
				'_reepay_subscription_fee' => array(
					'enabled' => 'yes',
					'text' => 'test'
				),
				'_line_discount' => 10
			)
		));
		$order_items_generator->generate_line_item( array(
			'type' => 'rp_sub',
			'product_meta' => array(
				'name' => 'Product #-1',
				'_reepay_subscription_fee' => array(
					'enabled' => 'yes',
					'text' => 'test'
				),
				'_line_discount' => 10
			),
			'order_item_meta' => array(
				'settled' => true
			)
		) );
		$order_items_generator->generate_fee_item(array(
			'name' => 'Product #-1 - test'
		));

		$this->assertSame(
			array(),
			self::$gateway->get_order_items( $this->order_generator->order(), $only_not_settled )
		);
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
	public function test_get_order_items_shipping_items( bool $include_tax, bool $only_not_settled ) {
		$order_items_generator = new OrderItemsGenerator(
			$this->order_generator,
			array(
				'include_tax' => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item([
			'price' => 200,
			'quantity' => 3,
			'tax' => 5
		]);

		$order_items_generator->generate_shipping_item([
			'price' => 300
		]);

		$order_items_generator->generate_shipping_item( array(
			'price' => 300,
			'meta' => array(
				'settled' => true
			)
		) );

		$this->assertSame(
			$order_items_generator->get_order_items(),
			self::$gateway->get_order_items( $this->order_generator->order(), $only_not_settled )
		);
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
	public function test_get_order_items_fee_items( bool $include_tax, bool $only_not_settled ) {
		$order_items_generator = new OrderItemsGenerator(
			$this->order_generator,
			array(
				'include_tax' => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_fee_item();
		$order_items_generator->generate_fee_item( array(
			'meta' => array(
				'settled' => true
			)
		) );

		$this->assertSame(
			$order_items_generator->get_order_items(),
			self::$gateway->get_order_items( $this->order_generator->order(), $only_not_settled )
		);
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
	public function test_get_order_reepay_subscription_with_fee( bool $include_tax, bool $only_not_settled ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order_items_generator = new OrderItemsGenerator(
			$this->order_generator,
			array(
				'include_tax' => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(array(
			'type' => 'rp_sub'
		));
		$order_items_generator->generate_fee_item();
		$order_items_generator->generate_fee_item( array(
			'meta' => array(
				'settled' => true
			)
		) );

		$this->assertSame(
			$order_items_generator->get_order_items(),
			self::$gateway->get_order_items( $this->order_generator->order(), $only_not_settled )
		);
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
	public function test_get_order_items_total_discount( bool $include_tax, bool $only_not_settled ) {
		$order_items_generator = new OrderItemsGenerator(
			$this->order_generator,
			array(
				'include_tax' => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(array(
			'price' => 99
		));

		$order_items_generator->add_total_discount( array(
			'amount' => 15,
			'tax' => 7
		) );

		$this->assertSame(
			$order_items_generator->get_order_items(),
			self::$gateway->get_order_items( $this->order_generator->order(), $only_not_settled )
		);
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
	public function test_get_order_items_pw_gift_card( bool $include_tax, bool $only_not_settled ) {
		$this->markTestIncomplete();
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
	public function test_get_order_items_giftup( bool $include_tax, bool $only_not_settled ) {
		$this->markTestIncomplete();
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
	 * ["china_union_pay", "cup"]
	 * ["paypal", "paypal"]
	 * ["applepay", "applepay"]
	 * ["googlepay", "googlepay"]
	 * ["vipps", "vipps"]
	 */
	public function test_logo( $card_type, $result ) {
        $logo_svg_path = reepay()->get_setting( 'images_path' ) . 'svg/' . $result . '.logo.svg';
		$this->assertSame(
            file_exists($logo_svg_path) ?
                reepay()->get_setting( 'images_url' ) . 'svg/' . $result . '.logo.svg' :
                reepay()->get_setting( 'images_url' ) . $result . '.png',
			self::$gateway->get_logo( $card_type )
		);
	}

	public function test_logo_default() {
		$card_type = 'custom';

		$this->assertSame(
			reepay()->get_setting( 'images_url' ) . 'svg/card.logo.svg',
			self::$gateway->get_logo( $card_type )
		);
	}

	// -----------------------------------------------------------------------
	// is_webhook_configured()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayGateway::is_webhook_configured returns false when API returns WP_Error.
	 *
	 * @group gateways_gateway
	 */
	public function test_is_webhook_configured_api_error_returns_false() {
		$this->api_mock->method( 'request' )->willReturn(
			new WP_Error( '401', 'Unauthorized' )
		);

		$this->assertFalse( self::$gateway->is_webhook_configured() );
	}

	/**
	 * Test @see ReepayGateway::is_webhook_configured returns true when webhook URL is
	 * already registered and no waste URLs exist.
	 *
	 * @group gateways_gateway
	 */
	public function test_is_webhook_configured_already_registered_returns_true() {
		$webhook_url = ReepayGateway::get_webhook_url();

		$this->api_mock->method( 'request' )->willReturn(
			array(
				'urls'         => array( $webhook_url ),
				'alert_emails' => array(),
				'disabled'     => false,
				'secret'       => 'secret_key',
			)
		);

		$this->assertTrue( self::$gateway->is_webhook_configured() );
	}

	/**
	 * Test @see ReepayGateway::is_webhook_configured registers and returns true when URL is missing.
	 *
	 * @group gateways_gateway
	 */
	public function test_is_webhook_configured_registers_missing_url() {
		$webhook_url = ReepayGateway::get_webhook_url();

		// First call: GET — URL not yet present.
		// Second call: PUT — successful registration.
		$this->api_mock->method( 'request' )->willReturnOnConsecutiveCalls(
			array(
				'urls'         => array(),
				'alert_emails' => array(),
				'disabled'     => false,
				'secret'       => 'secret_key',
			),
			array(
				'urls'         => array( $webhook_url ),
				'alert_emails' => array(),
				'disabled'     => false,
			)
		);

		$this->assertTrue( self::$gateway->is_webhook_configured() );
	}

	// -----------------------------------------------------------------------
	// exclude_payment_gateway_based_on_currency()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayGateway::exclude_payment_gateway_based_on_currency
	 * keeps supported gateways.
	 *
	 * @group gateways_gateway
	 */
	public function test_exclude_payment_gateway_based_on_currency_keeps_all_when_no_restriction() {
		$gateways = array(
			'reepay_checkout' => self::$gateway,
		);

		$filtered = self::$gateway->exclude_payment_gateway_based_on_currency( $gateways );

		$this->assertArrayHasKey( 'reepay_checkout', $filtered );
	}

	// -----------------------------------------------------------------------
	// get_skip_order_lines_amount()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayGateway::get_skip_order_lines_amount returns rp_prepare_amount result.
	 *
	 * @group gateways_gateway
	 */
	public function test_get_skip_order_lines_amount_returns_prepared_amount() {
		$price = 25.00;
		$this->order_generator->add_product(
			'simple',
			array( 'regular_price' => $price )
		);
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$amount = self::$gateway->get_skip_order_lines_amount( $this->order_generator->order() );

		$this->assertIsNumeric( $amount );
		$this->assertGreaterThan( 0, $amount );
	}

	/**
	 * Test @see ReepayGateway::get_skip_order_lines_amount with skip_fn_rp_amount=true
	 * returns raw float.
	 *
	 * @group gateways_gateway
	 */
	public function test_get_skip_order_lines_amount_without_rp_prepare() {
		$this->order_generator->add_product(
			'simple',
			array( 'regular_price' => 50.00 )
		);
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$amount_with    = self::$gateway->get_skip_order_lines_amount( $this->order_generator->order(), false );
		$amount_without = self::$gateway->get_skip_order_lines_amount( $this->order_generator->order(), true );

		// With rp_prepare_amount the value is multiplied by 100 for non-ISK currencies;
		// the raw amount equals the WC order total.
		$this->assertIsNumeric( $amount_with );
		$this->assertIsNumeric( $amount_without );
	}

	// -----------------------------------------------------------------------
	// process_payment() — basic guards via gateway
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayGateway::process_payment returns false for a non-existent order.
	 *
	 * @group gateways_gateway
	 */
	public function test_process_payment_returns_false_for_missing_order() {
		$result = self::$gateway->process_payment( 999888777 );

		$this->assertFalse( $result );
	}

	/**
	 * Test @see ReepayGateway::process_payment uses session charge and returns success
	 * array when API succeeds.
	 *
	 * @group gateways_gateway
	 */
	public function test_process_payment_session_charge_returns_success() {
		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );

		self::$gateway->id = 'reepay_checkout';
		$this->order_generator->set_prop( 'payment_method', self::$gateway->id );
		$this->order_generator->add_product( 'simple' );
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();

		$this->api_mock->method( 'request' )->willReturn(
			array(
				'id'  => 'session_xyz',
				'url' => 'https://checkout.reepay.com/pay/session_xyz',
			)
		);
		$this->api_mock->method( 'get_customer_handle_by_order' )->willReturn( 'customer-' . $user_id );

		$result = self::$gateway->process_payment( $order_id );

		$this->assertIsArray( $result );
		$this->assertSame( 'success', $result['result'] );

		wp_set_current_user( 0 );
	}
}
