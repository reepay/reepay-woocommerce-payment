<?php

namespace Reepay\Checkout\Integrations;

use Reepay\Checkout\Integrations\WooBlocks\WooBlocksIntegration;

defined( 'ABSPATH' ) || exit();

class Main {
	public function __construct() {
		new WooBlocksIntegration();
	}
}

