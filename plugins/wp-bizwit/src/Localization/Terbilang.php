<?php
/**
 * Converts numbers into Indonesian words.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Localization;

/**
 * Spells out a number in Indonesian ("terbilang").
 *
 * Indonesian invoices and especially kwitansi carry the amount written out in
 * words as well as in figures. It is not decoration: the written form is what
 * makes a receipt hard to alter after the fact, which is why it is expected on
 * any document a client signs.
 *
 * The grammar has a few irregular forms that a naive digit-by-digit conversion
 * gets wrong: 11 is "sebelas" rather than "satu belas", and a leading single
 * unit contracts to "se-" ("seratus", "seribu", "sepuluh") rather than "satu
 * ratus" or "satu ribu".
 */
class Terbilang {

	/**
	 * Words for the numbers zero to eleven.
	 *
	 * @var string[]
	 */
	private const UNITS = array(
		'nol',
		'satu',
		'dua',
		'tiga',
		'empat',
		'lima',
		'enam',
		'tujuh',
		'delapan',
		'sembilan',
		'sepuluh',
		'sebelas',
	);

	/**
	 * Scale names, ordered from the largest supported scale downwards.
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private const SCALES = array(
		array( 1000000000000, 'triliun' ),
		array( 1000000000, 'miliar' ),
		array( 1000000, 'juta' ),
		array( 1000, 'ribu' ),
	);

	/**
	 * Spell out a whole number in Indonesian.
	 *
	 * @param int $number Number to convert. Negative values are prefixed with 'minus'.
	 *
	 * @return string The number in words, lowercase.
	 */
	public static function convert( int $number ): string {
		if ( $number < 0 ) {
			return 'minus ' . self::convert( abs( $number ) );
		}

		if ( $number < 12 ) {
			return self::UNITS[ $number ];
		}

		if ( $number < 20 ) {
			return self::UNITS[ $number - 10 ] . ' belas';
		}

		if ( $number < 100 ) {
			return trim( self::UNITS[ intdiv( $number, 10 ) ] . ' puluh ' . self::remainder( $number % 10 ) );
		}

		if ( $number < 1000 ) {
			// 100-199 contracts to "seratus" rather than "satu ratus".
			$hundreds = $number < 200 ? 'seratus' : self::UNITS[ intdiv( $number, 100 ) ] . ' ratus';

			return trim( $hundreds . ' ' . self::remainder( $number % 100 ) );
		}

		foreach ( self::SCALES as $scale ) {
			list( $value, $name ) = $scale;

			if ( $number < $value ) {
				continue;
			}

			// The thousands scale contracts the same way: "seribu", not "satu ribu".
			$count = intdiv( $number, $value );
			$head  = ( 1000 === $value && 1 === $count ) ? 'seribu' : self::convert( $count ) . ' ' . $name;

			return trim( $head . ' ' . self::remainder( $number % $value ) );
		}

		return self::UNITS[0];
	}

	/**
	 * Spell out a rupiah amount as it would appear on a kwitansi.
	 *
	 * @param int $rupiah Whole rupiah.
	 *
	 * @return string Amount in words, sentence case, ending in 'rupiah'.
	 */
	public static function rupiah( int $rupiah ): string {
		$words = self::convert( $rupiah ) . ' rupiah';

		return self::sentence_case( $words );
	}

	/**
	 * Spell out an amount with a fractional part, for currencies that have one.
	 *
	 * @param int    $whole    Whole units.
	 * @param int    $fraction Fractional units.
	 * @param string $unit     Name of the whole unit, for example 'rupiah'.
	 * @param string $subunit  Name of the fractional unit, for example 'sen'.
	 *
	 * @return string Amount in words, sentence case.
	 */
	public static function amount( int $whole, int $fraction, string $unit, string $subunit ): string {
		$words = self::convert( $whole ) . ' ' . $unit;

		if ( $fraction > 0 ) {
			$words .= ' ' . self::convert( $fraction ) . ' ' . $subunit;
		}

		return self::sentence_case( $words );
	}

	/**
	 * Convert the trailing part of a number, or '' when there is none.
	 *
	 * @param int $number Remainder to convert.
	 *
	 * @return string The remainder in words, or ''.
	 */
	private static function remainder( int $number ): string {
		return 0 === $number ? '' : self::convert( $number );
	}

	/**
	 * Capitalise the first letter and collapse repeated spaces.
	 *
	 * @param string $words Words to tidy.
	 *
	 * @return string Sentence-cased string.
	 */
	private static function sentence_case( string $words ): string {
		$words = (string) preg_replace( '/\s+/', ' ', trim( $words ) );

		return ucfirst( $words );
	}
}
