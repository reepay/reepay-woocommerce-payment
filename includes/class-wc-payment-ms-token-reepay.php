<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

class WC_Payment_Token_Reepay_MS extends WC_Payment_Token {
	protected $type = 'Reepay_MS';

    public function get_display_name($deprecated = '')
    {
        ob_start();
        ?>
        <img src="<?php echo esc_url( plugins_url( '/assets/images/'. 'mobilepay' . '.png', dirname( __FILE__ ) ) ); ?>" width="46" height="24" />

	    <?php if ( is_checkout() ): ?>
	        <?php echo '&nbsp;' . $this->get_token(); ?>
        <?php else: ?>
            <?php echo sprintf( __( 'Reepay - Mobilepay Subscriptions [%s]', 'reepay-checkout-gateway' ), $this->get_token() ); ?>
        <?php endif; ?>

		<?php
		$display = ob_get_contents();
		ob_end_clean();

		return $display;
	}

    /**
     * Controls the output for credit cards on the my account page.
     *
     * @param  array            $item         Individual list item from woocommerce_saved_payment_methods_list.
     * @param  WC_Payment_Token $payment_token The payment token associated with this method entry.
     * @return array                           Filtered item.
     */
    public static function wc_get_account_saved_payment_methods_list_item($item, $payment_token) {
        if ( 'reepay_mobilepay_subscriptions' !== $payment_token->get_gateway_id() ) {
            return $item;
        }

		$item['method']['id'] = $payment_token->get_id();
		$item['expires']      = 'N/A';

		return $item;
	}

	public static function wc_account_payment_methods_column_method( $method ) {
		if ( 'reepay_mobilepay_subscriptions' !== $method['method']['gateway'] ) {
			return;
		}

		$token = new WC_Payment_Token_Reepay_MS( $method['method']['id'] );
		if ( in_array( $method['method']['gateway'], WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			echo $token->get_display_name();
		}
	}
}

add_filter( 'woocommerce_payment_methods_list_item', 'WC_Payment_Token_Reepay_MS::wc_get_account_saved_payment_methods_list_item', 10, 2 );
add_action( 'woocommerce_account_payment_methods_column_method', 'WC_Payment_Token_Reepay_MS::wc_account_payment_methods_column_method', 10, 1 );
