<?php
/**
 * Trait Reepay_UnitTestCase_Trait
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use PHPUnit\Framework\MockObject\MockObject;
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
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$options                 = new OptionsController();
		self::$product_generator       = new ProductGenerator();
		self::$instant_settle_instance = new InstantSettle();

		self::$instant_settle_instance::set_order_capture(
			new OrderCaptureMock()
		);
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order_statuses  = new OrderStatuses();
		$this->order_capture   = new OrderCapture();
		$this->order_generator = new OrderGenerator();
		$this->cart_generator  = new CartGenerator();

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->api_mock = $this->getMockBuilder( Api::class )->getMock();
		reepay()->di()->set( Api::class, $this->api_mock );
	}
}