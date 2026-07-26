<?php
/**
 * Persistence for client records.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Repositories;

use WP_Error;
use WP_BizWit\Database\Schema;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Settings;

/**
 * Reads and writes client records of every entity type.
 *
 * A "client" here is deliberately one table rather than four. An individual, a
 * company, a government body and a non-profit differ in which fields are
 * *relevant*, not in which fields exist, and every one of them can be billed the
 * same way. Splitting them into separate tables would force a union in every
 * invoice query for no gain.
 */
class Client_Repository extends Repository {

	/**
	 * A private person billed in their own name.
	 *
	 * @var string
	 */
	public const TYPE_INDIVIDUAL = 'individual';

	/**
	 * A commercial entity.
	 *
	 * @var string
	 */
	public const TYPE_COMPANY = 'company';

	/**
	 * A government department, agency or state-owned body.
	 *
	 * @var string
	 */
	public const TYPE_GOVERNMENT = 'government';

	/**
	 * Any other organisation, such as an NGO, school or association.
	 *
	 * @var string
	 */
	public const TYPE_ORGANIZATION = 'organization';

	/**
	 * Client is currently doing business with you.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Client is dormant but may return.
	 *
	 * @var string
	 */
	public const STATUS_INACTIVE = 'inactive';

	/**
	 * Client is kept for historical records only.
	 *
	 * @var string
	 */
	public const STATUS_ARCHIVED = 'archived';

	/**
	 * Table this repository owns.
	 *
	 * @return string Fully prefixed table name.
	 */
	protected function table(): string {
		return Schema::table( Schema::CLIENTS );
	}

	/**
	 * Selectable client types with their translated labels.
	 *
	 * @return array<string, string> Type slug mapped to label.
	 */
	public static function types(): array {
		// Labels come from the active region: the same four stored slugs are
		// called Perorangan, Perusahaan, Instansi Pemerintah and Yayasan /
		// Koperasi to an Indonesian business.
		return Regions::current()->client_types();
	}

	/**
	 * Selectable client statuses with their translated labels.
	 *
	 * @return array<string, string> Status slug mapped to label.
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_ACTIVE   => __( 'Active', 'wp-bizwit' ),
			self::STATUS_INACTIVE => __( 'Inactive', 'wp-bizwit' ),
			self::STATUS_ARCHIVED => __( 'Archived', 'wp-bizwit' ),
		);
	}

	/**
	 * Query clients with search, filtering, sorting and pagination.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $search   Free-text search across name, email and tax id.
	 *     @type string $type     Restrict to a single client type.
	 *     @type string $status   Restrict to a single status.
	 *     @type string $orderby  Column to sort by. Whitelisted.
	 *     @type string $order    ASC or DESC.
	 *     @type int    $per_page Rows per page.
	 *     @type int    $page     1-based page number.
	 * }
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int} Matching rows and the unpaginated total.
	 */
	public function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'type'     => '',
				'status'   => '',
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $this->db()->esc_like( $search ) . '%';
			$where[]  = '( display_name LIKE %s OR legal_name LIKE %s OR email LIKE %s OR tax_id LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( array_key_exists( (string) $args['type'], self::types() ) ) {
			$where[]  = 'type = %s';
			$params[] = (string) $args['type'];
		}

		if ( array_key_exists( (string) $args['status'], self::statuses() ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}

		$where_sql = implode( ' AND ', $where );
		$table     = $this->table();

		// ORDER BY cannot be parameterised, so the column and direction are
		// resolved against fixed whitelists rather than escaped.
		$orderby  = $this->safe_orderby( (string) $args['orderby'] );
		$order    = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( max( 1, (int) $args['page'] ) - 1 ) * $per_page );

		$total = $this->count_in( $table, $where_sql, $params );

		$sql           = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $list_params ), ARRAY_A );
		// phpcs:enable

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * All clients as an id => name map, for use in select fields.
	 *
	 * @param bool $active_only Whether to exclude archived and inactive clients.
	 *
	 * @return array<int, string> Client id mapped to display name.
	 */
	public function options( bool $active_only = true ): array {
		$table = $this->table();
		$where = $active_only ? "WHERE status = '" . self::STATUS_ACTIVE . "'" : '';
		$sql   = "SELECT id, display_name FROM `{$table}` {$where} ORDER BY display_name ASC";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db()->get_results( $sql, ARRAY_A );
		// phpcs:enable

		$options = array();
		foreach ( (array) $rows as $row ) {
			$options[ (int) $row['id'] ] = (string) $row['display_name'];
		}

		return $options;
	}

	/**
	 * Count clients grouped by status.
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
	 * Create a client from raw, unsanitised input.
	 *
	 * @param array<string, mixed> $input Raw field values, typically from $_POST.
	 *
	 * @return int|WP_Error New client id, or an error describing what was invalid.
	 */
	public function create( array $input ) {
		$data  = $this->sanitize( $input );
		$error = $this->validate( $data );

		if ( null !== $error ) {
			return $error;
		}

		$data['created_by'] = get_current_user_id();
		$data['created_at'] = $this->now();
		$data['updated_at'] = $data['created_at'];

		$id = $this->insert_row( $data, $this->formats( $data ) );

		if ( 0 === $id ) {
			return new WP_Error( 'wp_bizwit_insert_failed', __( 'The client could not be saved.', 'wp-bizwit' ) );
		}

		/**
		 * Fires after a client has been created.
		 *
		 * @param int                  $id   New client id.
		 * @param array<string, mixed> $data Stored column values.
		 */
		do_action( 'wp_bizwit_client_created', $id, $data );

		return $id;
	}

	/**
	 * Update an existing client from raw, unsanitised input.
	 *
	 * @param int                  $id    Client id.
	 * @param array<string, mixed> $input Raw field values, typically from $_POST.
	 *
	 * @return true|WP_Error True on success, or an error describing what went wrong.
	 */
	public function update( int $id, array $input ) {
		if ( null === $this->find( $id ) ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That client no longer exists.', 'wp-bizwit' ) );
		}

		$data  = $this->sanitize( $input );
		$error = $this->validate( $data );

		if ( null !== $error ) {
			return $error;
		}

		$data['updated_at'] = $this->now();

		if ( ! $this->update_row( $id, $data, $this->formats( $data ) ) ) {
			return new WP_Error( 'wp_bizwit_update_failed', __( 'The client could not be updated.', 'wp-bizwit' ) );
		}

		/**
		 * Fires after a client has been updated.
		 *
		 * @param int                  $id   Client id.
		 * @param array<string, mixed> $data Stored column values.
		 */
		do_action( 'wp_bizwit_client_updated', $id, $data );

		return true;
	}

	/**
	 * Delete a client, refusing when dependent records would be orphaned.
	 *
	 * There are no foreign keys on these tables, so nothing at the database
	 * level would stop this from stranding invoices against a client id that no
	 * longer resolves. The check lives here instead.
	 *
	 * @param int $id Client id.
	 *
	 * @return true|WP_Error True on success, or an error explaining the refusal.
	 */
	public function delete( int $id ) {
		if ( null === $this->find( $id ) ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That client no longer exists.', 'wp-bizwit' ) );
		}

		$projects = $this->count_in( Schema::table( Schema::PROJECTS ), 'client_id = %d', array( $id ) );
		$invoices = $this->count_in( Schema::table( Schema::INVOICES ), 'client_id = %d', array( $id ) );
		$payments = $this->count_in( Schema::table( Schema::PAYMENTS ), 'client_id = %d', array( $id ) );

		if ( $projects > 0 || $invoices > 0 || $payments > 0 ) {
			return new WP_Error(
				'wp_bizwit_client_in_use',
				sprintf(
					/* translators: 1: number of projects, 2: number of invoices, 3: number of payments */
					__( 'This client still has %1$d project(s), %2$d invoice(s) and %3$d payment(s). Archive the client instead of deleting it, so those records keep their history.', 'wp-bizwit' ),
					$projects,
					$invoices,
					$payments
				)
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db()->delete( Schema::table( Schema::CLIENT_CONTACTS ), array( 'client_id' => $id ), array( '%d' ) );
		// phpcs:enable

		if ( ! $this->delete_row( $id ) ) {
			return new WP_Error( 'wp_bizwit_delete_failed', __( 'The client could not be deleted.', 'wp-bizwit' ) );
		}

		/**
		 * Fires after a client has been deleted.
		 *
		 * @param int $id Deleted client id.
		 */
		do_action( 'wp_bizwit_client_deleted', $id );

		return true;
	}

	/**
	 * Check sanitised data against the rules of the active region.
	 *
	 * @param array<string, mixed> $data Sanitised column values.
	 *
	 * @return WP_Error|null An error to return to the user, or null when valid.
	 */
	private function validate( array $data ): ?WP_Error {
		if ( '' === $data['display_name'] ) {
			return new WP_Error( 'wp_bizwit_missing_name', __( 'A client name is required.', 'wp-bizwit' ) );
		}

		$region = Regions::current();
		$tax_id = (string) $data['tax_id'];

		if ( ! $region->is_valid_tax_id( $tax_id ) ) {
			return new WP_Error(
				'wp_bizwit_invalid_tax_id',
				sprintf(
					/* translators: %s: name of the tax identifier, for example NPWP */
					__( 'The %s does not look right. Check the number of digits and try again.', 'wp-bizwit' ),
					$region->field_label( 'tax_id', __( 'tax ID', 'wp-bizwit' ) )
				)
			);
		}

		return null;
	}

	/**
	 * Reduce raw input to exactly the columns this table accepts, sanitised.
	 *
	 * Building the column list here rather than from array keys of the input is
	 * what keeps an unexpected `$_POST` key from ever reaching a query.
	 *
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return array<string, mixed> Column values ready to persist.
	 */
	private function sanitize( array $input ): array {
		$type   = (string) ( $input['type'] ?? self::TYPE_COMPANY );
		$status = (string) ( $input['status'] ?? self::STATUS_ACTIVE );

		$currency = strtoupper( trim( (string) ( $input['currency'] ?? '' ) ) );
		if ( 3 !== strlen( $currency ) ) {
			$currency = Settings::currency();
		}

		$terms = isset( $input['payment_terms_days'] ) ? (int) $input['payment_terms_days'] : (int) Settings::get( 'payment_terms_days', 30 );

		// The region normalises its own tax identifier, so an NPWP typed as bare
		// digits is stored in the 00.000.000.0-000.000 form people expect to see.
		$tax_id = Regions::current()->format_tax_id( (string) ( $input['tax_id'] ?? '' ) );

		return array(
			'type'               => array_key_exists( $type, self::types() ) ? $type : self::TYPE_COMPANY,
			'status'             => array_key_exists( $status, self::statuses() ) ? $status : self::STATUS_ACTIVE,
			'display_name'       => $this->text( $input, 'display_name', 191 ),
			'legal_name'         => $this->text( $input, 'legal_name', 191 ),
			'tax_id'             => substr( sanitize_text_field( $tax_id ), 0, 64 ),
			'registration_no'    => $this->text( $input, 'registration_no', 64 ),
			'email'              => sanitize_email( (string) ( $input['email'] ?? '' ) ),
			'phone'              => $this->text( $input, 'phone', 64 ),
			'website'            => esc_url_raw( (string) ( $input['website'] ?? '' ) ),
			'address_line1'      => $this->text( $input, 'address_line1', 191 ),
			'address_line2'      => $this->text( $input, 'address_line2', 191 ),
			'city'               => $this->text( $input, 'city', 96 ),
			'state'              => $this->text( $input, 'state', 96 ),
			'postal_code'        => $this->text( $input, 'postal_code', 32 ),
			'country'            => strtoupper( substr( sanitize_text_field( (string) ( $input['country'] ?? '' ) ), 0, 2 ) ),
			'currency'           => $currency,
			'payment_terms_days' => max( 0, min( 3650, $terms ) ),
			'notes'              => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
			'meta'               => $this->sanitize_meta( $input ),
		);
	}

	/**
	 * Reduce region-specific input to a JSON blob for the meta column.
	 *
	 * Only keys the active region actually declares are kept, so switching
	 * region never lets an unexpected field through, and a checkbox that was
	 * simply not submitted is stored as false rather than being left stale.
	 *
	 * @param array<string, mixed> $input Raw field values.
	 *
	 * @return string JSON object, or '' when the region declares no extra fields.
	 */
	private function sanitize_meta( array $input ): string {
		$fields = Regions::current()->meta_fields();

		if ( array() === $fields ) {
			return '';
		}

		$meta = array();

		foreach ( $fields as $key => $definition ) {
			$raw = $input[ 'meta_' . $key ] ?? null;

			if ( 'checkbox' === $definition['type'] ) {
				$meta[ $key ] = ! empty( $raw );

				continue;
			}

			$value = sanitize_text_field( (string) $raw );

			if ( 'select' === $definition['type'] ) {
				$options      = $definition['options'] ?? array();
				$meta[ $key ] = array_key_exists( $value, $options ) ? $value : '';

				continue;
			}

			$meta[ $key ] = substr( $value, 0, (int) ( $definition['maxlength'] ?? 191 ) );
		}

		return (string) wp_json_encode( $meta );
	}

	/**
	 * Decode a client's region-specific meta fields.
	 *
	 * @param array<string, mixed> $client Client row.
	 *
	 * @return array<string, mixed> Meta values, empty when none are stored.
	 */
	public static function meta( array $client ): array {
		$raw = (string) ( $client['meta'] ?? '' );

		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
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
	 * Printf-style formats matching the column order produced by sanitize().
	 *
	 * @param array<string, mixed> $data Column values.
	 *
	 * @return string[] Format specifiers in the same order as $data.
	 */
	private function formats( array $data ): array {
		$integer_columns = array( 'payment_terms_days', 'created_by' );
		$formats         = array();

		foreach ( array_keys( $data ) as $column ) {
			$formats[] = in_array( $column, $integer_columns, true ) ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * Resolve a requested sort column against a whitelist.
	 *
	 * @param string $orderby Requested column.
	 *
	 * @return string A column name that is safe to interpolate into SQL.
	 */
	private function safe_orderby( string $orderby ): string {
		$allowed = array( 'id', 'display_name', 'type', 'status', 'email', 'city', 'created_at', 'updated_at' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'display_name';
	}
}
