<?php
/**
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
		echo esc_html__(
			'WooCommerce plugin is inactive or missing. Please install and active it.',
			'reepay-checkout-gateway'
		);
		echo '<br />';
		/* translators: 1: plugin name */
		echo sprintf(
			esc_html__(
				'%1$s will be deactivated.',
				'reepay-checkout-gateway'
			),
			'WooCommerce Reepay Checkout Gateway'
		);

		?>
	</p>
</div>
