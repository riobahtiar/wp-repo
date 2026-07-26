<?php
/**
 * Project add/edit form.
 *
 * Basics stay open. Billing details and termin stages sit behind disclosure.
 * Termin rows are plain PHP — no Vue required for this screen.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Projects_Screen.
 */

use WP_BizWit\Localization\Region;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Money;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$project  = is_array( $data['project'] ?? null ) ? $data['project'] : array();
$terms    = is_array( $data['terms'] ?? null ) ? $data['terms'] : array();
$is_edit  = ! empty( $data['is_edit'] );
$defaults = is_array( $data['defaults'] ?? null ) ? $data['defaults'] : array();
$region   = $data['region'] ?? null;

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
$field = static function ( string $key, string $fallback = '' ) use ( $project ): string {
	if ( ! array_key_exists( $key, $project ) || null === $project[ $key ] ) {
		return $fallback;
	}

	$value = (string) $project[ $key ];

	return '' === $value ? $fallback : $value;
};

$currency = $field( 'currency', (string) ( $defaults['currency'] ?? 'IDR' ) );
$billing  = $field( 'billing_type', (string) ( $defaults['billing'] ?? Project_Repository::BILLING_FIXED ) );
$rate     = Money::to_decimal( (int) ( $project['rate_minor'] ?? 0 ), $currency );
$budget   = Money::to_decimal( (int) ( $project['budget_minor'] ?? 0 ), $currency );
$retensi  = $field( 'retensi_percent', '0' );
// Trim trailing zeros for a cleaner input.
$retensi = rtrim( rtrim( number_format( (float) $retensi, 4, '.', '' ), '0' ), '.' );
if ( '' === $retensi ) {
	$retensi = '0';
}

$show_termin = ( Project_Repository::BILLING_TERMIN === $billing ) || array() !== $terms;

// Ensure at least three blank rows beyond existing terms for quick entry.
$blank_rows = 3;
$form_terms = $terms;
for ( $i = 0; $i < $blank_rows; $i++ ) {
	$form_terms[] = array(
		'name'         => '',
		'amount_minor' => 0,
		'notes'        => '',
	);
}
?>

<h1 class="wp-heading-inline">
	<?php echo $is_edit ? esc_html__( 'Edit project', 'wp-bizwit' ) : esc_html__( 'Add project', 'wp-bizwit' ); ?>
</h1>

<a href="<?php echo esc_url( (string) $data['list_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Back to projects', 'wp-bizwit' ); ?>
</a>

<hr class="wp-header-end" />

<form method="post" class="wp-bizwit-form" id="wp-bizwit-project-form">
	<?php wp_nonce_field( (string) $data['nonce_field'] ); ?>
	<input type="hidden" name="wp_bizwit_project_form" value="1" />
	<input type="hidden" name="project_id" value="<?php echo esc_attr( $field( 'id', '0' ) ); ?>" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wp-bizwit-client-id"><?php esc_html_e( 'Client', 'wp-bizwit' ); ?> <span class="description">(<?php esc_html_e( 'required', 'wp-bizwit' ); ?>)</span></label>
			</th>
			<td>
				<select id="wp-bizwit-client-id" name="client_id" required>
					<option value=""><?php esc_html_e( '— Select client —', 'wp-bizwit' ); ?></option>
					<?php foreach ( (array) $data['client_options'] as $client_id => $client_name ) : ?>
						<option value="<?php echo esc_attr( (string) $client_id ); ?>" <?php selected( $field( 'client_id' ), (string) $client_id ); ?>>
							<?php echo esc_html( (string) $client_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-project-name"><?php esc_html_e( 'Name', 'wp-bizwit' ); ?> <span class="description">(<?php esc_html_e( 'required', 'wp-bizwit' ); ?>)</span></label>
			</th>
			<td>
				<input type="text" id="wp-bizwit-project-name" name="name" class="regular-text" required
					autocomplete="off" value="<?php echo esc_attr( $field( 'name' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-project-code"><?php echo esc_html( $region->field_label( 'project_code', __( 'Code', 'wp-bizwit' ) ) ); ?></label>
			</th>
			<td>
				<input type="text" id="wp-bizwit-project-code" name="code" class="regular-text"
					value="<?php echo esc_attr( $field( 'code' ) ); ?>" />
				<p class="description"><?php echo esc_html( $region->field_description( 'project_code', __( 'Optional internal or contract reference.', 'wp-bizwit' ) ) ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-bizwit-project-status"><?php esc_html_e( 'Status', 'wp-bizwit' ); ?></label></th>
			<td>
				<select id="wp-bizwit-project-status" name="status">
					<?php foreach ( (array) $data['statuses'] as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $field( 'status', (string) ( $defaults['status'] ?? 'active' ) ), (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-bizwit-billing-type"><?php echo esc_html( $region->field_label( 'billing_type', __( 'Billing type', 'wp-bizwit' ) ) ); ?></label>
			</th>
			<td>
				<select id="wp-bizwit-billing-type" name="billing_type">
					<?php foreach ( (array) $data['billing_types'] as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $billing, (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<details class="wp-bizwit-section" open>
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Billing details', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint">
				<?php
				printf(
					/* translators: 1: currency code, 2: billing type label */
					esc_html__( '%1$s · %2$s', 'wp-bizwit' ),
					esc_html( $currency ),
					esc_html( (string) ( ( $data['billing_types'][ $billing ] ?? $billing ) ) )
				);
				?>
			</span>
		</summary>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-project-currency"><?php esc_html_e( 'Currency', 'wp-bizwit' ); ?></label></th>
				<td>
					<select id="wp-bizwit-project-currency" name="currency">
						<?php foreach ( (array) $data['currencies'] as $code ) : ?>
							<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $currency, (string) $code ); ?>>
								<?php echo esc_html( (string) $code ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-project-rate"><?php esc_html_e( 'Rate', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="text" id="wp-bizwit-project-rate" name="rate" class="regular-text"
						inputmode="decimal" value="<?php echo esc_attr( '0' === $rate ? '' : $rate ); ?>" />
					<p class="description"><?php esc_html_e( 'Hourly or retainer rate. Leave blank when not applicable.', 'wp-bizwit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wp-bizwit-project-budget"><?php echo esc_html( $region->field_label( 'budget', __( 'Budget', 'wp-bizwit' ) ) ); ?></label>
				</th>
				<td>
					<input type="text" id="wp-bizwit-project-budget" name="budget" class="regular-text"
						inputmode="decimal" value="<?php echo esc_attr( '0' === $budget ? '' : $budget ); ?>" />
					<p class="description"><?php echo esc_html( $region->field_description( 'budget', __( 'Contract or agreed total for this project.', 'wp-bizwit' ) ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wp-bizwit-retensi"><?php echo esc_html( $region->field_label( 'retensi', __( 'Retention %', 'wp-bizwit' ) ) ); ?></label>
				</th>
				<td>
					<input type="text" id="wp-bizwit-retensi" name="retensi_percent" class="small-text"
						inputmode="decimal" value="<?php echo esc_attr( $retensi ); ?>" />
					<p class="description"><?php echo esc_html( $region->field_description( 'retensi', __( 'Percentage held back until final acceptance. Applied when invoicing.', 'wp-bizwit' ) ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-starts-on"><?php esc_html_e( 'Start date', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="date" id="wp-bizwit-starts-on" name="starts_on"
						value="<?php echo esc_attr( $field( 'starts_on' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-bizwit-ends-on"><?php esc_html_e( 'End date', 'wp-bizwit' ); ?></label></th>
				<td>
					<input type="date" id="wp-bizwit-ends-on" name="ends_on"
						value="<?php echo esc_attr( $field( 'ends_on' ) ); ?>" />
				</td>
			</tr>
		</table>
	</details>

	<details class="wp-bizwit-section" <?php echo $show_termin ? 'open' : ''; ?> id="wp-bizwit-termin-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php echo esc_html( $region->field_label( 'termin', __( 'Termin stages', 'wp-bizwit' ) ) ); ?></span>
			<span class="wp-bizwit-section__hint"><?php esc_html_e( 'Ordered billing stages that sum to the budget', 'wp-bizwit' ); ?></span>
		</summary>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Stages', 'wp-bizwit' ); ?></th>
				<td>
					<table class="widefat striped" style="max-width: 48rem;">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', 'wp-bizwit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Amount', 'wp-bizwit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Notes', 'wp-bizwit' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $form_terms as $index => $term_row ) : ?>
								<?php
								$term_amount = Money::to_decimal( (int) ( $term_row['amount_minor'] ?? 0 ), $currency );
								$term_amount = '0' === $term_amount ? '' : $term_amount;
								?>
								<tr>
									<td>
										<input type="text" name="terms[<?php echo esc_attr( (string) $index ); ?>][name]" class="regular-text"
											value="<?php echo esc_attr( (string) ( $term_row['name'] ?? '' ) ); ?>"
											placeholder="<?php esc_attr_e( 'e.g. Termin 1 — DP', 'wp-bizwit' ); ?>" />
									</td>
									<td>
										<input type="text" name="terms[<?php echo esc_attr( (string) $index ); ?>][amount]" class="regular-text"
											inputmode="decimal" value="<?php echo esc_attr( $term_amount ); ?>" />
									</td>
									<td>
										<input type="text" name="terms[<?php echo esc_attr( (string) $index ); ?>][notes]" class="regular-text"
											value="<?php echo esc_attr( (string) ( $term_row['notes'] ?? '' ) ); ?>" />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Empty rows are ignored. Leave all empty if you will add stages later.', 'wp-bizwit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Budget check', 'wp-bizwit' ); ?></th>
				<td>
					<label for="wp-bizwit-terms-override">
						<input type="checkbox" id="wp-bizwit-terms-override" name="terms_sum_override" value="1"
							<?php checked( ! empty( $project['terms_sum_override'] ) ); ?> />
						<?php esc_html_e( 'Allow termin total to differ from the project budget', 'wp-bizwit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When unchecked, the stage amounts must add up to the budget.', 'wp-bizwit' ); ?></p>
				</td>
			</tr>
		</table>
	</details>

	<details class="wp-bizwit-section">
		<summary>
			<span class="wp-bizwit-section__title"><?php esc_html_e( 'Description', 'wp-bizwit' ); ?></span>
			<span class="wp-bizwit-section__hint"><?php esc_html_e( 'Internal notes about the work', 'wp-bizwit' ); ?></span>
		</summary>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-bizwit-description"><?php esc_html_e( 'Description', 'wp-bizwit' ); ?></label></th>
				<td>
					<textarea id="wp-bizwit-description" name="description" rows="5" class="large-text"><?php echo esc_textarea( $field( 'description' ) ); ?></textarea>
				</td>
			</tr>
		</table>
	</details>

	<?php submit_button( $is_edit ? __( 'Update project', 'wp-bizwit' ) : __( 'Add project', 'wp-bizwit' ) ); ?>
</form>

<script>
(function () {
	var select = document.getElementById('wp-bizwit-billing-type');
	var section = document.getElementById('wp-bizwit-termin-section');
	if (!select || !section) {
		return;
	}
	function sync() {
		if (select.value === 'termin') {
			section.open = true;
		}
	}
	select.addEventListener('change', sync);
})();
</script>
