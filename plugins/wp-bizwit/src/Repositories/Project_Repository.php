<?php
/**
 * Persistence for project records and their billing stages (termin).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use WP_Error;
use WP_BizWit\Database\Schema;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Reads and writes projects belonging to clients.
 *
 * Termin (staged billing) stages live in `bizwit_project_terms` and are always
 * replaced as a set on save — reordering is just re-submitting the list.
 */
class Project_Repository extends Repository {

	/**
	 * Fixed contract price for the whole project.
	 *
	 * @var string
	 */
	public const BILLING_FIXED = 'fixed';

	/**
	 * Billed by hours at a rate (hours entered when invoicing).
	 *
	 * @var string
	 */
	public const BILLING_HOURLY = 'hourly';

	/**
	 * Billed in ordered stages (termin), each with its own amount.
	 *
	 * @var string
	 */
	public const BILLING_TERMIN = 'termin';

	/**
	 * Recurring retainer amount.
	 *
	 * @var string
	 */
	public const BILLING_RETAINER = 'retainer';

	/**
	 * Work is underway.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Work is paused.
	 *
	 * @var string
	 */
	public const STATUS_ON_HOLD = 'on_hold';

	/**
	 * Work is finished.
	 *
	 * @var string
	 */
	public const STATUS_COMPLETED = 'completed';

	/**
	 * Project was cancelled before completion.
	 *
	 * @var string
	 */
	public const STATUS_CANCELLED = 'cancelled';

	/**
	 * Table this repository owns.
	 *
	 * @return string Fully prefixed table name.
	 */
	protected function table(): string {
		return Schema::table( Schema::PROJECTS );
	}

	/**
	 * Fully prefixed project_terms table name.
	 *
	 * @return string Table name.
	 */
	private function terms_table(): string {
		return Schema::table( Schema::PROJECT_TERMS );
	}

	/**
	 * Selectable billing types with their translated labels.
	 *
	 * @return array<string, string> Billing type slug mapped to label.
	 */
	public static function billing_types(): array {
		return array(
			self::BILLING_FIXED    => __( 'Fixed price', 'wp-bizwit' ),
			self::BILLING_HOURLY   => __( 'Hourly', 'wp-bizwit' ),
			self::BILLING_TERMIN   => __( 'Termin (stages)', 'wp-bizwit' ),
			self::BILLING_RETAINER => __( 'Retainer', 'wp-bizwit' ),
		);
	}

	/**
	 * Selectable project statuses with their translated labels.
	 *
	 * @return array<string, string> Status slug mapped to label.
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_ACTIVE    => __( 'Active', 'wp-bizwit' ),
			self::STATUS_ON_HOLD   => __( 'On hold', 'wp-bizwit' ),
			self::STATUS_COMPLETED => __( 'Completed', 'wp-bizwit' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'wp-bizwit' ),
		);
	}

	/**
	 * Query projects with search, filtering, sorting and pagination.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $search       Free-text search across name and code.
	 *     @type int    $client_id    Restrict to a single client.
	 *     @type string $status       Restrict to a single status.
	 *     @type string $billing_type Restrict to a single billing type.
	 *     @type string $orderby      Column to sort by. Whitelisted.
	 *     @type string $order        ASC or DESC.
	 *     @type int    $per_page     Rows per page.
	 *     @type int    $page         1-based page number.
	 * }
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int} Matching rows and the unpaginated total.
	 */
	public function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'search'       => '',
				'client_id'    => 0,
				'status'       => '',
				'billing_type' => '',
				'orderby'      => 'updated_at',
				'order'        => 'DESC',
				'per_page'     => 20,
				'page'         => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $this->db()->esc_like( $search ) . '%';
			$where[]  = '( p.name LIKE %s OR p.code LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$client_id = (int) $args['client_id'];
		if ( $client_id > 0 ) {
			$where[]  = 'p.client_id = %d';
			$params[] = $client_id;
		}

		if ( array_key_exists( (string) $args['status'], self::statuses() ) ) {
			$where[]  = 'p.status = %s';
			$params[] = (string) $args['status'];
		}

		if ( array_key_exists( (string) $args['billing_type'], self::billing_types() ) ) {
			$where[]  = 'p.billing_type = %s';
			$params[] = (string) $args['billing_type'];
		}

		$where_sql = implode( ' AND ', $where );
		$table     = $this->table();
		$clients   = Schema::table( Schema::CLIENTS );

		$orderby  = $this->safe_orderby( (string) $args['orderby'] );
		$order    = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( max( 1, (int) $args['page'] ) - 1 ) * $per_page );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}` p WHERE {$where_sql}";
		$total     = (int) $this->db()->get_var(
			array() === $params
				? $count_sql
				: $this->db()->prepare( $count_sql, $params )
		);

		$sql           = "SELECT p.*, c.display_name AS client_name FROM `{$table}` p LEFT JOIN `{$clients}` c ON c.id = p.client_id WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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
	 * Projects for one client as an id => name map.
	 *
	 * @param int $client_id Client id.
	 *
	 * @return array<int, string> Project id mapped to name.
	 */
	public function options_for_client( int $client_id ): array {
		if ( $client_id <= 0 ) {
			return array();
		}

		$table = $this->table();
		$sql   = "SELECT id, name FROM `{$table}` WHERE client_id = %d ORDER BY name ASC";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $client_id ), ARRAY_A );
		// phpcs:enable

		$options = array();
		foreach ( (array) $rows as $row ) {
			$options[ (int) $row['id'] ] = (string) $row['name'];
		}

		return $options;
	}

	/**
	 * Count projects grouped by status.
	 *
	 * @return array<string, int> Status slug mapped to count.
	 */
	public function counts_by_status(): array {
		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results( "SELECT status, COUNT(*) AS total FROM `{$table}` GROUP BY status", ARRAY_A );
		// phpcs:enable

		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Ordered termin rows for a project.
	 *
	 * @param int $project_id Project id.
	 *
	 * @return array<int, array<string, mixed>> Term rows.
	 */
	public function get_terms( int $project_id ): array {
		if ( $project_id <= 0 ) {
			return array();
		}

		$table = $this->terms_table();
		$sql   = "SELECT * FROM `{$table}` WHERE project_id = %d ORDER BY sort_order ASC, id ASC";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $project_id ), ARRAY_A );
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Replace every termin row for a project with the given set.
	 *
	 * @param int                              $project_id Project id.
	 * @param array<int, array<string, mixed>> $terms      Sanitised term rows (name, amount_minor, percent, notes).
	 *
	 * @return true|WP_Error True on success.
	 */
	public function replace_terms( int $project_id, array $terms ) {
		if ( $project_id <= 0 ) {
			return new WP_Error( 'wp_bizwit_invalid_project', __( 'A valid project is required for termin stages.', 'wp-bizwit' ) );
		}

		$table = $this->terms_table();
		$now   = $this->now();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->delete( $table, array( 'project_id' => $project_id ), array( '%d' ) );

		$sort = 0;
		foreach ( $terms as $term ) {
			$result = $this->db()->insert(
				$table,
				array(
					'project_id'   => $project_id,
					'sort_order'   => $sort,
					'name'         => (string) ( $term['name'] ?? '' ),
					'amount_minor' => (int) ( $term['amount_minor'] ?? 0 ),
					'percent'      => (string) ( $term['percent'] ?? '0.0000' ),
					'notes'        => (string) ( $term['notes'] ?? '' ),
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( false === $result ) {
				return new WP_Error( 'wp_bizwit_terms_failed', __( 'The termin stages could not be saved.', 'wp-bizwit' ) );
			}

			++$sort;
		}
		// phpcs:enable

		return true;
	}

	/**
	 * Create a project from raw, unsanitised input.
	 *
	 * @param array<string, mixed> $input Raw field values, typically from $_POST.
	 *
	 * @return int|WP_Error New project id, or an error describing what was invalid.
	 */
	public function create( array $input ) {
		$data  = $this->sanitize( $input );
		$terms = $this->sanitize_terms( $input['terms'] ?? array(), (string) $data['currency'], (int) $data['budget_minor'] );
		$error = $this->validate( $data, $terms );

		if ( null !== $error ) {
			return $error;
		}

		$data['created_by'] = get_current_user_id();
		$data['created_at'] = $this->now();
		$data['updated_at'] = $data['created_at'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		$id = $this->insert_row( $data, $this->formats( $data ) );

		if ( 0 === $id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_insert_failed', __( 'The project could not be saved.', 'wp-bizwit' ) );
		}

		$terms_result = $this->replace_terms( $id, $terms );

		if ( is_wp_error( $terms_result ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return $terms_result;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		/**
		 * Fires after a project has been created.
		 *
		 * @param int                  $id   New project id.
		 * @param array<string, mixed> $data Stored column values.
		 */
		do_action( 'wp_bizwit_project_created', $id, $data );

		return $id;
	}

	/**
	 * Update an existing project from raw, unsanitised input.
	 *
	 * @param int                  $id    Project id.
	 * @param array<string, mixed> $input Raw field values, typically from $_POST.
	 *
	 * @return true|WP_Error True on success, or an error describing what went wrong.
	 */
	public function update( int $id, array $input ) {
		if ( null === $this->find( $id ) ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That project no longer exists.', 'wp-bizwit' ) );
		}

		$data  = $this->sanitize( $input );
		$terms = $this->sanitize_terms( $input['terms'] ?? array(), (string) $data['currency'], (int) $data['budget_minor'] );
		$error = $this->validate( $data, $terms );

		if ( null !== $error ) {
			return $error;
		}

		$data['updated_at'] = $this->now();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'START TRANSACTION' );
		// phpcs:enable

		if ( ! $this->update_row( $id, $data, $this->formats( $data ) ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return new WP_Error( 'wp_bizwit_update_failed', __( 'The project could not be updated.', 'wp-bizwit' ) );
		}

		$terms_result = $this->replace_terms( $id, $terms );

		if ( is_wp_error( $terms_result ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db()->query( 'ROLLBACK' );
			// phpcs:enable

			return $terms_result;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->query( 'COMMIT' );
		// phpcs:enable

		/**
		 * Fires after a project has been updated.
		 *
		 * @param int                  $id   Project id.
		 * @param array<string, mixed> $data Stored column values.
		 */
		do_action( 'wp_bizwit_project_updated', $id, $data );

		return true;
	}

	/**
	 * Delete a project, refusing when invoices still reference it.
	 *
	 * @param int $id Project id.
	 *
	 * @return true|WP_Error True on success, or an error explaining the refusal.
	 */
	public function delete( int $id ) {
		if ( null === $this->find( $id ) ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That project no longer exists.', 'wp-bizwit' ) );
		}

		$invoices = $this->count_in( Schema::table( Schema::INVOICES ), 'project_id = %d', array( $id ) );

		if ( $invoices > 0 ) {
			return new WP_Error(
				'wp_bizwit_project_in_use',
				sprintf(
					/* translators: %d: number of invoices */
					__( 'This project still has %d invoice(s). Cancel the project instead of deleting it, so those records keep their history.', 'wp-bizwit' ),
					$invoices
				)
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->delete( $this->terms_table(), array( 'project_id' => $id ), array( '%d' ) );
		// phpcs:enable

		if ( ! $this->delete_row( $id ) ) {
			return new WP_Error( 'wp_bizwit_delete_failed', __( 'The project could not be deleted.', 'wp-bizwit' ) );
		}

		/**
		 * Fires after a project has been deleted.
		 *
		 * @param int $id Deleted project id.
		 */
		do_action( 'wp_bizwit_project_deleted', $id );

		return true;
	}

	/**
	 * Check sanitised data against project rules.
	 *
	 * @param array<string, mixed>             $data  Sanitised column values.
	 * @param array<int, array<string, mixed>> $terms Sanitised termin rows.
	 *
	 * @return WP_Error|null An error to return to the user, or null when valid.
	 */
	private function validate( array $data, array $terms ): ?WP_Error {
		if ( '' === $data['name'] ) {
			return new WP_Error( 'wp_bizwit_missing_name', __( 'A project name is required.', 'wp-bizwit' ) );
		}

		$client_id = (int) $data['client_id'];
		if ( $client_id <= 0 || ! $this->client_exists( $client_id ) ) {
			return new WP_Error( 'wp_bizwit_invalid_client', __( 'Please choose a client for this project.', 'wp-bizwit' ) );
		}

		// Soft: termin with no stages yet is allowed so the user can add them later.
		if (
			self::BILLING_TERMIN === $data['billing_type']
			&& array() !== $terms
			&& (int) $data['budget_minor'] > 0
			&& empty( $data['terms_sum_override'] )
		) {
			$sum = 0;
			foreach ( $terms as $term ) {
				$sum += (int) $term['amount_minor'];
			}

			if ( $sum !== (int) $data['budget_minor'] ) {
				return new WP_Error(
					'wp_bizwit_terms_sum_mismatch',
					__( 'The termin amounts must add up to the project budget. Adjust the stages, the budget, or allow a different total.', 'wp-bizwit' )
				);
			}
		}

		return null;
	}

	/**
	 * Whether a client id exists in the clients table.
	 *
	 * @param int $client_id Client id.
	 *
	 * @return bool True when the client row exists.
	 */
	private function client_exists( int $client_id ): bool {
		return $this->count_in( Schema::table( Schema::CLIENTS ), 'id = %d', array( $client_id ) ) > 0;
	}

	/**
	 * Reduce raw input to exactly the columns this table accepts, sanitised.
	 *
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return array<string, mixed> Column values ready to persist.
	 */
	private function sanitize( array $input ): array {
		$status       = (string) ( $input['status'] ?? self::STATUS_ACTIVE );
		$billing_type = (string) ( $input['billing_type'] ?? self::BILLING_FIXED );

		$currency = strtoupper( trim( (string) ( $input['currency'] ?? '' ) ) );
		if ( 3 !== strlen( $currency ) ) {
			$client_id = (int) ( $input['client_id'] ?? 0 );
			$currency  = $this->currency_for_client( $client_id );
		}

		// Form fields are human amounts (`rate`, `budget`); tests may pass minor ints.
		if ( array_key_exists( 'rate', $input ) ) {
			$rate_minor = Money::to_minor( (string) $input['rate'], $currency );
		} else {
			$rate_minor = (int) ( $input['rate_minor'] ?? 0 );
		}

		if ( array_key_exists( 'budget', $input ) ) {
			$budget_minor = Money::to_minor( (string) $input['budget'], $currency );
		} else {
			$budget_minor = (int) ( $input['budget_minor'] ?? 0 );
		}

		$retensi = isset( $input['retensi_percent'] ) ? (float) $input['retensi_percent'] : 0.0;
		$retensi = max( 0.0, min( 100.0, $retensi ) );

		return array(
			'client_id'          => max( 0, (int) ( $input['client_id'] ?? 0 ) ),
			'code'               => $this->text( $input, 'code', 64 ),
			'name'               => $this->text( $input, 'name', 191 ),
			'status'             => array_key_exists( $status, self::statuses() ) ? $status : self::STATUS_ACTIVE,
			'billing_type'       => array_key_exists( $billing_type, self::billing_types() ) ? $billing_type : self::BILLING_FIXED,
			'currency'           => $currency,
			'rate_minor'         => $rate_minor,
			'budget_minor'       => $budget_minor,
			'retensi_percent'    => number_format( $retensi, 4, '.', '' ),
			'terms_sum_override' => ! empty( $input['terms_sum_override'] ) ? 1 : 0,
			'starts_on'          => $this->date_or_null( $input, 'starts_on' ),
			'ends_on'            => $this->date_or_null( $input, 'ends_on' ),
			'description'        => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
		);
	}

	/**
	 * Reduce raw termin rows to sanitised column sets, dropping empty rows.
	 *
	 * @param mixed  $raw_terms Raw terms list from input.
	 * @param string $currency  ISO currency for amount parsing.
	 * @param int    $budget    Project budget in minor units (for optional percent).
	 *
	 * @return array<int, array<string, mixed>> Sanitised terms.
	 */
	private function sanitize_terms( $raw_terms, string $currency, int $budget ): array {
		if ( ! is_array( $raw_terms ) ) {
			return array();
		}

		$terms = array();

		foreach ( $raw_terms as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name  = substr( sanitize_text_field( (string) ( $row['name'] ?? '' ) ), 0, 191 );
			$notes = sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) );

			if ( isset( $row['amount_minor'] ) && is_int( $row['amount_minor'] ) ) {
				$amount = (int) $row['amount_minor'];
			} elseif ( array_key_exists( 'amount', $row ) ) {
				$amount = Money::to_minor( (string) $row['amount'], $currency );
			} elseif ( isset( $row['amount_minor'] ) ) {
				$amount = Money::to_minor( (string) $row['amount_minor'], $currency );
			} else {
				$amount = 0;
			}

			$percent = 0.0;
			if ( isset( $row['percent'] ) && '' !== (string) $row['percent'] ) {
				$percent = max( 0.0, min( 100.0, (float) $row['percent'] ) );
			} elseif ( $budget > 0 && $amount > 0 ) {
				$percent = min( 100.0, ( $amount / $budget ) * 100.0 );
			}

			// Skip completely empty rows (extra blanks on the form).
			if ( '' === $name && 0 === $amount && '' === $notes ) {
				continue;
			}

			$terms[] = array(
				'name'         => $name,
				'amount_minor' => $amount,
				'percent'      => number_format( $percent, 4, '.', '' ),
				'notes'        => $notes,
			);
		}

		return $terms;
	}

	/**
	 * Default currency for a client, falling back to site settings.
	 *
	 * @param int $client_id Client id.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function currency_for_client( int $client_id ): string {
		if ( $client_id <= 0 ) {
			return Settings::currency();
		}

		$clients = Schema::table( Schema::CLIENTS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$currency = $this->db()->get_var(
			$this->db()->prepare( "SELECT currency FROM `{$clients}` WHERE id = %d", $client_id )
		);
		// phpcs:enable

		if ( is_string( $currency ) && 3 === strlen( $currency ) ) {
			return strtoupper( $currency );
		}

		return Settings::currency();
	}

	/**
	 * Sanitise a single-line text field and clamp it to its column width.
	 *
	 * @param array<string, mixed> $input  Raw field values.
	 * @param string               $key    Field name.
	 * @param int                  $length Maximum stored length.
	 *
	 * @return string Sanitised value.
	 */
	private function text( array $input, string $key, int $length ): string {
		return substr( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ), 0, $length );
	}

	/**
	 * Parse a date field to Y-m-d or null when empty/invalid.
	 *
	 * @param array<string, mixed> $input Raw field values.
	 * @param string               $key   Field name.
	 *
	 * @return string|null MySQL date or null.
	 */
	private function date_or_null( array $input, string $key ): ?string {
		$raw = trim( (string) ( $input[ $key ] ?? '' ) );

		if ( '' === $raw ) {
			return null;
		}

		$stamp = strtotime( $raw );
		if ( false === $stamp ) {
			return null;
		}

		return gmdate( 'Y-m-d', $stamp );
	}

	/**
	 * Printf-style formats matching the column order produced by sanitize().
	 *
	 * @param array<string, mixed> $data Column values.
	 *
	 * @return string[] Format specifiers in the same order as $data.
	 */
	private function formats( array $data ): array {
		$integer_columns = array( 'client_id', 'rate_minor', 'budget_minor', 'terms_sum_override', 'created_by' );
		$formats         = array();

		foreach ( array_keys( $data ) as $column ) {
			if ( in_array( $column, $integer_columns, true ) ) {
				$formats[] = '%d';
				continue;
			}

			// starts_on / ends_on may be null; wpdb treats %s + null as NULL when formats match.
			$formats[] = '%s';
		}

		return $formats;
	}

	/**
	 * Resolve a requested sort column against a whitelist.
	 *
	 * @param string $orderby Requested column.
	 *
	 * @return string A column expression that is safe to interpolate into SQL.
	 */
	private function safe_orderby( string $orderby ): string {
		$allowed = array(
			'id'           => 'p.id',
			'name'         => 'p.name',
			'code'         => 'p.code',
			'status'       => 'p.status',
			'billing_type' => 'p.billing_type',
			'budget_minor' => 'p.budget_minor',
			'client_id'    => 'p.client_id',
			'client_name'  => 'c.display_name',
			'starts_on'    => 'p.starts_on',
			'ends_on'      => 'p.ends_on',
			'created_at'   => 'p.created_at',
			'updated_at'   => 'p.updated_at',
		);

		return $allowed[ $orderby ] ?? 'p.updated_at';
	}
}
