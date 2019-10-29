<?php
/*
 * Plugin Name: WooCommerce Reepay Checkout Gateway
 * Plugin URI: #
 * Description: Provides a Payment Gateway through Reepay for WooCommerce.
 * Author: AAIT
 * Author URI: #
 * Version: 1.0.0
 * Text Domain: woocommerce-gateway-reepay-checkout
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class WC_ReepayCheckout {
	const PAYMENT_METHODS = array('reepay_token' , 'reepay_checkout');

	public static $db_version = '1.2.3';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Activation
		register_activation_hook( __FILE__, __CLASS__ . '::install' );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded' ), 40 );

		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'add_valid_order_statuses'
		), 10, 2 );


		// Add meta boxes
		//add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add action buttons
		add_action( 'woocommerce_order_item_add_action_buttons', __CLASS__ . '::add_action_buttons', 10, 1 );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Footer HTML
		add_action( 'wp_footer', __CLASS__ . '::add_footer' );

		// Add Admin Backend Actions
		add_action( 'wp_ajax_reepay_capture', array(
			$this,
			'ajax_reepay_capture'
		) );

		add_action( 'wp_ajax_reepay_cancel', array(
			$this,
			'ajax_reepay_cancel'
		) );

		$this->includes();

		// Add admin menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 99 );
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
		include_once( dirname( __FILE__ ) . '/includes/class-wc-reepay-order.php' );
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
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=reepay_checkout' ) . '">' . __( 'Settings', 'woocommerce-gateway-reepay-checkout' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-reepay-checkout', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Show Upgrade notification
		if ( version_compare( get_option( 'woocommerce_reepay_version', self::$db_version ), self::$db_version, '<' ) ) {
			if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
				add_action( 'admin_notices', __CLASS__ . '::upgrade_notice' );
			}
		}
	}

	/**
	 * WooCommerce Loaded: load classes
	 * @return void
	 */
	public function woocommerce_loaded() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payment-token-reepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/interfaces/class-wc-payment-gateway-reepay-interface.php' );
		include_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-payment-gateway-reepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-checkout.php' );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'wc-gateway-reepay-checkout', plugins_url( '/assets/css/style' . $suffix . '.css', __FILE__ ), array(), FALSE, 'all' );
	}

	/**
	 * Add Footer HTML
	 */
	public static function add_footer() {
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( is_array( $settings ) && ! empty( $settings['logo_height'] ) ):
		?>
		<style type="text/css">
			.reepay-logos .reepay-logo img {
				height: <?php echo esc_html( $settings['logo_height'] ); ?>;
			}
		</style>
		<?php
		endif;
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
	 * Add meta boxes in admin
	 * @return void
	 */
	public static function add_meta_boxes() {
		global $post_id;
		if ( $order = wc_get_order( $post_id ) ) {
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
				add_meta_box(
					'reepay_payment_actions',
					__( 'Reepay Payments Actions', 'woocommerce-gateway-reepay-checkout' ),
					__CLASS__ . '::order_meta_box_payment_actions',
					'shop_order',
					'side',
					'default'
				);
			}
		}
	}

	/**
	 * MetaBox for Payment Actions
	 * @return void
	 */
	public static function order_meta_box_payment_actions() {
		global $post_id;
		$order = wc_get_order( $post_id );

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_Reepay_Checkout $gateway */
		$gateway = 	$gateways[ $payment_method ];

		wc_get_template(
			'admin/payment-actions.php',
			array(
				'gateway'    => $gateway,
				'order'      => $order,
				'order_id'   => $post_id,
			),
			'',
			dirname( __FILE__ ) . '/templates/'
		);
	}

	/**
	 * @param WC_Order $order
	 */
	public static function add_action_buttons( $order ) {
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];

			wc_get_template(
				'admin/action-buttons.php',
				array(
					'gateway'    => $gateway,
					'order'      => $order
				),
				'',
				dirname( __FILE__ ) . '/templates/'
			);
		}
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script( 'reepay-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin' . $suffix . '.js' );

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'woocommerce-gateway-reepay-checkout' ),
			);
			wp_localize_script( 'reepay-admin-js', 'Reepay_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'reepay-admin-js' );
		}
	}

	/**
	 * Action for Capture
	 */
	public function ajax_reepay_capture() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->capture_payment( $order );
			wp_send_json_success( __( 'Capture success.', 'woocommerce-gateway-reepay-checkout' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_reepay_cancel() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->cancel_payment( $order );
			wp_send_json_success( __( 'Capture success.', 'woocommerce-gateway-reepay-checkout' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
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

		echo esc_html__( 'Upgrade finished.', 'woocommerce-gateway-reepay-checkout' );
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
					echo esc_html__( 'Warning! WooCommerce Reepay Checkout plugin requires to update the database structure.', 'woocommerce-gateway-reepay-checkout' );
					echo ' ' . sprintf( esc_html__( 'Please click %s here %s to start upgrade.', 'woocommerce-gateway-reepay-checkout' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-reepay-upgrade' ) ) . '">', '</a>' );
					?>
				</p>
			</div>
			<?php
		}
	}
}

new WC_ReepayCheckout();

