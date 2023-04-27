<?php
/**
 * Class FormatTest
 *
 * @package Reepay\Checkout
 */

/**
 * CurrencyTest.
 */
class FormatTest extends WP_UnitTestCase {
	/**
	 * @param string $card_number
	 * @param string $result
	 * @testWith ["1234567890123456", "1234 56XX XX12 3456"]
	 *           ["123456789012", "1234 56X8 9012"]
	 *           ["12345678", "1234 5678"]
	 *           ["1234", "1234"]
	 *           ["", ""]
	 *
	 */
	public function test_rp_format_credit_card( string $card_number, string $result) {
		$this->assertSame(
			$result,
			rp_format_credit_card( $card_number )
		);
	}
}