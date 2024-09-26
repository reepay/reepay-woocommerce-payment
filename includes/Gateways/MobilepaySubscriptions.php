<?php
/**
 * MobilepaySubscriptions gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Api;

defined( 'ABSPATH' ) || exit();

/**
 * Class MobilepaySubscriptions
 *
 * @package Reepay\Checkout\Gateways
 */
class MobilepaySubscriptions extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'mobilepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'mobilepay_subscriptions',
	);

	/**
	 * MobilepaySubscriptions constructor.
	 */
	public function __construct() {
		$this->id                 = 'reepay_mobilepay_subscriptions';
		$this->has_fields         = true;
		$this->method_title       = __( 'Billwerk+ Pay - Mobilepay Subscriptions', 'reepay-checkout-gateway' );
		$this->method_description = '<span style="color:red">' . $this->warning_message() . '</span>';

		$this->supports = array(
			'products',
			'refunds',
			'add_payment_method',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		$this->logos = array( 'mobilepay' );

		parent::__construct();

		$this->apply_parent_settings();

		add_action( 'wp_ajax_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_nopriv_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	/**
	 * This payment method works only for subscription products
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return parent::is_available()
				&& ( ( is_checkout() && wcs_cart_have_subscription() )
					|| is_add_payment_method_page() );
	}

	/**
	 * If There are no payment fields show the description if set.
	 *
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();

		$this->tokenization_script();
		$this->save_payment_method_checkbox();
	}

	/**
	 * Process admin options and add a custom notice on successful save.
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		if ( $saved && $this->get_option( 'enabled' ) === 'yes' ) {
			set_transient( 'reepay_mobilepay_subscriptions_gateway_settings_saved_notice', true, 30 );
		}
	}

	/**
	 * Display an admin notice when settings are saved and payment method enabled.
	 */
	public function display_admin_notice() {
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		if ( get_transient( 'reepay_mobilepay_subscriptions_gateway_settings_saved_notice' ) || $this->get_option( 'enabled' ) === 'yes' ) {
			$this->admin_notice_message();
			delete_transient( 'reepay_mobilepay_subscriptions_gateway_settings_saved_notice' );
		}
	}

	/**
	 * Admin notice message.
	 */
	public function admin_notice_message() {
		?>
		<div class="woo-connect-notice notice notice-error">
			<p>
				<?php echo $this->warning_message(); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Warning message.
	 */
	public function warning_message() {
		$message = __( 'MobilePay Subscription has been discontinued following the merger of MobilePay and Vipps. Please switch to using Vipps MobilePay Recurring instead.', 'reepay-checkout-gateway' );
		return $message;
	}
}
