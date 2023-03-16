<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class WC_Gateway_Reepay extends WC_Payment_Gateway implements WC_Payment_Gateway_Reepay_Interface {
	use WC_Reepay_Token;

	/**
	 * @var WC_Reepay_Api
	 */
	public $api;

	/**
	 * Test Mode
	 * @var string
	 */
	public $test_mode = 'yes';

	/**
	 * @var string
	 */
	public $private_key;

	/**
	 * @var
	 */
	public $private_key_test;

	/**
	 * @var string
	 */

	public $public_key;

	/**
	 * Settle
	 * @var string
	 */
	public $settle = array(
		WC_Reepay_Instant_Settle::SETTLE_VIRTUAL,
		WC_Reepay_Instant_Settle::SETTLE_PHYSICAL,
		WC_Reepay_Instant_Settle::SETTLE_RECURRING,
		WC_Reepay_Instant_Settle::SETTLE_FEE
	);

	/**
	 * Debug Mode
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * Language
	 * @var string
	 */
	public $language = 'en_US';

	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'dankort',
		'visa',
		'mastercard',
		'visa-electron',
		'maestro',
		'mobilepay',
		'viabill',
		'applepay',
		'paypal_logo',
		'klarna-pay-later',
		'klarna-pay-now',
		'klarna',
		'resursbank'
	);

	/**
	 * Payment Type
	 * @var string
	 */
	public $payment_type = 'OVERLAY';

	/**
	 * Save CC
	 * @var string
	 */
	public $save_cc = 'yes';

	/**
	 * Logo Height
	 * @var string
	 */
	public $logo_height = '';

	/**
	 * Skip order lines to Reepay and use order totals instead
	 */
	public $skip_order_lines = 'no';

	/**
	 * If automatically cancel unpaid orders should be ignored
	 */
	public $enable_order_autocancel = 'no';

	/**
	 * Email address for notification about failed webhooks
	 * @var string
	 */
	public $failed_webhooks_email = '';

	/**
	 * If webhooks have been configured
	 * @var string
	 */
	public $is_webhook_configured = 'no';

	/**
	 * Order handle failover.
	 * @var string
	 */
	public $handle_failover = 'yes';

	/**
	 * Init
	 */
	public function __construct() {

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled     = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';

		$this->api = new WC_Reepay_Api( $this );

		add_action( 'admin_notices', array( $this, 'admin_notice_api_action' ) );

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Payment confirmation
		add_action( 'the_post', array( $this, 'payment_confirm' ) );

		// Cancel actions
		add_action( 'wp_ajax_reepay_cancel_payment', array( $this, 'reepay_cancel_payment' ) );
		add_action( 'wp_ajax_nopriv_reepay_cancel_payment', array( $this, 'reepay_cancel_payment' ) );

		static $handler_added = false;

		if ( ! $handler_added ) {
			// Payment listener/API hook
			add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
				$this,
				'return_handler'
			) );

			$handler_added = true;
		}
	}

	public function check_is_active() {
		$gateway   = new WC_Gateway_Reepay_Checkout();
		$this->api = new WC_Reepay_Api( $gateway );

		$gateways_reepay = get_transient( 'gateways_reepay' );
		if ( empty( $gateways_reepay ) ) {
			$gateways_reepay = $this->api->request( 'GET', 'https://api.reepay.com/v1/agreement?only_active=true' );
			set_transient( 'gateways_reepay', $gateways_reepay, 5 );
		}

		$current_name = str_replace( 'reepay_', '', $this->id );


		if ( ! empty( $gateways_reepay ) ) {
			foreach ( $gateways_reepay as $app ) {
				if ( stripos( $app['type'], $current_name ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	public function is_configured() {
		$configured = false;

		if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'checkout' && ! empty( $_GET['section'] ) && $_GET['section'] != 'reepay_checkout' ) {
			$configured = $this->check_is_active();
		}

		return $configured;
	}


	public function get_account_info( $gateway, $is_test = false ) {
		if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'checkout' && ! empty( $_GET['section'] ) && $_GET['section'] == 'reepay_checkout' ) {

			$this->api = new WC_Reepay_Api( $gateway );

			$key = 'account_info';

			if ( $is_test ) {
				$key = 'account_info_test';
			}

			$account_info = get_transient( $key );
			if ( empty( $account_info ) ) {
				$account_info = $this->api->request( 'GET', 'https://api.reepay.com/v1/account' );
				set_transient( $key, $account_info, 5 );
			}

			return $account_info;
		}

		return [];

	}


	/**
	 * @return string
	 */
	public static function get_default_api_url( $request = '' ) {
		$default_wc_api_url = WC()->api_request_url( '' );

		if ( class_exists( 'SitePress' ) ) {
			$languages = apply_filters( 'wpml_active_languages', null, 'orderby=id&order=desc' );
			$languages = wp_list_pluck( $languages, 'default_locale' );
		} else {
			$languages = get_available_languages();
		}

		if ( ! empty( $languages ) ) {
			foreach ( $languages as $available_language ) {
				$lang = explode( '_', $available_language );
				if ( stripos( $default_wc_api_url, '/' . $lang[0] . '/' ) !== false ) {
					$default_wc_api_url = str_replace( '/' . $lang[0] . '/', '/', $default_wc_api_url );
				}
			}
		}


		return ! empty( $request ) ? $default_wc_api_url . $request . '/' : $default_wc_api_url;
	}

	/**
	 * @return bool
	 */
	public function is_webhook_configured() {
		try {
			$request = $this->api->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
			if ( is_wp_error( $request ) ) {
				/** @var WP_Error $request */
				if ( ! empty( $request->get_error_code() ) ) {
					throw new Exception( $request->get_error_message(), intval( $request->get_error_code() ) );
				}
			}

			$default_wc_api_url = self::get_default_api_url();

			$alert_emails = $request['alert_emails'];

			// The webhook settings of the payment plugin
			$webhook_url = $default_wc_api_url . 'WC_Gateway_Reepay/';
			$alert_email = '';
			if ( ! empty( $this->settings['failed_webhooks_email'] ) &&
			     is_email( $this->settings['failed_webhooks_email'] )
			) {
				$alert_email = $this->settings['failed_webhooks_email'];
			}


			$exist_waste_urls = false;

			$urls = [];

			foreach ( $request['urls'] as $url ) {
				if ( strpos( $url, $default_wc_api_url ) === false ||
				     $url === $webhook_url ) {
					$urls[] = $url;
				} else {
					$exist_waste_urls = true;
				}
			}

			// Verify the webhook settings
			if ( ! empty( $urls ) && in_array( $webhook_url, $urls )
			     && ( empty( $alert_email ) || in_array( $alert_email, $alert_emails ) )
			     && ! $exist_waste_urls
			) {
				// Webhook has been configured before
				$this->update_option( 'is_webhook_configured', 'yes' );

				// Skip the update
				return true;
			}

			// Update the webhook settings
			try {
				if ( ! in_array( $webhook_url, $urls ) ) {
					$urls[] = $webhook_url;
				}

				if ( ! empty( $alert_email ) && is_email( $alert_email ) ) {
					$alert_emails[] = $alert_email;
				}

				$data = array(
					'urls'         => array_unique( $urls ),
					'disabled'     => false,
					'alert_emails' => array_unique( $alert_emails )
				);

				//$this->test_mode = 'yes';

				$request = $this->api->request( 'PUT', 'https://api.reepay.com/v1/account/webhook_settings', $data );
				if ( is_wp_error( $request ) ) {
					/** @var WP_Error $request */
					throw new Exception( $request->get_error_message(), $request->get_error_code() );
				}

				$this->log( sprintf( 'WebHook has been successfully created/updated: %s', var_export( $request, true ) ) );
				$this->update_option( 'is_webhook_configured', 'yes' );
				WC_Admin_Settings::add_message( __( 'Reepay: WebHook has been successfully created/updated', 'reepay-checkout-gateway' ) );

				return true;
			} catch ( Exception $e ) {
				$this->update_option( 'is_webhook_configured', 'no' );
				$this->log( sprintf( 'WebHook creation/update has been failed: %s', var_export( $request, true ) ) );
				WC_Admin_Settings::add_error( __( 'Reepay: WebHook creation/update has been failed', 'reepay-checkout-gateway' ) );
			}
		} catch ( Exception $e ) {
			$this->update_option( 'is_webhook_configured', 'no' );
			WC_Admin_Settings::add_error( __( 'Unable to retrieve the webhook settings. Wrong api credentials?', 'reepay-checkout-gateway' ) );
		}

		return false;
	}

	public function needs_setup() {
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'woocommerce_toggle_gateway_enabled' ) {
			return ! $this->check_is_active();
		}

		return false;
	}

	/**
	 * Generate Gateway Status HTML.
	 *
	 * @param string $key Field key.
	 * @param array $data Field data.
	 *
	 * @return string
	 */
	public function generate_gateway_status_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'       => '',
			'type'        => 'webhook_status',
			'desc_tip'    => false,
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$enabled = $this->get_option( 'enabled' ) == 'yes';

		$configured = $this->is_configured();

		ob_start();
		?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok.
					?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span>
                    </legend>

					<?php if ( $configured ): ?>
                        <span style="color: green;">
							<?php esc_html_e( 'Active', 'reepay-checkout-gateway' ); ?>
						</span>
					<?php else : ?>
                        <span style="color: red;">
							<?php esc_html_e( 'Inactive', 'reepay-checkout-gateway' ); ?>
						</span>
					<?php endif; ?>

                    <input type="hidden" name="<?php echo esc_attr( $field_key ); ?>"
                           id="<?php echo esc_attr( $field_key ); ?>"
                           value="<?php echo esc_attr( $this->get_option( $key ) ); // WPCS: XSS ok.
					       ?>"/>
                </fieldset>
            </td>
        </tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Check is Capture possible
	 *
	 * @param WC_Order|int $order
	 * @param bool $amount
	 *
	 * @return bool
	 * @api
	 */
	public function can_capture( $order, $amount = false ) {
		return $this->api->can_capture( $order, $amount );
	}

	/**
	 * Check is Cancel possible
	 *
	 * @param WC_Order|int $order
	 *
	 * @return bool
	 * @api
	 */
	public function can_cancel( $order ) {
		return $this->api->can_cancel( $order );
	}

	/**
	 * @param \WC_Order $order
	 * @param bool $amount
	 *
	 * @return bool
	 * @api
	 */
	public function can_refund( $order, $amount = false ) {
		return $this->api->can_refund( $order, $amount );
	}

	/**
	 * Capture.
	 *
	 * @param WC_Order|int $order
	 * @param float|false $amount
	 *
	 * @return void
	 * @throws Exception
	 * @api
	 */
	public function capture_payment( $order, $amount ) {
		// Check if the order is cancelled - if so - then return as nothing has happened
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( 'Order is already canceled' );
		}

		$result = $this->api->capture_payment( $order, $amount );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
	}

	/**
	 * Cancel.
	 *
	 * @param WC_Order|int $order
	 *
	 * @return void
	 * @throws Exception
	 */
	public function cancel_payment( $order ) {
		// Check if hte order is cancelled - if so - then return as nothing has happened
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( 'Order is already canceled' );
		}

		$result = $this->api->cancel( $order );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
		add_action( 'the_post', array( $this, 'payment_confirm' ) );

		// Cancel actions
		add_action( 'wp_ajax_reepay_cancel', array( $this, 'reepay_cancel' ) );
		add_action( 'wp_ajax_nopriv_reepay_cancel', array( $this, 'reepay_cancel' ) );
	}

	/**
	 * Refund
	 *
	 * @param WC_Order|int $order
	 * @param bool $amount
	 * @param string $reason
	 *
	 * @return void
	 * @throws Exception
	 */
	public function refund_payment( $order, $amount = false, $reason = '' ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Check if the order is cancelled - if so - then return as nothing has happened
		if ( '1' === $order->get_meta( '_reepay_order_cancelled', true ) ) {
			throw new Exception( 'Order is already canceled' );
		}

		if ( ! $this->can_refund( $order, $amount ) ) {
			throw new Exception( 'Payment can\'t be refunded.' );
		}

		$result = $this->api->refund( $order, $amount, $reason );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
	}

	/**
	 * Add notifications in admin for api actions.
	 */
	public function admin_notice_api_action() {
		if ( 'yes' === $this->enabled ) {
			$error   = get_transient( 'reepay_api_action_error' );
			$success = get_transient( 'reepay_api_action_success' );

			if ( ! empty( $error ) ):
				?>
                <div class="error notice is-dismissible">
                    <p><?php echo esc_html( $error ); ?></p>
                </div>
				<?php
				set_transient( 'reepay_api_action_error', null, 1 );
			endif;

			if ( ! empty( $success ) ):
				?>
                <div class="notice-success notice is-dismissible">
                    <p><?php echo esc_html( $success ); ?></p>
                </div>
				<?php
				set_transient( 'reepay_api_action_success', null, 1 );
			endif;
		}
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$html  = '';
		$logos = array_filter( (array) $this->logos, 'strlen' );
		if ( count( $logos ) > 0 ) {
			$html = '<ul class="reepay-logos">';
			foreach ( $logos as $logo ) {
				$html .= '<li class="reepay-logo">';
				$html .= '<img src="' . esc_url( plugins_url( '/assets/images/' . $logo . '.png', dirname( __FILE__ ) . '/../../../' ) ) . '" alt="' . esc_attr( sprintf( __( 'Pay with %s on Reepay', 'reepay-checkout-gateway' ), $this->get_title() ) ) . '" />';
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for payment on checkout page
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		if ( is_order_received_page() ) {
			return;
		}

		$this->enqueue_payment_scripts();
	}

	/**
	 * enqueue_payment_scripts function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function enqueue_payment_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'reepay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/dist/js/checkout-cdn.js', array(), false, false );
		wp_enqueue_script( 'wc-gateway-reepay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/dist/js/checkout' . $suffix . '.js', array(
			'jquery',
			'wc-checkout',
			'reepay-checkout',
		), filemtime( REEPAY_CHECKOUT_PLUGIN_PATH . 'assets/dist/js/checkout' . $suffix . '.js' ), true );

		// Localize the script with new data
		$translation_array = array(
			'payment_type' => $this->payment_type,
			'public_key'   => $this->public_key,
			'language'     => substr( $this->get_language(), 0, 2 ),
			'buttonText'   => __( 'Pay', 'reepay-checkout-gateway' ),
			'recurring'    => true,
			'nonce'        => wp_create_nonce( 'reepay' ),
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'cancel_text'  => __( 'Payment was canceled, please try again', 'reepay-checkout-gateway' ),
			'error_text'   => __( 'Error with payment, please try again', 'reepay-checkout-gateway' ),
		);
		wp_localize_script( 'wc-gateway-reepay-checkout', 'WC_Gateway_Reepay_Checkout', $translation_array );
	}

	/**
	 * If There are no payment fields show the description if set.
	 * @return void
	 */
	public function payment_fields() {
		wc_get_template(
			'checkout/payment-fields.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../../templates/'
		);
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		return true;
	}


	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		if ( 'application/json' === $_SERVER['CONTENT_TYPE'] ) {
			try {
				$_POST = json_decode( file_get_contents( 'php://input' ), true );

				foreach ( $_POST['payment_data'] ?? [] as $data ) {
					if ( empty( $_POST[ $data['key'] ] ) ) {
						$_POST[ $data['key'] ] = $data['value'];
					}
				}

			} catch ( Exception $e ) {
				return [
					'messages' => __( 'Wrong Request. Try again', 'reepay-checkout-gateway' ),
					'result'   => 'failure'
				];
			}
		}


		$order    = wc_get_order( $order_id );
		$token_id = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? wc_clean( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) : 'new';

		if ( isset( $_POST[ 'wc-' . $this->id . '-new-payment-method' ] ) && $_POST[ 'wc-' . $this->id . '-new-payment-method' ] !== false ) {
			$maybe_save_card = (bool) $_POST[ 'wc-' . $this->id . '-new-payment-method' ];
		} else {
			$maybe_save_card = wcs_cart_have_subscription();
		}

		if ( 'yes' !== $this->save_cc ) {
			$token_id        = 'new';
			$maybe_save_card = false;
		}
		$WC_Countries = new WC_Countries();
		$country      = '';
		if ( method_exists( $WC_Countries, 'country_exists' ) ) {
			$country = $WC_Countries->country_exists( $order->get_billing_country() ) ? $order->get_billing_country() : '';
			if ( $order->needs_shipping_address() ) {
				$country = $WC_Countries->country_exists( $order->get_shipping_country() ) ? $order->get_shipping_country() : '';
			}
		}

		// Switch of Payment Method
		if ( wcs_is_payment_change() ) {
			$customer_handle = $this->api->get_customer_handle_order( $order_id );

			if ( absint( $token_id ) > 0 ) {
				$token = new WC_Payment_Token_Reepay( $token_id );
				if ( ! $token->get_id() ) {
					wc_add_notice( __( 'Failed to load token.', 'reepay-checkout-gateway' ), 'error' );

					return false;
				}

				// Check access
				if ( $token->get_user_id() !== $order->get_user_id() ) {
					wc_add_notice( __( 'Access denied.', 'reepay-checkout-gateway' ), 'error' );

					return false;
				}

				// Replace token
				try {
					self::assign_payment_token( $order, $token );
				} catch ( Exception $e ) {
					$order->add_order_note( $e->getMessage() );

					return array(
						'result'  => 'failure',
						'message' => $e->getMessage()
					);
				}

				// Add note
				$order->add_order_note(
					sprintf(
						__( 'Payment method changed to "%s"', 'reepay-checkout-gateway' ),
						$token->get_display_name()
					)
				);

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} else {
				// Add new Card
				$params = [
					'locale'          => $this->get_language(),
					'button_text'     => __( 'Add card', 'reepay-checkout-gateway' ),
					'create_customer' => [
						'test'        => $this->test_mode === 'yes',
						'handle'      => $customer_handle,
						'email'       => $order->get_billing_email(),
						'address'     => $order->get_billing_address_1(),
						'address2'    => $order->get_billing_address_2(),
						'city'        => $order->get_billing_city(),
						'phone'       => $order->get_billing_phone(),
						'company'     => $order->get_billing_company(),
						'vat'         => '',
						'first_name'  => $order->get_billing_first_name(),
						'last_name'   => $order->get_billing_last_name(),
						'postal_code' => $order->get_billing_postcode()
					],
					'accept_url'      => add_query_arg(
						array(
							'action' => 'reepay_finalize',
							'key'    => $order->get_order_key()
						),
						admin_url( 'admin-ajax.php' )
					),
					'cancel_url'      => $order->get_cancel_order_url()
				];

				if ( ! empty( $country ) ) {
					$params['create_customer']['country'] = $country;
				}

				if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
					$params['payment_methods'] = $this->payment_methods;
				}

				$result = $this->api->request(
					'POST',
					'https://checkout-api.reepay.com/v1/session/recurring',
					$params
				);
				if ( is_wp_error( $result ) ) {
					/** @var WP_Error $result */
					return array(
						'result'  => 'failure',
						'message' => $result->get_error_message()
					);
				}

				return array(
					'result'   => 'success',
					'redirect' => $result['url']
				);
			}
		}

		// Get Customer reference
		$customer_handle = $this->api->get_customer_handle_order( $order->get_id() );

		$data = [
			'country'         => $country,
			'customer_handle' => $customer_handle,
			'test_mode'       => $this->test_mode,
			'return_url'      => $this->get_return_url( $order ),
			'language'        => $this->get_language(),
		];

		$order_handle = rp_get_order_handle( $order );

		// Initialize Payment
		$params = [
			'locale'     => $this->get_language(),
			'recurring'  => apply_filters( 'order_contains_reepay_subscription', $maybe_save_card || order_contains_subscription( $order ) || wcs_is_payment_change(), $order ),
			'order'      => [
				'handle'          => $order_handle,
				'amount'          => $this->skip_order_lines === 'yes' ? rp_prepare_amount( $order->get_total(), $order->get_currency() ) : null,
				'order_lines'     => $this->skip_order_lines === 'no' ? $this->get_order_items( $order ) : null,
				'currency'        => $order->get_currency(),
				'customer'        => [
					'test'        => $this->test_mode === 'yes',
					'handle'      => $customer_handle,
					'email'       => $order->get_billing_email(),
					'address'     => $order->get_billing_address_1(),
					'address2'    => $order->get_billing_address_2(),
					'city'        => $order->get_billing_city(),
					'phone'       => $order->get_billing_phone(),
					'company'     => $order->get_billing_company(),
					'vat'         => '',
					'first_name'  => $order->get_billing_first_name(),
					'last_name'   => $order->get_billing_last_name(),
					'postal_code' => $order->get_billing_postcode()
				],
				'billing_address' => [
					'attention'         => '',
					'email'             => $order->get_billing_email(),
					'address'           => $order->get_billing_address_1(),
					'address2'          => $order->get_billing_address_2(),
					'city'              => $order->get_billing_city(),
					'phone'             => $order->get_billing_phone(),
					'company'           => $order->get_billing_company(),
					'vat'               => '',
					'first_name'        => $order->get_billing_first_name(),
					'last_name'         => $order->get_billing_last_name(),
					'postal_code'       => $order->get_billing_postcode(),
					'state_or_province' => $order->get_billing_state()
				],
			],
			'accept_url' => $this->get_return_url( $order ),
			'cancel_url' => $order->get_cancel_order_url(),
		];

		// Get setting from parent method
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );

		$have_sub = wc_cart_only_reepay_subscriptions() || wcs_cart_only_subscriptions();

		if ( $params['recurring'] ) {
			$params['button_text'] = $settings['payment_button_text'];
		}

		if ( ! empty( $country ) ) {
			$params['order']['customer']['country']        = $country;
			$params['order']['billing_address']['country'] = $country;
		}

		// skip order lines if calculated amount not equal to total order amount
		/*if ($this->get_calculated_amount($order) != rp_prepare_amount($order->get_total(), $order->get_currency())) {
			$params['order']['amount'] = rp_prepare_amount($order->get_total(), $order->get_currency());
			$params['order']['order_lines'] = null;
		}*/

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		if ( $order->needs_shipping_address() ) {

			$params['order']['shipping_address'] = [
				'attention'         => '',
				'email'             => $order->get_billing_email(),
				'address'           => $order->get_shipping_address_1(),
				'address2'          => $order->get_shipping_address_2(),
				'city'              => $order->get_shipping_city(),
				'phone'             => $order->get_billing_phone(),
				'company'           => $order->get_shipping_company(),
				'vat'               => '',
				'first_name'        => $order->get_shipping_first_name(),
				'last_name'         => $order->get_shipping_last_name(),
				'postal_code'       => $order->get_shipping_postcode(),
				'state_or_province' => $order->get_shipping_state()
			];

			if ( ! empty( $country ) ) {
				$params['order']['shipping_address']['country'] = $country;
			}

//			if (!strlen($params['order']['shipping_address'])) {
//				$params['order']['shipping_address'] = $params['order']['billing_address'];
//			}
		}

		if ( $order->get_payment_method() == 'reepay_mobilepay_subscriptions' ) {
			$params['parameters']['mps_ttl'] = "PT24H";
		}

		// Try to charge with saved token
		if ( absint( $token_id ) > 0 ) {
			$token = new WC_Payment_Token_Reepay( $token_id );
			if ( ! $token->get_id() ) {
				wc_add_notice( __( 'Failed to load token.', 'reepay-checkout-gateway' ), 'error' );

				return false;
			}

			// Check access
			if ( $token->get_user_id() !== $order->get_user_id() ) {
				wc_add_notice( __( 'Access denied.', 'reepay-checkout-gateway' ), 'error' );

				return false;
			}

			$params['card_on_file']                  = $token->get_token();
			$params['card_on_file_require_cvv']      = false;
			$params['card_on_file_require_exp_date'] = false;
			unset( $params['recurring'] );

			if ( abs( $order->get_total() ) < 0.01 ) {
				// Don't charge payment if zero amount
				if ( wcs_cart_only_subscriptions() ) {
					$result = $this->api->recurring( $this->payment_methods, $order, $data, $token->get_token(), $params['button_text'] );

					if ( is_wp_error( $result ) ) {
						/** @var WP_Error $result */
						throw new Exception( $result->get_error_message(), $result->get_error_code() );
					}

					if ( ! empty( $result['id'] ) ) {
						update_post_meta( $order_id, 'reepay_session_id', $result['id'] );
					}

					if ( is_wp_error( $result ) ) {
						/** @var WP_Error $result */
						throw new Exception( $result->get_error_message(), $result->get_error_code() );
					}
				}

				$order->payment_complete();

			} else {
				// Replace token
				try {
					self::assign_payment_token( $order, $token );
				} catch ( Exception $e ) {
					$order->add_order_note( $e->getMessage() );

					return array(
						'result'  => 'failure',
						'message' => $e->getMessage()
					);
				}

				if ( wc_cart_only_reepay_subscriptions() ) {
					$method = $this->api->request( 'GET', 'https://api.reepay.com/v1/payment_method/' . $token->get_token() );
					if ( is_wp_error( $method ) ) {
						wc_add_notice( $method->get_error_message(), 'error' );

						return false;
					} elseif ( ! empty( $method ) && $method['state'] == 'active' ) {

						$data = [
							'payment_method' => $method['id'],
							'customer'       => $method['customer'],
						];

						do_action( 'reepay_create_subscription', $data, $order );
					}
				} elseif ( wcs_cart_have_subscription() ) {
					try {
						return $this->process_session_charge( $params, $order );
					} catch ( Exception $e ) {
						throw new Exception( $e->get_error_message(), $e->get_error_code() );
					}

				} else {
					$order_lines = $this->skip_order_lines === 'no' ? $this->get_order_items( $order ) : null;
					$amount      = $this->skip_order_lines === 'yes' ? $order->get_total() : null;
					// Charge payment
					$result = $this->api->charge( $order, $token->get_token(), $amount, $order->get_currency(), $order_lines );

					if ( is_wp_error( $result ) ) {
						wc_add_notice( $result->get_error_message(), 'error' );

						return false;
					}
				}

				// Settle the charge
				do_action( 'reepay_instant_settle', $order );

			}

			try {
				self::assign_payment_token( $order, $token->get_id() );
			} catch ( Exception $e ) {
				$order->add_order_note( $e->getMessage() );
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// "Save Card" flag
		$order->update_meta_data( '_reepay_maybe_save_card', $maybe_save_card );
		$order->save_meta_data();

		if ( ! empty( $customer_handle ) && $order->get_customer_id() == 0 ) {
			$this->api->request(
				'PUT',
				'https://api.reepay.com/v1/customer/' . $customer_handle,
				$params['order']['customer']
			);
		}


		if ( class_exists( 'WC_Reepay_Renewals' ) && WC_Reepay_Renewals::is_order_contain_subscription( $order ) ) {
			$have_sub = true;
		}


		$only_items_lines = [];

		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */

			if ( ! wcr_is_subscription_product( $order_item->get_product() ) ) {
				$only_items_lines[] = $order_item;
			}
		}

		// If here's Subscription or zero payment
		if ( ( $have_sub ) && ( abs( $order->get_total() ) < 0.01 || empty( $only_items_lines ) ) ) {

			$result = $this->api->recurring( $this->payment_methods, $order, $data, false, $params['button_text'] );

			if ( is_wp_error( $result ) ) {
				/** @var WP_Error $result */
				throw new Exception( $result->get_error_message(), $result->get_error_code() );
			}

			if ( ! empty( $result['id'] ) ) {
				update_post_meta( $order_id, 'reepay_session_id', $result['id'] );
			}

			do_action( 'reepay_instant_settle', $order );

			$redirect = '#!reepay-checkout';

			if ( ! empty( $result['url'] ) ) {
				$redirect = $result['url'];
			}

			return array(
				'result'             => 'success',
				'redirect'           => $redirect,
				'is_reepay_checkout' => true,
				'reepay'             => $result,
				'reepay_id'          => $result['id'],
				'accept_url'         => $this->get_return_url( $order ),
				'cancel_url'         => $order->get_cancel_order_url()
			);
		}

		try {
			return $this->process_session_charge( $params, $order );
		} catch ( Exception $e ) {
			throw new Exception( $e->get_error_message(), $e->get_error_code() );
		}

	}

	public function process_session_charge( $params, $order ) {

		$result = $this->api->request(
			'POST',
			'https://checkout-api.reepay.com/v1/session/charge',
			$params
		);

		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */
			if ( 'yes' === $this->handle_failover ) {
				// invoice with handle $params['order']['handle'] already exists and authorized/settled
				// try to create another invoice with unique handle in format order-id-time()
				if ( in_array( $result->get_error_code(), [ 105, 79, 29, 99, 72 ] ) ) {
					$handle                    = rp_get_order_handle( $order, true );
					$params['order']['handle'] = $handle;

					update_post_meta( $order->get_id(), '_reepay_order', $handle );

					$result = $this->api->request(
						'POST',
						'https://checkout-api.reepay.com/v1/session/charge',
						$params
					);
					if ( is_wp_error( $result ) ) {
						/** @var WP_Error $result */
						return array(
							'result'  => 'failure',
							'message' => $result->get_error_message()
						);
					}
				} else {
					return array(
						'result'  => 'failure',
						'message' => $result->get_error_message()
					);
				}
			} else {
				return array(
					'result'  => 'failure',
					'message' => $result->get_error_message()
				);
			}
		}

		if ( ! empty( $result['id'] ) ) {
			update_post_meta( $order->get_id(), 'reepay_session_id', $result['id'] );
		}

		if ( is_checkout_pay_page() ) {
			if ( $this->payment_type === self::METHOD_OVERLAY ) {
				return array(
					'result'   => 'success',
					'redirect' => sprintf( '#!reepay-pay?rid=%s&accept_url=%s&cancel_url=%s',
						$result['id'],
						html_entity_decode( $this->get_return_url( $order ) ),
						html_entity_decode( $order->get_cancel_order_url() )
					),
				);
			} else {
				return array(
					'result'   => 'success',
					'redirect' => $result['url'],
				);
			}
		}

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message(), $result->get_error_code() );
		} else {
			return array(
				'result'             => 'success',
				'redirect'           => '#!reepay-checkout',
				'is_reepay_checkout' => true,
				'reepay'             => $result,
				'reepay_id'          => $result['id'],
				'accept_url'         => $this->get_return_url( $order ),
				'cancel_url'         => add_query_arg(
					array( 'action' => 'reepay_cancel', 'order_id' => $order->get_id() ),
					admin_url( 'admin-ajax.php' )
				)
			);
		}
	}

	/**
	 * Ajax: Cancel Payment
	 *
	 * @throws Exception
	 */
	public function reepay_cancel() {
		if ( ! isset( $_GET['order_id'] ) ) {
			return;
		}

		$order   = wc_get_order( wc_clean( $_GET['order_id'] ) );
		$gateway = rp_get_payment_method( $order );
		$result  = $gateway->api->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			return;
		}

		if ( 'failed' == $result['state'] ) {
			if ( count( $result['transactions'] ) > 0 &&
			     isset( $result['transactions'][0]['card_transaction']['acquirer_message'] )
			) {
				$message = $result['transactions'][0]['card_transaction']['acquirer_message'];

				$order->add_order_note( 'Payment failed. Error from acquire: ' . $message );
				wc_add_notice( __( 'Payment error: ', 'error' ) . $message, 'error' );
			}

			wp_redirect( wc_get_cart_url() );
			exit();
		}
	}

	/**
	 * Payment confirm action
	 * @return void
	 */
	public function payment_confirm() {
		if ( ! ( is_wc_endpoint_url( 'order-received' ) ) ) {
			return;
		}

		if ( empty( $_GET['id'] ) ) {
			return;
		}

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		if ( ! $order_id = wc_get_order_id_by_order_key( $_GET['key'] ) ) {
			wc_add_notice( __( 'Cannot found the order', 'reepay-checkout-gateway' ), 'error' );

			return;
		}

		if ( ! $order = wc_get_order( $order_id ) ) {
			wc_add_notice( __( 'Cannot found the order', 'reepay-checkout-gateway' ), 'error' );

			return;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$this->log( sprintf( 'accept_url: Incoming data: %s', var_export( $_GET, true ) ) );

		// Save Payment Method
		$maybe_save_card = $order->get_meta( '_reepay_maybe_save_card' );

		if ( ! empty( $_GET['payment_method'] ) && ( $maybe_save_card || order_contains_subscription( $order ) ) ) {
			$this->reepay_save_token( $order, wc_clean( $_GET['payment_method'] ) );
		}

		// @see WC_Reepay_Thankyou::thankyou_page()
	}

	/**
	 * WebHook Callback
	 * @return void
	 */
	public function return_handler() {
		try {
			$raw_body = file_get_contents( 'php://input' );
			$this->log( sprintf( 'WebHook: Initialized %s from %s', $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'] ) );
			$this->log( sprintf( 'WebHook: Post data: %s', var_export( $raw_body, true ) ) );
			$data = @json_decode( $raw_body, true );
			if ( ! $data ) {
				throw new Exception( __( 'Missing parameters', 'reepay-checkout-gateway' ) );
			}

			// Get Secret
			if ( ! ( $secret = get_transient( 'reepay_webhook_settings_secret' ) ) ) {
				$result = $this->api->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
				if ( is_wp_error( $result ) ) {
					/** @var WP_Error $result */
					throw new Exception( $result->get_error_message(), $result->get_error_code() );
				}

				$secret = $result['secret'];

				set_transient( 'reepay_webhook_settings_secret', $secret, HOUR_IN_SECONDS );
			}

			// Verify secret
			$check = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], $secret, true ) );
			if ( $check !== $data['signature'] ) {
				throw new Exception( __( 'Signature verification failed', 'reepay-checkout-gateway' ) );
			}

			( new WC_Reepay_Webhook( $data ) )->process();

			http_response_code( 200 );
		} catch ( Exception $e ) {
			$this->log( sprintf( 'WebHook: Error: %s', $e->getMessage() ) );
			http_response_code( 200 );
		}
	}

	/**
	 * Enqueue the webhook processing.
	 *
	 * @param $raw_body
	 *
	 * @return void
	 */
	public function enqueue_webhook_processing( $raw_body ) {
		$data = @json_decode( $raw_body, true );

		// Create Background Process Task
		$background_process = new WC_Background_Reepay_Queue();
		$background_process->push_to_queue(
			array(
				'payment_method_id' => $this->id,
				'webhook_data'      => $raw_body,
			)
		);
		$background_process->save();

		$this->log(
			sprintf( 'WebHook: Task enqueued. ID: %s',
				$data['id']
			)
		);
	}

	/**
	 * Get parent settings
	 *
	 * @return array
	 */
	protected function get_parent_settings() {
		// Get setting from parent method
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( isset( $settings['private_key'] ) ) {
			$settings['private_key'] = apply_filters( 'woocommerce_reepay_private_key', $settings['private_key'] );
		}

		if ( isset( $settings['private_key_test'] ) ) {
			$settings['private_key_test'] = apply_filters( 'woocommerce_reepay_private_key_test', $settings['private_key_test'] );
		}

		return array_merge( array(
			'enabled'                 => 'no',
			'private_key'             => $this->private_key,
			'private_key_test'        => $this->private_key_test,
			'test_mode'               => $this->test_mode,
			'payment_type'            => $this->payment_type,
			'payment_methods'         => $this->payment_methods,
			'settle'                  => $this->settle,
			'language'                => $this->language,
			'save_cc'                 => $this->save_cc,
			'debug'                   => $this->debug,
			'logos'                   => $this->logos,
			'logo_height'             => $this->logo_height,
			'skip_order_lines'        => $this->skip_order_lines,
			'enable_order_autocancel' => $this->enable_order_autocancel,
			'is_webhook_configured'   => isset( $settings['is_webhook_configured'] ) ?
				$settings['is_webhook_configured'] : $this->is_webhook_configured
		), $settings );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 *
	 * @return array
	 * @see WC_Log_Levels
	 * Get parent settings
	 *
	 */
	public function log( $message, $level = 'info' ) {
		// Is Enabled
		if ( 'yes' !== $this->debug ) {
			return;
		}

		// Get Logger instance
		$logger = wc_get_logger();

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
		}

		$logger->log( $level, $message, array(
			'source'  => $this->id,
			'_legacy' => true
		) );
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param int $order_id
	 * @param float $amount
	 * @param string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		try {
			$this->refund_payment( $order, $amount, $reason );

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'refund', $e->getMessage() );
		}
	}

	/**
	 * Get Order lines.
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_order_items( $order, $not_settled = false ) {
		$prices_incl_tax = wc_prices_include_tax();

		$items      = [];
		$setup_fees = [];
		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */

			if ( wcr_is_subscription_product( $order_item->get_product() ) ) {
				$fee = $order_item->get_product()->get_meta( '_reepay_subscription_fee' );
				if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ) {
					$setup_fees[] = $order_item->get_product()->get_name() . ' - ' . $fee["text"];
				}
				continue;
			}

			$price        = $order->get_line_subtotal( $order_item, false, false );
			$priceWithTax = $order->get_line_subtotal( $order_item, true, false );
			$tax          = $priceWithTax - $price;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;
			$unitPrice    = round( ( $prices_incl_tax ? $priceWithTax : $price ) / $order_item->get_quantity(), 2 );

			if ( $not_settled && ! empty( $order_item->get_meta( 'settled' ) ) ) {
				continue;
			}

			$items[] = array(
				'ordertext'       => $order_item->get_name(),
				'quantity'        => $order_item->get_quantity(),
				'amount'          => rp_prepare_amount( $unitPrice, $order->get_currency() ),
				'vat'             => round( $taxPercent / 100, 2 ),
				'amount_incl_vat' => $prices_incl_tax
			);
		}

		// Add Shipping Line
		if ( (float) $order->get_shipping_total() > 0 ) {
			foreach ( $order->get_items( 'shipping' ) as $item_shipping ) {
				$prices_incl_tax = wc_prices_include_tax();

				$price = WC_Reepay_Order_Capture::get_item_price( $item_shipping, $order );

				$tax        = $price['with_tax'] - $price['original'];
				$taxPercent = ( $tax > 0 ) ? 100 / ( $price['original'] / $tax ) : 0;
				$unitPrice  = round( ( $prices_incl_tax ? $price['with_tax'] : $price['original'] ) / $item_shipping->get_quantity(), 2 );

				if ( $not_settled && ! empty( $item_shipping->get_meta( 'settled' ) ) ) {
					continue;
				}

				$items[] = [
					'ordertext'       => $item_shipping->get_name(),
					'quantity'        => $item_shipping->get_quantity(),
					'amount'          => rp_prepare_amount( $unitPrice, $order->get_currency() ),
					'vat'             => round( $taxPercent / 100, 2 ),
					'amount_incl_vat' => $prices_incl_tax
				];
			}
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			/** @var WC_Order_Item_Fee $order_fee */

			if ( ! empty( $setup_fees ) && in_array( $order_fee['name'], $setup_fees ) ) {
				continue;
			}

			$fee        = $order_fee->get_total();
			$tax        = $order_fee->get_total_tax();
			$feeWithTax = $fee + $tax;
			$taxPercent = ( $tax > 0 ) ? round( 100 / ( $fee / $tax ) ) : 0;

			if ( $not_settled && ! empty( $order_fee->get_meta( 'settled' ) ) ) {
				continue;
			}

			$items[] = array(
				'ordertext'       => $order_fee->get_name(),
				'quantity'        => 1,
				'amount'          => rp_prepare_amount( $prices_incl_tax ? $feeWithTax : $fee, $order->get_currency() ),
				'vat'             => round( $taxPercent / 100, 2 ),
				'amount_incl_vat' => $prices_incl_tax
			);
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount        = $order->get_total_discount( true );
			$discountWithTax = $order->get_total_discount( false );
			$tax             = $discountWithTax - $discount;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$items[] = array(
				'ordertext'       => __( 'Discount', 'reepay-checkout-gateway' ),
				'quantity'        => 1,
				'amount'          => round( - 1 * rp_prepare_amount( $prices_incl_tax ? $discountWithTax : $discount, $order->get_currency() ) ),
				'vat'             => round( $taxPercent / 100, 2 ),
				'amount_incl_vat' => $prices_incl_tax
			);
		}

		// Add "Gift Up!" discount
		if ( defined( 'GIFTUP_ORDER_META_CODE_KEY' ) &&
		     defined( 'GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY' )
		) {
			if ( $order->meta_exists( GIFTUP_ORDER_META_CODE_KEY ) ) {
				$code              = $order->get_meta( GIFTUP_ORDER_META_CODE_KEY );
				$requested_balance = $order->get_meta( GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY );

				if ( $requested_balance > 0 ) {
					$items[] = array(
						'ordertext'       => sprintf( __( 'Gift card (%s)', 'reepay-checkout-gateway' ), $code ),
						'quantity'        => 1,
						'amount'          => rp_prepare_amount( - 1 * $requested_balance, $order->get_currency() ),
						'vat'             => 0,
						'amount_incl_vat' => $prices_incl_tax
					);
				}
			}
		}

		return $items;
	}

	/**
	 * calculate amount from order rows that
	 * can be compared with amount from order
	 *
	 * @param $order
	 *
	 * @return float|int
	 */
	private function get_calculated_amount( $order ) {
		$order_items = $this->get_order_items( $order );
		$order_total = 0;

		foreach ( $order_items as $item ) {
			$order_total += $item['amount'] * $item['quantity'];
		}

		return $order_total;
	}

	/**
	 * Get Language
	 * @return string
	 */
	protected function get_language() {
		if ( ! empty( $this->language ) ) {
			return $this->language;
		}

		$locale = get_locale();
		if ( in_array(
			$locale,
			array( 'en_US', 'da_DK', 'sv_SE', 'no_NO', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL' )
		) ) {
			return $locale;
		}

		return 'en_US';
	}

	/**
	 * Converts a Reepay card_type into a logo.
	 *
	 * @param string $card_type is the Reepay card type
	 *
	 * @return string the logo
	 */
	public function get_logo( $card_type ) {
		switch ( $card_type ) {
			case 'visa':
				$image = 'visa';
				break;
			case 'mc':
				$image = 'mastercard';
				break;
			case 'dankort':
			case 'visa_dk':
				$image = 'dankort';
				break;
			case 'ffk':
				$image = 'forbrugsforeningen';
				break;
			case 'visa_elec':
				$image = 'visa-electron';
				break;
			case 'maestro':
				$image = 'maestro';
				break;
			case 'amex':
				$image = 'american-express';
				break;
			case 'diners':
				$image = 'diners';
				break;
			case 'discover':
				$image = 'discover';
				break;
			case 'jcb':
				$image = 'jcb';
				break;
			case 'mobilepay':
			case 'ms_subscripiton':
				$image = 'mobilepay';
				break;
			case 'viabill':
				$image = 'viabill';
				break;
			case 'klarna_pay_later':
			case 'klarna_pay_now':
				$image = 'klarna';
				break;
			case 'resurs':
				$image = 'resurs';
				break;
			case 'china_union_pay':
				$image = 'cup';
				break;
			case 'paypal':
				$image = 'paypal';
				break;
			case 'applepay':
				$image = 'applepay';
				break;
			case 'googlepay':
				$image = 'googlepay';
				break;
			case 'vipps':
				$image = 'vipps';
				break;
			default:
				//$image = 'reepay.png';
				// Use an image of payment method
				$logos = $this->logos;
				$logo  = array_shift( $logos );

				return untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/images/' . $logo . '.png';
		}

		return untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/images/svg/' . $image . '.logo.svg';
	}
}
