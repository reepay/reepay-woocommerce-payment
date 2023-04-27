<?php
/**
 * Class CurrencyTest
 *
 * @package Reepay\Checkout
 */

/**
 * CurrencyTest.
 */
class CurrencyTest extends WP_UnitTestCase {
	/**
	 * @param float  $amount
	 * @param string $currency
	 * @param float  $result
	 *
	 * @testWith [1234, "ISK", 1234]
	 *           [12.34, "USD", 1234]
	 *           [12.34, "EUR", 1234]
	 */
	public function test_rp_prepare_amount( float $amount, string $currency, float $result ) {
		$this->assertSame(
			$result,
			rp_prepare_amount( $amount, $currency )
		);
	}

	/**
	 * @param int  $amount
	 * @param string $currency
	 * @param float  $result
	 *
	 * @testWith [1234, "ISK", 1234]
	 *           [1234, "USD", 12.34]
	 *           [1234, "EUR", 12.34]
	 */
	public function test_rp_make_initial_amount( int $amount, string $currency, float $result ) {
		$this->assertSame(
			$result,
			rp_make_initial_amount( $amount, $currency )
		);
	}

	/**
	 * @param int    $multiplier
	 * @param string $currency
	 *
	 * @testWith [1, "ISK"]
	 *           [100, "USD"]
	 *           [100, "EUR"]
	 */
	public function test_rp_get_currency_multiplier( int $multiplier, string $currency ) {
		$this->assertSame(
			$multiplier,
			rp_get_currency_multiplier( $currency )
		);
	}
}