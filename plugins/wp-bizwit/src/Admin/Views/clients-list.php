<?php
/**
 * Clients list view.
 *
 * Search and filtering use GET so the resulting view stays in the URL and can
 * be sorted, paginated, bookmarked and shared. Bulk actions use POST, because
 * archiving or deleting records should never be something a URL can trigger.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Clients_Screen.
 */

use WP_BizWit\Admin\Screens\Clients_Screen;
use WP_BizWit\Admin\Tables\Clients_Table;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$table = $data['table'] ?? null;

if ( ! $table instanceof Clients_Table ) {
	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter state echoed back into the search form.
$current_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$current_type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
// phpcs:enable
?>

<h1 class="wp-heading-inline"><?php esc_html_e( 'Clients', 'wp-bizwit' ); ?></h1>

<a href="<?php echo esc_url( (string) $data['new_url'] ); ?>" class="page-title-action">
	<?php esc_html_e( 'Add client', 'wp-bizwit' ); ?>
</a>

<hr class="wp-header-end" />

<?php $table->views(); ?>

<form method="get">
	<input type="hidden" name="page" value="<?php echo esc_attr( Clients_Screen::SLUG ); ?>" />
	<?php if ( '' !== $current_status ) : ?>
		<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
	<?php endif; ?>
	<?php if ( '' !== $current_type ) : ?>
		<input type="hidden" name="type" value="<?php echo esc_attr( $current_type ); ?>" />
	<?php endif; ?>
	<?php $table->search_box( __( 'Search clients', 'wp-bizwit' ), 'wp-bizwit-client-search' ); ?>
</form>

<form method="post">
	<?php $table->display(); ?>
</form>
