<?php
/**
 * Payments list view.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data.
 */

use WP_BizWit\Admin\Screens\Payments_Screen;
use WP_BizWit\Admin\Tables\Payments_Table;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$table = $data['table'] ?? null;
if ( ! $table instanceof Payments_Table ) {
	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$current_client  = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
$current_method  = isset( $_GET['method'] ) ? sanitize_key( wp_unslash( $_GET['method'] ) ) : '';
$current_invoice = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
// phpcs:enable
?>

<h1 class="wp-heading-inline"><?php esc_html_e( 'Payments', 'wp-bizwit' ); ?></h1>

<a href="<?php echo esc_url( (string) $data['new_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Record payment', 'wp-bizwit' ); ?>
</a>

<hr class="wp-header-end" />

<p class="description">
	<?php esc_html_e( 'Record money that has already arrived. BizWit never processes or moves funds.', 'wp-bizwit' ); ?>
</p>

<form method="get">
	<input type="hidden" name="page" value="<?php echo esc_attr( Payments_Screen::SLUG ); ?>" />
	<?php if ( $current_invoice > 0 ) : ?>
		<input type="hidden" name="invoice_id" value="<?php echo esc_attr( (string) $current_invoice ); ?>" />
	<?php endif; ?>

	<label class="screen-reader-text" for="wp-bizwit-filter-client"><?php esc_html_e( 'Filter by client', 'wp-bizwit' ); ?></label>
	<select name="client_id" id="wp-bizwit-filter-client">
		<option value="0"><?php esc_html_e( 'All clients', 'wp-bizwit' ); ?></option>
		<?php foreach ( $table->client_options() as $cid => $cname ) : ?>
			<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $current_client, (int) $cid ); ?>>
				<?php echo esc_html( $cname ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="screen-reader-text" for="wp-bizwit-filter-method"><?php esc_html_e( 'Filter by method', 'wp-bizwit' ); ?></label>
	<select name="method" id="wp-bizwit-filter-method">
		<option value=""><?php esc_html_e( 'All methods', 'wp-bizwit' ); ?></option>
		<?php foreach ( $table->method_options() as $slug => $label ) : ?>
			<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_method, $slug ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php submit_button( __( 'Filter', 'wp-bizwit' ), '', 'filter_action', false ); ?>
	<?php $table->search_box( __( 'Search payments', 'wp-bizwit' ), 'wp-bizwit-payment-search' ); ?>
</form>

<form method="post">
	<?php $table->display(); ?>
</form>
