<?php
/**
 * Printable kwitansi (payment receipt) — A4 HTML for browser print-to-PDF.
 *
 * Variables set by Payments_Screen::handle_print().
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed>      $payment Payment row.
 * @var array<string, mixed>|null $invoice Invoice row.
 * @var array<string, mixed>|null $client  Client row.
 */

use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Payment_Repository;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$currency  = (string) ( $payment['currency'] ?? 'IDR' );
$biz_name  = (string) Settings::get( 'business_name', get_bloginfo( 'name' ) );
$biz_addr  = (string) Settings::get( 'business_address', '' );
$biz_phone = (string) Settings::get( 'business_phone', '' );
$tax_id    = (string) Settings::get( 'tax_id', '' );

$received = (int) $payment['amount_minor'];
$withheld = (int) ( $payment['withheld_minor'] ?? 0 );
$settled  = Payment_Repository::settlement_minor( $payment );
// Kwitansi states the amount acknowledged — typically bank received; show both if withheld.
$words_of = $received > 0 ? $received : $settled;

$methods      = Regions::current()->payment_methods();
$method       = (string) ( $payment['method'] ?? '' );
$method_label = $methods[ $method ] ?? $method;

$client_name = is_array( $client ) ? (string) ( $client['display_name'] ?? '' ) : '';
$inv_number  = is_array( $invoice ) ? (string) ( $invoice['invoice_number'] ?? '' ) : '';

$show_meterai = Settings::get( 'apply_stamp_duty', true )
	&& 'IDR' === $currency
	&& $words_of >= Indonesia::METERAI_THRESHOLD;

$fmt_date = static function ( string $date ): string {
	if ( '' === $date || '0000-00-00' === $date ) {
		return '—';
	}
	$ts = strtotime( $date );

	return false === $ts ? $date : (string) wp_date( get_option( 'date_format' ), $ts );
};

$document_title = sprintf(
	/* translators: %s: receipt number */
	__( 'Receipt %s', 'wp-bizwit' ),
	(string) $payment['receipt_number']
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $document_title ); ?></title>
	<style>
		@page { size: A4; margin: 16mm; }
		* { box-sizing: border-box; }
		body {
			font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			font-size: 11pt;
			color: #1d2327;
			margin: 0;
			padding: 12mm;
			line-height: 1.45;
		}
		h1 { font-size: 18pt; margin: 0 0 2mm; }
		.muted { color: #646970; }
		.header {
			display: flex;
			justify-content: space-between;
			gap: 12mm;
			border-bottom: 1px solid #c3c4c7;
			padding-bottom: 6mm;
			margin-bottom: 8mm;
		}
		.box {
			border: 1px solid #dcdcde;
			padding: 5mm;
			margin-bottom: 6mm;
		}
		.amount {
			font-size: 16pt;
			font-weight: 700;
			margin: 4mm 0;
		}
		.words { font-style: italic; margin-bottom: 6mm; }
		table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
		table.meta th, table.meta td { text-align: left; padding: 2mm 2mm 2mm 0; vertical-align: top; }
		table.meta th { width: 32%; color: #646970; font-weight: 600; }
		.sign {
			display: flex;
			justify-content: space-between;
			gap: 20mm;
			margin-top: 16mm;
		}
		.sign-box {
			width: 45%;
			min-height: 32mm;
			border-top: 1px solid #c3c4c7;
			padding-top: 3mm;
			font-size: 9pt;
			color: #646970;
		}
		.meterai {
			border: 1px dashed #646970;
			padding: 4mm;
			margin-top: 6mm;
			font-size: 9pt;
			color: #646970;
			min-height: 18mm;
		}
		@media print {
			body { padding: 0; }
			.no-print { display: none !important; }
		}
	</style>
</head>
<body>
	<p class="no-print muted">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'wp-bizwit' ); ?></button>
		—
		<?php esc_html_e( 'Use your browser’s print dialog to save as PDF (A4).', 'wp-bizwit' ); ?>
	</p>

	<div class="header">
		<div>
			<h1><?php echo esc_html( $biz_name ); ?></h1>
			<?php if ( '' !== $biz_addr ) : ?>
				<div><?php echo nl2br( esc_html( $biz_addr ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $biz_phone ) : ?>
				<div class="muted"><?php echo esc_html( $biz_phone ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $tax_id ) : ?>
				<div class="muted"><?php echo esc_html( 'NPWP: ' . $tax_id ); ?></div>
			<?php endif; ?>
		</div>
		<div style="text-align:right;">
			<h1><?php esc_html_e( 'Receipt', 'wp-bizwit' ); ?> / <?php esc_html_e( 'Kwitansi', 'wp-bizwit' ); ?></h1>
			<div><strong><?php echo esc_html( (string) $payment['receipt_number'] ); ?></strong></div>
			<div class="muted"><?php echo esc_html( $fmt_date( (string) ( $payment['paid_on'] ?? '' ) ) ); ?></div>
		</div>
	</div>

	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: payer / client name */
				__( 'Received from: %s', 'wp-bizwit' ),
				'' !== $client_name ? $client_name : '—'
			)
		);
		?>
	</p>

	<div class="box">
		<div class="muted"><?php esc_html_e( 'The sum of', 'wp-bizwit' ); ?></div>
		<div class="amount"><?php echo esc_html( Money::format( $words_of, $currency ) ); ?></div>
		<?php if ( 'IDR' === $currency ) : ?>
			<div class="words">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: amount in words */
						__( 'In words: %s', 'wp-bizwit' ),
						Money::in_words( $words_of, $currency )
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>

	<table class="meta">
		<?php if ( '' !== $inv_number ) : ?>
			<tr>
				<th><?php esc_html_e( 'For invoice', 'wp-bizwit' ); ?></th>
				<td><?php echo esc_html( $inv_number ); ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<th><?php esc_html_e( 'Payment method', 'wp-bizwit' ); ?></th>
			<td><?php echo esc_html( $method_label ); ?></td>
		</tr>
		<?php if ( '' !== (string) ( $payment['reference'] ?? '' ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Reference', 'wp-bizwit' ); ?></th>
				<td><?php echo esc_html( (string) $payment['reference'] ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( $withheld > 0 ) : ?>
			<tr>
				<th><?php esc_html_e( 'Received in bank', 'wp-bizwit' ); ?></th>
				<td><?php echo esc_html( Money::format( $received, $currency ) ); ?></td>
			</tr>
			<tr>
				<th><?php echo esc_html( (string) Settings::get( 'withholding_label', 'PPh 23' ) ); ?></th>
				<td><?php echo esc_html( Money::format( $withheld, $currency ) ); ?></td>
			</tr>
			<?php if ( '' !== (string) ( $payment['withholding_ref'] ?? '' ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Withholding certificate', 'wp-bizwit' ); ?></th>
					<td><?php echo esc_html( (string) $payment['withholding_ref'] ); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Settled toward invoice', 'wp-bizwit' ); ?></th>
				<td><?php echo esc_html( Money::format( $settled, $currency ) ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( '' !== (string) ( $payment['notes'] ?? '' ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></th>
				<td><?php echo nl2br( esc_html( (string) $payment['notes'] ) ); ?></td>
			</tr>
		<?php endif; ?>
	</table>

	<?php if ( $show_meterai ) : ?>
		<div class="meterai">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: threshold amount, 2: meterai amount */
					__( 'Stamp duty (bea meterai) of %2$s may apply on documents above %1$s — affix meterai here as required.', 'wp-bizwit' ),
					Money::format( Indonesia::METERAI_THRESHOLD, 'IDR' ),
					Money::format( Indonesia::METERAI_AMOUNT, 'IDR' )
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="sign">
		<div class="sign-box">
			<?php esc_html_e( 'Payer', 'wp-bizwit' ); ?>
		</div>
		<div class="sign-box">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: business name */
					__( 'Received by %s', 'wp-bizwit' ),
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
