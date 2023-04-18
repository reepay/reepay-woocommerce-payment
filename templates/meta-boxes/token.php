<?php
/**
 * @package Reepay\Checkout
 *
 * @var array $args arguments sent to template.
 */

defined( 'ABSPATH' ) || exit;
?>
<ul class="order_action js-order_action">
	<li class="reepay-admin-section-li">
		<input type="text"
			   value="<?php echo $args['token'] ?>"
			   style="width:100%"
			   data-reepay-token-value>
	</li>
	<li class="reepay-admin-section-li">
		<button class="button"
				style="display:none"
				data-reepay-token-update>
			<?php _e( 'Update ', 'reepay-subscriptions-for-woocommerce' ); ?>
		</button>
	</li>
</ul>
