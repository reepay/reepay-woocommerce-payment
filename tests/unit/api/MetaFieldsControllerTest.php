<?php
/**
 * Class MetaFieldsControllerTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api\Controller\MetaFieldsController;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Tests\Mocks\Api\Controller\MetaFieldsControllerTestGadget;

/**
 * Tests for @see MetaFieldsController::prepare_item_for_response regression-testing
 * CVE-2026-118076 (PHP Object Injection).
 *
 * @covers \Reepay\Checkout\Api\Controller\MetaFieldsController
 * @group api
 */
class MetaFieldsControllerTest extends Reepay_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var MetaFieldsController
	 */
	private MetaFieldsController $controller;

	/**
	 * Sets up the controller under test.
	 */
	public function set_up() {
		parent::set_up();

		$this->controller                      = new MetaFieldsController();
		MetaFieldsControllerTestGadget::$woken = false;
	}

	/**
	 * A serialized PHP object (as an attacker-controlled meta value) must never be
	 * instantiated into a real object when the response is prepared.
	 *
	 * @testdox AC1 - Unsafe objects are not created
	 */
	public function test_prepare_item_for_response_does_not_instantiate_objects_from_serialized_value() {
		$malicious_value = serialize( new MetaFieldsControllerTestGadget() );

		$request = new WP_REST_Request( 'GET', '/billwerk/v1/meta-fields/shop_order/1' );

		$this->controller->prepare_item_for_response(
			array(
				'key'   => '_poc_injection',
				'value' => $malicious_value,
			),
			$request
		);

		$this->assertFalse(
			MetaFieldsControllerTestGadget::$woken,
			'Untrusted serialized meta value must not be unserialized into a real object (PHP Object Injection).'
		);
	}

	/**
	 * Existing display behaviour must be preserved: a serialized array value is still
	 * decoded and returned as JSON for the admin UI.
	 *
	 * @testdox AC2 - Existing metadata still displays correctly
	 */
	public function test_prepare_item_for_response_still_decodes_serialized_array_value() {
		$request = new WP_REST_Request( 'GET', '/billwerk/v1/meta-fields/shop_order/1' );

		$response = $this->controller->prepare_item_for_response(
			array(
				'key'   => '_some_array_meta',
				'value' => serialize( array( 'foo' => 'bar' ) ),
			),
			$request
		);

		$data = $response->get_data();

		$this->assertSame( wp_json_encode( array( 'foo' => 'bar' ) ), $data['value'] );
	}

	/**
	 * A Contributor (who only has the generic 'edit_posts'/'delete_posts' capabilities,
	 * unrelated to order management) must not be allowed to read or write order meta
	 * fields through this endpoint.
	 *
	 * @testdox AC3 - Contributors cannot access restricted order metadata
	 */
	public function test_get_items_permissions_check_denies_contributor_for_order_meta() {
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		wp_set_current_user( $contributor_id );

		$request = new WP_REST_Request( 'GET', '/billwerk/v1/meta-fields/shop_order/1' );
		$request->set_param( 'post_id', 1 );

		$result = $this->controller->get_items_permissions_check( $request );

		$this->assertWPError( $result );
	}

	/**
	 * An Administrator (who has 'edit_shop_orders') must still be allowed to read/write
	 * order meta fields through this endpoint.
	 *
	 * @testdox AC4 - Administrators retain access
	 */
	public function test_get_items_permissions_check_allows_administrator_for_order_meta() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'GET', '/billwerk/v1/meta-fields/shop_order/1' );
		$request->set_param( 'post_id', 1 );

		$result = $this->controller->get_items_permissions_check( $request );

		$this->assertTrue( $result );
	}
}
