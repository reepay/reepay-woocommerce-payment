<?php
/**
 * Class OrderCaptureTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\InstantSettle;

use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\Tests\Helpers\OptionsController;
use Reepay\Checkout\Tests\Helpers\OrderGenerator;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
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

	/**
	 * Test @see OrderCapture::check_capture_allowed with reepay subscription product
	 */
	public function test_check_capture_allowed_with_reepay_subscription() {
		if ( ! PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped();
		}

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->order_generator->add_product( 'rp_sub' );

		$this->assertFalse( $this->order_capture->check_capture_allowed( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderCapture::check_capture_allowed with non reepay gateway
	 */
	public function test_check_capture_allowed_with_non_reepay_gateway() {
		$this->order_generator->set_prop( 'payment_method', 'cod' );

		$this->order_generator->add_product( 'simple' );

		$this->assertFalse( $this->order_capture->check_capture_allowed( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderCapture::check_capture_allowed with wp error from api
	 */
	public function test_check_capture_allowed_with_wp_error() {
		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->order_generator->add_product( 'simple' );

		$mock = $this->getMockBuilder( Api::class )
					 ->onlyMethods( array( 'get_invoice_data' ) )
					 ->getMock();

		$mock->method( 'get_invoice_data' )->willReturn( new WP_Error( 'test error' ) );

		reepay()->di()->set( Api::class, $mock );

		$this->assertFalse( $this->order_capture->check_capture_allowed( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderCapture::check_capture_allowed with allowed data from api
	 */
	public function test_check_capture_allowed_allowed_by_api() {
		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->order_generator->add_product( 'simple' );

		$mock = $this->getMockBuilder( Api::class )
					 ->onlyMethods( array( 'get_invoice_data' ) )
					 ->getMock();

		$mock->method( 'get_invoice_data' )->willReturn( array(
			'authorized_amount' => 100,
			'settled_amount'    => 10,
		) );

		reepay()->di()->set( Api::class, $mock );

		$this->assertTrue( $this->order_capture->check_capture_allowed( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderCapture::check_capture_allowed with not allowed data from api
	 */
	public function test_check_capture_allowed_not_allowed_by_api() {
		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->order_generator->add_product( 'simple' );

		$mock = $this->getMockBuilder( Api::class )
					 ->onlyMethods( array( 'get_invoice_data' ) )
					 ->getMock();

		$mock->method( 'get_invoice_data' )->willReturn( array(
			'authorized_amount' => 10,
			'settled_amount'    => 100,
		) );

		reepay()->di()->set( Api::class, $mock );

		$this->assertFalse( $this->order_capture->check_capture_allowed( $this->order_generator->order() ) );
	}

	/**
	 * Test @see OrderCapture::get_not_settled_amount with full settle amount
	 */
	public function test_get_not_settled_amount_full() {
		$prices = array( 1.02, 2.03, 3.05, 5.07 );
		$qty    = 2;

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

	/**
	 * Test @see OrderCapture::get_not_settled_amount with zero settle amount
	 */
	public function test_get_not_settled_amount_empty() {
		$prices = array( 1.02, 2.03, 3.05, 5.07 );
		$qty    = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[0]
			),
			array(
				'quantity' => $qty,
				'settled'  => '1'
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1]
			),
			array(
				'quantity' => $qty,
				'settled'  => '1'
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

	/**
	 * Test @see OrderCapture::get_item_data of product order item without tax
	 */
	public function test_get_item_data_prices_not_include_tax() {
		$price = 12.34;
		$qty   = 2;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price
			),
			array(
				'quantity' => $qty
			)
		);

		$order_item = WC_Order_Factory::get_order_item( $order_item_id );

		$this->assertEqualsCanonicalizing(
			array(
				'ordertext'       => $order_item->get_name(),
				'quantity'        => $order_item->get_quantity(),
				'amount'          => $price * 100,
				'vat'             => 0,
				'amount_incl_vat' => false,
			),
			$this->order_capture->get_item_data( $order_item, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderCapture::get_item_data of product order item with tax
	 */
	public function test_get_item_data_prices_include_tax() {
		$product_name = 'test product';
		$price        = 12.34;
		$qty          = 2;
		$tax_rate     = 10;

		add_filter( 'wc_tax_enabled', '__return_true' );
		add_filter( 'woocommerce_prices_include_tax', '__return_true' );

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'name'          => $product_name,
				'regular_price' => $price
			),
			array(
				'quantity' => $qty
			)
		);

		$this->order_generator->add_tax( $tax_rate );

		$this->assertEqualsCanonicalizing(
			array(
				'ordertext'       => $product_name,
				'quantity'        => $qty,
				'amount'          => round( $price * 100 * ( 1 + $tax_rate / 100 ) ),
				'vat'             => $tax_rate / 100,
				'amount_incl_vat' => true,
			),
			$this->order_capture->get_item_data( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderCapture::get_item_price of product
	 */
	public function test_get_item_price_product() {
		$price = 12.34;
		$qty   = 2;

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
	}

	/**
	 * Test @see OrderCapture::get_item_price of product with discount
	 */
	public function test_get_item_price_product_with_discount() {
		$price      = 12.34;
		$sale_price = 1.23;
		$qty        = 2;

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
	}

	/**
	 * Test @see OrderCapture::get_item_price of product with taxes
	 */
	public function test_get_item_price_product_with_taxes() {
		$price      = 12.34;
		$sale_price = 1.23;
		$qty        = 2;
		$tax_rate   = 10;

		add_filter( 'wc_tax_enabled', '__return_true' );

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

		$this->order_generator->add_tax( $tax_rate );

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => ( $sale_price * $qty ) * ( 1 + $tax_rate / 100 ),
			),
			OrderCapture::get_item_price( $order_item_id, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderCapture::get_item_price of shipping
	 */
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
	}

	/**
	 * Test @see OrderCapture::get_item_price of fee
	 */
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
	}
}
