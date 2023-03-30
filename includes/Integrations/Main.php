<?php
/**
 * @package Reepay\Checkout\Integrations
 */

namespace Reepay\Checkout\Integrations;

use Reepay\Checkout\Integrations\WooBlocks\WooBlocksIntegration;

defined( 'ABSPATH' ) || exit();

/**
 * Class Main
 *
 * @package Reepay\Checkout\Integrations
 */
class Main {
	public function __construct() {
		new WooBlocksIntegration();
	}
}

