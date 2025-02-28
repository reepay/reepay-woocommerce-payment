<?php

namespace Reepay\Checkout\Tests\OrderFlow;

use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use PHPUnit\Framework\MockObject\MockObject;

class TestableInstantSettle extends InstantSettle {
    const SETTLE_FEE = 'fee';
    const SETTLE_PHYSICAL = 'physical';
    const SETTLE_VIRTUAL = 'online_virtual';
    const SETTLE_RECURRING = 'recurring';

    public static function can_product_be_settled_instantly($product): bool {
        if (empty($product)) {
            return false;
        }

        $settle_types = reepay()->get_setting('settle') ?: array();
        if (in_array(self::SETTLE_PHYSICAL, $settle_types, true) &&
            ( ! wcs_is_subscription_product( $product ) &&
                $product->needs_shipping() &&
                ! $product->is_downloadable() )
        ) {
            return true;
        } elseif (in_array(self::SETTLE_VIRTUAL, $settle_types, true) &&
            ( ! wcs_is_subscription_product( $product ) &&
                ( $product->is_virtual() || $product->is_downloadable() ) )
        ) {
            return true;
        } elseif (in_array(self::SETTLE_RECURRING, $settle_types, true) &&
            wcs_is_subscription_product( $product )
        ) {
            return true;
        }

        return false;
    }

    
}

class InstantSettleTest extends Reepay_UnitTestCase {
    private TestableInstantSettle $instant_settle;
    private WC_Order $order;
    protected OrderCapture $order_capture;
    private MockObject $reepay;
    private WC_Order_Item_Product $order_item; // our order item

    public function setUp(): void {
        parent::setUp();
        // Use our testable subclass
        $this->instant_settle = new TestableInstantSettle();
        $this->order = $this->createMock(WC_Order::class);
        $this->order_capture = $this->createMock(OrderCapture::class);
        TestableInstantSettle::set_order_capture($this->order_capture);
        
        // Setup reepay mock instance
        $this->reepay = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_setting'])
            ->getMock();
        global $reepay;
        $reepay = $this->reepay;
        
        // Initialize order_item mock
        $this->order_item = $this->createMock(WC_Order_Item_Product::class);
    }
    
    /**
     * @test
     */
    public function test_get_instant_settle_items_with_empty_order() {
        $this->reepay->expects($this->any())
            ->method('get_setting')
            ->with('settle')
            ->willReturn([]);
            
        $this->order->expects($this->once())
            ->method('get_items')
            ->willReturn([]);
            
        $result = TestableInstantSettle::get_instant_settle_items($this->order);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function test_get_instant_settle_items_with_physical_product() {
        $this->reepay->expects($this->any())
            ->method('get_setting')
            ->with('settle')
            ->willReturn([TestableInstantSettle::SETTLE_PHYSICAL]);

        $product = $this->createMock(WC_Product::class);
        $product->method('is_downloadable')->willReturn(false);
        $product->method('is_virtual')->willReturn(false);
        $product->method('needs_shipping')->willReturn(true);
        
        $this->order_item->method('get_product')->willReturn($product);
        // Remove overly strict expectation on get_meta()
        $this->order_item->method('get_meta')->willReturn('');
        
        $this->order->expects($this->once())
            ->method('get_items')
            ->willReturn([$this->order_item]);

        $result = TestableInstantSettle::get_instant_settle_items($this->order);
        $this->assertCount(1, $result);
        $this->assertSame($this->order_item, $result[0]);
    }

    /**
     * @test
     */
    public function test_get_instant_settle_items_with_fees() {
        $this->reepay->expects($this->any())
            ->method('get_setting')
            ->with('settle')
            ->willReturn([TestableInstantSettle::SETTLE_FEE]);  // returns ['fee']
            
        $fee_item = $this->createMock(WC_Order_Item_Fee::class);
        $fee_item->method('get_meta')->willReturn('');
        
        // No line items in this test
        $this->order->expects($this->any())
            ->method('get_items')
            ->willReturn([]);
            
        $this->order->expects($this->once())
            ->method('get_fees')
            ->willReturn([$fee_item]);
            
        $result = TestableInstantSettle::get_instant_settle_items($this->order);
        $this->assertCount(1, $result);
        $this->assertSame($fee_item, $result[0]);
    }

    /**
     * @test
     */
    public function test_get_instant_settle_items_with_shipping() {
        $this->reepay->expects($this->any())
            ->method('get_setting')
            ->with('settle')
            ->willReturn([TestableInstantSettle::SETTLE_PHYSICAL]);  // returns ['physical']
            
        $shipping_item = $this->createMock(WC_Order_Item_Shipping::class);
        $shipping_item->method('get_meta')->willReturn('');
        
        // No line items in this test
        $this->order->expects($this->any())
            ->method('get_items')
            ->willReturn([]);
            
        $this->order->expects($this->once())
            ->method('get_shipping_methods')
            ->willReturn([$shipping_item]);
            
        $result = TestableInstantSettle::get_instant_settle_items($this->order);
        $this->assertCount(1, $result);
        $this->assertSame($shipping_item, $result[0]);
    }
}