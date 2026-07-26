<?php
/**
 * Payment not found.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<h1><?php esc_html_e( 'Payment not found', 'wp-bizwit' ); ?></h1>
<p><?php esc_html_e( 'That payment does not exist or was deleted.', 'wp-bizwit' ); ?></p>
<p>
	<a class="button" href="<?php echo esc_url( (string) ( $data['list_url'] ?? '' ) ); ?>">
		<?php esc_html_e( 'Back to payments', 'wp-bizwit' ); ?>
	</a>
</p>
