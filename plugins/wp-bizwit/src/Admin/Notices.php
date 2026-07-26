<?php
/**
 * One-shot admin notices that survive a redirect.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin;

/**
 * Stores a notice for the current user and replays it on the next page load.
 *
 * Form handlers run on admin-post.php and then redirect, so anything they want
 * to tell the user has to outlive that redirect. Passing the text through a
 * query string would mean echoing user-influenced content back out of the URL;
 * a per-user transient keeps the message server-side and lets the URL stay
 * clean and shareable.
 */
class Notices {

	/**
	 * Transient name prefix. The current user id is appended.
	 *
	 * @var string
	 */
	private const PREFIX = 'wp_bizwit_notice_';

	/**
	 * Queue a notice for the current user.
	 *
	 * @param string $message Already-translated, plain text message.
	 * @param string $type    One of 'success', 'error', 'warning' or 'info'.
	 *
	 * @return void
	 */
	public static function add( string $message, string $type = 'success' ): void {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$allowed = array( 'success', 'error', 'warning', 'info' );

		set_transient(
			self::PREFIX . $user_id,
			array(
				'message' => $message,
				'type'    => in_array( $type, $allowed, true ) ? $type : 'info',
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Print and clear any queued notice.
	 *
	 * @return void
	 */
	public static function render(): void {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$key    = self::PREFIX . $user_id;
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || ! isset( $notice['message'], $notice['type'] ) ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( (string) $notice['type'] ),
			esc_html( (string) $notice['message'] )
		);
	}
}
