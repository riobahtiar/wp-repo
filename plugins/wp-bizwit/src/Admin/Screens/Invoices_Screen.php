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
				__( 'VAT (PPN) only appears when your business is registered as PKP.', 'wp-bizwit' ),
				__( 'Record PPh 23 withholding so invoice totals and bank credits can still be reconciled.', 'wp-bizwit' ),
				__( 'Staged billing: down payment, termin stages, and retention.', 'wp-bizwit' ),
				__( 'PO, SPK and BAST reference numbers on invoices.', 'wp-bizwit' ),
			)
		);
	}
}
