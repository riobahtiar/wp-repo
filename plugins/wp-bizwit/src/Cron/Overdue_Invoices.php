<?php
/**
 * Daily job: mark past-due open invoices as overdue.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Cron;

use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Stats_Repository;

/**
 * Schedules and runs overdue invoice status updates.
 */
class Overdue_Invoices {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'wp_bizwit_mark_overdue_invoices';

	/**
	 * Ensure a daily event is scheduled.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
	}

	/**
	 * Clear scheduled events (deactivation).
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );

		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Run the overdue pass.
	 *
	 * @return void
	 */
	public function run(): void {
		$updated = ( new Invoice_Repository() )->mark_overdue();

		if ( $updated > 0 ) {
			Stats_Repository::bust_cache();
		}

		/**
		 * Fires after overdue invoices were marked.
		 *
		 * @param int $updated Number of rows updated.
		 */
		do_action( 'wp_bizwit_overdue_invoices_marked', $updated );
	}
}
