<?php
/**
 * Class with woocommerce activation checks
 *
 * @package Reepay\Checkout\Plugin
 */

namespace Reepay\Checkout\Plugin;

use WooCommerce;

defined( 'ABSPATH' ) || exit();

/**
 * Class WoocommerceExists
 *
 * @package Reepay\Checkout\Plugin
 */
class WoocommerceExists {
	/**
	 * WoocommerceExists constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_deactivate' ), 0 );
	}

	/**
	 * Add notices
	 */
	public function maybe_deactivate() {
		if ( ! self::woo_activated() ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
		}
	}

	/**
	 * Show notice in admin
	 */
	public function missing_woocommerce_notice() {
		$template = <<<OUTPUT
<div id="message" class="error">
	<p class="main">
		<strong>%s</strong>
	</p>
	<p>%s<br/>%s</p>
</div>
OUTPUT;

		printf(
			$template,
			esc_html__(
				'WooCommerce is inactive or missing.',
				'reepay-checkout-gateway'
			),
			__(
				'WooCommerce plugin is inactive or missing. Please install and active it.',
				'reepay-checkout-gateway'
			),
			__(
				'WooCommerce Billwerk+ Pay Gateway isn\'t active now.',
				'reepay-checkout-gateway'
			)
		);
	}

	/**
	 * Check if Woo activated
	 *
	 * @return bool
	 */
	public static function woo_activated(): bool {
		return class_exists( WooCommerce::class, false ) && defined( 'WC_ABSPATH' );
	}
}
