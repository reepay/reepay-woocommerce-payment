<?php
/**
 * Trait for logging
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout;

defined( 'ABSPATH' ) || exit();

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
		// Get Logger instance.
		$logger = wc_get_logger();

		// Write message to log.
		if ( ! is_string( $message ) ) {
			$message = print_r( $message, true ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$logger->debug(
			$message,
			array(
				'source'  => $this->logging_source,
				'_legacy' => true,
			)
		);
	}
}
