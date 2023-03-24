<?php
/*
 * Plugin Name: Reepay Checkout for WooCommerce
 * Description: Get a plug-n-play payment solution for WooCommerce, that is easy to use, highly secure and is built to maximize the potential of your e-commerce.
 * Author: reepay
 * Author URI: http://reepay.com
 * Version: 1.4.59
 * Text Domain: reepay-checkout-gateway
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 7.5.0
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\Gateways;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\PluginLifeCycle;
use Reepay\Checkout\WoocommerceExists;

defined( 'ABSPATH' ) || exit();

define( 'REEPAY_CHECKOUT_PLUGIN_FILE', __FILE__ );
define( 'REEPAY_CHECKOUT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once dirname( __FILE__ ) . '/includes/trait-wc-reepay-log.php';
require_once dirname( __FILE__ ) . '/includes/class-wc-reepay-statistics.php';

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
	 * Constructor
	 */
	private function __construct() {
		include_once dirname( __FILE__ ) . '/vendor/autoload.php';

		new PluginLifeCycle( $this->get_setting( 'plugin_path' ) );
		new WoocommerceExists();

		add_action( 'plugins_loaded', array( $this, 'include_classes' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Add statuses for payment complete
		add_filter(
			'woocommerce_valid_order_statuses_for_payment_complete',
			array( $this, 'add_valid_order_statuses' ),
			10,
			2
		);

		// Add Footer HTML
		add_action( 'wp_footer', __CLASS__ . '::add_footer' );

		$this->includes();

		load_plugin_textdomain( 'reepay-checkout-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * @return WC_ReepayCheckout
	 */
	public static function get_instance() {
		static $instance_requested = false;

		if ( true === $instance_requested && is_null( self::$instance ) ) {
			$message = 'Function reepay called in time of initialization main plugin class. Recursion prevented';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message .= '<br>Stack trace for debugging:<br><pre>' . print_r( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), true ) . '</pre>';
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
	 * @param  string $name  Setting key.
	 *
	 * @return string|null
	 */
	public function get_setting( $name ) {
		if ( empty( $this->settings ) ) {
			$gateway_settings = get_option( 'woocommerce_reepay_checkout_settings' );

			if ( ! is_array( $gateway_settings ) ) {
				$gateway_settings = array();
			}

			if ( isset( $gateway_settings['private_key'] ) ) {
				$gateway_settings['private_key'] = apply_filters( 'woocommerce_reepay_private_key', $gateway_settings['private_key'] ?? '' );
			}

			if ( isset( $gateway_settings['private_key_test'] ) ) {
				$gateway_settings['private_key_test'] = apply_filters( 'woocommerce_reepay_private_key_test', $gateway_settings['private_key_test'] ?? '' );
			}

			$this->settings = array(
				'plugin_file'     => __FILE__,
				'plugin_basename' => plugin_basename( __FILE__ ),
				'plugin_url'      => plugin_dir_url( __FILE__ ),
				'plugin_path'     => plugin_dir_path( __FILE__ ),

				'private_key'             => $gateway_settings['private_key'] ?? '',
				'private_key_test'        => $gateway_settings['private_key_test'] ?? '',
				'test_mode'               => $gateway_settings['test_mode'] ?? '',
				'settle'                  => $gateway_settings['settle'] ?? '',
				'language'                => $gateway_settings['language'] ?? '',
				'debug'                   => $gateway_settings['debug'] ?? '',
				'payment_type'            => $gateway_settings['payment_type'] ?? '',
				'skip_order_lines'        => $gateway_settings['skip_order_lines'] ?? '',
				'enable_order_autocancel' => $gateway_settings['enable_order_autocancel'] ?? '',
				'is_webhook_configured'   => $gateway_settings['is_webhook_configured'] ?? '',
				'handle_failover'         => $gateway_settings['handle_failover'] ?? '',
			);
		}

		return $this->settings[ $name ] ?? null;
	}

	/**
	 * Wrapper of wc_get_template function
	 *
	 * @param  string $template  Template name.
	 * @param  array  $args      Arguments.
	 */
	public function get_template( $template, $args = array() ) {
		wc_get_template(
			$template,
			$args,
			'',
			$this->get_setting( 'plugin_path' ) . 'templates/'
		);
	}

	/**
	 * Check if payment method is reepay payment method
	 *
	 * @param string $payment_method
	 */
	public function is_reepay_payment_method( $payment_method ) {
		return in_array( $payment_method, Gateways::PAYMENT_METHODS );
	}

	/**
	 * Check if payment method is reepay payment method
	 *
	 * @param WC_Order $order
	 */
	public function is_order_paid_via_reepay( $order ) {
		return in_array( $order->get_payment_method(), Gateways::PAYMENT_METHODS );
	}

	/**
	 * Set logging source.
	 *
	 * @param ReepayGateway|string $source
     *
     * @return Api;
	 */
	public function api( $source ) {
	    /** @var Api|null $api */
	    static $api = null;

		if ( is_null( $api ) ) {
		    $api = new Api( $source );
		} else {
		    $api->set_logging_source( $source );
        }

		return $api;
    }

	public function includes() {
		include_once dirname( __FILE__ ) . '/includes/class-wc-reepay-order-statuses.php';
	}

	/**
	 * WooCommerce Loaded: load classes
	 *
	 * @return void
	 */
	public function include_classes() {
		new Reepay\Checkout\Admin\Main();

		new Reepay\Checkout\Tokens\Main();

		new Reepay\Checkout\UpdateDB();

		include_once dirname( __FILE__ ) . '/includes/class-wc-reepay-capture.php';
		include_once dirname( __FILE__ ) . '/includes/class-wc-reepay-instant-settle.php';
		include_once dirname( __FILE__ ) . '/includes/class-wc-reepay-webhook.php';
		include_once dirname( __FILE__ ) . '/includes/class-wc-reepay-thankyou.php';
		include_once dirname( __FILE__ ) . '/includes/class-wc-reepay-subscriptions.php';

		new Reepay\Checkout\Gateways();

		new Reepay\Checkout\Integrations\Main();
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		if ( is_checkout() ) {
			wp_enqueue_style( 'wc-gateway-reepay-checkout', plugins_url( '/assets/dist/css/style' . $suffix . '.css', __FILE__ ), array() );
		}
	}

	/**
	 * Add Footer HTML
	 */
	public static function add_footer() {
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( is_array( $settings ) && ! empty( $settings['logo_height'] ) ) :
			$logo_height = $settings['logo_height'];
			if ( is_numeric( $logo_height ) ) {
				$logo_height .= 'px';
			}
			?>
			<style type="text/css">
				#payment .wc_payment_method > label:first-of-type img {
					height: <?php echo esc_html( $logo_height ); ?>;
					max-height: <?php echo esc_html( $logo_height ); ?>;
					list-style: none;
				}

				#payment .reepay-logos li {
					list-style: none;
				}
			</style>
			<?php
		endif;

		if ( is_checkout() ) :
			?>
			<style type="text/css">
				#payment li.payment_method_reepay_applepay {
					display: none;
				}

				#payment li.payment_method_reepay_googlepay {
					display: none;
				}

			</style>

			<script type="text/javascript">
				jQuery('body').on('updated_checkout', function () {
					var className = 'wc_payment_method payment_method_reepay_applepay';
					if (true == Reepay.isApplePayAvailable()) {
						for (let element of document.getElementsByClassName(className)) {
							element.style.display = 'block';
						}
					}

					var className = 'wc_payment_method payment_method_reepay_googlepay';
					Reepay.isGooglePayAvailable().then(isAvailable => {
						if (true == isAvailable) {
							for (let element of document.getElementsByClassName(className)) {
								element.style.display = 'block';
							}
						}
					});
				});
			</script>

			<?php
		endif;
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param array    $statuses
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses( $statuses, $order ) {
		if ( reepay()->is_order_paid_via_reepay( $order ) ) {
			$statuses = array_merge(
				$statuses,
				array(
					'processing',
					'completed',
				)
			);
		}

		return $statuses;
	}
}

/**
 * Get reepay checkout instance
 *
 * @return WC_ReepayCheckout
 */
function reepay() {
	return WC_ReepayCheckout::get_instance();
}

reepay();
