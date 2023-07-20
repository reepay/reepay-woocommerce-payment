<?php
/**
 * Class InstantSettleTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\InstantSettle;

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * InstantSettleTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\InstantSettle
 */
class InstantSettleTest extends Reepay_UnitTestCase {
	/**
	 * Test @see InstantSettle::maybe_settle_instantly with reepay payment method
	 */
	public function test_maybe_settle_instantly_with_reepay_payment_method() {
		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => false,
				'downloadable' => false,
			)
		);

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );

		self::$instant_settle_instance->maybe_settle_instantly( $this->order_generator->order() );

		$this->assertSame( '1', $this->order_generator->get_meta( '_is_instant_settled' ) );
	}

	/**
	 * Test @see InstantSettle::maybe_settle_instantly with non reepay payment method
	 */
	public function test_maybe_settle_instantly_with_non_reepay_payment_method() {
		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => false,
				'downloadable' => false,
			)
		);

		$this->order_generator->set_prop( 'payment_method', 'cod' );

		self::$instant_settle_instance->maybe_settle_instantly( $this->order_generator->order() );

		$this->assertSame( '', $this->order_generator->get_meta( '_is_instant_settled' ) );
	}

	/**
	 * Test @see InstantSettle::process_instant_settle and make sure instant settlement can be processed just once
	 */
	public function test_process_instant_settle_already_settled() {
		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => false,
				'downloadable' => false,
			)
		);

		self::$instant_settle_instance->process_instant_settle( $this->order_generator->order() );

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => false,
				'downloadable' => false,
			)
		);

		self::$instant_settle_instance->process_instant_settle( $this->order_generator->order() );

		$this->assertFalse(
			WC_Order_Factory::get_order_item( $order_item_id )->meta_exists( 'settled' )
		);
	}

	/**
	 * Test @see InstantSettle::process_instant_settle
	 */
	public function test_process_instant_settle() {
		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
				InstantSettle::SETTLE_VIRTUAL,
				InstantSettle::SETTLE_RECURRING,
				InstantSettle::SETTLE_FEE,
			)
		);

		$order_item_ids = array();

		$order_item_ids[] = $this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => false,
				'downloadable' => false,
			)
		);

		$order_item_ids[] = $this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => true,
				'downloadable' => true,
			)
		);

		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$order_item_ids[] = $this->order_generator->add_product( 'woo_sub' );
		}

		$order_item_ids[] = $this->order_generator->add_fee();

		self::$instant_settle_instance->process_instant_settle( $this->order_generator->order() );

		foreach ( $order_item_ids as $order_item_id ) {
			$this->assertTrue(
				WC_Order_Factory::get_order_item( $order_item_id )->meta_exists( 'settled' )
			);
		}
	}

	/**
	 * Test @see InstantSettle::get_instant_settle_items with physical product
	 *
	 * @param string $type         product type.
	 * @param bool   $virtual      is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param int    $result       expected Result.
	 *
	 * @testWith
	 * ["simple", false, false, 1]
	 * ["simple", true, false, 0]
	 * ["simple", false, true, 0]
	 * ["simple", true, true, 0]
	 * ["woo_sub", false, false, 0]
	 * ["woo_sub", true, false, 0]
	 * ["woo_sub", false, true, 0]
	 * ["woo_sub", true, true, 0]
	 * ["rp_sub", false, false, 1]
	 * ["rp_sub", true, false, 0]
	 * ["rp_sub", false, true, 0]
	 * ["rp_sub", true, true, 0]
	 */
	public function test_get_instant_settle_items_physical( string $type, bool $virtual, bool $downloadable, int $result ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
			)
		);

		$this->order_generator->add_product(
			$type,
			array(
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			)
		);

		$this->assertSame(
			$result,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);
	}

	/**
	 * Test @see InstantSettle::get_instant_settle_items with virtual product
	 *
	 * @param string $type         product type.
	 * @param bool   $virtual      is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param int    $result       expected Result.
	 *
	 * @testWith
	 * ["simple", false, false, 0]
	 * ["simple", true, false, 1]
	 * ["simple", false, true, 1]
	 * ["simple", true, true, 1]
	 * ["woo_sub", false, false, 0]
	 * ["woo_sub", true, false, 0]
	 * ["woo_sub", false, true, 0]
	 * ["woo_sub", true, true, 0]
	 * ["rp_sub", false, false, 0]
	 * ["rp_sub", true, false, 1]
	 * ["rp_sub", false, true, 1]
	 * ["rp_sub", true, true, 1]
	 */
	public function test_get_instant_settle_items_virtual( string $type, bool $virtual, bool $downloadable, int $result ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_VIRTUAL,
			)
		);

		$this->order_generator->add_product(
			$type,
			array(
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			)
		);

		$this->assertSame(
			$result,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);
	}

	/**
	 * Test @see InstantSettle::get_instant_settle_items with recurring product
	 *
	 * @param string $type   product type.
	 * @param int    $result expected Result.
	 *
	 * @testWith
	 * ["simple", 0]
	 * ["woo_sub", 1]
	 * ["rp_sub", 0]
	 */
	public function test_get_instant_settle_items_recurring( string $type, int $result ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_RECURRING,
			)
		);

		$this->order_generator->add_product( $type );

		$this->assertSame(
			$result,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);
	}

	/**
	 * Test @see InstantSettle::get_instant_settle_items with fee order item
	 */
	public function test_get_instant_settle_items_fee() {
		$this->order_generator->add_fee();

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_FEE,
			)
		);

		$this->assertSame(
			1,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);

		self::$options->set_option( 'settle', array() );

		$this->assertSame(
			0,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);
	}

	/**
	 * Test @see InstantSettle::get_instant_settle_items with shipping order item
	 */
	public function test_get_instant_settle_items_shipping() {
		$this->order_generator->add_shipping();

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
			)
		);

		$this->assertSame(
			1,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);

		self::$options->set_option( 'settle', array() );

		$this->assertSame(
			0,
			count( InstantSettle::get_instant_settle_items( $this->order_generator->order() ) )
		);
	}

	/**
	 * Test @see InstantSettle::can_product_be_settled_instantly with physical product
	 *
	 * @param string $type         product type.
	 * @param bool   $virtual      is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param bool   $result       expected Result.
	 *
	 * @testWith
	 * ["simple", false, false, true]
	 * ["simple", true, false, false]
	 * ["simple", false, true, false]
	 * ["simple", true, true, false]
	 * ["woo_sub", false, false, false]
	 * ["woo_sub", true, false, false]
	 * ["woo_sub", false, true, false]
	 * ["woo_sub", true, true, false]
	 * ["rp_sub", false, false, true]
	 * ["rp_sub", true, false, false]
	 * ["rp_sub", false, true, false]
	 * ["rp_sub", true, true, false]
	 */
	public function test_can_product_be_settled_instantly_physical( string $type, bool $virtual, bool $downloadable, bool $result ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
			)
		);

		$product = self::$product_generator->generate(
			$type,
			array(
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			)
		);

		$this->assertSame( $result, InstantSettle::can_product_be_settled_instantly( $product ) );
	}

	/**
	 * Test @see InstantSettle::can_product_be_settled_instantly with virtual product
	 *
	 * @param string $type         product type.
	 * @param bool   $virtual      is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param bool   $result       expected Result.
	 *
	 * @testWith
	 * ["simple", false, false, false]
	 * ["simple", true, false, true]
	 * ["simple", false, true, true]
	 * ["simple", true, true, true]
	 * ["woo_sub", false, false, false]
	 * ["woo_sub", true, false, false]
	 * ["woo_sub", false, true, false]
	 * ["woo_sub", true, true, false]
	 * ["rp_sub", false, false, false]
	 * ["rp_sub", true, false, true]
	 * ["rp_sub", false, true, true]
	 * ["rp_sub", true, true, true]
	 */
	public function test_can_product_be_settled_instantly_virtual( string $type, bool $virtual, bool $downloadable, bool $result ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_VIRTUAL,
			)
		);

		$product = self::$product_generator->generate(
			$type,
			array(
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			)
		);

		$this->assertSame( $result, InstantSettle::can_product_be_settled_instantly( $product ) );
	}

	/**
	 * Test @see InstantSettle::can_product_be_settled_instantly with recurring product
	 *
	 * @param string $type   product type.
	 * @param bool   $result expected Result.
	 *
	 * @testWith
	 * ["simple", false]
	 * ["woo_sub", true]
	 * ["rp_sub", false]
	 */
	public function test_can_product_be_settled_instantly_recurring( string $type, bool $result ) {
		PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_RECURRING,
			)
		);

		$product = self::$product_generator->generate( $type );

		$this->assertSame( $result, InstantSettle::can_product_be_settled_instantly( $product ) );
	}

	/**
	 * Test @see InstantSettle::calculate_instant_settle
	 */
	public function test_calculate_instant_settle() {
		$prices              = array( 1.02, 2.03, 3.05, 5.07 );
		$discounts           = array( 0.13 );
		$order_item_products = array();

		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
				InstantSettle::SETTLE_VIRTUAL,
				InstantSettle::SETTLE_RECURRING,
				InstantSettle::SETTLE_FEE,
			)
		);

		$order_item_products[] = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[0],
				'virtual'       => false,
				'downloadable'  => false,
			)
		);

		$order_item_products[] = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1],
				'virtual'       => true,
				'downloadable'  => true,
			)
		);

		$this->order_generator->add_shipping(
			array(
				'total' => $prices[2],
			)
		);

		$this->order_generator->add_fee(
			array(
				'total' => $prices[3],
			)
		);

		$this->order_generator->set_prop( 'discount_total', $discounts[0] );

		$calculations = InstantSettle::calculate_instant_settle( $this->order_generator->order() );

		$this->assertSame(
			array_sum( $prices ) - array_sum( $discounts ),
			$calculations['settle_amount']
		);

		$this->assertSame(
			count( $order_item_products ),
			count( $calculations['items'] )
		);
	}

	/**
	 * Test @see InstantSettle::calculate_instant_settle with zero total
	 */
	public function test_calculate_instant_settle_zero_total() {
		self::$options->set_option(
			'settle',
			array(
				InstantSettle::SETTLE_PHYSICAL,
				InstantSettle::SETTLE_VIRTUAL,
				InstantSettle::SETTLE_RECURRING,
				InstantSettle::SETTLE_FEE,
			)
		);

		$this->order_generator->add_product( 'simple' );
		$this->order_generator->set_prop( 'discount_total', 200 );

		$calculations = InstantSettle::calculate_instant_settle( $this->order_generator->order() );

		$this->assertSame(
			0,
			$calculations['settle_amount']
		);
	}

	/**
	 * Test @see InstantSettle::get_settled_items
	 */
	public function test_get_settled_items() {
		$settled_order_items[] = WC_Order_Factory::get_order_item(
			$this->order_generator->add_product( 'simple' )
		);

		$not_settled_order_items[] = WC_Order_Factory::get_order_item(
			$this->order_generator->add_product( 'simple' )
		);

		$settled_order_items[] = WC_Order_Factory::get_order_item(
			$this->order_generator->add_product( 'simple' )
		);

		foreach ( $settled_order_items as $order_item ) {
			$order_item->add_meta_data( 'settled', '1' );
			$order_item->save();
		}

		$settled_items      = InstantSettle::get_settled_items( $this->order_generator->order() );
		$settled_items_keys = array_keys( $settled_items );

		foreach ( $settled_order_items as $order_item ) {
			$this->assertContains( $order_item->get_id(), $settled_items_keys );
		}

		foreach ( $not_settled_order_items as $order_item ) {
			$this->assertNotContains( $order_item->get_id(), $settled_items_keys );
		}
	}
}
