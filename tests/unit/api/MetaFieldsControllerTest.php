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

	/**
	 * A user_id query-string parameter on an order route must not reroute the permission check
	 * into the user-metadata branch, bypassing the order capability check entirely. Reported
	 * externally: WP_REST_Request's ArrayAccess reads query/body params in addition to URL route
	 * params, so isset( $request['user_id'] ) previously matched a ?user_id=0 query string even
	 * on order routes (which never declare user_id as a URL parameter). Since an unauthenticated
	 * request has get_current_user_id() === 0, that matched the forged user_id === 0 too, letting
	 * the request sail through to return true without ever reaching the edit_shop_orders check.
	 *
	 * @testdox Order routes ignore a forged user_id query parameter
	 */
	public function test_get_items_permissions_check_order_route_ignores_query_string_user_id() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/billwerk/v1/meta-fields/shop_order/1' );
		$request->set_url_params( array( 'post_id' => 1 ) );
		$request->set_query_params( array( 'user_id' => 0 ) );

		$result = $this->controller->get_items_permissions_check( $request );

		$this->assertWPError(
			$result,
			'An order-route request must not be routed into the user-metadata branch via a user_id query parameter.'
		);
	}

	/**
	 * Deleting a meta row belonging to a different user than the one in the route must be denied,
	 * even when given that other user's meta ID directly. edit_user_meta_field() already verifies
	 * this; delete_user_meta_field() previously did not.
	 *
	 * @testdox Deleting another user's meta row is denied
	 */
	public function test_delete_user_meta_field_denies_deleting_another_users_meta() {
		$route_user_id = self::factory()->user->create();
		$other_user_id = self::factory()->user->create();

		$meta_id = add_user_meta( $other_user_id, 'some_key', 'some_value' );

		$request = new WP_REST_Request( 'DELETE', "/billwerk/v1/meta-fields/user/$route_user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $route_user_id,
				'field_id' => $meta_id,
			)
		);

		$result = $this->controller->delete_user_meta_field( $request );

		$this->assertWPError( $result );
		$this->assertNotFalse(
			get_metadata_by_mid( 'user', $meta_id ),
			"The other user's meta row must not have been deleted."
		);
	}

	/**
	 * A user can still delete their own meta row through this endpoint.
	 *
	 * @testdox Deleting your own meta row still works
	 */
	public function test_delete_user_meta_field_allows_deleting_own_meta() {
		$user_id = self::factory()->user->create();
		$meta_id = add_user_meta( $user_id, 'some_key', 'some_value' );

		$request = new WP_REST_Request( 'DELETE', "/billwerk/v1/meta-fields/user/$user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $meta_id,
			)
		);

		$result = $this->controller->delete_user_meta_field( $request );

		$this->assertNotWPError( $result );
		$this->assertFalse(
			get_metadata_by_mid( 'user', $meta_id ),
			"The user's own meta row should have been deleted."
		);
	}

	/**
	 * Updating a meta row belonging to a different user than the one in the route must be denied,
	 * even when given that other user's meta ID and the correct key directly. This check was
	 * already in place before today's fixes; this test closes the gap where that protection had
	 * no automated regression coverage.
	 *
	 * @testdox Editing another user's meta row is denied
	 */
	public function test_edit_user_meta_field_denies_editing_another_users_meta() {
		$route_user_id = self::factory()->user->create();
		$other_user_id = self::factory()->user->create();

		$meta_id = add_user_meta( $other_user_id, 'some_key', 'original_value' );

		$request = new WP_REST_Request( 'PUT', "/billwerk/v1/meta-fields/user/$route_user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $route_user_id,
				'field_id' => $meta_id,
			)
		);
		$request->set_param( 'key', 'some_key' );
		$request->set_param( 'value', 'malicious_value' );
		$request->set_body(
			wp_json_encode(
				array(
					'key'   => 'some_key',
					'value' => 'malicious_value',
				)
			)
		);

		$result = $this->controller->edit_user_meta_field( $request );

		$this->assertWPError( $result );
		$this->assertSame(
			'original_value',
			get_metadata_by_mid( 'user', $meta_id )->meta_value,
			"The other user's meta row must not have been modified."
		);
	}

	/**
	 * A user can still edit their own meta row through this endpoint.
	 *
	 * @testdox Editing your own meta row still works
	 */
	public function test_edit_user_meta_field_allows_editing_own_meta() {
		$user_id = self::factory()->user->create();
		$meta_id = add_user_meta( $user_id, 'some_key', 'original_value' );

		$request = new WP_REST_Request( 'PUT', "/billwerk/v1/meta-fields/user/$user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $meta_id,
			)
		);
		$request->set_param( 'key', 'some_key' );
		$request->set_param( 'value', 'updated_value' );
		$request->set_body(
			wp_json_encode(
				array(
					'key'   => 'some_key',
					'value' => 'updated_value',
				)
			)
		);

		$result = $this->controller->edit_user_meta_field( $request );

		$this->assertNotWPError( $result );
		$this->assertSame(
			'updated_value',
			get_metadata_by_mid( 'user', $meta_id )->meta_value,
			"The user's own meta row should have been updated."
		);
	}

	// -------------------------------------------------------------------------
	// BWPM-307: handlers must read user_id from the same source (the URL route)
	// as get_items_permissions_check(), not from WP_REST_Request's ArrayAccess
	// (query/body first). Before this fix, a logged-in user could put their own
	// ID in the route (passing the permission check) and a victim's ID in
	// ?user_id= or the JSON body (so the handler acted on the victim instead).
	// -------------------------------------------------------------------------

	/**
	 * A request whose route names the caller's own account must not act on a different user named
	 * only via a forged query-string user_id.
	 *
	 * @testdox BWPM-307: get_user_meta_fields() ignores a forged query-string user_id
	 */
	public function test_get_user_meta_fields_ignores_query_string_user_id() {
		$self_id   = self::factory()->user->create();
		$victim_id = self::factory()->user->create();

		add_user_meta( $self_id, 'self_key', 'self_value' );
		add_user_meta( $victim_id, 'victim_key', 'victim_value' );

		$request = new WP_REST_Request( 'GET', "/billwerk/v1/meta-fields/user/$self_id" );
		$request->set_url_params( array( 'user_id' => $self_id ) );
		$request->set_query_params( array( 'user_id' => $victim_id ) );

		$response = $this->controller->get_user_meta_fields( $request );
		$keys     = wp_list_pluck( $response->get_data(), 'key' );

		$this->assertContains( 'self_key', $keys, "Must return the route user's own meta." );
		$this->assertNotContains(
			'victim_key',
			$keys,
			'Must not return another user\'s meta via a forged query-string user_id.'
		);
	}

	/**
	 * A request whose route names the caller's own account must not add meta to a different user
	 * named only via a forged body user_id.
	 *
	 * @testdox BWPM-307: add_user_meta_field() ignores a forged body user_id
	 */
	public function test_add_user_meta_field_ignores_body_user_id() {
		$self_id   = self::factory()->user->create();
		$victim_id = self::factory()->user->create();

		$request = new WP_REST_Request( 'POST', "/billwerk/v1/meta-fields/user/$self_id" );
		$request->set_url_params( array( 'user_id' => $self_id ) );
		$request->set_body_params(
			array(
				'user_id' => $victim_id,
				'key'     => 'new_key',
				'value'   => 'new_value',
			)
		);

		$this->controller->add_user_meta_field( $request );

		$this->assertSame(
			'new_value',
			get_user_meta( $self_id, 'new_key', true ),
			'Meta must be added to the route user.'
		);
		$this->assertSame(
			'',
			get_user_meta( $victim_id, 'new_key', true ),
			'Meta must not be added to a user named only via a forged body user_id.'
		);
	}

	/**
	 * Attacker aligns the forged user_id with the victim's actual ID, attempting to make the
	 * ownership check (which used to compare against that same forged value) pass. After the fix,
	 * the ownership check uses the route's user_id, so this must still be denied.
	 *
	 * @testdox BWPM-307: edit_user_meta_field() ownership check cannot be bypassed via a forged user_id
	 */
	public function test_edit_user_meta_field_denies_when_query_user_id_matches_victim_meta_owner() {
		$self_id   = self::factory()->user->create();
		$victim_id = self::factory()->user->create();

		$meta_id = add_user_meta( $victim_id, 'some_key', 'original_value' );

		$request = new WP_REST_Request( 'PUT', "/billwerk/v1/meta-fields/user/$self_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $self_id,
				'field_id' => $meta_id,
			)
		);
		$request->set_query_params( array( 'user_id' => $victim_id ) );
		$request->set_body_params(
			array(
				'key'   => 'some_key',
				'value' => 'malicious_value',
			)
		);
		$request->set_body(
			wp_json_encode(
				array(
					'key'   => 'some_key',
					'value' => 'malicious_value',
				)
			)
		);

		$result = $this->controller->edit_user_meta_field( $request );

		$this->assertWPError( $result );
		$this->assertSame(
			'original_value',
			get_metadata_by_mid( 'user', $meta_id )->meta_value,
			"The victim's meta row must not have been modified."
		);
	}

	/**
	 * Same forged-user_id-alignment attack as the edit test above, against delete instead.
	 *
	 * @testdox BWPM-307: delete_user_meta_field() ownership check cannot be bypassed via a forged user_id
	 */
	public function test_delete_user_meta_field_denies_when_query_user_id_matches_victim_meta_owner() {
		$self_id   = self::factory()->user->create();
		$victim_id = self::factory()->user->create();

		$meta_id = add_user_meta( $victim_id, 'some_key', 'some_value' );

		$request = new WP_REST_Request( 'DELETE', "/billwerk/v1/meta-fields/user/$self_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $self_id,
				'field_id' => $meta_id,
			)
		);
		$request->set_query_params( array( 'user_id' => $victim_id ) );

		$result = $this->controller->delete_user_meta_field( $request );

		$this->assertWPError( $result );
		$this->assertNotFalse(
			get_metadata_by_mid( 'user', $meta_id ),
			"The victim's meta row must not have been deleted."
		);
	}

	/**
	 * Deleting protected keys (e.g. wp_capabilities) must be rejected, matching the check
	 * edit_user_meta_field() already does. delete_user_meta_field() never validated the meta key
	 * before deleting; this was a separate, pre-existing gap not introduced by BWPM-306, reported
	 * alongside it.
	 *
	 * @testdox BWPM-307: delete_user_meta_field() rejects deleting a protected key
	 */
	public function test_delete_user_meta_field_denies_deleting_protected_key() {
		$user_id = self::factory()->user->create();
		$meta_id = add_user_meta( $user_id, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}' );

		$request = new WP_REST_Request( 'DELETE', "/billwerk/v1/meta-fields/user/$user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $meta_id,
			)
		);

		$result = $this->controller->delete_user_meta_field( $request );

		$this->assertWPError( $result );
		$this->assertNotFalse(
			get_metadata_by_mid( 'user', $meta_id ),
			'A protected key must not have been deleted.'
		);
	}

	/**
	 * Resolves the open question from the BWPM-307 review: add and edit requests for a protected
	 * key are already rejected, by get_items_permissions_check() (the shared permission_callback
	 * for every billwerk/v1/meta-fields/user/... route) validating the key before either handler
	 * ever runs.
	 *
	 * @testdox BWPM-307: permission check rejects a protected key for non-admin add/edit requests
	 */
	public function test_get_items_permissions_check_denies_protected_key_for_own_user_route() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'PUT', "/billwerk/v1/meta-fields/user/$user_id/123" );
		$request->set_url_params( array( 'user_id' => $user_id ) );
		$request->set_body_params(
			array(
				'key'   => 'wp_capabilities',
				'value' => 'malicious_value',
			)
		);

		$result = $this->controller->get_items_permissions_check( $request );

		$this->assertWPError(
			$result,
			'A protected key must be rejected before add/edit handlers ever run, even for the user\'s own route.'
		);
	}
}
