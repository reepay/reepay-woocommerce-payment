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
<?php wp_kses_post( wpautop( $gateway->get_method_description() ) ); ?>
<p><?php _e( 'Billwerk+ Payments', 'reepay-checkout-gateway' ); ?></p>
<?php if ( ! $webhook_installed ) : ?>
	<p>
		<?php
		echo sprintf(
				// translators: %s link to reepay dashboard.
			__( 'Please setup WebHook in <a href="%s" target="_blank">Billwerk+ Dashboard</a>.', 'reepay-checkout-gateway' ),
			'https://admin.reepay.com/'
		);
		?>
		<br>
		<?php
		echo sprintf(
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
