<?php
/**
 * Region registry and resolution.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Localization;

use WP_BizWit\Support\Settings;

/**
 * Resolves which regional profile is active.
 *
 * Indonesia is the default. The plugin is built for Indonesian companies and
 * UMKM first, so a fresh install should already speak the right vocabulary
 * without anyone having to find a setting.
 */
class Regions {

	/**
	 * Setting value meaning "work it out from the business details".
	 *
	 * @var string
	 */
	public const AUTO = 'auto';

	/**
	 * Cached instance of the active region.
	 *
	 * @var Region|null
	 */
	private static ?Region $current = null;

	/**
	 * Every selectable region, keyed by its code.
	 *
	 * @return array<string, Region> Region code mapped to instance.
	 */
	public static function all(): array {
		return array(
			'id'      => new Indonesia(),
			'generic' => new Generic_Region(),
		);
	}

	/**
	 * Region choices for a settings dropdown, including the automatic option.
	 *
	 * @return array<string, string> Setting value mapped to label.
	 */
	public static function choices(): array {
		$choices = array( self::AUTO => __( 'Detect from business details', 'wp-bizwit' ) );

		foreach ( self::all() as $code => $region ) {
			$choices[ $code ] = $region->label();
		}

		return $choices;
	}

	/**
	 * The active regional profile.
	 *
	 * @return Region Active region.
	 */
	public static function current(): Region {
		if ( null !== self::$current ) {
			return self::$current;
		}

		$configured = (string) Settings::get( 'region', self::AUTO );
		$available  = self::all();

		if ( isset( $available[ $configured ] ) ) {
			self::$current = $available[ $configured ];

			return self::$current;
		}

		self::$current = self::detect();

		return self::$current;
	}

	/**
	 * Forget the cached region.
	 *
	 * Called after settings are saved, and by tests that change the profile.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$current = null;
	}

	/**
	 * Work out the region from the business's own details.
	 *
	 * Either an Indonesian address or rupiah billing is treated as enough
	 * evidence: a business invoicing in IDR is doing Indonesian paperwork
	 * whatever address it put in settings.
	 *
	 * @return Region Detected region.
	 */
	private static function detect(): Region {
		$country  = strtoupper( (string) Settings::get( 'business_country', '' ) );
		$currency = strtoupper( (string) Settings::get( 'currency', '' ) );

		if ( 'ID' === $country || 'IDR' === $currency ) {
			return new Indonesia();
		}

		if ( '' === $country && '' === $currency ) {
			return new Indonesia();
		}

		return new Generic_Region();
	}
}
