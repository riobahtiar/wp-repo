<?php
/**
 * Client add/edit form.
 *
 * Deliberately short by default. Adding a client requires a name and nothing
 * else; address, tax identity and billing terms sit in collapsed sections that
 * only expand for the users who need them. A freelancer saving a client's name
 * and WhatsApp number should never have to scroll past an NPWP field.
 *
 * Sections use native `<details>` rather than a JavaScript accordion, so
 * disclosure works with keyboard, screen readers, printing and no-JS alike.
 *
 * Field labels, help text, the province list and the extra paperwork fields all
 * come from the active regional profile.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Clients_Screen.
 */

use WP_BizWit\Admin\Screens\Projects_Screen;
use WP_BizWit\Localization\Region;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Capabilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$client      = is_array( $data['client'] ?? null ) ? $data['client'] : array();
$client_meta = is_array( $data['meta'] ?? null ) ? $data['meta'] : array();
$is_edit     = ! empty( $data['is_edit'] );
$defaults    = is_array( $data['defaults'] ?? null ) ? $data['defaults'] : array();
$meta_fields = is_array( $data['meta_fields'] ?? null ) ? $data['meta_fields'] : array();
$provinces   = is_array( $data['provinces'] ?? null ) ? $data['provinces'] : array();
$handles_tax = ! empty( $data['handles_tax'] );
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

/**
 * Render one region meta field as a form-table row.
 *
 * @param string               $key        Meta field key.
 * @param array<string, mixed> $definition Field definition from the region.
 * @param array<string, mixed> $values     Stored meta values.
 *
 * @return void
 */
$meta_row = static function ( string $key, array $definition, array $values ): void {
	$id      = 'wp-bizwit-meta-' . str_replace( '_', '-', $key );
	$value   = $values[ $key ] ?? '';
	$kind    = (string) $definition['type'];
	$label   = (string) $definition['label'];
	$name    = 'meta_' . $key;
	$explain = (string) ( $definition['description'] ?? '' );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<?php if ( 'checkbox' === $kind ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="1" <?php checked( ! empty( $value ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php elseif ( 'select' === $kind ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( (array) ( $definition['options'] ?? array() ) as $option => $option_label ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>>
							<?php echo esc_html( (string) $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
					class="regular-text"
					<?php if ( isset( $definition['maxlength'] ) ) : ?>
						maxlength="<?php echo esc_attr( (string) $definition['maxlength'] ); ?>"
					<?php endif; ?>
					value="<?php echo esc_attr( (string) $value ); ?>" />
			<?php endif; ?>

			<?php if ( '' !== $explain ) : ?>
				<p class="description"><?php echo esc_html( $explain ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
};

// Split region fields by where they belong in the form.
$address_keys  = array( 'rt_rw', 'kelurahan', 'kecamatan' );
$identity_meta = array();
$address_meta  = array();

foreach ( $meta_fields as $meta_key => $definition ) {
	if ( ! empty( $definition['tax_only'] ) && ! $handles_tax ) {
		continue;
	}

	if ( in_array( $meta_key, $address_keys, true ) ) {
		$address_meta[ $meta_key ] = $definition;
		continue;
	}

	$identity_meta[ $meta_key ] = $definition;
}
?>

<h1 class="wp-heading-inline">
	<?php echo $is_edit ? esc_html__( 'Edit client', 'wp-bizwit' ) : esc_html__( 'Add client', 'wp-bizwit' ); ?>
</h1>

<a href="<?php echo esc_url( (string) $data['list_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Back to clients', 'wp-bizwit' ); ?>
</a>

<hr class="wp-header-end" />

<form method="post" class="wp-bizwit-form">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_client_form" value="1" />
	<input type="hidden" name="client_id" value="<?php echo esc_attr( $field( 'id', '0' ) ); ?>" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wp-bizwit-display-name"><?php esc_html_e( 'Name', 'wp-bizwit' ); ?> <span class="description">(<?php esc_html_e( 'required', 'wp-bizwit' ); ?>)</span></label>
			</th>
			<td>
				<input type="text" id="wp-bizwit-display-name" name="display_name" class="regular-text" required
					autocomplete="off" value="<?php echo esc_attr( $field( 'display_name' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'A single name is fine. This is how the client appears throughout BizWit and on documents.', 'wp-bizwit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-email"><?php esc_html_e( 'Email', 'wp-bizwit' ); ?></label></th>
			<td>
				<input type="email" id="wp-bizwit-email" name="email" class="regular-text"
					value="<?php echo esc_attr( $field( 'email' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-phone"><?php echo esc_html( $region->field_label( 'phone', __( 'Phone', 'wp-bizwit' ) ) ); ?></label></th>
			<td>
				<input type="text" id="wp-bizwit-phone" name="phone" class="regular-text"
					value="<?php echo esc_attr( $field( 'phone' ) ); ?>" />
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
	</table>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Address', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><?php esc_html_e( 'Needed on printed invoices and receipts', 'wp-bizwit' ); ?></span>
		</summary>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-address-1"><?php echo esc_html( $region->field_label( 'address_line1', __( 'Address', 'wp-bizwit' ) ) ); ?></label></th>
				<td><input type="text" id="wp-bizwit-address-1" name="address_line1" class="regular-text" value="<?php echo esc_attr( $field( 'address_line1' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-address-2"><?php echo esc_html( $region->field_label( 'address_line2', __( 'Address line 2', 'wp-bizwit' ) ) ); ?></label></th>
				<td><input type="text" id="wp-bizwit-address-2" name="address_line2" class="regular-text" value="<?php echo esc_attr( $field( 'address_line2' ) ); ?>" /></td>
			</tr>

			<?php foreach ( $address_meta as $meta_key => $definition ) : ?>
				<?php $meta_row( (string) $meta_key, $definition, $client_meta ); ?>
			<?php endforeach; ?>

			<tr>
				<th scope="row"><label for="wp-bizwit-city"><?php echo esc_html( $region->field_label( 'city', __( 'City', 'wp-bizwit' ) ) ); ?></label></th>
				<td><input type="text" id="wp-bizwit-city" name="city" class="regular-text" value="<?php echo esc_attr( $field( 'city' ) ); ?>" /></td>
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
						<input type="text" id="wp-bizwit-state" name="state" class="regular-text" value="<?php echo esc_attr( $field( 'state' ) ); ?>" />
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-postal-code"><?php echo esc_html( $region->field_label( 'postal_code', __( 'Postal code', 'wp-bizwit' ) ) ); ?></label></th>
				<td><input type="text" id="wp-bizwit-postal-code" name="postal_code" class="small-text" value="<?php echo esc_attr( $field( 'postal_code' ) ); ?>" /></td>
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
	</details>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Billing', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint">
				<?php
				printf(
					/* translators: 1: currency code, 2: number of days */
					esc_html__( 'Currently %1$s, %2$d days', 'wp-bizwit' ),
					esc_html( $field( 'currency', (string) ( $defaults['currency'] ?? 'IDR' ) ) ),
					(int) $field( 'payment_terms_days', (string) ( $defaults['payment_terms_days'] ?? 30 ) )
				);
				?>
			</span>
		</summary>
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
		</table>
	</details>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Legal and tax identity', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><?php esc_html_e( 'Only needed if you invoice a registered entity', 'wp-bizwit' ); ?></span>
		</summary>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-legal-name"><?php echo esc_html( $region->field_label( 'legal_name', __( 'Legal name', 'wp-bizwit' ) ) ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-legal-name" name="legal_name" class="regular-text" value="<?php echo esc_attr( $field( 'legal_name' ) ); ?>" />
					<p class="description">
						<?php echo esc_html( $region->field_description( 'legal_name', __( 'Registered name, if it differs from the name above. Used on invoices.', 'wp-bizwit' ) ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-tax-id"><?php echo esc_html( $region->field_label( 'tax_id', __( 'Tax ID', 'wp-bizwit' ) ) ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-tax-id" name="tax_id" class="regular-text" value="<?php echo esc_attr( $field( 'tax_id' ) ); ?>" />
					<?php $tax_help = $region->field_description( 'tax_id' ); ?>
					<?php if ( '' !== $tax_help ) : ?>
						<p class="description"><?php echo esc_html( $tax_help ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-registration-no"><?php echo esc_html( $region->field_label( 'registration_no', __( 'Registration number', 'wp-bizwit' ) ) ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-registration-no" name="registration_no" class="regular-text" value="<?php echo esc_attr( $field( 'registration_no' ) ); ?>" />
					<p class="description">
						<?php echo esc_html( $region->field_description( 'registration_no', __( 'Company number, agency code or other official identifier.', 'wp-bizwit' ) ) ); ?>
					</p>
				</td>
			</tr>

			<?php foreach ( $identity_meta as $meta_key => $definition ) : ?>
				<?php $meta_row( (string) $meta_key, $definition, $client_meta ); ?>
			<?php endforeach; ?>
		</table>
	</details>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><?php esc_html_e( 'Never shown to the client', 'wp-bizwit' ); ?></span>
		</summary>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-notes"><?php esc_html_e( 'Internal notes', 'wp-bizwit' ); ?></label></th>
				<td>
					<textarea id="wp-bizwit-notes" name="notes" rows="5" class="large-text"><?php echo esc_textarea( $field( 'notes' ) ); ?></textarea>
				</td>
			</tr>
		</table>
	</details>

	<?php submit_button( $is_edit ? __( 'Update client', 'wp-bizwit' ) : __( 'Add client', 'wp-bizwit' ) ); ?>
</form>

<?php
$client_projects = is_array( $data['client_projects'] ?? null ) ? $data['client_projects'] : array();
$client_id       = (int) $field( 'id', '0' );
if ( $is_edit && $client_id > 0 && current_user_can( Capabilities::MANAGE_PROJECTS ) ) :
	$new_project_url = add_query_arg(
		array(
			'page'      => Projects_Screen::SLUG,
			'action'    => 'new',
			'client_id' => $client_id,
		),
		admin_url( 'admin.php' )
	);
	$statuses        = Project_Repository::statuses();
	?>
	<hr />
	<h2><?php esc_html_e( 'Projects', 'wp-bizwit' ); ?></h2>
	<?php if ( array() === $client_projects ) : ?>
		<p><?php esc_html_e( 'No projects yet for this client.', 'wp-bizwit' ); ?></p>
	<?php else : ?>
		<table class="widefat striped" style="max-width: 40rem;">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'wp-bizwit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'wp-bizwit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $client_projects as $project_row ) : ?>
					<?php
					$project_id  = (int) ( $project_row['id'] ?? 0 );
					$edit_url    = add_query_arg(
						array(
							'page'    => Projects_Screen::SLUG,
							'action'  => 'edit',
							'project' => $project_id,
						),
						admin_url( 'admin.php' )
					);
					$status_slug = (string) ( $project_row['status'] ?? '' );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( (string) ( $project_row['name'] ?? '' ) ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $statuses[ $status_slug ] ?? $status_slug ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<p>
		<a class="button" href="<?php echo esc_url( $new_project_url ); ?>">
			<?php esc_html_e( 'Add project for this client', 'wp-bizwit' ); ?>
		</a>
	</p>
	<?php
endif;
