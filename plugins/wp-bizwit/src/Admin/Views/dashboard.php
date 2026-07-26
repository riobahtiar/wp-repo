<?php
/**
 * Dashboard view: tiles, setup checklist, ageing, recent invoices, quick actions.
 *
 * @package WP_BizWit
 *
 * @var array<string, mixed> $data View data supplied by Dashboard_Screen.
 */

use WP_BizWit\Support\Money;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$tiles           = is_array( $data['tiles'] ?? null ) ? $data['tiles'] : array();
$checklist_steps = is_array( $data['checklist_steps'] ?? null ) ? $data['checklist_steps'] : array();
$recent          = is_array( $data['recent_invoices'] ?? null ) ? $data['recent_invoices'] : array();
$activity        = is_array( $data['recent_activity'] ?? null ) ? $data['recent_activity'] : array();
$quick           = is_array( $data['quick_actions'] ?? null ) ? $data['quick_actions'] : array();
$ageing          = is_array( $data['ageing'] ?? null ) ? $data['ageing'] : null;
$currency        = (string) ( $data['ageing_currency'] ?? 'IDR' );
?>

<h1 class="wp-heading-inline"><?php esc_html_e( 'BizWit', 'wp-bizwit' ); ?></h1>

<?php if ( ! empty( $data['can_clients'] ) ) : ?>
	<a href="<?php echo esc_url( (string) $data['new_client_url'] ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add client', 'wp-bizwit' ); ?>
	</a>
<?php endif; ?>
<?php if ( ! empty( $data['can_invoices'] ) ) : ?>
	<a href="<?php echo esc_url( (string) $data['new_invoice_url'] ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add invoice', 'wp-bizwit' ); ?>
	</a>
<?php endif; ?>

<hr class="wp-header-end" />

<p class="description">
	<?php esc_html_e( 'Client records, projects, invoices and payment receipts. Record keeping only — BizWit never processes or moves money.', 'wp-bizwit' ); ?>
</p>

<?php if ( ! empty( $data['show_checklist'] ) ) : ?>
	<div class="wp-bizwit-panel wp-bizwit-checklist" role="region" aria-labelledby="wp-bizwit-checklist-title">
		<div class="wp-bizwit-checklist__header">
			<h2 id="wp-bizwit-checklist-title"><?php esc_html_e( 'Get set up', 'wp-bizwit' ); ?></h2>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: remaining steps */
						_n( '%d step remaining.', '%d steps remaining.', (int) $data['checklist_left'], 'wp-bizwit' ),
						(int) $data['checklist_left']
					)
				);
				?>
			</p>
		</div>
		<ul class="wp-bizwit-checklist__list">
			<?php foreach ( $checklist_steps as $step ) : ?>
				<li class="<?php echo ! empty( $step['done'] ) ? 'is-done' : 'is-todo'; ?>">
					<span class="wp-bizwit-checklist__mark" aria-hidden="true">
						<?php echo ! empty( $step['done'] ) ? '✓' : '○'; ?>
					</span>
					<?php if ( ! empty( $step['done'] ) ) : ?>
						<span class="wp-bizwit-checklist__label"><?php echo esc_html( (string) $step['label'] ); ?></span>
					<?php else : ?>
						<a class="wp-bizwit-checklist__label" href="<?php echo esc_url( (string) $step['url'] ); ?>">
							<?php echo esc_html( (string) $step['label'] ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="wp-bizwit-checklist__dismiss">
			<a href="<?php echo esc_url( (string) $data['dismiss_url'] ); ?>">
				<?php esc_html_e( 'Dismiss checklist', 'wp-bizwit' ); ?>
			</a>
		</p>
	</div>
<?php endif; ?>

<div class="wp-bizwit-tiles">
	<?php foreach ( $tiles as $tile ) : ?>
		<a class="wp-bizwit-tile" href="<?php echo esc_url( (string) $tile['url'] ); ?>">
			<span class="wp-bizwit-tile__value"><?php echo esc_html( (string) $tile['value'] ); ?></span>
			<span class="wp-bizwit-tile__label"><?php echo esc_html( (string) $tile['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</div>

<?php if ( array() !== $quick ) : ?>
	<div class="wp-bizwit-panel">
		<h2><?php esc_html_e( 'Quick actions', 'wp-bizwit' ); ?></h2>
		<p class="wp-bizwit-quick">
			<?php foreach ( $quick as $quick_action ) : ?>
				<a class="button" href="<?php echo esc_url( (string) $quick_action['url'] ); ?>">
					<?php echo esc_html( (string) $quick_action['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</p>
	</div>
<?php endif; ?>

<?php if ( null !== $ageing ) : ?>
	<div class="wp-bizwit-panel" role="region" aria-labelledby="wp-bizwit-ageing-title">
		<h2 id="wp-bizwit-ageing-title"><?php esc_html_e( 'Receivables ageing', 'wp-bizwit' ); ?></h2>
		<p class="description" id="wp-bizwit-ageing-desc">
			<?php esc_html_e( 'Unpaid invoice balances by how long past the due date.', 'wp-bizwit' ); ?>
		</p>
		<table class="widefat striped wp-bizwit-ageing" aria-describedby="wp-bizwit-ageing-desc">
			<caption class="screen-reader-text"><?php esc_html_e( 'Receivables ageing by overdue bucket', 'wp-bizwit' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Bucket', 'wp-bizwit' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Amount', 'wp-bizwit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Current (not overdue)', 'wp-bizwit' ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( (int) $ageing['current'], $currency ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '1–30 days overdue', 'wp-bizwit' ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( (int) $ageing['d1_30'], $currency ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '31–60 days overdue', 'wp-bizwit' ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( (int) $ageing['d31_60'], $currency ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '61+ days overdue', 'wp-bizwit' ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( (int) $ageing['d61_plus'], $currency ) ); ?></td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row"><?php esc_html_e( 'Total outstanding', 'wp-bizwit' ); ?></th>
					<th class="num"><?php echo esc_html( Money::format( (int) $ageing['total'], $currency ) ); ?></th>
				</tr>
			</tfoot>
		</table>
	</div>
<?php endif; ?>

<?php if ( array() !== $recent || array() !== $activity ) : ?>
	<div class="wp-bizwit-dashboard-cols">
		<?php if ( array() !== $recent ) : ?>
			<div class="wp-bizwit-panel" role="region" aria-labelledby="wp-bizwit-recent-inv-title">
				<h2 id="wp-bizwit-recent-inv-title"><?php esc_html_e( 'Recent invoices', 'wp-bizwit' ); ?></h2>
				<table class="widefat striped">
					<caption class="screen-reader-text"><?php esc_html_e( 'Recently updated invoices', 'wp-bizwit' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Number', 'wp-bizwit' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Client', 'wp-bizwit' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'wp-bizwit' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Total', 'wp-bizwit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $row ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( (string) $row['url'] ); ?>">
										<?php echo esc_html( (string) $row['number'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( (string) $row['client'] ); ?></td>
								<td><?php echo esc_html( (string) $row['status'] ); ?></td>
								<td class="num"><?php echo esc_html( (string) $row['total'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<a href="<?php echo esc_url( (string) $data['invoices_url'] ); ?>">
						<?php esc_html_e( 'View all invoices', 'wp-bizwit' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( array() !== $activity ) : ?>
			<div class="wp-bizwit-panel" role="region" aria-labelledby="wp-bizwit-activity-title">
				<h2 id="wp-bizwit-activity-title"><?php esc_html_e( 'Recent activity', 'wp-bizwit' ); ?></h2>
				<p class="description" id="wp-bizwit-activity-desc">
					<?php esc_html_e( 'Who changed clients, projects, invoices and payments — kept for one year.', 'wp-bizwit' ); ?>
				</p>
				<ul class="wp-bizwit-activity" aria-describedby="wp-bizwit-activity-desc">
					<?php foreach ( $activity as $row ) : ?>
						<li>
							<span class="wp-bizwit-activity__summary"><?php echo esc_html( (string) $row['summary'] ); ?></span>
							<span class="wp-bizwit-activity__when muted">
								<time><?php echo esc_html( (string) $row['when'] ); ?></time>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php
// Mount root for the Vite dashboard island (resources/screens/DashboardApp.vue).
?>
<div id="wp-bizwit-dashboard" class="wp-bizwit-dashboard-mount"></div>
