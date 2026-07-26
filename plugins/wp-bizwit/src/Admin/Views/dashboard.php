<?php
/**
 * Dashboard view.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Dashboard_Screen.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$tiles = is_array( $data['tiles'] ?? null ) ? $data['tiles'] : array();
?>

<h1 class="wp-heading-inline"><?php esc_html_e( 'BizWit', 'wp-bizwit' ); ?></h1>

<?php if ( ! empty( $data['can_clients'] ) ) : ?>
	<a href="<?php echo esc_url( (string) $data['new_client_url'] ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add client', 'wp-bizwit' ); ?>
	</a>
<?php endif; ?>

<hr class="wp-header-end" />

<p class="description">
	<?php esc_html_e( 'Client records, project billing, invoices and payment receipts. Record keeping only: BizWit never processes payments.', 'wp-bizwit' ); ?>
</p>

<div class="wp-bizwit-tiles">
	<?php foreach ( $tiles as $tile ) : ?>
		<a class="wp-bizwit-tile" href="<?php echo esc_url( (string) $tile['url'] ); ?>">
			<span class="wp-bizwit-tile__value"><?php echo esc_html( (string) $tile['value'] ); ?></span>
			<span class="wp-bizwit-tile__label"><?php echo esc_html( (string) $tile['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</div>

<div class="wp-bizwit-panel">
	<h2><?php esc_html_e( 'Getting started', 'wp-bizwit' ); ?></h2>
	<ol>
		<?php if ( ! empty( $data['can_settings'] ) ) : ?>
			<li>
				<a href="<?php echo esc_url( (string) $data['settings_url'] ); ?>">
					<?php esc_html_e( 'Set your business details, currency and document numbering.', 'wp-bizwit' ); ?>
				</a>
			</li>
		<?php endif; ?>
		<?php if ( ! empty( $data['can_clients'] ) ) : ?>
			<li>
				<a href="<?php echo esc_url( (string) $data['clients_url'] ); ?>">
					<?php esc_html_e( 'Add the clients you work with.', 'wp-bizwit' ); ?>
				</a>
			</li>
		<?php endif; ?>
		<li><?php esc_html_e( 'Projects, invoices and payments are next on the roadmap.', 'wp-bizwit' ); ?></li>
	</ol>
</div>

<?php
// Mount root for the Vite dashboard island (resources/screens/DashboardApp.vue).
// Outer `.wrap.wp-bizwit` is printed by Screen::render_page(); do not nest another.
?>
<div id="wp-bizwit-dashboard" class="wp-bizwit-dashboard-mount"></div>
