<?php
/**
 * @var WC_Payment_Gateway $gateway
 */

defined( 'ABSPATH' ) || exit();
?>

<?php if ( $description = $gateway->get_description() ) : ?>
	<?php echo wpautop( wptexturize( $description ) ); ?>
	<?php
endif;
