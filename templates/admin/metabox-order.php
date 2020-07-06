<?php
/** @var WC_Gateway_Reepay_Checkout $gateway */
/** @var WC_Order $order */
/** @var int $order_id */
/** @var array $order_data */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<ul class="order_action">
	<li class="reepay-admin-section-li-header">
        <?php echo __( 'State', 'woocommerce-gateway-reepay-checkout' ); ?>: <?php echo $order_data['state']; ?>
    </li>

	<?php $order_is_cancelled = ( $order->get_meta( '_reepay_order_cancelled', true ) === '1' ); ?>
	<?php if ($order_is_cancelled && 'cancelled' != $order_data['state']): ?>
		<li class="reepay-admin-section-li-small">
            <?php echo __( 'Order is cancelled', 'woocommerce-gateway-reepay-checkout' ); ?>
        </li>
	<?php endif; ?>

	<li class="reepay-admin-section-li">
        <span class="reepay-balance__label">
            <?php echo __( 'Remaining balance', 'woocommerce-gateway-reepay-checkout' ); ?>:
        </span>
        <span class="reepay-balance__amount">
            <span class='reepay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( ( $order_data['authorized_amount'] - $order_data['settled_amount'] ) / 100 ); ?>
        </span>
    </li>
	<li class="reepay-admin-section-li">
        <span class="reepay-balance__label">
            <?php echo __( 'Total authorized', 'woocommerce-gateway-reepay-checkout' ); ?>:
        </span>
        <span class="reepay-balance__amount">
            <span class='reepay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $order_data['authorized_amount'] / 100 ); ?>
        </span>
    </li>
	<li class="reepay-admin-section-li">
        <span class="reepay-balance__label">
            <?php echo __( 'Total settled', 'woocommerce-gateway-reepay-checkout' ); ?>:
        </span>
        <span class="reepay-balance__amount">
            <span class='reepay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $order_data['settled_amount'] / 100 ); ?>
        </span>
    </li>
	<li class="reepay-admin-section-li">
        <span class="reepay-balance__label">
            <?php echo __( 'Total refunded', 'woocommerce-gateway-reepay-checkout' ); ?>:
        </span>
        <span class="reepay-balance__amount">
            <span class='reepay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $order_data['refunded_amount'] / 100 ); ?>
        </span>
    </li>
	<li style='font-size: xx-small'>&nbsp;</li>
	<?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="reepay-full-width">
            <a class="button button-primary" data-action="reepay_capture" id="reepay_capture" data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>" data-order-id="<?php echo $order_id; ?>" data-confirm="<?php echo __( 'You are about to CAPTURE this payment', 'woocommerce-gateway-reepay-checkout' ); ?>">
                <?php echo sprintf( __( 'Capture Full Amount (%s)', 'woocommerce-gateway-reepay-checkout' ), wc_price( $order_data['authorized_amount'] / 100 ) ); ?>
            </a>
        </li>
	<?php endif; ?>

	<?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="reepay-full-width">
            <a class="button" data-action="reepay_cancel" id="reepay_cancel" data-confirm="<?php echo __( 'You are about to CANCEL this payment', 'woocommerce-gateway-reepay-checkout' ); ?>" data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Cancel remaining balance', 'woocommerce-gateway-reepay-checkout' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<?php if ($order_data['authorized_amount'] > $order_data['settled_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="reepay-admin-section-li-header">
            <?php echo __( 'Partly capture', 'woocommerce-gateway-reepay-checkout' ); ?>
        </li>
		<li class="reepay-balance last">
            <span class="reepay-balance__label" style="margin-right: 0;">
                <?php echo __( 'Capture amount', 'woocommerce-gateway-reepay-checkout' ); ?>:
            </span>
            <span class="reepay-partly_capture_amount">
                <input id='reepay-capture_partly_amount-field' class='reepay-capture_partly_amount-field' type='number' min="0.00" step="0.01" size='6' value="<?php echo ( $order_data['authorized_amount'] - $order_data['settled_amount'] ) / 100; ?>" />
            </span>
        </li>
		<li class="reepay-full-width">
            <a class="button" id="reepay_capture_partly" data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Capture Specified Amount', 'woocommerce-gateway-reepay-checkout' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<?php if ( $order_data['settled_amount'] > $order_data['refunded_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled ): ?>
		<li class="reepay-admin-section-li-header">
            <?php echo __( 'Partly refund', 'woocommerce-gateway-reepay-checkout' ); ?>
        </li>
		<li class="reepay-balance last">
            <span class="reepay-balance__label" style='margin-right: 0;'>
                <?php echo __( 'Refund amount', 'woocommerce-gateway-reepay-checkout' ); ?>:
            </span>
            <span class="reepay-partly_refund_amount">
                <input id='reepay-refund_partly_amount-field' class='reepay-refund_partly_amount-field' type='number' size='6' min="0.00" step="0.01" value="<?php echo ( $order_data['settled_amount'] - $order_data['refunded_amount'] ) / 100; ?>" />
            </span>
        </li>
		<li class="reepay-full-width">
            <a class="button" id="reepay_refund_partly" data-nonce="<?php echo wp_create_nonce( 'reepay' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Refund Specified Amount', 'woocommerce-gateway-reepay-checkout' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<li class="reepay-admin-section-li-header-small">
        <?php echo __( 'Order ID', 'woocommerce-gateway-reepay-checkout' ) ?>
    </li>
	<li class="reepay-admin-section-li-small">
        <?php echo $order_data["handle"]; ?>
    </li>
	<li class="reepay-admin-section-li-header-small">
        <?php echo __( 'Transaction ID', 'woocommerce-gateway-reepay-checkout' ) ?>
    </li>
	<li class="reepay-admin-section-li-small">
        <?php echo $order_data["id"]; ?>
    </li>
	<?php if ( isset( $order_data['transactions'][0] ) ): ?>
        <li class="reepay-admin-section-li-header-small">
			<?php echo __( 'Card number', 'woocommerce-gateway-reepay-checkout' ); ?>
        </li>
        <li class="reepay-admin-section-li-small">
			<?php echo WC_ReepayCheckout::formatCreditCard( $order_data['transactions'][0]['card_transaction']['masked_card'] ); ?>
        </li>
        <p>
            <center>
                <img src="<?php echo WC_ReepayCheckout::get_logo( $order_data['transactions'][0]['card_transaction']['card_type'] ); ?>" class="reepay-admin-card-logo" />
            </center>
        </p>
	<?php endif; ?>
</ul>
