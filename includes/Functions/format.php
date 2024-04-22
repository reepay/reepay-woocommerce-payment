<?php
/**
 * Formatting functions
 *
 * @package Reepay\Checkout\Functions
 */

use Reepay\Checkout\Utils\EmojiRemover;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_format_credit_card' ) ) {
	/**
	 * Formats a credit card nicely
	 *
	 * @param string $cc is the card number to format nicely.
	 *
	 * @return string the nicely formatted value
	 */
	function rp_format_credit_card( string $cc ): string {
		$cc        = str_replace( array( '-', ' ' ), '', $cc );
		$cc_length = strlen( $cc );
		$new_cc    = substr( $cc, - 4 );

		for ( $i = $cc_length - 5; $i >= 0; $i-- ) {
			if ( ( ( $i + 1 ) - $cc_length ) % 4 === 0 ) {
				$new_cc = ' ' . $new_cc;
			}
			$new_cc = $cc[ $i ] . $new_cc;
		}

		for ( $i = 7; $i < $cc_length - 4; $i++ ) {
			if ( ' ' === $new_cc[ $i ] ) {
				continue;
			}
			$new_cc[ $i ] = 'X';
		}

		return $new_cc;
	}
}

if ( ! function_exists( 'rp_clear_ordertext' ) ) {
	/**
	 * Clear product titles emoji
	 *
	 * @param string $title Product title.
	 *
	 * @return string the nicely formatted value
	 */
	function rp_clear_ordertext( string $title ): string {
		return EmojiRemover::filter( $title );
	}
}
