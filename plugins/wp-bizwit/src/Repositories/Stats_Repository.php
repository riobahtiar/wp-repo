<?php
/**
 * Aggregate figures for the dashboard.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use WP_BizWit\Database\Schema;

/**
 * Read-only aggregates across the plugin's tables.
 *
 * Kept separate from the entity repositories because these queries answer
 * questions about the business rather than about a record, and because they are
 * the ones most likely to grow caching later.
 */
class Stats_Repository extends Repository {

	/**
	 * Nominal table for this repository.
	 *
	 * Unused by the aggregate queries below, which each name their own table.
	 *
	 * @return string Fully prefixed table name.
	 */
	protected function table(): string {
		return Schema::table( Schema::CLIENTS );
	}

	/**
	 * Number of clients, optionally restricted to a status.
	 *
	 * @param string $status Status slug, or '' for all statuses.
	 *
	 * @return int Client count.
	 */
	public function clients( string $status = '' ): int {
		if ( '' === $status ) {
			return $this->count_in( Schema::table( Schema::CLIENTS ), '1=%d', array( 1 ) );
		}

		return $this->count_in( Schema::table( Schema::CLIENTS ), 'status = %s', array( $status ) );
	}

	/**
	 * Number of projects, optionally restricted to a status.
	 *
	 * @param string $status Status slug, or '' for all statuses.
	 *
	 * @return int Project count.
	 */
	public function projects( string $status = '' ): int {
		if ( '' === $status ) {
			return $this->count_in( Schema::table( Schema::PROJECTS ), '1=%d', array( 1 ) );
		}

		return $this->count_in( Schema::table( Schema::PROJECTS ), 'status = %s', array( $status ) );
	}

	/**
	 * Number of invoices, optionally restricted to a status.
	 *
	 * @param string $status Status slug, or '' for all statuses.
	 *
	 * @return int Invoice count.
	 */
	public function invoices( string $status = '' ): int {
		if ( '' === $status ) {
			return $this->count_in( Schema::table( Schema::INVOICES ), '1=%d', array( 1 ) );
		}

		return $this->count_in( Schema::table( Schema::INVOICES ), 'status = %s', array( $status ) );
	}

	/**
	 * Total unpaid amount across all invoices that are not draft or void.
	 *
	 * @return int Outstanding amount in minor units.
	 */
	public function outstanding_minor(): int {
		$table = Schema::table( Schema::INVOICES );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $this->db()->get_var(
			"SELECT COALESCE( SUM( total_minor - paid_minor ), 0 )
			FROM `{$table}`
			WHERE status NOT IN ( 'draft', 'void' )"
		);
		// phpcs:enable

		return (int) $total;
	}

	/**
	 * Total payments recorded within a date range.
	 *
	 * @param string $from Inclusive start date, Y-m-d.
	 * @param string $to   Inclusive end date, Y-m-d.
	 *
	 * @return int Amount received in minor units.
	 */
	public function payments_between( string $from, string $to ): int {
		$table = Schema::table( Schema::PAYMENTS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $this->db()->get_var(
			$this->db()->prepare(
				"SELECT COALESCE( SUM( amount_minor ), 0 ) FROM `{$table}` WHERE paid_on BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		// phpcs:enable

		return (int) $total;
	}
}
