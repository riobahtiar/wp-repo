<?php
/**
 * Persistence for invoices and their line items.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use WP_Error;
use WP_BizWit\Database\Schema;
use WP_BizWit\Database\Sequence;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Reads and writes invoices. Totals are always recomputed server-side.
 *
 * Numbering: a permanent invoice number is allocated when the invoice first
 * leaves draft (or is created already as sent). Gaps from failed saves after
 * allocation are accepted for v1; void keeps the number for audit.
 */
class Invoice_Repository extends Repository {

	/**
	 * Table this repository owns.
	 *
	 * @return string Fully prefixed table name.
	 */
	protected function table(): string {
		return Schema::table( Schema::INVOICES );
	}

	/**
	 * Fully prefixed invoice_items table name.
	 *
	 * @return string Table name.
	 */
	private function items_table(): string {
		return Schema::table( Schema::INVOICE_ITEMS );
	}

	/**
	 * Query invoices with search, filtering, sorting and pagination.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'search'     => '',
				'client_id'  => 0,
				'project_id' => 0,
				'status'     => '',
				'overdue'    => false,
				'orderby'    => 'issue_date',
				'order'      => 'DESC',
				'per_page'   => 20,
				'page'       => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $this->db()->esc_like( $search ) . '%';
			$where[]  = '( i.invoice_number LIKE %s OR c.display_name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$client_id = (int) $args['client_id'];
		if ( $client_id > 0 ) {
			$where[]  = 'i.client_id = %d';
			$params[] = $client_id;
		}

		$project_id = (int) $args['project_id'];
		if ( $project_id > 0 ) {
			$where[]  = 'i.project_id = %d';
			$params[] = $project_id;
		}

		if ( Invoice_Status::is_valid( (string) $args['status'] ) ) {
			$where[]  = 'i.status = %s';
			$params[] = (string) $args['status'];
		}

		if ( ! empty( $args['overdue'] ) ) {
			// Overdue view: status overdue, or still open past due date.
			$where[]  = '( i.status = %s OR ( i.status IN (%s, %s) AND i.due_date IS NOT NULL AND i.due_date < %s AND i.paid_minor < i.total_minor ) )';
			$params[] = Invoice_Status::OVERDUE;
			$params[] = Invoice_Status::SENT;
			$params[] = Invoice_Status::PARTIAL;
			$params[] = (string) current_time( 'Y-m-d' );
		}

		$where_sql = implode( ' AND ', $where );
		$table     = $this->table();
		$clients   = Schema::table( Schema::CLIENTS );

		$orderby  = $this->safe_orderby( (string) $args['orderby'] );
		$order    = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( max( 1, (int) $args['page'] ) - 1 ) * $per_page );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}` i LEFT JOIN `{$clients}` c ON c.id = i.client_id WHERE {$where_sql}";
		$total     = (int) $this->db()->get_var(
			array() === $params
				? $count_sql
				: $this->db()->prepare( $count_sql, $params )
		);

		$sql           = "SELECT i.*, c.display_name AS client_name FROM `{$table}` i LEFT JOIN `{$clients}` c ON c.id = i.client_id WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $list_params ), ARRAY_A );
		// phpcs:enable

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Count invoices grouped by status.
	 *
	 * @return array<string, int> Status slug mapped to count.
	 */
	public function counts_by_status(): array {
		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results( "SELECT status, COUNT(*) AS total FROM `{$table}` GROUP BY status", ARRAY_A );
		// phpcs:enable

		$counts = array_fill_keys( array_keys( Invoice_Status::labels() ), 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Ordered line items for an invoice.
	 *
	 * @param int $invoice_id Invoice id.
	 *
	 * @return array<int, array<string, mixed>> Item rows.
	 */
	public function get_items( int $invoice_id ): array {
		if ( $invoice_id <= 0 ) {
			return array();
		}

		$table = $this->items_table();
		$sql   = "SELECT * FROM `{$table}` WHERE invoice_id = %d ORDER BY sort_order ASC, id ASC";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $invoice_id ), ARRAY_A );
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find an invoice by its public share token.
	 *
	 * @param string $token Token (6–12 alnum).
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_by_public_token( string $token ): ?array {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', $token ) ?? '';
		if ( strlen( $token ) < 6 ) {
			return null;
		}

		$table = $this->table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM `{$table}` WHERE public_token = %s LIMIT 1", $token ),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Ensure a public share token exists (creates one if empty).
	 *
	 * @param int $invoice_id Invoice id.
	 *
	 * @return string Token, or empty on failure.
	 */
	public function ensure_public_token( int $invoice_id ): string {
		$row = $this->find( $invoice_id );
		if ( null === $row ) {
			return '';
		}

		$existing = (string) ( $row['public_token'] ?? '' );
		if ( '' !== $existing ) {
			return $existing;
		}

		$token = $this->generate_unique_public_token();
		if ( '' === $token ) {
			return '';
		}

		$this->update_row(
			$invoice_id,
			array(
				'public_token' => $token,
				'updated_at'   => $this->now(),
			),
			array( '%s', '%s' )
		);

		return $token;
	}

	/**
	 * Rotate the public share token (invalidates old links).
	 *
	 * @param int $invoice_id Invoice id.
	 *
	 * @return string New token.
	 */
	public function rotate_public_token( int $invoice_id ): string {
		$token = $this->generate_unique_public_token();
		if ( '' === $token ) {
			return '';
		}

		$this->update_row(
			$invoice_id,
			array(
				'public_token' => $token,
				'updated_at'   => $this->now(),
			),
			array( '%s', '%s' )
		);

		return $token;
	}

	/**
	 * @return string 10-char alnum token unique in the table.
	 */
	private function generate_unique_public_token(): string {
		$table = $this->table();
		for ( $i = 0; $i < 12; $i++ ) {
			$raw   = wp_generate_password( 16, false, false );
			$token = strtolower( substr( preg_replace( '/[^a-zA-Z0-9]/', '', $raw ) ?? '', 0, 10 ) );
			if ( strlen( $token ) < 10 ) {
				continue;
			}
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $this->db()->get_var(
				$this->db()->prepare( "SELECT id FROM `{$table}` WHERE public_token = %s LIMIT 1", $token )
			);
			// phpcs:enable
			if ( null === $exists || '' === (string) $exists ) {
				return $token;
			}
		}
		return '';
	}

	/**
	 * Create an invoice from raw input.
	 *
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return int|WP_Error New invoice id, or error.
	 */
	public function create( array $input ) {
		$built = $this->build_record( $input, null );

		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$data  = $built['data'];
		$items = $built['items'];

		$data['created_by'] = get_current_user_id();
		$data['created_at'] = $this->now();
		$data['updated_at'] = $data['created_at'];
		$data['paid_minor'] = 0;

		// Number: drafts get a provisional unique placeholder; permanent number
		// is assigned when first leaving draft (or if created non-draft).
		if ( Invoice_Status::DRAFT === $data['status'] ) {
			$data['invoice_number'] = $this->provisional_number();
		} else {
			$number = $this->allocate_number( (string) $data['issue_date'] );
			if ( '' === $number ) {
				return new WP_Error( 'wp_bizwit_number_failed', __( 'Could not allocate an invoice number. Please try again.', 'wp-bizwit' ) );
			}
			$data['invoice_number'] = $number;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		$id = $this->insert_row( $data, $this->formats( $data ) );

		if ( 0 === $id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_insert_failed', __( 'The invoice could not be saved.', 'wp-bizwit' ) );
		}

		$items_result = $this->replace_items( $id, $items );

		if ( is_wp_error( $items_result ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return $items_result;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		/**
		 * Fires after an invoice has been created.
		 *
		 * @param int                  $id   New invoice id.
		 * @param array<string, mixed> $data Stored column values.
		 */
		do_action( 'wp_bizwit_invoice_created', $id, $data );

		return $id;
	}

	/**
	 * Update an invoice from raw input.
	 *
	 * Non-draft invoices cannot change lines, client, project, or money fields.
	 * Status transitions and notes/terms remain allowed where the status machine permits.
	 *
	 * @param int                  $id    Invoice id.
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function update( int $id, array $input ) {
		$existing = $this->find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That invoice no longer exists.', 'wp-bizwit' ) );
		}

		$from_status = (string) $existing['status'];

		if ( Invoice_Status::VOID === $from_status ) {
			return new WP_Error( 'wp_bizwit_invoice_void', __( 'A void invoice cannot be changed.', 'wp-bizwit' ) );
		}

		if ( ! Invoice_Status::is_editable( $from_status ) ) {
			return $this->update_locked( $id, $existing, $input );
		}

		$built = $this->build_record( $input, $existing );

		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$data  = $built['data'];
		$items = $built['items'];

		// Preserve paid_minor and number; allocate permanent number when leaving draft.
		$data['paid_minor']     = (int) $existing['paid_minor'];
		$data['invoice_number'] = (string) $existing['invoice_number'];
		$data['created_by']     = (int) $existing['created_by'];
		$data['created_at']     = (string) $existing['created_at'];
		$data['updated_at']     = $this->now();

		if (
			Invoice_Status::DRAFT === $from_status
			&& Invoice_Status::DRAFT !== $data['status']
			&& $this->is_provisional_number( (string) $existing['invoice_number'] )
		) {
			$number = $this->allocate_number( (string) $data['issue_date'] );
			if ( '' === $number ) {
				return new WP_Error( 'wp_bizwit_number_failed', __( 'Could not allocate an invoice number. Please try again.', 'wp-bizwit' ) );
			}
			$data['invoice_number'] = $number;
		}

		if ( ! Invoice_Status::can_transition( $from_status, (string) $data['status'] ) ) {
			return new WP_Error(
				'wp_bizwit_bad_transition',
				__( 'That status change is not allowed for this invoice.', 'wp-bizwit' )
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		if ( ! $this->update_row( $id, $data, $this->formats( $data ) ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_update_failed', __( 'The invoice could not be updated.', 'wp-bizwit' ) );
		}

		$items_result = $this->replace_items( $id, $items );

		if ( is_wp_error( $items_result ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return $items_result;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		/**
		 * Fires after an invoice has been updated.
		 *
		 * @param int                  $id   Invoice id.
		 * @param array<string, mixed> $data Stored column values.
		 */
		do_action( 'wp_bizwit_invoice_updated', $id, $data );

		return true;
	}

	/**
	 * Transition status only (send, mark paid, void, …).
	 *
	 * @param int    $id     Invoice id.
	 * @param string $status Target status.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function transition( int $id, string $status ) {
		$existing = $this->find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That invoice no longer exists.', 'wp-bizwit' ) );
		}

		$from = (string) $existing['status'];

		if ( ! Invoice_Status::can_transition( $from, $status ) ) {
			return new WP_Error(
				'wp_bizwit_bad_transition',
				__( 'That status change is not allowed for this invoice.', 'wp-bizwit' )
			);
		}

		if ( $from === $status ) {
			return true;
		}

		$update = array(
			'status'     => $status,
			'updated_at' => $this->now(),
		);

		// Leaving draft: ensure a real document number.
		if (
			Invoice_Status::DRAFT === $from
			&& $this->is_provisional_number( (string) $existing['invoice_number'] )
		) {
			$number = $this->allocate_number( (string) ( $existing['issue_date'] ?: current_time( 'Y-m-d' ) ) );
			if ( '' === $number ) {
				return new WP_Error( 'wp_bizwit_number_failed', __( 'Could not allocate an invoice number. Please try again.', 'wp-bizwit' ) );
			}
			$update['invoice_number'] = $number;
			if ( empty( $existing['issue_date'] ) ) {
				$update['issue_date'] = (string) current_time( 'Y-m-d' );
			}
		}

		if ( ! $this->update_row( $id, $update, $this->formats( $update ) ) ) {
			return new WP_Error( 'wp_bizwit_update_failed', __( 'The invoice could not be updated.', 'wp-bizwit' ) );
		}

		/**
		 * Fires after an invoice status transition.
		 *
		 * @param int    $id     Invoice id.
		 * @param string $from   Previous status.
		 * @param string $status New status.
		 */
		do_action( 'wp_bizwit_invoice_transitioned', $id, $from, $status );

		return true;
	}

	/**
	 * Void an invoice, preserving its number and record.
	 *
	 * @param int $id Invoice id.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function void( int $id ) {
		return $this->transition( $id, Invoice_Status::VOID );
	}

	/**
	 * Recompute paid_minor and payment-driven status from settlement totals.
	 *
	 * Called by Payment_Repository after create/update/delete. Does not use the
	 * user-facing transition map — settlement is a system recompute (e.g. paid
	 * can return to partial when a payment is deleted).
	 *
	 * @param int $invoice_id  Invoice id.
	 * @param int $paid_minor  Sum of bank received + withheld for this invoice.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function apply_settlement( int $invoice_id, int $paid_minor ) {
		$existing = $this->find( $invoice_id );

		if ( null === $existing ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That invoice no longer exists.', 'wp-bizwit' ) );
		}

		$paid_minor = max( 0, $paid_minor );
		$current    = (string) $existing['status'];
		$total      = (int) $existing['total_minor'];
		$status     = Invoice_Status::from_payment_amounts( $total, $paid_minor, $current );

		// Still open and past due → overdue (unless void/draft).
		if (
			! in_array( $status, array( Invoice_Status::VOID, Invoice_Status::DRAFT, Invoice_Status::PAID ), true )
			&& $paid_minor < $total
			&& ! empty( $existing['due_date'] )
			&& (string) $existing['due_date'] < (string) current_time( 'Y-m-d' )
		) {
			$status = Invoice_Status::OVERDUE;
		}

		$update = array(
			'paid_minor' => $paid_minor,
			'status'     => $status,
			'updated_at' => $this->now(),
		);

		if ( ! $this->update_row( $invoice_id, $update, $this->formats( $update ) ) ) {
			return new WP_Error( 'wp_bizwit_update_failed', __( 'The invoice could not be updated.', 'wp-bizwit' ) );
		}

		if ( $current !== $status ) {
			do_action( 'wp_bizwit_invoice_transitioned', $invoice_id, $current, $status );
		}

		/**
		 * Fires after settlement amounts were applied to an invoice.
		 *
		 * @param int    $invoice_id Invoice id.
		 * @param int    $paid_minor Settled amount in minor units.
		 * @param string $status     Resulting status.
		 */
		do_action( 'wp_bizwit_invoice_settlement_applied', $invoice_id, $paid_minor, $status );

		return true;
	}

	/**
	 * Invoices that may receive a payment (open, not draft/void).
	 *
	 * @param int $client_id Optional client filter (0 = all).
	 *
	 * @return array<int, string> Invoice id mapped to label (number · client · balance).
	 */
	public function open_options( int $client_id = 0 ): array {
		$map = array();
		foreach ( $this->open_select_options( $client_id ) as $opt ) {
			$map[ (int) $opt['value'] ] = (string) $opt['search'];
		}

		return $map;
	}

	/**
	 * Structured options for Admin\Searchable_Select (payment form faktur picker).
	 *
	 * Primary: invoice number — client name. Meta chip: remaining balance.
	 *
	 * @param int $client_id Optional client filter (0 = all).
	 *
	 * @return array<int, array{value: string, label: string, meta: string, search: string}>
	 */
	public function open_select_options( int $client_id = 0 ): array {
		$table   = $this->table();
		$clients = Schema::table( Schema::CLIENTS );
		$where   = 'i.status NOT IN (%s, %s)';
		$params  = array( Invoice_Status::DRAFT, Invoice_Status::VOID );

		if ( $client_id > 0 ) {
			$where   .= ' AND i.client_id = %d';
			$params[] = $client_id;
		}

		$sql = "SELECT i.id, i.invoice_number, i.total_minor, i.paid_minor, i.currency, c.display_name AS client_name
			FROM `{$table}` i
			LEFT JOIN `{$clients}` c ON c.id = i.client_id
			WHERE {$where}
			ORDER BY i.issue_date DESC, i.id DESC
			LIMIT 500";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable

		$options = array();
		foreach ( (array) $rows as $row ) {
			$number  = (string) $row['invoice_number'];
			$client  = (string) ( $row['client_name'] ?? '' );
			$balance = Invoice_Totals::balance_minor( (int) $row['total_minor'], (int) $row['paid_minor'] );
			$money   = Money::format( $balance, (string) $row['currency'] );
			$label   = '' !== $client
				? sprintf(
					/* translators: 1: invoice number, 2: client name */
					__( '%1$s — %2$s', 'wp-bizwit' ),
					$number,
					$client
				)
				: $number;

			$options[] = array(
				'value'  => (string) (int) $row['id'],
				'label'  => $label,
				'meta'   => $money,
				'search' => trim( $number . ' ' . $client . ' ' . $money ),
			);
		}

		return $options;
	}

	/**
	 * Delete only draft invoices that never received a permanent number.
	 *
	 * @param int $id Invoice id.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function delete( int $id ) {
		$existing = $this->find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That invoice no longer exists.', 'wp-bizwit' ) );
		}

		if ( Invoice_Status::DRAFT !== (string) $existing['status'] ) {
			return new WP_Error(
				'wp_bizwit_invoice_not_deletable',
				__( 'Only draft invoices can be deleted. Void a sent invoice to keep the number for your records.', 'wp-bizwit' )
			);
		}

		$payments = $this->count_in( Schema::table( Schema::PAYMENTS ), 'invoice_id = %d', array( $id ) );
		if ( $payments > 0 ) {
			return new WP_Error(
				'wp_bizwit_invoice_has_payments',
				__( 'This invoice has payment records and cannot be deleted.', 'wp-bizwit' )
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->delete( $this->items_table(), array( 'invoice_id' => $id ), array( '%d' ) );
		// phpcs:enable

		if ( ! $this->delete_row( $id ) ) {
			return new WP_Error( 'wp_bizwit_delete_failed', __( 'The invoice could not be deleted.', 'wp-bizwit' ) );
		}

		/**
		 * Fires after an invoice has been deleted.
		 *
		 * @param int $id Deleted invoice id.
		 */
		do_action( 'wp_bizwit_invoice_deleted', $id );

		return true;
	}

	/**
	 * Mark sent/partial invoices past their due date as overdue.
	 *
	 * @return int Number of rows updated.
	 */
	public function mark_overdue(): int {
		$table = $this->table();
		$today = (string) current_time( 'Y-m-d' );
		$now   = $this->now();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->db()->query(
			$this->db()->prepare(
				"UPDATE `{$table}`
				SET status = %s, updated_at = %s
				WHERE status IN (%s, %s)
				  AND due_date IS NOT NULL
				  AND due_date < %s
				  AND paid_minor < total_minor",
				Invoice_Status::OVERDUE,
				$now,
				Invoice_Status::SENT,
				Invoice_Status::PARTIAL,
				$today
			)
		);
		// phpcs:enable

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Prefill line items from a project (budget or one termin stage).
	 *
	 * @param int $project_id Project id.
	 * @param int $term_id    Optional termin row id (0 = whole budget / first useful line).
	 *
	 * @return array{client_id: int, project_id: int, currency: string, items: array<int, array<string, mixed>>}|WP_Error
	 */
	public function prefill_from_project( int $project_id, int $term_id = 0 ) {
		$projects = new Project_Repository();
		$project  = $projects->find( $project_id );

		if ( null === $project ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That project no longer exists.', 'wp-bizwit' ) );
		}

		$currency = (string) $project['currency'];
		$items    = array();
		$tax_rate = Settings::effective_tax_rate();

		if ( $term_id > 0 ) {
			foreach ( $projects->get_terms( $project_id ) as $term ) {
				if ( (int) $term['id'] !== $term_id ) {
					continue;
				}
				$items[] = array(
					'description'      => (string) $term['name'],
					'quantity'         => '1.0000',
					'unit'             => '',
					'unit_price_minor' => (int) $term['amount_minor'],
					'tax_rate'         => $tax_rate,
				);
				break;
			}
		}

		if ( array() === $items ) {
			$budget = (int) $project['budget_minor'];
			$name   = (string) $project['name'];
			if ( $budget > 0 ) {
				$items[] = array(
					'description'      => $name,
					'quantity'         => '1.0000',
					'unit'             => '',
					'unit_price_minor' => $budget,
					'tax_rate'         => $tax_rate,
				);
			} else {
				$items[] = array(
					'description'      => $name,
					'quantity'         => '1.0000',
					'unit'             => '',
					'unit_price_minor' => 0,
					'tax_rate'         => $tax_rate,
				);
			}
		}

		return array(
			'client_id'  => (int) $project['client_id'],
			'project_id' => $project_id,
			'currency'   => $currency,
			'items'      => $items,
		);
	}

	/**
	 * Update notes/terms/status only when the invoice is no longer a draft.
	 *
	 * @param int                  $id       Invoice id.
	 * @param array<string, mixed> $existing Current row.
	 * @param array<string, mixed> $input    Raw input.
	 *
	 * @return true|WP_Error True on success.
	 */
	private function update_locked( int $id, array $existing, array $input ) {
		$from   = (string) $existing['status'];
		$status = sanitize_key( (string) ( $input['status'] ?? $from ) );

		if ( ! Invoice_Status::is_valid( $status ) ) {
			$status = $from;
		}

		if ( ! Invoice_Status::can_transition( $from, $status ) ) {
			return new WP_Error(
				'wp_bizwit_bad_transition',
				__( 'That status change is not allowed for this invoice.', 'wp-bizwit' )
			);
		}

		$update = array(
			'status'     => $status,
			'notes'      => sanitize_textarea_field( (string) ( $input['notes'] ?? $existing['notes'] ) ),
			'terms'      => sanitize_textarea_field( (string) ( $input['terms'] ?? $existing['terms'] ) ),
			'updated_at' => $this->now(),
		);

		// Allow due date adjustment after send (payment terms negotiation).
		$due = $this->sanitize_date( $input['due_date'] ?? $existing['due_date'] );
		if ( null !== $due ) {
			$update['due_date'] = $due;
		}

		if ( ! $this->update_row( $id, $update, $this->formats( $update ) ) ) {
			return new WP_Error( 'wp_bizwit_update_failed', __( 'The invoice could not be updated.', 'wp-bizwit' ) );
		}

		do_action( 'wp_bizwit_invoice_updated', $id, array_merge( $existing, $update ) );

		return true;
	}

	/**
	 * Sanitize input, recompute totals, return data + items ready to persist.
	 *
	 * @param array<string, mixed>      $input    Raw input.
	 * @param array<string, mixed>|null $existing Existing row when updating.
	 *
	 * @return array{data: array<string, mixed>, items: array<int, array<string, mixed>>}|WP_Error
	 */
	private function build_record( array $input, ?array $existing ) {
		$currency = strtoupper( trim( (string) ( $input['currency'] ?? '' ) ) );
		if ( 3 !== strlen( $currency ) ) {
			$currency = Settings::currency();
		}

		$client_id  = absint( $input['client_id'] ?? 0 );
		$project_id = absint( $input['project_id'] ?? 0 );
		$status     = sanitize_key( (string) ( $input['status'] ?? Invoice_Status::DRAFT ) );

		if ( ! Invoice_Status::is_valid( $status ) ) {
			$status = Invoice_Status::DRAFT;
		}

		$issue_date = $this->sanitize_date( $input['issue_date'] ?? current_time( 'Y-m-d' ) );
		$due_date   = $this->sanitize_date( $input['due_date'] ?? '' );

		if ( null === $issue_date ) {
			$issue_date = (string) current_time( 'Y-m-d' );
		}

		if ( null === $due_date ) {
			$days     = max( 0, (int) Settings::get( 'payment_terms_days', 30 ) );
			$due_ts   = strtotime( $issue_date . ' +' . $days . ' days' );
			$due_date = false !== $due_ts
				? (string) wp_date( 'Y-m-d', $due_ts )
				: $issue_date;
		}

		$charges_tax = Settings::charges_sales_tax();
		$raw_items   = is_array( $input['items'] ?? null ) ? $input['items'] : array();
		$items       = $this->sanitize_items( $raw_items, $currency, $charges_tax );

		if ( array() === $items ) {
			return new WP_Error(
				'wp_bizwit_no_items',
				__( 'Add at least one line item with a description.', 'wp-bizwit' )
			);
		}

		$apply_withholding = ! empty( $input['apply_withholding'] );
		$withholding_rate  = $apply_withholding
			? (string) ( $input['withholding_rate'] ?? Settings::get( 'withholding_rate', '2' ) )
			: '0';

		// Discount: accept minor units or formatted money string.
		$discount_minor = 0;
		if ( isset( $input['discount_minor'] ) && is_numeric( $input['discount_minor'] ) ) {
			$discount_minor = max( 0, (int) $input['discount_minor'] );
		} elseif ( isset( $input['discount'] ) && '' !== (string) $input['discount'] ) {
			$discount_minor = max( 0, Money::to_minor( $input['discount'], $currency ) );
		}

		// Never trust posted totals.
		$totals = Invoice_Totals::calculate(
			$items,
			array(
				'discount_minor'    => $discount_minor,
				'charges_sales_tax' => $charges_tax,
				'apply_withholding' => $apply_withholding,
				'withholding_rate'  => $withholding_rate,
			)
		);

		// Merge computed line totals into items for storage.
		foreach ( $items as $i => $item ) {
			$items[ $i ]['line_total_minor'] = $totals['lines'][ $i ]['line_total_minor'];
			$items[ $i ]['tax_rate']         = $totals['lines'][ $i ]['tax_rate'];
		}

		if ( $client_id <= 0 || ! $this->client_exists( $client_id ) ) {
			return new WP_Error( 'wp_bizwit_invalid_client', __( 'Please choose a client for this invoice.', 'wp-bizwit' ) );
		}

		if ( $project_id > 0 && ! $this->project_belongs_to_client( $project_id, $client_id ) ) {
			return new WP_Error(
				'wp_bizwit_invalid_project',
				__( 'That project does not belong to the selected client.', 'wp-bizwit' )
			);
		}

		$data = array(
			'client_id'         => $client_id,
			'project_id'        => $project_id,
			'status'            => $status,
			'issue_date'        => $issue_date,
			'due_date'          => $due_date,
			'currency'          => $currency,
			'subtotal_minor'    => $totals['subtotal_minor'],
			'discount_minor'    => $totals['discount_minor'],
			'tax_minor'         => $totals['tax_minor'],
			'total_minor'       => $totals['total_minor'],
			'withholding_rate'  => $totals['withholding_rate'],
			'withholding_minor' => $totals['withholding_minor'],
			'notes'             => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
			'terms'             => sanitize_textarea_field( (string) ( $input['terms'] ?? '' ) ),
		);

		return array(
			'data'  => $data,
			'items' => $items,
		);
	}

	/**
	 * Sanitize posted line items; drop empty rows.
	 *
	 * @param array<int, mixed> $raw         Raw items.
	 * @param string            $currency    Currency code.
	 * @param bool              $charges_tax Whether tax rates are allowed.
	 *
	 * @return array<int, array<string, mixed>> Sanitised items (pre-totals).
	 */
	private function sanitize_items( array $raw, string $currency, bool $charges_tax ): array {
		$default_rate = $charges_tax ? Settings::effective_tax_rate() : '0';
		$items        = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$description = sanitize_text_field( (string) ( $row['description'] ?? '' ) );
			if ( '' === $description ) {
				continue;
			}

			$qty = (string) ( $row['quantity'] ?? '1' );
			if ( '' === trim( $qty ) ) {
				$qty = '1';
			}

			if ( isset( $row['unit_price_minor'] ) && is_numeric( $row['unit_price_minor'] ) ) {
				$unit_price = (int) $row['unit_price_minor'];
			} else {
				$unit_price = Money::to_minor( $row['unit_price'] ?? '0', $currency );
			}

			$tax_rate = $charges_tax
				? Invoice_Totals::normalize_rate( $row['tax_rate'] ?? $default_rate )
				: '0.0000';

			$items[] = array(
				'description'      => $description,
				'quantity'         => Money::quantity_from_scaled( Money::quantity_to_scaled( $qty ) ),
				'unit'             => sanitize_text_field( (string) ( $row['unit'] ?? '' ) ),
				'unit_price_minor' => $unit_price,
				'tax_rate'         => $tax_rate,
				'line_total_minor' => 0,
			);
		}

		return $items;
	}

	/**
	 * Replace every line item for an invoice.
	 *
	 * @param int                              $invoice_id Invoice id.
	 * @param array<int, array<string, mixed>> $items      Sanitised items.
	 *
	 * @return true|WP_Error True on success.
	 */
	private function replace_items( int $invoice_id, array $items ) {
		$table = $this->items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->delete( $table, array( 'invoice_id' => $invoice_id ), array( '%d' ) );

		$sort = 0;
		foreach ( $items as $item ) {
			$result = $this->db()->insert(
				$table,
				array(
					'invoice_id'       => $invoice_id,
					'sort_order'       => $sort,
					'description'      => (string) $item['description'],
					'quantity'         => (string) $item['quantity'],
					'unit'             => (string) $item['unit'],
					'unit_price_minor' => (int) $item['unit_price_minor'],
					'tax_rate'         => (string) $item['tax_rate'],
					'line_total_minor' => (int) $item['line_total_minor'],
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
			);

			if ( false === $result ) {
				return new WP_Error( 'wp_bizwit_items_failed', __( 'The invoice lines could not be saved.', 'wp-bizwit' ) );
			}

			++$sort;
		}
		// phpcs:enable

		return true;
	}

	/**
	 * Allocate a permanent invoice number for a calendar date.
	 *
	 * @param string $date Y-m-d issue date.
	 *
	 * @return string Formatted number, or empty on failure.
	 */
	private function allocate_number( string $date ): string {
		$year = substr( $date, 0, 4 );
		if ( ! preg_match( '/^\d{4}$/', $year ) ) {
			$year = (string) wp_date( 'Y' );
		}

		$seq = Sequence::next( 'invoice:' . $year );

		if ( $seq <= 0 ) {
			return '';
		}

		return Settings::document_number( 'invoice', $seq, $date );
	}

	/**
	 * Unique placeholder for draft invoices (not a real document number).
	 *
	 * @return string Provisional number.
	 */
	private function provisional_number(): string {
		return 'DRAFT-' . strtoupper( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Whether a number is a draft placeholder.
	 *
	 * @param string $number Invoice number.
	 *
	 * @return bool True when provisional.
	 */
	private function is_provisional_number( string $number ): bool {
		return str_starts_with( $number, 'DRAFT-' );
	}

	/**
	 * Sanitize a Y-m-d date string.
	 *
	 * @param mixed $value Raw date.
	 *
	 * @return string|null Valid date or null.
	 */
	private function sanitize_date( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return null;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Whether a client id exists.
	 *
	 * @param int $client_id Client id.
	 *
	 * @return bool True when exists.
	 */
	private function client_exists( int $client_id ): bool {
		return $this->count_in( Schema::table( Schema::CLIENTS ), 'id = %d', array( $client_id ) ) > 0;
	}

	/**
	 * Whether a project belongs to a client.
	 *
	 * @param int $project_id Project id.
	 * @param int $client_id  Client id.
	 *
	 * @return bool True when the project exists for that client.
	 */
	private function project_belongs_to_client( int $project_id, int $client_id ): bool {
		return $this->count_in(
			Schema::table( Schema::PROJECTS ),
			'id = %d AND client_id = %d',
			array( $project_id, $client_id )
		) > 0;
	}

	/**
	 * Whitelist order-by expressions (aliased with i./c. for joins).
	 *
	 * @param string $orderby Requested key.
	 *
	 * @return string Safe SQL fragment.
	 */
	private function safe_orderby( string $orderby ): string {
		$map = array(
			'invoice_number' => 'i.invoice_number',
			'client_name'    => 'c.display_name',
			'status'         => 'i.status',
			'issue_date'     => 'i.issue_date',
			'due_date'       => 'i.due_date',
			'total_minor'    => 'i.total_minor',
			'updated_at'     => 'i.updated_at',
			'created_at'     => 'i.created_at',
		);

		return $map[ $orderby ] ?? 'i.issue_date';
	}

	/**
	 * Printf formats for a partial or full column set.
	 *
	 * @param array<string, mixed> $data Column values.
	 *
	 * @return string[] Formats in the same order as $data keys.
	 */
	private function formats( array $data ): array {
		$all = array(
			'invoice_number'    => '%s',
			'client_id'         => '%d',
			'project_id'        => '%d',
			'status'            => '%s',
			'issue_date'        => '%s',
			'due_date'          => '%s',
			'currency'          => '%s',
			'subtotal_minor'    => '%d',
			'discount_minor'    => '%d',
			'tax_minor'         => '%d',
			'total_minor'       => '%d',
			'paid_minor'        => '%d',
			'withholding_rate'  => '%s',
			'withholding_minor' => '%d',
			'notes'             => '%s',
			'terms'             => '%s',
			'created_by'        => '%d',
			'created_at'        => '%s',
			'updated_at'        => '%s',
		);

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			$formats[] = $all[ $key ] ?? '%s';
		}

		return $formats;
	}
}
