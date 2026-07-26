<?php
/**
 * Invoice add/edit form with line-item repeater.
 *
 * Line items are plain PHP rows (same pattern as project termin). Totals are
 * recomputed server-side on save — never trust posted totals.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Invoices_Screen.
 */

use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$invoice  = is_array( $data['invoice'] ?? null ) ? $data['invoice'] : array();
$items    = is_array( $data['items'] ?? null ) ? $data['items'] : array();
$is_edit  = ! empty( $data['is_edit'] );
$locked   = ! empty( $data['locked'] );
$defaults = is_array( $data['defaults'] ?? null ) ? $data['defaults'] : array();

$charges_sales_tax = ! empty( $data['charges_sales_tax'] );
$handles_tax       = ! empty( $data['handles_tax'] );
$tax_label         = (string) ( $data['tax_label'] ?? 'PPN' );
$default_tax_rate  = (string) ( $data['default_tax_rate'] ?? '0' );
$withholding_label = (string) ( $data['withholding_label'] ?? 'PPh 23' );
$default_wht_rate  = (string) ( $data['withholding_rate'] ?? '2' );

/**
 * Read a field from the invoice record.
 *
 * @param string $key      Field name.
 * @param string $fallback Default.
 *
 * @return string
 */
$field = static function ( string $key, string $fallback = '' ) use ( $invoice ): string {
	if ( ! array_key_exists( $key, $invoice ) || null === $invoice[ $key ] ) {
		return $fallback;
	}
	$value = (string) $invoice[ $key ];

	return '' === $value ? $fallback : $value;
};

$currency       = $field( 'currency', (string) ( $defaults['currency'] ?? 'IDR' ) );
$invoice_status = $field( 'status', (string) ( $defaults['status'] ?? Invoice_Status::DRAFT ) );
$issue_date     = $field( 'issue_date', (string) ( $defaults['issue_date'] ?? '' ) );
$due_date       = $field( 'due_date', '' );
$client_id      = (int) ( $invoice['client_id'] ?? 0 );
$project_id     = (int) ( $invoice['project_id'] ?? 0 );
$discount       = Money::to_input( (int) ( $invoice['discount_minor'] ?? 0 ), $currency );
$wht_rate       = $field( 'withholding_rate', '0' );
$wht_on         = (int) ( $invoice['withholding_minor'] ?? 0 ) > 0 || (float) $wht_rate > 0;
if ( '0.0000' === $wht_rate || '0' === $wht_rate ) {
	$wht_rate = $default_wht_rate;
}

// Display quantity without forced trailing zeros for inputs.
$fmt_qty = static function ( $qty ): string {
	$s = rtrim( rtrim( number_format( (float) $qty, 4, '.', '' ), '0' ), '.' );

	return '' === $s ? '1' : $s;
};

$form_items = $items;
if ( ! $locked ) {
	// Always offer one blank row for a new line.
	$form_items[] = array(
		'description'      => '',
		'quantity'         => '1',
		'unit'             => '',
		'unit_price_minor' => 0,
		'tax_rate'         => $default_tax_rate,
	);
}

if ( array() === $form_items ) {
	$form_items[] = array(
		'description'      => '',
		'quantity'         => '1',
		'unit'             => '',
		'unit_price_minor' => 0,
		'tax_rate'         => $default_tax_rate,
	);
}

$subtotal   = (int) ( $invoice['subtotal_minor'] ?? 0 );
$tax_amount = (int) ( $invoice['tax_minor'] ?? 0 );
$total      = (int) ( $invoice['total_minor'] ?? 0 );
$paid       = (int) ( $invoice['paid_minor'] ?? 0 );
$wht        = (int) ( $invoice['withholding_minor'] ?? 0 );
$balance    = Invoice_Totals::balance_minor( $total, $paid );
$net        = $total - $wht;

$money_example = Money::to_input( 1500000, $currency );
if ( '' === $money_example ) {
	$money_example = '1.500.000';
}

$print_url = '';
if ( $is_edit && ! empty( $invoice['id'] ) ) {
	$print_url = add_query_arg(
		array(
			'page'    => 'wp-bizwit-invoices',
			'action'  => 'print',
			'invoice' => (int) $invoice['id'],
		),
		admin_url( 'admin.php' )
	);
}

$statuses = is_array( $data['statuses'] ?? null ) ? $data['statuses'] : array();
?>

<h1 class="wp-heading-inline">
	<?php echo $is_edit ? esc_html__( 'Edit invoice', 'wp-bizwit' ) : esc_html__( 'Add invoice', 'wp-bizwit' ); ?>
</h1>

<a href="<?php echo esc_url( (string) $data['list_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Back to invoices', 'wp-bizwit' ); ?>
</a>

<?php if ( '' !== $print_url ) : ?>
	<a href="<?php echo esc_url( $print_url ); ?>" class="page-title-action" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Print', 'wp-bizwit' ); ?>
	</a>
<?php endif; ?>
<?php
if (
	$is_edit
	&& ! empty( $invoice['id'] )
	&& Invoice_Status::DRAFT !== $invoice_status
	&& Invoice_Status::VOID !== $invoice_status
) :
	$pay_url = add_query_arg(
		array(
			'page'       => 'wp-bizwit-payments',
			'action'     => 'new',
			'invoice_id' => (int) $invoice['id'],
		),
		admin_url( 'admin.php' )
	);
	?>
	<a href="<?php echo esc_url( $pay_url ); ?>" class="page-title-action">
		<?php esc_html_e( 'Record payment', 'wp-bizwit' ); ?>
	</a>
<?php endif; ?>

<hr class="wp-header-end" />

<?php if ( $locked ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: invoice status label */
					__( 'This invoice is %s. Line items and amounts are locked. You can still update status, due date, notes and terms — or void the invoice.', 'wp-bizwit' ),
					$statuses[ $invoice_status ] ?? $invoice_status
				)
			);
			?>
		</p>
	</div>
<?php endif; ?>

<form method="post" class="wp-bizwit-form" id="wp-bizwit-invoice-form">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_invoice_form" value="1" />
	<input type="hidden" name="invoice_id" value="<?php echo esc_attr( (string) ( $invoice['id'] ?? 0 ) ); ?>" />

	<?php if ( $is_edit && ! empty( $invoice['invoice_number'] ) ) : ?>
		<p class="description">
			<?php
			echo esc_html__( 'Number:', 'wp-bizwit' ) . ' ';
			echo '<strong>' . esc_html( (string) $invoice['invoice_number'] ) . '</strong>';
			?>
		</p>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wp-bizwit-client"><?php esc_html_e( 'Client', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<select name="client_id" id="wp-bizwit-client" required <?php disabled( $locked ); ?>>
					<option value="0"><?php esc_html_e( '— Select client —', 'wp-bizwit' ); ?></option>
					<?php foreach ( (array) $data['client_options'] as $cid => $cname ) : ?>
						<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $client_id, (int) $cid ); ?>>
							<?php echo esc_html( (string) $cname ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-project"><?php esc_html_e( 'Project', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<select name="project_id" id="wp-bizwit-project" <?php disabled( $locked ); ?>>
					<option value="0"><?php esc_html_e( '— No project —', 'wp-bizwit' ); ?></option>
					<?php foreach ( (array) $data['project_options'] as $pid => $pname ) : ?>
						<option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( $project_id, (int) $pid ); ?>>
							<?php echo esc_html( (string) $pname ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Optional. Link this invoice to a project when billing staged work.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-status"><?php esc_html_e( 'Status', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<select name="status" id="wp-bizwit-status">
					<?php
					// When locked, only show current + allowed transitions.
					$allowed = array( $invoice_status );
					if ( $locked ) {
						$allowed = array_unique(
							array_merge(
								array( $invoice_status ),
								Invoice_Status::allowed_transitions( $invoice_status )
							)
						);
					}
					foreach ( $statuses as $slug => $label ) {
						if ( $locked && ! in_array( $slug, $allowed, true ) ) {
							continue;
						}
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( $slug ),
							selected( $invoice_status, $slug, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-issue-date"><?php esc_html_e( 'Issue date', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<input type="date" name="issue_date" id="wp-bizwit-issue-date" value="<?php echo esc_attr( $issue_date ); ?>" <?php disabled( $locked ); ?> />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-due-date"><?php esc_html_e( 'Due date', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<input type="date" name="due_date" id="wp-bizwit-due-date" value="<?php echo esc_attr( $due_date ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-currency"><?php esc_html_e( 'Currency', 'wp-bizwit' ); ?></label>
			</th>
			<td>
				<select name="currency" id="wp-bizwit-currency" <?php disabled( $locked ); ?>>
					<?php foreach ( (array) $data['currencies'] as $code ) : ?>
						<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $currency, (string) $code ); ?>>
							<?php echo esc_html( (string) $code ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<details class="wp-bizwit-section" open>
		<summary class="wp-bizwit-section__summary">
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Line items', 'wp-bizwit' ); ?></span>
		</summary>
		<div class="wp-bizwit-section__body">
			<table class="widefat striped wp-bizwit-items" id="wp-bizwit-items">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Description', 'wp-bizwit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Qty', 'wp-bizwit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Unit', 'wp-bizwit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Unit price', 'wp-bizwit' ); ?></th>
						<?php if ( $charges_sales_tax ) : ?>
							<th scope="col">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: tax label e.g. PPN */
										__( '%s %%', 'wp-bizwit' ),
										$tax_label
									)
								);
								?>
							</th>
						<?php endif; ?>
						<th scope="col"><?php esc_html_e( 'Line total', 'wp-bizwit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $form_items as $i => $item ) : ?>
						<?php
						$desc  = (string) ( $item['description'] ?? '' );
						$qty   = $fmt_qty( $item['quantity'] ?? '1' );
						$unit  = (string) ( $item['unit'] ?? '' );
						$price = Money::to_input( (int) ( $item['unit_price_minor'] ?? 0 ), $currency );
						$rate  = rtrim( rtrim( number_format( (float) ( $item['tax_rate'] ?? $default_tax_rate ), 4, '.', '' ), '0' ), '.' );
						if ( '' === $rate ) {
							$rate = '0';
						}
						$line_total = (int) ( $item['line_total_minor'] ?? 0 );
						?>
						<tr>
							<td>
								<input type="text" class="large-text" name="items[<?php echo esc_attr( (string) $i ); ?>][description]" value="<?php echo esc_attr( $desc ); ?>" <?php disabled( $locked ); ?> <?php echo 0 === $i && ! $locked ? 'required' : ''; ?> />
							</td>
							<td>
								<input type="text" class="small-text" name="items[<?php echo esc_attr( (string) $i ); ?>][quantity]" value="<?php echo esc_attr( $qty ); ?>" <?php disabled( $locked ); ?> />
							</td>
							<td>
								<input type="text" class="small-text" name="items[<?php echo esc_attr( (string) $i ); ?>][unit]" value="<?php echo esc_attr( $unit ); ?>" placeholder="<?php esc_attr_e( 'pcs', 'wp-bizwit' ); ?>" <?php disabled( $locked ); ?> />
							</td>
							<td>
								<input type="text" class="regular-text" name="items[<?php echo esc_attr( (string) $i ); ?>][unit_price]" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php echo esc_attr( $money_example ); ?>" <?php disabled( $locked ); ?> />
							</td>
							<?php if ( $charges_sales_tax ) : ?>
								<td>
									<input type="text" class="small-text" name="items[<?php echo esc_attr( (string) $i ); ?>][tax_rate]" value="<?php echo esc_attr( $rate ); ?>" <?php disabled( $locked ); ?> />
								</td>
							<?php endif; ?>
							<td class="wp-bizwit-items__total">
								<?php
								echo $line_total > 0
									? esc_html( Money::format( $line_total, $currency ) )
									: '&mdash;';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( ! $locked ) : ?>
				<p class="description">
					<?php esc_html_e( 'Leave a description blank to drop that row. Save to recompute totals. Amounts use whole rupiah when the currency is IDR.', 'wp-bizwit' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</details>

	<details class="wp-bizwit-section" open>
		<summary class="wp-bizwit-section__summary">
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Totals', 'wp-bizwit' ); ?></span>
		</summary>
		<div class="wp-bizwit-section__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wp-bizwit-discount"><?php esc_html_e( 'Discount', 'wp-bizwit' ); ?></label>
					</th>
					<td>
						<input type="text" name="discount" id="wp-bizwit-discount" class="regular-text" value="<?php echo esc_attr( $discount ); ?>" placeholder="<?php echo esc_attr( $money_example ); ?>" <?php disabled( $locked ); ?> />
						<p class="description"><?php esc_html_e( 'Header discount applied after line totals (before tax scaling).', 'wp-bizwit' ); ?></p>
					</td>
				</tr>
				<?php if ( $handles_tax ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $withholding_label ); ?></th>
						<td>
							<label class="wp-bizwit-check">
								<input type="checkbox" name="apply_withholding" value="1" <?php checked( $wht_on ); ?> <?php disabled( $locked ); ?> />
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: withholding label e.g. PPh 23 */
										__( 'Client withholds %s on this invoice', 'wp-bizwit' ),
										$withholding_label
									)
								);
								?>
							</label>
							<p>
								<label for="wp-bizwit-wht-rate">
									<?php esc_html_e( 'Rate (%)', 'wp-bizwit' ); ?>
								</label>
								<input type="text" name="withholding_rate" id="wp-bizwit-wht-rate" class="small-text" value="<?php echo esc_attr( rtrim( rtrim( $wht_rate, '0' ), '.' ) ?: '0' ); ?>" <?php disabled( $locked ); ?> />
							</p>
							<p class="description">
								<?php esc_html_e( 'Shown as gross, withheld and net expected so bank credits can be reconciled.', 'wp-bizwit' ); ?>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<?php if ( $is_edit ) : ?>
				<table class="widefat" style="max-width: 24rem;">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Subtotal', 'wp-bizwit' ); ?></th>
							<td><?php echo esc_html( Money::format( $subtotal, $currency ) ); ?></td>
						</tr>
						<?php if ( (int) ( $invoice['discount_minor'] ?? 0 ) > 0 ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Discount', 'wp-bizwit' ); ?></th>
								<td>− <?php echo esc_html( Money::format( (int) $invoice['discount_minor'], $currency ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $charges_sales_tax || $tax_amount > 0 ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $tax_label ); ?></th>
								<td><?php echo esc_html( Money::format( $tax_amount, $currency ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<th scope="row"><strong><?php esc_html_e( 'Total', 'wp-bizwit' ); ?></strong></th>
							<td><strong><?php echo esc_html( Money::format( $total, $currency ) ); ?></strong></td>
						</tr>
						<?php if ( $wht > 0 ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $withholding_label ); ?></th>
								<td>− <?php echo esc_html( Money::format( $wht, $currency ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Net expected', 'wp-bizwit' ); ?></th>
								<td><?php echo esc_html( Money::format( $net, $currency ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $paid > 0 ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Paid', 'wp-bizwit' ); ?></th>
								<td><?php echo esc_html( Money::format( $paid, $currency ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Balance', 'wp-bizwit' ); ?></th>
								<td><?php echo esc_html( Money::format( $balance, $currency ) ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Totals are calculated when you save.', 'wp-bizwit' ); ?></p>
			<?php endif; ?>
		</div>
	</details>

	<details class="wp-bizwit-section">
		<summary class="wp-bizwit-section__summary">
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Notes and terms', 'wp-bizwit' ); ?></span>
		</summary>
		<div class="wp-bizwit-section__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wp-bizwit-notes"><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></label>
					</th>
					<td>
						<textarea name="notes" id="wp-bizwit-notes" class="large-text" rows="3"><?php echo esc_textarea( $field( 'notes' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp-bizwit-terms"><?php esc_html_e( 'Terms', 'wp-bizwit' ); ?></label>
					</th>
					<td>
						<textarea name="terms" id="wp-bizwit-terms" class="large-text" rows="3"><?php echo esc_textarea( $field( 'terms' ) ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>
	</details>

	<p class="submit">
		<button type="submit" class="button button-primary">
			<?php echo $is_edit ? esc_html__( 'Update invoice', 'wp-bizwit' ) : esc_html__( 'Create invoice', 'wp-bizwit' ); ?>
		</button>
	</p>
</form>
