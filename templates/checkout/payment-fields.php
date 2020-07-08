<?php
/** @var WC_Payment_Gateway $gateway */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<?php if ( $description = $gateway->get_description() ): ?>
	<?php echo wpautop( wptexturize( $description ) ); ?>
<?php endif; ?>
