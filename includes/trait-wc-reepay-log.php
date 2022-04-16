<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

trait WC_Reepay_Log {
	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 *
	 * @return void
	 * @see WC_Log_Levels
	 *
	 */
	private function log( $message, $level = 'info' ) {
		// Get Logger instance
		$logger = wc_get_logger();

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
		}

		$logger->log( $level, $message, array(
			'source'  => $this->logging_source,
			'_legacy' => true
		) );
	}
}
