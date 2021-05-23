<?php
/*
 * Plugin Name: WooCommerce Reepay Checkout Gateway
 * Description: Provides a Payment Gateway through Reepay for WooCommerce.
 * Author: reepay
 * Author URI: http://reepay.com
 * Version: 1.2.8
 * Text Domain: woocommerce-gateway-reepay-checkout
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


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
        'reepay_viabill'
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


		// Add meta boxes
		//add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add action buttons
		//add_action( 'woocommerce_order_item_add_action_buttons', __CLASS__ . '::add_action_buttons', 10, 1 );

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
		
		add_action( 'wp_ajax_reepay_refund', array(
			$this,
			'ajax_reepay_refund'
		) );
		
		add_action( 'wp_ajax_reepay_capture_partly', array(
			$this,
			'ajax_reepay_capture_partly'
		) );
		
		add_action( 'wp_ajax_reepay_refund_partly', array(
			$this,
			'ajax_reepay_refund_partly'
		) );

		$this->includes();

		// Add admin menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 99 );
		
		// add meta boxes
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

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
			add_action( 'admin_notices', __CLASS__ . '::upgrade_notice' );
		}
	}

	/**
	 * WooCommerce Init
	 */
	public function woocommerce_init() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-background-reepay-queue.php' );
		self::$background_process = new WC_Background_Reepay_Queue();

		// Generate guest ID is save it in the session
		if ( ! is_user_logged_in() ) {
			if ( PHP_SESSION_ACTIVE !== session_status() ) {
				session_start();
			}

			if ( ! isset( $_SESSION['reepay_guest'] ) ) {
				$_SESSION['reepay_guest'] = 'guest-' . wp_generate_password(12, false);
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
		include_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-gateway-reepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-checkout.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-mobilepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-viabill.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-klarna-pay-later.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-klarna-pay-now.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-resurs.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-swish.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-paypal.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-reepay-apple-pay.php' );
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
                            'woocommerce-gateway-reepay-checkout'
                    );
                    ?>
                </strong>
            </p>
            <p>
				<?php
				echo esc_html__(
				        'WooCommerce plugin is inactive or missing. Please install and active it.',
                        'woocommerce-gateway-reepay-checkout'
                );
				echo '<br />';
				echo sprintf(
				/* translators: 1: plugin name */                        esc_html__(
					'%1$s will be deactivated.',
					'woocommerce-gateway-reepay-checkout'
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
		wp_enqueue_style( 'wc-gateway-reepay-checkout', plugins_url( '/assets/css/style' . $suffix . '.css', __FILE__ ), array(), FALSE, 'all' );
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
			.reepay-logos .reepay-logo img {
				height: <?php echo esc_html( $logo_height ); ?> !important;
				max-height: <?php echo esc_html( $logo_height ); ?> !important;
			}
		</style>
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
	 * Add meta boxes in admin
	 * @return void
	 */
	public function add_meta_boxes() {
		/*global $post_id;
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
		}*/
		
		global $post;
		$screen     = get_current_screen();
		$post_types = [ 'shop_order', 'shop_subscription' ];
	
		if ( in_array( $screen->id, $post_types, true ) && in_array( $post->post_type, $post_types, true ) ) {
			if ( $order = wc_get_order( $post->ID ) ) {
				$payment_method = $order->get_payment_method();
				if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
					add_meta_box( 'reepay-payment-actions', __( 'Reepay Payment', 'woocommerce-gateway-reepay-checkout' ), [
						&$this,
						'meta_box_payment',
					], 'shop_order', 'side', 'high' );
					//add_meta_box( 'reepay-payment-actions', __( 'Reepay Subscription', 'woocommerce-gateway-reepay-checkout' ), [
					//	&$this,
					//	'meta_box_subscription',
					//], 'shop_subscription', 'side', 'high' );
				}
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
			wp_register_script(
                'reepay-js-input-mask',
                plugin_dir_url( __FILE__ ) . 'assets/js/jquery.inputmask' . $suffix . '.js',
                array( 'jquery'),
                '5.0.3'
            );
			wp_register_script(
                'reepay-admin-js',
                plugin_dir_url( __FILE__ ) . 'assets/js/admin' . $suffix . '.js',
                array(
                    'jquery',
	                'reepay-js-input-mask'
                )
            );
			wp_enqueue_style( 'wc-gateway-reepay-checkout', plugins_url( '/assets/css/style' . $suffix . '.css', __FILE__ ), array(), FALSE, 'all' );

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
		
		//
		// Check if the order is already cancelled
		// ensure no more actions are made
		//
		if ( $order->get_meta( '_reepay_order_cancelled', true ) === "1" ) {
			wp_send_json_success( __( 'Order already cancelled.', 'woocommerce-gateway-reepay-checkout' ) );
			return;
		}

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			
			// Check if the payment can be cancelled
			// $order->update_meta_data( '_' . $key, $value );
			// order->get_meta( '_reepay_token', true )
			if ($gateway->can_cancel( $order )) {
				$gateway->cancel_payment( $order );
			} 
			
			//
			// Mark the order as cancelled - no more communciation to reepay is done!
			// 
			$order->update_meta_data( '_reepay_order_cancelled', 1 );
			$order->save_meta_data();
			
			// Return success
			wp_send_json_success( __( 'Cancel success.', 'woocommerce-gateway-reepay-checkout' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}
	
	/**
	 * Action for Cancel
	 */
	public function ajax_reepay_refund() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}
		
		
		$amount = (int) $_REQUEST['amount'];
		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->refund_payment( $order, $amount );
			wp_send_json_success( __( 'Refund success.', 'woocommerce-gateway-reepay-checkout' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}
	
	/**
	 * Action for Cancel
	 */
	public function ajax_reepay_capture_partly() {
		
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}
		
		$amount = $_REQUEST['amount'];
		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );
		
		$amount = str_replace(",", "", $amount);
		$amount = str_replace(".", "", $amount);
		
		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->capture_payment( $order, (float)((float)$amount / 100) );
			wp_send_json_success( __( 'Capture partly success.', 'woocommerce-gateway-reepay-checkout' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}
	
	/**
	 * Action for Cancel
	 */
	public function ajax_reepay_refund_partly() {
		
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'reepay' ) ) {
			exit( 'No naughty business' );
		}
		
		$amount = $_REQUEST['amount'];
		$order_id = (int) $_REQUEST['order_id'];
		$order = wc_get_order( $order_id );
		
		$amount = str_replace(",", "", $amount);
		$amount = str_replace(".", "", $amount);
		
		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->refund_payment( $order, (float)((float)$amount / 100) );
			wp_send_json_success( __( 'Refund partly success.', 'woocommerce-gateway-reepay-checkout' ) );
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

	/**
	 * Inserts the content of the API actions into the meta box
	 */
	public function meta_box_payment() {
		global $post;
		
		if ( $order = wc_get_order( $post->ID ) ) {
			
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
				
				do_action( 'woocommerce_reepay_meta_box_payment_before_content', $order );

				global $post_id;
				$order = wc_get_order( $post_id );

				// Get Payment Gateway
				$gateways = WC()->payment_gateways()->get_available_payment_gateways();

				/** @var WC_Gateway_Reepay_Checkout $gateway */
				$gateway = 	$gateways[ $payment_method ];

				try {
					wc_get_template(
						'admin/metabox-order.php',
						array(
							'gateway'    => $gateway,
							'order'      => $order,
							'order_id'   => $order->get_id(),
							'order_data' => $gateway->get_invoice_data( $order )
						),
						'',
						dirname( __FILE__ ) . '/templates/'
					);
				} catch ( Exception $e ) {
				    // Silence is golden
				}
			}
		}
	}

	public function meta_box_subscription() {
	    $this->meta_box_payment();
	}
	
	/*
	 * Formats a minor unit value into float with two decimals
	 * @priceMinor is the amount to format
	 * @return the nicely formatted value
	 */
	public function format_price_decimals( $priceMinor ) {
		return number_format( $priceMinor / 100, 2, wc_get_price_decimal_separator(), '' );
	}
	
	/**
     * Formats a credit card nicely
     *
	 * @param string $cc is the card number to format nicely
	 *
	 * @return false|string the nicely formatted value
	 */
	public static function formatCreditCard( $cc ) {
		$cc = str_replace(array('-', ' '), '', $cc);
		$cc_length = strlen($cc);
		$newCreditCard = substr($cc, -4);

		for ( $i = $cc_length - 5; $i >= 0; $i-- ) {
			if ( (($i + 1) - $cc_length) % 4 == 0 ) {
				$newCreditCard = ' ' . $newCreditCard;
			}
			$newCreditCard = $cc[$i] . $newCreditCard;
		}

		for ( $i = 7; $i < $cc_length - 4; $i++ ) {
			if ( $newCreditCard[$i] == ' ' ) {
				continue;
			}
			$newCreditCard[$i] = 'X';
		}

		return $newCreditCard;
	}

}

new WC_ReepayCheckout();
