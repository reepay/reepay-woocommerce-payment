<?php
/**
 *  Unit test
 *
 * @package Reepay\Checkout\Tests\Unit\Actions
 */

namespace Reepay\Checkout\Tests\Unit\Actions;

use Billwerk\Sdk\Exception\BillwerkApiException;
use Billwerk\Sdk\Model\Customer\CustomerCollectionModel;
use Billwerk\Sdk\Model\Customer\CustomerModel;
use Exception;
use Reepay\Checkout\Actions\ReepayCustomer;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use WC_Customer;
use WP_Error;

/**
 * Test class
 *
 * @covers \Reepay\Checkout\Actions\ReepayCustomer
 */
class ReepayCustomerTest extends Reepay_UnitTestCase {
	/**
	 * For mocking.
	 *
	 * @var CustomerCollectionModel $default_customer_collection
	 */
	private CustomerCollectionModel $default_customer_collection;

	/**
	 * For mocking.
	 *
	 * @var CustomerModel $default_customer
	 */
	private CustomerModel $default_customer;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->default_customer_collection = ( new CustomerCollectionModel() )
			->setContent(
				array(
					( new CustomerModel() )
						->setHandle( 'custom-handle' ),
				)
			);

		$this->default_customer = ( new CustomerModel() )
			->setEmail( 'test@example.com' );
	}

	/**
	 * Mocking.
	 *
	 * @return void
	 */
	public function mock_default_customer_collection() {
		$this->customer_service_mock
			->expects( self::once() )
			->method( 'list' )
			->willReturn( $this->default_customer_collection );
	}

	/**
	 * Mocking.
	 *
	 * @param int $exactly Number of calls.
	 * @return void
	 */
	public function mock_default_customer( int $exactly = 1 ) {
		$this->customer_service_mock
			->expects( $this->exactly( $exactly ) )
			->method( 'get' )
			->willReturn( $this->default_customer );
	}

	/**
	 * Testing hooks and functions
	 *
	 * @see ReepayCustomer::user_register()
	 * @return void
	 */
	public function test_user_register_hook() {
		$this->mock_default_customer_collection();

		$this->_backup_hooks();
		new ReepayCustomer();
		$user_id = $this->factory()->user->create();
		$this->assertSame(
			'custom-handle',
			get_user_meta( $user_id, 'reepay_customer_id', true )
		);
		$this->_restore_hooks();
	}

	/**
	 * Test WC Customer exception.
	 *
	 * @see ReepayCustomer::set_reepay_handle()
	 * @return void
	 */
	public function test_wc_customer_exception() {
		$this->_backup_hooks();
		add_filter(
			'woocommerce_customer_data_store',
			function () {
				return 'Invalid_Class_Name';
			},
			10,
			0
		);
		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle(
				0
			)
		);
		$this->_restore_hooks();
	}

	/**
	 * Test exist billing email.
	 *
	 * @return void
	 * @throws Exception WC customer.
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_exist_billing_email() {
		$user_id  = $this->factory()->user->create();
		$customer = new WC_Customer( $user_id );

		$customer->set_billing_email( 'test@example.com' );
		$customer->save();

		$this->customer_service_mock
			->method( 'list' )
			->willReturnOnConsecutiveCalls(
				$this->default_customer_collection,
			);

		$this->assertSame(
			'custom-handle',
			ReepayCustomer::set_reepay_handle( $user_id )
		);
	}

	/**
	 * Test post billing email.
	 *
	 * @return void
	 * @throws Exception WC customer.
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_post_billing_email() {
		$user_id                = $this->factory()->user->create();
		$_POST['billing_email'] = 'test@example.com';

		$this->mock_default_customer_collection();

		$this->assertSame(
			'custom-handle',
			ReepayCustomer::set_reepay_handle( $user_id )
		);
	}

	/**
	 * Test exist email.
	 *
	 * @return void
	 * @throws Exception WC customer.
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_exist_email() {
		$user_id  = $this->factory()->user->create();
		$customer = new WC_Customer( $user_id );

		$customer->set_email( 'test@example.com' );
		$customer->save();

		$this->mock_default_customer_collection();

		$this->assertSame(
			'custom-handle',
			ReepayCustomer::set_reepay_handle( $user_id )
		);
	}

	/**
	 * Test empty email.
	 *
	 * @return void
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_empty_email() {
		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle( 0 )
		);
	}

	/**
	 * Test api exception.
	 *
	 * @return void
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_api_exception() {
		$user_id = $this->factory()->user->create();

		$this->customer_service_mock
			->method( 'list' )
			->willReturnOnConsecutiveCalls(
				$this->throwException( new BillwerkApiException() ),
			);

		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle(
				$user_id
			)
		);
	}

	/**
	 * Test empty api.
	 *
	 * @return void
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_empty_api() {
		$user_id = $this->factory()->user->create();

		$this->customer_service_mock
			->method( 'list' )
			->willReturnOnConsecutiveCalls(
				( new CustomerCollectionModel() )
					->setContent(
						array()
					),
			);

		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle(
				$user_id
			)
		);
	}

	/**
	 * Test default set_reepay_handle.
	 *
	 * @return void
	 * @see ReepayCustomer::set_reepay_handle()
	 */
	public function test_default_set_reepay_handle() {
		$this->mock_default_customer_collection();

		$user_id = $this->factory()->user->create();
		$this->assertSame(
			'custom-handle',
			ReepayCustomer::set_reepay_handle( $user_id )
		);
	}

	/**
	 *  Testing api exception in have_same_handle
	 *
	 * @see ReepayCustomer::have_same_handle()
	 * @return void
	 */
	public function test_api_exception_in_have_same_handle() {
		$this->customer_service_mock
			->method( 'get' )
			->willReturnOnConsecutiveCalls(
				$this->throwException( new BillwerkApiException() ),
			);

		$this->assertFalse(
			ReepayCustomer::have_same_handle( 0, '' )
		);
	}

	/**
	 *  Testing wc customer exception in have_same_handle
	 *
	 * @see ReepayCustomer::have_same_handle()
	 * @return void
	 */
	public function test_wc_customer_exception_in_have_same_handle() {
		$this->mock_default_customer();

		$this->_backup_hooks();
		add_filter(
			'woocommerce_customer_data_store',
			function () {
				return 'Invalid_Class_Name';
			},
			10,
			0
		);
		$this->assertFalse(
			ReepayCustomer::have_same_handle( 0, '' )
		);
		$this->_restore_hooks();
	}

	/**
	 *  Testing empty email in have_same_handle
	 *
	 * @see ReepayCustomer::have_same_handle()
	 * @return void
	 */
	public function test_empty_email_in_have_same_handle() {
		$this->mock_default_customer();

		$this->assertFalse(
			ReepayCustomer::have_same_handle( 0, '' )
		);
	}

	/**
	 *  Testing another email in have_same_handle
	 *
	 * @see ReepayCustomer::have_same_handle()
	 * @return void
	 */
	public function test_another_email_in_have_same_handle() {
		$this->mock_default_customer();
		$_POST['billing_email'] = 'another_email';

		$this->assertTrue(
			ReepayCustomer::have_same_handle( 0, '' )
		);
	}

	/**
	 *  Testing billing email in have_same_handle
	 *
	 * @return void
	 * @throws Exception WC customer.
	 * @see ReepayCustomer::have_same_handle()
	 */
	public function test_billing_email_in_have_same_handle() {
		$this->mock_default_customer( 2 );

		$user_id  = $this->factory()->user->create();
		$customer = new WC_Customer( $user_id );

		$customer->set_billing_email( 'another_email@mail.com' );
		$customer->save();

		$this->assertTrue(
			ReepayCustomer::have_same_handle( $user_id, '' )
		);

		$customer->set_billing_email( 'test@example.com' );
		$customer->save();

		$this->assertFalse(
			ReepayCustomer::have_same_handle( $user_id, '' )
		);
	}
}
