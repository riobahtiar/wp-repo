<?php
/**
 * Invoices admin screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Capabilities;

/**
 * Invoicing. Schema is in place; the UI lands in a later pass.
 */
class Invoices_Screen extends Placeholder_Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-invoices';

	/**
	 * Capability required to use this screen.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return Capabilities::MANAGE_INVOICES;
	}

	/**
	 * Heading shown at the top of the screen.
	 *
	 * @return string Translated heading.
	 */
	protected function heading(): string {
		return __( 'Invoices', 'wp-bizwit' );
	}

	/**
	 * Sentence describing what this screen will do.
	 *
	 * @return string Translated description.
	 */
	protected function description(): string {
		return __( 'Issue invoices against a client or project, and keep a record of what has been billed.', 'wp-bizwit' );
	}

	/**
	 * Bullet points describing the planned functionality.
	 *
	 * @return string[] Translated list items.
	 */
	protected function planned(): array {
		$planned = array(
			__( 'Line items with quantity, unit price, per-line tax and discounts.', 'wp-bizwit' ),
			__( 'Race-free invoice numbering in your chosen format.', 'wp-bizwit' ),
			__( 'Draft, sent, partially paid, paid, overdue and void statuses.', 'wp-bizwit' ),
			__( 'Print-ready invoice output, with the running balance shown against payments.', 'wp-bizwit' ),
		);

		if ( ! Regions::current() instanceof Indonesia ) {
			return $planned;
		}

		return array_merge(
			$planned,
			array(
				__( 'PPN hanya dicantumkan bila usaha Anda berstatus PKP.', 'wp-bizwit' ),
				__( 'Pencatatan potongan PPh 23, sehingga nilai faktur dan dana yang diterima di rekening tetap dapat direkonsiliasi.', 'wp-bizwit' ),
				__( 'Penagihan bertahap: uang muka, termin, dan retensi.', 'wp-bizwit' ),
				__( 'Rujukan nomor PO, SPK dan BAST pada faktur.', 'wp-bizwit' ),
			)
		);
	}
}
