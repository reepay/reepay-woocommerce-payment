<?php
/**
 * Custom gateway settings page
 *
 * @package Reepay\Checkout
 *
 * @var WC_Payment_Gateway $gateway
 * @var bool $webhook_installed
 */

use Reepay\Checkout\Gateways\ReepayGateway;

defined( 'ABSPATH' ) || exit();
?>

<h2><?php esc_html( $gateway->get_method_title() ); ?></h2>
<?php
if ( 'yes' === reepay()->get_setting( 'test_mode' ) ) {
	// translators: notice message enabled test mode.
	$notice_message = sprintf( __( 'You just enabled test mode, meaning your test API key will now be used. Please note that all subscription products previously linked to plans on your live account are no longer linked. If you try to purchase a subscription product now, an error will occur. Disabling test mode will restore all connections. <a href="%s" target="_blank">Read more about this here.</a>', 'reepay-checkout-gateway' ), 'https://optimize-docs.billwerk.com/reference/account' );
} else {
	// translators: notice message disabled test mode.
	$notice_message = sprintf( __( 'You just disabled test mode, meaning your live API key will now be used. Please note that all subscription products previously linked to plans on your live account are now restored. If you haven\'t linked your subscription products with your test account, they will remain unlinked. <a href="%s" target="_blank">Read more about this here.</a>', 'reepay-checkout-gateway' ), 'https://optimize-docs.billwerk.com/reference/account' );
}
?>
<div class="notice notice-info">
	<p><?php echo $notice_message; ?></p>
</div>

<?php wp_kses_post( wpautop( $gateway->get_method_description() ) ); ?>
<p><?php _e( 'Billwerk+ Pay', 'reepay-checkout-gateway' ); ?></p>
<?php if ( ! $webhook_installed ) : ?>
	<p>
		<?php
		printf(
				// translators: %s link to reepay dashboard.
			__( 'Please setup WebHook in <a href="%s" target="_blank">Billwerk+ Pay Dashboard</a>.', 'reepay-checkout-gateway' ),
			'https://admin.billwerk.plus/'
		);
		?>
		<br>
		<?php
		printf(
			// translators: %1$s, %2$s - webhook url.
			__( 'WebHook URL: <a href="%1$s" target="_blank">%2$s</a>', 'reepay-checkout-gateway' ),
			ReepayGateway::get_webhook_url(),
			ReepayGateway::get_webhook_url()
		);
		?>
	</p>
<?php endif; ?>
<table class="form-table">
	<?php $gateway->generate_settings_html( $gateway->get_form_fields() ); ?>
</table>
