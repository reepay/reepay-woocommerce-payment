<?php

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\OrderFlow\Webhook;
use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Tests\Helpers\HPOS_STATE;

class WebhookProcessTest extends Reepay_UnitTestCase {
    protected Webhook $webhook;

    /**
	 * Set up before class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
        HPOS_STATE::init('yes');
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
        $customer = 'customer-123';
        $handle = 'order-123';
        $transaction = 'transaction_id_123';

        /**
         * Example WebHook invoice_authorized data
         */
        /*
        array (
            'id' => '81ea2fad46ab00072f8a5cb30bd29383',
            'timestamp' => '2025-03-10T02:40:54.562Z',
            'signature' => '130a8b794c8b9d7aaf4200db1c7fcbbe5d0dfbc16a86016ce3b211a098bd8aef',
            'invoice' => 'order-4390',
            'customer' => 'customer-2',
            'transaction' => 'ba1b4ff79f334280decbf648af357be1',
            'event_type' => 'invoice_authorized',
            'event_id' => '1ab2f7be7689fb1cc8d447f546a08bed',
        )
        */
        $data = [
            'event_type' => 'invoice_authorized',
            'invoice' => $handle,
            'customer' => $customer,
            'transaction' => $transaction
        ];

        //Set specific order properties including the order ID that matches the invoice
        $this->order_generator->set_props([
            'status' => OrderStatuses::$status_created,
            'payment_method' => reepay()->gateways()->checkout(),
        ]);

        $this->order_generator->set_meta( '_reepay_order', $handle );
        
        // Setup API mock with complete invoice data
        $api_mock = $this->getMockBuilder(Api::class)->getMock();
        
        // Mock get_invoice_data with complete response
        $api_mock->method('get_invoice_by_handle')
        ->with($handle)
        ->willReturn([
            'id' => 'invoice_id_123',
            'handle' => $handle,
            'customer' => $customer,
            'state' => 'authorized',
            'type' => 'ch',
            'amount' => 10000,
            'currency' => 'DKK',
            'org_amount' => 10000,
            'settled_amount' => 0,
            'refunded_amount' => 0,
            'authorized_amount' => 10000,
        ]);

        reepay()->di()->set(Api::class, $api_mock);

        // Process webhook
        $this->webhook->process($data);

        $order = wc_get_order($this->order_generator->order()->get_id());

        // Verify transaction ID was set
        $this->assertEquals($transaction, $order->get_transaction_id());
    }

    public function testProcessInvoiceSettled() {
        PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

        // Setup test data
        $customer = 'customer-456';
        $handle = 'order-456';
        $transaction = 'transaction_id_456';

        $data = [
            'event_type' => 'invoice_settled',
            'invoice' => $handle,
            'customer' => $customer,
            'transaction' => $transaction
        ];

        // Create order
        $this->order_generator->set_props([
            'status' => OrderStatuses::$status_authorized,
            'payment_method' => reepay()->gateways()->checkout()->id
        ]);

        $this->order_generator->set_meta( '_reepay_order', $handle );
        
        // Setup API mock with complete invoice data
        $api_mock = $this->getMockBuilder(Api::class)->getMock();
        
        // Mock get_invoice_data with complete response
        $api_mock->method('get_invoice_by_handle')
        ->with($handle)
        ->willReturn([
            'id' => 'invoice_id_456',
            'handle' => $handle,
            'customer' => $customer,
            'state' => 'settled',
            'type' => 'ch',
            'amount' => 10000,
            'currency' => 'DKK',
            'org_amount' => 10000,
            'settled_amount' => 10000,
            'refunded_amount' => 0,
            'authorized_amount' => 10000,
        ]);

        // Mock get_invoice_data
        $api_mock->method('get_invoice_data')
        ->with( $this->isInstanceOf( WC_Order::class ))
        ->willReturn([
            'id' => 'invoice_id_456',
            'handle' => $handle,
            'customer' => $customer,
            'state' => 'settled',
            'type' => 'ch',
            'amount' => 10000,
            'currency' => 'DKK',
            'org_amount' => 10000,
            'settled_amount' => 10000,
            'refunded_amount' => 0,
            'authorized_amount' => 10000,
        ]);

        $api_mock->method('request')
        ->willReturn([
            'card_transaction' => [
                'card' => 'test'
            ]
        ]);

        reepay()->di()->set(Api::class, $api_mock);

        // Process webhook
        $this->webhook->process($data);

        $order = wc_get_order($this->order_generator->order()->get_id());

        // Verify transaction ID was set
        $this->assertEquals($transaction, $order->get_meta('_reepay_capture_transaction'));
    }

    public function testProcessInvoiceCancelled() {
        PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

        // Setup test data
        $customer = 'customer-789';
        $handle = 'order-789';
        $transaction = 'transaction_id_789';

        $data = [
            'event_type' => 'invoice_cancelled',
            'invoice' => $handle,
            'customer' => $customer,
            'transaction' => $transaction
        ];

        // Create order
        $this->order_generator->set_props([
            'status' => OrderStatuses::$status_authorized,
            'payment_method' => reepay()->gateways()->checkout()->id
        ]);

        $this->order_generator->set_meta( '_reepay_order', $handle );

        // Setup API mock with complete invoice data
        $api_mock = $this->getMockBuilder(Api::class)->getMock();
        
        // Mock get_invoice_data with complete response
        $api_mock->method('get_invoice_by_handle')
        ->with($handle)
        ->willReturn([
            'id' => 'invoice_id_789',
            'handle' => $handle,
            'customer' => $customer,
            'state' => 'settled',
            'type' => 'ch',
            'amount' => 10000,
            'currency' => 'DKK',
            'org_amount' => 10000,
            'settled_amount' => 10000,
            'refunded_amount' => 0,
            'authorized_amount' => 10000,
        ]);

        // Process webhook
        $this->webhook->process($data);

        $order = wc_get_order($this->order_generator->order()->get_id());

        // Verify transaction ID was set
        $this->assertEquals($transaction, $order->get_meta('_reepay_cancel_transaction'));
    }

    protected function tearDown(): void {
        parent::tearDown();
    }
}
