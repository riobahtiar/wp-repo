<?php
/**
 * Client add/edit form.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Clients_Screen.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$client   = is_array( $data['client'] ?? null ) ? $data['client'] : array();
$is_edit  = ! empty( $data['is_edit'] );
$defaults = is_array( $data['defaults'] ?? null ) ? $data['defaults'] : array();

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
			<th scope="row"><label for="wp-bizwit-legal-name"><?php esc_html_e( 'Legal name', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-legal-name" name="legal_name" class="regular-text"
					value="<?php echo esc_attr( $field( 'legal_name' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Registered name, if it differs from the name above. Used on invoices.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-tax-id"><?php esc_html_e( 'Tax ID', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-tax-id" name="tax_id" class="regular-text"
					value="<?php echo esc_attr( $field( 'tax_id' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-registration-no"><?php esc_html_e( 'Registration number', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-registration-no" name="registration_no" class="regular-text"
					value="<?php echo esc_attr( $field( 'registration_no' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Company number, agency code or other official identifier.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
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
			<th scope="row"><label for="wp-bizwit-address-1"><?php esc_html_e( 'Address', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-address-1" name="address_line1" class="regular-text"
					value="<?php echo esc_attr( $field( 'address_line1' ) ); ?>" />
				<br />
				<input type="text" name="address_line2" class="regular-text"
					aria-label="<?php esc_attr_e( 'Address line 2', 'wp-bizwit' ); ?>"
					value="<?php echo esc_attr( $field( 'address_line2' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-city"><?php esc_html_e( 'City', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-city" name="city" class="regular-text"
					value="<?php echo esc_attr( $field( 'city' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-state"><?php esc_html_e( 'State or province', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-state" name="state" class="regular-text"
					value="<?php echo esc_attr( $field( 'state' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-postal-code"><?php esc_html_e( 'Postal code', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-postal-code" name="postal_code" class="small-text"
					value="<?php echo esc_attr( $field( 'postal_code' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-country"><?php esc_html_e( 'Country', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-country" name="country" class="small-text" maxlength="2"
					placeholder="US" value="<?php echo esc_attr( $field( 'country' ) ); ?>" />
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
					$selected_currency = $field( 'currency', (string) ( $defaults['currency'] ?? 'USD' ) );
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
			<th scope="row"><label for="wp-bizwit-terms"><?php esc_html_e( 'Payment terms', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="number" id="wp-bizwit-terms" name="payment_terms_days" class="small-text" min="0" max="3650"
					value="<?php echo esc_attr( $field( 'payment_terms_days', (string) ( $defaults['payment_terms_days'] ?? 30 ) ) ); ?>" />
				<?php esc_html_e( 'days', 'wp-bizwit' ); ?>
				<p class="description"><?php esc_html_e( 'Used to calculate the due date on new invoices.', 'wp-bizwit' ); ?></p>
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
