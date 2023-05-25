<?php
/**
 * Class InstantSettle
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\InstantSettle;

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\OptionsController;
use Reepay\Checkout\Tests\Helpers\OrderGenerator;
use Reepay\Checkout\Tests\Helpers\ProductGenerator;
use Reepay\Checkout\Tests\Mocks\OrderFlow\OrderCaptureMock;

/**
 * InstantSettle.
 *
 * @covers \Reepay\Checkout\OrderFlow\InstantSettle
 */
class InstantSettleTest extends WP_UnitTestCase {
	/**
	 * OptionsController instance
	 *
	 * @var OptionsController
	 */
	private static OptionsController $options;

	/**
	 * ProductGenerator instance
	 *
	 * @var ProductGenerator
	 */
	private static ProductGenerator $product_generator;

	/**
	 * InstantSettle instance
	 *
	 * @var InstantSettle
	 */
	private static InstantSettle $instant_settle_instance;

	/**
	 * OrderGenerator instance
	 *
	 * @var OrderGenerator
	 */
	private OrderGenerator $order_generator;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$options                 = new OptionsController();
		self::$product_generator       = new ProductGenerator();
		self::$instant_settle_instance = new InstantSettle();

		self::$instant_settle_instance::set_order_capture(
			new OrderCaptureMock()
		);
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order_generator = new OrderGenerator();
	}

	/**
	 * Make sure instant settlement can be processed just once
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
				'virtual'      => true,
				'downloadable' => true,
			)
		);

		self::$instant_settle_instance->process_instant_settle( $this->order_generator->order() );

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'virtual'      => true,
				'downloadable' => true,
			)
		);

		self::$instant_settle_instance->process_instant_settle( $this->order_generator->order() );

		$this->assertEmpty( ( new WC_Order_Item_Product( $order_item_id ) )->get_meta( 'settled' ) );
	}

	/**
	 * Test get_instant_settle_items with physical product
	 *
	 * @param string $type product type.
	 * @param bool   $virtual is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param int    $result expected Result.
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
	 * Test get_instant_settle_items with virtual product
	 *
	 * @param string $type product type.
	 * @param bool   $virtual is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param int    $result expected Result.
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
	 * Test get_instant_settle_items with recurring product
	 *
	 * @param string $type product type.
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
	 * Test get_instant_settle_items with fee order item
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
	 * Test get_instant_settle_items with shipping order item
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
	 * Test can_product_be_settled_instantly with physical product
	 *
	 * @param string $type product type.
	 * @param bool   $virtual is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param bool   $result expected Result.
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
	 * Test can_product_be_settled_instantly with virtual product
	 *
	 * @param string $type product type.
	 * @param bool   $virtual is virtual product.
	 * @param bool   $downloadable is downloadable product.
	 * @param bool   $result expected Result.
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
	 * Test can_product_be_settled_instantly with recurring product
	 *
	 * @param string $type product type.
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
}
