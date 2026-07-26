<?php
/**
 * Base class for regional business profiles.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Localization;

/**
 * Adapts the plugin's vocabulary, fields and formatting to a country's business practice.
 *
 * A region is not a language. The interface language comes from WordPress and the
 * plugin's translation files; a region changes what the business *domain* is
 * called and which fields exist. An Indonesian company running wp-admin in
 * English still needs an NPWP field labelled NPWP, a Provinsi dropdown, and
 * receipts that carry the amount in words. Tying those to the site locale would
 * force a language switch to get correct paperwork.
 */
abstract class Region {

	/**
	 * Machine-readable region identifier.
	 *
	 * @return string Region code, for example 'id'.
	 */
	abstract public function code(): string;

	/**
	 * Human-readable region name.
	 *
	 * @return string Translated label.
	 */
	abstract public function label(): string;

	/**
	 * Client type labels for this region.
	 *
	 * Keys must match the type slugs stored in the clients table; only the
	 * wording changes per region.
	 *
	 * @return array<string, string> Type slug mapped to label.
	 */
	abstract public function client_types(): array;

	/**
	 * Override the label of a core client field.
	 *
	 * @param string $key      Field name, matching the column name.
	 * @param string $fallback Region-neutral label.
	 *
	 * @return string Label to display.
	 */
	public function field_label( string $key, string $fallback ): string {
		$labels = $this->field_labels();

		return $labels[ $key ] ?? $fallback;
	}

	/**
	 * Override the help text of a core client field.
	 *
	 * @param string $key      Field name, matching the column name.
	 * @param string $fallback Region-neutral help text.
	 *
	 * @return string Help text to display.
	 */
	public function field_description( string $key, string $fallback = '' ): string {
		$descriptions = $this->field_descriptions();

		return $descriptions[ $key ] ?? $fallback;
	}

	/**
	 * Region-specific labels for core client fields.
	 *
	 * @return array<string, string> Field name mapped to label.
	 */
	protected function field_labels(): array {
		return array();
	}

	/**
	 * Region-specific help text for core client fields.
	 *
	 * @return array<string, string> Field name mapped to help text.
	 */
	protected function field_descriptions(): array {
		return array();
	}

	/**
	 * Additional client fields stored in the client's meta column.
	 *
	 * Region-specific fields live in meta rather than in their own columns, so
	 * adding a second country's profile later does not widen the table for
	 * everyone. These are paperwork fields, not fields anyone filters a list by.
	 *
	 * @return array<string, array{label: string, type: string, description?: string, maxlength?: int, options?: array<string, string>}> Field key mapped to definition.
	 */
	public function meta_fields(): array {
		return array();
	}

	/**
	 * Ways a client in this region pays.
	 *
	 * @return array<string, string> Method slug mapped to label.
	 */
	public function payment_methods(): array {
		return array(
			'bank_transfer' => __( 'Bank transfer', 'wp-bizwit' ),
			'cash'          => __( 'Cash', 'wp-bizwit' ),
			'card'          => __( 'Card', 'wp-bizwit' ),
			'cheque'        => __( 'Cheque', 'wp-bizwit' ),
			'other'         => __( 'Other', 'wp-bizwit' ),
		);
	}

	/**
	 * Fixed list of first-level administrative divisions.
	 *
	 * An empty array means the state or province field stays free text.
	 *
	 * @return string[] Division names.
	 */
	public function provinces(): array {
		return array();
	}

	/**
	 * Default currency for businesses in this region.
	 *
	 * @return string ISO 4217 currency code.
	 */
	public function default_currency(): string {
		return 'USD';
	}

	/**
	 * Default sales tax label and rate for this region.
	 *
	 * @return array{label: string, rate: string} Tax label and percentage rate.
	 */
	public function default_tax(): array {
		return array(
			'label' => __( 'Tax', 'wp-bizwit' ),
			'rate'  => '0',
		);
	}

	/**
	 * Normalise a tax identifier into the region's canonical presentation.
	 *
	 * @param string $raw Tax identifier as typed.
	 *
	 * @return string Formatted identifier, or the trimmed input when it cannot be formatted.
	 */
	public function format_tax_id( string $raw ): string {
		return trim( $raw );
	}

	/**
	 * Whether a tax identifier is structurally valid for this region.
	 *
	 * An empty identifier is always acceptable; not every client has one.
	 *
	 * @param string $raw Tax identifier as typed.
	 *
	 * @return bool True when the value is empty or well formed.
	 */
	public function is_valid_tax_id( string $raw ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature exists for subclasses that do validate.
		return true;
	}

	/**
	 * Format a number for display using this region's separators.
	 *
	 * @param float $value    Number to format.
	 * @param int   $decimals Decimal places to show.
	 *
	 * @return string Formatted number.
	 */
	public function format_number( float $value, int $decimals = 0 ): string {
		return number_format_i18n( $value, $decimals );
	}

	/**
	 * Format a stored date for display.
	 *
	 * @param string $date Date in Y-m-d form.
	 *
	 * @return string Formatted date, or '' when the input is not a date.
	 */
	public function format_date( string $date ): string {
		$stamp = strtotime( $date );

		if ( false === $stamp ) {
			return '';
		}

		return (string) date_i18n( (string) get_option( 'date_format', 'Y-m-d' ), $stamp );
	}

	/**
	 * Express a monetary amount in words.
	 *
	 * Returns '' when the region has no such convention.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return string The amount written out, or '' when not applicable.
	 */
	public function amount_in_words( int $minor, string $currency ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature exists for subclasses with such a convention.
		return '';
	}

	/**
	 * Build a document number from an allocated sequence value.
	 *
	 * @param string $type     Document type key, 'invoice' or 'receipt'.
	 * @param int    $sequence Allocated sequence value.
	 * @param string $date     Document date in Y-m-d form.
	 *
	 * @return string Formatted document number.
	 */
	abstract public function document_number( string $type, int $sequence, string $date ): string;

	/**
	 * Stamp duty payable on a document stating the given amount.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return int Duty in minor units. Zero when none applies.
	 */
	public function stamp_duty( int $minor, string $currency ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature exists for subclasses with stamp duty.
		return 0;
	}

	/**
	 * Notes this region expects to appear on an invoice or receipt.
	 *
	 * @return string[] Translated notes.
	 */
	public function document_notes(): array {
		return array();
	}
}
