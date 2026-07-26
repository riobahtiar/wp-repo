<?php
/**
 * Placeholder view for screens whose UI is not built yet.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Placeholder_Screen.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$planned = is_array( $data['planned'] ?? null ) ? $data['planned'] : array();
?>

<h1><?php echo esc_html( (string) $data['heading'] ); ?></h1>

<div class="wp-bizwit-panel">
	<p><?php echo esc_html( (string) $data['description'] ); ?></p>

	<p><strong><?php esc_html_e( 'Planned for this screen:', 'wp-bizwit' ); ?></strong></p>
	<ul class="ul-disc">
		<?php foreach ( $planned as $item ) : ?>
			<li><?php echo esc_html( (string) $item ); ?></li>
		<?php endforeach; ?>
	</ul>

	<p class="description">
		<?php esc_html_e( 'The database tables behind this screen already exist, so nothing needs migrating when the interface lands.', 'wp-bizwit' ); ?>
	</p>
</div>
