<?php
/**
 * Mobilepay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Mobilepay
 *
 * @package Reepay\Checkout\Gateways
 */
class Mobilepay extends ReepayGateway {
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
		'mobilepay',
	);

	/**
	 * Mobilepay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_mobilepay';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Mobilepay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'mobilepay' );

		parent::__construct();

		$this->apply_parent_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	/**
	 * Process admin options and add a custom notice on successful save.
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		if ( $saved && $this->get_option( 'enabled' ) === 'yes' ) {
			set_transient( 'reepay_mobilepay_gateway_settings_saved_notice', true, 30 );
		}
	}

	/**
	 * Display an admin notice when settings are saved and payment method enabled.
	 */
	public function display_admin_notice() {
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		if ( get_transient( 'reepay_mobilepay_gateway_settings_saved_notice' ) || $this->get_option( 'enabled' ) === 'yes' ) {
			$this->warning_message();
			delete_transient( 'reepay_mobilepay_gateway_settings_saved_notice' );
		}
	}

	/**
	 * Warning message.
	 */
	public function warning_message() {
		?>
		<div class="woo-connect-notice notice notice-error">
			<p>
				<?php _e( 'The new Vipps MobilePay payment method, which utilizes bank transfers instead of card payments, will replace the old MobilePay Online payment method. Please refer to Vipps MobilePay for more efficient transactions and a better conversion rate.', 'reepay-checkout-gateway' ); ?>
			</p>
		</div>
		<?php
	}
}
