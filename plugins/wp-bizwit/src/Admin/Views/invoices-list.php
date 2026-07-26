<?php
/**
 * Invoices list view.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Invoices_Screen.
 */

use WP_BizWit\Admin\Screens\Invoices_Screen;
use WP_BizWit\Admin\Tables\Invoices_Table;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$table = $data['table'] ?? null;

if ( ! $table instanceof Invoices_Table ) {
	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
$current_status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$current_client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
$current_overdue   = isset( $_GET['overdue'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['overdue'] ) );
// phpcs:enable
?>

<h1 class="wp-heading-inline"><?php esc_html_e( 'Invoices', 'wp-bizwit' ); ?></h1>

<a href="<?php echo esc_url( (string) $data['new_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Add invoice', 'wp-bizwit' ); ?>
</a>

<hr class="wp-header-end" />

<?php $table->views(); ?>

<form method="get">
	<input type="hidden" name="page" value="<?php echo esc_attr( Invoices_Screen::SLUG ); ?>" />
	<?php if ( '' !== $current_status ) : ?>
		<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
	<?php endif; ?>
	<?php if ( $current_overdue ) : ?>
		<input type="hidden" name="overdue" value="1" />
	<?php endif; ?>

	<label class="screen-reader-text" for="wp-bizwit-filter-client"><?php esc_html_e( 'Filter by client', 'wp-bizwit' ); ?></label>
	<select name="client_id" id="wp-bizwit-filter-client">
		<option value="0"><?php esc_html_e( 'All clients', 'wp-bizwit' ); ?></option>
		<?php foreach ( $table->client_options() as $client_id => $client_name ) : ?>
			<option value="<?php echo esc_attr( (string) $client_id ); ?>" <?php selected( $current_client_id, (int) $client_id ); ?>>
				<?php echo esc_html( $client_name ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php submit_button( __( 'Filter', 'wp-bizwit' ), '', 'filter_action', false ); ?>
	<?php $table->search_box( __( 'Search invoices', 'wp-bizwit' ), 'wp-bizwit-invoice-search' ); ?>
</form>

<form method="post">
	<?php $table->display(); ?>
</form>
