<?php
/**
 * Functions to make testing easier
 *
 * @package Reepay\Checkout
 */

if ( ! function_exists( 'array_any' ) ) {
	function array_any( array $array, callable $fn ) {
		foreach ( $array as $value ) {
			if ( $fn( $value ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'array_every' ) ) {
	function array_every( array $array, callable $fn ) {
		foreach ( $array as $value ) {
			if ( ! $fn( $value ) ) {
				return false;
			}
		}

		return true;
	}
}