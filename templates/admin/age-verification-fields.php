<?php
/**
 * Age Verification Fields Template
 * Template for displaying age verification fields in product admin
 *
 * @package Reepay\Checkout
 * @var array $args Template arguments
 * @var int $product_id Product ID
 * @var string $enable_age_verification Current value of enable age verification
 * @var string $minimum_age Current value of minimum age
 */

defined( 'ABSPATH' ) || exit;

// Extract variables from args.
$product_id              = $args['product_id'] ?? 0;
$enable_age_verification = $args['enable_age_verification'] ?? '';
$minimum_age             = $args['minimum_age'] ?? '';
?>

<div class="reepay-age-verification-fields">
	<h3><?php esc_html_e( 'Age Verification Settings', 'reepay-checkout-gateway' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Configure age verification requirements for this product. These settings apply to both Subscription and Pay modules.', 'reepay-checkout-gateway' ); ?>
	</p>

	<table class="form-table reepay-age-verification-table">
		<tbody>
			<tr class="reepay-enable-age-verification-row">
				<th scope="row">
					<label for="_reepay_enable_age_verification">
						<?php esc_html_e( 'Enable age verification?', 'reepay-checkout-gateway' ); ?>
					</label>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Enable age verification', 'reepay-checkout-gateway' ); ?></span>
						</legend>
						<label for="_reepay_enable_age_verification">
							<input 
								type="checkbox" 
								id="_reepay_enable_age_verification" 
								name="_reepay_enable_age_verification" 
								value="yes" 
								<?php checked( $enable_age_verification, 'yes' ); ?>
							/>
							<?php esc_html_e( 'Yes', 'reepay-checkout-gateway' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable or disable age verification at product level.', 'reepay-checkout-gateway' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr class="reepay-minimum-age-row reepay-minimum-age-field" style="<?php echo 'yes' !== $enable_age_verification ? 'display: none;' : ''; ?>">
				<th scope="row">
					<label for="_reepay_minimum_age">
						<?php esc_html_e( 'Minimum user age', 'reepay-checkout-gateway' ); ?>
					</label>
				</th>
				<td>
					<select id="_reepay_minimum_age" name="_reepay_minimum_age">
						<option value=""><?php esc_html_e( 'Select age...', 'reepay-checkout-gateway' ); ?></option>
						<option value="15" <?php selected( $minimum_age, '15' ); ?>>
							<?php esc_html_e( '15', 'reepay-checkout-gateway' ); ?>
						</option>
						<option value="16" <?php selected( $minimum_age, '16' ); ?>>
							<?php esc_html_e( '16', 'reepay-checkout-gateway' ); ?>
						</option>
						<option value="18" <?php selected( $minimum_age, '18' ); ?>>
							<?php esc_html_e( '18', 'reepay-checkout-gateway' ); ?>
						</option>
						<option value="21" <?php selected( $minimum_age, '21' ); ?>>
							<?php esc_html_e( '21', 'reepay-checkout-gateway' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the minimum age required for this product. This field is shown only if "Enable age verification" is set to Yes.', 'reepay-checkout-gateway' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<div class="reepay-age-verification-info">
		<h4><?php esc_html_e( 'Important Information', 'reepay-checkout-gateway' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'Age verification applies to both Subscription module and Pay module.', 'reepay-checkout-gateway' ); ?></li>
			<li><?php esc_html_e( 'The minimum age field is only shown when age verification is enabled.', 'reepay-checkout-gateway' ); ?></li>
			<li><?php esc_html_e( 'Available age options: 15, 16, 18, and 21 years.', 'reepay-checkout-gateway' ); ?></li>
		</ul>
	</div>
</div>
