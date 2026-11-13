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
		// An administrator, so the RSCC-011 key filter for customers does not hide the test keys.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
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

	/**
	 * Calls the permission check for a user route and returns the result and the security log lines.
	 *
	 * @param int|string $route_user_id user_id in the URL.
	 * @param mixed      $key           Value of the key query parameter, or null for none.
	 *
	 * @return array{0: mixed, 1: string[]}
	 */
	private function check_user_route( $route_user_id, $key = null ): array {
		$logs = array();
		$grab = function ( $message ) use ( &$logs ) {
			if ( false !== strpos( (string) $message, 'SECURITY' ) || false !== strpos( (string) $message, 'Security Event' ) ) {
				$logs[ (string) $message ] = (string) $message;
			}

			return $message;
		};
		add_filter( 'woocommerce_logger_log_message', $grab );

		$request = new WP_REST_Request( 'GET', "/billwerk/v1/meta-fields/user/$route_user_id" );
		$request->set_url_params( array( 'user_id' => $route_user_id ) );
		if ( null !== $key ) {
			$request->set_query_params( array( 'key' => $key ) );
		}

		$result = $this->controller->get_items_permissions_check( $request );
		remove_filter( 'woocommerce_logger_log_message', $grab );

		return array( $result, array_values( $logs ) );
	}

	/**
	 * RSCC-010: an anonymous caller (user id 0) must not match the "own user" comparison.
	 *
	 * @testdox RSCC-010 - Anonymous caller is refused and nothing is logged
	 */
	public function test_user_route_refuses_anonymous_caller_without_logging() {
		wp_set_current_user( 0 );

		list( $result, $logs ) = $this->check_user_route( 0, 'forged_key' );

		$this->assertWPError( $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
		$this->assertCount( 0, $logs );
	}

	/**
	 * RSCC-010: ids that are not positive integers are refused before any key check.
	 *
	 * @testdox RSCC-010 - Invalid user ids are refused and nothing is logged
	 */
	public function test_user_route_refuses_invalid_user_ids() {
		wp_set_current_user( self::factory()->user->create() );

		foreach ( array( '0', '-1', 'abc' ) as $id ) {
			list( $result, $logs ) = $this->check_user_route( $id, 'forged_key' );

			$this->assertWPError( $result, "user_id $id" );
			$this->assertCount( 0, $logs, "user_id $id" );
		}
	}

	/**
	 * RSCC-010: genuine own-user and administrator access is unchanged.
	 *
	 * @testdox RSCC-010 - Own user and administrator keep access
	 */
	public function test_user_route_still_allows_own_user_and_administrator() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		list( $result ) = $this->check_user_route( $user_id, 'nickname' );
		$this->assertTrue( $result );

		list( $result ) = $this->check_user_route( $user_id + 1000, 'nickname' );
		$this->assertWPError( $result );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		list( $result ) = $this->check_user_route( $user_id, 'nickname' );
		$this->assertTrue( $result );
	}

	/**
	 * RSCC-010: request-derived values reach the log short and on one line, and an array key is not printed.
	 *
	 * @testdox RSCC-010 - Logged key is cleaned and capped
	 */
	public function test_logged_key_is_single_line_and_capped() {
		wp_set_current_user( self::factory()->user->create() );

		list( $result, $logs ) = $this->check_user_route( get_current_user_id(), "bad\r\n[2026-01-01] forged " . str_repeat( 'A', 10000 ) );

		$this->assertWPError( $result );
		$this->assertCount( 1, $logs );
		$this->assertLessThan( 1500, strlen( $logs[0] ) );
		$this->assertStringNotContainsString( "\r", $logs[0] );
		$this->assertSame( 0, preg_match( '/^\[2026-01-01\]/m', $logs[0] ), 'Forged text must not start a log line.' );

		wp_set_current_user( self::factory()->user->create() );
		list( , $logs ) = $this->check_user_route( get_current_user_id(), array( 'a' => 'b' ) );
		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( '[set_meta_key] => invalid', $logs[0] );
	}

	/**
	 * RSCC-010: forwarding headers must not decide the logged IP.
	 *
	 * @testdox RSCC-010 - Logged IP comes from REMOTE_ADDR only
	 */
	public function test_logged_ip_ignores_forwarding_headers() {
		wp_set_current_user( self::factory()->user->create() );
		$_SERVER['REMOTE_ADDR']           = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '198.51.100.99';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.98';

		list( , $logs ) = $this->check_user_route( get_current_user_id(), 'forged_key' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP'] );

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( '203.0.113.7', $logs[0] );
		$this->assertStringNotContainsString( '198.51.100', $logs[0] );
	}

	/**
	 * RSCC-010: values of a successful edit reach the audit log capped and on one line.
	 *
	 * @testdox RSCC-010 - Old and new values of an edit are cleaned and capped in the log
	 */
	public function test_edit_event_values_are_single_line_and_capped() {
		$user_id = self::factory()->user->create();
		// An administrator-only key: allow-listed profile keys refuse a value this long (RSCC-012).
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$meta_id = add_user_meta( $user_id, 'reepay_audit_note', "old\r\n[2026-01-01] forged " . str_repeat( 'O', 10000 ) );
		$new     = "new\r\n[2026-01-01] forged " . str_repeat( 'N', 10000 );

		$logs = array();
		$grab = function ( $message ) use ( &$logs ) {
			if ( false !== strpos( (string) $message, 'user_metadata_modification' ) ) {
				$logs[ (string) $message ] = (string) $message;
			}

			return $message;
		};
		add_filter( 'woocommerce_logger_log_message', $grab );

		$request = new WP_REST_Request( 'PUT', "/billwerk/v1/meta-fields/user/$user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $meta_id,
			)
		);
		$request->set_param( 'key', 'reepay_audit_note' );
		$request->set_param( 'value', $new );
		$this->controller->edit_user_meta_field( $request );
		remove_filter( 'woocommerce_logger_log_message', $grab );

		$logs = array_values( $logs );
		$this->assertCount( 1, $logs );
		$this->assertLessThan( 2500, strlen( $logs[0] ) );
		$this->assertStringNotContainsString( "\r", $logs[0] );
		$this->assertSame( 0, preg_match( '/^\[2026-01-01\]/m', $logs[0] ), 'Forged text must not start a log line.' );
	}

	/**
	 * RSCC-010: a user can log one denied attempt per interval, and the Allow-header re-run logs nothing.
	 *
	 * @testdox RSCC-010 - Denied attempts are limited and logged once per request
	 */
	public function test_denied_attempts_are_rate_limited_and_not_repeated_for_allow_header() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		list( , $logs ) = $this->check_user_route( $user_id, 'forged_one' );
		$this->assertCount( 1, $logs, 'The first denied attempt is logged.' );

		list( $result, $logs ) = $this->check_user_route( $user_id, 'forged_two' );
		$this->assertWPError( $result, 'The request is still refused.' );
		$this->assertCount( 0, $logs, 'A second denied attempt inside the interval is not logged.' );

		wp_set_current_user( self::factory()->user->create() );
		$inside_allow_header = array();
		add_filter(
			'rest_post_dispatch',
			function ( $response ) use ( &$inside_allow_header ) {
				$inside_allow_header = $this->check_user_route( get_current_user_id(), 'forged_three' )[1];

				return $response;
			}
		);
		apply_filters( 'rest_post_dispatch', new WP_REST_Response(), rest_get_server(), new WP_REST_Request() );
		$this->assertCount( 0, $inside_allow_header, 'The permission callback re-run for the Allow header does not log.' );
	}

	/**
	 * Runs the permission callback for a user route that names a meta row by id.
	 *
	 * @param int    $route_user_id User id in the route.
	 * @param int    $meta_id       Meta row id in the route.
	 * @param string $method        HTTP method.
	 *
	 * @return true|WP_Error
	 */
	private function check_row_route( int $route_user_id, int $meta_id, string $method = 'DELETE' ) {
		$request = new WP_REST_Request( $method, "/billwerk/v1/meta-fields/user/$route_user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $route_user_id,
				'field_id' => $meta_id,
			)
		);

		return $this->controller->get_items_permissions_check( $request );
	}

	/**
	 * RSCC-011: DELETE and PUT carry a meta id, so the allow-list applies to the key stored in that row.
	 *
	 * @testdox RSCC-011 - Customer cannot delete or edit a meta row outside the allow-list
	 */
	public function test_customer_row_routes_refuse_keys_outside_allow_list() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$meta_id = add_user_meta( $user_id, 'reepay_customer_id', 'cust-1' );

		foreach ( array( 'DELETE', 'PUT' ) as $method ) {
			$result = $this->check_row_route( $user_id, $meta_id, $method );
			$this->assertWPError( $result, "$method must be refused." );
			$this->assertSame( 'rest_forbidden_meta_key', $result->get_error_code() );
			$this->assertSame( 403, $result->get_error_data()['status'] );
		}
	}

	/**
	 * RSCC-011: allow-listed rows, and administrators, are not affected.
	 *
	 * @testdox RSCC-011 - Allow-listed rows and administrators still pass
	 */
	public function test_row_routes_still_allow_allow_listed_keys_and_administrator() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$nickname_id = add_user_meta( $user_id, 'nickname', 'nick' );
		$this->assertTrue( $this->check_row_route( $user_id, $nickname_id ) );

		$other_id = add_user_meta( $user_id, 'reepay_customer_id', 'cust-1' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( $this->check_row_route( $user_id, $other_id ) );
	}

	/**
	 * RSCC-011: another user's row id is not allowed through, and the row is not deleted.
	 *
	 * @testdox RSCC-011 - A row of another user is not reachable
	 */
	public function test_customer_row_route_with_other_users_row_does_not_delete_it() {
		$user_id  = self::factory()->user->create();
		$other_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$meta_id = add_user_meta( $other_id, 'nickname', 'nick' );

		$request = new WP_REST_Request( 'DELETE', "/billwerk/v1/meta-fields/user/$user_id/$meta_id" );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $meta_id,
			)
		);
		$result = $this->controller->delete_user_meta_field( $request );

		$this->assertWPError( $result );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $meta_id ) );
	}

	/**
	 * RSCC-011: the list for a customer holds only allow-listed keys; the administrator list is unchanged.
	 *
	 * @testdox RSCC-011 - GET list is filtered for customers
	 */
	public function test_get_user_meta_fields_lists_only_allow_listed_keys_for_customer() {
		$user_id = self::factory()->user->create();
		add_user_meta( $user_id, 'reepay_customer_id', 'cust-1' );
		add_user_meta( $user_id, 'nickname', 'nick' );

		$keys_for = function () use ( $user_id ) {
			$request = new WP_REST_Request( 'GET', "/billwerk/v1/meta-fields/user/$user_id" );
			$request->set_url_params( array( 'user_id' => $user_id ) );
			$data = $this->controller->get_user_meta_fields( $request )->get_data();

			return array_column( $data, 'key' );
		};

		wp_set_current_user( $user_id );
		$keys = $keys_for();
		$this->assertContains( 'nickname', $keys );
		$this->assertSame( array(), array_diff( $keys, MetaFieldsController::NON_ADMIN_USER_META_KEYS ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertContains( 'reepay_customer_id', $keys_for() );
	}

	/**
	 * RSCC-011: the row id comes from the URL, as the permission check reads it; a query or body field_id
	 * must not select another row.
	 *
	 * @testdox RSCC-011 - A forged query or body field_id does not select another row
	 */
	public function test_user_row_handlers_ignore_forged_field_id() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$nickname_id = add_user_meta( $user_id, 'nickname', 'nick' );
		$other_id    = add_user_meta( $user_id, 'reepay_customer_id', 'cust-1' );

		foreach ( array( 'query', 'body' ) as $where ) {
			$request = new WP_REST_Request( 'DELETE', "/billwerk/v1/meta-fields/user/$user_id/$nickname_id" );
			$request->set_url_params(
				array(
					'user_id'  => $user_id,
					'field_id' => $nickname_id,
				)
			);
			if ( 'query' === $where ) {
				$request->set_query_params( array( 'field_id' => $other_id ) );
			} else {
				$request->set_body_params( array( 'field_id' => $other_id ) );
			}

			$this->controller->delete_user_meta_field( $request );
			$this->assertNotFalse( get_metadata_by_mid( 'user', $other_id ), "A $where field_id must not delete another row." );
			$this->assertFalse( get_metadata_by_mid( 'user', $nickname_id ), 'The row named in the URL is deleted.' );
			$nickname_id = add_user_meta( $user_id, 'nickname', 'nick' );
		}

		$request = new WP_REST_Request( 'PUT', "/billwerk/v1/meta-fields/user/$user_id/$nickname_id" );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $nickname_id,
			)
		);
		$request->set_query_params( array( 'field_id' => $other_id ) );
		$request->set_param( 'key', 'nickname' );
		$request->set_param( 'value', 'changed' );
		$this->controller->edit_user_meta_field( $request );
		$this->assertSame( 'cust-1', get_metadata_by_mid( 'user', $other_id )->meta_value, 'A query field_id must not edit another row.' );
		$this->assertSame( 'changed', get_metadata_by_mid( 'user', $nickname_id )->meta_value );
	}

	/**
	 * Send a POST (add) or PUT (edit) for one of the user's profile keys straight to the handler.
	 *
	 * @param int    $user_id Route user.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @param int    $meta_id Row id; when set the request is an edit.
	 *
	 * @return mixed Handler result.
	 */
	private function write_profile_field( int $user_id, string $key, $value, int $meta_id = 0 ) {
		$route   = "/billwerk/v1/meta-fields/user/$user_id" . ( $meta_id ? "/$meta_id" : '' );
		$request = new WP_REST_Request( $meta_id ? 'PUT' : 'POST', $route );
		$request->set_url_params(
			array(
				'user_id'  => $user_id,
				'field_id' => $meta_id,
			)
		);
		$request->set_param( 'key', $key );
		$request->set_param( 'value', $value );

		return $meta_id ? $this->controller->edit_user_meta_field( $request ) : $this->controller->add_user_meta_field( $request );
	}

	/**
	 * RSCC-012: markup in a profile field is stored in the form WordPress would store it.
	 *
	 * @testdox RSCC-012 - Markup in profile fields is cleaned on add and edit
	 */
	public function test_profile_fields_are_sanitised() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		// Core creates a nickname row for every new user; start without it.
		delete_user_meta( $user_id, 'nickname' );

		$result = $this->write_profile_field( $user_id, 'nickname', '<script>alert(1)</script><b>Bob</b>' );
		$this->assertNotWPError( $result );
		$stored = get_user_meta( $user_id, 'nickname', true );
		$this->assertSame( sanitize_user_field( 'nickname', '<script>alert(1)</script><b>Bob</b>', $user_id, 'db' ), $stored );
		$this->assertStringNotContainsString( '<', $stored );
		$this->assertSame( $stored, $result->get_data()['value'] );

		$meta_id = $result->get_data()['id'];
		$result  = $this->write_profile_field( $user_id, 'nickname', "Line1\n<img src=x onerror=alert(1)>", $meta_id );
		$this->assertNotWPError( $result );
		$stored = get_user_meta( $user_id, 'nickname', true );
		$this->assertStringNotContainsString( '<', $stored );
		$this->assertSame( sanitize_user_field( 'nickname', "Line1\n<img src=x onerror=alert(1)>", $user_id, 'db' ), $stored );
	}

	/**
	 * RSCC-012: unsupported locale, non-string and oversize values are refused and nothing is stored.
	 *
	 * @testdox RSCC-012 - Bad locale, non-string and oversize values are refused
	 */
	public function test_profile_fields_refuse_invalid_values() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$cases = array(
			'locale'      => 'xx_XX',
			'nickname'    => array( 'a', 'b' ),
			'first_name'  => str_repeat( 'a', 256 ),
			'description' => str_repeat( 'a', 1001 ),
		);
		foreach ( $cases as $key => $value ) {
			delete_user_meta( $user_id, $key );
			$result = $this->write_profile_field( $user_id, $key, $value );
			$this->assertWPError( $result, "$key must be refused." );
			$this->assertSame( 'rest_invalid_meta_value', $result->get_error_code() );
			$this->assertSame( 400, $result->get_error_data()['status'] );
			$this->assertSame( array(), get_user_meta( $user_id, $key ), "$key must not be stored." );
		}

		// An edit is refused too, and the stored row is unchanged.
		$meta_id = add_user_meta( $user_id, 'locale', 'en_US' );
		$result  = $this->write_profile_field( $user_id, 'locale', 'xx_XX', $meta_id );
		$this->assertWPError( $result );
		$this->assertSame( 'en_US', get_metadata_by_mid( 'user', $meta_id )->meta_value );
	}

	/**
	 * RSCC-012: a second POST for the same profile key does not add a row.
	 *
	 * @testdox RSCC-012 - Repeated POST for a profile key keeps one row
	 */
	public function test_profile_field_post_does_not_duplicate_rows() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		// Core creates a nickname row for every new user, so start from a key it does not create.
		delete_user_meta( $user_id, 'description' );

		$this->assertNotWPError( $this->write_profile_field( $user_id, 'description', 'first' ) );
		$second = $this->write_profile_field( $user_id, 'description', 'second' );

		$this->assertWPError( $second );
		$this->assertSame( 'meta_key_exists', $second->get_error_code() );
		$this->assertSame( 409, $second->get_error_data()['status'] );
		$this->assertSame( array( 'first' ), get_user_meta( $user_id, 'description' ) );
	}

	/**
	 * RSCC-012: normal values, an installed locale and the empty locale are stored as sent.
	 *
	 * @testdox RSCC-012 - Valid profile values are stored
	 */
	public function test_profile_fields_store_valid_values() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$valid = array(
			'first_name'  => 'Anna',
			'last_name'   => 'Andersen',
			'description' => 'Hello there',
			'locale'      => 'en_US',
		);
		foreach ( $valid as $key => $value ) {
			delete_user_meta( $user_id, $key );
			$result = $this->write_profile_field( $user_id, $key, $value );
			$this->assertNotWPError( $result, "$key must be accepted." );
			$this->assertSame( $value, get_user_meta( $user_id, $key, true ) );
		}

		// A numeric string stays a string (it used to be JSON-decoded to an int).
		$meta_id = add_user_meta( $user_id, 'nickname', 'x' );
		$this->assertNotWPError( $this->write_profile_field( $user_id, 'nickname', '123', $meta_id ) );
		$this->assertSame( '123', get_metadata_by_mid( 'user', $meta_id )->meta_value );
	}

	/**
	 * RSCC-012: other keys written by an administrator keep the old JSON handling.
	 *
	 * @testdox RSCC-012 - Administrator writes to other keys are unchanged
	 */
	public function test_administrator_other_keys_unchanged() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = $this->write_profile_field( $user_id, 'reepay_custom', '{"a":1}' );
		$this->assertNotWPError( $result );
		$this->assertSame( array( 'a' => 1 ), get_user_meta( $user_id, 'reepay_custom', true ) );
	}

	/**
	 * RSCC-012 review finding: apostrophes, quotes and backslashes are stored as sent, not with an added backslash.
	 *
	 * @testdox RSCC-012 - Apostrophes and quotes are stored without an added backslash
	 */
	public function test_profile_fields_keep_apostrophes_quotes_and_backslashes() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$cases = array(
			'last_name'  => "O'Brien",
			'first_name' => 'Say "hi" a\\b',
			'nickname'   => 'Tom & Jerry',
		);
		// Nickname is stored HTML-encoded by the WordPress field filters, as in core.
		$expected = array(
			'last_name'  => "O'Brien",
			'first_name' => 'Say "hi" a\\b',
			'nickname'   => 'Tom &amp; Jerry',
		);

		foreach ( $cases as $key => $value ) {
			delete_user_meta( $user_id, $key );

			$added = $this->write_profile_field( $user_id, $key, $value );
			$this->assertNotWPError( $added );
			$this->assertSame( $expected[ $key ], get_user_meta( $user_id, $key, true ), "POST $key" );
			$this->assertSame( $expected[ $key ], $added->get_data()['value'], "POST response $key" );

			$edited = $this->write_profile_field( $user_id, $key, $value, $added->get_data()['id'] );
			$this->assertNotWPError( $edited );
			$this->assertSame( $expected[ $key ], get_user_meta( $user_id, $key, true ), "PUT $key" );
			$this->assertSame( $expected[ $key ], $edited->get_data()['value'], "PUT response $key" );
		}
	}
}
