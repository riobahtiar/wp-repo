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
 * Results are cached briefly (transient) so a busy dashboard does not re-run
 * the same SUM/COUNT queries on every tile. Cache is busted from
 * Activity_Repository on every business write.
 */
class Stats_Repository extends Repository {

	/**
	 * Transient key for the aggregated stats bag.
	 *
	 * @var string
	 */
	public const CACHE_KEY = 'wp_bizwit_stats_v1';

	/**
	 * How long to keep dashboard aggregates (seconds).
	 *
	 * Ageing buckets depend on "today", so keep this short.
	 *
	 * @var int
	 */
	public const CACHE_TTL = 120;

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
	 * Drop cached aggregates (call after any money/status write).
	 *
	 * @return void
	 */
	public static function bust_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Number of clients, optionally restricted to a status.
	 *
	 * @param string $status Status slug, or '' for all statuses.
	 *
	 * @return int Client count.
	 */
	public function clients( string $status = '' ): int {
		return (int) $this->remember(
			'clients_' . $status,
			function () use ( $status ) {
				if ( '' === $status ) {
					return $this->count_in( Schema::table( Schema::CLIENTS ), '1=%d', array( 1 ) );
				}

				return $this->count_in( Schema::table( Schema::CLIENTS ), 'status = %s', array( $status ) );
			}
		);
	}

	/**
	 * Number of projects, optionally restricted to a status.
	 *
	 * @param string $status Status slug, or '' for all statuses.
	 *
	 * @return int Project count.
	 */
	public function projects( string $status = '' ): int {
		return (int) $this->remember(
			'projects_' . $status,
			function () use ( $status ) {
				if ( '' === $status ) {
					return $this->count_in( Schema::table( Schema::PROJECTS ), '1=%d', array( 1 ) );
				}

				return $this->count_in( Schema::table( Schema::PROJECTS ), 'status = %s', array( $status ) );
			}
		);
	}

	/**
	 * Number of invoices, optionally restricted to a status.
	 *
	 * @param string $status Status slug, or '' for all statuses.
	 *
	 * @return int Invoice count.
	 */
	public function invoices( string $status = '' ): int {
		return (int) $this->remember(
			'invoices_' . $status,
			function () use ( $status ) {
				if ( '' === $status ) {
					return $this->count_in( Schema::table( Schema::INVOICES ), '1=%d', array( 1 ) );
				}

				return $this->count_in( Schema::table( Schema::INVOICES ), 'status = %s', array( $status ) );
			}
		);
	}

	/**
	 * Total unpaid amount across all invoices that are not draft or void.
	 *
	 * @return int Outstanding amount in minor units.
	 */
	public function outstanding_minor(): int {
		return (int) $this->remember(
			'outstanding',
			function () {
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
		);
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
		return (int) $this->remember(
			'payments_' . $from . '_' . $to,
			function () use ( $from, $to ) {
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
		);
	}

	/**
	 * Outstanding balances grouped by ageing bucket (relative to due date).
	 *
	 * Buckets: current (not past due), d1_30, d31_60, d61_plus.
	 * Only open invoices (not draft/void/paid) with a balance contribute.
	 *
	 * @return array{current: int, d1_30: int, d31_60: int, d61_plus: int, total: int}
	 */
	public function outstanding_ageing_minor(): array {
		$cached = $this->remember(
			'ageing_' . (string) current_time( 'Y-m-d' ),
			function () {
				return $this->query_outstanding_ageing_minor();
			}
		);

		return is_array( $cached ) ? $cached : $this->empty_ageing();
	}

	/**
	 * Most recently updated invoices for the dashboard activity list.
	 *
	 * @param int $limit Max rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_invoices( int $limit = 5 ): array {
		$limit = max( 1, min( 20, $limit ) );
		// Not cached: list should feel live and is a cheap ORDER BY LIMIT query.
		$table   = Schema::table( Schema::INVOICES );
		$clients = Schema::table( Schema::CLIENTS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT i.id, i.invoice_number, i.status, i.total_minor, i.currency, i.updated_at, c.display_name AS client_name
				FROM `{$table}` i
				LEFT JOIN `{$clients}` c ON c.id = i.client_id
				ORDER BY i.updated_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Memoize a scalar or array value inside the shared transient bag.
	 *
	 * @param string   $key Cache key inside the bag.
	 * @param callable $cb  Producer when missing.
	 *
	 * @return mixed
	 */
	private function remember( string $key, callable $cb ) {
		$bag = get_transient( self::CACHE_KEY );
		if ( ! is_array( $bag ) ) {
			$bag = array();
		}

		if ( array_key_exists( $key, $bag ) ) {
			return $bag[ $key ];
		}

		$value       = $cb();
		$bag[ $key ] = $value;
		set_transient( self::CACHE_KEY, $bag, self::CACHE_TTL );

		return $value;
	}

	/**
	 * Run the ageing aggregate query.
	 *
	 * @return array{current: int, d1_30: int, d31_60: int, d61_plus: int, total: int}
	 */
	private function query_outstanding_ageing_minor(): array {
		$table = Schema::table( Schema::INVOICES );
		$today = (string) current_time( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT
					CASE
						WHEN due_date IS NULL OR due_date = '0000-00-00' OR due_date >= %s THEN 'current'
						WHEN DATEDIFF( %s, due_date ) BETWEEN 1 AND 30 THEN 'd1_30'
						WHEN DATEDIFF( %s, due_date ) BETWEEN 31 AND 60 THEN 'd31_60'
						ELSE 'd61_plus'
					END AS bucket,
					COALESCE( SUM( total_minor - paid_minor ), 0 ) AS amount
				FROM `{$table}`
				WHERE status NOT IN ( 'draft', 'void', 'paid' )
				  AND total_minor > paid_minor
				GROUP BY bucket",
				$today,
				$today,
				$today
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = $this->empty_ageing();

		foreach ( (array) $rows as $row ) {
			$bucket = (string) ( $row['bucket'] ?? '' );
			$amount = (int) ( $row['amount'] ?? 0 );
			if ( isset( $out[ $bucket ] ) ) {
				$out[ $bucket ] = $amount;
				$out['total']  += $amount;
			}
		}

		return $out;
	}

	/**
	 * Empty ageing structure.
	 *
	 * @return array{current: int, d1_30: int, d31_60: int, d61_plus: int, total: int}
	 */
	private function empty_ageing(): array {
		return array(
			'current'  => 0,
			'd1_30'    => 0,
			'd31_60'   => 0,
			'd61_plus' => 0,
			'total'    => 0,
		);
	}
}
