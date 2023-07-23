<?php
/**
 * Class ThankyouPage
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_Ajax_UnitTestCase;


/**
 * ThankyouPage.
 *
 * @covers \Reepay\Checkout\OrderFlow\ThankyouPage
 */
class ThankyouPage_AJAX extends Reepay_Ajax_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		new \Reepay\Checkout\OrderFlow\ThankyouPage();
	}

	/**
	 * @param $api_status
	 * @param $expected_status
	 *
	 * @testWith
	 * [ "pending",    "pending" ]
	 * [ "authorized", "paid"    ]
	 * [ "settled",    "paid"    ]
	 * [ "cancelled",  "failed"  ]
	 * [ "failed", 	   "failed"  ]
	 */
	public function test_ajax_check_payment( $api_status, $expected_status ) {
		$_POST['nonce']     = wp_create_nonce( 'reepay' );
		$_POST['order_id']  = $this->order_generator->order()->get_id();
		$_POST['order_key'] = $this->order_generator->order()->get_order_key();

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'state' => $api_status,
				'transactions' => array()
			)
		);

		try {
			$this->_handleAjax( 'reepay_check_payment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$result = json_decode( $this->_last_response, true );

		$this->assertSame( true, $result['success'] );

		$this->assertSame( $expected_status, $result['data']['state'] );
	}
}
