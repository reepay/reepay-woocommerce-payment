<?php
/**
 * Capture button template
 *
 * @package Reepay\Checkout
 *
 * @var string $name
 * @var string $value
 * @var string $text
 */

defined( 'ABSPATH' ) || exit();
?>

<button
	type="submit"
	class="button save_order button-primary capture-item-button"
	name="<?php echo esc_attr( $name ); ?>"
	value="<?php echo esc_attr( $value ); ?>"
>
	<?php echo wp_kses_post( $text ); ?>
</button>
