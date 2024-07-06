<?php
/**
 * Helper for order statuses
 *
 * @package Reepay\Checkout\Utils
 */

namespace Reepay\Checkout\Utils;

/**
 * Enum
 *
 * @package Reepay\Checkout\Utils
 */
class WcOrderStatus {
	public const PENDING    = 'pending';
	public const PROCESSING = 'processing';
	public const ON_HOLD    = 'on-hold';
	public const COMPLETED  = 'completed';
	public const CANCELLED  = 'cancelled';
	public const REFUNDED   = 'refunded';
	public const FAILED     = 'failed';
}
