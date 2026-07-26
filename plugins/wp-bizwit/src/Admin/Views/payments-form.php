<?php
/**
 * Payment add/edit form.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data.
 */

use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$payment   = is_array( $data['payment'] ?? null ) ? $data['payment'] : array();
$invoice   = is_array( $data['invoice'] ?? null ) ? $data['invoice'] : null;
$is_edit   = ! empty( $data['is_edit'] );
$defaults  = is_array( $data['defaults'] ?? null ) ? $data['defaults'] : array();
$methods   = is_array( $data['methods'] ?? null ) ? $data['methods'] : array();
$handles   = ! empty( $data['handles_tax'] );
$wht_label = (string) ( $data['wht_label'] ?? 'PPh 23' );

$field = static function ( string $key, string $fallback = '' ) use ( $payment ): string {
	if ( ! array_key_exists( $key, $payment ) || null === $payment[ $key ] ) {
		return $fallback;
	}
	$value = (string) $payment[ $key ];

	return '' === $value ? $fallback : $value;
};

$currency   = $field( 'currency', $invoice ? (string) $invoice['currency'] : 'IDR' );
$invoice_id = (int) ( $payment['invoice_id'] ?? 0 );
$paid_on    = $field( 'paid_on', (string) ( $defaults['paid_on'] ?? '' ) );
$method     = $field( 'method', (string) ( $defaults['method'] ?? 'bank_transfer' ) );
$amount     = Money::to_input( (int) ( $payment['amount_minor'] ?? 0 ), $currency );
$withheld   = Money::to_input( (int) ( $payment['withheld_minor'] ?? 0 ), $currency );
$money_ex   = Money::to_input( 1500000, $currency ) ?: '1.500.000';

$print_url = '';
if ( $is_edit && ! empty( $payment['id'] ) ) {
	$print_url = add_query_arg(
		array(
			'page'    => 'wp-bizwit-payments',
			'action'  => 'print',
			'payment' => (int) $payment['id'],
		),
		admin_url( 'admin.php' )
	);
}
?>

<h1 class="wp-heading-inline">
	<?php echo $is_edit ? esc_html__( 'Edit payment', 'wp-bizwit' ) : esc_html__( 'Record payment', 'wp-bizwit' ); ?>
</h1>

<a href="<?php echo esc_url( (string) $data['list_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Back to payments', 'wp-bizwit' ); ?>
</a>

<?php if ( '' !== $print_url ) : ?>
	<a href="<?php echo esc_url( $print_url ); ?>" class="page-title-action" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Print receipt', 'wp-bizwit' ); ?>
	</a>
<?php endif; ?>

<hr class="wp-header-end" />

<?php if ( is_array( $invoice ) ) : ?>
	<?php
	$balance = Invoice_Totals::balance_minor( (int) $invoice['total_minor'], (int) $invoice['paid_minor'] );
	$paid    = (int) $invoice['paid_minor'];
	$total   = (int) $invoice['total_minor'];
	?>
	<div class="notice notice-info inline">
		<p>
			<strong><?php echo esc_html( (string) $invoice['invoice_number'] ); ?></strong>
			—
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: total, 2: paid, 3: balance */
					__( 'Total %1$s · Settled %2$s · Balance %3$s', 'wp-bizwit' ),
					Money::format( $total, $currency ),
					Money::format( $paid, $currency ),
					Money::format( $balance, $currency )
				)
			);
			?>
			<?php if ( $paid > $total ) : ?>
				<em>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: credit amount */
							__( '(overpaid by %s)', 'wp-bizwit' ),
							Money::format( $paid - $total, $currency )
						)
					);
					?>
				</em>
			<?php endif; ?>
		</p>
	</div>
<?php endif; ?>

<form method="post" class="wp-bizwit-form">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_payment_form" value="1" />
	<input type="hidden" name="payment_id" value="<?php echo esc_attr( (string) ( $payment['id'] ?? 0 ) ); ?>" />

	<?php if ( $is_edit && ! empty( $payment['receipt_number'] ) ) : ?>
		<p class="description">
			<?php echo esc_html__( 'Receipt:', 'wp-bizwit' ) . ' '; ?>
			<strong><?php echo esc_html( (string) $payment['receipt_number'] ); ?></strong>
		</p>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wp-bizwit-invoice"><?php esc_html_e( 'Invoice', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<select name="invoice_id" id="wp-bizwit-invoice" required>
					<option value="0"><?php esc_html_e( '— Select invoice —', 'wp-bizwit' ); ?></option>
					<?php foreach ( (array) $data['invoice_options'] as $iid => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $iid ); ?>" <?php selected( $invoice_id, (int) $iid ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
					<?php
					// Keep current invoice selectable even if paid (edit/history).
					if ( $invoice_id > 0 && is_array( $invoice ) && ! isset( $data['invoice_options'][ $invoice_id ] ) ) :
						?>
						<option value="<?php echo esc_attr( (string) $invoice_id ); ?>" selected>
							<?php echo esc_html( (string) $invoice['invoice_number'] ); ?>
						</option>
					<?php endif; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-paid-on"><?php esc_html_e( 'Date received', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<input type="date" name="paid_on" id="wp-bizwit-paid-on" value="<?php echo esc_attr( $paid_on ); ?>" required />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-amount"><?php esc_html_e( 'Amount received (bank)', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<input type="text" name="amount" id="wp-bizwit-amount" class="regular-text" value="<?php echo esc_attr( $amount ); ?>" placeholder="<?php echo esc_attr( $money_ex ); ?>" required />
				<p class="description"><?php esc_html_e( 'What actually arrived in your account.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<?php if ( $handles ) : ?>
			<tr>
				<th scope="row">
					<label for="wp-bizwit-withheld"><?php echo esc_html( $wht_label ); ?></label>
				</th>
				<td>
					<input type="text" name="withheld" id="wp-bizwit-withheld" class="regular-text" value="<?php echo esc_attr( $withheld ); ?>" placeholder="<?php echo esc_attr( $money_ex ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Withheld by the client and remitted on your behalf. Counts toward settling the invoice even though it is not in your bank.', 'wp-bizwit' ); ?>
					</p>
					<p>
						<label for="wp-bizwit-wht-ref"><?php esc_html_e( 'Withholding certificate ref.', 'wp-bizwit' ); ?></label>
						<input type="text" name="withholding_ref" id="wp-bizwit-wht-ref" class="regular-text" value="<?php echo esc_attr( $field( 'withholding_ref' ) ); ?>" />
					</p>
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-method"><?php esc_html_e( 'Method', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<select name="method" id="wp-bizwit-method">
					<?php foreach ( $methods as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $method, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-reference"><?php esc_html_e( 'Reference', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<input type="text" name="reference" id="wp-bizwit-reference" class="regular-text" value="<?php echo esc_attr( $field( 'reference' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Bank transfer ref, VA number, or other reconciliation hint.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-notes"><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<textarea name="notes" id="wp-bizwit-notes" class="large-text" rows="3"><?php echo esc_textarea( $field( 'notes' ) ); ?></textarea>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">
			<?php echo $is_edit ? esc_html__( 'Update payment', 'wp-bizwit' ) : esc_html__( 'Record payment', 'wp-bizwit' ); ?>
		</button>
	</p>
</form>
