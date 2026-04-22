<?php
/**
 * Controller debug
 *
 * @package Reepay\Checkout\Api\Controller
 */

namespace Reepay\Checkout\Api\Controller;

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
class DebugController extends WP_REST_Controller {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'billwerk/v1';
		$this->rest_base = 'debug';
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
					'callback'            => array( $this, 'run_debug' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Run debug diagnostics (safe — no arbitrary code execution).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function run_debug( WP_REST_Request $request ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'Debug endpoint is only available when WP_DEBUG is enabled.', 'reepay-checkout-gateway' ), array( 'status' => 403 ) );
		}

		return rest_ensure_response(
			array(
				'php_version'    => PHP_VERSION,
				'wp_version'     => get_bloginfo( 'version' ),
				'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
				'plugin_version' => defined( 'REEPAY_PLUGIN_VERSION' ) ? REEPAY_PLUGIN_VERSION : 'N/A',
				'wp_debug'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'php_memory'     => ini_get( 'memory_limit' ),
				'timestamp'      => current_time( 'mysql' ),
			)
		);
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
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post resource.', 'reepay-checkout-gateway' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}
