<?php
/** @var WC_Gateway_Reepay_Checkout $gateway */
/** @var WC_Order $order */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<?php if ( $gateway->can_capture( $order ) ): ?>
	<button id="reepay_capture"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Capture Payment', 'reepay-checkout-gateway' ) ?>
	</button>
<?php endif; ?>

<?php if ( $gateway->can_cancel( $order ) ): ?>
	<button id="reepay_cancel"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Cancel Payment', 'reepay-checkout-gateway' ) ?>
	</button>
<?php endif; ?>

