<?php
/**
 * Class ThankyouPage
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;


/**
 * ThankyouPage.
 *
 * @covers \Reepay\Checkout\OrderFlow\ThankyouPage
 */
class ThankyouPage extends Reepay_UnitTestCase {
	public function test_override_template() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$args = array(
			'checkout/thankyou.php',
			'',
			array(
				'order' => $this->order_generator->order()->get_id()
			),
			'',
			''
		);

		$path = ( new \Reepay\Checkout\OrderFlow\ThankyouPage() )->override_template( ...$args );

		$this->assertSame(
			reepay()->get_setting( 'templates_path' ) . 'checkout/thankyou.php',
			$path
		);

		$this->assertSame(
			reepay()->get_setting( 'templates_path' ) . 'checkout/thankyou.php',
			apply_filters( 'wc_get_template', ...$args )
		);
	}

	public function test_thankyou_scripts() {
		add_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$_GET['key'] = $this->order_generator->order()->get_order_key();
		set_query_var( 'order-received', $this->order_generator->order()->get_id() );

		new \Reepay\Checkout\OrderFlow\ThankyouPage();
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_script_is( 'wc-gateway-reepay-thankyou' ) );
	}

	// -----------------------------------------------------------------------
	// order_has_prorated_subscription()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ThankyouPage::order_has_prorated_subscription returns true when
	 * WC_Reepay_Subscription_Plan_Simple is absent (fallback path).
	 *
	 * @group orderflow_thankyou
	 */
	public function test_order_has_prorated_subscription_fallback_when_no_class() {
		if ( class_exists( 'WC_Reepay_Subscription_Plan_Simple' ) ) {
			$this->markTestSkipped( 'WC_Reepay_Subscription_Plan_Simple is loaded — fallback path not active.' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$result = \Reepay\Checkout\OrderFlow\ThankyouPage::order_has_prorated_subscription(
			$this->order_generator->order()
		);

		$this->assertTrue( $result, 'Should return true when WC_Reepay_Subscription_Plan_Simple is absent' );
	}

	/**
	 * Test @see ThankyouPage::order_has_prorated_subscription returns false for a simple
	 * product with no Reepay subscription schedule meta.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_order_has_prorated_subscription_false_for_simple_product() {
		if ( ! class_exists( 'WC_Reepay_Subscription_Plan_Simple' ) ) {
			$this->markTestSkipped( 'WC_Reepay_Subscription_Plan_Simple not loaded — cannot reach false path.' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple' );
		$this->order_generator->order()->save();

		// Simple product has no _reepay_subscription_schedule_type meta → returns false.
		$result = \Reepay\Checkout\OrderFlow\ThankyouPage::order_has_prorated_subscription(
			$this->order_generator->order()
		);

		$this->assertFalse( $result, 'Simple product with no Reepay subscription meta should return false' );
	}

	/**
	 * Test @see ThankyouPage::order_has_prorated_subscription returns true when product
	 * has bill_prorated schedule meta.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_order_has_prorated_subscription_true_with_prorated_meta() {
		if ( ! class_exists( 'WC_Reepay_Subscription_Plan_Simple' ) ) {
			$this->markTestSkipped( 'WC_Reepay_Subscription_Plan_Simple not loaded — cannot test meta path.' );
		}

		// Create a simple product and add subscription schedule meta directly.
		$product_id = self::$product_generator->create( 'simple' )->get_id();
		update_post_meta( $product_id, '_reepay_subscription_schedule_type', 'interval' );
		update_post_meta( $product_id, '_reepay_subscription_interval', array( 'period' => 'bill_prorated' ) );

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array(), $product_id );
		$this->order_generator->order()->save();

		$result = \Reepay\Checkout\OrderFlow\ThankyouPage::order_has_prorated_subscription(
			$this->order_generator->order()
		);

		$this->assertTrue( $result );
	}
}
