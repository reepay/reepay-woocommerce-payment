<?php
/**
 * Order lock that keeps two webhooks of the same order from running at the same time.
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

defined( 'ABSPATH' ) || exit();

/**
 * Class WebhookLock
 *
 * @package Reepay\Checkout\OrderFlow
 */
class WebhookLock {
	/**
	 * Seconds after which a lock left behind by a dead request can be taken over.
	 */
	const TTL = 60;

	/**
	 * Version of the lock table, stored per site in an option.
	 */
	const DB_VERSION = '1';

	/**
	 * Option with the version of the lock table that is installed.
	 */
	const DB_VERSION_OPTION = 'reepay_webhook_lock_db_version';

	/**
	 * Order id and owner token of the lock held by this object.
	 *
	 * @var array|null
	 */
	private ?array $held = null;

	/**
	 * Name of the lock table of the current site.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'reepay_webhook_locks';
	}

	/**
	 * Check that the lock table can be queried.
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );
		$table    = self::table_name();

		$wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = '' === $wpdb->last_error;

		$wpdb->suppress_errors( $suppress );

		return $exists;
	}

	/**
	 * Create or update the lock table. The version is stored only when the table exists,
	 * so a failed creation is tried again by the next request.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			order_id bigint(20) unsigned NOT NULL,
			token char(36) NOT NULL,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (order_id)
		) {$charset};"
		);

		if ( self::table_exists() ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}
	}

	/**
	 * Run the queries that take the lock: one insert decides the owner, and a lock past its expiry is
	 * taken over with one conditional update.
	 *
	 * @param int    $order_id order id.
	 * @param string $token    owner token.
	 * @param int    $now      current time.
	 *
	 * @return int|false Number of rows changed, false on a database error.
	 */
	private function run_acquire_queries( int $order_id, string $token, int $now ) {
		global $wpdb;

		$table   = self::table_name();
		$expires = $now + self::TTL;

		// A missing table is handled by the caller, so the error is not printed.
		$suppress = $wpdb->suppress_errors( true );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (order_id, token, expires_at) VALUES (%d, %s, %d)", $order_id, $token, $expires ) );

		if ( 0 === $result ) {
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET token = %s, expires_at = %d WHERE order_id = %d AND expires_at < %d", $token, $expires, $order_id, $now ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$wpdb->suppress_errors( $suppress );

		return $result;
	}

	/**
	 * Try once to take the lock of an order. Only one request can get it.
	 * If the lock table is missing although the version option says it is installed (for example after a
	 * database restore or a site migration), the table is created again and the attempt is repeated once.
	 *
	 * @param int $order_id order id.
	 *
	 * @return bool|null True if the lock is taken, false if another request holds it, null if the lock table cannot be used.
	 */
	public function acquire( int $order_id ): ?bool {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}

		$token  = wp_generate_uuid4();
		$now    = time();
		$result = $this->run_acquire_queries( $order_id, $token, $now );

		if ( false === $result && ! self::table_exists() ) {
			self::install();
			$result = $this->run_acquire_queries( $order_id, $token, $now );
		}

		if ( false === $result ) {
			return null;
		}

		if ( 1 !== $result ) {
			return false;
		}

		$this->held = array(
			'order_id' => $order_id,
			'token'    => $token,
		);

		return true;
	}

	/**
	 * Release the lock held by this object. A lock taken over by another request is left alone.
	 * Rows that expired more than a day ago are removed at the same time.
	 *
	 * @return void
	 */
	public function release() {
		global $wpdb;

		if ( null === $this->held ) {
			return;
		}

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE ( order_id = %d AND token = %s ) OR expires_at < %d", $this->held['order_id'], $this->held['token'], time() - DAY_IN_SECONDS ) );

		$this->held = null;
	}
}
