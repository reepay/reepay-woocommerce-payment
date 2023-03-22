<?php

namespace Reepay\Checkout\Admin;

defined( 'ABSPATH' ) || exit();

class Main {
	public function __construct() {
		new PluginsPage();
	}
}

