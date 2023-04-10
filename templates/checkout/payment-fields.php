<?php
/**
 * @package Reepay\Checkout
 *
 * @var string $description
 */

defined( 'ABSPATH' ) || exit();
?>

<?php if ( ! empty( $description ) ) : ?>
	<?php echo wpautop( wptexturize( $description ) ); ?>
	<?php
endif;
