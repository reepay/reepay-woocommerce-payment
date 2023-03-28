<?php

namespace Reepay\Checkout;

defined( 'ABSPATH' ) || exit();

trait LoggingTrait {
	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 * @see WC_Log_Levels
	 */
	public function log( $message ) {
		// Get Logger instance
		$logger = wc_get_logger();

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
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
