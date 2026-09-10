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

	/**
	 * Test @see ThankyouPage::thankyou_scripts localizes skip_status_check=true for
	 * Bank Transfer orders, so the client skips the payment-status polling loop that
	 * would otherwise wait forever for an invoice_authorized webhook that never
	 * arrives quickly for a real bank transfer.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_thankyou_scripts_skips_status_check_for_bank_transfer() {
		add_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );

		$this->order_generator->set_prop( 'payment_method', 'reepay_offline_bank_transfer' );

		$_GET['key'] = $this->order_generator->order()->get_order_key();
		set_query_var( 'order-received', $this->order_generator->order()->get_id() );

		new \Reepay\Checkout\OrderFlow\ThankyouPage();
		do_action( 'wp_enqueue_scripts' );

		$localized = wp_scripts()->get_data( 'wc-gateway-reepay-thankyou', 'data' );

		// wp_localize_script() serializes PHP booleans as WP-style "1"/"" strings,
		// not JSON true/false — the JS truthy check still works correctly either way.
		$this->assertStringContainsString( '"skip_status_check":"1"', $localized );

		remove_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );
	}

	/**
	 * Test @see ThankyouPage::thankyou_scripts localizes skip_status_check=false for
	 * regular (non-Bank-Transfer) Reepay orders, preserving the existing polling flow.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_thankyou_scripts_does_not_skip_status_check_for_other_gateways() {
		add_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );

		$_GET['key'] = $this->order_generator->order()->get_order_key();
		set_query_var( 'order-received', $this->order_generator->order()->get_id() );

		new \Reepay\Checkout\OrderFlow\ThankyouPage();
		do_action( 'wp_enqueue_scripts' );

		$localized = wp_scripts()->get_data( 'wc-gateway-reepay-thankyou', 'data' );

		// wp_localize_script() serializes PHP false as an empty string, not JSON false.
		$this->assertStringContainsString( '"skip_status_check":""', $localized );

		remove_filter( 'woocommerce_is_order_received_page', '__return_true', 1000 );
	}

	// -----------------------------------------------------------------------
	// order_has_prorated_subscription()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ThankyouPage::order_has_prorated_subscription returns false for a plain
	 * order with no subscription products, regardless of whether the separate
	 * reepay-woocommerce-subscriptions plugin (which provides
	 * WC_Reepay_Subscription_Plan_Simple) is active. Detection is driven entirely by
	 * product meta, so a merchant without that companion plugin must not have every
	 * thank-you page stuck polling for a subscription split that will never happen.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_order_has_prorated_subscription_false_when_no_class() {
		if ( class_exists( 'WC_Reepay_Subscription_Plan_Simple' ) ) {
			$this->markTestSkipped( 'WC_Reepay_Subscription_Plan_Simple is loaded — this scenario is covered by test_order_has_prorated_subscription_false_for_simple_product.' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple' );
		$this->order_generator->order()->save();

		$result = \Reepay\Checkout\OrderFlow\ThankyouPage::order_has_prorated_subscription(
			$this->order_generator->order()
		);

		$this->assertFalse( $result, 'Plain order with no subscription products must not be treated as prorated just because the class is absent' );
	}

	/**
	 * Test @see ThankyouPage::order_has_prorated_subscription returns false for a simple
	 * product with no Reepay subscription schedule meta.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_order_has_prorated_subscription_false_for_simple_product() {
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
	 * has bill_prorated schedule meta, even if WC_Reepay_Subscription_Plan_Simple isn't
	 * loaded — detection reads product meta directly and never calls that class.
	 *
	 * @group orderflow_thankyou
	 */
	public function test_order_has_prorated_subscription_true_with_prorated_meta() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product(
			'simple',
			array(
				'_reepay_subscription_schedule_type' => 'interval',
				'_reepay_subscription_interval'      => array( 'period' => 'bill_prorated' ),
			)
		);
		$this->order_generator->order()->save();

		$result = \Reepay\Checkout\OrderFlow\ThankyouPage::order_has_prorated_subscription(
			$this->order_generator->order()
		);

		$this->assertTrue( $result );
	}
}
