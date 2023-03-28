<?php

namespace Reepay\Checkout;

defined( 'ABSPATH' ) || exit();

class Statistics {
	use LoggingTrait;

	/**
	 * @var Statistics
	 */
	private static $instance;

	/**
	 * @var string
	 */
	private static $plugin_file;

	/**
	 * @var string
	 */
	private $logging_source = 'reepay-statistics';

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
	 * @return Statistics
	 */
	public static function get_instance( $plugin_file = null ) {
		if ( ! is_null( $plugin_file ) ) {
			self::$plugin_file = $plugin_file;
		}

		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function send_event( $event ) {
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

			$this->log( sprintf( 'Request: %s', json_encode( $params, JSON_PRETTY_PRINT ) ) );

			$url = 'https://hook.reepay.integromat.celonis.com/1dndgwx6cwsvl3shsee29yyqf4d648xf';

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => json_encode( $params, JSON_PRETTY_PRINT ),
				)
			);

			$this->log( sprintf( 'Response: %s', wp_remote_retrieve_body( $response ) ) );

			return $response;
		} else {
			$this->log( 'Empty private key');
		}
		return false;
	}

	public static function plugin_deactivated() {
		self::get_instance()->send_event( 'deactivated' );
	}

	public static function plugin_deleted() {
		self::get_instance()->send_event( 'deleted' );
	}

	public static function private_key_activated() {
		self::get_instance()->send_event( 'activated' );
	}

	public static function upgrade_completed( $upgrader_object, $options ) {
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( strpos( $plugin, self::$plugin_file ) ) {
					self::get_instance()->send_event( 'updated' );
				}
			}
		}
	}
}
