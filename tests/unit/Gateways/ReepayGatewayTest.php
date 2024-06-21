<?php
/**
 * Test
 *
 * @package Reepay\Checkout\Tests\Unit\Gateways
 */

namespace Reepay\Checkout\Tests\Unit\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;
use Billwerk\Sdk\Exception\BillwerkApiException;
use Billwerk\Sdk\Model\Account\AccountModel;
use Billwerk\Sdk\Model\Account\WebhookSettingsModel;
use Billwerk\Sdk\Model\Agreement\AgreementModel;
use Exception;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\Tests\Helpers\GatewayForTesting;
use Reepay\Checkout\Tests\Helpers\OrderItemsGenerator;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use WP_Error;

/**
 * Test class
 *
 * @package Reepay\Checkout\Tests\Unit\Gateways
 */
class ReepayGatewayTest extends Reepay_UnitTestCase {
	/**
	 * Gateway for testing
	 *
	 * @var GatewayForTesting $gateway
	 */
	public static GatewayForTesting $gateway;

	/**
	 * Set up before class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$gateway = new GatewayForTesting();
	}

	/**
	 * Tear down after class
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		update_option( 'woocommerce_calc_taxes', 'no' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
	}

	/**
	 * Test function is_webhook_configured
	 *
	 * @see ReepayGateway::is_webhook_configured()
	 * @return void
	 */
	public function test_is_webhook_configured() {
		$webhook_url = self::$gateway::get_webhook_url();
		$settings    = ( new WebhookSettingsModel() )
			->setUrls( array( $webhook_url ) );

		$this->account_service_mock
			->method( 'getWebHookSettings' )
			->willReturnOnConsecutiveCalls( $settings, $this->throwException( new BillwerkApiException() ) );

		$this::assertTrue( self::$gateway->is_webhook_configured() );
		$this::assertFalse( self::$gateway->is_webhook_configured() );
	}

	/**
	 * Test function check_is_active
	 *
	 * @param string $gateway gateway id.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::agreement_types()
	 * @throws Exception Exception.
	 * @see ReepayGateway::check_is_active()
	 */
	public function test_check_is_active( string $gateway ) {
		self::$gateway->id = 'reepay_' . $gateway;
		$this->agreement_service_mock
			->method( 'all' )
			->willReturn( array( ( new AgreementModel() )->setType( $gateway ) ) );

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
	 * ["resurs"]
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
	 * Test get_account_info
	 *
	 * @param bool $is_test use test or live billwerk api keys.
	 *
	 * @testWith
	 * [true]
	 * [false]
	 * @throws BillwerkApiException Api Error.
	 */
	public function test_get_account_info( bool $is_test ) {
		$_GET['tab']       = 'checkout';
		$_GET['section']   = 'checkout';
		self::$gateway->id = 'checkout';

		$account = ( new AccountModel() )
			->setHandle( 'test_1234' );

		$this->account_service_mock
			->method( 'get' )
			->willReturnOnConsecutiveCalls( $account, $account );

		self::assertSame(
			$account,
			self::$gateway->get_account_info( $is_test )
		);

		self::assertEquals(
			$account,
			self::$gateway->get_account_info( $is_test ),
			'transient cache error'
		);
	}

	/**
	 * Test get_account_info not on settings page
	 *
	 * @param bool $is_test use test or live reepay api keys.
	 *
	 * @testWith
	 * [true]
	 * [false]
	 * @throws BillwerkApiException Api Error.
	 */
	public function test_get_account_info_not_on_settings_page( bool $is_test ) {
		$this->assertFalse( self::$gateway->is_gateway_settings_page() );
		$this->assertNull( self::$gateway->get_account_info( $is_test ) );
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

		$this->expectException( Exception::class );

		self::$gateway->capture_payment( $this->order_generator->order() );
	}

	public function test_capture_payment_with_api_error() {
		$this->api_mock->method( 'capture_payment' )->willReturn( new WP_Error() );

		$this->expectException( Exception::class );

		self::$gateway->capture_payment( $this->order_generator->order() );
	}

	public function test_cancel_payment_with_cancelled_order() {
		$this->order_generator->set_meta( '_reepay_order_cancelled', '1' );

		$this->expectException( Exception::class );

		self::$gateway->cancel_payment( $this->order_generator->order() );
	}

	public function test_cancel_payment_with_api_error() {
		$this->api_mock->method( 'cancel_payment' )->willReturn( new WP_Error() );

		$this->expectException( Exception::class );

		self::$gateway->cancel_payment( $this->order_generator->order() );
	}

	public function test_refund_payment_with_cancelled_order() {
		$this->order_generator->set_meta( '_reepay_order_cancelled', '1' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Order is already canceled' );

		self::$gateway->refund_payment( $this->order_generator->order() );
	}

	public function test_refund_payment_with_impossible_to_cancel_order() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment can\'t be refunded.' );

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
			$this->expectException( Exception::class );
			$this->expectExceptionMessage( 'Refund amount must be greater than 0.' );
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
				'include_tax'      => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item();
		$order_items_generator->generate_line_item(
			array(
				'order_item_meta' => array(
					'settled' => true,
				),
			)
		);

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
				'include_tax'      => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(
			array(
				'type' => 'rp_sub',
			)
		);
		$order_items_generator->generate_line_item(
			array(
				'type'            => 'rp_sub',
				'order_item_meta' => array(
					'settled' => true,
				),
			)
		);
		$order_items_generator->generate_line_item(
			array(
				'type'         => 'rp_sub',
				'product_meta' => array(
					'_reepay_subscription_fee' => array(
						'enabled' => 'yes',
						'text'    => 'test',
					),
					'_line_discount'           => 10,
				),
			)
		);
		$order_items_generator->generate_line_item(
			array(
				'type'            => 'rp_sub',
				'product_meta'    => array(
					'name'                     => 'Product #-1',
					'_reepay_subscription_fee' => array(
						'enabled' => 'yes',
						'text'    => 'test',
					),
					'_line_discount'           => 10,
				),
				'order_item_meta' => array(
					'settled' => true,
				),
			)
		);
		$order_items_generator->generate_fee_item(
			array(
				'name' => 'Product #-1 - test',
			)
		);

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
				'include_tax'      => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(
			array(
				'price'    => 200,
				'quantity' => 3,
				'tax'      => 5,
			)
		);

		$order_items_generator->generate_shipping_item(
			array(
				'price' => 300,
			)
		);

		$order_items_generator->generate_shipping_item(
			array(
				'price' => 300,
				'meta'  => array(
					'settled' => true,
				),
			)
		);

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
				'include_tax'      => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_fee_item();
		$order_items_generator->generate_fee_item(
			array(
				'meta' => array(
					'settled' => true,
				),
			)
		);

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
				'include_tax'      => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(
			array(
				'type' => 'rp_sub',
			)
		);
		$order_items_generator->generate_fee_item();
		$order_items_generator->generate_fee_item(
			array(
				'meta' => array(
					'settled' => true,
				),
			)
		);

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
				'include_tax'      => $include_tax,
				'only_not_settled' => $only_not_settled,
			)
		);

		$order_items_generator->generate_line_item(
			array(
				'price' => 99,
			)
		);

		$order_items_generator->add_total_discount(
			array(
				'amount' => 15,
				'tax'    => 7,
			)
		);

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
	 * ["resurs", "resurs"]
	 * ["china_union_pay", "cup"]
	 * ["paypal", "paypal"]
	 * ["applepay", "applepay"]
	 * ["googlepay", "googlepay"]
	 * ["vipps", "vipps"]
	 */
	public function test_logo( $card_type, $result ) {
		$logo_svg_path = reepay()->get_setting( 'images_path' ) . 'svg/' . $result . '.logo.svg';
		$this->assertSame(
			file_exists( $logo_svg_path ) ?
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
}
