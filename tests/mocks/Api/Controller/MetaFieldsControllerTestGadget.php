<?php
/**
 * Class MetaFieldsControllerTestGadget
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Mocks\Api\Controller;

/**
 * Gadget class used by MetaFieldsControllerTest to prove that untrusted serialized
 * meta values are (or are not) turned into real PHP objects. In a real attack this
 * would be any autoloadable class with a __wakeup()/__destruct()/__toString()
 * available on the site (WP core, WooCommerce, or another active plugin).
 */
class MetaFieldsControllerTestGadget {

	/**
	 * Flipped to true if PHP actually instantiates this class via unserialize().
	 *
	 * @var bool
	 */
	public static bool $woken = false;

	/**
	 * Magic method invoked by unserialize() when the class is allowed.
	 */
	public function __wakeup() {
		self::$woken = true;
	}
}
