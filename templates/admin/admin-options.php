<?php
/** @var WC_Payment_Gateway $gateway */
/** @var bool $webhook_installed */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<h2><?php esc_html( $gateway->get_method_title() ); ?></h2>
<?php wp_kses_post( wpautop( $gateway->get_method_description() ) ); ?>
<p><?php _e('Reepay Checkout', 'woocommerce-gateway-reepay-checkout'); ?></p>
<?php if ( ! $webhook_installed ): ?>
	<p>
		<?php
		echo sprintf(
			__('Please setup WebHook in <a href="%s" target="_blank">Reepay Dashboard</a>.', 'woocommerce-gateway-reepay-checkout'),
			'https://admin.reepay.com/'
		);
		?>
		<br>
		<?php
		echo sprintf(
			__('WebHook URL: <a href="%s" target="_blank">%s</a>', 'woocommerce-gateway-reepay-checkout'),
			WC()->api_request_url( get_class( $gateway ) ),
			WC()->api_request_url( get_class( $gateway ) )
		);
		?>
	</p>
<?php endif; ?>
<table class="form-table">
	<?php $gateway->generate_settings_html( $gateway->get_form_fields(), true ); ?>
</table>
