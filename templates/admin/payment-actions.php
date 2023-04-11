<?php
/**
 * Payment actions in admin
 *
 * @package Reepay\Checkout
 *
 * @var ReepayCheckout $gateway
 * @var WC_Order $order
 * @var int      $order_id
 */

use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit();

?>
<div>
	<?php if ( $gateway->can_capture( $order ) ) : ?>
		<button id="reepay_capture"
				data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Capture Payment', 'reepay-checkout-gateway' ); ?>
		</button>
	<?php endif; ?>

	<?php if ( $gateway->can_cancel( $order ) ) : ?>
		<button id="reepay_cancel"
				data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Cancel Payment', 'reepay-checkout-gateway' ); ?>
		</button>
	<?php endif; ?>
</div>
