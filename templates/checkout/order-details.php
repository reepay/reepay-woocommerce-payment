<?php
/**
 * Order details on thankyou page
 *
 * @package Reepay\Checkout
 *
 * @var WC_Order $order current order.
 */

defined( 'ABSPATH' ) || exit();
?>
<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

	<li class="woocommerce-order-overview__order order">
		<?php esc_html_e( 'Order number:', 'woocommerce' ); ?>
		<strong>
		<?php
		echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			</strong>
	</li>

	<li class="woocommerce-order-overview__date date">
		<?php esc_html_e( 'Date:', 'woocommerce' ); ?>
		<strong>
		<?php
		echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			</strong>
	</li>

	<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
		<li class="woocommerce-order-overview__email email">
			<?php esc_html_e( 'Email:', 'woocommerce' ); ?>
			<strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
		</li>
	<?php endif; ?>

	<li class="woocommerce-order-overview__total total">
		<?php esc_html_e( 'Total:', 'woocommerce' ); ?>
		<strong>
		<?php
		echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			</strong>
	</li>

	<?php if ( $order->get_payment_method_title() ) : ?>
		<li class="woocommerce-order-overview__payment-method method">
			<?php esc_html_e( 'Payment method:', 'woocommerce' ); ?>
			<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
		</li>
	<?php endif; ?>

</ul>
