<?php

use PHPUnit\Framework\TestCase;
use Reepay\Checkout\OrderFlow\Webhook;

class WebhookProcessTest extends TestCase {
    protected Webhook $webhook;
    protected $mockOrder;

    protected function setUp(): void {
        parent::setUp();
        $this->webhook = new Webhook();
        $this->mockOrder = $this->getMockBuilder(\WC_Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Set common expectations for all tests
        $this->mockOrder->expects($this->any())
            ->method('get_id')
            ->willReturn(1);
            
        // Setup common order methods
        $this->mockOrder->expects($this->any())
            ->method('has_status')
            ->willReturn(false);
            
        $this->mockOrder->expects($this->any())
            ->method('get_meta')
            ->willReturn('');
    }

    public function testProcessInvoiceAuthorized() {
        $data = [
            'event_type' => 'invoice_authorized',
            'invoice' => 'inv_12345',
            'transaction' => 'txn_67890'
        ];

        // Setup order expectations
        $this->mockOrder->expects($this->once())
            ->method('set_transaction_id')
            ->with($data['transaction']);
        
        $this->mockOrder->expects($this->once())
            ->method('save');

        // Mock the order lookup function
        $this->mockFunction('rp_get_order_by_handle', function($invoice) {
            return $invoice === 'inv_12345' ? $this->mockOrder : null;
        });

        // Mock wait_for_unlock to return false
        $this->mockFunction('wait_for_unlock', function() {
            return false;
        });

        $this->webhook->process($data);
    }

    public function testProcessInvoiceSettled() {
        $data = [
            'event_type' => 'invoice_settled',
            'invoice' => 'inv_54321',
            'transaction' => 'txn_settled'
        ];

        // Setup order expectations
        $this->mockOrder->expects($this->once())
            ->method('update_meta_data')
            ->with('_reepay_capture_transaction', $data['transaction']);
        
        $this->mockOrder->expects($this->once())
            ->method('save_meta_data');

        // Mock the order lookup function
        $this->mockFunction('rp_get_not_subs_order_by_handle', function($invoice) {
            return $invoice === 'inv_54321' ? $this->mockOrder : null;
        });

        // Mock wait_for_unlock to return false
        $this->mockFunction('wait_for_unlock', function() {
            return false;
        });

        $this->webhook->process($data);
    }

    public function testProcessInvoiceCancelled() {
        $data = [
            'event_type' => 'invoice_cancelled',
            'invoice' => 'inv_cancel'
        ];

        // Setup order expectations
        $this->mockOrder->expects($this->once())
            ->method('update_status')
            ->with('cancelled', 'Cancelled by WebHook.');

        // Mock the order lookup function
        $this->mockFunction('rp_get_order_by_handle', function($invoice) {
            return $invoice === 'inv_cancel' ? $this->mockOrder : null;
        });

        // Mock wait_for_unlock to return false  
        $this->mockFunction('wait_for_unlock', function() {
            return false;
        });

        $this->webhook->process($data);
    }

    public function testProcessMissingInvoice() {
        $this->expectException(\TypeError::class);

        $data = [
            'event_type' => 'invoice_authorized',
            'invoice' => null  // Added invoice key with null value
        ];
        
        $this->mockFunction('rp_get_order_by_handle', function($handle) {
            if ($handle === null) {
                throw new \TypeError('rp_get_order_by_handle(): Argument #1 ($handle) must be of type string, null given');
            }
            return null;
        });

        $this->webhook->process($data);
    }

    protected function mockFunction(string $function_name, callable $callback): void {
        if (!function_exists($function_name)) {
            eval(sprintf(
                'function %s(...$args) { 
                    return call_user_func_array($GLOBALS["mock_%s"], $args); 
                }',
                $function_name,
                $function_name
            ));
        }
        $GLOBALS["mock_" . $function_name] = $callback;
    }

    protected function tearDown(): void {
        parent::tearDown();
        foreach ($GLOBALS as $key => $value) {
            if (strpos($key, 'mock_') === 0) {
                unset($GLOBALS[$key]);
            }
        }
    }
}
