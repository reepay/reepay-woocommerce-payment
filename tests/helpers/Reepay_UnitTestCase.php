<?php
/**
 * Class Reepay_UnitTestCase
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\Tests\Mocks\OrderFlow\OrderCaptureMock;
use WP_UnitTestCase;

/**
 * Class Reepay_UnitTestCase
 */
class Reepay_UnitTestCase extends WP_UnitTestCase {
	use Reepay_UnitTestCase_Trait;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::set_up_data_before_class();
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_data();
	}
}
