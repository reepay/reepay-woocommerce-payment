<?php

/**
 * Class WoocommerceExistsTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Plugin\WoocommerceExists;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * WoocommerceExistsTest.
 */
class WoocommerceExistsTest extends Reepay_UnitTestCase {
	public static WoocommerceExists $woocommerce_exists;

	protected bool $skip_if_woo_not_active = false;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$woocommerce_exists = new WoocommerceExists();
	}

	public function test_maybe_deactivate() {
		set_current_screen( 'post' );

		self::$woocommerce_exists->maybe_deactivate();

		ob_start();
		do_action( 'admin_notices' );
		$notices = ob_get_clean();

		$this->assertSame( PLUGINS_STATE::woo_activated(), empty( $notices ) );
	}

	public function test_missing_woocommerce_notice() {
		ob_start();
		self::$woocommerce_exists->missing_woocommerce_notice();

		$this->assertNotEmpty( ob_get_clean() );
	}

	public function test_woo_activated() {
		$this->assertSame( PLUGINS_STATE::woo_activated(), WoocommerceExists::woo_activated() );
	}
}