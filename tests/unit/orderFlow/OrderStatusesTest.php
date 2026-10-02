<?php
/**
 * Class OrderStatusesTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * OrderStatusesTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\OrderStatuses
 */
class OrderStatusesTest extends Reepay_UnitTestCase {
	/**
	 * Test function added to filter woocommerce_settings_api_form_fields_reepay_checkout @see OrderStatuses::form_fields()
	 */
	public function test_form_fields_filter() {
		$filter_name = 'reepay_checkout_form_fields';

		remove_all_actions( $filter_name );

		new OrderStatuses();

		$this->assertNotEmpty( apply_filters( $filter_name, array() ) );
	}

	/**
	 * Test @see OrderStatuses::plugins_loaded()
	 *
	 * @param string $status
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::order_statuses()
	 */
	public function test_payment_complete_action_setted( string $status ) {
		remove_all_actions( 'init' );

		$order_statuses = new OrderStatuses();

		do_action( 'init' );

		$this->assertTrue(
			has_action( 'woocommerce_payment_complete_order_status_' . $status, array( $order_statuses, 'payment_complete' ) ) > 0
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with non reepay payment method
	 */
	public function test_add_valid_order_statuses_for_payment_complete_with_non_reepay_gateway() {
		$statuses = array( '1', '2' );

		$this->assertSame(
			$statuses,
			$this->order_statuses->add_valid_order_statuses_for_payment_complete( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with disabled sync
	 */
	public function test_add_valid_order_statuses_for_payment_complete_with_disabled_sync() {
		$statuses = array( '1', '2' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->assertSame(
			$statuses,
			$this->order_statuses->add_valid_order_statuses_for_payment_complete( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with disabled sync
	 */
	public function test_add_valid_order_statuses_for_payment_complete() {
		$statuses = array( '1', '2' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		self::$options->set_options(
			array(
				'enable_sync' => 'yes',
			)
		);

		$this->assertSame(
			array_merge( $statuses, array( OrderStatuses::$status_authorized, OrderStatuses::$status_settled ) ),
			$this->order_statuses->add_valid_order_statuses_for_payment_complete( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with non reepay payment method
	 */
	public function test_payment_complete_order_status_with_non_reepay_gateway() {
		$status = 'default_status';

		$this->assertSame(
			$status,
			$this->order_statuses->payment_complete_order_status( $status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with disabled status sync
	 *
	 * @param bool   $needs_processing order needs processing.
	 * @param string $status expected status.
	 *
	 * @testWith
	 * [true, "processing"]
	 * [false, "completed"]
	 */
	public function test_payment_complete_order_status_with_disabled_status_sync( bool $needs_processing, string $status ) {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		set_transient( 'wc_order_' . $this->order_generator->order()->get_id() . '_needs_processing', $needs_processing ? '1' : '0' );

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->assertSame(
			$status,
			$this->order_statuses->payment_complete_order_status( $status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with status sync
	 */
	public function test_payment_complete_order_status_with_status_syncs() {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		$default_status  = 'pending';
		$expected_status = 'completed';

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		self::$options->set_options(
			array(
				'enable_sync'    => 'yes',
				'status_settled' => $expected_status,
			)
		);

		$this->assertSame(
			$expected_status,
			$this->order_statuses->payment_complete_order_status( $default_status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::payment_complete
	 *
	 * @param string $expected_status
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::order_statuses()
	 */
	public function test_payment_complete( string $expected_status ) {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
				'status'         => 'on-hold',
			)
		);

		self::$options->set_options(
			array(
				'enable_sync'    => 'yes',
				'status_settled' => $expected_status,
			)
		);

		$this->order_statuses->payment_complete( $this->order_generator->order()->get_id() );

		$this->order_generator->reset_order();

		$this->assertSame(
			$expected_status,
			$this->order_generator->order()->get_status()
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status with non reepay payment method
	 */
	public function test_get_authorized_order_status_with_non_reepay_gateway() {
		$status = 'default_status';

		$this->assertSame(
			$status,
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order(), $status )
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status with woo subscription
	 */
	public function test_get_authorized_order_status_with_woo_subscription() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'woo_sub' );

		$this->assertSame(
			'on-hold',
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status without reepay order status sync
	 */
	public function test_get_authorized_order_status_without_sync() {
		$status = 'default_status';

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple' );

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->assertSame(
			$status,
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order(), $status )
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status with reepay order status sync
	 *
	 * @param string $sync_status
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::order_statuses()
	 */
	public function test_get_authorized_order_status_with_sync( string $sync_status ) {
		$status = 'default_status';

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple' );

		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_authorized' => $sync_status,
			)
		);

		$this->assertSame(
			$sync_status,
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order(), $status )
		);
	}

	/**
	 * Test @see OrderStatuses::set_authorized_status with _reepay_state_authorized meta
	 */
	public function test_set_authorized_status_already_authorized() {
		$this->order_generator->set_meta('_reepay_state_authorized', 1);

		$this->assertFalse( OrderStatuses::set_authorized_status( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderStatuses::set_authorized_status to order with authorized status
	 */
	public function test_set_authorized_status_to_order_with_authorized_status() {
		$order_status = 'completed';

		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_authorized' => $order_status,
			)
		);

		$this->order_generator->set_props( array(
			'status' => $order_status,
			'payment_method' => reepay()->gateways()->checkout()
		) );

		$this->assertFalse( OrderStatuses::set_authorized_status( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderStatuses::set_authorized_status
	 */
	public function test_set_authorized_status() {
		$expected_status = 'completed';
		$default_status = 'on-hold';

		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_authorized' => $expected_status,
			)
		);

		$this->order_generator->set_props( array(
			'status' => $default_status,
			'payment_method' => reepay()->gateways()->checkout()
		) );

		$this->assertTrue( OrderStatuses::set_authorized_status( $this->order_generator->order() ) );
		$this->assertSame( 1, $this->order_generator->get_meta( '_reepay_state_authorized' ) );
		$this->assertSame( $expected_status, $this->order_generator->order()->get_status() );
	}

	/**
	 * Test @see OrderStatuses::set_settled_status  with non reepay payment method
	 */
	public function test_set_settled_status_with_non_reepay_payment_method() {
		$this->assertFalse( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderStatuses::set_settled_status  with _reepay_state_settled meta
	 */
	public function test_set_settled_status_with_already_settled_order_meta() {
		$this->order_generator->set_prop('payment_method', reepay()->gateways()->checkout());
		$this->order_generator->set_meta('_reepay_state_settled', 1);

		$this->assertFalse( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderStatuses::set_settled_status  with api error
	 */
	public function test_set_settled_status_with_api_error() {
		$this->order_generator->set_prop('payment_method', reepay()->gateways()->checkout());

		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(new WP_Error());
		reepay()->di()->set( Api::class, $api_mock );


		$this->assertFalse( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderStatuses::set_settled_status  with data for authorized status from api
	 */
	public function test_set_settled_status_with_authorized_data() {
		$expected_status = 'completed';
		$default_status = 'on-hold';

		$this->order_generator->set_props( array(
			'status' => $default_status,
			'payment_method' => reepay()->gateways()->checkout()
		) );

		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_authorized' => $expected_status,
			)
		);

		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10
			)
		);
		reepay()->di()->set( Api::class, $api_mock );

		$this->assertTrue( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
		$this->assertSame( 1, $this->order_generator->get_meta( '_reepay_state_authorized' ) );
		$this->assertSame( $expected_status, $this->order_generator->order()->get_status() );
	}

	/**
	 * Test @see OrderStatuses::set_settled_status
	 */
	public function test_set_settled_status() {
		$order_note = 'Test note';
		$order_transaction_id = 'test_2077';

		$expected_status = 'processing';
		$default_status = 'on-hold';

		$this->order_generator->set_props( array(
			'status' => $default_status,
			'payment_method' => reepay()->gateways()->checkout()
		) );

		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_settled' => $expected_status,
			)
		);

		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 100
			)
		);
		reepay()->di()->set( Api::class, $api_mock );

		$this->assertTrue( OrderStatuses::set_settled_status( $this->order_generator->order(), $order_note, $order_transaction_id ), 'Status not settled' );
		$this->assertSame( $expected_status, $this->order_generator->order()->get_status(), 'Wrong order status' );
		$this->assertSame( $order_transaction_id, $this->order_generator->order()->get_transaction_id(), 'Wrong transaction id' );
		$this->assertTrue( $this->order_generator->note_exists( $order_note ), 'Order note not added' );
		$this->assertSame( 1, $this->order_generator->get_meta( '_reepay_state_settled' ), '_reepay_state_settled meta not settled' );
	}

	/**
	 * Test @see OrderStatuses::set_settled_status does not revert an order that is already
	 * "completed" back to the configured "Status: Frisbii Pay Settled" status. Regression
	 * test for the settled-status-completed-guard fix.
	 *
	 * @group orderflow_statuses
	 */
	public function test_set_settled_status_does_not_downgrade_completed_order() {
		$order_note           = 'Test note';
		$order_transaction_id = 'test_2077';

		$configured_settled_status = 'processing';

		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		self::$options->set_options(
			array(
				'enable_sync'    => 'yes',
				'status_settled' => $configured_settled_status,
			)
		);

		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 100,
			)
		);
		reepay()->di()->set( Api::class, $api_mock );

		$this->assertTrue( OrderStatuses::set_settled_status( $this->order_generator->order(), $order_note, $order_transaction_id ), 'Status not settled' );
		$this->assertSame( 'completed', $this->order_generator->order()->get_status(), 'Order was downgraded away from completed' );
		$this->assertSame( $order_transaction_id, $this->order_generator->order()->get_transaction_id(), 'Wrong transaction id' );
		$this->assertTrue( $this->order_generator->note_exists( $order_note ), 'Order note not added' );
		$this->assertSame( 1, $this->order_generator->get_meta( '_reepay_state_settled' ), '_reepay_state_settled meta not settled' );
	}

	/**
	 * Test @see OrderStatuses::update_order_status
	 */
	public function test_update_order_status() {
		$order_status   = 'completed';
		$transaction_id = 'transaction_id_123';

		OrderStatuses::update_order_status(
			$this->order_generator->order(),
			$order_status,
			'',
			$transaction_id,
			true
		);

		$this->assertSame( $order_status, $this->order_generator->order()->get_status() );
		$this->assertSame( $transaction_id, $this->order_generator->order()->get_transaction_id() );
	}

	/**
	 * Test @see OrderStatuses::is_editable
	 *
	 * @param bool $default_value
	 * @param bool $status_sync_enabled
	 * @param bool $paid_via_reepay
	 * @param bool $order_has_settled_status
	 * @param bool $order_has_authorized_status
	 * @param bool $expected_value
	 *
	 * @testWith
	 * [false, false, false, false, false, false]
	 * [false, false, false, false, true, false]
	 * [false, false, false, true, false, false]
	 * [false, false, true, false, false, false]
	 * [false, false, true, false, true, false]
	 * [false, false, true, true, false, false]
	 * [false, false, false, false, false, false]
	 * [false, false, false, false, true, false]
	 * [false, true, false, true, false, false]
	 * [false, true, true, false, false, false]
	 * [false, true, true, false, true, true]
	 * [false, true, true, true, false, true]
	 * [true, true, false, false, false, true]
	 * [true, true, false, false, true, true]
	 * [true, false, false, true, false, true]
	 * [true, false, true, false, false, true]
	 * [true, false, true, false, true, true]
	 * [true, false, true, true, false, true]
	 * [true, true, false, false, false, true]
	 * [true, true, false, false, true, true]
	 * [true, true, false, true, false, true]
	 * [true, true, true, false, false, true]
	 * [true, true, true, false, true, true]
	 * [true, true, true, true, false, true]
	 */
	public function test_is_editable( bool $default_value, bool $status_sync_enabled, bool $paid_via_reepay, bool $order_has_settled_status, bool $order_has_authorized_status, bool $expected_value ) {
		if ( $order_has_settled_status && $order_has_authorized_status ) {
			$this->markTestSkipped( '$order_has_settled_status and $order_has_authorized_status are the same true' );
		}

		$status_settled    = 'pending';
		$status_authorized = 'processing';
		$order_status      = 'completed';

		if ( $order_has_settled_status ) {
			$order_status = $status_settled;
		} elseif ( $order_has_authorized_status ) {
			$order_status = $status_authorized;
		}

		$this->order_generator->set_prop( 'status', $order_status );

		if ( $paid_via_reepay ) {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		}

		self::$options->set_options(
			array(
				'enable_sync'       => $status_sync_enabled ? 'yes' : 'no',
				'status_settled'    => $status_settled,
				'status_authorized' => $status_authorized,
			)
		);

		$this->assertSame(
			$expected_value,
			$this->order_statuses->is_editable( $default_value, $this->order_generator->order() ),
			var_export( func_get_args(), true )
		);
	}

	/**
	 * Test @see OrderStatuses::is_paid
	 *
	 * @param bool $default_value
	 * @param bool $status_sync_enabled
	 * @param bool $paid_via_reepay
	 * @param bool $order_has_settled_status
	 * @param bool $expected_value
	 *
	 * @testWith
	 * [false, false, false, false, false]
	 * [false, false, false, true, false]
	 * [false, false, true, false, false]
	 * [false, false, true, true, false]
	 * [false, true, false, false, false]
	 * [false, true, false, true, false]
	 * [false, true, true, false, false]
	 * [false, true, true, true, true]
	 * [true, false, false, false, true]
	 * [true, false, false, true, true]
	 * [true, false, true, false, true]
	 * [true, false, true, true, true]
	 * [true, true, false, false, true]
	 * [true, true, false, true, true]
	 * [true, true, true, false, true]
	 * [true, true, true, true, true]
	 */
	public function test_is_paid( bool $default_value, bool $status_sync_enabled, bool $paid_via_reepay, bool $order_has_settled_status, bool $expected_value ) {
		$status_settled     = 'pending';
		$status_not_settled = 'completed';

		self::$options->set_options(
			array(
				'enable_sync'    => $status_sync_enabled ? 'yes' : 'no',
				'status_settled' => $status_settled,
			)
		);

		if ( $paid_via_reepay ) {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		}

		$this->order_generator->set_prop( 'status', $order_has_settled_status ? $status_settled : $status_not_settled );

		$this->assertSame(
			$expected_value,
			$this->order_statuses->is_paid( $default_value, $this->order_generator->order() ),
			var_export( func_get_args(), true )
		);
	}

	/**
	 * Test @see OrderStatuses::cancel_unpaid_order
	 *
	 * @param bool $default_value
	 * @param bool $paid_via_reepay
	 * @param bool $expected_value
	 *
	 * @testWith
	 * [false, false, false]
	 * [false, true, false]
	 * [true, false, true]
	 * [true, true, false]
	 */
	public function test_cancel_unpaid_order( bool $default_value, bool $paid_via_reepay, bool $expected_value ) {
		if ( $paid_via_reepay ) {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		}

		$this->assertSame(
			$expected_value,
			$this->order_statuses->cancel_unpaid_order( $default_value, $this->order_generator->order() ),
			var_export( func_get_args(), true )
		);
	}

	// -----------------------------------------------------------------------
	// payment_complete_order_status()
	// -----------------------------------------------------------------------

	/**
	 * Test @see OrderStatuses::payment_complete_order_status returns configured settled status
	 * when sync is enabled and order is paid via Reepay.
	 *
	 * @group orderflow_statuses
	 */
	public function test_payment_complete_order_status_returns_configured_status() {
		self::$options->set_options(
			array(
				'enable_sync'    => 'yes',
				'status_settled' => 'processing',
			)
		);
		OrderStatuses::init_statuses();

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$result = $this->order_statuses->payment_complete_order_status(
			'on-hold',
			$this->order_generator->order()->get_id(),
			$this->order_generator->order()
		);

		$this->assertSame( OrderStatuses::$status_settled, $result );
	}

	/**
	 * Test @see OrderStatuses::payment_complete_order_status returns the passed status unchanged
	 * for non-Reepay orders.
	 *
	 * @group orderflow_statuses
	 */
	public function test_payment_complete_order_status_unchanged_for_non_reepay() {
		$this->order_generator->set_prop( 'payment_method', 'cod' );
		$this->order_generator->order()->save();

		$result = $this->order_statuses->payment_complete_order_status(
			'on-hold',
			$this->order_generator->order()->get_id(),
			$this->order_generator->order()
		);

		$this->assertSame( 'on-hold', $result );
	}

	// -----------------------------------------------------------------------
	// order_status_changed()
	// -----------------------------------------------------------------------

	/**
	 * Regression test for BWPM-270: the "Status: Frisbii Pay Settled" capture path in
	 * OrderStatuses::order_status_changed() must capture the full remaining amount when
	 * Auto-settle is enabled and the order transitions to the configured settled status,
	 * even if the order's item type (e.g. physical) isn't selected in the Instant Settle
	 * "settle_types" setting. settle_types governs instant settlement at authorization
	 * time, not this capture-on-completion path.
	 *
	 * @group orderflow_statuses
	 */
	public function test_order_status_changed_captures_physical_item_despite_non_qualifying_settle_types() {
		self::$options->set_options(
			array(
				'enable_sync'          => 'yes',
				'status_settled'       => 'completed',
				'disable_auto_settle'  => 'no',
				'settle'               => array( 'online_virtual' ), // physical intentionally excluded.
			)
		);
		OrderStatuses::init_statuses();

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$order = $this->order_generator->order();

		// Make sure the item is physical (needs shipping, not virtual/downloadable) so it
		// would normally be excluded by InstantSettle::calculate_instant_settle().
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$product->set_virtual( false );
			$product->set_downloadable( false );
			$product->save();
		}

		delete_transient( 'reepay_order_complete_should_settle_' . $order->get_id() );

		$this->api_mock->method( 'can_capture' )->willReturn( true );
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 2000,
				'settled_amount'    => 0,
				'refunded_amount'   => 0,
			)
		);
		$this->api_mock->expects( $this->once() )->method( 'capture_payment' );

		$this->order_statuses->order_status_changed( $order->get_id(), 'processing', 'completed', $order );
	}

	/**
	 * Regression test: when "Status: Frisbii Pay Authorized" and "Status: Frisbii Pay
	 * Settled" are configured to the SAME WooCommerce status (e.g. both "Processing"),
	 * the very first pending -> processing transition is the AUTHORIZATION event, not a
	 * genuine settle intent. OrderCapture::capture_full_order() already guards against
	 * mistaking this for a settle trigger (BWPM-249/BWPM-265); order_status_changed() must
	 * have the same guard, otherwise the order gets captured prematurely at authorization
	 * instead of waiting for the admin to actually complete the order — contradicting the
	 * client's own BWPM-265 expectation ("I expected the orders to get settled when their
	 * status change to completed").
	 *
	 * @group orderflow_statuses
	 */
	public function test_order_status_changed_skips_genuine_authorization_event_when_settled_equals_authorized() {
		self::$options->set_options(
			array(
				'enable_sync'         => 'yes',
				'status_authorized'   => 'processing',
				'status_settled'      => 'processing',
				'disable_auto_settle' => 'no',
			)
		);
		OrderStatuses::init_statuses();

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$order = $this->order_generator->order();

		delete_transient( 'reepay_order_complete_should_settle_' . $order->get_id() );

		$this->api_mock->method( 'can_capture' )->willReturn( true );
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 2000,
				'settled_amount'    => 0,
				'refunded_amount'   => 0,
			)
		);
		$this->api_mock->expects( $this->never() )->method( 'capture_payment' );

		// Simulate the real authorization event: pending -> processing.
		$this->order_statuses->order_status_changed( $order->get_id(), 'pending', 'processing', $order );
	}

	/**
	 * Regression test for BWPM-286: "Modified orders get settled for their full original
	 * amount". Reproduces the reported bug end-to-end: OrderStatuses::order_status_changed()
	 * and OrderCapture::capture_full_order() are both hooked to woocommerce_order_status_changed
	 * and both used to attempt a full settle when an order transitioned to "completed". For an
	 * order that was reduced after authorization (e.g. a coupon applied post-authorization,
	 * so only part of the original authorization is still owed), OrderStatuses used to settle
	 * the correct (reduced) amount first, then OrderCapture - unaware anything had already been
	 * settled - resent the same amount again. The API rejected it ("Amount higher than
	 * authorized amount"), and Api::settle()'s retry-on-that-error fallback then silently
	 * settled whatever authorization remained, overcharging the customer for the difference
	 * between the original and the reduced order total.
	 *
	 * Uses a real (non-mocked) Api instance, shared via the DI container by both handlers
	 * exactly as in production, with HTTP mocked at the transport level so the invoice's
	 * settled_amount reflects the actual settle call made.
	 *
	 * @group orderflow_statuses
	 */
	public function test_order_status_changed_then_capture_full_order_does_not_double_settle_reduced_order() {
		$real_api = new Api();
		$real_api->set_logging_source( 'reepay_checkout' );
		reepay()->di()->set( Api::class, $real_api );

		self::$options->set_options(
			array(
				'test_mode'           => 'no',
				'private_key'         => 'priv_test_key_for_unit_tests',
				'enable_sync'         => 'yes',
				'status_authorized'   => 'processing',
				'status_settled'      => 'completed',
				'disable_auto_settle' => 'no',
			)
		);
		OrderStatuses::init_statuses();

		// Order was authorized for 657.00, then reduced (e.g. via coupon) to 607.00 before
		// completion - only the current (reduced) total should ever be charged.
		$authorized_amount_minor = 65700;
		$settled_amount_minor    = 0;
		$settle_call_count       = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$settled_amount_minor, $authorized_amount_minor, &$settle_call_count ) {
				if ( false !== strpos( $url, '/settle' ) ) {
					++$settle_call_count;

					$body            = json_decode( $parsed_args['body'] ?? '{}', true );
					$requested_minor = isset( $body['amount'] ) ? (int) $body['amount'] : 0;

					$settled_amount_minor += $requested_minor;

					return array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'state'       => 'settled',
								'transaction' => 'transaction_' . $settle_call_count,
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}

				if ( false !== strpos( $url, '/invoice/' ) ) {
					return array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'state'             => 'authorized',
								'authorized_amount' => $authorized_amount_minor,
								'settled_amount'    => $settled_amount_minor,
								'refunded_amount'   => 0,
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}

				return $preempt;
			},
			10,
			3
		);

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$order_item_id = $this->order_generator->add_product( 'simple', array( 'regular_price' => 607.00 ) );

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		$order_id = $order->get_id();
		delete_transient( 'reepay_order_complete_should_settle_' . $order_id );

		try {
			// Handler order matches production: OrderStatuses is hooked before OrderCapture
			// in OrderFlow\Main (@see OrderFlow\Main::__construct).
			$this->order_statuses->order_status_changed( $order_id, 'processing', 'completed', $order );

			$this->assertSame( 1, $settle_call_count, 'OrderStatuses alone should make exactly one settle call for the reduced total.' );

			// Regression guard for BWPM-286's second contributing bug: ReepayGateway::capture_payment()
			// used to unconditionally do $order = wc_get_order( $order ), which discards the caller's
			// already-loaded WC_Order instance for a freshly constructed one (@see WC_Order_Factory::get_order).
			// That silently broke object identity with the $order instance WooCommerce passes to every
			// woocommerce_order_status_changed callback (including OrderCapture::capture_full_order()
			// below), so the 'settled' item meta written during capture was invisible to it in-memory
			// within the same request, even though it was correctly persisted to the database.
			$this->assertNotEmpty(
				$order->get_item( $order_item_id, false )->get_meta( 'settled' ),
				'The order object shared across woocommerce_order_status_changed callbacks must reflect the item as settled in-memory, not just in the database.'
			);

			$this->order_capture->capture_full_order( $order_id, 'processing', 'completed', $order );

			$this->assertSame(
				1,
				$settle_call_count,
				'The settle endpoint must only be hit once - OrderCapture must recognize the amount was already captured by OrderStatuses and not resend it.'
			);
			$this->assertSame(
				60700,
				$settled_amount_minor,
				'Only the current (reduced) order total should be settled, not the original authorized amount.'
			);
			$this->assertEquals(
				607.00,
				(float) WC_Order_Factory::get_order_item( $order_item_id )->get_meta( 'settled' )
			);
		} finally {
			remove_all_filters( 'pre_http_request' );
		}
	}
}
