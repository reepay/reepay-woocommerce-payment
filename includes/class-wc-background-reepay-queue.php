<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Background_Process', false ) ) {
	include_once WC_ABSPATH . '/includes/abstracts/class-wc-background-process.php';
}

/**
 * Class WC_Background_Reepay_Queue
 */
class WC_Background_Reepay_Queue extends WC_Background_Process {
	use WC_Reepay_Log;

	/**
	 * @var string
	 */
	private $logging_source = 'wc_reepay_queue';


	/**
	 * Initiate new background process.
	 */
	public function __construct() {
		$this->logger = wc_get_logger();

		// Uses unique prefix per blog so each blog has separate queue.
		$this->prefix = 'wp_' . get_current_blog_id();
		$this->action = 'wc_reepay_queue';

		// Dispatch queue after shutdown.
		add_action( 'shutdown', array( $this, 'dispatch_queue' ), 100 );

		parent::__construct();
	}

	/**
	 * Schedule fallback event.
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event(
				time() + MINUTE_IN_SECONDS,
				$this->cron_interval_identifier,
				$this->cron_hook_identifier
			);
		}
	}

	/**
	 * Code to execute for each item in the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return bool
	 */
	protected function task( $item ) {
		$this->log( sprintf( 'Start task: %s', var_export( $item, true ) ) );

		try {
			$data = json_decode( $item['webhook_data'], true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$this->log( '[ERROR]: Invalid webhook data' );

				return false;
			}

			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Reepay_Checkout $gateway */
			$gateway = isset( $gateways[ $item['payment_method_id'] ] ) ? $gateways[ $item['payment_method_id'] ] : false;
			if ( ! $gateway ) {
				$this->log( '[ERROR]: Can\'t retrieve payment gateway instance: ' . $item['payment_method_id'] );

				return false;
			}

			if ( ! isset( $data['id'] ) ) {
				throw new Exception( 'Error: Invalid ID' );
			}

			// Process webhook
			$this->log( sprintf( 'Processing webhook: %s', var_export( $data, true ) ) );

			( new WC_Reepay_Webhook( $data ) )->process();
		} catch ( Exception $e ) {
			$this->log( sprintf( '[ERROR]: %s', $e->getMessage() ) );
		}

		return true;
	}

	/**
	 * This runs once the job has completed all items on the queue.
	 *
	 * @return void
	 */
	protected function complete() {
		parent::complete();

		$this->log( 'Completed reepay queue job.' );
	}

	/**
	 * Save and run queue.
	 */
	public function dispatch_queue() {
		if ( ! empty( $this->data ) ) {
			$this->save()->dispatch();
		}
	}
}
