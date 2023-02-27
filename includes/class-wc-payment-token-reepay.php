<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Payment_Token_Reepay extends WC_Payment_Token_CC {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'Reepay';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4'        => '',
		'expiry_year'  => '',
		'expiry_month' => '',
		'card_type'    => '',
		'masked_card'  => '',
	);

	/**
	 * Get type to display to user.
	 *
	 * @param string $deprecated Deprecated since WooCommerce 3.0.
	 *
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$img   = $this->get_card_image_url();
		$style = '';

		if ( $this->get_card_type() == 'visa_dk' ) {
			$style = 'style="width: 46px; height: 24px;"';
		}

		ob_start();
		?>
        <img <?php echo $style ?> src="<?php echo $img ?>"
                                  alt="<?php echo wc_get_credit_card_type_label( $this->get_card_type() ); ?>"/>
		<?php echo esc_html( $this->get_masked_card() ); ?>
		<?php echo esc_html( $this->get_expiry_month() . '/' . substr( $this->get_expiry_year(), 2 ) ); ?>

		<?php
		$display = ob_get_contents();
		ob_end_clean();

		return $display;
	}

	/**
     * Get card image url
     *
	 * @return string
	 */
	public function get_card_image_url() {
		if ( $this->get_card_type() == 'visa_dk' ) {
			return plugins_url( '/assets/images/dankort.png', dirname( __FILE__ ) );
		} else {
			return WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $this->get_card_type() . '.png' );
		}
	}

	/**
	 * Validate credit card payment tokens.
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_masked_card( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hook prefix
	 * @return string
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_reepay_get_';
	}

	/**
	 * Returns Masked Card
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Masked Card
	 */
	public function get_masked_card( $context = 'view' ) {
		return $this->get_prop( 'masked_card', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $masked_card Masked Card
	 */
	public function set_masked_card( $masked_card ) {
		$this->set_prop( 'masked_card', $masked_card );
	}

	/**
	 * Returns if the token is marked as default.
	 *
	 * @return boolean True if the token is default
	 */
	public function is_default() {
		// Mark Method as Checked on "Payment Change" page
		if ( wcs_is_payment_change() &&
		     isset( $_GET['change_payment_method'] ) &&
		     abs( $_GET['change_payment_method'] ) > 0 ) {
			$subscription = wcs_get_subscription( $_GET['change_payment_method'] );
			$tokens       = $subscription->get_payment_tokens();
			foreach ( $tokens as $token_id ) {
				if ( $this->get_id() == $token_id ) {
					return true;
				}
			}

			return false;
		}

		return parent::is_default();
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array $item Individual list item from woocommerce_saved_payment_methods_list.
	 * @param WC_Payment_Token $payment_token The payment token associated with this method entry.
	 *
	 * @return array                           Filtered item.
	 */
	public static function wc_get_account_saved_payment_methods_list_item( $item, $payment_token ) {

		if ( 'reepay_checkout' !== $payment_token->get_gateway_id() ) {
			return $item;
		}

		if ( ! method_exists( $payment_token, 'get_card_type' ) ) {
			return $item;
		}
        
		$card_type               = $payment_token->get_card_type();
		$item['method']['id']    = $payment_token->get_id();
		$item['method']['last4'] = $payment_token->get_last4();
		$item['method']['brand'] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Credit card', 'woocommerce' ) );

		$item['expires'] = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), - 2 );

		return $item;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param $method
	 *
	 * @return void
	 */
	public static function wc_account_payment_methods_column_method( $method ) {
		if ( 'reepay_checkout' !== $method['method']['gateway'] ) {
			return;
		}

		$token = new WC_Payment_Token_Reepay( $method['method']['id'] );
		if ( in_array( $method['method']['gateway'], WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			echo $token->get_display_name();

			return;
		}

		// Default output
		// @see woocommerce/myaccount/payment-methods.php
		if ( ! empty( $method['method']['last4'] ) ) {
			/* translators: 1: credit card type 2: last 4 digits */
			echo sprintf( __( '%1$s ending in %2$s', 'woocommerce' ), esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
		} else {
			if ( isset( $method['method']['brand'] ) ) {
				echo esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) );
			} else {
				echo esc_html( wc_get_credit_card_type_label( 'visa' ) );
			}
		}

	}

	/**
	 * Fix html on Payment methods list
	 *
	 * @param string $html
	 * @param WC_Payment_Token $token
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return string
	 */
	public static function wc_get_saved_payment_method_option_html( $html, $token, $gateway ) {
		if ( in_array( $token->get_gateway_id(), WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			// Revert esc_html()
			$html = html_entity_decode( $html, ENT_COMPAT | ENT_XHTML, 'UTF-8' );
		}

		return $html;
	}
}

// Improve Payment Method output
add_filter( 'woocommerce_payment_methods_list_item', 'WC_Payment_Token_Reepay::wc_get_account_saved_payment_methods_list_item', 10, 2 );
add_action( 'woocommerce_account_payment_methods_column_method', 'WC_Payment_Token_Reepay::wc_account_payment_methods_column_method', 10, 1 );
add_filter( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', 'WC_Payment_Token_Reepay::wc_get_saved_payment_method_option_html', 10, 3 );
