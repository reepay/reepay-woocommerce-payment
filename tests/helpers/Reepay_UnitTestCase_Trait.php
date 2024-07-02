<?php
/**
 * Trait Reepay_UnitTestCase_Trait
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Billwerk\Sdk\BillwerkClientFactory;
use Billwerk\Sdk\Sdk;
use Billwerk\Sdk\Service\AccountService;
use Billwerk\Sdk\Service\AgreementService;
use Billwerk\Sdk\Service\ChargeService;
use Billwerk\Sdk\Service\CustomerService;
use Billwerk\Sdk\Service\InvoiceService;
use Billwerk\Sdk\Service\PaymentMethodService;
use Billwerk\Sdk\Service\RefundService;
use Billwerk\Sdk\Service\SessionService;
use Billwerk\Sdk\Service\TransactionService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\Tests\Mocks\OrderFlow\OrderCaptureMock;

/**
 * Trait Reepay_UnitTestCase_Trait
 */
trait Reepay_UnitTestCase_Trait {
	/**
	 * OptionsController instance
	 *
	 * @var OptionsController
	 */
	protected static OptionsController $options;

	/**
	 * ProductGenerator instance
	 *
	 * @var ProductGenerator
	 */
	protected static ProductGenerator $product_generator;

	/**
	 * InstantSettle instance
	 *
	 * @var InstantSettle
	 */
	protected static InstantSettle $instant_settle_instance;

	/**
	 * OrderCapture instance
	 *
	 * @var OrderStatuses
	 */
	protected OrderStatuses $order_statuses;

	/**
	 * OrderCapture instance
	 *
	 * @var OrderCapture
	 */
	protected OrderCapture $order_capture;

	/**
	 * OrderGenerator instance
	 *
	 * @var OrderGenerator
	 */
	protected OrderGenerator $order_generator;

	/**
	 * ProductGenerator instance
	 *
	 * @var CartGenerator
	 */
	protected CartGenerator $cart_generator;

	/**
	 * Api class mock
	 *
	 * @var Api|MockObject
	 */
	protected Api $api_mock;

	/**
	 * Api account service mock
	 *
	 * @var AccountService|MockObject
	 */
	protected AccountService $account_service_mock;

	/**
	 * Api agreement service mock
	 *
	 * @var AgreementService|MockObject
	 */
	protected AgreementService $agreement_service_mock;

	/**
	 * Api charge service mock
	 *
	 * @var ChargeService|MockObject
	 */
	protected ChargeService $charge_service_mock;

	/**
	 * Api customer service mock
	 *
	 * @var CustomerService|MockObject
	 */
	protected CustomerService $customer_service_mock;

	/**
	 * Api invoice service mock
	 *
	 * @var InvoiceService|MockObject
	 */
	protected InvoiceService $invoice_service_mock;

	/**
	 * Api payment method service mock
	 *
	 * @var PaymentMethodService|MockObject
	 */
	protected PaymentMethodService $payment_method_service_mock;

	/**
	 * Api refund service mock
	 *
	 * @var RefundService|MockObject
	 */
	protected RefundService $refund_service_mock;

	/**
	 * Api session service mock
	 *
	 * @var SessionService|MockObject
	 */
	protected SessionService $session_service_mock;

	/**
	 * Api transaction service mock
	 *
	 * @var TransactionService|MockObject
	 */
	protected TransactionService $transaction_service_mock;

	/**
	 * Sdk api mock
	 *
	 * @var Sdk|MockObject
	 */
	protected Sdk $sdk_mock;

	/**
	 * Initializes necessary components and mocks
	 */
	public static function set_up_data_before_class() {
		self::$options                 = new OptionsController();
		self::$product_generator       = new ProductGenerator();
		self::$instant_settle_instance = new InstantSettle();

		InstantSettle::set_order_capture(
			new OrderCaptureMock()
		);
	}

	/**
	 * Sets the data for each test method
	 */
	public function set_up_data() {
		$this->order_statuses  = new OrderStatuses();
		$this->order_capture   = new OrderCapture();
		$this->order_generator = new OrderGenerator();
		$this->cart_generator  = new CartGenerator();

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->account_service_mock        = $this->createMock( AccountService::class );
		$this->agreement_service_mock      = $this->createMock( AgreementService::class );
		$this->charge_service_mock         = $this->createMock( ChargeService::class );
		$this->customer_service_mock       = $this->createMock( CustomerService::class );
		$this->invoice_service_mock        = $this->createMock( InvoiceService::class );
		$this->payment_method_service_mock = $this->createMock( PaymentMethodService::class );
		$this->refund_service_mock         = $this->createMock( RefundService::class );
		$this->session_service_mock        = $this->createMock( SessionService::class );
		$this->transaction_service_mock    = $this->createMock( TransactionService::class );
		reepay()->di()->set( AccountService::class, $this->account_service_mock );
		reepay()->di()->set( AgreementService::class, $this->agreement_service_mock );
		reepay()->di()->set( ChargeService::class, $this->charge_service_mock );
		reepay()->di()->set( CustomerService::class, $this->customer_service_mock );
		reepay()->di()->set( InvoiceService::class, $this->invoice_service_mock );
		reepay()->di()->set( PaymentMethodService::class, $this->payment_method_service_mock );
		reepay()->di()->set( RefundService::class, $this->refund_service_mock );
		reepay()->di()->set( SessionService::class, $this->session_service_mock );
		reepay()->di()->set( TransactionService::class, $this->transaction_service_mock );

		$this->api_mock = $this->getMockBuilder( Api::class )->getMock();
		reepay()->di()->set( Api::class, $this->api_mock );
	}
}
