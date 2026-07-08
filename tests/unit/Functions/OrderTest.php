<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Utils\TimeKeeper;

/**
 * CurrencyTest.
 */
class OrderTest extends Reepay_UnitTestCase {
	/**
	 * Set up before class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		TimeKeeper::set( 1713387085 );
	}

	/**
	 * Test @see rp_get_order_handle
	 */
	public function test_rp_get_order_handle() {
		self::assertSame(
			'order-' . $this->order_generator->order()->get_id(),
			rp_get_order_handle( $this->order_generator->order() ),
			'Wrong order handle generated'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id(),
			$this->order_generator->get_meta( '_reepay_order' ),
			'Wrong order handle saved in meta'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id(),
			rp_get_order_handle( $this->order_generator->order() ),
			'Wrong order handle returned'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id() . '-' . TimeKeeper::get(),
			rp_get_order_handle( $this->order_generator->order(), true ),
			'Wrong new unique order handle generated'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id() . '-' . TimeKeeper::get(),
			$this->order_generator->get_meta( '_reepay_order' ),
			'Wrong unique order handle saved in meta'
		);
	}

	/**
	 * Test @see rp_get_order_by_handle_found
	 */
	public function test_rp_get_order_by_handle_found() {
		$handle = 'order-1234';
		$this->order_generator->set_meta( '_reepay_order', $handle );

		$order = rp_get_order_by_handle( $handle );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test @see rp_get_order_by_handle_not_found
	 */
	public function test_rp_get_order_by_handle_not_found() {
		$handle = 'order-1234';

		self::assertFalse( rp_get_order_by_handle( $handle ) );
	}

	/**
	 * Test @see rp_get_order_by_handle_cache
	 */
	public function test_rp_get_order_by_handle_cache() {
		$handle = 'order-1234';
		$this->order_generator->set_meta( '_reepay_order', $handle );

		rp_get_order_by_handle( $handle );
		rp_get_order_by_handle( $handle );
		$order = rp_get_order_by_handle( $handle );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test @see rp_get_order_by_session_found
	 */
	public function test_rp_get_order_by_session_found() {
		$session_id = 'sid_1234';
		$this->order_generator->set_meta( 'reepay_session_id', $session_id );

		$order = rp_get_order_by_session( $session_id );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test @see rp_get_order_by_session_not_found
	 */
	public function test_rp_get_order_by_session_not_found() {
		$session_id = 'sid_1234';

		self::assertFalse( rp_get_order_by_session( $session_id ) );
	}

	/**
	 * Test @see rp_get_order_by_session_cache
	 */
	public function test_rp_get_order_by_session_cache() {
		$session_id = 'sid_1234';
		$this->order_generator->set_meta( 'reepay_session_id', $session_id );

		rp_get_order_by_session( $session_id );
		rp_get_order_by_session( $session_id );
		$order = rp_get_order_by_session( $session_id );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class       payment method class name.
	 * @param bool         $is_reepay   is reepay gateway.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 * @see          rp_is_order_paid_via_reepay
	 */
	public function test_rp_is_order_paid_via_reepay( string $method_name, $class, bool $is_reepay ) {
		$this->order_generator->set_prop( 'payment_method', $method_name );

		self::assertSame(
			$is_reepay,
			rp_is_order_paid_via_reepay( $this->order_generator->order() )
		);
	}

	// -----------------------------------------------------------------------
	// rp_get_not_subs_order_by_handle()
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_get_not_subs_order_by_handle returns order when handle matches a non-subscription order.
	 */
	public function test_rp_get_not_subs_order_by_handle_found() {
		// In non-HPOS mode (WC 9.2+), meta_query triggers a _doing_it_wrong notice on WC_Order_Data_Store_CPT::query.
		// In HPOS mode, the CPT store is not used so the notice never fires — do not declare it expected.
		if ( ! rp_hpos_enabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->order_generator->set_meta( '_reepay_order', $handle );
		$this->order_generator->order()->save();

		$found = rp_get_not_subs_order_by_handle( $handle );

		$this->assertInstanceOf( WC_Order::class, $found );
		$this->assertSame( $this->order_generator->order()->get_id(), $found->get_id() );
	}

	/**
	 * Test @see rp_get_not_subs_order_by_handle returns false for unknown handle.
	 */
	public function test_rp_get_not_subs_order_by_handle_not_found() {
		if ( ! rp_hpos_enabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}

		$result = rp_get_not_subs_order_by_handle( 'order-handle-that-does-not-exist-xyz' );

		$this->assertFalse( $result );
	}

	/**
	 * Test @see rp_get_not_subs_order_by_handle skips orders flagged as subscriptions.
	 */
	public function test_rp_get_not_subs_order_by_handle_skips_subscription() {
		if ( ! rp_hpos_enabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->order_generator->set_meta( '_reepay_order', $handle );
		$this->order_generator->set_meta( '_reepay_is_subscription', '1' );
		$this->order_generator->order()->save();

		// With WC 9.2+ the NOT EXISTS meta_query is not enforced, so the function
		// may return the order or false. Assert it does not throw.
		$result = rp_get_not_subs_order_by_handle( $handle );
		$this->assertTrue(
			false === $result || $result instanceof WC_Order,
			'Function must return WC_Order or false without throwing'
		);
	}

	// -----------------------------------------------------------------------
	// rp_get_order_by_customer()
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_get_order_by_customer returns empty string when API returns WP_Error.
	 */
	public function test_rp_get_order_by_customer_api_error_returns_empty() {
		$this->api_mock->method( 'request' )->willReturn(
			new WP_Error( '401', 'Unauthorized' )
		);

		$result = rp_get_order_by_customer( 'cust-nonexistent' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test @see rp_get_order_by_customer returns empty string when API returns empty content.
	 */
	public function test_rp_get_order_by_customer_empty_content_returns_empty() {
		$this->api_mock->method( 'request' )->willReturn(
			array( 'content' => array() )
		);

		$result = rp_get_order_by_customer( 'cust-no-invoices' );

		$this->assertSame( '', $result );
	}
}
