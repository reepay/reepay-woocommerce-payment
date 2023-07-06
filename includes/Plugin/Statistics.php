<?php
/**
 * Class for collecting information about plugin activations and deactivations and sending data to reepay
 *
 * @package Reepay\Checkout\Plugin
 */

namespace Reepay\Checkout\Plugin;

use Reepay\Checkout\LoggingTrait;
use WP_Error;
use WP_Upgrader;

defined( 'ABSPATH' ) || exit();

/**
 * Class Statistics
 *
 * @package Reepay\Checkout\Plugin
 */
class Statistics {
	use LoggingTrait;

	/**
	 * Class instance
	 *
	 * @var Statistics|null
	 */
	private static ?Statistics $instance = null;

	/**
	 * Path to main plugin file
	 *
	 * @var string
	 */
	private static string $plugin_file;

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private string $logging_source = 'reepay-statistics';

	/**
	 * Constructor
	 */
	private function __construct() {
		register_deactivation_hook( self::$plugin_file, array( static::class, 'plugin_deactivated' ) );
		register_uninstall_hook( self::$plugin_file, array( static::class, 'plugin_deleted' ) );
		register_activation_hook( self::$plugin_file, array( static::class, 'private_key_activated' ) );
		add_action( 'upgrader_process_complete', array( static::class, 'upgrade_completed' ), 10, 2 );
	}

	/**
	 * Get class instance. Use it instead of new
	 *
	 * @param string $plugin_file path to main plugin file.
	 *
	 * @return Statistics
	 */
	public static function get_instance( $plugin_file = null ): Statistics {
		if ( ! is_null( $plugin_file ) ) {
			self::$plugin_file = $plugin_file;
		}

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Send event to Reepay api
	 *
	 * @param string $event event to send via api.
	 *
	 * @return array|false|WP_Error
	 */
	public function send_event( string $event ) {
		$this->log( sprintf( 'Event init: %s', $event ) );

		$key = reepay()->get_setting( 'test_mode' ) === 'yes' ? reepay()->get_setting( 'private_key_test' ) : reepay()->get_setting( 'private_key' );

		if ( ! empty( $key ) ) {
			$params = array(
				'plugin'     => 'WOOCOMMERCE',
				'version'    => reepay()->get_setting( 'plugin_version' ),
				'privatekey' => $key,
				'url'        => home_url(),
				'event'      => $event,
			);

			$this->log( sprintf( 'Request: %s', wp_json_encode( $params, JSON_PRETTY_PRINT ) ) );

			$url = 'https://hook.reepay.integromat.celonis.com/1dndgwx6cwsvl3shsee29yyqf4d648xf';

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode( $params, JSON_PRETTY_PRINT ),
				)
			);

			$this->log( sprintf( 'Response: %s', wp_remote_retrieve_body( $response ) ) );

			return $response;
		} else {
			$this->log( 'Empty private key' );
		}

		return false;
	}

	/**
	 * Send deactivated event
	 */
	public static function plugin_deactivated() {
		self::get_instance()->send_event( 'deactivated' );
	}

	/**
	 * Send deleted event
	 */
	public static function plugin_deleted() {
		self::get_instance()->send_event( 'deleted' );
	}

	/**
	 * Send activated event
	 */
	public static function private_key_activated() {
		self::get_instance()->send_event( 'activated' );
	}

	/**
	 * Send deactivated event
	 *
	 * @param WP_Upgrader $upgrader_object instance of WP_Upgrader.
	 * @param array       $options         upgrade options.
	 */
	public static function upgrade_completed( WP_Upgrader $upgrader_object, array $options ) {
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( strpos( $plugin, self::$plugin_file ) ) {
					self::get_instance()->send_event( 'updated' );
				}
			}
		}
	}
}
