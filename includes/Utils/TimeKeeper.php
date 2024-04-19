<?php
/**
 * A class to help tests that depend on time()
 *
 * @package Reepay\Checkout\Utils
 */

namespace Reepay\Checkout\Utils;

/**
 * Class
 *
 * @package Reepay\Checkout\Utils
 */
class TimeKeeper {
	/**
	 * Current time
	 *
	 * @var int|null $current_time Unix time or null.
	 */
	public static ?int $current_time = null;

	/**
	 * USE IN TESTS ONLY
	 *
	 * Sets the current time for time-dependent functions
	 *
	 * @param int $time New current time in Unix timestamp format.
	 *
	 * @return void
	 */
	public static function set( int $time ): void {
		self::$current_time = $time;
	}

	/**
	 * Gets the current time.
	 *
	 * @return int
	 */
	public static function get(): int {
		return self::$current_time ?? time();
	}
}
