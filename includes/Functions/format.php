<?php
/**
 * Formatting functions
 *
 * @package Reepay\Checkout\Functions
 */

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_format_credit_card' ) ) {
	/**
	 * Formats a credit card nicely
	 *
	 * @param string $cc is the card number to format nicely.
	 *
	 * @return false|string the nicely formatted value
	 */
	function rp_format_credit_card( string $cc ) {
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

	/**
	 * Clear product titles emoji
	 *
	 * @param string $title Product title.
	 *
	 * @return string the nicely formatted value
	 */
	function rp_clear_ordertext( string $title ): string {
		// Match Enclosed Alphanumeric Supplement.
		$regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
		$clear_string       = preg_replace( $regex_alphanumeric, '', $title );

		// Match Miscellaneous Symbols and Pictographs.
		$regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
		$clear_string  = preg_replace( $regex_symbols, '', $clear_string );

		// Match Emoticons.
		$regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
		$clear_string    = preg_replace( $regex_emoticons, '', $clear_string );

		// Match Transport And Map Symbols.
		$regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
		$clear_string    = preg_replace( $regex_transport, '', $clear_string );

		// Match Supplemental Symbols and Pictographs.
		$regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
		$clear_string       = preg_replace( $regex_supplemental, '', $clear_string );

		// Match Miscellaneous Symbols.
		$regex_misc   = '/[\x{2600}-\x{26FF}]/u';
		$clear_string = preg_replace( $regex_misc, '', $clear_string );

		// Match Dingbats.
		$regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
		$clear_string   = preg_replace( $regex_dingbats, '', $clear_string );

		return $clear_string;
	}
}
