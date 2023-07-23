<?php
/**
 * Class ApiMock
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Mocks;

use Reepay\Checkout\Api;
use Exception;

/**
 * Class ApiMock
 */
class ApiMock extends Api {
	public function request( string $method, string $url, $params = array(), $force_live = false ) {
		throw new Exception( 'Api::request called. Api method not mocked' );
	}
}