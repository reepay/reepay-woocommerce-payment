<?php
/**
 * Register reepay product meta boxes for age verification
 *
 * @package Reepay\Checkout\Admin\MetaBoxes
 */

namespace Reepay\Checkout\Admin\MetaBoxes;

use Reepay\Checkout\Utils\MetaField;

defined( 'ABSPATH' ) || exit();

/**
 * Class Product
 *
 * @package Reepay\Checkout\Admin\MetaBoxes
 */
class Product {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_age_verification_panel' ) );
		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'add_age_verification_tab' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_age_verification_fields' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add age verification tab to product data tabs
	 *
	 * @return void
	 */
	public function add_age_verification_tab() {
		// Only show tab if global age verification setting is enabled
		if ( ! MetaField::is_global_age_verification_enabled() ) {
			return;
		}

		?>
		<li class="age_verification_tab age_verification_options">
			<a href="#age_verification_product_data">
				<span><?php esc_html_e( 'Age Verification', 'reepay-checkout-gateway' ); ?></span>
			</a>
		</li>
		<?php
	}

	/**
	 * Add age verification panel to product data panels
	 *
	 * @return void
	 */
	public function add_age_verification_panel() {
		global $post;

		// Only show panel if global age verification setting is enabled
		if ( ! MetaField::is_global_age_verification_enabled() ) {
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}

		$enable_age_verification = get_post_meta( $post->ID, '_reepay_enable_age_verification', true );
		$minimum_age = get_post_meta( $post->ID, '_reepay_minimum_age', true );
		
		?>
		<div id="age_verification_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => '_reepay_enable_age_verification',
						'value'       => $enable_age_verification,
						'label'       => __( 'Enable age verification?', 'reepay-checkout-gateway' ),
						'description' => __( 'Enable/disable age verification at product level.', 'reepay-checkout-gateway' ),
						'desc_tip'    => true,
					)
				);

				$age_options = array( '' => __( 'Select age...', 'reepay-checkout-gateway' ) ) + MetaField::get_age_options();

				woocommerce_wp_select(
					array(
						'id'          => '_reepay_minimum_age',
						'value'       => $minimum_age,
						'label'       => __( 'Minimum user age', 'reepay-checkout-gateway' ),
						'description' => __( 'Select the minimum age required for this product.', 'reepay-checkout-gateway' ),
						'desc_tip'    => true,
						'options'     => $age_options,
						'wrapper_class' => 'reepay-minimum-age-field',
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save age verification fields
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_age_verification_fields( $post_id ) {
		// Verify nonce for security
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

		// Check if user has permission to edit this post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save enable age verification checkbox
		$enable_age_verification = isset( $_POST['_reepay_enable_age_verification'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_reepay_enable_age_verification', $enable_age_verification );

		// Save minimum age dropdown
		if ( isset( $_POST['_reepay_minimum_age'] ) ) {
			$minimum_age = sanitize_text_field( $_POST['_reepay_minimum_age'] );

			// Validate minimum age if age verification is enabled
			if ( 'yes' === $enable_age_verification ) {
				$valid_ages = array_keys( MetaField::get_age_options() );
				if ( ! empty( $minimum_age ) && in_array( (int) $minimum_age, $valid_ages, true ) ) {
					update_post_meta( $post_id, '_reepay_minimum_age', $minimum_age );
				} else {
					// Clear invalid age
					delete_post_meta( $post_id, '_reepay_minimum_age' );

					// Add admin notice for invalid age
					add_action( 'admin_notices', function() {
						echo '<div class="notice notice-error"><p>' .
							esc_html__( 'Invalid minimum age selected for age verification. Please choose a valid age.', 'reepay-checkout-gateway' ) .
							'</p></div>';
					} );
				}
			} else {
				// Clear minimum age if age verification is disabled
				delete_post_meta( $post_id, '_reepay_minimum_age' );
			}
		}
	}

	/**
	 * Enqueue scripts for age verification
	 *
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		global $post;
		
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script(
			'reepay-age-verification-admin',
			reepay()->get_setting( 'js_url' ) . 'age-verification-admin.js',
			array( 'jquery' ),
			reepay()->get_setting( 'plugin_version' ),
			true
		);

		wp_enqueue_style(
			'reepay-age-verification-admin',
			reepay()->get_setting( 'css_url' ) . 'age-verification-admin.css',
			array(),
			reepay()->get_setting( 'plugin_version' )
		);
	}
}
