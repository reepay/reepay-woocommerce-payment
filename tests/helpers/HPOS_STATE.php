<?php
/**
 * Class PLUGINS_STATE
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Exception;
use PHPUnit\Framework\Assert;
use WC_Product;

/**
 * Class PLUGINS_STATE
 */
abstract class HPOS_STATE {
	private static bool $inited = false;

	private static bool $hpos_activated;

	private static string $option_name = 'woocommerce_custom_orders_table_enabled';

	/**
	 * Activate plugins. Depending on param or environment variable
	 *
	 * @param array $plugins plugins to activate.
	 */
	public static function init( ?bool $hpos_activated = null ) {
		if( self::$inited ) {
			throw new Exception('HPOS_STATE already inited');
		}

		if ( is_null( $hpos_activated ) ) {
			$hpos_activated = trim( getenv( 'HPOS_ENABLED' ) ) === 'yes';
		}

		self::$hpos_activated = $hpos_activated;

		update_option( self::$option_name, self::$hpos_activated ? 'yes' : 'no' );
	}

	public static function is_active(): bool {
		return self::$hpos_activated;
	}
}
