<?php
/**
 * Class OrderCaptureTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\InstantSettle;

use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\Tests\Helpers\OptionsController;
use Reepay\Checkout\Tests\Helpers\OrderGenerator;
use Reepay\Checkout\Tests\Helpers\ProductGenerator;

/**
 * OrderCaptureTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\OrderCapture
 */
class OrderCaptureTest extends WP_UnitTestCase {
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
	 * @var OrderCapture
	 */
	private OrderCapture $order_capture;

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
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order_generator = new OrderGenerator();
		$this->order_capture   = new OrderCapture();
	}

	public function test_get_item_price_product() {
		$price = 12.34;
		$qty = 2;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price
			),
			array(
				'quantity' => $qty
			)
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price * $qty,
				'with_tax' => $price * $qty,
			),
			OrderCapture::get_item_price( $order_item_id, $this->order_generator->order() )
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price * $qty,
				'with_tax' => $price * $qty,
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	public function test_get_item_price_product_with_discount() {
		$price      = 12.34;
		$sale_price = 1.23;
		$qty = 2;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price,
				'sale_price'    => $sale_price
			),
			array(
				'quantity' => $qty
			)
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => $sale_price * $qty,
			),
			OrderCapture::get_item_price( $order_item_id, $this->order_generator->order() )
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => $sale_price * $qty,
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	public function test_get_item_price_product_with_taxes() {
		$this->markTestIncomplete();
		add_filter( 'wc_tax_enabled', '__return_true' );

		$price      = 12.34;
		$sale_price = 1.23;
		$qty = 2;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price,
				'sale_price'    => $sale_price
			),
			array(
				'quantity' => $qty
			)
		);
		var_dump(WC_Tax::get_tax_classes());
		var_dump(WC_Tax::get_tax_rate_classes());
		$this->order_generator->add_tax(10 );

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => $sale_price * $qty,
			),
			OrderCapture::get_item_price( $order_item_id, $this->order_generator->order() )
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => $sale_price * $qty,
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	public function test_get_item_price_shipping() {
		$price = 12.34;

		$order_item_id = $this->order_generator->add_shipping(
			array(
				'total' => $price,
			)
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price,
				'with_tax' => $price,
			),
			OrderCapture::get_item_price( $order_item_id, $this->order_generator->order() )
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price,
				'with_tax' => $price,
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	public function test_get_item_price_fee() {
		$price = 12.34;

		$order_item_id = $this->order_generator->add_fee(
			array(
				'total' => $price,
			)
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price,
				'with_tax' => $price,
			),
			OrderCapture::get_item_price( $order_item_id, $this->order_generator->order() )
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price,
				'with_tax' => $price,
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	public function test_get_not_settled_amount_full() {
		$prices = array( 1.02, 2.03, 3.05, 5.07 );
		$qty = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[0]
			),
			array(
				'quantity' => $qty
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1]
			),
			array(
				'quantity' => $qty
			)
		);

		$this->order_generator->add_shipping(
			array(
				'total' => $prices[2],
			)
		);

		$this->order_generator->add_fee( array(
			'total' => $prices[3]
		) );

		$this->assertSame(
			$prices[0] * $qty + $prices[1] * $qty + $prices[2] + $prices[3],
			$this->order_capture->get_not_settled_amount( $this->order_generator->order() )
		);
	}

	public function test_get_not_settled_amount_empty() {
		$prices = array( 1.02, 2.03, 3.05, 5.07 );
		$qty = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[0]
			),
			array(
				'quantity' => $qty,
				'settled' => '1'
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1]
			),
			array(
				'quantity' => $qty,
				'settled' => '1'
			)
		);

		$this->order_generator->add_shipping(
			array(
				'total'   => $prices[2],
				'settled' => '1'
			)
		);

		$this->order_generator->add_fee( array(
			'total'   => $prices[3],
			'settled' => '1'
		) );

		$this->assertSame(
			0,
			$this->order_capture->get_not_settled_amount( $this->order_generator->order() )
		);
	}
}
