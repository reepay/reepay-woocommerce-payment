<?php
/**
 * Woocommerce missed notice
 *
 * @package Reepay\Checkout
 */

defined( 'ABSPATH' ) || exit();
?>
<div id="message" class="error">
	<p class="main">
		<strong>
			<?php
			echo esc_html__(
				'WooCommerce is inactive or missing.',
				'reepay-checkout-gateway'
			);
			?>
		</strong>
	</p>
	<p>
		<?php
		_e(
			'WooCommerce plugin is inactive or missing. Please install and active it.',
			'reepay-checkout-gateway'
		);
		?>
		<br />
		<?php
		_e(
			'WooCommerce Billwerk+ Payments Gateway will be deactivated.',
			'reepay-checkout-gateway'
		)
		?>
	</p>
</div>
