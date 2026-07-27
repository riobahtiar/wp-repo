<?php
/**
 * First-run setup checklist for the dashboard.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

use WP_BizWit\Documents\Template_Post_Type;
use WP_BizWit\Repositories\Stats_Repository;

/**
 * Evaluates which setup steps a site still needs, and per-user dismissal.
 *
 * Checklist is dismissible and never blocks the admin. It reappears only if
 * the user clears the dismissal (or we reset the user meta in tests).
 */
class Onboarding {

	/**
	 * User meta key: checklist dismissed by this user.
	 *
	 * @var string
	 */
	public const DISMISS_META = 'wp_bizwit_checklist_dismissed';

	/**
	 * Whether the current user has dismissed the checklist.
	 *
	 * @return bool
	 */
	public static function is_dismissed(): bool {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return true;
		}

		return (bool) get_user_meta( $user_id, self::DISMISS_META, true );
	}

	/**
	 * Dismiss the checklist for the current user.
	 *
	 * @return void
	 */
	public static function dismiss(): void {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::DISMISS_META, 1 );
		}
	}

	/**
	 * Setup steps with completion state and action URLs.
	 *
	 * @param array<string, string> $urls Named URLs from the dashboard screen.
	 *
	 * @return array<int, array{id: string, label: string, done: bool, url: string}>
	 */
	public static function steps( array $urls ): array {
		$settings = Settings::all();
		$stats    = new Stats_Repository();

		$business_name = trim( (string) ( $settings['business_name'] ?? '' ) );
		$has_bank      = Payment_Destinations::has_any();

		$clients  = $stats->clients();
		$invoices = $stats->invoices();
		$template = Template_Post_Type::get_default( 'invoice' );

		return array(
			array(
				'id'    => 'business',
				'label' => __( 'Add your business name and identity in Settings', 'wp-bizwit' ),
				'done'  => '' !== $business_name,
				'url'   => (string) ( $urls['settings'] ?? '' ),
			),
			array(
				'id'    => 'bank',
				'label' => __( 'Add payment details (bank, e-wallet, VA or link) for invoices', 'wp-bizwit' ),
				'done'  => $has_bank,
				'url'   => (string) ( $urls['settings'] ?? '' ),
			),
			array(
				'id'    => 'client',
				'label' => __( 'Add your first client', 'wp-bizwit' ),
				'done'  => $clients > 0,
				'url'   => (string) ( $urls['new_client'] ?? '' ),
			),
			array(
				'id'    => 'invoice',
				'label' => __( 'Issue your first invoice', 'wp-bizwit' ),
				'done'  => $invoices > 0,
				'url'   => (string) ( $urls['new_invoice'] ?? '' ),
			),
			array(
				'id'    => 'template',
				'label' => __( 'Review the invoice document template', 'wp-bizwit' ),
				'done'  => null !== $template,
				'url'   => (string) ( $urls['templates'] ?? '' ),
			),
		);
	}

	/**
	 * Whether any step is still incomplete (and checklist should show).
	 *
	 * @param array<int, array{done: bool}> $steps Steps from steps().
	 *
	 * @return bool
	 */
	public static function has_incomplete( array $steps ): bool {
		foreach ( $steps as $step ) {
			if ( empty( $step['done'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count incomplete steps.
	 *
	 * @param array<int, array{done: bool}> $steps Steps.
	 *
	 * @return int
	 */
	public static function incomplete_count( array $steps ): int {
		$n = 0;
		foreach ( $steps as $step ) {
			if ( empty( $step['done'] ) ) {
				++$n;
			}
		}

		return $n;
	}
}
