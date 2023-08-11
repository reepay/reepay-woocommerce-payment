<?php
/**
 * Mobilepay subscriptions token
 *
 * @package Reepay\Checkout\Tokens
 */

namespace Reepay\Checkout\Tokens;

use WC_Payment_Token;

defined( 'ABSPATH' ) || exit();

/**
 * Class TokenReepayMS
 *
 * @package Reepay\Checkout\Tokens
 */
class TokenReepayMS extends WC_Payment_Token {
	/**
	 * Token type
	 *
	 * @var string
	 */
	protected $type = 'Reepay_MS';

	/**
	 * Get type to display to user.
	 *
	 * @param string $deprecated Deprecated since WooCommerce 3.0.
	 *
	 * @return false|string
	 */
	public function get_display_name( $deprecated = '' ) {
		ob_start();
		?>
		<img src="<?php echo esc_url( reepay()->get_setting( 'images_url' ) . 'mobilepay.png' ); ?>" width="46" height="24"/>

		<?php if ( is_checkout() ) : ?>
			<?php echo '&nbsp;' . $this->get_token(); ?>
		<?php else : ?>
			<?php
			// translators: %s token.
			echo sprintf( __( 'Billwerk+ - Mobilepay Subscriptions [%s]', 'reepay-checkout-gateway' ), $this->get_token() );
			?>
		<?php endif; ?>

		<?php
		$display = ob_get_contents();
		ob_end_clean();

		return $display;
	}

	/**
	 * Register actions for displaying token
	 */
	public static function register_view_actions() {
		add_filter( 'woocommerce_payment_methods_list_item', array( __CLASS__, 'wc_get_account_saved_payment_methods_list_item' ), 10, 2 );
		add_action( 'woocommerce_account_payment_methods_column_method', __CLASS__ . '::wc_account_payment_methods_column_method', 10, 1 );
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array            $item          Individual list item from woocommerce_saved_payment_methods_list.
	 * @param WC_Payment_Token $payment_token The payment token associated with this method entry.
	 *
	 * @return array                           Filtered item.
	 */
	public static function wc_get_account_saved_payment_methods_list_item( array $item, WC_Payment_Token $payment_token ): array {
		if ( 'reepay_mobilepay_subscriptions' !== $payment_token->get_gateway_id() ) {
			return $item;
		}

		$item['method']['id'] = $payment_token->get_id();
		$item['expires']      = 'N/A';

		return $item;
	}

	/**
	 * Print content in column method in account payment methods table
	 *
	 * @param array $method payment method from wc_get_customer_saved_methods_list.
	 *
	 * @see wc_get_customer_saved_methods_list
	 */
	public static function wc_account_payment_methods_column_method( array $method ) {
		if ( 'reepay_mobilepay_subscriptions' !== $method['method']['gateway'] ) {
			return;
		}

		if ( rp_is_reepay_payment_method( $method['method']['gateway'] ) ) {
			$token = new TokenReepayMS( $method['method']['id'] );
			echo $token->get_display_name();
		}
	}
}
