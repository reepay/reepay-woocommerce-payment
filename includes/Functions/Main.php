<?php

namespace Reepay\Checkout\Functions;

defined( 'ABSPATH' ) || exit();

class Main {
	public function __construct() {
		include_once './currency.php';
		include_once './customer.php';
		include_once './format.php';
		include_once './gateways.php';
		include_once './order.php';
		include_once './subscriptions.php';
	}
}
