<?php
/*
 * Plugin Name: WooCommerce Reepay Checkout Gateway
 * Description: Provides a Payment Gateway through Reepay for WooCommerce.
 * Author: reepay
 * Author URI: http://reepay.com
 * Version: 1.4.22
 * Text Domain: reepay-checkout-gateway
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 6.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

define('REEPAY_CHECKOUT_PLUGIN_FILE', __FILE__);

include_once( dirname( __FILE__ ) . '/includes/trait-wc-reepay-log.php' );
include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-statistics.php' );

class WC_ReepayCheckout {
	const PAYMENT_METHODS = array(
		'reepay_checkout',
		'reepay_applepay',
		'reepay_klarna_pay_later',
		'reepay_klarna_pay_now',
		'reepay_mobilepay',
		'reepay_paypal',
		'reepay_resurs',
		'reepay_swish',
		'reepay_viabill',
		'reepay_googlepay',
		'reepay_vipps',
		'reepay_mobilepay_subscriptions'
	);

	public static $db_version = '1.2.9';

	/**
	 * @var WC_Background_Reepay_Queue
	 */
	public static $background_process;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Activation
		register_activation_hook( __FILE__, __CLASS__ . '::install' );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
		add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded' ), 40 );
		add_action( 'init', __CLASS__ . '::may_add_notices' );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'add_valid_order_statuses'
		), 10, 2 );

		// Add Footer HTML
		add_action( 'wp_footer', __CLASS__ . '::add_footer' );

		$this->includes();

		// Add admin menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 99 );

		// Process queue
		if ( ! is_multisite() ) {
			add_action( 'customize_save_after', array( $this, 'maybe_process_queue' ) );
			add_action( 'after_switch_theme', array( $this, 'maybe_process_queue' ) );
		}
	}

	/**
	 * Install
	 */
	public static function install() {
		if ( ! get_option( 'woocommerce_reepay_version' ) ) {
			add_option( 'woocommerce_reepay_version', self::$db_version );
		}
	}

	public function includes() {
		include_once( dirname( __FILE__ ) . '/includes/functions.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-order-statuses.php' );
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=reepay_checkout' ) . '">' . __( 'Settings', 'reepay-checkout-gateway' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
		load_plugin_textdomain( 'reepay-checkout-gateway', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Show Upgrade notification
		if ( version_compare( get_option( 'woocommerce_reepay_version', self::$db_version ), self::$db_version, '<' ) ) {
			add_action( 'admin_notices', __CLASS__ . '::upgrade_notice' );
		}
	}

	/**
	 * WooCommerce Init
	 */
	public function woocommerce_init() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-background-reepay-queue.php' );
		self::$background_process = new WC_Background_Reepay_Queue();
	}

	/**
	 * WooCommerce Loaded: load classes
	 * @return void
	 */
	public function woocommerce_loaded() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payment-token-reepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payment-ms-token-reepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/interfaces/class-wc-payment-gateway-reepay-interface.php' );
		include_once( dirname( __FILE__ ) . '/includes/trait-wc-reepay-token.php' );
		include_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-gateway-reepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-api.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-instant-settle.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-webhook.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-thankyou.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-subscriptions.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-admin.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-checkout.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-mobilepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-viabill.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-klarna-pay-later.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-klarna-pay-now.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-resurs.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-swish.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-paypal.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-apple-pay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-googlepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-vipps.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-ms.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-klarna-slice-it.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-capture.php' );
	}

	/**
	 * Add notices
	 */
	public static function may_add_notices() {
		// Check if WooCommerce is missing
		if ( ! class_exists( 'WooCommerce', false ) || ! defined( 'WC_ABSPATH' ) ) {
			add_action( 'admin_notices', __CLASS__ . '::missing_woocommerce_notice' );
		}
	}

	/**
	 * Check if WooCommerce is missing, and deactivate the plugin if needs
	 */
	public static function missing_woocommerce_notice() {
		?>
		<div id="message" class="error">
			<p class="main">
				<strong>
					<?php echo esc_html__(
							'WooCommerce is inactive or missing.',
							'reepay-checkout-gateway'
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				echo esc_html__(
						'WooCommerce plugin is inactive or missing. Please install and active it.',
						'reepay-checkout-gateway'
				);
				echo '<br />';
				echo sprintf(
				/* translators: 1: plugin name */                        esc_html__(
					'%1$s will be deactivated.',
					'reepay-checkout-gateway'
				),
					'WooCommerce Reepay Checkout Gateway'
				);

				?>
			</p>
		</div>
		<?php

		// Deactivate the plugin
		deactivate_plugins( plugin_basename( __FILE__ ), true );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		if ( is_checkout() ) {
			wp_enqueue_style( 'wc-gateway-reepay-checkout', plugins_url( '/assets/css/style' . $suffix . '.css', __FILE__ ), array() );
		}
	}

	/**
	 * Add Footer HTML
	 */
	public static function add_footer() {
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( is_array( $settings ) && ! empty( $settings['logo_height'] ) ):
			$logo_height = $settings['logo_height'];
			if ( is_numeric( $logo_height ) ) {
				$logo_height .= 'px';
			}
		?>
		<style type="text/css">
			#payment .wc_payment_method > label:first-of-type img
			{
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

		if( is_checkout() ):
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
			console.log('checkout page is loaded');
			jQuery('body').on('updated_checkout', function(){
				var className = 'wc_payment_method payment_method_reepay_applepay';
				if (true == Reepay.isApplePayAvailable()) {
					for (let element of document.
					getElementsByClassName(className)){
						element.style.display = 'block';
					}
				}

				var className = 'wc_payment_method payment_method_reepay_googlepay';
				Reepay.isGooglePayAvailable().then(isAvailable => {
					if(true == isAvailable) {
						for (let element of document.
						getElementsByClassName(className)){
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
	 * Dispatch Background Process
	 */
	public function maybe_process_queue() {
		self::$background_process->dispatch();
	}

	/**
	 * Register payment gateway
	 *
	 * @param string $class_name
	 */
	public static function register_gateway( $class_name ) {
		global $gateways;

		if ( ! $gateways ) {
			$gateways = array();
		}

		if ( ! isset( $gateways[ $class_name ] ) ) {
			// Initialize instance
			if ( $gateway = new $class_name ) {
				$gateways[] = $class_name;

				// Register gateway instance
				add_filter( 'woocommerce_payment_gateways', function ( $methods ) use ( $gateway ) {
					$methods[] = $gateway;

					return $methods;
				} );
			}
		}
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
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
			$statuses = array_merge( $statuses, array(
				'processing',
				'completed'
			) );
		}

		return $statuses;
	}

	/**
	 * Provide Admin Menu items
	 */
	public function admin_menu() {
		// Add Upgrade Page
		global $_registered_pages;

		$hookname = get_plugin_page_hookname( 'wc-reepay-upgrade', '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::upgrade_page' );
		}

		$_registered_pages[ $hookname ] = true;
	}

	/**
	 * Upgrade Page
	 */
	public static function upgrade_page() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Run Database Update
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-update.php' );
		WC_Reepay_Update::update();

		echo esc_html__( 'Upgrade finished.', 'reepay-checkout-gateway' );
	}

	/**
	 * Upgrade Notice
	 */
	public static function upgrade_notice() {
		if ( current_user_can( 'update_plugins' ) ) {
			?>
			<div id="message" class="error">
				<p>
					<?php
					echo esc_html__( 'Warning! WooCommerce Reepay Checkout plugin requires to update the database structure.', 'reepay-checkout-gateway' );
					echo ' ' . sprintf( esc_html__( 'Please click %s here %s to start upgrade.', 'reepay-checkout-gateway' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-reepay-upgrade' ) ) . '">', '</a>' );
					?>
				</p>
			</div>
			<?php
		}
	}
}

new WC_ReepayCheckout();
