<?php
/**
 * Controller meta fields
 *
 * @package Reepay\Checkout\Api\Controller
 */

namespace Reepay\Checkout\Api\Controller;

use Reepay\Checkout\Utils\LoggingTrait;
use Reepay\Checkout\Utils\MetaField;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Refund;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class controller
 *
 * @package Reepay\Checkout\Api\Controller
 */
class MetaFieldsController extends WP_REST_Controller {
	use LoggingTrait;

	/**
	 * Logging source for woo logs
	 *
	 * @var string
	 */
	private string $logging_source        = 'reepay_security_event';
	public const EXCLUDE_KEYS_META_FIELDS = array(
		'_edit_lock',
		'wp_user_level',
		'dismissed_wp_pointers',
		'wp_capabilities',
		'use_ssl',
		'admin_color',
		'comment_shortcuts',
		'syntax_highlighting',
		'rich_editing',
		'show_admin_bar_front',
		// Additional security-sensitive keys.
		'wp_user-settings',
		'wp_user-settings-time',
		'wp_dashboard_quick_press_last_post_id',
		'managenav-menuscolumnshidden',
		'metaboxhidden_nav-menus',
		'nav_menu_recently_edited',
		'session_tokens',
		// Plugin-specific sensitive keys (using prefixes for pattern matching).
		'billing_',
		'shipping_',
		'_woocommerce_',
		'_customer_',
	);

	/**
	 * User meta keys a non-administrator may read, add, edit or delete on their own user.
	 */
	public const NON_ADMIN_USER_META_KEYS = array(
		'locale',
		'description',
		'nickname',
		'first_name',
		'last_name',
	);

	/**
	 * Longest value stored for each allow-listed user key (RSCC-012).
	 */
	public const NON_ADMIN_USER_META_MAX_LENGTH = array(
		'locale'      => 255,
		'description' => 1000,
		'nickname'    => 255,
		'first_name'  => 255,
		'last_name'   => 255,
	);

	public const ORDER_ROUTE = 'shop_order';
	public const USER_ROUTE  = 'user';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'billwerk/v1';
		$this->rest_base = 'meta-fields';
	}

	/**
	 * Register API routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::ORDER_ROUTE . '/(?P<post_id>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order_meta_fields' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::ORDER_ROUTE . '/(?P<post_id>.+)/(?P<field_id>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_order_meta_field' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'post_id'  => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'field_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::ORDER_ROUTE . '/(?P<post_id>.+)/(?P<field_id>.+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'edit_order_meta_field' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'post_id'  => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'field_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'key'      => array(
							'required' => true,
							'type'     => 'string',
						),
						'value'    => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::ORDER_ROUTE . '/(?P<post_id>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_order_meta_field' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::USER_ROUTE . '/(?P<user_id>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_user_meta_field' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'user_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::USER_ROUTE . '/(?P<user_id>.+)/(?P<field_id>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_user_meta_field' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'user_id'  => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'field_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::USER_ROUTE . '/(?P<user_id>.+)/(?P<field_id>.+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'edit_user_meta_field' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'user_id'  => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'field_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'key'      => array(
							'required' => true,
							'type'     => 'string',
						),
						'value'    => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/" . self::USER_ROUTE . '/(?P<user_id>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user_meta_fields' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'user_id' => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Edit order meta field by ID
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function edit_order_meta_field( WP_REST_Request $request ) {
		$id       = (int) $request['post_id'];
		$field_id = (int) $request['field_id'];
		$key      = (string) $request['key'];
		$value    = (string) $request['value'];

		if ( ! isset( $field_id ) || ! isset( $key ) || ! isset( $value ) ) {
			return new WP_Error( 'incorrect_body', esc_html__( 'Incorrect body.', 'reepay-checkout-gateway' ) );
		}

		$order = wc_get_order( $id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found order.', 'reepay-checkout-gateway' ) );
		}

		$decoded_value = json_decode( $value, true );
		if ( null !== $decoded_value ) {
			$order->update_meta_data( $key, $decoded_value, $field_id );
		} else {
			$order->update_meta_data( $key, $value, $field_id );
		}
		$order->save_meta_data();

		return rest_ensure_response( json_decode( (string) $request->get_body(), true ) );
	}

	/**
	 * Add order meta field
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function add_order_meta_field( WP_REST_Request $request ) {
		$id    = (int) $request['post_id'];
		$key   = $request['key'];
		$value = $request['value'];

		if ( ! isset( $key ) || ! isset( $value ) ) {
			return new WP_Error( 'incorrect_body', esc_html__( 'Incorrect body.', 'reepay-checkout-gateway' ) );
		}

		$order = wc_get_order( $id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found order.', 'reepay-checkout-gateway' ) );
		}

		$old_meta = $order->get_meta_data();

		$decoded_value = json_decode( $value, true );
		if ( null !== $decoded_value ) {
			$order->add_meta_data( $key, $decoded_value );
		} else {
			$order->add_meta_data( $key, $value );
		}

		$order->add_meta_data( $key, $value );
		$order->save_meta_data();
		$new_meta = $order->get_meta_data();

		$new_field = null;

		foreach ( $new_meta as $meta_item ) {
			if ( ! in_array( $meta_item, $old_meta, true ) ) {
				$new_field = $meta_item;
				break;
			}
		}

		if ( ! is_a( $new_field, 'WC_Meta_Data' ) ) {
			return new WP_Error( 'creating_meta_field', esc_html__( 'Error creating meta field.', 'reepay-checkout-gateway' ) );
		}

		$field_data = $new_field->get_data();
		if ( is_array( $field_data['value'] ) ) {
			$field_data['value'] = wp_json_encode( $field_data['value'] );
		}

		return rest_ensure_response( $field_data );
	}

	/**
	 * Delete order meta field by ID
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_order_meta_field( WP_REST_Request $request ) {
		$responses = array();
		$id        = (int) $request['post_id'];
		$field_id  = (int) $request['field_id'];
		$order     = wc_get_order( $id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found order.', 'reepay-checkout-gateway' ) );
		}

		$order->delete_meta_data_by_mid( $field_id );
		$order->save_meta_data();

		return rest_ensure_response( $responses );
	}

	/**
	 * Retrieves order meta fields.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_order_meta_fields( WP_REST_Request $request ) {
		$responses = array();
		$id        = (int) $request['post_id'];
		$order     = wc_get_order( $id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found order.', 'reepay-checkout-gateway' ) );
		}

		$meta_data = $order->get_meta_data();

		foreach ( $meta_data as $item ) {
			if ( class_exists( 'WC_Meta_Data' ) && is_a( $item, 'WC_Meta_Data' ) ) {
				$data = $item->get_data();
				if ( in_array( $data['key'], self::EXCLUDE_KEYS_META_FIELDS, true ) ) {
					continue;
				}
				$response    = $this->prepare_item_for_response( $data, $request );
				$responses[] = $this->prepare_response_for_collection( $response );
			}
		}

		return rest_ensure_response( $responses );
	}

	/**
	 * Retrieves user meta fields.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_user_meta_fields( WP_REST_Request $request ) {
		$responses = array();
		// Read user_id from the URL route only, matching get_items_permissions_check() (BWPM-307).
		// $request['user_id'] reads query/body params before the route, letting a request whose
		// route names the caller's own account but whose query/body names another user's ID pass
		// the permission check ("me") and then act on the victim here.
		$id   = (int) $request->get_url_params()['user_id'];
		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found user.', 'reepay-checkout-gateway' ) );
		}

		$meta_data = get_user_meta( $id );
		$is_admin  = current_user_can( 'manage_options' );

		foreach ( $meta_data as $key => $item ) {
			if ( in_array( $key, self::EXCLUDE_KEYS_META_FIELDS, true ) ) {
				continue;
			}
			// Non-administrators see the same keys they are allowed to change.
			if ( ! $is_admin && ! in_array( $key, self::NON_ADMIN_USER_META_KEYS, true ) ) {
				continue;
			}
			$fields = MetaField::get_raw_user_meta( $id, $key );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					$data        = array(
						'id'    => (int) $field->umeta_id,
						'key'   => $field->meta_key,
						'value' => $field->meta_value,
					);
					$response    = $this->prepare_item_for_response( $data, $request );
					$responses[] = $this->prepare_response_for_collection( $response );
				}
			}
		}

		return rest_ensure_response( $responses );
	}

	/**
	 * Add user meta field
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function add_user_meta_field( WP_REST_Request $request ) {
		// Read user_id from the URL route only; see get_user_meta_fields() for why (BWPM-307).
		$id    = (int) $request->get_url_params()['user_id'];
		$key   = $request['key'];
		$value = $request['value'];

		if ( ! isset( $key ) || ! isset( $value ) ) {
			return new WP_Error( 'incorrect_body', esc_html__( 'Incorrect body.', 'reepay-checkout-gateway' ) );
		}

		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found user.', 'reepay-checkout-gateway' ) );
		}

		if ( in_array( $key, self::NON_ADMIN_USER_META_KEYS, true ) ) {
			// Profile fields are plain strings, one row per key (RSCC-012).
			$value = $this->sanitise_profile_value( $key, $value, $id );
			if ( is_wp_error( $value ) ) {
				return $value;
			}

			// add_user_meta() expects slashed data and unslashes it.
			$meta_id = add_user_meta( $id, $key, wp_slash( $value ), true );
			if ( false === $meta_id ) {
				return new WP_Error(
					'meta_key_exists',
					esc_html__( 'This metadata key already has a value; edit the existing one.', 'reepay-checkout-gateway' ),
					array( 'status' => 409 )
				);
			}

			return rest_ensure_response(
				array(
					'id'    => $meta_id,
					'key'   => $key,
					'value' => $value,
				)
			);
		}

		$decoded_value = json_decode( $value, true );
		if ( null !== $decoded_value ) {
			$meta_id = add_user_meta( $id, $key, $decoded_value );
		} else {
			$meta_id = add_user_meta( $id, $key, $value );
		}
		if ( false === $meta_id ) {
			return new WP_Error( 'creating_meta_field', esc_html__( 'Error creating meta field.', 'reepay-checkout-gateway' ) );
		}

		$response = array(
			'id'    => $meta_id,
			'key'   => $key,
			'value' => $decoded_value ? wp_json_encode( $decoded_value ) : $value,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Delete user meta field by ID
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_user_meta_field( WP_REST_Request $request ) {
		// Read user_id from the URL route only; see get_user_meta_fields() for why (BWPM-307).
		// The ownership check below compares against this value, so sourcing it correctly is what
		// makes that check actually mean something.
		$id = (int) $request->get_url_params()['user_id'];
		// Same for field_id: get_items_permissions_check() checks the key of the row named in the URL,
		// so a query or body field_id must not select a different row here (RSCC-011).
		$field_id = (int) $request->get_url_params()['field_id'];
		$user     = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found order.', 'reepay-checkout-gateway' ) );
		}

		// Verify the field_id actually belongs to the user in the route before deleting it,
		// matching the same check already done in edit_user_meta_field().
		$existing_meta = get_metadata_by_mid( 'user', $field_id );

		if ( ! $existing_meta || (int) $existing_meta->user_id !== $id ) {
			return new WP_Error(
				'invalid_meta_field',
				esc_html__( 'Invalid metadata field ID for this user.', 'reepay-checkout-gateway' ),
				array( 'status' => 400 )
			);
		}

		// Reject deleting protected keys (e.g. wp_capabilities), matching the check
		// edit_user_meta_field() already does. Validate the row's actual key, not user input —
		// deletion is identified by field_id, so there is no attacker-supplied key to check here.
		$security_violation = $this->validate_metadata_key_security( $existing_meta->meta_key );
		if ( $security_violation ) {
			return new WP_Error(
				$security_violation['error_code'],
				$security_violation['message'],
				array( 'status' => 403 )
			);
		}

		$result = delete_metadata_by_mid( 'user', $field_id );
		if ( false === $result ) {
			return new WP_Error( 'deleting_field', esc_html__( 'Error deleting meta field.', 'reepay-checkout-gateway' ) );
		}

		return rest_ensure_response( array() );
	}

	/**
	 * Edit user meta field by ID
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function edit_user_meta_field( WP_REST_Request $request ) {
		// Read user_id from the URL route only; see get_user_meta_fields() for why (BWPM-307).
		// The ownership check below compares against this value, so sourcing it correctly is what
		// makes that check actually mean something.
		$id       = (int) $request->get_url_params()['user_id'];
		$field_id = (int) $request->get_url_params()['field_id'];
		$key      = (string) $request['key'];
		$raw      = $request['value'];
		$value    = is_scalar( $raw ) ? (string) $raw : '';

		if ( ! isset( $field_id ) || ! isset( $key ) || ! isset( $value ) ) {
			return new WP_Error( 'incorrect_body', esc_html__( 'Incorrect body.', 'reepay-checkout-gateway' ) );
		}

		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found user.', 'reepay-checkout-gateway' ) );
		}

		// CRITICAL SECURITY CHECK: Consolidated metadata key validation.
		$security_violation = $this->validate_metadata_key_security( $key );
		if ( $security_violation ) {
			return new WP_Error(
				$security_violation['error_code'],
				$security_violation['message'],
				array( 'status' => 403 )
			);
		}

		// Verify the field_id belongs to the specified user and key.
		// Use WordPress metadata functions instead of direct database queries.
		$existing_meta = get_metadata_by_mid( 'user', $field_id );

		// Verify the metadata belongs to the correct user and has the correct key.
		if ( ! $existing_meta || (int) $existing_meta->user_id !== $id || $existing_meta->meta_key !== $key ) {
			$existing_meta = null;
		}

		if ( ! $existing_meta ) {
			return new WP_Error(
				'invalid_meta_field',
				esc_html__( 'Invalid metadata field ID for this user and key.', 'reepay-checkout-gateway' ),
				array( 'status' => 400 )
			);
		}

		$is_profile_key = in_array( $key, self::NON_ADMIN_USER_META_KEYS, true );
		if ( $is_profile_key ) {
			// Profile fields are plain strings, stored the way WordPress would store them (RSCC-012).
			$value = $this->sanitise_profile_value( $key, $raw, $id );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}

		// Log the metadata change for audit purposes.
		$this->log_security_event(
			array(
				'user_id'      => $id,
				'set_meta_key' => $key,
				'old_value'    => $existing_meta ? $existing_meta->meta_value : '',
				'new_value'    => $value,
				'modified_by'  => get_current_user_id(),
				'timestamp'    => current_time( 'mysql' ),
				'ip_address'   => $this->get_client_ip(),
			)
		);

		if ( $is_profile_key ) {
			update_metadata_by_mid( 'user', $field_id, $value, $key );

			// Keep the existing response shape: the decoded request body.
			return rest_ensure_response( json_decode( (string) $request->get_body(), true ) );
		}

		$decoded_value = json_decode( $value, true );
		if ( null !== $decoded_value ) {
			update_metadata_by_mid( 'user', $field_id, $decoded_value, $key );
		} else {
			update_metadata_by_mid( 'user', $field_id, $value, $key );
		}

		return rest_ensure_response( json_decode( (string) $request->get_body(), true ) );
	}

	/**
	 * Validate and sanitise a value for one of the allow-listed user profile keys (RSCC-012).
	 *
	 * @param string $key     Allow-listed key.
	 * @param mixed  $value   Value from the request.
	 * @param int    $user_id User the value is stored for.
	 *
	 * @return string|WP_Error The value to store, or a 400 error.
	 */
	private function sanitise_profile_value( string $key, $value, int $user_id ) {
		$invalid = new WP_Error(
			'rest_invalid_meta_value',
			esc_html__( 'Invalid value for this metadata key.', 'reepay-checkout-gateway' ),
			array( 'status' => 400 )
		);

		if ( ! is_string( $value ) || mb_strlen( $value ) > self::NON_ADMIN_USER_META_MAX_LENGTH[ $key ] ) {
			return $invalid;
		}

		if ( 'locale' === $key ) {
			// An empty locale means "site default" in WordPress.
			$allowed = array_merge( array( '', 'en_US' ), get_available_languages() );

			return in_array( $value, $allowed, true ) ? $value : $invalid;
		}

		// WordPress field filters work on slashed text and return slashed text (core unslashes it again
		// when it stores the value), so slash going in and unslash the result.
		return wp_unslash( (string) sanitize_user_field( $key, wp_slash( $value ), $user_id, 'db' ) );
	}

	/**
	 * Consolidated metadata key security validation
	 *
	 * @param string $key The metadata key to validate.
	 * @return array|null Security violation details or null if key is safe.
	 */
	private function validate_metadata_key_security( string $key ): ?array {
		// Check against explicit exclusion list first (most specific).
		if ( in_array( $key, self::EXCLUDE_KEYS_META_FIELDS, true ) ) {
			return array(
				'event_type' => 'excluded_key_access_attempt',
				'error_code' => 'rest_forbidden_excluded_key',
				'error_type' => 'excluded_key_blocked',
				'message'    => esc_html__( 'This metadata key is protected and cannot be modified.', 'reepay-checkout-gateway' ),
				'reason'     => 'key_in_exclusion_list',
			);
		}

		// Check for prefix-based patterns (catches keys not in exclusion list).
		$dangerous_patterns = array(
			'wp_'           => 'WordPress system key',
			'_wp_'          => 'WordPress private key',
			'capabilities'  => 'User capability key',
			'user_level'    => 'User level key',
			'session_'      => 'Session-related key',
			'_woocommerce_' => 'WooCommerce sensitive key',
			'billing_'      => 'Billing information key',
			'shipping_'     => 'Shipping information key',
		);

		foreach ( $dangerous_patterns as $pattern => $description ) {
			if ( strpos( $key, $pattern ) !== false ) {
				return array(
					'event_type'  => 'pattern_based_key_access_attempt',
					'error_code'  => 'rest_forbidden_pattern_key',
					'error_type'  => 'dangerous_pattern_blocked',
					'message'     => esc_html__( 'This type of metadata key cannot be modified via this endpoint.', 'reepay-checkout-gateway' ),
					'reason'      => "matches_pattern_{$pattern}",
					'pattern'     => $pattern,
					'description' => $description,
				);
			}
		}

		// Key is safe.
		return null;
	}

	/**
	 * Log security-related metadata operations
	 *
	 * @param array $log_data Log data array.
	 */
	private function log_security_event( $log_data ) {
		// A logged-in user can trigger denied attempts at will, so those are limited. Changes made by the user are always logged.
		if ( 'user_metadata_modification' !== ( $log_data['event_type'] ?? 'user_metadata_modification' ) && ! $this->may_log_denied_attempt() ) {
			return;
		}

		// Ensure required fields have defaults.
		$defaults = array(
			'event_type'   => $log_data['event_type'] ?? 'user_metadata_modification',
			'user_id'      => $log_data['user_id'] ?? 0,
			'set_meta_key' => $log_data['set_meta_key'] ?? 'unknown',
			'timestamp'    => $log_data['timestamp'] ?? current_time( 'mysql' ),
			'ip_address'   => $log_data['ip_address'] ?? $this->get_client_ip(),
			'user_agent'   => $log_data['user_agent'] ?? $this->get_user_agent(),
			'request_uri'  => $log_data['request_uri'] ?? $this->get_request_uri(),
		);

		// Merge provided data with defaults.
		$log_entry = array_merge( $defaults, $log_data );

		// These values come from the request: keep them on one line and short.
		$log_entry['user_id']      = (int) $log_entry['user_id'];
		$log_entry['set_meta_key'] = $this->clean_log_value( $log_entry['set_meta_key'], 100 );
		$log_entry['user_agent']   = $this->clean_log_value( $log_entry['user_agent'], 200 );
		$log_entry['request_uri']  = $this->clean_log_value( $log_entry['request_uri'], 200 );
		foreach ( array( 'old_value', 'new_value' ) as $value_key ) {
			if ( isset( $log_entry[ $value_key ] ) ) {
				$log_entry[ $value_key ] = $this->clean_log_value( is_scalar( $log_entry[ $value_key ] ) ? $log_entry[ $value_key ] : wp_json_encode( $log_entry[ $value_key ] ), 100 );
			}
		}

		// Add context information for debugging (only in development/debug mode).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_entry['context'] = array(
				'function' => __FUNCTION__,
				'class'    => __CLASS__,
				'line'     => __LINE__,
			);
		}

		// Generate human-readable summary based on event type.
		$summary = $this->generate_security_event_summary( $log_entry );

		// Combine structured data and human-readable summary into a single log entry.
		$combined_log_entry = array(
			'summary' => $summary,
			'details' => $log_entry,
		);

		// Log using WooCommerce logging system with 'reepay_security_event' source (single call).
		$this->log( $combined_log_entry );
	}

	/**
	 * Generate human-readable summary for security events
	 *
	 * @param array $log_entry Complete log entry data.
	 * @return string Human-readable summary.
	 */
	private function generate_security_event_summary( $log_entry ) {
		$event_type = $log_entry['event_type'];
		$user_id    = $log_entry['user_id'];
		$meta_key   = $log_entry['set_meta_key'];
		$ip_address = $log_entry['ip_address'];

		switch ( $event_type ) {
			case 'unauthorized_metadata_key_access':
				$attempted_by = $log_entry['attempted_by'] ?? 'unknown';
				return sprintf(
					'SECURITY ALERT: User ID %d attempted to modify unauthorized metadata key "%s" for user %d [IP: %s, Attempted by: %d]',
					$user_id,
					$meta_key,
					$user_id,
					$ip_address,
					$attempted_by
				);

			case 'user_metadata_modification':
				$modified_by = $log_entry['modified_by'] ?? 'unknown';
				$old_value   = isset( $log_entry['old_value'] ) ?
					( is_string( $log_entry['old_value'] ) ? substr( $log_entry['old_value'], 0, 100 ) : wp_json_encode( $log_entry['old_value'] ) ) :
					'unknown';
				$new_value   = isset( $log_entry['new_value'] ) ?
					( is_string( $log_entry['new_value'] ) ? substr( $log_entry['new_value'], 0, 100 ) : wp_json_encode( $log_entry['new_value'] ) ) :
					'unknown';

				return sprintf(
					'Security Event: User %d (modified by %d) changed metadata "%s" for user %d from "%s" to "%s" [IP: %s]',
					$user_id,
					$modified_by,
					$meta_key,
					$user_id,
					$old_value,
					$new_value,
					$ip_address
				);

			default:
				return sprintf(
					'Security Event [%s]: User %d, Meta Key "%s" [IP: %s]',
					$event_type,
					$user_id,
					$meta_key,
					$ip_address
				);
		}
	}

	/**
	 * Checks if a given request has access to user metadata operations.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		// Branch on the URL route params only, not WP_REST_Request's ArrayAccess (which also
		// reads query/body params). Using isset( $request['user_id'] ) let a forged ?user_id=
		// query string on an order route reroute the request into the user-metadata branch,
		// bypassing the edit_shop_orders check below entirely.
		$url_params = $request->get_url_params();

		// For user metadata operations, require manage_options capability.
		if ( isset( $url_params['user_id'] ) ) {
			$user_id = (int) $url_params['user_id'];

			// User routes need a logged-in caller and a real user id. Without this an anonymous caller
			// (id 0) matches the "own user" comparison below and can write to the security log.
			if ( $user_id <= 0 || ! is_user_logged_in() ) {
				return new WP_Error(
					'rest_forbidden',
					esc_html__( 'Sorry, you are not allowed to do that.', 'reepay-checkout-gateway' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			// Only administrators can modify user metadata.
			// OR users can modify their own non-sensitive metadata.
			if ( ! current_user_can( 'manage_options' ) ) {
				// Allow users to modify only their own metadata.
				if ( get_current_user_id() !== $user_id ) {
					return new WP_Error(
						'rest_forbidden',
						esc_html__( 'You cannot modify other users\' metadata.', 'reepay-checkout-gateway' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// For non-admin users, only allow specific safe metadata keys.
				if ( isset( $request['key'] ) ) {
					$allowed_keys = self::NON_ADMIN_USER_META_KEYS;

					if ( ! in_array( $request['key'], $allowed_keys, true ) ) {
						// Log unauthorized metadata key access attempt.
						$this->log_security_event(
							array(
								'event_type'   => 'unauthorized_metadata_key_access',
								'user_id'      => $user_id,
								'set_meta_key' => $request['key'],
								'attempted_by' => get_current_user_id(),
								'timestamp'    => current_time( 'mysql' ),
								'ip_address'   => $this->get_client_ip(),
								'user_agent'   => $this->get_user_agent(),
								'request_uri'  => $this->get_request_uri(),
								'error_type'   => 'forbidden_meta_key',
								'allowed_keys' => $allowed_keys,
							)
						);

						return new WP_Error(
							'rest_forbidden_meta_key',
							esc_html__( 'You cannot modify this metadata key.', 'reepay-checkout-gateway' ),
							array( 'status' => rest_authorization_required_code() )
						);
					}
				}

				// DELETE and PUT name the row by id, not by key. Check the key stored in that row.
				if ( isset( $url_params['field_id'] ) ) {
					$stored_row = get_metadata_by_mid( 'user', (int) $url_params['field_id'] );

					if ( $stored_row && (int) $stored_row->user_id === $user_id && ! in_array( $stored_row->meta_key, self::NON_ADMIN_USER_META_KEYS, true ) ) {
						$this->log_security_event(
							array(
								'event_type'   => 'unauthorized_metadata_key_access',
								'user_id'      => $user_id,
								'set_meta_key' => $stored_row->meta_key,
								'attempted_by' => get_current_user_id(),
								'timestamp'    => current_time( 'mysql' ),
								'ip_address'   => $this->get_client_ip(),
								'user_agent'   => $this->get_user_agent(),
								'request_uri'  => $this->get_request_uri(),
								'error_type'   => 'forbidden_meta_key',
								'allowed_keys' => self::NON_ADMIN_USER_META_KEYS,
							)
						);

						return new WP_Error(
							'rest_forbidden_meta_key',
							esc_html__( 'You cannot modify this metadata key.', 'reepay-checkout-gateway' ),
							array( 'status' => rest_authorization_required_code() )
						);
					}
				}
			}

			// Consolidated security check: Block protected keys for ALL users at permission level.
			if ( isset( $request['key'] ) ) {
				$key                = (string) $request['key'];
				$security_violation = $this->validate_metadata_key_security( $key );

				if ( $security_violation ) {
					// Log security violation attempt.
					$this->log_security_event(
						array(
							'event_type'       => $security_violation['event_type'],
							'user_id'          => $user_id,
							'set_meta_key'     => $key,
							'attempted_by'     => get_current_user_id(),
							'timestamp'        => current_time( 'mysql' ),
							'ip_address'       => $this->get_client_ip(),
							'user_agent'       => $this->get_user_agent(),
							'request_uri'      => $this->get_request_uri(),
							'error_type'       => $security_violation['error_type'],
							'violation_reason' => $security_violation['reason'],
						)
					);

					return new WP_Error(
						$security_violation['error_code'],
						$security_violation['message'],
						array( 'status' => rest_authorization_required_code() )
					);
				}
			}

			return true;
		}

		// For order metadata operations, require order-management capability. 'edit_posts'/
		// 'delete_posts' are generic blog capabilities every Contributor has by default and
		// are unrelated to order access; 'edit_shop_orders' is the capability WooCommerce
		// grants only to Shop Managers/Administrators (see OrderFlow\OrderCapture for the
		// same convention elsewhere in this plugin).
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You cannot view the post resource.', 'reepay-checkout-gateway' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Prepares the item for the REST response.
	 *
	 * @param array           $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$schema  = $this->get_item_schema();
		$data    = array();

		if ( isset( $schema['properties']['id'] ) && isset( $item['id'] ) ) {
			$data['id'] = $item['id'];
		}
		if ( isset( $schema['properties']['key'] ) ) {
			$data['key'] = $item['key'];
		}
		if ( isset( $schema['properties']['value'] ) ) {
			$value = $item['value'];
			if ( is_serialized( $value ) ) {
				// Disallow all classes so untrusted meta values can never be unserialized into
				// PHP objects (CVE-2026-118076 - PHP Object Injection). The @ matches
				// WordPress core's own maybe_unserialize(), guarding against a malformed
				// string that passed is_serialized()'s format check but still fails to parse.
				$unserialized_value = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- allowed_classes is false, so no object can ever be instantiated; this is the fix for CVE-2026-118076, not the vulnerability.
				if ( false !== $unserialized_value || 'b:0;' === trim( $value ) ) {
					$value = $unserialized_value;
				}
			}
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}
			$data['value'] = $value;
		}
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		return rest_ensure_response( $data );
	}

	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema(): array {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'meta_field',
			'type'       => 'object',
			'properties' => array(
				'id'    => array(
					'description' => 'Unique identifier.',
					'context'     => array( 'view' ),
					'type'        => 'integer',
				),
				'key'   => array(
					'description' => 'Key',
					'context'     => array( 'view' ),
					'type'        => 'string',
				),
				'value' => array(
					'description' => 'Value',
					'context'     => array( 'view' ),
					'type'        => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the client IP address.
	 *
	 * Only REMOTE_ADDR is used: forwarding headers are set by the caller, so a log line built from
	 * them can be forged. Behind a reverse proxy this is the proxy address.
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip(): string {
		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}

	/**
	 * Whether a denied attempt may be written to the security log.
	 *
	 * WordPress runs the permission callback again to build the Allow header, which would log the same
	 * request several times; that run is skipped. Otherwise one entry per user every 10 seconds.
	 *
	 * @return bool
	 */
	private function may_log_denied_attempt(): bool {
		if ( doing_filter( 'rest_post_dispatch' ) ) {
			return false;
		}

		$rate_limit_id = 'reepay_meta_denied_' . get_current_user_id();
		if ( \WC_Rate_Limiter::retried_too_soon( $rate_limit_id ) ) {
			return false;
		}

		\WC_Rate_Limiter::set_rate_limit( $rate_limit_id, 10 );

		return true;
	}

	/**
	 * Returns a request-derived value as a short single line for the log.
	 *
	 * @param mixed $value      Value to log.
	 * @param int   $max_length Maximum number of characters.
	 *
	 * @return string
	 */
	private function clean_log_value( $value, int $max_length ): string {
		if ( ! is_scalar( $value ) ) {
			return 'invalid';
		}

		return mb_substr( wc_clean( (string) $value ), 0, $max_length );
	}

	/**
	 * Get user agent safely
	 *
	 * @return string User agent string
	 */
	private function get_user_agent(): string {
		return ! empty( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: 'unknown';
	}

	/**
	 * Get request URI safely
	 *
	 * @return string Request URI
	 */
	private function get_request_uri(): string {
		return ! empty( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: 'unknown';
	}
}
