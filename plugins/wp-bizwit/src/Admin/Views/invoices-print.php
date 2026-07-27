<?php
/**
 * Printable invoice document (A4-oriented HTML for browser print-to-PDF).
 *
 * Variables are set by Invoices_Screen::handle_print() before include.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed>      $invoice Invoice row.
 * @var array<int, array<string, mixed>> $items Line items.
 * @var array<string, mixed>|null $client  Client row.
 * @var array<string, mixed>|null $project Project row or null.
 */

use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Payment_Destinations;
use WP_BizWit\Support\Settings;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$currency       = (string) ( $invoice['currency'] ?? 'IDR' );
$invoice_status = (string) ( $invoice['status'] ?? '' );
$tax_label      = (string) Settings::get( 'tax_label', 'PPN' );
$wht_label      = (string) Settings::get( 'withholding_label', 'PPh 23' );
$biz_name       = (string) Settings::get( 'business_name', get_bloginfo( 'name' ) );
$biz_addr       = (string) Settings::get( 'business_address', '' );
$biz_email      = (string) Settings::get( 'business_email', '' );
$biz_phone      = (string) Settings::get( 'business_phone', '' );
$tax_id         = (string) Settings::get( 'tax_id', '' );
$pay_block      = Payment_Destinations::block_html();
$charges_tax    = Settings::charges_sales_tax() || (int) $invoice['tax_minor'] > 0;

$subtotal   = (int) $invoice['subtotal_minor'];
$discount   = (int) $invoice['discount_minor'];
$tax_amount = (int) $invoice['tax_minor'];
$total      = (int) $invoice['total_minor'];
$paid       = (int) $invoice['paid_minor'];
$wht        = (int) ( $invoice['withholding_minor'] ?? 0 );
$balance    = Invoice_Totals::balance_minor( $total, $paid );
$net        = $total - $wht;

$fmt_date = static function ( string $date ): string {
	if ( '' === $date || '0000-00-00' === $date ) {
		return '—';
	}
	$formatted = Regions::current()->format_date( $date );

	return '' !== $formatted ? $formatted : $date;
};

$client_name    = is_array( $client ) ? (string) ( $client['display_name'] ?? '' ) : '';
$client_address = is_array( $client ) ? (string) ( $client['address'] ?? '' ) : '';
$client_tax     = is_array( $client ) ? (string) ( $client['tax_id'] ?? '' ) : '';
$project_name   = is_array( $project ) ? (string) ( $project['name'] ?? '' ) : '';

$labels         = Invoice_Status::labels();
$document_title = sprintf(
	/* translators: %s: invoice number */
	__( 'Invoice %s', 'wp-bizwit' ),
	(string) $invoice['invoice_number']
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $document_title ); ?></title>
	<style>
		/* Keep body padding in print — same breathing room as on-screen (do not zero it). */
		@page { size: A4; margin: 8mm; }
		* { box-sizing: border-box; }
		html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		body {
			font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			font-size: 11pt;
			color: #1d2327;
			margin: 0;
			padding: 12mm 14mm 14mm;
			line-height: 1.5;
			background: #fff;
		}
		h1 { font-size: 18pt; margin: 0 0 4mm; }
		h2 { font-size: 12pt; margin: 0 0 2.5mm; font-weight: 600; }
		.muted { color: #646970; }
		.header {
			display: flex;
			justify-content: space-between;
			gap: 12mm;
			margin-bottom: 8mm;
			border-bottom: 1px solid #c3c4c7;
			padding-bottom: 6mm;
		}
		.parties {
			display: flex;
			justify-content: space-between;
			gap: 12mm;
			margin-bottom: 8mm;
		}
		.party { flex: 1; }
		table.lines {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 7mm;
		}
		table.lines th,
		table.lines td {
			border-bottom: 1px solid #dcdcde;
			padding: 3mm 2.5mm;
			text-align: left;
			vertical-align: top;
		}
		table.lines th { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.02em; color: #646970; }
		table.lines .num { text-align: right; white-space: nowrap; }
		.totals {
			width: 70mm;
			margin-left: auto;
			margin-bottom: 8mm;
		}
		.totals td { padding: 2mm 2.5mm; }
		.totals .grand td { font-weight: 700; border-top: 1px solid #1d2327; padding-top: 3.5mm; }
		.bank, .notes, .sign {
			margin-top: 7mm;
		}
		.sign {
			display: flex;
			justify-content: space-between;
			gap: 20mm;
			margin-top: 16mm;
		}
		.sign-box {
			width: 45%;
			min-height: 34mm;
			border-top: 1px solid #c3c4c7;
			padding-top: 3.5mm;
			font-size: 9pt;
			color: #646970;
		}
		.stamp-note { font-size: 9pt; color: #646970; margin-top: 4mm; }
		.void-banner {
			border: 2px solid #b32d2e;
			color: #b32d2e;
			padding: 3.5mm;
			margin-bottom: 6mm;
			text-align: center;
			font-weight: 700;
			letter-spacing: 0.08em;
		}
		@media print {
			html, body {
				margin: 0 !important;
				/* Keep the same inner padding as screen preview */
				padding: 12mm 14mm 14mm !important;
				background: #fff !important;
			}
			.no-print { display: none !important; }
			.header, .parties, .sign { display: flex !important; }
		}
	</style>
</head>
<body>
	<p class="no-print muted">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'wp-bizwit' ); ?></button>
		—
		<?php esc_html_e( 'Use your browser’s print dialog to save as PDF (A4).', 'wp-bizwit' ); ?>
	</p>

	<?php if ( Invoice_Status::VOID === $invoice_status ) : ?>
		<div class="void-banner"><?php esc_html_e( 'VOID', 'wp-bizwit' ); ?></div>
	<?php endif; ?>

	<div class="header">
		<div>
			<h1><?php echo esc_html( $biz_name ); ?></h1>
			<?php if ( '' !== $biz_addr ) : ?>
				<div><?php echo nl2br( esc_html( $biz_addr ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $biz_phone || '' !== $biz_email ) : ?>
				<div class="muted">
					<?php echo esc_html( trim( $biz_phone . ( $biz_phone && $biz_email ? ' · ' : '' ) . $biz_email ) ); ?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $tax_id ) : ?>
				<div class="muted"><?php echo esc_html( 'NPWP / Tax ID: ' . $tax_id ); ?></div>
			<?php endif; ?>
		</div>
		<div style="text-align: right;">
			<h1><?php esc_html_e( 'Invoice', 'wp-bizwit' ); ?></h1>
			<div><strong><?php echo esc_html( (string) $invoice['invoice_number'] ); ?></strong></div>
			<div class="muted">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: issue date, 2: due date */
						__( 'Issued %1$s · Due %2$s', 'wp-bizwit' ),
						$fmt_date( (string) ( $invoice['issue_date'] ?? '' ) ),
						$fmt_date( (string) ( $invoice['due_date'] ?? '' ) )
					)
				);
				?>
			</div>
			<?php if ( Invoice_Status::DRAFT !== $invoice_status && Invoice_Status::VOID !== $invoice_status ) : ?>
				<div class="muted"><?php echo esc_html( $labels[ $invoice_status ] ?? $invoice_status ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="parties">
		<div class="party">
			<h2><?php esc_html_e( 'Bill to', 'wp-bizwit' ); ?></h2>
			<div><strong><?php echo esc_html( '' !== $client_name ? $client_name : '—' ); ?></strong></div>
			<?php if ( '' !== $client_address ) : ?>
				<div><?php echo nl2br( esc_html( $client_address ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $client_tax ) : ?>
				<div class="muted"><?php echo esc_html( 'NPWP: ' . $client_tax ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $project_name ) : ?>
				<div class="muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: project name */
							__( 'Project: %s', 'wp-bizwit' ),
							$project_name
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<table class="lines">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Description', 'wp-bizwit' ); ?></th>
				<th class="num"><?php esc_html_e( 'Qty', 'wp-bizwit' ); ?></th>
				<th><?php esc_html_e( 'Unit', 'wp-bizwit' ); ?></th>
				<th class="num"><?php esc_html_e( 'Unit price', 'wp-bizwit' ); ?></th>
				<?php if ( $charges_tax ) : ?>
					<th class="num"><?php echo esc_html( $tax_label . ' %' ); ?></th>
				<?php endif; ?>
				<th class="num"><?php esc_html_e( 'Amount', 'wp-bizwit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $item['description'] ); ?></td>
					<td class="num"><?php echo esc_html( rtrim( rtrim( (string) $item['quantity'], '0' ), '.' ) ); ?></td>
					<td><?php echo esc_html( (string) ( $item['unit'] ?? '' ) ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( (int) $item['unit_price_minor'], $currency ) ); ?></td>
					<?php if ( $charges_tax ) : ?>
						<td class="num"><?php echo esc_html( rtrim( rtrim( (string) $item['tax_rate'], '0' ), '.' ) ); ?></td>
					<?php endif; ?>
					<td class="num"><?php echo esc_html( Money::format( (int) $item['line_total_minor'], $currency ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="totals">
		<tr>
			<td><?php esc_html_e( 'Subtotal', 'wp-bizwit' ); ?></td>
			<td class="num"><?php echo esc_html( Money::format( $subtotal, $currency ) ); ?></td>
		</tr>
		<?php if ( $discount > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'Discount', 'wp-bizwit' ); ?></td>
				<td class="num">− <?php echo esc_html( Money::format( $discount, $currency ) ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( $charges_tax || $tax_amount > 0 ) : ?>
			<tr>
				<td><?php echo esc_html( $tax_label ); ?></td>
				<td class="num"><?php echo esc_html( Money::format( $tax_amount, $currency ) ); ?></td>
			</tr>
		<?php endif; ?>
		<tr class="grand">
			<td><?php esc_html_e( 'Total', 'wp-bizwit' ); ?></td>
			<td class="num"><?php echo esc_html( Money::format( $total, $currency ) ); ?></td>
		</tr>
		<?php if ( $wht > 0 ) : ?>
			<tr>
				<td><?php echo esc_html( $wht_label ); ?></td>
				<td class="num">− <?php echo esc_html( Money::format( $wht, $currency ) ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Net expected', 'wp-bizwit' ); ?></td>
				<td class="num"><?php echo esc_html( Money::format( $net, $currency ) ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( $paid > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'Paid', 'wp-bizwit' ); ?></td>
				<td class="num"><?php echo esc_html( Money::format( $paid, $currency ) ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Balance', 'wp-bizwit' ); ?></td>
				<td class="num"><?php echo esc_html( Money::format( $balance, $currency ) ); ?></td>
			</tr>
		<?php endif; ?>
	</table>

	<?php if ( 'IDR' === $currency ) : ?>
		<p class="muted">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: amount in words */
					__( 'In words: %s', 'wp-bizwit' ),
					Money::in_words( $total, $currency )
				)
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $pay_block ) : ?>
		<div class="bank">
			<h2><?php esc_html_e( 'Payment details', 'wp-bizwit' ); ?></h2>
			<?php echo $pay_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside Payment_Destinations::block_html(). ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== (string) ( $invoice['notes'] ?? '' ) ) : ?>
		<div class="notes">
			<h2><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></h2>
			<div><?php echo nl2br( esc_html( (string) $invoice['notes'] ) ); ?></div>
		</div>
	<?php endif; ?>

	<?php if ( '' !== (string) ( $invoice['terms'] ?? '' ) ) : ?>
		<div class="notes">
			<h2><?php esc_html_e( 'Terms', 'wp-bizwit' ); ?></h2>
			<div><?php echo nl2br( esc_html( (string) $invoice['terms'] ) ); ?></div>
		</div>
	<?php endif; ?>

	<?php if ( Settings::get( 'apply_stamp_duty', true ) && $total >= 5000000 && 'IDR' === $currency ) : ?>
		<p class="stamp-note">
			<?php esc_html_e( 'Stamp duty (bea meterai) may apply to this document under Indonesian law — affix as required.', 'wp-bizwit' ); ?>
		</p>
	<?php endif; ?>

	<div class="sign">
		<div class="sign-box">
			<?php esc_html_e( 'Received by', 'wp-bizwit' ); ?>
		</div>
		<div class="sign-box">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: business name */
					__( 'For %s', 'wp-bizwit' ),
					$biz_name
				)
			);
			?>
			<br />
			<span class="muted"><?php esc_html_e( 'Signature and company stamp', 'wp-bizwit' ); ?></span>
		</div>
	</div>
</body>
</html>
