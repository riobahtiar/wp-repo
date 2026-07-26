<?php
/**
 * Persistence for payment records (kwitansi sources).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use WP_Error;
use WP_BizWit\Database\Schema;
use WP_BizWit\Database\Sequence;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Records payments that already happened elsewhere — never moves money.
 *
 * Settlement toward an invoice = amount_minor (bank) + withheld_minor (PPh 23).
 * invoices.paid_minor is always recomputed from the payments table on write.
 */
class Payment_Repository extends Repository {

	/**
	 * Table this repository owns.
	 *
	 * @return string Fully prefixed table name.
	 */
	protected function table(): string {
		return Schema::table( Schema::PAYMENTS );
	}

	/**
	 * Query payments with filters and pagination.
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
				'invoice_id' => 0,
				'method'     => '',
				'orderby'    => 'paid_on',
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
			$where[]  = '( p.receipt_number LIKE %s OR p.reference LIKE %s OR i.invoice_number LIKE %s OR c.display_name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$client_id = (int) $args['client_id'];
		if ( $client_id > 0 ) {
			$where[]  = 'p.client_id = %d';
			$params[] = $client_id;
		}

		$invoice_id = (int) $args['invoice_id'];
		if ( $invoice_id > 0 ) {
			$where[]  = 'p.invoice_id = %d';
			$params[] = $invoice_id;
		}

		$method = sanitize_key( (string) $args['method'] );
		if ( '' !== $method && array_key_exists( $method, Regions::current()->payment_methods() ) ) {
			$where[]  = 'p.method = %s';
			$params[] = $method;
		}

		$where_sql = implode( ' AND ', $where );
		$table     = $this->table();
		$invoices  = Schema::table( Schema::INVOICES );
		$clients   = Schema::table( Schema::CLIENTS );

		$orderby  = $this->safe_orderby( (string) $args['orderby'] );
		$order    = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( max( 1, (int) $args['page'] ) - 1 ) * $per_page );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}` p
			LEFT JOIN `{$invoices}` i ON i.id = p.invoice_id
			LEFT JOIN `{$clients}` c ON c.id = p.client_id
			WHERE {$where_sql}";
		$total     = (int) $this->db()->get_var(
			array() === $params
				? $count_sql
				: $this->db()->prepare( $count_sql, $params )
		);

		$sql           = "SELECT p.*, i.invoice_number, c.display_name AS client_name
			FROM `{$table}` p
			LEFT JOIN `{$invoices}` i ON i.id = p.invoice_id
			LEFT JOIN `{$clients}` c ON c.id = p.client_id
			WHERE {$where_sql}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d";
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
	 * Payments for one invoice, newest first.
	 *
	 * @param int $invoice_id Invoice id.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_invoice( int $invoice_id ): array {
		if ( $invoice_id <= 0 ) {
			return array();
		}

		$result = $this->query(
			array(
				'invoice_id' => $invoice_id,
				'per_page'   => 200,
				'orderby'    => 'paid_on',
				'order'      => 'DESC',
			)
		);

		return $result['items'];
	}

	/**
	 * Create a payment from raw input and sync the invoice settlement.
	 *
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return int|WP_Error New payment id, or error.
	 */
	public function create( array $input ) {
		$built = $this->build_record( $input, null );

		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$data = $built;

		$number = $this->allocate_receipt_number( (string) $data['paid_on'] );
		if ( '' === $number ) {
			return new WP_Error( 'wp_bizwit_number_failed', __( 'Could not allocate a receipt number. Please try again.', 'wp-bizwit' ) );
		}

		$data['receipt_number'] = $number;
		$data['created_by']     = get_current_user_id();
		$data['created_at']     = $this->now();
		$data['updated_at']     = $data['created_at'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		$id = $this->insert_row( $data, $this->formats( $data ) );

		if ( 0 === $id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_insert_failed', __( 'The payment could not be saved.', 'wp-bizwit' ) );
		}

		$sync = $this->sync_invoice_settlement( (int) $data['invoice_id'] );

		if ( is_wp_error( $sync ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return $sync;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		/**
		 * Fires after a payment has been created.
		 *
		 * @param int                  $id   Payment id.
		 * @param array<string, mixed> $data Stored columns.
		 */
		do_action( 'wp_bizwit_payment_created', $id, $data );

		return $id;
	}

	/**
	 * Update a payment and re-sync the invoice (and previous invoice if moved).
	 *
	 * @param int                  $id    Payment id.
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function update( int $id, array $input ) {
		$existing = $this->find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That payment no longer exists.', 'wp-bizwit' ) );
		}

		$built = $this->build_record( $input, $existing );

		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$data                   = $built;
		$data['receipt_number'] = (string) $existing['receipt_number'];
		$data['created_by']     = (int) $existing['created_by'];
		$data['created_at']     = (string) $existing['created_at'];
		$data['updated_at']     = $this->now();

		$old_invoice = (int) $existing['invoice_id'];
		$new_invoice = (int) $data['invoice_id'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		if ( ! $this->update_row( $id, $data, $this->formats( $data ) ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_update_failed', __( 'The payment could not be updated.', 'wp-bizwit' ) );
		}

		$sync = $this->sync_invoice_settlement( $new_invoice );
		if ( is_wp_error( $sync ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return $sync;
		}

		if ( $old_invoice !== $new_invoice && $old_invoice > 0 ) {
			$sync_old = $this->sync_invoice_settlement( $old_invoice );
			if ( is_wp_error( $sync_old ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->db()->query( 'ROLLBACK' );
				// phpcs:enable

				return $sync_old;
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		do_action( 'wp_bizwit_payment_updated', $id, $data );

		return true;
	}

	/**
	 * Delete a payment and restore invoice settlement.
	 *
	 * @param int $id Payment id.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function delete( int $id ) {
		$existing = $this->find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That payment no longer exists.', 'wp-bizwit' ) );
		}

		$invoice_id = (int) $existing['invoice_id'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		if ( ! $this->delete_row( $id ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_delete_failed', __( 'The payment could not be deleted.', 'wp-bizwit' ) );
		}

		if ( $invoice_id > 0 ) {
			$sync = $this->sync_invoice_settlement( $invoice_id );
			if ( is_wp_error( $sync ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->db()->query( 'ROLLBACK' );
				// phpcs:enable

				return $sync;
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		/**
		 * Fires after a payment has been deleted.
		 *
		 * @param int $id         Deleted payment id.
		 * @param int $invoice_id Related invoice id.
		 */
		do_action( 'wp_bizwit_payment_deleted', $id, $invoice_id );

		return true;
	}

	/**
	 * Settlement contribution of one payment row (bank + withheld).
	 *
	 * @param array<string, mixed> $payment Payment row.
	 *
	 * @return int Minor units.
	 */
	public static function settlement_minor( array $payment ): int {
		return max( 0, (int) ( $payment['amount_minor'] ?? 0 ) )
			+ max( 0, (int) ( $payment['withheld_minor'] ?? 0 ) );
	}

	/**
	 * Recompute invoice.paid_minor from all payments and update status.
	 *
	 * @param int $invoice_id Invoice id.
	 *
	 * @return true|WP_Error True on success.
	 */
	public function sync_invoice_settlement( int $invoice_id ) {
		if ( $invoice_id <= 0 ) {
			return true;
		}

		$paid = $this->sum_settlement_for_invoice( $invoice_id );

		return ( new Invoice_Repository() )->apply_settlement( $invoice_id, $paid );
	}

	/**
	 * Sum amount_minor + withheld_minor for an invoice.
	 *
	 * @param int $invoice_id Invoice id.
	 *
	 * @return int Settlement total in minor units.
	 */
	public function sum_settlement_for_invoice( int $invoice_id ): int {
		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sum = $this->db()->get_var(
			$this->db()->prepare(
				"SELECT COALESCE(SUM(amount_minor + withheld_minor), 0) FROM `{$table}` WHERE invoice_id = %d",
				$invoice_id
			)
		);
		// phpcs:enable

		return max( 0, (int) $sum );
	}

	/**
	 * Sanitize and validate input into column values.
	 *
	 * @param array<string, mixed>      $input    Raw input.
	 * @param array<string, mixed>|null $existing Existing row when updating.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function build_record( array $input, ?array $existing ) {
		$invoice_id = absint( $input['invoice_id'] ?? 0 );
		$invoices   = new Invoice_Repository();
		$invoice    = $invoices->find( $invoice_id );

		if ( null === $invoice ) {
			return new WP_Error( 'wp_bizwit_invalid_invoice', __( 'Please choose an invoice for this payment.', 'wp-bizwit' ) );
		}

		$status = (string) $invoice['status'];
		if ( Invoice_Status::VOID === $status ) {
			return new WP_Error( 'wp_bizwit_invoice_void', __( 'Payments cannot be recorded against a void invoice.', 'wp-bizwit' ) );
		}
		if ( Invoice_Status::DRAFT === $status ) {
			return new WP_Error( 'wp_bizwit_invoice_draft', __( 'Send the invoice before recording a payment.', 'wp-bizwit' ) );
		}

		$currency  = (string) $invoice['currency'];
		$client_id = (int) $invoice['client_id'];

		$paid_on = $this->sanitize_date( $input['paid_on'] ?? current_time( 'Y-m-d' ) );
		if ( null === $paid_on ) {
			$paid_on = (string) current_time( 'Y-m-d' );
		}

		if ( isset( $input['amount_minor'] ) && is_numeric( $input['amount_minor'] ) ) {
			$amount = (int) $input['amount_minor'];
		} else {
			$amount = Money::to_minor( $input['amount'] ?? '0', $currency );
		}

		if ( isset( $input['withheld_minor'] ) && is_numeric( $input['withheld_minor'] ) ) {
			$withheld = max( 0, (int) $input['withheld_minor'] );
		} elseif ( isset( $input['withheld'] ) && '' !== (string) $input['withheld'] ) {
			$withheld = max( 0, Money::to_minor( $input['withheld'], $currency ) );
		} else {
			$withheld = 0;
		}

		// Negative amount allowed for simple refund/correction; settlement uses abs via max(0) on parts.
		// Allow negative bank amount only when intentionally correcting — still store as given.
		if ( 0 === $amount && 0 === $withheld ) {
			return new WP_Error(
				'wp_bizwit_zero_payment',
				__( 'Enter an amount received and/or a withheld amount.', 'wp-bizwit' )
			);
		}

		$methods = Regions::current()->payment_methods();
		$method  = sanitize_key( (string) ( $input['method'] ?? 'bank_transfer' ) );
		if ( ! array_key_exists( $method, $methods ) ) {
			$method = 'bank_transfer';
		}

		return array(
			'invoice_id'      => $invoice_id,
			'client_id'       => $client_id,
			'paid_on'         => $paid_on,
			'amount_minor'    => $amount,
			'withheld_minor'  => $withheld,
			'currency'        => $currency,
			'method'          => $method,
			'reference'       => sanitize_text_field( (string) ( $input['reference'] ?? '' ) ),
			'withholding_ref' => sanitize_text_field( (string) ( $input['withholding_ref'] ?? '' ) ),
			'notes'           => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
		);
	}

	/**
	 * Allocate a permanent receipt (kwitansi) number.
	 *
	 * @param string $date Y-m-d payment date.
	 *
	 * @return string Formatted number, or empty on failure.
	 */
	private function allocate_receipt_number( string $date ): string {
		$year = substr( $date, 0, 4 );
		if ( ! preg_match( '/^\d{4}$/', $year ) ) {
			$year = (string) wp_date( 'Y' );
		}

		$seq = Sequence::next( 'receipt:' . $year );

		if ( $seq <= 0 ) {
			return '';
		}

		return Settings::document_number( 'receipt', $seq, $date );
	}

	/**
	 * Sanitize a Y-m-d date.
	 *
	 * @param mixed $value Raw date.
	 *
	 * @return string|null
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
	 * Whitelist order-by expressions.
	 *
	 * @param string $orderby Requested key.
	 *
	 * @return string Safe SQL fragment.
	 */
	private function safe_orderby( string $orderby ): string {
		$map = array(
			'receipt_number' => 'p.receipt_number',
			'paid_on'        => 'p.paid_on',
			'amount_minor'   => 'p.amount_minor',
			'method'         => 'p.method',
			'client_name'    => 'c.display_name',
			'invoice_number' => 'i.invoice_number',
			'created_at'     => 'p.created_at',
			'updated_at'     => 'p.updated_at',
		);

		return $map[ $orderby ] ?? 'p.paid_on';
	}

	/**
	 * Printf formats for column sets.
	 *
	 * @param array<string, mixed> $data Column values.
	 *
	 * @return string[]
	 */
	private function formats( array $data ): array {
		$all = array(
			'receipt_number'  => '%s',
			'invoice_id'      => '%d',
			'client_id'       => '%d',
			'paid_on'         => '%s',
			'amount_minor'    => '%d',
			'withheld_minor'  => '%d',
			'currency'        => '%s',
			'method'          => '%s',
			'reference'       => '%s',
			'withholding_ref' => '%s',
			'notes'           => '%s',
			'created_by'      => '%d',
			'created_at'      => '%s',
			'updated_at'      => '%s',
		);

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			$formats[] = $all[ $key ] ?? '%s';
		}

		return $formats;
	}
}
