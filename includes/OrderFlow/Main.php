<?php

namespace Reepay\Checkout\OrderFlow;

defined( 'ABSPATH' ) || exit();

class Main {
	public function __construct() {
		new OrderStatuses();
		new OrderCapture();
		new InstantSettle();
		new ThankyouPage();
	}
}

