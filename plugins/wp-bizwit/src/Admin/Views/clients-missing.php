<?php
/**
 * Shown when an edit link points at a client that no longer exists.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Clients_Screen.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<h1><?php esc_html_e( 'Client not found', 'wp-bizwit' ); ?></h1>

<p><?php esc_html_e( 'That client may have been deleted by someone else, or the link may be out of date.', 'wp-bizwit' ); ?></p>

<p>
	<a class="button button-primary" href="<?php echo esc_url( (string) $data['list_url'] ); ?>">
		<?php esc_html_e( 'Back to clients', 'wp-bizwit' ); ?>
	</a>
</p>
