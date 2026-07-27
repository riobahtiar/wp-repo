<?php
/**
 * Multiple payment destinations shown on invoices (and stored in settings).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

use WP_BizWit\Localization\Indonesia_Banks;

/**
 * Catalogue, sanitise, migrate and render payment destinations.
 *
 * BizWit only records where clients may pay — it never initiates a payment.
 * Destinations are independent of the payment *method* slug recorded on a
 * payment row (how the money arrived).
 */
class Payment_Destinations {

	/**
	 * Max destinations a business may configure.
	 *
	 * @var int
	 */
	public const MAX = 20;

	/**
	 * Destination type slugs.
	 */
	public const TYPE_BANK_TRANSFER   = 'bank_transfer';
	public const TYPE_VIRTUAL_ACCOUNT = 'virtual_account';
	public const TYPE_PAYMENT_LINK    = 'payment_link';
	public const TYPE_DANA            = 'ewallet_dana';
	public const TYPE_GOPAY           = 'ewallet_gopay';
	public const TYPE_OVO             = 'ewallet_ovo';
	public const TYPE_SHOPEEPAY       = 'ewallet_shopeepay';
	public const TYPE_OFFLINE         = 'offline';
	public const TYPE_OTHER           = 'other';

	/**
	 * Type catalogue for the settings UI and print labels.
	 *
	 * @return array<string, string> Type slug => label.
	 */
	public static function types(): array {
		return array(
			self::TYPE_BANK_TRANSFER   => __( 'Bank transfer', 'wp-bizwit' ),
			self::TYPE_VIRTUAL_ACCOUNT => __( 'Virtual Account', 'wp-bizwit' ),
			self::TYPE_PAYMENT_LINK    => __( 'Payment link', 'wp-bizwit' ),
			self::TYPE_DANA            => __( 'DANA', 'wp-bizwit' ),
			self::TYPE_GOPAY           => __( 'GoPay', 'wp-bizwit' ),
			self::TYPE_OVO             => __( 'OVO', 'wp-bizwit' ),
			self::TYPE_SHOPEEPAY       => __( 'ShopeePay', 'wp-bizwit' ),
			self::TYPE_OFFLINE         => __( 'Offline payment', 'wp-bizwit' ),
			self::TYPE_OTHER           => __( 'Other', 'wp-bizwit' ),
		);
	}

	/**
	 * Empty destination skeleton.
	 *
	 * @return array<string, mixed>
	 */
	public static function empty_destination(): array {
		return array(
			'id'           => '',
			'type'         => self::TYPE_BANK_TRANSFER,
			'label'        => '',
			'enabled'      => true,
			'bank_code'    => '',
			'bank_name'    => '',
			'account_no'   => '',
			'account_name' => '',
			'branch'       => '',
			'va_bank_code' => '',
			'va_bank'      => '',
			'va_number'    => '',
			'ewallet_id'   => '',
			'url'          => '',
			'notes'        => '',
		);
	}

	/**
	 * All configured destinations (enabled and disabled), after migration.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$raw = Settings::get( 'payment_destinations', null );

		if ( is_array( $raw ) && array() !== $raw ) {
			$out = array();
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean = self::sanitize_one( $row );
				if ( null !== $clean ) {
					$out[] = $clean;
				}
			}
			if ( array() !== $out ) {
				return $out;
			}
		}

		return self::from_legacy_bank_fields();
	}

	/**
	 * Enabled destinations that have enough data to show on a document.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function enabled(): array {
		$out = array();
		foreach ( self::all() as $row ) {
			if ( empty( $row['enabled'] ) ) {
				continue;
			}
			if ( ! self::is_displayable( $row ) ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Whether any payment destination is configured for invoices.
	 *
	 * @return bool
	 */
	public static function has_any(): bool {
		return array() !== self::enabled();
	}

	/**
	 * Short summary for the settings accordion hint.
	 *
	 * @return string
	 */
	public static function summary(): string {
		$enabled = self::enabled();
		if ( array() === $enabled ) {
			return __( 'Not set yet', 'wp-bizwit' );
		}

		$types  = self::types();
		$labels = array();
		foreach ( $enabled as $row ) {
			$type     = (string) ( $row['type'] ?? '' );
			$custom   = trim( (string) ( $row['label'] ?? '' ) );
			$labels[] = '' !== $custom ? $custom : ( $types[ $type ] ?? $type );
		}

		$count = count( $labels );
		if ( 1 === $count ) {
			return $labels[0];
		}

		return sprintf(
			/* translators: 1: number of payment methods, 2: first method label */
			_n( '%1$d method · %2$s', '%1$d methods · %2$s', $count, 'wp-bizwit' ),
			$count,
			$labels[0]
		);
	}

	/**
	 * Sanitize a list from settings form POST.
	 *
	 * @param mixed $raw Posted array or null.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( count( $out ) >= self::MAX ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = self::sanitize_one( $row );
			if ( null === $clean ) {
				continue;
			}
			// Drop completely empty rows (add-slot placeholders).
			if ( ! self::is_displayable( $clean ) && '' === trim( (string) $clean['label'] ) && '' === trim( (string) $clean['notes'] ) ) {
				// Keep if user explicitly typed something; otherwise skip blanks.
				if ( self::is_blank( $clean ) ) {
					continue;
				}
			}
			if ( self::is_blank( $clean ) ) {
				continue;
			}
			$out[] = $clean;
		}

		return $out;
	}

	/**
	 * Sync first bank-transfer destination into legacy bank_* settings keys.
	 *
	 * Keeps older code paths and prints working until fully migrated.
	 *
	 * @param array<int, array<string, mixed>> $destinations Destinations.
	 *
	 * @return array<string, string> Legacy keys to merge into settings.
	 */
	public static function legacy_bank_fields_from( array $destinations ): array {
		foreach ( $destinations as $row ) {
			if ( self::TYPE_BANK_TRANSFER !== (string) ( $row['type'] ?? '' ) ) {
				continue;
			}
			if ( empty( $row['enabled'] ) ) {
				continue;
			}
			return array(
				'bank_name'         => (string) ( $row['bank_name'] ?? '' ),
				'bank_account_no'   => (string) ( $row['account_no'] ?? '' ),
				'bank_account_name' => (string) ( $row['account_name'] ?? '' ),
				'bank_branch'       => (string) ( $row['branch'] ?? '' ),
			);
		}

		// No bank transfer — clear legacy so documents don't show stale single bank.
		return array(
			'bank_name'         => '',
			'bank_account_no'   => '',
			'bank_account_name' => '',
			'bank_branch'       => '',
		);
	}

	/**
	 * HTML for invoice / template merge field `bank_block`.
	 *
	 * Structured label/value rows so print templates read like formal
	 * Indonesian paperwork rather than a plain text dump.
	 *
	 * @return string Escaped HTML fragment.
	 */
	public static function block_html(): string {
		$rows = self::enabled();
		if ( array() === $rows ) {
			return '';
		}

		$types = self::types();
		$parts = array();

		foreach ( $rows as $row ) {
			$type  = (string) ( $row['type'] ?? self::TYPE_OTHER );
			$title = trim( (string) ( $row['label'] ?? '' ) );
			if ( '' === $title ) {
				$title = $types[ $type ] ?? $type;
			}

			$detail_rows = self::detail_rows( $row );
			$notes       = trim( (string) ( $row['notes'] ?? '' ) );
			if ( array() === $detail_rows && '' === $notes ) {
				continue;
			}

			$inner  = '<div class="wp-bizwit-pay-method__head">';
			$inner .= '<span class="wp-bizwit-pay-method__title">' . esc_html( $title ) . '</span>';
			$inner .= '</div>';

			if ( array() !== $detail_rows ) {
				$inner .= '<dl class="wp-bizwit-pay-method__rows">';
				foreach ( $detail_rows as $detail ) {
					$kind    = (string) ( $detail['kind'] ?? '' );
					$row_cls = 'wp-bizwit-pay-method__row';
					if ( '' !== $kind ) {
						$row_cls .= ' wp-bizwit-pay-method__row--' . sanitize_html_class( $kind );
					}
					$inner .= '<div class="' . esc_attr( $row_cls ) . '">';
					$inner .= '<dt>' . esc_html( (string) $detail['label'] ) . '</dt>';
					$inner .= '<dd>' . esc_html( (string) $detail['value'] ) . '</dd>';
					$inner .= '</div>';
				}
				$inner .= '</dl>';
			}

			if ( '' !== $notes ) {
				$inner .= '<div class="wp-bizwit-pay-method__notes">' . esc_html( $notes ) . '</div>';
			}

			$parts[] = '<div class="wp-bizwit-pay-method" data-type="' . esc_attr( $type ) . '">' . $inner . '</div>';
		}

		if ( array() === $parts ) {
			return '';
		}

		return '<div class="wp-bizwit-pay-methods">' . implode( '', $parts ) . '</div>';
	}

	/**
	 * Labelled detail rows for one destination (unescaped).
	 *
	 * Bank transfer omits branch and transfer code — name, account number and
	 * holder are enough for Indonesian transfer forms and QRIS-less paperwork.
	 *
	 * @param array<string, mixed> $row Destination.
	 *
	 * @return array<int, array{label: string, value: string, kind?: string}>
	 */
	public static function detail_rows( array $row ): array {
		$type = (string) ( $row['type'] ?? '' );
		$out  = array();

		switch ( $type ) {
			case self::TYPE_BANK_TRANSFER:
				$name = self::resolved_bank_name(
					(string) ( $row['bank_code'] ?? '' ),
					(string) ( $row['bank_name'] ?? '' )
				);
				$no  = trim( (string) ( $row['account_no'] ?? '' ) );
				$acc = trim( (string) ( $row['account_name'] ?? '' ) );
				if ( '' !== $name ) {
					$out[] = array(
						'label' => __( 'Bank', 'wp-bizwit' ),
						'value' => $name,
						'kind'  => 'bank',
					);
				}
				if ( '' !== $no ) {
					$out[] = array(
						'label' => __( 'Account number', 'wp-bizwit' ),
						'value' => $no,
						'kind'  => 'account',
					);
				}
				if ( '' !== $acc ) {
					$out[] = array(
						'label' => __( 'Account holder', 'wp-bizwit' ),
						'value' => $acc,
						'kind'  => 'holder',
					);
				}
				break;

			case self::TYPE_VIRTUAL_ACCOUNT:
				$bank = self::resolved_bank_name(
					(string) ( $row['va_bank_code'] ?? '' ),
					(string) ( $row['va_bank'] ?? '' )
				);
				$va  = trim( (string) ( $row['va_number'] ?? '' ) );
				$acc = trim( (string) ( $row['account_name'] ?? '' ) );
				if ( '' !== $bank ) {
					$out[] = array(
						'label' => __( 'Bank', 'wp-bizwit' ),
						'value' => $bank,
						'kind'  => 'bank',
					);
				}
				if ( '' !== $va ) {
					$out[] = array(
						'label' => __( 'VA number', 'wp-bizwit' ),
						'value' => $va,
						'kind'  => 'account',
					);
				}
				if ( '' !== $acc ) {
					$out[] = array(
						'label' => __( 'Account holder', 'wp-bizwit' ),
						'value' => $acc,
						'kind'  => 'holder',
					);
				}
				break;

			case self::TYPE_PAYMENT_LINK:
				$url = trim( (string) ( $row['url'] ?? '' ) );
				if ( '' !== $url ) {
					$out[] = array(
						'label' => __( 'Link', 'wp-bizwit' ),
						'value' => $url,
						'kind'  => 'link',
					);
				}
				break;

			case self::TYPE_DANA:
			case self::TYPE_GOPAY:
			case self::TYPE_OVO:
			case self::TYPE_SHOPEEPAY:
				$id = trim( (string) ( $row['ewallet_id'] ?? '' ) );
				if ( '' !== $id ) {
					$out[] = array(
						'label' => __( 'Phone / ID', 'wp-bizwit' ),
						'value' => $id,
						'kind'  => 'account',
					);
				}
				break;

			case self::TYPE_OFFLINE:
			case self::TYPE_OTHER:
			default:
				$no  = trim( (string) ( $row['account_no'] ?? '' ) );
				$acc = trim( (string) ( $row['account_name'] ?? '' ) );
				$url = trim( (string) ( $row['url'] ?? '' ) );
				if ( '' !== $no ) {
					$out[] = array(
						'label' => __( 'Account number', 'wp-bizwit' ),
						'value' => $no,
						'kind'  => 'account',
					);
				}
				if ( '' !== $acc ) {
					$out[] = array(
						'label' => __( 'Account holder', 'wp-bizwit' ),
						'value' => $acc,
						'kind'  => 'holder',
					);
				}
				if ( '' !== $url ) {
					$out[] = array(
						'label' => __( 'Link', 'wp-bizwit' ),
						'value' => $url,
						'kind'  => 'link',
					);
				}
				break;
		}

		return $out;
	}

	/**
	 * Human detail lines for one destination (unescaped).
	 *
	 * Values only — prefer {@see detail_rows()} for labelled document output.
	 *
	 * @param array<string, mixed> $row Destination.
	 *
	 * @return string[]
	 */
	public static function detail_lines( array $row ): array {
		$lines = array();
		foreach ( self::detail_rows( $row ) as $detail ) {
			$lines[] = (string) $detail['value'];
		}
		return $lines;
	}

	/**
	 * Official bank name from transfer code, without the code suffix.
	 *
	 * @param string $code          Catalogue transfer code.
	 * @param string $fallback_name Free-text name.
	 *
	 * @return string
	 */
	private static function resolved_bank_name( string $code, string $fallback_name ): string {
		$code = trim( $code );
		$name = trim( $fallback_name );
		if ( '' !== $code ) {
			$found = Indonesia_Banks::find_by_code( $code );
			if ( null !== $found ) {
				return $found['name'];
			}
		}
		return $name;
	}

	/**
	 * Sanitize one destination row.
	 *
	 * @param array<string, mixed> $row Raw row.
	 *
	 * @return array<string, mixed>|null Null when type invalid.
	 */
	public static function sanitize_one( array $row ): ?array {
		$types = self::types();
		$type  = sanitize_key( (string) ( $row['type'] ?? self::TYPE_BANK_TRANSFER ) );
		if ( ! array_key_exists( $type, $types ) ) {
			$type = self::TYPE_OTHER;
		}

		$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = 'pd_' . wp_generate_password( 10, false, false );
		}

		$url = esc_url_raw( (string) ( $row['url'] ?? '' ) );

		$bank_code_raw = (string) ( $row['bank_code'] ?? '' );
		// "__custom__" means free-text bank_name only.
		if ( '__custom__' === $bank_code_raw ) {
			$bank_code_raw = '';
		}
		$bank_code = self::sanitize_bank_code( $bank_code_raw );
		$bank_name = sanitize_text_field( (string) ( $row['bank_name'] ?? '' ) );
		// Resolve name from catalogue when a transfer code is selected.
		if ( '' !== $bank_code ) {
			$found = Indonesia_Banks::find_by_code( $bank_code );
			if ( null !== $found ) {
				$bank_name = $found['name'];
			}
		} elseif ( '' !== $bank_name ) {
			// Legacy free-text → try map to code.
			$found = Indonesia_Banks::find_by_name( $bank_name );
			if ( null !== $found ) {
				$bank_code = $found['code'];
				$bank_name = $found['name'];
			}
		}

		$va_code_raw = (string) ( $row['va_bank_code'] ?? '' );
		if ( '__custom__' === $va_code_raw ) {
			$va_code_raw = '';
		}
		$va_bank_code = self::sanitize_bank_code( $va_code_raw );
		$va_bank      = sanitize_text_field( (string) ( $row['va_bank'] ?? '' ) );
		if ( '' !== $va_bank_code ) {
			$found = Indonesia_Banks::find_by_code( $va_bank_code );
			if ( null !== $found ) {
				$va_bank = $found['name'];
			}
		} elseif ( '' !== $va_bank ) {
			$found = Indonesia_Banks::find_by_name( $va_bank );
			if ( null !== $found ) {
				$va_bank_code = $found['code'];
				$va_bank      = $found['name'];
			}
		}

		return array(
			'id'           => $id,
			'type'         => $type,
			'label'        => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			'enabled'      => ! empty( $row['enabled'] ),
			'bank_code'    => $bank_code,
			'bank_name'    => $bank_name,
			'account_no'   => sanitize_text_field( (string) ( $row['account_no'] ?? '' ) ),
			'account_name' => sanitize_text_field( (string) ( $row['account_name'] ?? '' ) ),
			'branch'       => sanitize_text_field( (string) ( $row['branch'] ?? '' ) ),
			'va_bank_code' => $va_bank_code,
			'va_bank'      => $va_bank,
			'va_number'    => sanitize_text_field( (string) ( $row['va_number'] ?? '' ) ),
			'ewallet_id'   => sanitize_text_field( (string) ( $row['ewallet_id'] ?? '' ) ),
			'url'          => $url,
			'notes'        => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
		);
	}

	/**
	 * Normalise a 3-digit bank transfer code.
	 *
	 * @param string $code Raw code.
	 *
	 * @return string Empty or zero-padded code present in the catalogue.
	 */
	private static function sanitize_bank_code( string $code ): string {
		$code = preg_replace( '/\D/', '', $code ) ?? '';
		if ( '' === $code ) {
			return '';
		}
		if ( strlen( $code ) < 3 ) {
			$code = str_pad( $code, 3, '0', STR_PAD_LEFT );
		}
		return null !== Indonesia_Banks::find_by_code( $code ) ? $code : '';
	}

	/**
	 * Whether the row has enough identity fields for documents.
	 *
	 * @param array<string, mixed> $row Destination.
	 *
	 * @return bool
	 */
	public static function is_displayable( array $row ): bool {
		$lines = self::detail_lines( $row );
		$notes = trim( (string) ( $row['notes'] ?? '' ) );
		return array() !== $lines || '' !== $notes;
	}

	/**
	 * Whether every field is empty (placeholder row).
	 *
	 * @param array<string, mixed> $row Destination.
	 *
	 * @return bool
	 */
	private static function is_blank( array $row ): bool {
		$keys = array( 'label', 'bank_code', 'bank_name', 'account_no', 'account_name', 'branch', 'va_bank_code', 'va_bank', 'va_number', 'ewallet_id', 'url', 'notes' );
		foreach ( $keys as $key ) {
			if ( '' !== trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Build destinations from pre-1.1 single bank_* settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function from_legacy_bank_fields(): array {
		$name = trim( (string) Settings::get( 'bank_name', '' ) );
		$no   = trim( (string) Settings::get( 'bank_account_no', '' ) );
		$acc  = trim( (string) Settings::get( 'bank_account_name', '' ) );
		$br   = trim( (string) Settings::get( 'bank_branch', '' ) );

		if ( '' === $name && '' === $no ) {
			return array();
		}

		$row                 = self::empty_destination();
		$row['id']           = 'pd_legacy_bank';
		$row['type']         = self::TYPE_BANK_TRANSFER;
		$row['enabled']      = true;
		$row['bank_name']    = $name;
		$row['account_no']   = $no;
		$row['account_name'] = $acc;
		$row['branch']       = $br;

		$matched = Indonesia_Banks::find_by_name( $name );
		if ( null !== $matched ) {
			$row['bank_code'] = $matched['code'];
			$row['bank_name'] = $matched['name'];
		}

		return array( $row );
	}
}
