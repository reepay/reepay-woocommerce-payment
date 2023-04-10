<?php
/**
 * @package Reepay\Checkout
 *
 * @var ReepayCheckout $gateway
 * @var WC_Order $order
 */

use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit();

?>

<?php if ( $gateway->can_capture( $order ) ) : ?>
	<button id="reepay_capture"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Capture Payment', 'reepay-checkout-gateway' ); ?>
	</button>
<?php endif; ?>

<?php if ( $gateway->can_cancel( $order ) ) : ?>
	<button id="reepay_cancel"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Cancel Payment', 'reepay-checkout-gateway' ); ?>
	</button>
<?php endif; ?>
