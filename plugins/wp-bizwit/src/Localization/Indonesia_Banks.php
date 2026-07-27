<?php
/**
 * Indonesian bank catalogue (vendored from data-bank-indonesia).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Localization;

/**
 * Loads bank transfer codes and names for payment destination UI.
 *
 * Source: https://github.com/riobahtiar/data-bank-indonesia (MIT)
 * Dataset is bundled under data/indonesia-banks.json — no runtime HTTP.
 */
class Indonesia_Banks {

	/**
	 * Relative path from plugin root.
	 *
	 * @var string
	 */
	public const DATA_FILE = 'data/indonesia-banks.json';

	/**
	 * Upstream project URL (attribution).
	 *
	 * @var string
	 */
	public const SOURCE_URL = 'https://github.com/riobahtiar/data-bank-indonesia';

	/**
	 * Cached catalogue.
	 *
	 * @var array<int, array{code: string, name: string, type: string, is_digital: bool, is_islamic: bool}>|null
	 */
	private static ?array $banks = null;

	/**
	 * Human labels for bank type groups (select optgroups).
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return array(
			'state'    => __( 'State banks (BUMN)', 'wp-bizwit' ),
			'private'  => __( 'Private banks', 'wp-bizwit' ),
			'regional' => __( 'Regional banks (BPD)', 'wp-bizwit' ),
			'islamic'  => __( 'Islamic banks (syariah)', 'wp-bizwit' ),
			'digital'  => __( 'Digital banks', 'wp-bizwit' ),
			'foreign'  => __( 'Foreign banks', 'wp-bizwit' ),
			'emoney'   => __( 'E-money / other', 'wp-bizwit' ),
			'bpr'      => __( 'Rural banks (BPR)', 'wp-bizwit' ),
		);
	}

	/**
	 * Full bank list, sorted for selects (state → private → … → name).
	 *
	 * @return array<int, array{code: string, name: string, type: string, is_digital: bool, is_islamic: bool}>
	 */
	public static function all(): array {
		if ( null !== self::$banks ) {
			return self::$banks;
		}

		$path = WP_BIZWIT_PATH . self::DATA_FILE;
		if ( ! is_readable( $path ) ) {
			self::$banks = array();
			return self::$banks;
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin data file.
		if ( false === $raw || '' === $raw ) {
			self::$banks = array();
			return self::$banks;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			self::$banks = array();
			return self::$banks;
		}

		$type_order = array_flip( array_keys( self::type_labels() ) );
		$banks      = array();

		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$code = isset( $row['code'] ) ? preg_replace( '/\D/', '', (string) $row['code'] ) : '';
			$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			if ( '' === $code || '' === $name ) {
				continue;
			}
			// Normalise to 3-digit transfer codes when numeric.
			if ( strlen( $code ) < 3 ) {
				$code = str_pad( $code, 3, '0', STR_PAD_LEFT );
			}

			$type = sanitize_key( (string) ( $row['type'] ?? 'private' ) );
			if ( ! array_key_exists( $type, $type_order ) ) {
				$type = 'private';
			}

			$banks[] = array(
				'code'       => $code,
				'name'       => $name,
				'type'       => $type,
				'is_digital' => ! empty( $row['is_digital'] ),
				'is_islamic' => ! empty( $row['is_islamic'] ),
			);
		}

		usort(
			$banks,
			static function ( array $a, array $b ) use ( $type_order ): int {
				$ta = $type_order[ $a['type'] ] ?? 99;
				$tb = $type_order[ $b['type'] ] ?? 99;
				if ( $ta !== $tb ) {
					return $ta <=> $tb;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		self::$banks = $banks;
		return self::$banks;
	}

	/**
	 * Banks grouped by type for &lt;optgroup&gt; rendering.
	 *
	 * @return array<string, array<int, array{code: string, name: string, type: string, is_digital: bool, is_islamic: bool}>>
	 */
	public static function grouped(): array {
		$groups = array();
		foreach ( array_keys( self::type_labels() ) as $type ) {
			$groups[ $type ] = array();
		}
		foreach ( self::all() as $bank ) {
			$groups[ $bank['type'] ][] = $bank;
		}
		// Drop empty groups.
		return array_filter(
			$groups,
			static function ( array $banks_in_group ): bool {
				return array() !== $banks_in_group;
			}
		);
	}

	/**
	 * Find one bank by transfer code.
	 *
	 * @param string $code Bank code.
	 *
	 * @return array{code: string, name: string, type: string, is_digital: bool, is_islamic: bool}|null
	 */
	public static function find_by_code( string $code ): ?array {
		$code = preg_replace( '/\D/', '', $code ) ?? '';
		if ( '' === $code ) {
			return null;
		}
		if ( strlen( $code ) < 3 ) {
			$code = str_pad( $code, 3, '0', STR_PAD_LEFT );
		}
		foreach ( self::all() as $bank ) {
			if ( $bank['code'] === $code ) {
				return $bank;
			}
		}
		return null;
	}

	/**
	 * Match a free-text bank name to a catalogue entry (legacy migration).
	 *
	 * @param string $name Stored free-text name.
	 *
	 * @return array{code: string, name: string, type: string, is_digital: bool, is_islamic: bool}|null
	 */
	public static function find_by_name( string $name ): ?array {
		$name = trim( $name );
		if ( '' === $name ) {
			return null;
		}
		$needle = strtolower( $name );
		foreach ( self::all() as $bank ) {
			if ( strtolower( $bank['name'] ) === $needle ) {
				return $bank;
			}
		}
		// Loose match: "BCA" inside "Bank Central Asia (BCA)".
		foreach ( self::all() as $bank ) {
			if ( false !== stripos( $bank['name'], $name ) || false !== stripos( $name, $bank['name'] ) ) {
				return $bank;
			}
		}
		// Abbreviation in parentheses.
		foreach ( self::all() as $bank ) {
			if ( preg_match( '/\(([^)]+)\)/', $bank['name'], $m ) ) {
				$abbr = strtolower( trim( $m[1] ) );
				if ( $abbr === $needle || false !== stripos( $needle, $abbr ) ) {
					return $bank;
				}
			}
		}
		return null;
	}

	/**
	 * Display label for documents: "Bank Name (kode 014)".
	 *
	 * @param string $code Bank code.
	 * @param string $fallback_name Free-text name if code unknown.
	 *
	 * @return string
	 */
	public static function display_name( string $code, string $fallback_name = '' ): string {
		$bank = self::find_by_code( $code );
		if ( null !== $bank ) {
			return sprintf(
				/* translators: 1: bank name, 2: 3-digit transfer code */
				__( '%1$s (code %2$s)', 'wp-bizwit' ),
				$bank['name'],
				$bank['code']
			);
		}
		return '' !== trim( $fallback_name ) ? $fallback_name : $code;
	}

	/**
	 * How many banks are loaded.
	 *
	 * @return int
	 */
	public static function count(): int {
		return count( self::all() );
	}

	/**
	 * Reset cache (tests).
	 *
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$banks = null;
	}

	/**
	 * Render a bank &lt;select&gt; with optgroups from the catalogue.
	 *
	 * @param string $name          Input name attribute.
	 * @param string $id            Input id attribute.
	 * @param string $selected_code Currently selected bank code.
	 * @param string $fallback_name Free-text name when code empty (legacy).
	 *
	 * @return string HTML.
	 */
	public static function render_select( string $name, string $id, string $selected_code = '', string $fallback_name = '' ): string {
		if ( '' === $selected_code && '' !== $fallback_name ) {
			$match = self::find_by_name( $fallback_name );
			if ( null !== $match ) {
				$selected_code = $match['code'];
			}
		}

		$labels = self::type_labels();
		$html   = sprintf(
			'<select class="wp-bizwit-pay-card__select wp-bizwit-bank-select" name="%1$s" id="%2$s" data-bank-select data-placeholder="%3$s">',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr__( 'Search by bank name or transfer code…', 'wp-bizwit' )
		);
		// Empty option: short label for the closed control.
		$html .= '<option value="">' . esc_html__( 'Select a bank…', 'wp-bizwit' ) . '</option>';

		foreach ( self::grouped() as $type => $banks ) {
			$group_label = $labels[ $type ] ?? $type;
			$html       .= '<optgroup label="' . esc_attr( $group_label ) . '">';
			foreach ( $banks as $bank ) {
				// Text includes code for search; Tom Select renders name + code badge separately.
				$search_text = $bank['name'] . ' ' . $bank['code'];
				$html       .= sprintf(
					'<option value="%1$s" data-code="%1$s" data-bank-name="%2$s"%3$s>%4$s</option>',
					esc_attr( $bank['code'] ),
					esc_attr( $bank['name'] ),
					selected( $selected_code, $bank['code'], false ),
					esc_html( $search_text )
				);
			}
			$html .= '</optgroup>';
		}

		// Custom free-text bank not in the list.
		$custom = ( '' === $selected_code && '' !== trim( $fallback_name ) );
		$html  .= '<optgroup label="' . esc_attr__( 'Not in list', 'wp-bizwit' ) . '">';
		$html  .= sprintf(
			'<option value="__custom__" data-code="" data-bank-name=""%s>%s</option>',
			selected( $custom, true, false ),
			esc_html__( 'Other bank — type the name below', 'wp-bizwit' )
		);
		$html  .= '</optgroup>';
		$html  .= '</select>';

		return $html;
	}
}
