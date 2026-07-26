<?php
/**
 * Payments admin screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Support\Capabilities;

/**
 * Payment records and receipts. Schema is in place; the UI lands in a later pass.
 */
class Payments_Screen extends Placeholder_Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-payments';

	/**
	 * Capability required to use this screen.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return Capabilities::MANAGE_PAYMENTS;
	}

	/**
	 * Heading shown at the top of the screen.
	 *
	 * @return string Translated heading.
	 */
	protected function heading(): string {
		return __( 'Payments', 'wp-bizwit' );
	}

	/**
	 * Sentence describing what this screen will do.
	 *
	 * @return string Translated description.
	 */
	protected function description(): string {
		return __( 'Record payments that have already been received elsewhere, and issue the matching receipt.', 'wp-bizwit' );
	}

	/**
	 * Bullet points describing the planned functionality.
	 *
	 * @return string[] Translated list items.
	 */
	protected function planned(): array {
		return array(
			__( 'Payments recorded against an invoice, including partial payments.', 'wp-bizwit' ),
			__( 'Sequential receipt numbering, independent of invoice numbering.', 'wp-bizwit' ),
			__( 'Payment method and external reference, for reconciliation against a bank statement.', 'wp-bizwit' ),
			__( 'This plugin never processes or moves money. It records payments that happened elsewhere.', 'wp-bizwit' ),
		);
	}
}
