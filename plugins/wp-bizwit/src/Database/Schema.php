<?php
/**
 * Central registry of every custom database table owned by this plugin.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Database;

/**
 * Defines table names and the `CREATE TABLE` statements consumed by dbDelta().
 *
 * Region-specific paperwork fields (NPWP variants, NIK, kelurahan, satker and
 * so on) live in a JSON `meta` column rather than getting their own columns.
 * They are printed on documents, never filtered or sorted on, and giving each
 * jurisdiction its own columns would widen the table for every user who does not
 * operate there.
 *
 * All monetary columns are stored as signed BIGINT in the *minor unit* of the
 * relevant currency (cents, sen, pence). Never use FLOAT/DOUBLE for money:
 * binary floating point cannot represent 0.10 exactly, so summing invoice lines
 * accumulates error that eventually shows up as an invoice that will not
 * reconcile against its payments.
 */
class Schema {

	/**
	 * Unprefixed table name for client records.
	 *
	 * @var string
	 */
	public const CLIENTS = 'bizwit_clients';

	/**
	 * Unprefixed table name for contact people attached to a client.
	 *
	 * @var string
	 */
	public const CLIENT_CONTACTS = 'bizwit_client_contacts';

	/**
	 * Unprefixed table name for projects.
	 *
	 * @var string
	 */
	public const PROJECTS = 'bizwit_projects';

	/**
	 * Unprefixed table name for project billing stages (termin).
	 *
	 * @var string
	 */
	public const PROJECT_TERMS = 'bizwit_project_terms';

	/**
	 * Unprefixed table name for invoice headers.
	 *
	 * @var string
	 */
	public const INVOICES = 'bizwit_invoices';

	/**
	 * Unprefixed table name for invoice line items.
	 *
	 * @var string
	 */
	public const INVOICE_ITEMS = 'bizwit_invoice_items';

	/**
	 * Unprefixed table name for recorded payments / receipts.
	 *
	 * @var string
	 */
	public const PAYMENTS = 'bizwit_payments';

	/**
	 * Unprefixed table name for atomic document number counters.
	 *
	 * @var string
	 */
	public const SEQUENCES = 'bizwit_sequences';

	/**
	 * Resolve an unprefixed table constant to its real, prefixed table name.
	 *
	 * @param string $table One of the class constants, e.g. self::CLIENTS.
	 *
	 * @return string Fully prefixed table name.
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Every unprefixed table name this plugin owns.
	 *
	 * @return string[] List of unprefixed table names.
	 */
	public static function tables(): array {
		return array(
			self::CLIENTS,
			self::CLIENT_CONTACTS,
			self::PROJECTS,
			self::PROJECT_TERMS,
			self::INVOICES,
			self::INVOICE_ITEMS,
			self::PAYMENTS,
			self::SEQUENCES,
		);
	}

	/**
	 * Build every `CREATE TABLE` statement in dbDelta() compatible form.
	 *
	 * The dbDelta() helper parses these strings with regular expressions rather
	 * than a real SQL parser, so the formatting below is load-bearing: one column per line,
	 * lowercase types, `KEY` instead of `INDEX`, and two spaces after
	 * `PRIMARY KEY`.
	 *
	 * @return string[] List of SQL statements.
	 */
	public static function statements(): array {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		return array(
			self::clients_sql( $collate ),
			self::client_contacts_sql( $collate ),
			self::projects_sql( $collate ),
			self::project_terms_sql( $collate ),
			self::invoices_sql( $collate ),
			self::invoice_items_sql( $collate ),
			self::payments_sql( $collate ),
			self::sequences_sql( $collate ),
		);
	}

	/**
	 * Schema for client records of every entity type.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function clients_sql( string $collate ): string {
		$table = self::table( self::CLIENTS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'company',
			status varchar(20) NOT NULL DEFAULT 'active',
			display_name varchar(191) NOT NULL DEFAULT '',
			legal_name varchar(191) NOT NULL DEFAULT '',
			tax_id varchar(64) NOT NULL DEFAULT '',
			registration_no varchar(64) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(64) NOT NULL DEFAULT '',
			website varchar(191) NOT NULL DEFAULT '',
			address_line1 varchar(191) NOT NULL DEFAULT '',
			address_line2 varchar(191) NOT NULL DEFAULT '',
			city varchar(96) NOT NULL DEFAULT '',
			state varchar(96) NOT NULL DEFAULT '',
			postal_code varchar(32) NOT NULL DEFAULT '',
			country varchar(2) NOT NULL DEFAULT '',
			currency varchar(3) NOT NULL DEFAULT 'USD',
			payment_terms_days smallint(5) unsigned NOT NULL DEFAULT 30,
			notes longtext NOT NULL,
			meta longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY status (status),
			KEY display_name (display_name),
			KEY email (email)
		) {$collate};";
	}

	/**
	 * Schema for named contact people belonging to a client.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function client_contacts_sql( string $collate ): string {
		$table = self::table( self::CLIENT_CONTACTS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			job_title varchar(96) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(64) NOT NULL DEFAULT '',
			is_primary tinyint(1) NOT NULL DEFAULT 0,
			notes text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY is_primary (is_primary)
		) {$collate};";
	}

	/**
	 * Schema for projects belonging to a client.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function projects_sql( string $collate ): string {
		$table = self::table( self::PROJECTS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			code varchar(64) NOT NULL DEFAULT '',
			name varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			billing_type varchar(20) NOT NULL DEFAULT 'fixed',
			currency varchar(3) NOT NULL DEFAULT 'USD',
			rate_minor bigint(20) NOT NULL DEFAULT 0,
			budget_minor bigint(20) NOT NULL DEFAULT 0,
			retensi_percent decimal(7,4) NOT NULL DEFAULT 0.0000,
			terms_sum_override tinyint(1) NOT NULL DEFAULT 0,
			starts_on date DEFAULT NULL,
			ends_on date DEFAULT NULL,
			description longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY status (status),
			KEY code (code)
		) {$collate};";
	}

	/**
	 * Schema for ordered billing stages (termin) on a project.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function project_terms_sql( string $collate ): string {
		$table = self::table( self::PROJECT_TERMS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			amount_minor bigint(20) NOT NULL DEFAULT 0,
			percent decimal(7,4) NOT NULL DEFAULT 0.0000,
			notes text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY sort_order (sort_order)
		) {$collate};";
	}

	/**
	 * Schema for invoice headers.
	 *
	 * The outstanding balance is intentionally not stored: it is always
	 * `total_minor - paid_minor`. A stored balance is a second source of truth
	 * that will drift the first time a payment is edited outside the happy path.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function invoices_sql( string $collate ): string {
		$table = self::table( self::INVOICES );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			invoice_number varchar(64) NOT NULL DEFAULT '',
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			issue_date date DEFAULT NULL,
			due_date date DEFAULT NULL,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			subtotal_minor bigint(20) NOT NULL DEFAULT 0,
			discount_minor bigint(20) NOT NULL DEFAULT 0,
			tax_minor bigint(20) NOT NULL DEFAULT 0,
			total_minor bigint(20) NOT NULL DEFAULT 0,
			paid_minor bigint(20) NOT NULL DEFAULT 0,
			withholding_rate decimal(7,4) NOT NULL DEFAULT 0.0000,
			withholding_minor bigint(20) NOT NULL DEFAULT 0,
			notes longtext NOT NULL,
			terms longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY client_id (client_id),
			KEY project_id (project_id),
			KEY status (status),
			KEY issue_date (issue_date),
			KEY due_date (due_date)
		) {$collate};";
	}

	/**
	 * Schema for invoice line items.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function invoice_items_sql( string $collate ): string {
		$table = self::table( self::INVOICE_ITEMS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			description text NOT NULL,
			quantity decimal(14,4) NOT NULL DEFAULT 1.0000,
			unit varchar(32) NOT NULL DEFAULT '',
			unit_price_minor bigint(20) NOT NULL DEFAULT 0,
			tax_rate decimal(7,4) NOT NULL DEFAULT 0.0000,
			line_total_minor bigint(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY invoice_id (invoice_id),
			KEY sort_order (sort_order)
		) {$collate};";
	}

	/**
	 * Schema for recorded payments, which double as payment receipts.
	 *
	 * This plugin records payments that happened elsewhere; it never moves money.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function payments_sql( string $collate ): string {
		$table = self::table( self::PAYMENTS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			receipt_number varchar(64) NOT NULL DEFAULT '',
			invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			paid_on date DEFAULT NULL,
			amount_minor bigint(20) NOT NULL DEFAULT 0,
			withheld_minor bigint(20) NOT NULL DEFAULT 0,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			method varchar(32) NOT NULL DEFAULT 'bank_transfer',
			reference varchar(191) NOT NULL DEFAULT '',
			withholding_ref varchar(191) NOT NULL DEFAULT '',
			notes longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY receipt_number (receipt_number),
			KEY invoice_id (invoice_id),
			KEY client_id (client_id),
			KEY paid_on (paid_on)
		) {$collate};";
	}

	/**
	 * Schema for atomic document number counters.
	 *
	 * Deliberately keyless apart from the natural key so that a single
	 * `INSERT ... ON DUPLICATE KEY UPDATE` can allocate a number atomically.
	 *
	 * @param string $collate Charset and collation clause.
	 *
	 * @return string SQL statement.
	 */
	private static function sequences_sql( string $collate ): string {
		$table = self::table( self::SEQUENCES );

		return "CREATE TABLE {$table} (
			sequence_key varchar(64) NOT NULL,
			next_value bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (sequence_key)
		) {$collate};";
	}
}
