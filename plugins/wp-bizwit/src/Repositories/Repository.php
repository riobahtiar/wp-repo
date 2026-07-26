<?php
/**
 * Shared persistence plumbing for the plugin's custom tables.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use wpdb;

/**
 * Base class for table-backed repositories.
 *
 * Keeping every raw $wpdb call behind a repository means the phpcs suppressions
 * that direct database access requires live in a handful of audited methods
 * rather than being sprinkled across admin screens, where a missing prepare()
 * would be much easier to overlook in review.
 */
abstract class Repository {

	/**
	 * Fully prefixed table name this repository reads and writes.
	 *
	 * @return string Table name.
	 */
	abstract protected function table(): string;

	/**
	 * The global WordPress database handle.
	 *
	 * @return wpdb Database handle.
	 */
	protected function db(): wpdb {
		global $wpdb;

		return $wpdb;
	}

	/**
	 * Current site-local time in MySQL DATETIME format.
	 *
	 * Business records are read and reasoned about in the site's timezone, so
	 * they are stored that way too. Do not mix this with GMT timestamps.
	 *
	 * @return string Datetime string.
	 */
	protected function now(): string {
		return (string) current_time( 'mysql' );
	}

	/**
	 * Fetch a single row by primary key.
	 *
	 * @param int $id Row id.
	 *
	 * @return array<string, mixed>|null Row data, or null when not found.
	 */
	public function find( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string, mixed> $data    Column values.
	 * @param string[]             $formats Printf-style format for each column, in the same order.
	 *
	 * @return int Inserted row id, or 0 on failure.
	 */
	protected function insert_row( array $data, array $formats ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db()->insert( $this->table(), $data, $formats );
		// phpcs:enable

		return false === $result ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * Update a row by primary key.
	 *
	 * @param int                  $id      Row id.
	 * @param array<string, mixed> $data    Column values.
	 * @param string[]             $formats Printf-style format for each column, in the same order.
	 *
	 * @return bool True when the query executed without error.
	 */
	protected function update_row( int $id, array $data, array $formats ): bool {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db()->update( $this->table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
		// phpcs:enable

		return false !== $result;
	}

	/**
	 * Delete a row by primary key.
	 *
	 * @param int $id Row id.
	 *
	 * @return bool True when a row was removed.
	 */
	protected function delete_row( int $id ): bool {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db()->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		// phpcs:enable

		return ! empty( $result );
	}

	/**
	 * Count rows in an arbitrary table matching a prepared condition.
	 *
	 * @param string  $table  Fully prefixed table name. Must never contain user input.
	 * @param string  $where  WHERE clause with placeholders, without the `WHERE` keyword.
	 * @param mixed[] $params Values bound to the placeholders.
	 *
	 * @return int Matching row count.
	 */
	protected function count_in( string $table, string $where, array $params ): int {
		$sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->db()->get_var( $this->db()->prepare( $sql, $params ) );
		// phpcs:enable

		return (int) $count;
	}
}
