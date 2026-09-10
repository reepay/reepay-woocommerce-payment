<?php
/**
 * Class Reepay_Ajax_UnitTestCase
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use WP_Ajax_UnitTestCase;

/**
 * Class Reepay_Ajax_UnitTestCase
 */
class Reepay_Ajax_UnitTestCase extends WP_Ajax_UnitTestCase {
	use Reepay_UnitTestCase_Trait;

	protected $preserveGlobalState = false;
	protected $runTestInSeparateProcess = true;

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
