<?php
/** @var WC_Gateway_Reepay_Checkout $gateway */
/** @var WC_Order $order */
/** @var int $order_id */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>
<div>
	<?php if ( $gateway->can_capture( $order ) ): ?>
		<button id="reepay_capture"
				data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Capture Payment', 'woocommerce-gateway-reepay-checkout' ) ?>
		</button>
	<?php endif; ?>

	<?php if ( $gateway->can_cancel( $order ) ): ?>
		<button id="reepay_cancel"
				data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Cancel Payment', 'woocommerce-gateway-reepay-checkout' ) ?>
		</button>
	<?php endif; ?>
</div>
