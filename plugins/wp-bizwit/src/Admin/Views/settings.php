<?php
/**
 * Settings form.
 *
 * Only the handful of settings every user needs are visible: who you are, how
 * to reach you, what currency you bill in, and where payment goes. Tax,
 * document numbering and regional configuration sit in collapsed sections whose
 * summaries show the current state, so nothing is hidden - just out of the way
 * until it is wanted.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Settings_Screen.
 */

use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Localization\Region;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings     = is_array( $data['settings'] ?? null ) ? $data['settings'] : array();
$is_indonesia = ! empty( $data['is_indonesia'] );
$handles_tax  = ! empty( $data['handles_tax'] );
$region       = $data['region'] ?? null;

if ( ! $region instanceof Region ) {
	return;
}

/**
 * Read a stored setting as a string.
 *
 * @param string $key Setting name.
 *
 * @return string Setting value.
 */
$value = static function ( string $key ) use ( $settings ): string {
	return isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
};
?>

<h1><?php esc_html_e( 'BizWit Settings', 'wp-bizwit' ); ?></h1>

<p class="description wp-bizwit-lede">
	<?php esc_html_e( 'Fill in the basics below and you can start adding clients. Everything else is optional and grouped into the sections underneath.', 'wp-bizwit' ); ?>
</p>

<form method="post" class="wp-bizwit-form">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_settings_form" value="1" />

	<h2><?php esc_html_e( 'The basics', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'You are', 'wp-bizwit' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Business type', 'wp-bizwit' ); ?></legend>
					<?php foreach ( (array) $data['business_types'] as $type_slug => $label ) : ?>
						<label class="wp-bizwit-radio">
							<input type="radio" name="business_type" value="<?php echo esc_attr( (string) $type_slug ); ?>"
								<?php checked( $value( 'business_type' ), (string) $type_slug ); ?> />
							<span><?php echo esc_html( (string) $label ); ?></span>
						</label>
						<br />
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Only changes which fields BizWit shows by default. Nothing is ever locked away — every field stays reachable in the sections below.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-name"><?php esc_html_e( 'Your name or business name', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-business-name" name="business_name" class="regular-text" value="<?php echo esc_attr( $value( 'business_name' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Appears at the top of every invoice and receipt you issue.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-email"><?php esc_html_e( 'Email', 'wp-bizwit' ); ?></label></th>
			<td><input type="email" id="wp-bizwit-business-email" name="business_email" class="regular-text" value="<?php echo esc_attr( $value( 'business_email' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-phone"><?php echo esc_html( $region->field_label( 'phone', __( 'Phone', 'wp-bizwit' ) ) ); ?></label></th>
			<td><input type="text" id="wp-bizwit-business-phone" name="business_phone" class="regular-text" value="<?php echo esc_attr( $value( 'business_phone' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-address"><?php esc_html_e( 'Address', 'wp-bizwit' ); ?></label></th>
			<td><textarea id="wp-bizwit-business-address" name="business_address" rows="3" class="large-text"><?php echo esc_textarea( $value( 'business_address' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-settings-currency"><?php esc_html_e( 'Currency', 'wp-bizwit' ); ?></label></th>
			<td>
				<select id="wp-bizwit-settings-currency" name="currency">
					<?php foreach ( (array) $data['currencies'] as $code ) : ?>
						<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $value( 'currency' ), (string) $code ); ?>>
							<?php echo esc_html( (string) $code ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<label for="wp-bizwit-default-terms" class="wp-bizwit-inline-label"><?php echo esc_html( $region->field_label( 'payment_terms_days', __( 'Payment terms', 'wp-bizwit' ) ) ); ?></label>
				<input type="number" id="wp-bizwit-default-terms" name="payment_terms_days" class="small-text" min="0" max="3650" value="<?php echo esc_attr( $value( 'payment_terms_days' ) ); ?>" />
				<?php esc_html_e( 'days', 'wp-bizwit' ); ?>
			</td>
		</tr>
	</table>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Where clients pay you', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint">
				<?php
				$bank = trim( $value( 'bank_name' ) . ' ' . $value( 'bank_account_no' ) );
				echo '' === $bank
					? esc_html__( 'Not set yet', 'wp-bizwit' )
					: esc_html( $bank );
				?>
			</span>
		</summary>
		<div class="wp-bizwit-section__body">
		<p class="description">
			<?php if ( $is_indonesia ) : ?>
				<?php esc_html_e( 'Rekening tujuan transfer yang dicantumkan pada faktur. Hampir semua pembayaran antarperusahaan di Indonesia dilakukan lewat transfer bank.', 'wp-bizwit' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Shown on invoices so clients know where to send payment.', 'wp-bizwit' ); ?>
			<?php endif; ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-bank-name"><?php esc_html_e( 'Bank name', 'wp-bizwit' ); ?></label></th>
				<td><input type="text" id="wp-bizwit-bank-name" name="bank_name" class="regular-text" placeholder="BCA" value="<?php echo esc_attr( $value( 'bank_name' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-bank-account-no"><?php esc_html_e( 'Account number', 'wp-bizwit' ); ?></label></th>
				<td><input type="text" id="wp-bizwit-bank-account-no" name="bank_account_no" class="regular-text" value="<?php echo esc_attr( $value( 'bank_account_no' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-bank-account-name"><?php esc_html_e( 'Account holder', 'wp-bizwit' ); ?></label></th>
				<td><input type="text" id="wp-bizwit-bank-account-name" name="bank_account_name" class="regular-text" value="<?php echo esc_attr( $value( 'bank_account_name' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-bank-branch"><?php esc_html_e( 'Branch', 'wp-bizwit' ); ?></label></th>
				<td><input type="text" id="wp-bizwit-bank-branch" name="bank_branch" class="regular-text" value="<?php echo esc_attr( $value( 'bank_branch' ) ); ?>" /></td>
			</tr>
		</table>
	</div>
	</details>

	<details class="wp-bizwit-section" <?php echo $handles_tax ? 'open' : ''; ?>>
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Tax', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><?php echo esc_html( (string) $data['tax_summary'] ); ?></span>
		</summary>
		<div class="wp-bizwit-section__body">
		<p class="description">
			<?php esc_html_e( 'Many freelancers and small businesses do not need any of this. Leave it switched off and BizWit will not show a single tax field anywhere.', 'wp-bizwit' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-tax-regime"><?php echo $is_indonesia ? esc_html__( 'Status perpajakan', 'wp-bizwit' ) : esc_html__( 'Tax handling', 'wp-bizwit' ); ?></label></th>
				<td>
					<select id="wp-bizwit-tax-regime" name="tax_regime">
						<?php foreach ( (array) $data['tax_regimes'] as $regime => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $regime ); ?>" <?php selected( $value( 'tax_regime' ), (string) $regime ); ?>>
								<?php echo esc_html( (string) $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( $is_indonesia ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: formatted PKP registration threshold */
								esc_html__( 'Hanya PKP yang boleh memungut PPN. Usaha dengan peredaran bruto sampai %s umumnya belum wajib dikukuhkan sebagai PKP.', 'wp-bizwit' ),
								'<strong>' . esc_html( Money::format( Indonesia::PKP_THRESHOLD, 'IDR' ) ) . '</strong>'
							);
							?>
						</p>
						<p class="description">
							<?php esc_html_e( 'PPh Final UMKM 0,5% dihitung dari peredaran bruto Anda sendiri, bukan ditambahkan ke tagihan klien. BizWit tidak akan mencantumkannya pada faktur.', 'wp-bizwit' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-tax-label"><?php esc_html_e( 'Tax label', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-tax-label" name="tax_label" class="regular-text" value="<?php echo esc_attr( $value( 'tax_label' ) ); ?>" />
					<input type="text" name="default_tax_rate" class="small-text" value="<?php echo esc_attr( $value( 'default_tax_rate' ) ); ?>" aria-label="<?php esc_attr_e( 'Default tax rate', 'wp-bizwit' ); ?>" /> %
					<p class="description">
						<?php esc_html_e( 'What the tax is called on your documents and the rate applied to new invoice lines. Only used when your tax status allows you to charge tax. Confirm the current rate with your tax consultant.', 'wp-bizwit' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-withholding-label"><?php esc_html_e( 'Withholding tax', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-withholding-label" name="withholding_label" class="regular-text" value="<?php echo esc_attr( $value( 'withholding_label' ) ); ?>" />
					<input type="text" name="withholding_rate" class="small-text" value="<?php echo esc_attr( $value( 'withholding_rate' ) ); ?>" aria-label="<?php esc_attr_e( 'Withholding rate', 'wp-bizwit' ); ?>" /> %
					<p class="description">
						<?php if ( $is_indonesia ) : ?>
							<?php esc_html_e( 'Klien badan biasanya memotong PPh 23 atas jasa dan menyetorkannya atas nama Anda, sehingga dana yang masuk ke rekening lebih kecil dari nilai faktur.', 'wp-bizwit' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Used when a client withholds tax at source and remits it on your behalf.', 'wp-bizwit' ); ?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-business-tax-id"><?php echo esc_html( $region->field_label( 'tax_id', __( 'Your tax ID', 'wp-bizwit' ) ) ); ?></label></th>
				<td><input type="text" id="wp-bizwit-business-tax-id" name="tax_id" class="regular-text" value="<?php echo esc_attr( $value( 'tax_id' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-business-reg-no"><?php echo esc_html( $region->field_label( 'registration_no', __( 'Registration number', 'wp-bizwit' ) ) ); ?></label></th>
				<td><input type="text" id="wp-bizwit-business-reg-no" name="business_reg_no" class="regular-text" value="<?php echo esc_attr( $value( 'business_reg_no' ) ); ?>" /></td>
			</tr>
		</table>
	</div>
	</details>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Document numbering', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><code><?php echo esc_html( (string) $data['sample_number'] ); ?></code></span>
		</summary>
		<div class="wp-bizwit-section__body">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Number format', 'wp-bizwit' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Number format', 'wp-bizwit' ); ?></legend>
						<label class="wp-bizwit-radio">
							<input type="radio" name="number_format" value="regional" <?php checked( $value( 'number_format' ), 'regional' ); ?> />
							<?php
							printf(
								/* translators: %s: an example document number */
								esc_html__( 'Regional house style (%s)', 'wp-bizwit' ),
								'<code>' . esc_html( (string) $data['sample_number'] ) . '</code>'
							);
							?>
						</label>
						<br />
						<label class="wp-bizwit-radio">
							<input type="radio" name="number_format" value="simple" <?php checked( $value( 'number_format' ), 'simple' ); ?> />
							<?php
							printf(
								/* translators: %s: an example document number */
								esc_html__( 'Simple prefixed sequence (%s)', 'wp-bizwit' ),
								'<code>' . esc_html( $value( 'invoice_prefix' ) . '0001' ) . '</code>'
							);
							?>
						</label>
					</fieldset>
					<?php if ( $is_indonesia ) : ?>
						<p class="description">
							<?php esc_html_e( 'Format Indonesia menyusun nomor sebagai urutan/jenis/kode usaha/bulan romawi/tahun, sehingga bulan dan tahun terbaca langsung dari nomornya.', 'wp-bizwit' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-business-code"><?php esc_html_e( 'Business code', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-business-code" name="business_code" class="small-text" maxlength="12" placeholder="BW" value="<?php echo esc_attr( $value( 'business_code' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Short code for your business, used inside regional document numbers.', 'wp-bizwit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-invoice-prefix"><?php esc_html_e( 'Prefixes', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-invoice-prefix" name="invoice_prefix" class="small-text" value="<?php echo esc_attr( $value( 'invoice_prefix' ) ); ?>" aria-label="<?php esc_attr_e( 'Invoice prefix', 'wp-bizwit' ); ?>" />
					<input type="text" name="receipt_prefix" class="small-text" value="<?php echo esc_attr( $value( 'receipt_prefix' ) ); ?>" aria-label="<?php esc_attr_e( 'Receipt prefix', 'wp-bizwit' ); ?>" />
					<input type="number" name="number_padding" class="small-text" min="1" max="12" value="<?php echo esc_attr( $value( 'number_padding' ) ); ?>" aria-label="<?php esc_attr_e( 'Number padding', 'wp-bizwit' ); ?>" />
					<p class="description"><?php esc_html_e( 'Invoice prefix, receipt prefix, and how many digits to pad the sequence to.', 'wp-bizwit' ); ?></p>
				</td>
			</tr>
			<?php if ( $is_indonesia ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Bea meterai', 'wp-bizwit' ); ?></th>
					<td>
						<div class="wp-bizwit-check-block">
							<label class="wp-bizwit-check" for="wp-bizwit-stamp-duty">
								<input type="checkbox" id="wp-bizwit-stamp-duty" name="apply_stamp_duty" value="1" <?php checked( ! empty( $settings['apply_stamp_duty'] ) ); ?> />
								<span><?php esc_html_e( 'Ingatkan kebutuhan meterai pada kwitansi bernilai besar', 'wp-bizwit' ); ?></span>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: 1: threshold amount, 2: duty amount */
									esc_html__( 'Berdasarkan UU 10/2020, dokumen yang menyebut nilai uang di atas %1$s dikenai bea meterai %2$s.', 'wp-bizwit' ),
									'<strong>' . esc_html( Money::format( Indonesia::METERAI_THRESHOLD, 'IDR' ) ) . '</strong>',
									'<strong>' . esc_html( Money::format( Indonesia::METERAI_AMOUNT, 'IDR' ) ) . '</strong>'
								);
								?>
							</p>
						</div>
					</td>
				</tr>
			<?php endif; ?>
		</table>
	</div>
	</details>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Advanced', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><?php echo esc_html( $region->label() ); ?></span>
		</summary>
		<div class="wp-bizwit-section__body">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-region"><?php esc_html_e( 'Business region', 'wp-bizwit' ); ?></label></th>
				<td>
					<select id="wp-bizwit-region" name="region">
						<?php foreach ( (array) $data['region_choices'] as $choice => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $choice ); ?>" <?php selected( $value( 'region' ), (string) $choice ); ?>>
								<?php echo esc_html( (string) $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
							/* translators: %s: name of the active regional profile */
							esc_html__( 'Sets the terminology, tax fields, address fields and document numbering used throughout BizWit. Currently active: %s.', 'wp-bizwit' ),
							'<strong>' . esc_html( $region->label() ) . '</strong>'
						);
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'This is separate from the interface language, which follows your WordPress site language.', 'wp-bizwit' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-business-country"><?php esc_html_e( 'Country', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-business-country" name="business_country" class="small-text" maxlength="2" placeholder="ID" value="<?php echo esc_attr( $value( 'business_country' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Two-letter ISO 3166-1 code. Used to detect your region automatically.', 'wp-bizwit' ); ?></p>
				</td>
			</tr>
			<?php if ( $is_indonesia ) : ?>
				<tr>
					<th scope="row"><label for="wp-bizwit-scale"><?php esc_html_e( 'Skala usaha', 'wp-bizwit' ); ?></label></th>
					<td>
						<select id="wp-bizwit-scale" name="business_scale">
							<?php foreach ( (array) $data['scales'] as $scale => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $scale ); ?>" <?php selected( $value( 'business_scale' ), (string) $scale ); ?>>
									<?php echo esc_html( (string) $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Klasifikasi menurut PP 7/2021, berdasarkan modal usaha atau hasil penjualan tahunan.', 'wp-bizwit' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'On uninstall', 'wp-bizwit' ); ?></th>
				<td>
					<div class="wp-bizwit-check-block">
						<label class="wp-bizwit-check" for="wp-bizwit-delete-data">
							<input type="checkbox" id="wp-bizwit-delete-data" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
							<span><?php esc_html_e( 'Delete all BizWit data when the plugin is uninstalled', 'wp-bizwit' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Off by default. Deactivating the plugin never touches your data; only uninstalling with this box ticked does.', 'wp-bizwit' ); ?>
						</p>
					</div>
				</td>
			</tr>
		</table>
	</div>
	</details>

	<?php submit_button( __( 'Save settings', 'wp-bizwit' ) ); ?>
</form>
