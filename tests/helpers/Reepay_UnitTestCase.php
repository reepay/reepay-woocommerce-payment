<?php
/**
 * Class Reepay_UnitTestCase
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\Tests\Mocks\OrderFlow\OrderCaptureMock;
use WP_UnitTestCase;

/**
 * Class Reepay_UnitTestCase
 */
class Reepay_UnitTestCase extends WP_UnitTestCase {
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

		self::$instant_settle_instance::set_order_capture(
			new OrderCaptureMock()
		);
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order_generator = new OrderGenerator();

		$this->api_mock = $this->getMockBuilder( Api::class )->getMock();
		reepay()->di()->set( Api::class, $this->api_mock );
	}
}