<?php
/**
 * Trait for logging
 *
 * @package Reepay\Checkout\Utils
 */

namespace Reepay\Checkout\Utils;

defined( 'ABSPATH' ) || exit();

/**
 * Class logging
 *
 * @package Reepay\Checkout\Utils
 */
trait LoggingTrait {
	/**
	 * Logging method.
	 *
	 * @param mixed $message Log message.
	 *
	 * @return void
	 * @see WC_Log_Levels
	 */
	public function log( $message ) {
		if ( ! is_string( $message ) ) {
			$message = print_r( $message, true ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				$message,
				array(
					'source'  => $this->logging_source,
					'_legacy' => true,
				)
			);
		} else {
			// if Woocommerce disabled.
			error_log( print_r( $message, true ) ); //phpcs:ignore
		}
	}
}
