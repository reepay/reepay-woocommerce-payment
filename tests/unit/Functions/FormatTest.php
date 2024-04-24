<?php
/**
 * Class FormatTest
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
class FormatTest extends Reepay_UnitTestCase {
	/**
	 * Test rp_format_credit_card
	 *
	 * @param string $card_number card number.
	 * @param string $expected expected result.
	 *
	 * @testWith
	 * ["1234567890123456", "1234 56XX XX12 3456"]
	 * ["123456789012", "1234 56X8 9012"]
	 * ["12345678", "1234 5678"]
	 * ["1234", "1234"]
	 * ["", ""]
	 *
	 * @see rp_format_credit_card
	 * @group functions_format
	 */
	public function test_rp_format_credit_card( string $card_number, string $expected ) {
		self::assertSame(
			$expected,
			rp_format_credit_card( $card_number )
		);
	}

	/**
	 * Test rp_clear_ordertext
	 *
	 * @param string $title product title.
	 * @param string $expected expected result.
	 *
	 * @testWith
	 * ["Text🇦 with🆓 Enclosed🅑 Alphanumeric🅿️ 🅱Supplement: 🅰️🅙", "Text with Enclosed Alphanumeric Supplement: "]
	 * ["Text👎🏿 with👬🏼 🧤Miscellaneous💅🏽 Symbols🖥️ and🌕︎ Pictographs: 👂︎🖼️", "Text with Miscellaneous Symbols and Pictographs: "]
	 * ["☺️Text with🙋🏾 Emoticons☠️: 😊🫤🤔😕😟", "Text with Emoticons: "]
	 * ["🚇Text🚍︎ with🚑️ Transport🚶🏾 And🛌🏽 Map🛼 Symbols🚀: 🚆", "Text with Transport And Map Symbols: "]
	 * ["🤰Text🤾🏾 🤀with🧒🏾 Supplemental🧝🏿 Symbols🧕🏻 and🧿 Pictographs🤇: 🛑🥣", "Text with Supplemental Symbols and Pictographs: "]
	 * ["☀Text⚩ ⚗with⛿⛏ ⚧Miscellaneous☳ ⚪️Symbols⛹🏾: ♠️♥️", "Text with Miscellaneous Symbols: "]
	 * ["✀Text🙿 ❫with ➿Dingbats➴: ✂️", "Text with Dingbats: "]
	 * ["Text🇨🇾 with Regional 🇰🇲indicator symbols: 🇻🇬", "Text with Regional indicator symbols: "]
	 * ["This is a test string with 😊 emoji, 🚗 car, and 🔥 fire", "This is a test string with  emoji,  car, and  fire"]
	 * ["😊🥳", ""]
	 * ["abc123", "abc123"]
	 *
	 * @see rp_clear_ordertext
	 * @group functions_format
	 */
	public function test_rp_clear_ordertext( string $title, string $expected ) {
		self::assertSame(
			$expected,
			rp_clear_ordertext( $title )
		);
	}
}
