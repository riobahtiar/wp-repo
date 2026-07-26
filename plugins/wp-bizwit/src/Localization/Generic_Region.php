<?php
/**
 * Region-neutral fallback profile.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Localization;

use WP_BizWit\Support\Settings;

/**
 * Plain international profile, for a business outside any supported region.
 *
 * Deliberately minimal: it names things the way an English-language invoice
 * does and adds no jurisdiction-specific fields or rules.
 */
class Generic_Region extends Region {

	/**
	 * Machine-readable region identifier.
	 *
	 * @return string Region code.
	 */
	public function code(): string {
		return 'generic';
	}

	/**
	 * Human-readable region name.
	 *
	 * @return string Translated label.
	 */
	public function label(): string {
		return __( 'International (generic)', 'wp-bizwit' );
	}

	/**
	 * Region-neutral client type labels.
	 *
	 * @return array<string, string> Type slug mapped to label.
	 */
	public function client_types(): array {
		return array(
			'individual'   => __( 'Individual', 'wp-bizwit' ),
			'company'      => __( 'Company', 'wp-bizwit' ),
			'government'   => __( 'Government', 'wp-bizwit' ),
			'organization' => __( 'Organization', 'wp-bizwit' ),
		);
	}

	/**
	 * Build a plain sequential document number.
	 *
	 * @param string $type     Document type key, 'invoice' or 'receipt'.
	 * @param int    $sequence Allocated sequence value.
	 * @param string $date     Document date in Y-m-d form. Unused in this format.
	 *
	 * @return string Formatted document number.
	 */
	public function document_number( string $type, int $sequence, string $date ): string {
		$prefix = 'receipt' === $type
			? (string) Settings::get( 'receipt_prefix', 'RCP-' )
			: (string) Settings::get( 'invoice_prefix', 'INV-' );

		$padding = (int) Settings::get( 'number_padding', 4 );
		$padding = max( 1, min( 12, $padding ) );

		return $prefix . str_pad( (string) $sequence, $padding, '0', STR_PAD_LEFT );
	}
}
