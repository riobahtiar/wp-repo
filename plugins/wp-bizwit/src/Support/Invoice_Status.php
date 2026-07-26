<?php
/**
 * Invoice status labels and transition rules.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

/**
 * Status machine for invoices.
 *
 * Draft is freely editable. After send, content is locked; only status (and
 * notes via explicit flows) may change. Void is terminal and preserves the
 * number for auditability.
 */
class Invoice_Status {

	public const DRAFT   = 'draft';
	public const SENT    = 'sent';
	public const PARTIAL = 'partial';
	public const PAID    = 'paid';
	public const OVERDUE = 'overdue';
	public const VOID    = 'void';

	/**
	 * All statuses with translated labels.
	 *
	 * @return array<string, string> Status slug mapped to label.
	 */
	public static function labels(): array {
		return array(
			self::DRAFT   => __( 'Draft', 'wp-bizwit' ),
			self::SENT    => __( 'Sent', 'wp-bizwit' ),
			self::PARTIAL => __( 'Partially paid', 'wp-bizwit' ),
			self::PAID    => __( 'Paid', 'wp-bizwit' ),
			self::OVERDUE => __( 'Overdue', 'wp-bizwit' ),
			self::VOID    => __( 'Void', 'wp-bizwit' ),
		);
	}

	/**
	 * Whether the slug is a known status.
	 *
	 * @param string $status Status slug.
	 *
	 * @return bool True when known.
	 */
	public static function is_valid( string $status ): bool {
		return array_key_exists( $status, self::labels() );
	}

	/**
	 * Whether line items and money fields may still be edited.
	 *
	 * @param string $status Current status.
	 *
	 * @return bool True only for drafts.
	 */
	public static function is_editable( string $status ): bool {
		return self::DRAFT === $status;
	}

	/**
	 * Whether the invoice number has been issued to the outside world.
	 *
	 * @param string $status Current status.
	 *
	 * @return bool True when the number must be preserved.
	 */
	public static function has_issued_number( string $status ): bool {
		return self::DRAFT !== $status;
	}

	/**
	 * Allowed next statuses from the current one (manual transitions).
	 *
	 * Overdue is also set by a scheduled task; paid/partial are refined when
	 * payments land (0.6.0). Void is always available except from void itself.
	 *
	 * @param string $from Current status.
	 *
	 * @return string[] Allowed target statuses.
	 */
	public static function allowed_transitions( string $from ): array {
		$map = array(
			self::DRAFT   => array( self::SENT, self::VOID ),
			self::SENT    => array( self::PARTIAL, self::PAID, self::OVERDUE, self::VOID ),
			self::PARTIAL => array( self::PAID, self::OVERDUE, self::VOID ),
			self::PAID    => array( self::VOID ),
			self::OVERDUE => array( self::PARTIAL, self::PAID, self::VOID ),
			self::VOID    => array(),
		);

		return $map[ $from ] ?? array();
	}

	/**
	 * Whether moving from one status to another is allowed.
	 *
	 * @param string $from Current status.
	 * @param string $to   Desired status.
	 *
	 * @return bool True when allowed.
	 */
	public static function can_transition( string $from, string $to ): bool {
		if ( $from === $to ) {
			return true;
		}

		return in_array( $to, self::allowed_transitions( $from ), true );
	}

	/**
	 * Infer a payment-related status from amounts (used when payments update).
	 *
	 * @param int    $total_minor Invoice total.
	 * @param int    $paid_minor  Amount paid.
	 * @param string $current     Current status (preserved if void/draft).
	 *
	 * @return string Suggested status.
	 */
	public static function from_payment_amounts( int $total_minor, int $paid_minor, string $current ): string {
		if ( self::VOID === $current || self::DRAFT === $current ) {
			return $current;
		}

		if ( $paid_minor <= 0 ) {
			return in_array( $current, array( self::OVERDUE, self::SENT ), true ) ? $current : self::SENT;
		}

		if ( $paid_minor >= $total_minor && $total_minor > 0 ) {
			return self::PAID;
		}

		return self::PARTIAL;
	}
}
