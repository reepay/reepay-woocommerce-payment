<?php
/**
 * Class MetaFieldTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Utils\MetaField;

/**
 * MetaFieldTest.
 *
 * @covers \Reepay\Checkout\Utils\MetaField
 * @group utils_meta_field
 */
class MetaFieldTest extends Reepay_UnitTestCase {

	// -------------------------------------------------------------------------
	// is_age_verification_enabled
	// -------------------------------------------------------------------------

	/**
	 * Test @see MetaField::is_age_verification_enabled returns true when meta is 'yes'.
	 */
	public function test_is_age_verification_enabled_true() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		update_post_meta( $product_id, '_reepay_enable_age_verification', 'yes' );

		$this->assertTrue( MetaField::is_age_verification_enabled( $product_id ) );
	}

	/**
	 * Test @see MetaField::is_age_verification_enabled returns false when meta is not set.
	 */
	public function test_is_age_verification_enabled_false() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		// No meta set.

		$this->assertFalse( MetaField::is_age_verification_enabled( $product_id ) );
	}

	// -------------------------------------------------------------------------
	// get_minimum_age
	// -------------------------------------------------------------------------

	/**
	 * Test @see MetaField::get_minimum_age returns integer when meta is set.
	 */
	public function test_get_minimum_age_set() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		update_post_meta( $product_id, '_reepay_minimum_age', '18' );

		$this->assertSame( 18, MetaField::get_minimum_age( $product_id ) );
	}

	/**
	 * Test @see MetaField::get_minimum_age returns null when meta is not set.
	 */
	public function test_get_minimum_age_not_set() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();

		$this->assertNull( MetaField::get_minimum_age( $product_id ) );
	}

	// -------------------------------------------------------------------------
	// is_global_age_verification_enabled
	// -------------------------------------------------------------------------

	/**
	 * Test @see MetaField::is_global_age_verification_enabled returns true when WP option is 'yes'.
	 */
	public function test_is_global_age_verification_enabled_true() {
		update_option(
			'woocommerce_reepay_checkout_settings',
			array( 'age_verification' => 'yes' )
		);

		$this->assertTrue( MetaField::is_global_age_verification_enabled() );
	}

	/**
	 * Test @see MetaField::is_global_age_verification_enabled returns false when WP option is not 'yes'.
	 */
	public function test_is_global_age_verification_enabled_false() {
		update_option(
			'woocommerce_reepay_checkout_settings',
			array( 'age_verification' => 'no' )
		);

		$this->assertFalse( MetaField::is_global_age_verification_enabled() );
	}

	// -------------------------------------------------------------------------
	// validate_age_verification
	// -------------------------------------------------------------------------

	/**
	 * Test @see MetaField::validate_age_verification returns empty array for a valid product.
	 */
	public function test_validate_age_verification_valid() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		update_post_meta( $product_id, '_reepay_enable_age_verification', 'yes' );
		update_post_meta( $product_id, '_reepay_minimum_age', '18' );

		$errors = MetaField::validate_age_verification( $product_id );

		$this->assertIsArray( $errors );
		$this->assertEmpty( $errors );
	}

	/**
	 * Test @see MetaField::validate_age_verification returns errors when age is enabled but minimum age is missing.
	 */
	public function test_validate_age_verification_missing_minimum_age() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		update_post_meta( $product_id, '_reepay_enable_age_verification', 'yes' );
		// No minimum age set.

		$errors = MetaField::validate_age_verification( $product_id );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
	}

	/**
	 * Test @see MetaField::validate_age_verification returns errors when minimum age is not in allowed options.
	 */
	public function test_validate_age_verification_invalid_age() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		update_post_meta( $product_id, '_reepay_enable_age_verification', 'yes' );
		update_post_meta( $product_id, '_reepay_minimum_age', '99' ); // not in allowed options.

		$errors = MetaField::validate_age_verification( $product_id );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
	}

	/**
	 * Test @see MetaField::validate_age_verification returns empty array when age verification is disabled.
	 */
	public function test_validate_age_verification_disabled() {
		$product_id = self::$product_generator->generate( 'simple' )->get_id();
		// Age verification not enabled, so no errors regardless.

		$errors = MetaField::validate_age_verification( $product_id );

		$this->assertIsArray( $errors );
		$this->assertEmpty( $errors );
	}
}
