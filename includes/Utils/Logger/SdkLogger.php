<?php
/**
 * Billwerk Api logger
 *
 * @package Reepay\Checkout\Utils\Logger
 */

namespace Reepay\Checkout\Utils\Logger;

use Billwerk\Sdk\Logger\SdkLoggerInterface;

/**
 * Class logger
 */
class SdkLogger implements SdkLoggerInterface {
	public const SOURCE = 'api';

	/**
	 * Debug log
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function debug( $message, array $context = array() ) {
		reepay()->log( self::SOURCE )->debug( $message, $context );
	}

	/**
	 * Info log
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function info( $message, array $context = array() ) {
		reepay()->log( self::SOURCE )->info( $message, $context );
	}

	/**
	 * Error log
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function error( $message, array $context = array() ) {
		reepay()->log( self::SOURCE )->error( $message, $context );
	}
}
