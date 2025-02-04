<?php
/**
 * Integration with Polylang plugin https://wordpress.org/plugins/polylang/
 *
 * @package Reepay\Checkout\Integrations
 */

namespace Reepay\Checkout\Integrations;

/**
 * Class integration
 *
 * @package Reepay\Checkout\Integrations
 */
class PolylangIntegration {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'initialize' ) );
	}

	/**
	 * Initialize the integration.
	 */
	public function initialize() {
		// Check if the request is an AJAX call.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Check if Polylang function exists.
		if ( ! function_exists( 'pll_current_language' ) ) {
			return;
		}

		add_filter( 'woocommerce_get_script_data', array( $this, 'polylang_ajax_handler_fix_translation' ), 10, 1 );
	}

	/**
	 * Return params for script handles.
	 *
	 * @param  string $params Script handle the data will be attached to.
	 * @return array|bool
	 */
	public function polylang_ajax_handler_fix_translation( $params ) {
		if ( ! function_exists( 'ajax_handler_fix_translation' ) ) {
			// Get the current language.
			$locale = determine_locale();
			// Take just the first part of the $locale.
			$lang = ( ! empty( $locale ) ) ? strstr( $locale, '_', true ) : '';
			if ( empty( $lang ) ) {
				// If there is no $lang parameter, just return to standard.
				return $params;
			}
			if ( isset( $params['wc_ajax_url'] ) ) {
				$locale = $this->get_locale_from_language_code( $lang );
				set_transient( 'billwerk_pll_current_language_transient', $locale, HOUR_IN_SECONDS );
			}

			return $params;
		}
	}

	/**
	 * Get the locale from the language code.
	 *
	 * @param  string $lang Language code.
	 * @return string Locale.
	 */
	private function get_locale_from_language_code( $lang ) {
		// Get the list of languages.
		$languages = PLL()->model->get_languages_list();

		// Find the locale corresponding to the language code.
		foreach ( $languages as $language ) {
			if ( $language->slug === $lang ) {
				return $language->locale;
			}
		}

		// Fallback to default locale if not found.
		return pll_default_language( 'locale' );
	}
}
