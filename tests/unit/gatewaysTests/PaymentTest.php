<?php

namespace Tests\Unit\GatewaysTests;

use PHPUnit\Framework\TestCase;
use Reepay\Checkout\Gateways\ReepayGateway;

class TestReepayPaymentGateway extends ReepayGateway {
    public function process_payment( $order_id ) {

    }
}

class PaymentTest extends TestCase
{
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Payment Gateway
        $this->gateway = $this->getMockBuilder(TestReepayPaymentGateway::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function test_process_payment_success()
    {
        $order_id = 1234;
        $expected_response = [
            'result' => 'success',
            'redirect' => 'https://reepay.com/checkout'
        ];

        $this->gateway->method('process_payment')
            ->with($order_id)
            ->willReturn($expected_response);

        $response = $this->gateway->process_payment($order_id);

        print_r("\n\ntest_process_payment_success");
        print_r($response);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('success', $response['result']);
        $this->assertArrayHasKey('redirect', $response);
    }

    public function test_process_payment_failure()
    {
        $order_id = 5678;
        $expected_response = [
            'result' => 'failure'
        ];

        $this->gateway->method('process_payment')
            ->with($order_id)
            ->willReturn($expected_response);

        $response = $this->gateway->process_payment($order_id);

        print_r("\n\ntest_process_payment_failure");
        print_r($response);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('failure', $response['result']);
    }
}