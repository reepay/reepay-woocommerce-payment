<?php
/**
 * Class ThankyouPage
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\OrderGenerator;
use Reepay\Checkout\Tests\Helpers\Reepay_Ajax_UnitTestCase;


/**
 * ThankyouPage.
 *
 * @covers \Reepay\Checkout\OrderFlow\ThankyouPage
 */
class ThankyouPage_AJAX extends Reepay_Ajax_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		new \Reepay\Checkout\OrderFlow\ThankyouPage();
	}

	/**
	 * @param $api_status
	 * @param $expected_status
	 *
	 * @testWith
	 * [ "pending",    "pending" ]
	 * [ "authorized", "paid"    ]
	 * [ "settled",    "paid"    ]
	 * [ "cancelled",  "failed"  ]
	 * [ "failed", 	   "failed"  ]
	 */
	public function test_ajax_check_payment( $api_status, $expected_status ) {
		$_POST['nonce']     = wp_create_nonce( 'reepay' );
		$_POST['order_id']  = $this->order_generator->order()->get_id();
		$_POST['order_key'] = $this->order_generator->order()->get_order_key();

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'state' => $api_status,
				'transactions' => array()
			)
		);

		try {
			$this->_handleAjax( 'reepay_check_payment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$result = json_decode( $this->_last_response, true );

		$this->assertSame( true, $result['success'] );

		$this->assertSame( $expected_status, $result['data']['state'] );
	}

	/**
	 * The linked subscription/renewal order is created asynchronously by the
	 * reepay-woocommerce-subscriptions plugin (on the invoice_authorized/settled
	 * webhook), which can take well over a minute. While a prorated subscription
	 * is expected but its sibling order hasn't been linked yet via
	 * `_reepay_another_orders`, the endpoint must tell the client to keep polling
	 * instead of rendering an incomplete page.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_ajax_order_descriptions_waits_when_prorated_subscription_not_yet_split() {
		if ( ! class_exists( 'WC_Reepay_Subscription_Plan_Simple' ) ) {
			$this->markTestSkipped( 'WC_Reepay_Subscription_Plan_Simple not loaded — cannot test prorated path.' );
		}

		$this->order_generator->add_product(
			'simple',
			array(
				'_reepay_subscription_schedule_type' => 'interval',
				'_reepay_subscription_interval'      => array( 'period' => 'bill_prorated' ),
			)
		);

		$_POST['nonce']     = wp_create_nonce( 'reepay' );
		$_POST['order_id']  = $this->order_generator->order()->get_id();
		$_POST['order_key'] = $this->order_generator->order()->get_order_key();

		try {
			$this->_handleAjax( 'reepay_order_descriptions' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$result = json_decode( $this->_last_response, true );

		$this->assertSame( false, $result['success'] );
		$this->assertSame( 'prorated_split_pending', $result['data']['reason'] );
	}

	/**
	 * Once the sibling order is linked via `_reepay_another_orders`, the endpoint
	 * must render successfully even though the order has a prorated subscription.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_ajax_order_descriptions_renders_once_sibling_order_is_linked() {
		if ( ! class_exists( 'WC_Reepay_Subscription_Plan_Simple' ) ) {
			$this->markTestSkipped( 'WC_Reepay_Subscription_Plan_Simple not loaded — cannot test prorated path.' );
		}

		$this->order_generator->add_product(
			'simple',
			array(
				'_reepay_subscription_schedule_type' => 'interval',
				'_reepay_subscription_interval'      => array( 'period' => 'bill_prorated' ),
			)
		);

		$sibling_order = ( new OrderGenerator() )->order();
		$this->order_generator->set_meta( '_reepay_another_orders', array( $sibling_order->get_id() ) );

		$_POST['nonce']     = wp_create_nonce( 'reepay' );
		$_POST['order_id']  = $this->order_generator->order()->get_id();
		$_POST['order_key'] = $this->order_generator->order()->get_order_key();

		try {
			$this->_handleAjax( 'reepay_order_descriptions' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$result = json_decode( $this->_last_response, true );

		$this->assertSame( true, $result['success'] );
	}

	/**
	 * Orders without a prorated subscription must render immediately on the
	 * first poll — no reason to wait on `_reepay_another_orders`.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_ajax_order_descriptions_renders_immediately_when_no_prorated_subscription() {
		$this->order_generator->add_product( 'simple' );

		$_POST['nonce']     = wp_create_nonce( 'reepay' );
		$_POST['order_id']  = $this->order_generator->order()->get_id();
		$_POST['order_key'] = $this->order_generator->order()->get_order_key();

		try {
			$this->_handleAjax( 'reepay_order_descriptions' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$result = json_decode( $this->_last_response, true );

		$this->assertSame( true, $result['success'] );
	}
}
