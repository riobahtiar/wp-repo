<?php
/**
 * Client add/edit form.
 *
 * Field labels, help text, the province list and the extra paperwork fields all
 * come from the active regional profile, so an Indonesian business sees NPWP,
 * NIB, Kelurahan and Provinsi rather than generic international wording.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Clients_Screen.
 */

use WP_BizWit\Localization\Region;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$client      = is_array( $data['client'] ?? null ) ? $data['client'] : array();
$client_meta = is_array( $data['meta'] ?? null ) ? $data['meta'] : array();
$is_edit     = ! empty( $data['is_edit'] );
$defaults    = is_array( $data['defaults'] ?? null ) ? $data['defaults'] : array();
$meta_fields = is_array( $data['meta_fields'] ?? null ) ? $data['meta_fields'] : array();
$provinces   = is_array( $data['provinces'] ?? null ) ? $data['provinces'] : array();
$region      = $data['region'] ?? null;

if ( ! $region instanceof Region ) {
	return;
}

/**
 * Read a field from the record being edited, falling back to a default.
 *
 * @param string $key      Field name.
 * @param string $fallback Value used when the field is absent or empty.
 *
 * @return string Field value.
 */
$field = static function ( string $key, string $fallback = '' ) use ( $client ): string {
	$value = isset( $client[ $key ] ) ? (string) $client[ $key ] : '';

	return '' === $value ? $fallback : $value;
};
?>

<h1 class="wp-heading-inline">
	<?php echo $is_edit ? esc_html__( 'Edit client', 'wp-bizwit' ) : esc_html__( 'Add client', 'wp-bizwit' ); ?>
</h1>

<a href="<?php echo esc_url( (string) $data['list_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Back to clients', 'wp-bizwit' ); ?>
</a>

<hr class="wp-header-end" />

<form method="post">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_client_form" value="1" />
	<input type="hidden" name="client_id" value="<?php echo esc_attr( $field( 'id', '0' ) ); ?>" />

	<h2><?php esc_html_e( 'Identity', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wp-bizwit-display-name"><?php esc_html_e( 'Name', 'wp-bizwit' ); ?> <span class="description">(<?php esc_html_e( 'required', 'wp-bizwit' ); ?>)</span></label>
			</th>
			<td>
				<input type="text" id="wp-bizwit-display-name" name="display_name" class="regular-text" required
					value="<?php echo esc_attr( $field( 'display_name' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'How this client appears throughout BizWit and on documents.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-type"><?php esc_html_e( 'Type', 'wp-bizwit' ); ?></label></th>
			<td>
				<select id="wp-bizwit-type" name="type">
					<?php foreach ( (array) $data['types'] as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $field( 'type', 'company' ), (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-status"><?php esc_html_e( 'Status', 'wp-bizwit' ); ?></label></th>
			<td>
				<select id="wp-bizwit-status" name="status">
					<?php foreach ( (array) $data['statuses'] as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $field( 'status', 'active' ), (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Archive a client instead of deleting it to keep its invoices and payments intact.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-legal-name"><?php echo esc_html( $region->field_label( 'legal_name', __( 'Legal name', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-legal-name" name="legal_name" class="regular-text"
					value="<?php echo esc_attr( $field( 'legal_name' ) ); ?>" />
				<p class="description">
					<?php echo esc_html( $region->field_description( 'legal_name', __( 'Registered name, if it differs from the name above. Used on invoices.', 'wp-bizwit' ) ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-tax-id"><?php echo esc_html( $region->field_label( 'tax_id', __( 'Tax ID', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-tax-id" name="tax_id" class="regular-text"
					value="<?php echo esc_attr( $field( 'tax_id' ) ); ?>" />
				<?php $tax_help = $region->field_description( 'tax_id' ); ?>
				<?php if ( '' !== $tax_help ) : ?>
					<p class="description"><?php echo esc_html( $tax_help ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-registration-no"><?php echo esc_html( $region->field_label( 'registration_no', __( 'Registration number', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-registration-no" name="registration_no" class="regular-text"
					value="<?php echo esc_attr( $field( 'registration_no' ) ); ?>" />
				<p class="description">
					<?php echo esc_html( $region->field_description( 'registration_no', __( 'Company number, agency code or other official identifier.', 'wp-bizwit' ) ) ); ?>
				</p>
			</td>
		</tr>

		<?php foreach ( $meta_fields as $meta_key => $meta_field ) : ?>
			<?php
			// Address components belong in the address section further down.
			if ( in_array( $meta_key, array( 'rt_rw', 'kelurahan', 'kecamatan' ), true ) ) {
				continue;
			}

			$meta_id    = 'wp-bizwit-meta-' . str_replace( '_', '-', (string) $meta_key );
			$meta_value = $client_meta[ $meta_key ] ?? '';
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $meta_id ); ?>"><?php echo esc_html( (string) $meta_field['label'] ); ?></label></th>
				<td>
					<?php if ( 'checkbox' === $meta_field['type'] ) : ?>
						<label for="<?php echo esc_attr( $meta_id ); ?>">
							<input type="checkbox" id="<?php echo esc_attr( $meta_id ); ?>"
								name="meta_<?php echo esc_attr( (string) $meta_key ); ?>" value="1"
								<?php checked( ! empty( $meta_value ) ); ?> />
							<?php echo esc_html( (string) $meta_field['label'] ); ?>
						</label>
					<?php elseif ( 'select' === $meta_field['type'] ) : ?>
						<select id="<?php echo esc_attr( $meta_id ); ?>" name="meta_<?php echo esc_attr( (string) $meta_key ); ?>">
							<?php foreach ( (array) ( $meta_field['options'] ?? array() ) as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( (string) $meta_value, (string) $option_value ); ?>>
									<?php echo esc_html( (string) $option_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" id="<?php echo esc_attr( $meta_id ); ?>"
							name="meta_<?php echo esc_attr( (string) $meta_key ); ?>" class="regular-text"
							<?php if ( isset( $meta_field['maxlength'] ) ) : ?>
								maxlength="<?php echo esc_attr( (string) $meta_field['maxlength'] ); ?>"
							<?php endif; ?>
							value="<?php echo esc_attr( (string) $meta_value ); ?>" />
					<?php endif; ?>

					<?php if ( ! empty( $meta_field['description'] ) ) : ?>
						<p class="description"><?php echo esc_html( (string) $meta_field['description'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>

	<h2><?php esc_html_e( 'Contact', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-email"><?php esc_html_e( 'Email', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="email" id="wp-bizwit-email" name="email" class="regular-text"
					value="<?php echo esc_attr( $field( 'email' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-phone"><?php esc_html_e( 'Phone', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-phone" name="phone" class="regular-text"
					value="<?php echo esc_attr( $field( 'phone' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-website"><?php esc_html_e( 'Website', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="url" id="wp-bizwit-website" name="website" class="regular-text"
					value="<?php echo esc_attr( $field( 'website' ) ); ?>" />
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Address', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-address-1"><?php echo esc_html( $region->field_label( 'address_line1', __( 'Address', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-address-1" name="address_line1" class="regular-text"
					value="<?php echo esc_attr( $field( 'address_line1' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-address-2"><?php echo esc_html( $region->field_label( 'address_line2', __( 'Address line 2', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-address-2" name="address_line2" class="regular-text"
					value="<?php echo esc_attr( $field( 'address_line2' ) ); ?>" />
			</td>
		</tr>

		<?php foreach ( array( 'rt_rw', 'kelurahan', 'kecamatan' ) as $address_key ) : ?>
			<?php
			if ( ! isset( $meta_fields[ $address_key ] ) ) {
				continue;
			}

			$address_field = $meta_fields[ $address_key ];
			$address_id    = 'wp-bizwit-meta-' . str_replace( '_', '-', $address_key );
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $address_id ); ?>"><?php echo esc_html( (string) $address_field['label'] ); ?></label></th>
				<td>
					<input type="text" id="<?php echo esc_attr( $address_id ); ?>"
						name="meta_<?php echo esc_attr( $address_key ); ?>" class="regular-text"
						maxlength="<?php echo esc_attr( (string) ( $address_field['maxlength'] ?? 191 ) ); ?>"
						value="<?php echo esc_attr( (string) ( $client_meta[ $address_key ] ?? '' ) ); ?>" />
				</td>
			</tr>
		<?php endforeach; ?>

		<tr>
			<th scope="row"><label for="wp-bizwit-city"><?php echo esc_html( $region->field_label( 'city', __( 'City', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-city" name="city" class="regular-text"
					value="<?php echo esc_attr( $field( 'city' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-state"><?php echo esc_html( $region->field_label( 'state', __( 'State or province', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<?php if ( array() !== $provinces ) : ?>
					<select id="wp-bizwit-state" name="state">
						<option value=""><?php esc_html_e( '— Select —', 'wp-bizwit' ); ?></option>
						<?php foreach ( $provinces as $province ) : ?>
							<option value="<?php echo esc_attr( (string) $province ); ?>" <?php selected( $field( 'state' ), (string) $province ); ?>>
								<?php echo esc_html( (string) $province ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" id="wp-bizwit-state" name="state" class="regular-text"
						value="<?php echo esc_attr( $field( 'state' ) ); ?>" />
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-postal-code"><?php echo esc_html( $region->field_label( 'postal_code', __( 'Postal code', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-postal-code" name="postal_code" class="small-text"
					value="<?php echo esc_attr( $field( 'postal_code' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-country"><?php esc_html_e( 'Country', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-country" name="country" class="small-text" maxlength="2"
					placeholder="ID" value="<?php echo esc_attr( $field( 'country' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Two-letter ISO 3166-1 country code.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Billing', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-currency"><?php esc_html_e( 'Currency', 'wp-bizwit' ); ?></label></th>
			<td>
				<select id="wp-bizwit-currency" name="currency">
					<?php
					$selected_currency = $field( 'currency', (string) ( $defaults['currency'] ?? 'IDR' ) );
					foreach ( (array) $data['currencies'] as $code ) :
						?>
						<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $selected_currency, (string) $code ); ?>>
							<?php echo esc_html( (string) $code ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Invoices for this client default to this currency.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-terms"><?php echo esc_html( $region->field_label( 'payment_terms_days', __( 'Payment terms', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="number" id="wp-bizwit-terms" name="payment_terms_days" class="small-text" min="0" max="3650"
					value="<?php echo esc_attr( $field( 'payment_terms_days', (string) ( $defaults['payment_terms_days'] ?? 30 ) ) ); ?>" />
				<?php esc_html_e( 'days', 'wp-bizwit' ); ?>
				<p class="description">
					<?php echo esc_html( $region->field_description( 'payment_terms_days', __( 'Used to calculate the due date on new invoices.', 'wp-bizwit' ) ) ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wp-bizwit-notes"><?php esc_html_e( 'Internal notes', 'wp-bizwit' ); ?></label></th>
			<td>
				<textarea id="wp-bizwit-notes" name="notes" rows="5" class="large-text"><?php echo esc_textarea( $field( 'notes' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Never shown to the client.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( $is_edit ? __( 'Update client', 'wp-bizwit' ) : __( 'Add client', 'wp-bizwit' ) ); ?>
</form>
