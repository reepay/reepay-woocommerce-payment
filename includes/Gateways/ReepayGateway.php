<?php
/**
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Exception;
use SitePress;
use Reepay\Checkout\LoggingTrait;
use Reepay\Checkout\Tokens\TokenReepay;
use Reepay\Checkout\Tokens\TokenReepayTrait;
use WC_Admin_Settings;
use WC_Countries;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Payment_Gateway;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
use WC_Reepay_Renewals;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Class ReepayGateway
 *
 * @package Reepay\Checkout\Gateways
 */
abstract class ReepayGateway extends WC_Payment_Gateway {
	use TokenReepayTrait, LoggingTrait;

	const METHOD_WINDOW = 'WINDOW';

	const METHOD_OVERLAY = 'OVERLAY';

	/**
	 * @var string
	 */
	public $test_mode = 'yes';

	/**
	 * @var string
	 */
	public $private_key;

	/**
	 * @var string
	 */
	public $private_key_test;

	/**
	 * @var string
	 */

	public $public_key;

	/**
	 * @var string[]
	 */
	public $settle = array(
		InstantSettle::SETTLE_VIRTUAL,
		InstantSettle::SETTLE_PHYSICAL,
		InstantSettle::SETTLE_RECURRING,
		InstantSettle::SETTLE_FEE,
	);

	/**
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * @var string
	 */
	public $language = 'en_US';

	/**
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
		'resursbank',
	);

	/**
	 * @var string
	 */
	public $payment_type = 'OVERLAY';

	/**
	 * @var string
	 */
	public $save_cc = 'yes';

	/**
	 * @var string
	 */
	public $logo_height = '';

	/**
	 * Skip order lines to Reepay and use order totals instead
	 *
	 * @var string
	 */
	public $skip_order_lines = 'no';

	/**
	 * If automatically cancel unpaid orders should be ignored
	 *
	 * @var string
	 */
	public $enable_order_autocancel = 'no';

	/**
	 * Email address for notification about failed webhooks
	 *
	 * @var string
	 */
	public $failed_webhooks_email = '';

	/**
	 * If webhooks have been configured
	 *
	 * @var string
	 */
	public $is_webhook_configured = 'no';

	/**
	 * Order handle failover.
	 *
	 * @var string
	 */
	public $handle_failover = 'yes';

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array();

	/**
	 * @var string
	 */
	private $logging_source;

	/**
	 * ReepayGateway constructor.
	 */
	public function __construct() {
		$this->logging_source = $this->id;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->enabled     = $this->settings['enabled'] ?? 'no';
		$this->title       = $this->settings['title'] ?? 'no';
		$this->description = $this->settings['description'] ?? 'no';

		add_action( 'admin_notices', array( $this, 'admin_notice_api_action' ) );

		add_action( 'the_post', array( $this, 'payment_confirm' ) );

		add_action( 'wp_ajax_reepay_cancel_payment', array( $this, 'reepay_cancel_payment' ) );
		add_action( 'wp_ajax_nopriv_reepay_cancel_payment', array( $this, 'reepay_cancel_payment' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'action_checkout_create_order_line_item' ), 10, 4 );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if payment method activated in repay
	 *
	 * @return bool
	 */
	public function check_is_active() {
		$gateways_reepay = get_transient( 'gateways_reepay' );
		if ( empty( $gateways_reepay ) ) {
			$gateways_reepay = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/agreement?only_active=true' );
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

	/**
	 * Check if current page is gateway settings page
	 *
	 * @return bool
	 */
	public function is_gateway_settings_page() {
		return isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] &&
			   ! empty( $_GET['section'] ) && $_GET['section'] === $this->id;
	}

	/**
	 * @param bool $is_test use test or live reepay api keys.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function get_account_info( $is_test = false ) {
		if ( $this->is_gateway_settings_page() ) {
			$key = 'account_info';

			if ( $is_test ) {
				$key = 'account_info_test';
			}

			$account_info = get_transient( $key );
			if ( empty( $account_info ) ) {
				$account_info = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/account' );
				set_transient( $key, $account_info, 5 );
			}

			return $account_info;
		}

		return array();
	}


	/**
	 * @return string
	 */
	public static function get_default_api_url() {
		$default_wc_api_url = WC()->api_request_url( '' );

		if ( class_exists( SitePress::class ) ) {
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

		return $default_wc_api_url . 'WC_Gateway_Reepay/';
	}

	/**
	 * @return bool
	 *
	 * @throws Exception Never, just for phpcs.
	 */
	public function is_webhook_configured() {
		try {
			$request = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
			if ( is_wp_error( $request ) ) {
				/** @var WP_Error $request */
				if ( ! empty( $request->get_error_code() ) ) {
					throw new Exception( $request->get_error_message(), intval( $request->get_error_code() ) );
				}
			}

			$webhook_url = self::get_default_api_url();

			$alert_emails = $request['alert_emails'];

			// The webhook settings of the payment plugin.
			$alert_email = '';
			if ( ! empty( $this->settings['failed_webhooks_email'] ) &&
				 is_email( $this->settings['failed_webhooks_email'] )
			) {
				$alert_email = $this->settings['failed_webhooks_email'];
			}

			$exist_waste_urls = false;

			$urls = array();

			foreach ( $request['urls'] as $url ) {
				if ( strpos( $url, $webhook_url ) === false || // either another site or exact url match.
					 $url === $webhook_url ) {
					$urls[] = $url;
				} else {
					$exist_waste_urls = true;
				}
			}

			// Verify the webhook settings.
			if ( ! $exist_waste_urls &&
				 in_array( $webhook_url, $urls, true )
				 && ( empty( $alert_email ) || in_array( $alert_email, $alert_emails, true ) )
			) {
				$this->update_option( 'is_webhook_configured', 'yes' );
				return true;
			}

			// Update the webhook settings.
			try {
				if ( ! in_array( $webhook_url, $urls, true ) ) {
					$urls[] = $webhook_url;
				}

				if ( ! empty( $alert_email ) && is_email( $alert_email ) ) {
					$alert_emails[] = $alert_email;
				}

				$data = array(
					'urls'         => array_unique( $urls ),
					'disabled'     => false,
					'alert_emails' => array_unique( $alert_emails ),
				);

				$request = reepay()->api( $this )->request( 'PUT', 'https://api.reepay.com/v1/account/webhook_settings', $data );
				if ( is_wp_error( $request ) ) {
					/** @var WP_Error $request */
					throw new Exception( $request->get_error_message(), $request->get_error_code() );
				}

				$this->log(
					array(
						'source' => 'WebHook has been successfully created/updated',
						$request,
					)
				);

				$this->update_option( 'is_webhook_configured', 'yes' );
				WC_Admin_Settings::add_message( __( 'Reepay: WebHook has been successfully created/updated', 'reepay-checkout-gateway' ) );

				return true;
			} catch ( Exception $e ) {
				$this->log(
					array(
						'source' => 'WebHook creation/update has been failed',
						$request,
					)
				);

				$this->update_option( 'is_webhook_configured', 'no' );
				WC_Admin_Settings::add_error( __( 'Reepay: WebHook creation/update has been failed', 'reepay-checkout-gateway' ) );
			}
		} catch ( Exception $e ) {
			$this->update_option( 'is_webhook_configured', 'no' );
			WC_Admin_Settings::add_error( __( 'Unable to retrieve the webhook settings. Wrong api credentials?', 'reepay-checkout-gateway' ) );
		}

		return false;
	}

	/**
	 * Redirect to the gateway settings page after enabling the gateway if it needs to be configured.
	 *
	 * @see WC_AJAX::toggle_gateway_enabled
	 *
	 * @return bool
	 */
	public function needs_setup() {
		return doing_action( 'wp_ajax_woocommerce_toggle_gateway_enabled' ) && ! $this->check_is_active();
	}

	/**
	 * Generate Gateway Status HTML.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 *
	 * @return string
	 * @see WC_Settings_API::generate_settings_html
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

		$is_active = $this->check_is_active();

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?>
					<?php echo $this->get_tooltip_html( $data ); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span>
					</legend>

					<?php if ( $is_active ) : ?>
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
						   value="<?php echo esc_attr( $is_active ); ?>"/>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Check is Capture possible
	 *
	 * @param WC_Order|int $order order to check.
	 * @param float|false  $amount amount to capture.
	 *
	 * @return bool
	 */
	public function can_capture( $order, $amount = false ) {
		return reepay()->api( $this )->can_capture( $order, $amount );
	}

	/**
	 * Check is Cancel possible
	 *
	 * @param WC_Order|int $order order to check.
	 *
	 * @return bool
	 */
	public function can_cancel( $order ) {
		return reepay()->api( $this )->can_cancel( $order );
	}

	/**
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 */
	public function can_refund( $order ) {
		return reepay()->api( $this )->can_refund( $order );
	}

	/**
	 * Capture.
	 *
	 * @param WC_Order|int $order order to capture.
	 * @param float|null   $amount amount to capture. Null to capture order total.
	 *
	 * @return void
	 * @throws Exception If capture error.
	 */
	public function capture_payment( $order, $amount = null ) {
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( 'Order is already canceled' );
		}

		$result = reepay()->api( $this )->capture_payment( $order, $amount );

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
	}

	/**
	 * Cancel.
	 *
	 * @param WC_Order|int $order order to cancel.
	 *
	 * @throws Exception
	 */
	public function cancel_payment( $order ) {
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( 'Order is already canceled' );
		}

		$result = reepay()->api( $this )->cancel_payment( $order );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
		add_action( 'the_post', array( $this, 'payment_confirm' ) );
	}

	/**
	 * Refund
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 * @param string       $reason
	 *
	 * @return void
	 * @throws Exception
	 */
	public function refund_payment( $order, $amount = false, $reason = '' ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Check if the order is cancelled - if so - then return as nothing has happened
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( 'Order is already canceled' );
		}

		if ( ! $this->can_refund( $order ) ) {
			throw new Exception( 'Payment can\'t be refunded.' );
		}

		$result = reepay()->api( $this )->refund( $order, $amount, $reason );
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

			if ( ! empty( $error ) ) :
				?>
				<div class="error notice is-dismissible">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
				<?php
				set_transient( 'reepay_api_action_error', null, 1 );
			endif;

			if ( ! empty( $success ) ) :
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
				$html .= '<img src="' . esc_url( reepay()->get_setting( 'images_url' ) . $logo . '.png' ) . '" alt="' . esc_attr( sprintf( __( 'Pay with %s on Reepay', 'reepay-checkout-gateway' ), $this->get_title() ) ) . '" />';
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
	}

	/**
	 * If There are no payment fields show the description if set.
	 *
	 * @return void
	 */
	public function payment_fields() {
		reepay()->get_template(
			'checkout/payment-fields.php',
			array(
				'description' => $this->get_description(),
			)
		);
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 * @throws Exception
	 */
	public function process_payment( $order_id ) {
		$is_woo_blocks_checkout_request = false;

		if ( 'application/json' === $_SERVER['CONTENT_TYPE'] ) {
			$is_woo_blocks_checkout_request = true;

			try {
				$_POST = json_decode( file_get_contents( 'php://input' ), true );

				foreach ( $_POST['payment_data'] ?? array() as $data ) {
					if ( empty( $_POST[ $data['key'] ] ) ) {
						$_POST[ $data['key'] ] = $data['value'];
					}
				}
			} catch ( Exception $e ) {
				return array(
					'messages' => __( 'Wrong Request. Try again', 'reepay-checkout-gateway' ),
					'result'   => 'failure',
				);
			}
		}

		$order = wc_get_order( $order_id );

		if ( $is_woo_blocks_checkout_request ) {
			/**
			 * Fix for zero total amount in woo blocks checkout integration
			 */
			$order->calculate_totals();
		}

		$token_id = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? wc_clean( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) : 'new';

		if ( 'yes' === $this->save_cc
			 && $token_id === 'new'
			 && isset( $_POST[ 'wc-' . $this->id . '-new-payment-method' ] )
			 && $_POST[ 'wc-' . $this->id . '-new-payment-method' ] !== false
		) {
			$maybe_save_card = $_POST[ 'wc-' . $this->id . '-new-payment-method' ] === 'true';
		} else {
			$maybe_save_card = wcs_cart_have_subscription();
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
			$customer_handle = reepay()->api( $this )->get_customer_handle_by_order( $order_id );

			if ( absint( $token_id ) > 0 ) {
				$token = new TokenReepay( $token_id );
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
						'message' => $e->getMessage(),
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
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				// Add new Card
				$params = array(
					'locale'          => $this->get_language(),
					'button_text'     => __( 'Add card', 'reepay-checkout-gateway' ),
					'create_customer' => array(
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
						'postal_code' => $order->get_billing_postcode(),
					),
					'accept_url'      => add_query_arg(
						array(
							'action' => 'reepay_finalize',
							'key'    => $order->get_order_key(),
						),
						admin_url( 'admin-ajax.php' )
					),
					'cancel_url'      => $order->get_cancel_order_url(),
				);

				if ( ! empty( $country ) ) {
					$params['create_customer']['country'] = $country;
				}

				if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
					$params['payment_methods'] = $this->payment_methods;
				}

				$result = reepay()->api( $this )->request(
					'POST',
					'https://checkout-api.reepay.com/v1/session/recurring',
					$params
				);
				if ( is_wp_error( $result ) ) {
					/** @var WP_Error $result */
					return array(
						'result'  => 'failure',
						'message' => $result->get_error_message(),
					);
				}

				return array(
					'result'   => 'success',
					'redirect' => $result['url'],
				);
			}
		}

		// Get Customer reference
		$customer_handle = reepay()->api( $this )->get_customer_handle_by_order( $order );

		$data = array(
			'country'         => $country,
			'customer_handle' => $customer_handle,
			'test_mode'       => $this->test_mode,
			'return_url'      => $this->get_return_url( $order ),
			'language'        => $this->get_language(),
		);

		$order_handle = rp_get_order_handle( $order );

		// Initialize Payment
		$params = array(
			'locale'     => $this->get_language(),
			'recurring'  => apply_filters( 'order_contains_reepay_subscription', $maybe_save_card || order_contains_subscription( $order ) || wcs_is_payment_change(), $order ),
			'order'      => array(
				'handle'          => $order_handle,
				'amount'          => $this->skip_order_lines === 'yes' ? rp_prepare_amount( $order->get_total(), $order->get_currency() ) : null,
				'order_lines'     => $this->skip_order_lines === 'no' ? $this->get_order_items( $order ) : null,
				'currency'        => $order->get_currency(),
				'customer'        => array(
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
					'postal_code' => $order->get_billing_postcode(),
				),
				'billing_address' => array(
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
					'state_or_province' => $order->get_billing_state(),
				),
			),
			'accept_url' => $this->get_return_url( $order ),
			'cancel_url' => $order->get_cancel_order_url(),
		);

		if ( $params['recurring'] ) {
			$params['button_text'] = reepay()->get_setting( 'payment_button_text' );
		}

		if ( ! empty( $country ) ) {
			$params['order']['customer']['country']        = $country;
			$params['order']['billing_address']['country'] = $country;
		}

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		if ( $order->needs_shipping_address() ) {

			$params['order']['shipping_address'] = array(
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
				'state_or_province' => $order->get_shipping_state(),
			);

			if ( ! empty( $country ) ) {
				$params['order']['shipping_address']['country'] = $country;
			}
		}

		if ( $order->get_payment_method() == 'reepay_mobilepay_subscriptions' ) {
			$params['parameters']['mps_ttl'] = 'PT24H';
		}

		// Try to charge with saved token
		if ( absint( $token_id ) > 0 ) {
			$token = new TokenReepay( $token_id );
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
					$result = reepay()->api( $this )->recurring( $this->payment_methods, $order, $data, $token->get_token(), $params['button_text'] );

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
						'message' => $e->getMessage(),
					);
				}

				if ( wc_cart_only_reepay_subscriptions() ) {
					$method = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/payment_method/' . $token->get_token() );
					if ( is_wp_error( $method ) ) {
						wc_add_notice( $method->get_error_message(), 'error' );

						return false;
					} elseif ( ! empty( $method ) && $method['state'] == 'active' ) {

						$data = array(
							'payment_method' => $method['id'],
							'customer'       => $method['customer'],
						);

						do_action( 'reepay_create_subscription', $data, $order );
					}
				} elseif ( wcs_cart_have_subscription() ) {
					return $this->process_session_charge( $params, $order );
				} else {
					$order_lines = $this->skip_order_lines === 'no' ? $this->get_order_items( $order ) : null;
					$amount      = $this->skip_order_lines === 'yes' ? $order->get_total() : null;
					// Charge payment
					$result = reepay()->api( $this )->charge( $order, $token->get_token(), $amount, $order_lines );

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
				'redirect' => $this->get_return_url( $order ),
			);
		}

		// "Save Card" flag
		$order->update_meta_data( '_reepay_maybe_save_card', $maybe_save_card );
		$order->save_meta_data();

		if ( ! empty( $customer_handle ) && $order->get_customer_id() == 0 ) {
			reepay()->api( $this )->request(
				'PUT',
				'https://api.reepay.com/v1/customer/' . $customer_handle,
				$params['order']['customer']
			);
		}

		$have_sub =  class_exists( WC_Reepay_Renewals::class ) && WC_Reepay_Renewals::is_order_contain_subscription( $order );

		$only_items_lines = array();

		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */

			if ( ! wcr_is_subscription_product( $order_item->get_product() ) ) {
				$only_items_lines[] = $order_item;
			}
		}

		// If here's Subscription or zero payment
		if ( ( $have_sub ) && ( abs( $order->get_total() ) < 0.01 || empty( $only_items_lines ) ) ) {

			$result = reepay()->api( $this )->recurring( $this->payment_methods, $order, $data, false, $params['button_text'] );

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
				'cancel_url'         => $order->get_cancel_order_url(),
			);
		}

		return $this->process_session_charge( $params, $order );
	}

	/**
	 * @param array    $params
	 * @param WC_Order $order
	 *
	 * @return array
	 * @throws Exception
	 */
	public function process_session_charge( $params, $order ) {

		$result = reepay()->api( $this )->request(
			'POST',
			'https://checkout-api.reepay.com/v1/session/charge',
			$params
		);

		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */
			if ( 'yes' === $this->handle_failover ) {
				// invoice with handle $params['order']['handle'] already exists and authorized/settled
				// try to create another invoice with unique handle in format order-id-time()
				if ( in_array( $result->get_error_code(), array( 105, 79, 29, 99, 72 ) ) ) {
					$handle                    = rp_get_order_handle( $order, true );
					$params['order']['handle'] = $handle;

					update_post_meta( $order->get_id(), '_reepay_order', $handle );

					$result = reepay()->api( $this )->request(
						'POST',
						'https://checkout-api.reepay.com/v1/session/charge',
						$params
					);
					if ( is_wp_error( $result ) ) {
						/** @var WP_Error $result */
						return array(
							'result'  => 'failure',
							'message' => $result->get_error_message(),
						);
					}
				} else {
					return array(
						'result'  => 'failure',
						'message' => $result->get_error_message(),
					);
				}
			} else {
				return array(
					'result'  => 'failure',
					'message' => $result->get_error_message(),
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
					'redirect' => sprintf(
						'#!reepay-pay?rid=%s&accept_url=%s&cancel_url=%s',
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
					array(
						'action'   => 'reepay_cancel',
						'order_id' => $order->get_id(),
					),
					admin_url( 'admin-ajax.php' )
				),
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
		$result  = reepay()->api( $gateway )->get_invoice_data( $order );
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
	 *
	 * @return void
	 * @throws Exception
	 * @see ThankyouPage::thankyou_page()
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
	}

	/**
	 * Apply settings from Reepay Checkout Gateway to other gateways. Use it in constructor
	 */
	protected function apply_parent_settings() {
		$this->private_key             = reepay()->get_setting( 'private_key' );
		$this->private_key_test        = reepay()->get_setting( 'private_key_test' );
		$this->test_mode               = reepay()->get_setting( 'test_mode' );
		$this->settle                  = reepay()->get_setting( 'settle' );
		$this->language                = reepay()->get_setting( 'language' );
		$this->debug                   = reepay()->get_setting( 'debug' );
		$this->payment_type            = reepay()->get_setting( 'payment_type' );
		$this->skip_order_lines        = reepay()->get_setting( 'skip_order_lines' );
		$this->enable_order_autocancel = reepay()->get_setting( 'enable_order_autocancel' );
		$this->is_webhook_configured   = reepay()->get_setting( 'is_webhook_configured' );
		$this->handle_failover         = reepay()->get_setting( 'handle_failover' );
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param int    $order_id
	 * @param float  $amount
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

	function action_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
		$line_discount     = $values['line_subtotal'] - $values['line_total'];
		$line_discount_tax = $values['line_subtotal_tax'] - $values['line_tax'];

		$item->update_meta_data( '_line_discount', $line_discount + $line_discount_tax );
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

		$items               = array();
		$setup_fees          = array();
		$sub_amount_discount = 0;
		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */

			if ( wcr_is_subscription_product( $order_item->get_product() ) ) {
				$fee = $order_item->get_product()->get_meta( '_reepay_subscription_fee' );
				if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ) {
					$setup_fees[] = $order_item->get_product()->get_name() . ' - ' . $fee['text'];
				}
				$sub_amount_discount += floatval( $order_item->get_meta( '_line_discount' ) );
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
				'amount_incl_vat' => $prices_incl_tax,
			);
		}

		// Add Shipping Line
		if ( (float) $order->get_shipping_total() > 0 ) {
			foreach ( $order->get_items( 'shipping' ) as $item_shipping ) {
				$prices_incl_tax = wc_prices_include_tax();

				$price = OrderCapture::get_item_price( $item_shipping, $order );

				$tax        = $price['with_tax'] - $price['original'];
				$taxPercent = ( $tax > 0 ) ? 100 / ( $price['original'] / $tax ) : 0;
				$unitPrice  = round( ( $prices_incl_tax ? $price['with_tax'] : $price['original'] ) / $item_shipping->get_quantity(), 2 );

				if ( $not_settled && ! empty( $item_shipping->get_meta( 'settled' ) ) ) {
					continue;
				}

				$items[] = array(
					'ordertext'       => $item_shipping->get_name(),
					'quantity'        => $item_shipping->get_quantity(),
					'amount'          => rp_prepare_amount( $unitPrice, $order->get_currency() ),
					'vat'             => round( $taxPercent / 100, 2 ),
					'amount_incl_vat' => $prices_incl_tax,
				);
			}
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			/** @var WC_Order_Item_Fee $order_fee */

			if ( ! empty( $setup_fees ) && in_array( $order_fee['name'], $setup_fees ) ) {
				continue;
			}

			$fee        = (float) $order_fee->get_total();
			$tax        = (float) $order_fee->get_total_tax();
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
				'amount_incl_vat' => $prices_incl_tax,
			);
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount        = $order->get_total_discount();
			$discountWithTax = $order->get_total_discount( false );
			$tax             = $discountWithTax - $discount;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$items[] = array(
				'ordertext'       => __( 'Discount', 'reepay-checkout-gateway' ),
				'quantity'        => 1,
				'amount'          => round( - 1 * rp_prepare_amount( $prices_incl_tax ? $discountWithTax : $discount, $order->get_currency() ) ) + ( $sub_amount_discount * 100 ),
				'vat'             => round( $taxPercent / 100, 2 ),
				'amount_incl_vat' => $prices_incl_tax,
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
						'amount_incl_vat' => $prices_incl_tax,
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Get Language
	 *
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
				// $image = 'reepay.png';
				// Use an image of payment method
				$logos = $this->logos;
				$logo  = array_shift( $logos );

				return reepay()->get_setting( 'logo_url' ) . $logo . '.png';
		}

		return reepay()->get_setting( 'logo_url' ) . 'svg/' . $image . '.logo.svg';
	}

	/**
	 * Initialise default settings form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'type'    => 'gateway_status',
				'label'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'default' => $this->test_mode,
			),
			'enabled'              => array(
				'title'    => __( 'Enable/Disable', 'reepay-checkout-gateway' ),
				'type'     => 'checkbox',
				'label'    => __( 'Enable plugin', 'reepay-checkout-gateway' ),
				'default'  => 'no',
				'disabled' => $this->is_gateway_settings_page() && ! $this->check_is_active(), // Check calls api, so use it only on gateway page.
			),
			'title'                => array(
				'title'       => __( 'Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => $this->method_title,
			),
			'description'          => array(
				'title'       => __( 'Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => $this->method_title,
			),
		);
	}
}
