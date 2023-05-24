<?php
/**
 * DB need update notice
 *
 * @package Reepay\Checkout
 *
 * @var string $update_page_url
 */

?>
<div id="message" class="error">
	<p>
		<?php echo esc_html__( 'Warning! WooCommerce Reepay Checkout plugin requires to update the database structure.', 'reepay-checkout-gateway' ); ?>
		<?php
		// translators:  %1$s openning link tag to update page %2$s closing link tag.
		echo sprintf( esc_html__( 'Please click %1$s here %2$s to start upgrade.', 'reepay-checkout-gateway' ), '<a href="' . esc_url( $update_page_url ) . '">', '</a>' );
		?>
	</p>
</div>
