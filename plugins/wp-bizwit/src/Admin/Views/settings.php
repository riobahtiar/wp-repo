<?php
/**
 * Settings form.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Settings_Screen.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings = is_array( $data['settings'] ?? null ) ? $data['settings'] : array();

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

<form method="post">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_settings_form" value="1" />

	<h2><?php esc_html_e( 'Your business', 'wp-bizwit' ); ?></h2>
	<p class="description"><?php esc_html_e( 'These details appear on the invoices and receipts you issue.', 'wp-bizwit' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-business-name"><?php esc_html_e( 'Business name', 'wp-bizwit' ); ?></label></th>
			<td><input type="text" id="wp-bizwit-business-name" name="business_name" class="regular-text" value="<?php echo esc_attr( $value( 'business_name' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-email"><?php esc_html_e( 'Business email', 'wp-bizwit' ); ?></label></th>
			<td><input type="email" id="wp-bizwit-business-email" name="business_email" class="regular-text" value="<?php echo esc_attr( $value( 'business_email' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-phone"><?php esc_html_e( 'Business phone', 'wp-bizwit' ); ?></label></th>
			<td><input type="text" id="wp-bizwit-business-phone" name="business_phone" class="regular-text" value="<?php echo esc_attr( $value( 'business_phone' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-address"><?php esc_html_e( 'Business address', 'wp-bizwit' ); ?></label></th>
			<td><textarea id="wp-bizwit-business-address" name="business_address" rows="4" class="large-text"><?php echo esc_textarea( $value( 'business_address' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-business-tax-id"><?php esc_html_e( 'Your tax ID', 'wp-bizwit' ); ?></label></th>
			<td><input type="text" id="wp-bizwit-business-tax-id" name="tax_id" class="regular-text" value="<?php echo esc_attr( $value( 'tax_id' ) ); ?>" /></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Money', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-settings-currency"><?php esc_html_e( 'Default currency', 'wp-bizwit' ); ?></label></th>
			<td>
				<select id="wp-bizwit-settings-currency" name="currency">
					<?php foreach ( (array) $data['currencies'] as $code ) : ?>
						<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $value( 'currency' ), (string) $code ); ?>>
							<?php echo esc_html( (string) $code ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-tax-label"><?php esc_html_e( 'Tax label', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-tax-label" name="tax_label" class="regular-text" value="<?php echo esc_attr( $value( 'tax_label' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'What tax is called on your documents, for example VAT, GST or PPN.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-default-tax-rate"><?php esc_html_e( 'Default tax rate', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-default-tax-rate" name="default_tax_rate" class="small-text" value="<?php echo esc_attr( $value( 'default_tax_rate' ) ); ?>" /> %
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-default-terms"><?php esc_html_e( 'Default payment terms', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="number" id="wp-bizwit-default-terms" name="payment_terms_days" class="small-text" min="0" max="3650" value="<?php echo esc_attr( $value( 'payment_terms_days' ) ); ?>" />
				<?php esc_html_e( 'days', 'wp-bizwit' ); ?>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Document numbering', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-invoice-prefix"><?php esc_html_e( 'Invoice prefix', 'wp-bizwit' ); ?></label></th>
			<td><input type="text" id="wp-bizwit-invoice-prefix" name="invoice_prefix" class="small-text" value="<?php echo esc_attr( $value( 'invoice_prefix' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-receipt-prefix"><?php esc_html_e( 'Receipt prefix', 'wp-bizwit' ); ?></label></th>
			<td><input type="text" id="wp-bizwit-receipt-prefix" name="receipt_prefix" class="small-text" value="<?php echo esc_attr( $value( 'receipt_prefix' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-number-padding"><?php esc_html_e( 'Number padding', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="number" id="wp-bizwit-number-padding" name="number_padding" class="small-text" min="1" max="12" value="<?php echo esc_attr( $value( 'number_padding' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Digits to pad sequential numbers to. A padding of 4 gives INV-0007.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Data', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'On uninstall', 'wp-bizwit' ); ?></th>
			<td>
				<label for="wp-bizwit-delete-data">
					<input type="checkbox" id="wp-bizwit-delete-data" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
					<?php esc_html_e( 'Delete all BizWit data when the plugin is uninstalled', 'wp-bizwit' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Off by default. Deactivating the plugin never touches your data; only uninstalling with this box ticked does.', 'wp-bizwit' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save settings', 'wp-bizwit' ) ); ?>
</form>
