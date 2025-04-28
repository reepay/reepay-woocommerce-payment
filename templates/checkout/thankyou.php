<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.7.0
 * @global WC_Order $order
 */

use WC_Reepay_Renewals as WCRR;

defined( 'ABSPATH' ) || exit;

$show_customer_details = is_user_logged_in() && $order->get_user_id() === get_current_user_id();

?>
<style type="text/css">
	.transaction-error {
		font-weight: bold;
		color: red;
	}
	.woocommerce-order-overview__total.reepay-pro-rated {
		display: flex;
    	flex-wrap: wrap;
    	gap: 20px;
	}
	.woocommerce-order-overview__total.reepay-pro-rated .reepay-pro-rated-subscription {
		flex: 0 0 calc(50% - 10px);
    	box-sizing: border-box;
    	margin: 0;
	}
	@media screen and (max-width: 768px) {
    	.woocommerce-order-overview__total.reepay-pro-rated .reepay-pro-rated-subscription {
        	flex: 0 0 100%;
    	}
	}
</style>
<div class="woocommerce-order woocommerce-order--thankyou">

	<?php
	if ( $order ) :
		$another_orders = $order->get_meta( '_reepay_another_orders' ) ?: array();

		do_action( 'woocommerce_before_thankyou', $order->get_id() );
		?>

		<div id="order-status-checking">
			<p>
				<?php esc_html_e( 'Please wait. We\'re checking the payment status.', 'reepay-checkout-gateway' ); ?>
			</p>
		</div>

		<div id="order-success" style="display: none;">
			<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
			<?php
			echo apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'reepay-checkout-gateway' ), $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			</p>
			<div id="reepay-order-details">
				<?php
				if (!rp_is_order_paid_via_reepay($order)) {
					reepay()->get_template(
						'checkout/order-details.php',
						array(
							'order' => $order,
						)
					);
	
					foreach ( $another_orders as $order_id ) {
						if ( $order->get_id() === $order_id ) {
							continue; // Backward compatibility.
						}
	
						reepay()->get_template(
							'checkout/order-details.php',
							array(
								'order' => wc_get_order( $order_id ),
							)
						);
					}
				}
				?>
			</div>
		</div>

		<div id="order-failed" style="display: none;">
			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
				<?php if ( ! wcr_cart_only_reepay_subscriptions() ) : ?>
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"
						class="button pay"><?php esc_html_e( 'Pay', 'reepay-checkout-gateway' ); ?></a>
				<?php endif; ?>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"
						class="button pay"><?php esc_html_e( 'My account', 'reepay-checkout-gateway' ); ?></a>
				<?php endif; ?>
			</p>
		</div>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php
		$order_rp_subscription = false;
		if ( class_exists( WCRR::class ) && ( WCRR::is_order_contain_subscription( $order ) || $another_orders ) ) {
			$order_rp_subscription = true;
		}

		if ( true === $order_rp_subscription ) {
			if ( $show_customer_details ) {
				wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
			}
		} else {
			do_action( 'woocommerce_thankyou', $order->get_id() );
		}
		?>

	<?php else : ?>

		<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'reepay-checkout-gateway' ), null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

	<?php endif; ?>

</div>
