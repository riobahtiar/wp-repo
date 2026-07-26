<?php
/**
 * Activity / audit trail for business record changes.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use WP_BizWit\Database\Schema;
use WP_BizWit\Support\Invoice_Status;

/**
 * Records who changed which entity, and when.
 *
 * Writes are hooked from existing `wp_bizwit_*` repository actions so screens
 * never call this class directly. Retention defaults to 365 days.
 */
class Activity_Repository extends Repository {

	/**
	 * Default retention in days.
	 *
	 * @var int
	 */
	public const RETENTION_DAYS = 365;

	/**
	 * Entity type constants.
	 */
	public const ENTITY_CLIENT  = 'client';
	public const ENTITY_PROJECT = 'project';
	public const ENTITY_INVOICE = 'invoice';
	public const ENTITY_PAYMENT = 'payment';

	/**
	 * Transient that rate-limits prune runs.
	 *
	 * @var string
	 */
	private const PRUNE_TRANSIENT = 'wp_bizwit_activity_pruned';

	/**
	 * Activity table.
	 *
	 * @return string
	 */
	protected function table(): string {
		return Schema::table( Schema::ACTIVITY );
	}

	/**
	 * Register listeners on repository lifecycle actions.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_bizwit_client_created', array( $this, 'on_client_created' ), 10, 2 );
		add_action( 'wp_bizwit_client_updated', array( $this, 'on_client_updated' ), 10, 2 );
		add_action( 'wp_bizwit_client_deleted', array( $this, 'on_client_deleted' ), 10, 1 );

		add_action( 'wp_bizwit_project_created', array( $this, 'on_project_created' ), 10, 2 );
		add_action( 'wp_bizwit_project_updated', array( $this, 'on_project_updated' ), 10, 2 );
		add_action( 'wp_bizwit_project_deleted', array( $this, 'on_project_deleted' ), 10, 1 );

		add_action( 'wp_bizwit_invoice_created', array( $this, 'on_invoice_created' ), 10, 2 );
		add_action( 'wp_bizwit_invoice_updated', array( $this, 'on_invoice_updated' ), 10, 2 );
		add_action( 'wp_bizwit_invoice_deleted', array( $this, 'on_invoice_deleted' ), 10, 1 );
		add_action( 'wp_bizwit_invoice_transitioned', array( $this, 'on_invoice_transitioned' ), 10, 3 );

		add_action( 'wp_bizwit_payment_created', array( $this, 'on_payment_created' ), 10, 2 );
		add_action( 'wp_bizwit_payment_updated', array( $this, 'on_payment_updated' ), 10, 2 );
		add_action( 'wp_bizwit_payment_deleted', array( $this, 'on_payment_deleted' ), 10, 2 );
	}

	/**
	 * Append one activity row and bust dashboard stats cache.
	 *
	 * @param string               $entity_type Entity type slug.
	 * @param int                  $entity_id   Entity primary key.
	 * @param string               $action      Action slug (created, updated, …).
	 * @param string               $summary     Short human-readable line (already translated).
	 * @param array<string, mixed> $meta        Non-sensitive metadata (keys only preferred).
	 *
	 * @return int New row id, or 0 on failure.
	 */
	public function record( string $entity_type, int $entity_id, string $action, string $summary, array $meta = array() ): int {
		$entity_type = sanitize_key( $entity_type );
		$action      = sanitize_key( $action );
		$summary     = sanitize_text_field( $summary );
		if ( '' === $entity_type || '' === $action || '' === $summary ) {
			return 0;
		}

		$meta_json = wp_json_encode( $meta );
		if ( ! is_string( $meta_json ) ) {
			$meta_json = '{}';
		}

		$data = array(
			'actor_id'    => get_current_user_id(),
			'entity_type' => $entity_type,
			'entity_id'   => max( 0, $entity_id ),
			'action'      => $action,
			'summary'     => self::truncate_summary( $summary ),
			'meta'        => $meta_json,
			'created_at'  => $this->now(),
		);

		$id = $this->insert_row(
			$data,
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		Stats_Repository::bust_cache();
		$this->maybe_prune();

		return $id;
	}

	/**
	 * Most recent activity rows for the dashboard.
	 *
	 * @param int $limit Max rows (1–50).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 12 ): array {
		$limit = max( 1, min( 50, $limit ) );
		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT id, actor_id, entity_type, entity_id, action, summary, created_at
				FROM `{$table}`
				ORDER BY id DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete rows older than the retention window.
	 *
	 * @param int $days Keep this many days of history.
	 *
	 * @return int Rows deleted.
	 */
	public function prune( int $days = self::RETENTION_DAYS ): int {
		$days  = max( 30, min( 3650, $days ) );
		$table = $this->table();
		// created_at is site-local (current_time( 'mysql' )); cut off the same way.
		$cutoff = gmdate(
			'Y-m-d H:i:s',
			(int) current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $this->db()->query(
			$this->db()->prepare(
				"DELETE FROM `{$table}` WHERE created_at < %s",
				$cutoff
			)
		);
		// phpcs:enable

		return is_int( $deleted ) ? max( 0, $deleted ) : 0;
	}

	/**
	 * Client created.
	 *
	 * @param int                  $id   Client id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_client_created( int $id, array $data ): void {
		$name = (string) ( $data['display_name'] ?? '' );
		$this->record(
			self::ENTITY_CLIENT,
			$id,
			'created',
			sprintf(
				/* translators: %s: client display name */
				__( 'Client “%s” created', 'wp-bizwit' ),
				$name
			)
		);
	}

	/**
	 * Client updated.
	 *
	 * @param int                  $id   Client id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_client_updated( int $id, array $data ): void {
		$name = (string) ( $data['display_name'] ?? '' );
		$this->record(
			self::ENTITY_CLIENT,
			$id,
			'updated',
			sprintf(
				/* translators: %s: client display name */
				__( 'Client “%s” updated', 'wp-bizwit' ),
				$name
			)
		);
	}

	/**
	 * Client deleted.
	 *
	 * @param int $id Client id.
	 *
	 * @return void
	 */
	public function on_client_deleted( int $id ): void {
		$this->record(
			self::ENTITY_CLIENT,
			$id,
			'deleted',
			sprintf(
				/* translators: %d: client id */
				__( 'Client #%d deleted', 'wp-bizwit' ),
				$id
			)
		);
	}

	/**
	 * Project created.
	 *
	 * @param int                  $id   Project id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_project_created( int $id, array $data ): void {
		$name = (string) ( $data['name'] ?? '' );
		$this->record(
			self::ENTITY_PROJECT,
			$id,
			'created',
			sprintf(
				/* translators: %s: project name */
				__( 'Project “%s” created', 'wp-bizwit' ),
				$name
			)
		);
	}

	/**
	 * Project updated.
	 *
	 * @param int                  $id   Project id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_project_updated( int $id, array $data ): void {
		$name = (string) ( $data['name'] ?? '' );
		$this->record(
			self::ENTITY_PROJECT,
			$id,
			'updated',
			sprintf(
				/* translators: %s: project name */
				__( 'Project “%s” updated', 'wp-bizwit' ),
				$name
			)
		);
	}

	/**
	 * Project deleted.
	 *
	 * @param int $id Project id.
	 *
	 * @return void
	 */
	public function on_project_deleted( int $id ): void {
		$this->record(
			self::ENTITY_PROJECT,
			$id,
			'deleted',
			sprintf(
				/* translators: %d: project id */
				__( 'Project #%d deleted', 'wp-bizwit' ),
				$id
			)
		);
	}

	/**
	 * Invoice created.
	 *
	 * @param int                  $id   Invoice id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_invoice_created( int $id, array $data ): void {
		$number = (string) ( $data['invoice_number'] ?? (string) $id );
		$this->record(
			self::ENTITY_INVOICE,
			$id,
			'created',
			sprintf(
				/* translators: %s: invoice number */
				__( 'Invoice %s created', 'wp-bizwit' ),
				$number
			),
			array( 'status' => (string) ( $data['status'] ?? '' ) )
		);
	}

	/**
	 * Invoice updated.
	 *
	 * @param int                  $id   Invoice id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_invoice_updated( int $id, array $data ): void {
		$number = (string) ( $data['invoice_number'] ?? (string) $id );
		$this->record(
			self::ENTITY_INVOICE,
			$id,
			'updated',
			sprintf(
				/* translators: %s: invoice number */
				__( 'Invoice %s updated', 'wp-bizwit' ),
				$number
			),
			array( 'status' => (string) ( $data['status'] ?? '' ) )
		);
	}

	/**
	 * Invoice deleted.
	 *
	 * @param int $id Invoice id.
	 *
	 * @return void
	 */
	public function on_invoice_deleted( int $id ): void {
		$this->record(
			self::ENTITY_INVOICE,
			$id,
			'deleted',
			sprintf(
				/* translators: %d: invoice id */
				__( 'Invoice #%d deleted', 'wp-bizwit' ),
				$id
			)
		);
	}

	/**
	 * Invoice status transition (sent, void, paid, …).
	 *
	 * @param int    $id   Invoice id.
	 * @param string $from Previous status.
	 * @param string $to   New status.
	 *
	 * @return void
	 */
	public function on_invoice_transitioned( int $id, string $from, string $to ): void {
		$labels = Invoice_Status::labels();
		$from_l = $labels[ $from ] ?? $from;
		$to_l   = $labels[ $to ] ?? $to;

		// Prefer number when still findable.
		$row    = $this->find_invoice_number( $id );
		$number = '' !== $row ? $row : (string) $id;

		$this->record(
			self::ENTITY_INVOICE,
			$id,
			'transitioned',
			sprintf(
				/* translators: 1: invoice number, 2: previous status, 3: new status */
				__( 'Invoice %1$s: %2$s → %3$s', 'wp-bizwit' ),
				$number,
				$from_l,
				$to_l
			),
			array(
				'from' => $from,
				'to'   => $to,
			)
		);
	}

	/**
	 * Payment created.
	 *
	 * @param int                  $id   Payment id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_payment_created( int $id, array $data ): void {
		$receipt = (string) ( $data['receipt_number'] ?? (string) $id );
		$this->record(
			self::ENTITY_PAYMENT,
			$id,
			'created',
			sprintf(
				/* translators: %s: receipt number */
				__( 'Payment %s recorded', 'wp-bizwit' ),
				$receipt
			)
		);
	}

	/**
	 * Payment updated.
	 *
	 * @param int                  $id   Payment id.
	 * @param array<string, mixed> $data Stored columns.
	 *
	 * @return void
	 */
	public function on_payment_updated( int $id, array $data ): void {
		$receipt = (string) ( $data['receipt_number'] ?? (string) $id );
		$this->record(
			self::ENTITY_PAYMENT,
			$id,
			'updated',
			sprintf(
				/* translators: %s: receipt number */
				__( 'Payment %s updated', 'wp-bizwit' ),
				$receipt
			)
		);
	}

	/**
	 * Payment deleted.
	 *
	 * @param int $id         Payment id.
	 * @param int $invoice_id Related invoice id (may be 0).
	 *
	 * @return void
	 */
	public function on_payment_deleted( int $id, int $invoice_id = 0 ): void {
		$this->record(
			self::ENTITY_PAYMENT,
			$id,
			'deleted',
			sprintf(
				/* translators: %d: payment id */
				__( 'Payment #%d deleted', 'wp-bizwit' ),
				$id
			),
			array( 'invoice_id' => $invoice_id )
		);
	}

	/**
	 * Look up invoice_number without coupling to Invoice_Repository.
	 *
	 * @param int $id Invoice id.
	 *
	 * @return string Number or empty.
	 */
	private function find_invoice_number( int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}
		$table = Schema::table( Schema::INVOICES );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$num = $this->db()->get_var(
			$this->db()->prepare( "SELECT invoice_number FROM `{$table}` WHERE id = %d", $id )
		);
		// phpcs:enable

		return is_string( $num ) ? $num : '';
	}

	/**
	 * At most once per day, drop rows past the retention window.
	 *
	 * @return void
	 */
	private function maybe_prune(): void {
		if ( false !== get_transient( self::PRUNE_TRANSIENT ) ) {
			return;
		}
		$this->prune( self::RETENTION_DAYS );
		set_transient( self::PRUNE_TRANSIENT, 1, DAY_IN_SECONDS );
	}

	/**
	 * Cap summary to the column width without requiring ext-mbstring.
	 *
	 * @param string $summary Summary text.
	 *
	 * @return string
	 */
	private static function truncate_summary( string $summary ): string {
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $summary ) > 191 ? (string) mb_substr( $summary, 0, 191 ) : $summary;
		}

		return strlen( $summary ) > 191 ? substr( $summary, 0, 191 ) : $summary;
	}
}
