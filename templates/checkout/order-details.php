<?php
/**
 * Order details on thankyou page
 *
 * @package Reepay\Checkout
 *
 * @var WC_Order $order current order.
 */

use Reepay\Checkout\OrderFlow\ThankyouPage;

defined( 'ABSPATH' ) || exit();
?>
<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

	<li class="woocommerce-order-overview__order order">
		<?php esc_html_e( 'Order number:', 'reepay-checkout-gateway' ); ?>
		<strong>
		<?php
		echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			</strong>
	</li>

	<li class="woocommerce-order-overview__date date">
		<?php esc_html_e( 'Date:', 'reepay-checkout-gateway' ); ?>
		<strong>
		<?php
		echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			</strong>
	</li>

	<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
		<li class="woocommerce-order-overview__email email">
			<?php esc_html_e( 'Email:', 'reepay-checkout-gateway' ); ?>
			<strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
		</li>
	<?php endif; ?>

	
	<?php
	$pro_rated_subscription = ThankyouPage::get_pro_rated_reepay_subscription( $order );
	if ( $pro_rated_subscription !== null ) {
		?>
		<li class="woocommerce-order-overview__total reepay-pro-rated total">
			<div class="reepay-pro-rated-subscription">
				<?php esc_html_e( 'Paid for partial period:', 'reepay-checkout-gateway' ); ?>
				<strong><?php echo wc_price( rp_make_initial_amount( $pro_rated_subscription['invoice_amount'], $order->get_currency() ) ); ?></strong>
			</div>
			<div class="reepay-pro-rated-subscription">
				<?php esc_html_e( 'Successively per period:', 'reepay-checkout-gateway' ); ?>
				<strong><?php echo wc_price( rp_make_initial_amount( $pro_rated_subscription['next_invoice_preview_amount'], $order->get_currency() ) ); ?></strong>
			</div>
		</li>
		<?php
	} else {
		?>
		<li class="woocommerce-order-overview__total total">
		<?php esc_html_e( 'Total:', 'reepay-checkout-gateway' ); ?>
		<strong>
		<?php
		if ( (float) $order->get_total() > 0.00 ) {
			echo wc_price( $order->get_total() );
		} else {
			$real_total = $order->get_meta( '_real_total' );
			echo wc_price( $real_total );
		}
		?>
		</strong>
		</li>
		<?php
	}
	?>

	<?php if ( $order->get_payment_method_title() ) : ?>
		<li class="woocommerce-order-overview__payment-method method">
			<?php esc_html_e( 'Payment method:', 'reepay-checkout-gateway' ); ?>
			<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
		</li>
	<?php endif; ?>

</ul>
