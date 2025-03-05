<?php

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\OrderFlow\Webhook;
use Reepay\Checkout\Api;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

class WebhookProcessTest extends Reepay_UnitTestCase {
    protected Webhook $webhook;

    /**
	 * Set up before class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
	}

    protected function setUp(): void {
        parent::setUp(); // This initializes $order_generator and $options from Reepay_UnitTestCase
        $this->webhook = new Webhook();
        
        // Initialize order generator with base payment method
        $this->order_generator->set_props([
            'payment_method' => reepay()->gateways()->checkout()->id
        ]);
    }

    public function testProcessInvoiceAuthorized() {
        PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

        // Setup test data
        $handle = 'order-123';
        $transaction = '6cfcbe5c8fe5e65b278b46bdfe72eddc';

        /**
         * Example webhook data
         */
        /*
        array (
            'id' => '8a6cd0aac1fb661b1d244d38bd7258b8',
            'timestamp' => '2025-03-03T04:06:28.025Z',
            'signature' => '4e32abf6b9952644e81230099e7ec6d54f6b13be6f46c8f80c2fd4936a16fd1c',
            'invoice' => 'order-4346',
            'customer' => 'customer-2',
            'transaction' => '6cfcbe5c8fe5e65b278b46bdfe72eddc',
            'event_type' => 'invoice_authorized',
            'event_id' => '584cffd83db38dd85ba66d7f01854811',
        )
        */

        //Set specific order properties including the order ID that matches the invoice
        $this->order_generator->set_props([
            'status' => 'processing',
            'payment_method' => reepay()->gateways()->checkout(),
        ]);

        // $handle_return = rp_get_order_handle( $this->order_generator->order() );
        // error_log('Return : '. $handle_return);

        $data = [
            'event_type' => 'invoice_authorized',
            'invoice' => $handle,
            'transaction' => $transaction
        ];

        $this->order_generator->set_meta( '_reepay_order', $handle );
        // $order->update_meta_data( '_reepay_order', $handle );
        // $order->save_meta_data();

        // Setup API mock with complete invoice data
        $api_mock = $this->getMockBuilder(Api::class)->getMock();
        
        // Mock get_invoice_data with complete response
        $api_mock->method('get_invoice_by_handle')
        ->with($handle)
        ->willReturn([
            'id' => $handle,
            'state' => 'authorized',
            'amount' => 10000, // Amount in cents
            'authorized_amount' => 10000,
            'settled_amount' => 0,
            'refunded_amount' => 0,
            'currency' => 'DKK',
            'order' => [
                'handle' => $handle
            ],
            // Add these fields to prevent null checks
            'recurring_payment_method' => 'card',
            'customer' => 'cust_123'
        ]);

        reepay()->di()->set(Api::class, $api_mock);

        // Process webhook
        $this->webhook->process($data);

        // Reload order from database to get fresh data
        $order = wc_get_order($this->order_generator->order()->get_id());

        print_r('Order ID : '. $this->order_generator->order()->get_id());
        print_r('Transaction ID : '. $order->get_transaction_id());

        // Verify transaction ID was set
        $this->assertEquals($transaction, $order->get_transaction_id());
    }

    // public function testProcessInvoiceSettled() {
    //     $data = [
    //         'event_type' => 'invoice_settled',
    //         'invoice' => 'inv_54321',
    //         'transaction' => 'txn_settled'
    //     ];

    //     // Create order
    //     $this->order_generator->set_props([
    //         'status' => 'processing',
    //         'payment_method' => reepay()->gateways()->checkout()->id
    //     ]);

    //     self::$options->set_options([
    //         'enable_sync' => 'yes',
    //         'status_settled' => 'completed'
    //     ]);

    //     // Setup API mock
    //     $api_mock = $this->getMockBuilder(Api::class)->getMock();
    //     $api_mock->method('get_invoice_data')->willReturn([
    //         'authorized_amount' => 100,
    //         'settled_amount' => 100
    //     ]);
    //     reepay()->di()->set(Api::class, $api_mock);

    //     // Process webhook
    //     $this->webhook->process($data);

    //     // Assert order changes
    //     $order = $this->order_generator->order();
    //     $this->assertEquals($data['transaction'], $order->get_meta('_reepay_capture_transaction'));
    // }

    // public function testProcessInvoiceCancelled() {
    //     $data = [
    //         'event_type' => 'invoice_cancelled',
    //         'invoice' => 'inv_cancel'
    //     ];

    //     // Create order
    //     $this->order_generator->set_props([
    //         'status' => 'pending',
    //         'payment_method' => reepay()->gateways()->checkout()->id
    //     ]);

    //     // Process webhook
    //     $this->webhook->process($data);

    //     // Assert order status change
    //     $order = $this->order_generator->order();
    //     $this->assertEquals('cancelled', $order->get_status());
    // }

    protected function tearDown(): void {
        parent::tearDown();
    }
}
