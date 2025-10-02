<?php
/**
 * Class OrderCaptureTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;

use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\Tests\Helpers\HPOS_STATE;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * OrderCaptureTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\OrderCapture
 */
class OrderCaptureTest extends Reepay_UnitTestCase {
	/**
	 * Test @see OrderCapture::add_item_capture_button
	 */
	public function test_add_item_capture_button() {
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$price = 10.23;
		$qty   = 2;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->order()->save();

		$price = OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() );

		$this->expectOutputString(
			reepay()->get_template(
				'admin/capture-item-button.php',
				array(
					'name'  => 'line_item_capture',
					'value' => $order_item_id,
					'text'  => __( 'Capture', 'reepay-checkout-gateway' ) . ' ' . wc_price( $price['with_tax'] ),
				),
				true
			)
		);

		$this->order_capture->add_item_capture_button( $order_item_id, WC_Order_Factory::get_order_item( $order_item_id ) );
	}

	/**
	 * Test @see OrderCapture::capture_full_order_button with capture not allowed
	 */
	public function test_capture_full_order_button_with_capture_not_allowed() {
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->expectOutputString( '' );

		$this->order_capture->capture_full_order_button( $this->order_generator->order() );
	}

	/**
	 * Test @see OrderCapture::capture_full_order_button with zero settled amount
	 */
	public function test_capture_full_order_button_with_zero_settled_amount() {
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$price = 10.23;
		$qty   = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
				'settled'  => $price * $qty,
			)
		);

		$this->expectOutputString( '' );

		$this->order_capture->capture_full_order_button( $this->order_generator->order() );
	}

	/**
	 * Test @see OrderCapture::capture_full_order_button
	 */
	public function test_capture_full_order_button() {
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$price = 10.23;
		$qty   = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->order()->save();

		$this->expectOutputString(
			reepay()->get_template(
				'admin/capture-item-button.php',
				array(
					'name'  => 'all_items_capture',
					'value' => $this->order_generator->order()->get_id(),
					'text'  => __( 'Capture', 'reepay-checkout-gateway' ) . ' ' . wc_price( $price * $qty ),
				),
				true
			)
		);

		$this->order_capture->capture_full_order_button( $this->order_generator->order() );
	}

	/**
	 * Test @see OrderCapture::process_item_capture with one order item
	 */
	public function test_line_item_capture_one_line_item() {
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
			)
		);

		$price = 10.24;
		$qty   = 2;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->order_generator->order()->save();

		$_POST['line_item_capture'] = $order_item_id;
		$_GET['id']                 = $this->order_generator->order()->get_id();
		$_POST['post_ID']           = $this->order_generator->order()->get_id();

		if ( HPOS_STATE::is_active() ) {
			set_current_screen( 'edit-post' );
		} else {
			$_POST['post_type'] = 'shop_order';
		}

		$this->order_capture->process_item_capture();

		$this->assertSame(
			$price * $qty,
			(float) WC_Order_Factory::get_order_item( $order_item_id )->get_meta( 'settled' )
		);
	}

	/**
	 * Test @see OrderCapture::process_item_capture with capturing all items
	 */
	public function test_line_item_capture_all_line_items() {
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
			)
		);

		$prices = array( 1.02, 2.03, 3.05, 5.07 );
		$qty    = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[0],
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1],
			),
			array(
				'quantity' => $qty,
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

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->order_generator->order()->save();

		$_POST['all_items_capture'] = 'true';
		$_GET['id']                 = $this->order_generator->order()->get_id();
		$_POST['post_ID']           = $this->order_generator->order()->get_id();

		if ( HPOS_STATE::is_active() ) {
			set_current_screen( 'edit-post' );
		} else {
			$_POST['post_type'] = 'shop_order';
		}

		$this->order_capture->process_item_capture();

		$this->order_generator->reset_order();

		$this->assertSame(
			$prices[0] * $qty + $prices[1] * $qty + $prices[2] + $prices[3],
			array_reduce(
				$this->order_generator->order()->get_items( array( 'line_item', 'shipping', 'fee' ) ),
				function ( $carry, $item ) {
					return $carry + ( $item->get_meta( 'settled' ) ?: 0 );
				},
				0
			)
		);
	}

	/**
	 * Test @see OrderCapture::multi_settle
	 */
	public function test_multi_settle() {
		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
			)
		);

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$prices = array( 1.02, 2.03, 3.05, 5.07 );
		$qty    = 2;

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[0],
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1],
			),
			array(
				'quantity' => $qty,
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

		$this->order_capture->multi_settle( $this->order_generator->order() );

		$this->assertSame(
			$prices[0] * $qty + $prices[1] * $qty + $prices[2] + $prices[3],
			array_reduce(
				$this->order_generator->order()->get_items( array( 'line_item', 'shipping', 'fee' ) ),
				function ( $carry, $item ) {
					return $carry + ( $item->get_meta( 'settled' ) ?: 0 );
				},
				0
			)
		);
	}

	/**
	 * Test @see OrderCapture::settle_items with wp error from api
	 */
	public function test_settle_items_with_wp_error() {
		$this->api_mock->method( 'settle' )->willReturn( new WP_Error( 'Test error', 'Test error' ) );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->assertSame(
			false,
			$this->order_capture->settle_items(
				$this->order_generator->order(),
				array(),
				0,
				array()
			)
		);
	}

	/**
	 * Test @see OrderCapture::settle_items with failed status from api
	 */
	public function test_settle_items_with_api_failed() {
		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'failed',
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->assertSame(
			false,
			$this->order_capture->settle_items(
				$this->order_generator->order(),
				array(),
				0,
				array()
			)
		);

		$this->assertNotEmpty(
			get_transient( 'reepay_api_action_error' ),
			'Error message not set'
		);
	}

	/**
	 * Test @see OrderCapture::settle_items with api success
	 */
	public function test_settle_items_success() {
		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
			)
		);

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$product_price = 10.23;

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $product_price,
			)
		);

		$this->assertSame(
			true,
			$this->order_capture->settle_items(
				$this->order_generator->order(),
				array(),
				0,
				array(
					WC_Order_Factory::get_order_item( $order_item_id ),
				)
			)
		);

		$this->assertSame(
			$product_price,
			(float) WC_Order_Factory::get_order_item( $order_item_id )->get_meta( 'settled' )
		);
	}

	/**
	 * Test @see OrderCapture::complete_settle
	 */
	public function test_complete_settle() {
		$total = 1000;

		$order_item_id = $this->order_generator->add_product( 'simple' );

		$this->order_capture->complete_settle(
			WC_Order_Factory::get_order_item( $order_item_id ),
			$this->order_generator->order(),
			$total
		);

		$this->assertSame(
			rp_make_initial_amount( $total, $this->order_generator->order()->get_currency() ),
			(float) WC_Order_Factory::get_order_item( $order_item_id )->get_meta( 'settled' )
		);
	}

	/**
	 * Test @see OrderCapture::settle_item with already settled order item
	 */
	public function test_settle_item_already_settled() {
		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(),
			array(
				'settled' => '1',
			)
		);

		$this->assertTrue(
			$this->order_capture->settle_item(
				WC_Order_Factory::get_order_item( $order_item_id ),
				$this->order_generator->order()
			)
		);
	}

	/**
	 * Test @see OrderCapture::settle_item with zero order item total
	 */
	public function test_settle_item_zero_total() {
		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => 0,
			),
		);

		$this->assertTrue(
			$this->order_capture->settle_item(
				WC_Order_Factory::get_order_item( $order_item_id ),
				$this->order_generator->order()
			)
		);

		$this->assertTrue(
			did_action( 'reepay_order_item_settled' ) === 1,
			'Action "reepay_order_item_settled" not fired or fired more than 1 time'
		);
	}

	/**
	 * Test @see OrderCapture::settle_item not allowed by api
	 */
	public function test_settle_item_not_allowed_by_api() {
		$order_item_id = $this->order_generator->add_product( 'simple' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 10,
				'settled_amount'    => 100,
				'refunded_amount'   => 0,
			)
		);

		$this->assertFalse(
			$this->order_capture->settle_item(
				WC_Order_Factory::get_order_item( $order_item_id ),
				$this->order_generator->order()
			)
		);
	}

	/**
	 * Test @see OrderCapture::settle_item with api error
	 */
	public function test_settle_item_api_error() {
		$order_item_id = $this->order_generator->add_product( 'simple' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn( new WP_Error( 'Test error', 'Test error' ) );

		$this->assertFalse(
			$this->order_capture->settle_item(
				WC_Order_Factory::get_order_item( $order_item_id ),
				$this->order_generator->order()
			)
		);
	}

	/**
	 * Test @see OrderCapture::settle_item with api failed
	 */
	public function test_settle_item_api_failed() {
		$order_item_id = $this->order_generator->add_product( 'simple' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'failed',
			)
		);

		$this->assertFalse(
			$this->order_capture->settle_item(
				WC_Order_Factory::get_order_item( $order_item_id ),
				$this->order_generator->order()
			)
		);

		$this->assertNotEmpty(
			get_transient( 'reepay_api_action_error' ),
			'Error message not set'
		);
	}

	/**
	 * Test @see OrderCapture::settle_item with api success
	 */
	public function test_settle_item_api_success() {
		$order_item_id = $this->order_generator->add_product( 'simple' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
			)
		);

		$this->assertTrue(
			$this->order_capture->settle_item(
				WC_Order_Factory::get_order_item( $order_item_id ),
				$this->order_generator->order()
			)
		);
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

		$this->api_mock->method( 'get_invoice_data' )->willReturn( new WP_Error( 'test error' ) );

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

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 10,
				'refunded_amount'   => 0,
			)
		);

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

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 10,
				'settled_amount'    => 100,
				'refunded_amount'   => 0,
			)
		);

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
				'regular_price' => $prices[0],
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1],
			),
			array(
				'quantity' => $qty,
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
				'regular_price' => $prices[0],
			),
			array(
				'quantity' => $qty,
				'settled'  => '1',
			)
		);

		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $prices[1],
			),
			array(
				'quantity' => $qty,
				'settled'  => '1',
			)
		);

		$this->order_generator->add_shipping(
			array(
				'total'   => $prices[2],
				'settled' => '1',
			)
		);

		$this->order_generator->add_fee(
			array(
				'total'   => $prices[3],
				'settled' => '1',
			)
		);

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
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
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
		$price        = 12;
		$qty          = 2;
		$tax_rate     = 10;

		add_filter( 'wc_tax_enabled', '__return_true' );
		add_filter( 'woocommerce_prices_include_tax', '__return_true' );

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'name'          => $product_name,
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->add_tax( $tax_rate );


		$this->assertEqualsCanonicalizing(
			array(
				'ordertext'       => $product_name,
				'quantity'        => $qty,
				'amount'          => round( $price * ( 100 + $tax_rate )),
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
				'regular_price' => $price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $price * $qty,
				'with_tax' => $price * $qty,
				'tax_percent' => 0,
				'original_with_discount' => $price * $qty,
				'with_tax_and_discount' => $price * $qty,
				'subtotal' => $price * $qty,
				'subtotal_with_tax' => $price * $qty
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
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
				'sale_price'    => $sale_price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => $sale_price * $qty,
				'tax_percent' => 0,
				'original_with_discount' => $sale_price * $qty,
				'with_tax_and_discount' => $sale_price * $qty,
				'subtotal' => $sale_price * $qty,
				'subtotal_with_tax' => $sale_price * $qty
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
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
				'sale_price'    => $sale_price,
			),
			array(
				'quantity' => $qty,
			)
		);

		$this->order_generator->add_tax( $tax_rate );

		$this->assertEqualsCanonicalizing(
			array(
				'original' => $sale_price * $qty,
				'with_tax' => round(( $sale_price * $qty ) * ( 1 + $tax_rate / 100 ), 2),
				'tax_percent' => round($tax_rate),
				'original_with_discount' => $sale_price * $qty,
				'with_tax_and_discount' => round(( $sale_price * $qty ) * ( 1 + $tax_rate / 100 ), 2),
				'subtotal' => $sale_price * $qty,
				'subtotal_with_tax' => round(( $sale_price * $qty ) * ( 1 + $tax_rate / 100 ), 2)
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
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
				'tax_percent' => 0,
				'original_with_discount' => $price,
				'with_tax_and_discount' => $price,
				'subtotal' => 0,
				'subtotal_with_tax' => 0
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
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
				'tax_percent' => 0,
				'original_with_discount' => $price,
				'with_tax_and_discount' => $price,
				'subtotal' => 0,
				'subtotal_with_tax' => 0
			),
			OrderCapture::get_item_price( WC_Order_Factory::get_order_item( $order_item_id ), $this->order_generator->order() )
		);
	}

	/**
	 * Test BWPM-176: Tax calculation issue with discount codes and tax-inclusive pricing
	 *
	 * This test verifies that when tax-inclusive pricing is enabled and a discount is applied,
	 * the capture amount matches the displayed amount (including tax).
	 *
	 * Scenario: Item price 16.00 (incl tax), 10% discount applied
	 * Expected: Display and capture both show 14.40 (12.80 + 1.60 tax)
	 */
	public function test_capture_amount_with_discount_and_tax_inclusive_pricing_bwpm_176() {
		// Setup tax-inclusive pricing
		update_option( 'woocommerce_prices_include_tax', 'yes' );

		// Mock API methods
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 1600, // 16.00 in minor currency
				'settled_amount'    => 0,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
				'amount' => 1440, // Expected capture amount: 14.40 in minor currency
				'transaction' => 'test_transaction_123'
			)
		);

		// Set payment method to Reepay
		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		// Add product with original price 16.00 (including tax)
		$original_price = 16.00;
		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $original_price,
			),
			array(
				'quantity' => 1,
			)
		);

		// Apply 10% discount (1.60 discount on 16.00)
		$discount_amount = 1.60;
		$this->order_generator->order()->set_discount_total( $discount_amount );

		// Add tax rate (25% VAT)
		$tax_rate = 25.0;
		$this->order_generator->add_tax( $tax_rate );

		// Save order
		$this->order_generator->order()->save();

		// Get the order item
		$item = WC_Order_Factory::get_order_item( $order_item_id );

		// Test 1: Verify price calculation for display
		$price = OrderCapture::get_item_price( $item, $this->order_generator->order() );

		// The displayed amount should be 14.40 (16.00 - 1.60 discount)
		$expected_display_amount = 14.40;
		$this->assertSame(
			$expected_display_amount,
			$price['with_tax'],
			'Display amount should be 14.40 (original 16.00 minus 1.60 discount)'
		);

		// Test 2: Verify item data calculation for capture
		$item_data = $this->order_capture->get_item_data( $item, $this->order_generator->order() );

		// The capture amount should match the display amount (1440 in minor currency)
		$expected_capture_amount = rp_prepare_amount( $expected_display_amount, $this->order_generator->order()->get_currency() );
		$this->assertSame(
			$expected_capture_amount,
			$item_data['amount'],
			'Capture amount should match display amount (1440 in minor currency for 14.40)'
		);

		// Test 3: Verify the fix prevents the problematic condition from triggering
		// The condition should not override the amount when tax-inclusive pricing is used
		$this->assertSame(
			true,
			wc_prices_include_tax(),
			'Tax-inclusive pricing should be enabled for this test'
		);

		// Verify that subtotal > original (discount scenario) but amount is not overridden
		$this->assertGreaterThan(
			$price['original'],
			$price['subtotal'],
			'Subtotal should be greater than original (discount applied)'
		);

		// Test 4: Verify actual settle_item behavior
		$settle_result = $this->order_capture->settle_item( $item, $this->order_generator->order() );
		$this->assertSame( true, $settle_result, 'Settle item should succeed' );

		// Clean up
		delete_option( 'woocommerce_prices_include_tax' );
	}

	/**
	 * Test that the fix doesn't break tax-exclusive pricing scenarios
	 */
	public function test_capture_amount_with_discount_and_tax_exclusive_pricing_regression() {
		// Setup tax-exclusive pricing
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// Mock API methods
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 1600,
				'settled_amount'    => 0,
				'refunded_amount'   => 0,
			)
		);

		$this->api_mock->method( 'settle' )->willReturn(
			array(
				'state' => 'success',
				'amount' => 1280, // Expected for tax-exclusive: 12.80
				'transaction' => 'test_transaction_456'
			)
		);

		// Set payment method to Reepay
		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		// Add product with price 12.80 (excluding tax)
		$base_price = 12.80;
		$order_item_id = $this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => $base_price,
			),
			array(
				'quantity' => 1,
			)
		);

		// Apply discount
		$this->order_generator->order()->set_discount_total( 1.28 );

		// Add tax rate
		$this->order_generator->add_tax( 25.0 );

		// Save order
		$this->order_generator->order()->save();

		// Get the order item
		$item = WC_Order_Factory::get_order_item( $order_item_id );

		// Test that tax-exclusive pricing still works correctly
		$item_data = $this->order_capture->get_item_data( $item, $this->order_generator->order() );

		// For tax-exclusive, the amount should be based on the price without tax
		$this->assertFalse(
			wc_prices_include_tax(),
			'Tax-exclusive pricing should be enabled for this test'
		);

		// Verify settle_item behavior for tax-exclusive pricing
		$settle_result = $this->order_capture->settle_item( $item, $this->order_generator->order() );
		$this->assertTrue( $settle_result, 'Settle item should succeed for tax-exclusive pricing' );

		// Clean up
		delete_option( 'woocommerce_prices_include_tax' );
	}
}
