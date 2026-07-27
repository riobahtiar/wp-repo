<?php
/**
 * Line item kind + billing period metadata for invoice lines.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

/**
 * Pure helpers for the four additive columns on `bizwit_invoice_items`:
 * `item_kind`, `billing_period`, `period_count`, `period_unit`.
 *
 * Two kinds of strings live here, on purpose:
 *
 * - **Canonical satuan terms** (`bulan`, `kuartal`, `tahun`, …) are *stored
 *   data* written to the `unit` column. They are never gettext-translated on
 *   input, so stored values stay identical regardless of the admin locale.
 * - **Labels and period phrases** are produced at render time through gettext
 *   and follow the active WordPress locale, like the rest of the document
 *   chrome. They are never persisted.
 *
 * Kind and period are labelling only: they never change money math.
 */
class Line_Item_Meta {

	/**
	 * Kind: physical goods.
	 *
	 * @var string
	 */
	public const KIND_GOODS = 'goods';

	/**
	 * Kind: service / jasa.
	 *
	 * @var string
	 */
	public const KIND_SERVICE = 'service';

	/**
	 * Kind: digital product.
	 *
	 * @var string
	 */
	public const KIND_DIGITAL = 'digital';

	/**
	 * Period: one-time charge.
	 *
	 * @var string
	 */
	public const PERIOD_ONE_TIME = 'one_time';

	/**
	 * Period: monthly billing.
	 *
	 * @var string
	 */
	public const PERIOD_MONTHLY = 'monthly';

	/**
	 * Period: quarterly billing.
	 *
	 * @var string
	 */
	public const PERIOD_QUARTERLY = 'quarterly';

	/**
	 * Period: yearly billing.
	 *
	 * @var string
	 */
	public const PERIOD_YEARLY = 'yearly';

	/**
	 * Period: custom length (uses period_count + period_unit).
	 *
	 * @var string
	 */
	public const PERIOD_CUSTOM = 'custom';

	/**
	 * Custom length unit: days.
	 *
	 * @var string
	 */
	public const UNIT_DAY = 'day';

	/**
	 * Custom length unit: weeks.
	 *
	 * @var string
	 */
	public const UNIT_WEEK = 'week';

	/**
	 * Custom length unit: months.
	 *
	 * @var string
	 */
	public const UNIT_MONTH = 'month';

	/**
	 * Custom length unit: years.
	 *
	 * @var string
	 */
	public const UNIT_YEAR = 'year';

	/**
	 * Kind slug → translated label.
	 *
	 * @return array<string, string>
	 */
	public static function kinds(): array {
		return array(
			self::KIND_GOODS   => __( 'Goods', 'wp-bizwit' ),
			self::KIND_SERVICE => __( 'Service', 'wp-bizwit' ),
			self::KIND_DIGITAL => __( 'Digital product', 'wp-bizwit' ),
		);
	}

	/**
	 * Period slug → translated label.
	 *
	 * @return array<string, string>
	 */
	public static function periods(): array {
		return array(
			self::PERIOD_ONE_TIME  => __( 'One-time', 'wp-bizwit' ),
			self::PERIOD_MONTHLY   => __( 'Monthly', 'wp-bizwit' ),
			self::PERIOD_QUARTERLY => __( 'Quarterly', 'wp-bizwit' ),
			self::PERIOD_YEARLY    => __( 'Yearly', 'wp-bizwit' ),
			self::PERIOD_CUSTOM    => __( 'Custom length', 'wp-bizwit' ),
		);
	}

	/**
	 * Custom length units: slug → canonical stored term + translated label.
	 *
	 * The canonical term is what `suggest_unit()` writes into the `unit`
	 * column (Indonesian domain vocabulary, never translated). The label is
	 * for the admin dropdown and may follow the user locale.
	 *
	 * @return array<string, array{canonical: string, label: string}>
	 */
	public static function period_units(): array {
		return array(
			self::UNIT_DAY   => array(
				'canonical' => 'hari',
				'label'     => __( 'Day', 'wp-bizwit' ),
			),
			self::UNIT_WEEK  => array(
				'canonical' => 'minggu',
				'label'     => __( 'Week', 'wp-bizwit' ),
			),
			self::UNIT_MONTH => array(
				'canonical' => 'bulan',
				'label'     => __( 'Month', 'wp-bizwit' ),
			),
			self::UNIT_YEAR  => array(
				'canonical' => 'tahun',
				'label'     => __( 'Year', 'wp-bizwit' ),
			),
		);
	}

	/**
	 * Canonical stored term for a period-unit slug ('' when unknown).
	 *
	 * @param string $slug Period unit slug (day/week/month/year).
	 *
	 * @return string Canonical satuan, e.g. 'bulan'.
	 */
	public static function canonical_period_unit( string $slug ): string {
		$units = self::period_units();

		return isset( $units[ $slug ] ) ? $units[ $slug ]['canonical'] : '';
	}

	/**
	 * Whitelist a kind slug; '' (unspecified) is valid.
	 *
	 * @param mixed $raw Raw value.
	 *
	 * @return string Valid kind slug or ''.
	 */
	public static function sanitize_kind( $raw ): string {
		$kind = sanitize_key( (string) $raw );

		return array_key_exists( $kind, self::kinds() ) ? $kind : '';
	}

	/**
	 * Whitelist a period slug; anything unknown becomes one_time.
	 *
	 * @param mixed $raw Raw value.
	 *
	 * @return string Valid period slug.
	 */
	public static function sanitize_period( $raw ): string {
		$period = sanitize_key( (string) $raw );

		return array_key_exists( $period, self::periods() ) ? $period : self::PERIOD_ONE_TIME;
	}

	/**
	 * Validate a custom length pair.
	 *
	 * @param mixed $count Raw count.
	 * @param mixed $unit  Raw unit slug.
	 *
	 * @return array{count: int, unit: string}
	 */
	public static function sanitize_custom_length( $count, $unit ): array {
		$count = max( 0, min( 999, (int) $count ) );
		$unit  = sanitize_key( (string) $unit );

		if ( ! array_key_exists( $unit, self::period_units() ) ) {
			$unit = '';
		}

		return array(
			'count' => $count,
			'unit'  => $unit,
		);
	}

	/**
	 * Apply the storage invariants to raw meta values.
	 *
	 * - one_time → count 0, unit ''.
	 * - custom → requires count >= 1 and a valid unit, else one_time.
	 * - other recurring periods ignore count/unit (normalised to 0 / '').
	 *
	 * @param array<string, mixed> $meta Raw item row or meta subset.
	 *
	 * @return array{item_kind: string, billing_period: string, period_count: int, period_unit: string}
	 */
	public static function normalize( array $meta ): array {
		$kind   = self::sanitize_kind( $meta['item_kind'] ?? '' );
		$period = self::sanitize_period( $meta['billing_period'] ?? self::PERIOD_ONE_TIME );
		$count  = 0;
		$unit   = '';

		if ( self::PERIOD_CUSTOM === $period ) {
			$length = self::sanitize_custom_length( $meta['period_count'] ?? 0, $meta['period_unit'] ?? '' );
			if ( $length['count'] >= 1 && '' !== $length['unit'] ) {
				$count = $length['count'];
				$unit  = $length['unit'];
			} else {
				// Incomplete custom length falls back to one_time.
				$period = self::PERIOD_ONE_TIME;
			}
		}

		return array(
			'item_kind'      => $kind,
			'billing_period' => $period,
			'period_count'   => $count,
			'period_unit'    => $unit,
		);
	}

	/**
	 * Column defaults for reads that predate the 1.7.0 migration.
	 *
	 * @return array{item_kind: string, billing_period: string, period_count: int, period_unit: string}
	 */
	public static function defaults(): array {
		return array(
			'item_kind'      => '',
			'billing_period' => self::PERIOD_ONE_TIME,
			'period_count'   => 0,
			'period_unit'    => '',
		);
	}

	/**
	 * Canonical satuan presets (stored data; never translated).
	 *
	 * @return string[]
	 */
	public static function canonical_units(): array {
		return array( 'pcs', 'jam', 'paket', 'satuan', 'hari', 'minggu', 'bulan', 'kuartal', 'tahun' );
	}

	/**
	 * Whether a satuan string is a known preset (case-insensitive).
	 *
	 * Empty counts as preset (safe to suggest into). Generated custom presets
	 * like `6 bulan` also count. Comparison runs against the canonical list
	 * only — never against translated strings.
	 *
	 * @param string $unit Satuan to test.
	 *
	 * @return bool True when the value is a preset the UI may overwrite.
	 */
	public static function is_preset_unit( string $unit ): bool {
		$unit = strtolower( trim( $unit ) );

		if ( '' === $unit ) {
			return true;
		}

		if ( in_array( $unit, self::canonical_units(), true ) ) {
			return true;
		}

		foreach ( self::period_units() as $def ) {
			if ( preg_match( '/^[1-9][0-9]{0,2} ' . preg_quote( $def['canonical'], '/' ) . '$/', $unit ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Suggest a satuan for a billing period.
	 *
	 * Returns '' for one_time and incomplete custom lengths, meaning "leave
	 * the current unit alone". Plain canonical forms, no '/' prefix, so the
	 * satuan reads correctly next to any quantity ("3 bulan").
	 *
	 * @param string $period Billing period slug.
	 * @param int    $count  Custom length count (custom only).
	 * @param string $unit   Custom length unit slug (custom only).
	 *
	 * @return string Suggested satuan or ''.
	 */
	public static function suggest_unit( string $period, int $count = 0, string $unit = '' ): string {
		switch ( self::sanitize_period( $period ) ) {
			case self::PERIOD_MONTHLY:
				return 'bulan';
			case self::PERIOD_QUARTERLY:
				return 'kuartal';
			case self::PERIOD_YEARLY:
				return 'tahun';
			case self::PERIOD_CUSTOM:
				$canonical = self::canonical_period_unit( $unit );
				if ( $count >= 1 && '' !== $canonical ) {
					return $count . ' ' . $canonical;
				}
				return '';
			default:
				return '';
		}
	}

	/**
	 * Satuan to print: the stored unit, else the derived period preset.
	 *
	 * @param array<string, mixed> $item Item row.
	 *
	 * @return string Satuan for display (may be '').
	 */
	public static function display_unit( array $item ): string {
		$stored = trim( (string) ( $item['unit'] ?? '' ) );

		if ( '' !== $stored ) {
			return $stored;
		}

		return self::suggest_unit(
			(string) ( $item['billing_period'] ?? '' ),
			(int) ( $item['period_count'] ?? 0 ),
			(string) ( $item['period_unit'] ?? '' )
		);
	}

	/**
	 * Translated label for a kind slug ('' when empty/unknown).
	 *
	 * @param string $kind Kind slug.
	 *
	 * @return string Label or ''.
	 */
	public static function format_kind_label( string $kind ): string {
		return self::kinds()[ $kind ] ?? '';
	}

	/**
	 * Human period phrase for the description subline (render-time gettext).
	 *
	 * @param array<string, mixed> $item Item row.
	 *
	 * @return string Phrase ('' for one_time or incomplete custom).
	 */
	public static function format_period_label( array $item ): string {
		$period = self::sanitize_period( $item['billing_period'] ?? '' );

		switch ( $period ) {
			case self::PERIOD_MONTHLY:
				return __( 'billed monthly', 'wp-bizwit' );
			case self::PERIOD_QUARTERLY:
				return __( 'billed quarterly', 'wp-bizwit' );
			case self::PERIOD_YEARLY:
				return __( 'billed yearly', 'wp-bizwit' );
			case self::PERIOD_CUSTOM:
				$count = (int) ( $item['period_count'] ?? 0 );
				$unit  = (string) ( $item['period_unit'] ?? '' );

				if ( $count < 1 || ! array_key_exists( $unit, self::period_units() ) ) {
					return '';
				}

				switch ( $unit ) {
					case self::UNIT_DAY:
						/* translators: %d: number of days */
						return sprintf( _n( '%d day', '%d days', $count, 'wp-bizwit' ), $count );
					case self::UNIT_WEEK:
						/* translators: %d: number of weeks */
						return sprintf( _n( '%d week', '%d weeks', $count, 'wp-bizwit' ), $count );
					case self::UNIT_MONTH:
						/* translators: %d: number of months */
						return sprintf( _n( '%d month', '%d months', $count, 'wp-bizwit' ), $count );
					default:
						/* translators: %d: number of years */
						return sprintf( _n( '%d year', '%d years', $count, 'wp-bizwit' ), $count );
				}
		}

		return '';
	}

	/**
	 * The `{Kind} · {period}` subline, or '' when nothing is worth saying.
	 *
	 * Shown when a kind is set and/or the period is not one_time; bare
	 * one_time lines keep the legacy single-line look.
	 *
	 * @param array<string, mixed> $item Item row.
	 *
	 * @return string Subline text (unescaped).
	 */
	public static function subline( array $item ): string {
		$parts = array();

		$kind = self::format_kind_label( self::sanitize_kind( $item['item_kind'] ?? '' ) );
		if ( '' !== $kind ) {
			$parts[] = $kind;
		}

		$period = self::format_period_label( $item );
		if ( '' !== $period ) {
			$parts[] = $period;
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Description cell HTML: escaped description + optional muted subline.
	 *
	 * Safe to echo — everything is escaped here.
	 *
	 * @param array<string, mixed> $item Item row.
	 *
	 * @return string HTML.
	 */
	public static function enrich_description_html( array $item ): string {
		$html = esc_html( (string) ( $item['description'] ?? '' ) );
		$sub  = self::subline( $item );

		if ( '' !== $sub ) {
			$html .= '<span class="wp-bizwit-line-sub">' . esc_html( $sub ) . '</span>';
		}

		return $html;
	}
}
