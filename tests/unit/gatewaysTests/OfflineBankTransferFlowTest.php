<?php
/**
 * Class OfflineBankTransferFlowTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\ThankyouPage;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Integration coverage for the Frisbii Pay - Bank Transfer checkout-to-thankyou flow.
 *
 * @covers \Reepay\Checkout\Gateways\ReepayGateway
 * @covers \Reepay\Checkout\OrderFlow\InstantSettle
 * @covers \Reepay\Checkout\OrderFlow\ThankyouPage
 */
class OfflineBankTransferFlowTest extends Reepay_UnitTestCase {

	/**
	 * Full flow: checkout places the order on-hold immediately, a premature
	 * instant-settle attempt is skipped because the invoice isn't authorized yet,
	 * and the thank-you page is told to skip polling.
	 *
	 * @group gateways_gateway
	 * @group orderflow_instant_settle
	 * @group orderflow_thankyou
	 */
	public function test_bank_transfer_checkout_to_thankyou_flow() {
		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );

		$gateway = reepay()->gateways()->get_gateway( 'reepay_offline_bank_transfer' );
		$this->assertNotNull( $gateway, 'reepay_offline_bank_transfer gateway must be registered' );

		self::$options->set_option( 'settle', array( InstantSettle::SETTLE_PHYSICAL ) );

		$this->order_generator->set_prop( 'payment_method', $gateway->id );
		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => '20.00',
				'virtual'       => false,
				'downloadable'  => false,
			)
		);
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();

		$this->api_mock->method( 'request' )->willReturn(
			array(
				'id'  => 'session_xyz',
				'url' => 'https://checkout.reepay.com/pay/session_xyz',
			)
		);
		$this->api_mock->method( 'get_customer_handle_by_order' )->willReturn( 'customer-' . $user_id );
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 2000,
				'settled_amount'    => 0,
				'state'             => 'created',
			)
		);

		// 1. Checkout: order must go straight to on-hold.
		$result = $gateway->process_payment( $order_id );
		$this->assertSame( 'success', $result['result'] );

		$order = wc_get_order( $order_id );
		$this->assertSame( 'on-hold', $order->get_status() );

		// 2. A webhook-triggered instant-settle attempt on a still-"created" invoice
		//    must not settle anything.
		self::$instant_settle_instance->maybe_settle_instantly( $order );
		$this->assertFalse(
			WC_Order_Factory::get_order_item( $order_item_id )->meta_exists( 'settled' )
		);

		// 3. Thank-you page must tell the client to skip polling.
		add_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );
		$_GET['key'] = $order->get_order_key();
		set_query_var( 'order-received', $order_id );

		new ThankyouPage();
		do_action( 'wp_enqueue_scripts' );

		$localized = wp_scripts()->get_data( 'wc-gateway-reepay-thankyou', 'data' );
		// wp_localize_script() serializes PHP true as the string "1", not JSON true.
		$this->assertStringContainsString( '"skip_status_check":"1"', $localized );

		remove_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );
		wp_set_current_user( 0 );
	}
}
