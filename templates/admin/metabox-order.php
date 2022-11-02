<?php
/** @var WC_Gateway_Reepay_Checkout $gateway */
/** @var WC_Order $order */
/** @var int $order_id */
/** @var array $order_data */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<?php
$amount_to_capture = 0;
$order_total       = rp_prepare_amount( $order->get_total(), $order_data['currency'] );
if ( $order_total <= $order_data['authorized_amount'] ) {
	$amount_to_capture = $order_total;
} else {
	$amount_to_capture = $order_data['authorized_amount'];
}
?>

<ul class="order_action">
    <li class="reepay-admin-section-li-header" style="text-transform: capitalize;">
		<?php echo esc_html__( 'State', 'reepay-checkout-gateway' ); ?>: <?php echo $order_data['state']; ?>
    </li>

	<?php $order_is_cancelled = ( $order->get_meta( '_reepay_order_cancelled', true ) === '1' ); ?>
	<?php if ( $order_is_cancelled && 'cancelled' != $order_data['state'] ): ?>
        <li class="reepay-admin-section-li-small">
			<?php echo esc_html__( 'Order is cancelled', 'reepay-checkout-gateway' ); ?>
        </li>
	<?php endif; ?>

    <li class="reepay-admin-section-li">
        <span class="reepay-balance__label">
            <?php echo esc_html__( 'Remaining balance', 'reepay-checkout-gateway' ); ?>:
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
            <?php echo esc_html__( 'Total authorized', 'reepay-checkout-gateway' ); ?>:
        </span>
        <span class="reepay-balance__amount">
            <span class='reepay-balance__currency'>
                &nbsp;
            </span>
            <?php echo rp_make_initial_amount( $order_data['authorized_amount'], $order_data['currency'] ) . ' ' . get_woocommerce_currency_symbol(); ?>
       </span>
    </li>

    <input id="reepay_order_id" type="hidden" data-order-id="<?php echo $order_id; ?>">
    <input id="reepay_order_total_settled" type="hidden"
           value="<?php echo rp_make_initial_amount( $order_data['settled_amount'], $order_data['currency'] ); ?>">
    <input id="reepay_order_total_authorized" type="hidden"
           value="<?php echo rp_make_initial_amount( $order_data['authorized_amount'], $order_data['currency'] ); ?>">
    <input id="reepay_order_total" type="hidden"
           value="<?php echo rp_make_initial_amount( $order_data['authorized_amount'] - $order_data['settled_amount'], $order_data['currency'] ) . ' ' . $order_data['currency']; ?>"
           data-order-total="<?php echo rp_make_initial_amount( $order_data['authorized_amount'] - $order_data['settled_amount'], $order_data['currency'] ); ?>">

    <li class="reepay-admin-section-li">
        <span class="reepay-balance__label">
            <?php echo esc_html__( 'Total settled', 'reepay-checkout-gateway' ); ?>:
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
            <?php echo esc_html__( 'Total refunded', 'reepay-checkout-gateway' ); ?>:
        </span>
        <span class="reepay-balance__amount">
            <span class='reepay-balance__currency'>
                &nbsp;
            </span>
            <?php echo rp_make_initial_amount( $order_data['refunded_amount'], $order_data['currency'] ) . ' ' . get_woocommerce_currency_symbol(); ?>
        </span>
    </li>
    <li style='font-size: xx-small'>&nbsp;</li>

	<?php if ( $order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array(
			'cancelled',
			'created'
		) ) && ! $order_is_cancelled ): ?>
        <li class="reepay-full-width">
            <a class="button" data-action="reepay_cancel" id="reepay_cancel"
               data-confirm="<?php echo __( 'You are about to CANCEL this payment', 'reepay-checkout-gateway' ); ?>"
               data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>" data-order-id="<?php echo $order_id; ?>">
				<?php echo esc_html__( 'Cancel remaining balance', 'reepay-checkout-gateway' ); ?>
            </a>
        </li>
        <li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

    <li class="reepay-admin-section-li-header-small">
		<?php echo __( 'Invoice handle', 'reepay-checkout-gateway' ) ?>
    </li>
    <li class="reepay-admin-section-li-small">
		<?php echo $order_data['handle']; ?>
    </li>
    <li class="reepay-admin-section-li-header-small">
		<?php echo esc_html__( 'Transaction ID', 'reepay-checkout-gateway' ) ?>
    </li>
    <li class="reepay-admin-section-li-small">
		<?php echo $order_data["id"]; ?>
    </li>

	<?php if ( isset( $order_data['transactions'][0] ) && isset( $order_data['transactions'][0]['card_transaction'] ) ) : ?>
        <li class="reepay-admin-section-li-header-small">
			<?php echo esc_html__( 'Card number', 'reepay-checkout-gateway' ); ?>
        </li>
        <li class="reepay-admin-section-li-small">
			<?php echo esc_html( rp_format_credit_card( $order_data['transactions'][0]['card_transaction']['masked_card'] ) ); ?>
        </li>
        <p>
        <center>
            <img src="<?php echo $gateway->get_logo( $order_data['transactions'][0]['card_transaction']['card_type'] ); ?>"
                 class="reepay-admin-card-logo"/>
        </center>
        </p>
	<?php endif; ?>

	<?php if ( isset( $order_data['transactions'][0]['mps_transaction'] ) ): ?>
        <center>
            <img src="<?php echo $gateway->get_logo( 'ms_subscripiton' ); ?>" class="reepay-admin-card-logo"/>
        </center>
	<?php endif; ?>
</ul>
