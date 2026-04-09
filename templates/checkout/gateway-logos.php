<?php
/**
 * List of gateway logos in checkout
 *
 * @package Reepay\Checkout
 *
 * @var array $logos
 */

defined( 'ABSPATH' ) || exit();

?>

<ul class="reepay-logos">
	<?php foreach ( $logos as $logo ) : ?>
	<li class="reepay-logo">
		<img
			src="<?php echo esc_url( $logo['src'] ); ?>"
			alt="<?php echo esc_attr( $logo['alt'] ); ?>"
		>
	</li>
	<?php endforeach; ?>
</ul>
