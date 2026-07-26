<?php
/**
 * Race-free allocation of sequential document numbers.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Database;

/**
 * Allocates the next number for a named counter.
 *
 * The naive approach - `SELECT MAX(invoice_number) + 1` - is a lost-update bug
 * waiting to happen: two people saving an invoice in the same second both read
 * the same maximum and both write the same number, which then collides with the
 * UNIQUE index (or worse, does not, and you ship duplicate invoice numbers).
 *
 * Instead we let MySQL do the read-modify-write in one statement. The row lock
 * taken by `INSERT ... ON DUPLICATE KEY UPDATE` serialises concurrent callers,
 * and `LAST_INSERT_ID(expr)` smuggles the freshly computed value back out
 * through the connection's insert id, so the allocation and the read are a
 * single round trip that no other transaction can interleave with.
 */
class Sequence {

	/**
	 * Allocate and return the next value for a counter.
	 *
	 * @param string $key Counter identifier, e.g. 'invoice:2026'.
	 *
	 * @return int The allocated number, or 0 if allocation failed.
	 */
	public static function next( string $key ): int {
		global $wpdb;

		$table = Schema::table( Schema::SEQUENCES );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$table}` (sequence_key, next_value)
				VALUES (%s, LAST_INSERT_ID(1))
				ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value + 1)",
				$key
			)
		);
		// phpcs:enable

		if ( false === $result ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Peek at the next value without consuming it.
	 *
	 * Useful for previewing a number in a form. Never persist this value: by the
	 * time the form is submitted another user may have consumed it.
	 *
	 * @param string $key Counter identifier.
	 *
	 * @return int The value that would be allocated next.
	 */
	public static function peek( string $key ): int {
		global $wpdb;

		$table = Schema::table( Schema::SEQUENCES );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT next_value FROM `{$table}` WHERE sequence_key = %s",
				$key
			)
		);
		// phpcs:enable

		if ( null === $current ) {
			return 1;
		}

		return (int) $current + 1;
	}

	/**
	 * Force a counter to a specific value.
	 *
	 * Lets a business continue an existing numbering series when migrating onto
	 * this plugin mid-year.
	 *
	 * @param string $key   Counter identifier.
	 * @param int    $value The value that should be allocated next.
	 *
	 * @return bool True on success.
	 */
	public static function reset( string $key, int $value ): bool {
		global $wpdb;

		$table = Schema::table( Schema::SEQUENCES );
		$value = max( 1, $value );
		// next_value stores the *last allocated* number; next() returns last+1.
		// So to make the next allocation equal $value, persist $value - 1.
		$last = $value - 1;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$table}` (sequence_key, next_value)
				VALUES (%s, %d)
				ON DUPLICATE KEY UPDATE next_value = %d",
				$key,
				$last,
				$last
			)
		);
		// phpcs:enable

		return false !== $result;
	}
}
