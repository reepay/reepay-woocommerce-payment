<?php

namespace Reepay\Checkout\Frontend;

defined( 'ABSPATH' ) || exit();

class Main {
	public function __construct() {
		new Assets();
	}
}
