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
}