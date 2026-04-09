<?php
/**
 * Metabox plan template
 *
 * @package Reepay\Checkout
 *
 * @var array{post_type: string} $args arguments sent to template.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="billwerk-meta-fields" data-post-type="<?php echo esc_attr( $args['post_type'] ); ?>"></div>
