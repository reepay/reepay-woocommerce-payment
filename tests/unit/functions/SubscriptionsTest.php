<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\CartGenerator;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * CurrencyTest.
 */
class SubscriptionsTest extends Reepay_UnitTestCase {
	/**
	 * Test @see order_contains_subscription with simple product
	 */
	public function test_order_contains_subscription_simple_product() {
		$this->order_generator->add_product( 'simple' );

		$this->assertFalse( order_contains_subscription( $this->order_generator->order() ) );
	}

	/**
	 * Test @see order_contains_subscription with woo subscription
	 */
	public function test_order_contains_subscription_woo_subscription() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->add_product( 'woo_sub' );

		$this->assertTrue( order_contains_subscription( $this->order_generator->order() ) );
	}

	/**
	 * Test @see order_contains_subscription with reepay subscription
	 */
	public function test_order_contains_subscription_reepay_subscription() {
		$this->order_generator->add_product( 'rp_sub' );

		$this->assertFalse( order_contains_subscription( $this->order_generator->order() ) );
	}

	/**
	 * Test @see wcs_is_subscription_product
	 */
	public function test_wcs_is_subscription_product() {
		$this->assertFalse( wcs_is_subscription_product( self::$product_generator->generate( 'simple' ) ), 'simple' );

		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->assertTrue( wcs_is_subscription_product( self::$product_generator->generate( 'woo_sub' ) ), 'woo_sub' );
		}

		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->assertFalse( wcs_is_subscription_product( self::$product_generator->generate( 'rp_sub' ) ), 'rp_sub' );
		}
	}

	/**
	 * Test @see wcr_is_subscription_product
	 */
	public function test_wcr_is_subscription_product() {
		$this->assertFalse( wcr_is_subscription_product( self::$product_generator->generate( 'simple' ) ), 'simple' );

		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->assertTrue( wcs_is_subscription_product( self::$product_generator->generate( 'woo_sub' ) ), 'woo_sub' );
		}

		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->assertFalse( wcs_is_subscription_product( self::$product_generator->generate( 'rp_sub' ) ), 'rp_sub' );
		}
	}

	/**
	 * Test @see wcs_is_payment_change
	 *
	 * @param bool $test_val test value.
	 * @param bool $result expected result.
	 *
	 * @testWith
	 * [true, true]
	 * [false, false]
	 */
	public function test_wcs_is_payment_change( bool $test_val, bool $result ) {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = $test_val;
		$this->assertSame( wcs_is_payment_change(), $result );
	}

	/**
	 * Test @see wcs_cart_have_subscription
	 */
	public function test_wcs_cart_have_subscription() {
		$this->cart_generator->new_cart( 'simple' );
		$this->assertFalse( wcs_cart_have_subscription(), 'simple' );

		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->cart_generator->new_cart( 'woo_sub' );
			$this->assertTrue( wcs_cart_have_subscription(), 'woo_sub' );
		}

		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->cart_generator->new_cart( 'rp_sub' );
			$this->assertTrue( wcs_cart_have_subscription(), 'rp_sub' );
		}
	}

	/**
	 * Test @see wcs_cart_only_subscriptions
	 */
	public function test_wcs_cart_only_subscriptions() {
		$this->cart_generator->new_cart( 'simple' );
		$this->assertFalse( wcs_cart_only_subscriptions(), 'simple' );

		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->cart_generator->new_cart( array( 'simple', 'woo_sub' ) );
			$this->assertFalse( wcs_cart_only_subscriptions(), 'simple, woo_sub' );

			$this->cart_generator->new_cart( array( 'woo_sub', 'woo_sub' ) );
			$this->assertTrue( wcs_cart_only_subscriptions(), 'woo_sub, woo_sub' );
		}

		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->cart_generator->new_cart( array( 'simple', 'rp_sub' ) );
			$this->assertFalse( wcs_cart_only_subscriptions(), 'simple, rp_sub' );

			$this->cart_generator->new_cart( array( 'rp_sub', 'rp_sub' ) );
			$this->assertTrue( wcs_cart_only_subscriptions(), 'rp_sub, rp_sub' );
		}

		if ( PLUGINS_STATE::woo_subs_activated() && PLUGINS_STATE::rp_subs_activated() ) {
			$this->cart_generator->new_cart( array( 'simple', 'rp_sub', 'woo_sub' ) );
			$this->assertFalse( wcs_cart_only_subscriptions(), 'simple, rp_sub, rp_sub' );

			$this->cart_generator->new_cart( array( 'rp_sub', 'woo_sub' ) );
			$this->assertTrue( wcs_cart_only_subscriptions(), 'rp_sub, woo_sub' );
		}
	}

	/**
	 * Test @see wcr_cart_only_reepay_subscriptions
	 */
	public function test_wcr_cart_only_reepay_subscriptions() {
		$this->cart_generator->new_cart( 'simple' );
		$this->assertFalse( wcr_cart_only_reepay_subscriptions(), 'simple' );

		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->cart_generator->new_cart( array( 'simple', 'woo_sub' ) );
			$this->assertFalse( wcr_cart_only_reepay_subscriptions(), 'simple, woo_sub' );

			$this->cart_generator->new_cart( array( 'woo_sub', 'woo_sub' ) );
			$this->assertFalse( wcr_cart_only_reepay_subscriptions(), 'woo_sub, woo_sub' );
		}

		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->cart_generator->new_cart( array( 'simple', 'rp_sub' ) );
			$this->assertFalse( wcr_cart_only_reepay_subscriptions(), 'simple, rp_sub' );

			$this->cart_generator->new_cart( array( 'rp_sub', 'rp_sub' ) );
			$this->assertTrue( wcr_cart_only_reepay_subscriptions(), 'rp_sub, rp_sub' );
		}

		if ( PLUGINS_STATE::woo_subs_activated() && PLUGINS_STATE::rp_subs_activated() ) {
			$this->cart_generator->new_cart( array( 'simple', 'rp_sub', 'woo_sub' ) );
			$this->assertFalse( wcr_cart_only_reepay_subscriptions(), 'simple, rp_sub, rp_sub' );

			$this->cart_generator->new_cart( array( 'rp_sub', 'woo_sub' ) );
			$this->assertFalse( wcr_cart_only_reepay_subscriptions(), 'rp_sub, woo_sub' );
		}
	}
}
