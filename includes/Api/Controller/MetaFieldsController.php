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
	private string $logging_source = 'reepay_security_event';
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
		'dismissed_wp_pointers',
		// Additional security-sensitive keys
		'wp_user-settings',
		'wp_user-settings-time',
		'wp_dashboard_quick_press_last_post_id',
		'managenav-menuscolumnshidden',
		'metaboxhidden_nav-menus',
		'nav_menu_recently_edited',
		'session_tokens',
		'wp_user_level',
		// Plugin-specific sensitive keys
		'billing_',
		'shipping_',
		'_woocommerce_',
		'_customer_',
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

		return rest_ensure_response( json_decode( $request->get_body(), true ) );
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
		$id        = (int) $request['user_id'];
		$user      = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found user.', 'reepay-checkout-gateway' ) );
		}

		$meta_data = get_user_meta( $id );

		foreach ( $meta_data as $key => $item ) {
			if ( in_array( $key, self::EXCLUDE_KEYS_META_FIELDS, true ) ) {
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
		$id    = (int) $request['user_id'];
		$key   = $request['key'];
		$value = $request['value'];

		if ( ! isset( $key ) || ! isset( $value ) ) {
			return new WP_Error( 'incorrect_body', esc_html__( 'Incorrect body.', 'reepay-checkout-gateway' ) );
		}

		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found user.', 'reepay-checkout-gateway' ) );
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
		$id       = (int) $request['user_id'];
		$field_id = (int) $request['field_id'];
		$user     = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found order.', 'reepay-checkout-gateway' ) );
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
		$id       = (int) $request['user_id'];
		$field_id = (int) $request['field_id'];
		$key      = (string) $request['key'];
		$value    = (string) $request['value'];

		if ( ! isset( $field_id ) || ! isset( $key ) || ! isset( $value ) ) {
			return new WP_Error( 'incorrect_body', esc_html__( 'Incorrect body.', 'reepay-checkout-gateway' ) );
		}

		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new WP_Error( 'not_found', esc_html__( 'Not found user.', 'reepay-checkout-gateway' ) );
		}

		// CRITICAL SECURITY CHECK: Validate metadata key against exclusion list
		if ( in_array( $key, self::EXCLUDE_KEYS_META_FIELDS, true ) ) {
			return new WP_Error(
				'rest_forbidden_meta_key',
				esc_html__( 'This metadata key cannot be modified.', 'reepay-checkout-gateway' ),
				array( 'status' => 403 )
			);
		}

		// Additional security check for capability-related keys
		if ( strpos( $key, 'capabilities' ) !== false ||
			 strpos( $key, 'user_level' ) !== false ||
			 strpos( $key, 'wp_' ) === 0 ) {
			return new WP_Error(
				'rest_forbidden_capability_key',
				esc_html__( 'System metadata cannot be modified via this endpoint.', 'reepay-checkout-gateway' ),
				array( 'status' => 403 )
			);
		}

		// Verify the field_id belongs to the specified user and key
		global $wpdb;
		$existing_meta = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->usermeta} WHERE umeta_id = %d AND user_id = %d AND meta_key = %s",
			$field_id,
			$id,
			$key
		));

		if ( ! $existing_meta ) {
			return new WP_Error(
				'invalid_meta_field',
				esc_html__( 'Invalid metadata field ID for this user and key.', 'reepay-checkout-gateway' ),
				array( 'status' => 400 )
			);
		}

		// Log the metadata change for audit purposes
		$this->log_security_event( array(
			'user_id' => $id,
			'meta_key' => $key,
			'old_value' => $existing_meta->meta_value,
			'new_value' => $value,
			'modified_by' => get_current_user_id(),
			'timestamp' => current_time( 'mysql' ),
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
		));

		$decoded_value = json_decode( $value, true );
		if ( null !== $decoded_value ) {
			update_metadata_by_mid( 'user', $field_id, $decoded_value, $key );
		} else {
			update_metadata_by_mid( 'user', $field_id, $value, $key );
		}

		return rest_ensure_response( json_decode( $request->get_body(), true ) );
	}

	/**
	 * Log security-related metadata operations
	 *
	 * @param array $log_data Log data array
	 */
	private function log_security_event( $log_data ) {
		// Ensure required fields have defaults
		$defaults = array(
			'event_type'   => $log_data['event_type'] ?? 'user_metadata_modification',
			'user_id'      => $log_data['user_id'] ?? 0,
			'meta_key'     => $log_data['meta_key'] ?? 'unknown',
			'timestamp'    => $log_data['timestamp'] ?? current_time( 'mysql' ),
			'ip_address'   => $log_data['ip_address'] ?? ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
			'user_agent'   => $log_data['user_agent'] ?? ( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' ),
			'request_uri'  => $log_data['request_uri'] ?? ( $_SERVER['REQUEST_URI'] ?? 'unknown' ),
		);

		// Merge provided data with defaults
		$log_entry = array_merge( $defaults, $log_data );

		// Add backtrace for debugging
		$log_entry['backtrace(3)'] = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		// Log using WooCommerce logging system with 'security_event' source
		$this->log( $log_entry );

		// Generate human-readable summary based on event type
		$summary = $this->generate_security_event_summary( $log_entry );
		$this->log( $summary );
	}

	/**
	 * Generate human-readable summary for security events
	 *
	 * @param array $log_entry Complete log entry data
	 * @return string Human-readable summary
	 */
	private function generate_security_event_summary( $log_entry ) {
		$event_type = $log_entry['event_type'];
		$user_id = $log_entry['user_id'];
		$meta_key = $log_entry['meta_key'];
		$ip_address = $log_entry['ip_address'];

		switch ( $event_type ) {
			case 'unauthorized_metadata_key_access':
				$attempted_by = $log_entry['attempted_by'] ?? 'unknown';
				return sprintf(
					'SECURITY ALERT: User %d attempted to modify unauthorized metadata key "%s" for user %d [IP: %s, Attempted by: %d]',
					$user_id,
					$meta_key,
					$user_id,
					$ip_address,
					$attempted_by
				);

			case 'user_metadata_modification':
				$modified_by = $log_entry['modified_by'] ?? 'unknown';
				$old_value = isset( $log_entry['old_value'] ) ?
					( is_string( $log_entry['old_value'] ) ? substr( $log_entry['old_value'], 0, 100 ) : wp_json_encode( $log_entry['old_value'] ) ) :
					'unknown';
				$new_value = isset( $log_entry['new_value'] ) ?
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
		// For user metadata operations, require manage_options capability
		if ( isset( $request['user_id'] ) ) {
			$user_id = (int) $request['user_id'];

			// Only administrators can modify user metadata
			// OR users can modify their own non-sensitive metadata
			if ( ! current_user_can( 'manage_options' ) ) {
				// Allow users to modify only their own metadata
				if ( get_current_user_id() !== $user_id ) {
					return new WP_Error(
						'rest_forbidden',
						esc_html__( 'You cannot modify other users\' metadata.', 'reepay-checkout-gateway' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// For non-admin users, only allow specific safe metadata keys
				if ( isset( $request['key'] ) ) {
					$allowed_keys = array(
						'locale',
						'description',
						'nickname',
						'first_name',
						'last_name'
					);

					if ( ! in_array( $request['key'], $allowed_keys, true ) ) {
						// Log unauthorized metadata key access attempt
						$this->log_security_event( array(
							'event_type'   => 'unauthorized_metadata_key_access',
							'user_id'      => $user_id,
							'meta_key'     => $request['key'],
							'attempted_by' => get_current_user_id(),
							'timestamp'    => current_time( 'mysql' ),
							'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
							'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
							'request_uri'  => $_SERVER['REQUEST_URI'] ?? 'unknown',
							'error_type'   => 'forbidden_meta_key',
							'allowed_keys' => $allowed_keys
						));

						return new WP_Error(
							'rest_forbidden_meta_key',
							esc_html__( 'You cannot modify this metadata key.', 'reepay-checkout-gateway' ),
							array( 'status' => rest_authorization_required_code() )
						);
					}
				}
			}

			return true;
		}

		// For order metadata operations, require appropriate post capabilities
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'delete_posts' ) ) {
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
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$value = unserialize( $value );
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
}
