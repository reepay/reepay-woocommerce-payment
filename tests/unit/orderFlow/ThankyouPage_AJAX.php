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
	 * `_reepay_another_orders` is populated synchronously at checkout by
	 * reepay-woocommerce-subscriptions only when a cart splits across multiple orders —
	 * it stays permanently empty for a single-product pro-rated subscription order (no
	 * webhook ever fills it in). Readiness must therefore be decided purely by whether
	 * pro-rated pricing data is available (get_pro_rated_reepay_subscription()), even
	 * when a sibling order is already linked and pricing data genuinely isn't ready yet.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_ajax_order_descriptions_waits_for_pricing_data_even_with_sibling_linked() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product(
			'simple',
			array(
				'_reepay_subscription_schedule_type' => 'interval',
				'_reepay_subscription_interval'      => array( 'period' => 'bill_prorated' ),
			)
		);

		$sibling_order = ( new OrderGenerator() )->order();
		$this->order_generator->set_meta( '_reepay_another_orders', array( $sibling_order->get_id() ) );
		// No `_reepay_order` invoice handle set yet, so pricing data isn't ready.

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
	 * Regression test for the case reported on order #31181 (BWPM-277 follow-up): a
	 * single-product pro-rated subscription order must not be blocked forever just
	 * because `_reepay_another_orders` is empty — that meta was never going to be
	 * populated for this order in the first place (no cart split was ever needed), so
	 * gating on it here previously left the thank-you page waiting indefinitely and
	 * rendering without its pro-rated breakdown, no matter how long the customer waited.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_ajax_order_descriptions_renders_even_when_another_orders_stays_permanently_empty() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product(
			'simple',
			array(
				'_reepay_subscription_schedule_type' => 'interval',
				'_reepay_subscription_interval'      => array( 'period' => 'bill_prorated' ),
			)
		);
		// No sibling order, and never will be one — must not wait on that basis alone.

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
