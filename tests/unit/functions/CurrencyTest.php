<?php
/**
 * Class CurrencyTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * CurrencyTest.
 */
class CurrencyTest extends Reepay_UnitTestCase {
	/**
	 * Test @see rp_prepare_amount
	 *
	 * @param float  $amount amount for calculation.
	 * @param string $currency currency for calculation.
	 * @param float  $result expected result.
	 *
	 * @testWith
	 * [1234, "ISK", 1234]
	 * [12.34, "USD", 1234]
	 * [12.34, "EUR", 1234]
	 */
	public function test_rp_prepare_amount( float $amount, string $currency, float $result ) {
		$this->assertSame(
			$result,
			rp_prepare_amount( $amount, $currency )
		);
	}

	/**
	 * Test @see rp_make_initial_amount
	 *
	 * @param int    $amount amount for calculation.
	 * @param string $currency currency for calculation.
	 * @param float  $result expected result.
	 *
	 * @testWith
	 * [1234, "ISK", 1234]
	 * [1234, "USD", 12.34]
	 * [1234, "EUR", 12.34]
	 */
	public function test_rp_make_initial_amount( int $amount, string $currency, float $result ) {
		$this->assertSame(
			$result,
			rp_make_initial_amount( $amount, $currency )
		);
	}

	/**
	 * Test @see rp_get_currency_multiplier
	 *
	 * @param int    $multiplier multiplier value.
	 * @param string $currency currency for calculation.
	 *
	 * @testWith
	 * [1, "ISK"]
	 * [100, "USD"]
	 * [100, "EUR"]
	 */
	public function test_rp_get_currency_multiplier( int $multiplier, string $currency ) {
		$this->assertSame(
			$multiplier,
			rp_get_currency_multiplier( $currency )
		);
	}
}
