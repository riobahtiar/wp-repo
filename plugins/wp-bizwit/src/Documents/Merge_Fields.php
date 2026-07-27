<?php
/**
 * Merge field catalogue and resolution for document templates.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Payment_Destinations;
use WP_BizWit\Support\Settings;

/**
 * Maps stable field keys to translated labels and resolved values.
 *
 * Field *labels* use __() so they follow the site language. Values are data.
 */
class Merge_Fields {

	/**
	 * All known fields for the block picker and shortcode help.
	 *
	 * @return array<string, string> Field key mapped to translated label.
	 */
	public static function catalogue(): array {
		return array(
			'business_name'     => __( 'Business name', 'wp-bizwit' ),
			'business_address'  => __( 'Business address', 'wp-bizwit' ),
			'business_email'    => __( 'Business email', 'wp-bizwit' ),
			'business_phone'    => __( 'Business phone', 'wp-bizwit' ),
			'business_tax_id'   => __( 'Business tax ID (NPWP)', 'wp-bizwit' ),
			'document_title'    => __( 'Document title', 'wp-bizwit' ),
			'invoice_number'    => __( 'Invoice number', 'wp-bizwit' ),
			'issue_date'        => __( 'Issue date', 'wp-bizwit' ),
			'due_date'          => __( 'Due date', 'wp-bizwit' ),
			'status_label'      => __( 'Status', 'wp-bizwit' ),
			'client_name'       => __( 'Client name', 'wp-bizwit' ),
			'client_address'    => __( 'Client address', 'wp-bizwit' ),
			'client_tax_id'     => __( 'Client tax ID', 'wp-bizwit' ),
			'project_name'      => __( 'Project name', 'wp-bizwit' ),
			'currency'          => __( 'Currency', 'wp-bizwit' ),
			'subtotal'          => __( 'Subtotal', 'wp-bizwit' ),
			'discount'          => __( 'Discount', 'wp-bizwit' ),
			'tax'               => __( 'Tax', 'wp-bizwit' ),
			'tax_label'         => __( 'Tax label', 'wp-bizwit' ),
			'total'             => __( 'Total', 'wp-bizwit' ),
			'total_in_words'    => __( 'Total in words (terbilang)', 'wp-bizwit' ),
			'paid'              => __( 'Paid', 'wp-bizwit' ),
			'balance'           => __( 'Balance', 'wp-bizwit' ),
			'withholding'       => __( 'Withholding amount', 'wp-bizwit' ),
			'withholding_label' => __( 'Withholding label', 'wp-bizwit' ),
			'net_expected'      => __( 'Net expected', 'wp-bizwit' ),
			'notes'             => __( 'Notes', 'wp-bizwit' ),
			'terms'             => __( 'Terms', 'wp-bizwit' ),
			'bank_name'         => __( 'Bank name', 'wp-bizwit' ),
			'bank_account_no'   => __( 'Bank account number', 'wp-bizwit' ),
			'bank_account_name' => __( 'Bank account name', 'wp-bizwit' ),
			'bank_branch'       => __( 'Bank branch', 'wp-bizwit' ),
			'bank_block'        => __( 'Bank details (block)', 'wp-bizwit' ),
		);
	}

	/**
	 * Resolve a field to HTML-safe plain text or markup (escaped).
	 *
	 * @param string $field Field key.
	 *
	 * @return string Escaped output (may contain <br> for multiline).
	 */
	public static function resolve( string $field ): string {
		$invoice = Document_Context::get( 'invoice' );
		$client  = Document_Context::get( 'client' );
		$project = Document_Context::get( 'project' );
		$invoice = is_array( $invoice ) ? $invoice : array();
		$client  = is_array( $client ) ? $client : array();
		$project = is_array( $project ) ? $project : null;

		$currency = (string) ( $invoice['currency'] ?? Settings::currency() );

		switch ( $field ) {
			case 'business_name':
				return esc_html( (string) Settings::get( 'business_name', get_bloginfo( 'name' ) ) );
			case 'business_address':
				return nl2br( esc_html( (string) Settings::get( 'business_address', '' ) ) );
			case 'business_email':
				return esc_html( (string) Settings::get( 'business_email', '' ) );
			case 'business_phone':
				return esc_html( (string) Settings::get( 'business_phone', '' ) );
			case 'business_tax_id':
				return esc_html( (string) Settings::get( 'tax_id', '' ) );
			case 'document_title':
				return esc_html__( 'Invoice', 'wp-bizwit' );
			case 'invoice_number':
				return esc_html( (string) ( $invoice['invoice_number'] ?? '' ) );
			case 'issue_date':
				return esc_html( self::format_date( (string) ( $invoice['issue_date'] ?? '' ) ) );
			case 'due_date':
				return esc_html( self::format_date( (string) ( $invoice['due_date'] ?? '' ) ) );
			case 'status_label':
				$status = (string) ( $invoice['status'] ?? '' );
				$labels = Invoice_Status::labels();
				return esc_html( $labels[ $status ] ?? $status );
			case 'client_name':
				return esc_html( (string) ( $client['display_name'] ?? '' ) );
			case 'client_address':
				return nl2br( esc_html( (string) ( $client['address'] ?? '' ) ) );
			case 'client_tax_id':
				return esc_html( (string) ( $client['tax_id'] ?? '' ) );
			case 'project_name':
				return esc_html( is_array( $project ) ? (string) ( $project['name'] ?? '' ) : '' );
			case 'currency':
				return esc_html( $currency );
			case 'subtotal':
				return esc_html( Money::format( (int) ( $invoice['subtotal_minor'] ?? 0 ), $currency ) );
			case 'discount':
				return esc_html( Money::format( (int) ( $invoice['discount_minor'] ?? 0 ), $currency ) );
			case 'tax':
				return esc_html( Money::format( (int) ( $invoice['tax_minor'] ?? 0 ), $currency ) );
			case 'tax_label':
				return esc_html( (string) Settings::get( 'tax_label', 'PPN' ) );
			case 'total':
				return esc_html( Money::format( (int) ( $invoice['total_minor'] ?? 0 ), $currency ) );
			case 'total_in_words':
				return esc_html( Money::in_words( (int) ( $invoice['total_minor'] ?? 0 ), $currency ) );
			case 'paid':
				return esc_html( Money::format( (int) ( $invoice['paid_minor'] ?? 0 ), $currency ) );
			case 'balance':
				$bal = Invoice_Totals::balance_minor(
					(int) ( $invoice['total_minor'] ?? 0 ),
					(int) ( $invoice['paid_minor'] ?? 0 )
				);
				return esc_html( Money::format( $bal, $currency ) );
			case 'withholding':
				return esc_html( Money::format( (int) ( $invoice['withholding_minor'] ?? 0 ), $currency ) );
			case 'withholding_label':
				return esc_html( (string) Settings::get( 'withholding_label', 'PPh 23' ) );
			case 'net_expected':
				$total = (int) ( $invoice['total_minor'] ?? 0 );
				$wht   = (int) ( $invoice['withholding_minor'] ?? 0 );
				return esc_html( Money::format( $total - $wht, $currency ) );
			case 'notes':
				return nl2br( esc_html( (string) ( $invoice['notes'] ?? '' ) ) );
			case 'terms':
				return nl2br( esc_html( (string) ( $invoice['terms'] ?? '' ) ) );
			case 'bank_name':
				return esc_html( (string) Settings::get( 'bank_name', '' ) );
			case 'bank_account_no':
				return esc_html( (string) Settings::get( 'bank_account_no', '' ) );
			case 'bank_account_name':
				return esc_html( (string) Settings::get( 'bank_account_name', '' ) );
			case 'bank_branch':
				return esc_html( (string) Settings::get( 'bank_branch', '' ) );
			case 'bank_block':
				return self::bank_block_html();
			default:
				return '';
		}
	}

	/**
	 * Format a Y-m-d date for documents (regional style, gettext month names).
	 *
	 * Uses the active region formatter so Indonesian paperwork reads
	 * "15 Juli 2026" when the UI locale is id_ID, and English month names
	 * when the active language is English — independent of Settings → date format.
	 *
	 * @param string $date Date string.
	 *
	 * @return string
	 */
	public static function format_date( string $date ): string {
		if ( '' === $date || '0000-00-00' === $date ) {
			return '—';
		}

		$formatted = Regions::current()->format_date( $date );

		return '' !== $formatted ? $formatted : $date;
	}

	/**
	 * Composite payment destinations block (bank, VA, e-wallets, links, …).
	 *
	 * @return string HTML.
	 */
	private static function bank_block_html(): string {
		return Payment_Destinations::block_html();
	}
}
