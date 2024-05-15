<?php
/**
 * Unit Test
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */

namespace Reepay\Checkout\Tests\Unit\Functions;

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Test Class
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */
class CurrencyTest extends Reepay_UnitTestCase {
	/**
	 * Test rp_prepare_amount
	 *
	 * @param float  $amount amount for calculation.
	 * @param string $currency currency for calculation.
	 * @param float  $expected expected result.
	 *
	 * @testWith
	 * [1234, "ISK", 1234]
	 * [12.34, "USD", 1234]
	 * [12.34, "EUR", 1234]
	 * @see rp_prepare_amount
	 * @group functions_currency
	 */
	public function test_rp_prepare_amount( float $amount, string $currency, float $expected ) {
		self::assertSame(
			$expected,
			rp_prepare_amount( $amount, $currency ),
			'Wrong prepare amount'
		);
	}

	/**
	 * Test rp_make_initial_amount
	 *
	 * @param int    $amount amount for calculation.
	 * @param string $currency currency for calculation.
	 * @param float  $expected expected result.
	 *
	 * @testWith
	 * [1234, "ISK", 1234]
	 * [1234, "USD", 12.34]
	 * [1234, "EUR", 12.34]
	 * @see rp_make_initial_amount
	 * @group functions_currency
	 */
	public function test_rp_make_initial_amount( int $amount, string $currency, float $expected ) {
		self::assertSame(
			$expected,
			rp_make_initial_amount( $amount, $currency ),
			'Wrong initial amount'
		);
	}

	/**
	 * Test rp_get_currency_multiplier
	 *
	 * @param int    $expected multiplier value.
	 * @param string $currency currency for calculation.
	 *
	 * @testWith
	 * [1, "ISK"]
	 * [100, "USD"]
	 * [100, "EUR"]
	 * @see rp_get_currency_multiplier
	 * @group functions_currency
	 */
	public function test_rp_get_currency_multiplier( int $expected, string $currency ) {
		self::assertSame(
			$expected,
			rp_get_currency_multiplier( $currency ),
			'Wrong currency multiplier'
		);
	}
}
