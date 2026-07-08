<?php
/**
 * Class EmojiRemoverTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Utils\EmojiRemover;

/**
 * EmojiRemoverTest.
 *
 * @covers \Reepay\Checkout\Utils\EmojiRemover
 * @group utils_emoji_remover
 */
class EmojiRemoverTest extends Reepay_UnitTestCase {

	/**
	 * Test @see EmojiRemover::filter strips emoji from a string with plain text preserved.
	 */
	public function test_filter_removes_emoji() {
		$input    = "Hello 😀 World 🎉";
		$expected = "Hello  World ";

		$result = EmojiRemover::filter( $input );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test @see EmojiRemover::filter returns plain ASCII string unchanged.
	 */
	public function test_filter_plain_text() {
		$input = 'Hello World 123';

		$result = EmojiRemover::filter( $input );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test @see EmojiRemover::filter returns empty string when input is empty.
	 */
	public function test_filter_empty_string() {
		$result = EmojiRemover::filter( '' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test @see EmojiRemover::filter replaces emoji with the given $replace_to string.
	 */
	public function test_filter_with_custom_replace() {
		$input  = "Order 🛒 item";
		$result = EmojiRemover::filter( $input, '[emoji]' );

		$this->assertStringContainsString( '[emoji]', $result );
		$this->assertStringContainsString( 'Order', $result );
		$this->assertStringContainsString( 'item', $result );
	}

	/**
	 * Test @see EmojiRemover::filter handles string with only emoji (should return empty after filter).
	 */
	public function test_filter_only_emoji() {
		$input  = "😀🎉🛒";
		$result = EmojiRemover::filter( $input );

		$this->assertSame( '', $result );
	}

	/**
	 * Test @see EmojiRemover::filter handles flag emoji (regional indicator symbols).
	 */
	public function test_filter_removes_flag_emoji() {
		$input = "Ship to 🇩🇰";

		$result = EmojiRemover::filter( $input );

		$this->assertStringNotContainsString( '🇩🇰', $result );
		$this->assertStringContainsString( 'Ship to', $result );
	}
}
