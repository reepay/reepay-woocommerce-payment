<?php
use PHPUnit\Framework\TestCase;

class Test_Reepay_WC_Payment extends TestCase {

    protected function setUp(): void {
        if (class_exists('WP_Mock')) {
            \WP_Mock::setUp();
        }
        require_once dirname(__DIR__) . '/reepay-woocommerce-payment.php';
    }

    protected function tearDown(): void {
        if (class_exists('WP_Mock')) {
            \WP_Mock::tearDown();
        }
    }

    public function test_initialize_gateway() {
        $gateway = new Reepay_WC_Payment_Gateway();
        $this->assertInstanceOf(Reepay_WC_Payment_Gateway::class, $gateway, 'Gateway instance should be created.');
    }

    public function test_process_payment_with_valid_order() {
        $order_id = 123;
        $gateway = new Reepay_WC_Payment_Gateway();

        $result = $gateway->process_payment($order_id);

        $this->assertIsArray($result, 'Result should be an array.');
        $this->assertArrayHasKey('result', $result, 'Result array should have key "result".');
        $this->assertEquals('success', $result['result'], 'Payment processing should return success for valid order.');
    }

    public function test_handle_webhook_valid_data() {
        $_POST = [
            'event'          => 'payment_completed',
            'transaction_id' => 'tx_test_001'
        ];

        $gateway = new Reepay_WC_Payment_Gateway();
        $response = $gateway->handle_webhook();

        $this->assertNotEmpty($response, 'Webhook handler should return a non-empty response.');
    }
}
