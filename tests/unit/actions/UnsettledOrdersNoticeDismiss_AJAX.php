<?php
/**
 * Class UnsettledOrdersNoticeDismissTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Actions\UnsettledOrdersNotice;
use Reepay\Checkout\Tests\Helpers\Reepay_Ajax_UnitTestCase;

/**
 * UnsettledOrdersNoticeDismissTest.
 *
 * @covers \Reepay\Checkout\Actions\UnsettledOrdersNotice
 */
class UnsettledOrdersNoticeDismissTest extends Reepay_Ajax_UnitTestCase {

	/**
	 * Test @see UnsettledOrdersNotice::dismiss() stores the dismissed hash for the current user.
	 */
	public function test_dismiss_stores_hash_for_current_user() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['nonce'] = wp_create_nonce( 'reepay_dismiss_unsettled_orders_notice' );
		$_POST['hash']  = 'abc123';

		new UnsettledOrdersNotice();

		try {
			$this->_handleAjax( 'reepay_dismiss_unsettled_orders_notice' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( 'abc123', get_user_meta( $admin_id, UnsettledOrdersNotice::DISMISSED_META, true ) );
	}
}
