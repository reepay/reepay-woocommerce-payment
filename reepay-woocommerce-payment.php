<?php
/**
 * Plugin Name: Billwerk+ Payments for WooCommerce
 * Description: Get a plug-n-play payment solution for WooCommerce, that is easy to use, highly secure and is built to maximize the potential of your e-commerce.
 * Author: Billwerk+
 * Author URI: http://billwerk.plus
 * Version: 1.5.0
 * Text Domain: reepay-checkout-gateway
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 7.5.0
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\DIContainer;
use Reepay\Checkout\Gateways;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\Plugin\LifeCycle;
use Reepay\Checkout\Plugin\Statistics;
use Reepay\Checkout\Plugin\WoocommerceExists;
use Reepay\Checkout\Plugin\WoocommerceHPOS;

defined( 'ABSPATH' ) || exit();

/**
 * Main plugin class
 */
class WC_ReepayCheckout {
	/**
	 * Class instance
	 *
	 * @var WC_ReepayCheckout
	 */
	private static $instance;

	/**
	 * Settings array
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Gateways class instance
	 *
	 * @var Gateways
	 */
	private $gateways = null;

	/**
	 * Dependency injection container
	 *
	 * @var DIContainer
	 */
	private $di_container = null;

	/**
	 * Constructor
	 */
	private function __construct() {
		include_once dirname( __FILE__ ) . '/vendor/autoload.php';

		Statistics::get_instance( $this->get_setting( 'plugin_file' ) );

		new LifeCycle( $this->get_setting( 'plugin_path' ) );
		new WoocommerceExists();
		new WoocommerceHPOS();

		new Reepay\Checkout\Functions\Main();

		add_action( 'plugins_loaded', array( $this, 'include_classes' ), 0 );

		load_plugin_textdomain( 'reepay-checkout-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Get main class instance.
	 *
	 * @return WC_ReepayCheckout
	 */
	public static function get_instance(): WC_ReepayCheckout {
		static $instance_requested = false;

		if ( true === $instance_requested && is_null( self::$instance ) ) {
			$message = 'Function billwerk+ called in time of initialization main plugin class. Recursion prevented';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message .= '<br>Stack trace for debugging:<br><pre>' . print_r( //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
					true
				) . '</pre>';
			}

			wp_die( $message );
		}

		$instance_requested = true;

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get plugin or reepay checkout gateway setting
	 *
	 * @param string $name Setting key.
	 *
	 * @return string|string[]|null
	 */
	public function get_setting( string $name ) {
		if ( empty( $this->settings ) ) {
			$gateway_settings = get_option( 'woocommerce_reepay_checkout_settings' );

			if ( ! is_array( $gateway_settings ) ) {
				$gateway_settings = array();
			}

			if ( isset( $gateway_settings['private_key'] ) ) {
				$gateway_settings['private_key'] = apply_filters( 'woocommerce_reepay_private_key', $gateway_settings['private_key'] );
			}

			if ( isset( $gateway_settings['private_key_test'] ) ) {
				$gateway_settings['private_key_test'] = apply_filters( 'woocommerce_reepay_private_key_test', $gateway_settings['private_key_test'] );
			}

			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugin_data = get_plugin_data( __FILE__ );

			$this->settings = array(
				'plugin_version'          => $plugin_data['Version'],

				'plugin_file'             => __FILE__,
				'plugin_basename'         => plugin_basename( __FILE__ ),

				'plugin_url'              => plugin_dir_url( __FILE__ ),
				'plugin_path'             => plugin_dir_path( __FILE__ ),

				'templates_url'           => plugin_dir_url( __FILE__ ) . 'templates/',
				'templates_path'          => plugin_dir_path( __FILE__ ) . 'templates/',

				'css_url'                 => plugin_dir_url( __FILE__ ) . 'assets/dist/css/',
				'css_path'                => plugin_dir_path( __FILE__ ) . 'assets/dist/css/',

				'js_url'                  => plugin_dir_url( __FILE__ ) . 'assets/dist/js/',
				'js_path'                 => plugin_dir_path( __FILE__ ) . 'assets/dist/js/',

				'images_url'              => plugin_dir_url( __FILE__ ) . 'assets/images/',
				'images_path'             => plugin_dir_path( __FILE__ ) . 'assets/images/',

				'private_key'             => ! empty( $gateway_settings['private_key'] ) ? $gateway_settings['private_key'] : '',
				'private_key_test'        => ! empty( $gateway_settings['private_key_test'] ) ? $gateway_settings['private_key_test'] : '',
				'test_mode'               => ! empty( $gateway_settings['test_mode'] ) ? $gateway_settings['test_mode'] : '',
				'settle'                  => ! empty( $gateway_settings['settle'] ) ? $gateway_settings['settle'] : array(),
				'language'                => ! empty( $gateway_settings['language'] ) ? $gateway_settings['language'] : '',
				'debug'                   => ! empty( $gateway_settings['debug'] ) ? $gateway_settings['debug'] : '',
				'payment_type'            => ! empty( $gateway_settings['payment_type'] ) ? $gateway_settings['payment_type'] : '',
				'skip_order_lines'        => ! empty( $gateway_settings['skip_order_lines'] ) ? $gateway_settings['skip_order_lines'] : '',
				'enable_order_autocancel' => ! empty( $gateway_settings['enable_order_autocancel'] ) ? $gateway_settings['enable_order_autocancel'] : '',
				'is_webhook_configured'   => ! empty( $gateway_settings['is_webhook_configured'] ) ? $gateway_settings['is_webhook_configured'] : '',
				'handle_failover'         => ! empty( $gateway_settings['handle_failover'] ) ? $gateway_settings['handle_failover'] : '',
				'payment_button_text'     => ! empty( $gateway_settings['payment_button_text'] ) ? $gateway_settings['payment_button_text'] : '',

				'enable_sync'       => ! empty( $gateway_settings['enable_sync'] ) ? $gateway_settings['enable_sync'] : '',
				'status_created'    => ! empty( $gateway_settings['status_created'] ) ? $gateway_settings['status_created'] : '',
				'status_authorized' => ! empty( $gateway_settings['status_authorized'] ) ? $gateway_settings['status_authorized'] : '',
				'status_settled'    => ! empty( $gateway_settings['status_settled'] ) ? $gateway_settings['status_settled'] : '',
				'logo_height'       => ! empty( $gateway_settings['logo_height'] ) ? $gateway_settings['logo_height'] : '',
			);
		}

		return $this->settings[ $name ] ?? null;
	}

	/**
	 * Reset options from database. Now using only for testing purposes
	 */
	public function reset_settings() {
		$this->settings = null;
		$this->get_setting('');
	}

	/**
	 * Wrapper of wc_get_template function
	 *
	 * @param string $template Template name.
	 * @param  array $args     Arguments.
	 * @param  bool  $return   Return or echo template.
	 */
	public function get_template( string $template, $args = array(), $return = false ) {
		if ( $return ) {
			ob_start();
		}

		wc_get_template(
			$template,
			$args,
			'',
			$this->get_setting( 'templates_path' )
		);

		if ( $return ) {
			return ob_get_clean();
		}

		return true;
	}

	/**
	 * Set logging source.
	 *
	 * @param ReepayGateway|WC_Order|string $source Source for logging.
	 *
	 * @return Api
	 */
	public function api( $source ): Api {
		/**
		 * Api instance
		 *
		 * @var Api $api
		 */
		$api = $this->di()->get( API::class );
		$api->set_logging_source( $source );

		return $api;
	}

	/**
	 * Get gateways class instance
	 *
	 * @return Gateways|null
	 */
	public function gateways(): ?Gateways {
		return $this->gateways;
	}

	/**
	 * Get dependency injection container
	 *
	 * @return DIContainer
	 */
	public function di(): DIContainer {
		if( is_null( $this->di_container ) ) {
			$this->di_container = new DIContainer();
		}

		return $this->di_container;
	}

	/**
	 * WooCommerce Loaded: load classes
	 *
	 * @return void
	 */
	public function include_classes() {
		new Reepay\Checkout\Admin\Main();

		new Reepay\Checkout\Tokens\Main();

		new Reepay\Checkout\Plugin\UpdateDB();

		new Reepay\Checkout\OrderFlow\Main();

		new Reepay\Checkout\Actions\Subscriptions();

		$this->gateways = new Reepay\Checkout\Gateways();

		new Reepay\Checkout\Integrations\Main();

		new Reepay\Checkout\Frontend\Main();

		new Reepay\Checkout\Actions\ReepayCustomer();
	}
}

/**
 * Get reepay checkout instance
 *
 * @return WC_ReepayCheckout
 */
function reepay(): WC_ReepayCheckout {
	return WC_ReepayCheckout::get_instance();
}

reepay();
