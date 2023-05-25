<?php
/**
 * Metabox invoice template
 *
 * @package Reepay\Checkout
 *
 * @var ReepayCheckout $gateway
 * @var WC_Order                   $order
 * @var int                        $order_id
 * @var array                      $order_data
 * @var bool                       $order_is_cancelled
 * @var string                     $link
 */

use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit();

if ( ! empty( $order_data['transactions'][0] ) && ! empty( $order_data['transactions'][0]['card_transaction'] ) && ! empty( $order_data['transactions'][0]['card_transaction']['card_type'] ) ) {
	$card_logo = $gateway->get_logo( $order_data['transactions'][0]['card_transaction']['card_type'] );
}

?>

<ul class="order_action order_action_reepay_subscriotions clearfix">
	<li class="reepay-admin-section-li-header-small">
		<?php echo __( 'Invoice handle', 'reepay-subscriptions-for-woocommerce' ); ?>
	</li>
	<li class="reepay-admin-section-li-small">
		<?php echo $order_data['handle']; ?>
	</li>

	<li class="reepay-admin-section-li-header-small">
		<?php echo __( 'State', 'reepay-subscriptions-for-woocommerce' ); ?>
	</li>
	<li class="reepay-admin-section-li-small reepay-invoice-status reepay-invoice-status-<?php echo $order_data['state']; ?> ">
		<?php echo ucfirst( $order_data['state'] ); ?>
	</li>
	<?php if ( $order_is_cancelled ) : ?>
		<li class="reepay-admin-section-li-small">
			<?php echo esc_html__( 'Order is cancelled', 'reepay-subscriptions-for-woocommerce' ); ?>
		</li>
	<?php endif; ?>

	<?php if ( isset( $order_data['transactions'][0] ) && isset( $order_data['transactions'][0]['card_transaction'] ) ) : ?>
		<li class="reepay-admin-section-li-header-small">
			<?php echo esc_html__( 'Payment method', 'reepay-subscriptions-for-woocommerce' ); ?>
		</li>
		<li class="reepay-admin-section-li-small" style="display: flex;  align-items: center;">
			<?php if ( isset( $card_logo ) ) : ?>
				<img style="max-width: 70px; margin-right: 0"
					 src="<?php echo $card_logo; ?>"
					 class="reepay-admin-card-logo"/>
			<?php endif; ?>
			<?php echo esc_html( rp_format_credit_card( $order_data['transactions'][0]['card_transaction']['masked_card'] ) ); ?>
		</li>
	<?php endif; ?>

	<?php if ( isset( $order_data['transactions'][0]['mps_transaction'] ) ) : ?>
		<div style="text-align: center;">
			<img src="<?php echo $gateway->get_logo( 'ms_subscripiton' ); ?>" class="reepay-admin-card-logo"/>
		</div>
	<?php endif; ?>

	<li class="reepay-admin-section-li">
		<span class="reepay-balance__label">
			<?php echo esc_html__( 'Remaining balance', 'reepay-subscriptions-for-woocommerce' ); ?>:
		</span>
		<span class="reepay-balance__amount">
			<span class='reepay-balance__currency'>
				&nbsp;
			</span>
			<?php echo $order_data['authorized_amount'] ? rp_make_initial_amount( $order_data['authorized_amount'] - $order_data['settled_amount'], $order_data['currency'] ) . ' ' . get_woocommerce_currency_symbol() : $order_data['authorized_amount'] . ' ' . get_woocommerce_currency_symbol(); ?>
		</span>
	</li>
	<li class="reepay-admin-section-li">
		<span class="reepay-balance__label">
			<?php echo esc_html__( 'Total authorized', 'reepay-subscriptions-for-woocommerce' ); ?>:
		</span>
		<span class="reepay-balance__amount">
			<span class='reepay-balance__currency'>
				&nbsp;
			</span>
			<?php echo rp_make_initial_amount( $order_data['authorized_amount'], $order_data['currency'] ) . ' ' . get_woocommerce_currency_symbol(); ?>
	   </span>
	</li>

	<li class="reepay-admin-section-li">
		<span class="reepay-balance__label">
			<?php echo esc_html__( 'Total settled', 'reepay-subscriptions-for-woocommerce' ); ?>:
		</span>
		<span class="reepay-balance__amount">
			<span class='reepay-balance__currency'>
				&nbsp;
			</span>
			<?php echo rp_make_initial_amount( $order_data['settled_amount'], $order_data['currency'] ) . ' ' . get_woocommerce_currency_symbol(); ?>
		</span>
	</li>
	<li class="reepay-admin-section-li">
		<span class="reepay-balance__label">
			<?php echo esc_html__( 'Total refunded', 'reepay-subscriptions-for-woocommerce' ); ?>:
		</span>
		<span class="reepay-balance__amount">
			<span class='reepay-balance__currency'>
				&nbsp;
			</span>
			<?php echo rp_make_initial_amount( $order_data['refunded_amount'], $order_data['currency'] ) . ' ' . get_woocommerce_currency_symbol(); ?>
		</span>
	</li>

	<li class="reepay-admin-section-li-small" style="margin-top: 15px;">
		<a class="button" href="<?php echo $link; ?>" target="_blank">
			<?php _e( 'See invoice', 'reepay-subscriptions-for-woocommerce' ); ?>
		</a>
	</li>
</ul>
