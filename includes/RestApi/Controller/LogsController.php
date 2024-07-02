<?php
/**
 * Controller logs
 *
 * @package Reepay\Checkout\RestApi\Controller
 */

namespace Reepay\Checkout\RestApi\Controller;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class controller
 *
 * @package Reepay\Checkout\RestApi\Controller
 */
class LogsController extends WP_REST_Controller {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'billwerk/v1';
		$this->rest_base = 'logs';
	}

	/**
	 * Register API routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/",
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/clean",
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_file_log' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Clean file log
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_file_log( WP_REST_Request $request ) {
		$log_path = (string) $request['logPath'];

		reepay()->log()->clean_file_log( $log_path );

		return rest_ensure_response( array() );
	}

	/**
	 * Retrieves files logs
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_logs( WP_REST_Request $request ) {
		$files = reepay()->log()->get_files();

		return rest_ensure_response( $files );
	}

	/**
	 * Checks if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}
