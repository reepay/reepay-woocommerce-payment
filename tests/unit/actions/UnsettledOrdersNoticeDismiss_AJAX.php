<?php
/**
 * Class UnsettledOrdersNoticeDismissTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Actions\UnsettledOrdersNotice;
use Reepay\Checkout\OrderFlow\UnsettledOrdersMonitor;
use Reepay\Checkout\Tests\Helpers\Reepay_Ajax_UnitTestCase;

/**
 * UnsettledOrdersNoticeDismissTest.
 *
 * @covers \Reepay\Checkout\Actions\UnsettledOrdersNotice
 */
class UnsettledOrdersNoticeDismissTest extends Reepay_Ajax_UnitTestCase {

	/**
	 * Test @see UnsettledOrdersNotice::dismiss() stores the server's own current generation for
	 * the current user — not anything client-supplied, so a stale or tampered client can't record
	 * an incorrect dismissal.
	 */
	public function test_dismiss_stores_current_generation_for_current_user() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_option( UnsettledOrdersMonitor::GENERATION_OPTION, 5, false );

		$_POST['nonce'] = wp_create_nonce( 'reepay_dismiss_unsettled_orders_notice' );

		new UnsettledOrdersNotice();

		try {
			$this->_handleAjax( 'reepay_dismiss_unsettled_orders_notice' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( 5, (int) get_user_meta( $admin_id, UnsettledOrdersNotice::DISMISSED_META, true ) );
	}
}
