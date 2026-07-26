<?php
/**
 * Base class for admin screens.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Admin\Notices;

/**
 * Shared rendering and authorisation behaviour for every BizWit admin screen.
 *
 * The capability check lives here rather than in each screen so it cannot be
 * forgotten. Registering a menu page with a capability only hides the menu item;
 * it does not stop someone from requesting the page URL directly, so the
 * callback has to check again.
 */
abstract class Screen {

	/**
	 * Capability a user must hold to view this screen.
	 *
	 * @return string Capability name.
	 */
	abstract public function capability(): string;

	/**
	 * Render the screen body.
	 *
	 * @return void
	 */
	abstract protected function render(): void;

	/**
	 * Handle form submissions before any output is sent.
	 *
	 * Runs on the `load-{page}` hook, which fires before the admin header is
	 * printed. That is what allows a handler to finish with wp_safe_redirect()
	 * and implement the post/redirect/get pattern, so a browser refresh cannot
	 * resubmit the form.
	 *
	 * @return void
	 */
	public function on_load(): void {
	}

	/**
	 * Menu page callback: authorise, print notices, then render.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to view this page.', 'wp-bizwit' ),
				esc_html__( 'Permission denied', 'wp-bizwit' ),
				array( 'response' => 403 )
			);
		}

		echo '<div class="wrap wp-bizwit">';
		Notices::render();
		$this->render();
		echo '</div>';
	}

	/**
	 * Include a view template.
	 *
	 * Templates receive a single `$data` array rather than extracted variables,
	 * so every value used in a template can be traced back to its caller.
	 *
	 * @param string               $name Template file name without extension.
	 * @param array<string, mixed> $data Values made available to the template.
	 *
	 * @return void
	 */
	protected function view( string $name, array $data = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $data is consumed by the required template.
		$file = WP_BIZWIT_PATH . 'src/Admin/Views/' . $name . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		require $file;
	}

	/**
	 * Build a URL to a BizWit admin page.
	 *
	 * @param string                    $page Menu slug.
	 * @param array<string, string|int> $args Extra query arguments.
	 *
	 * @return string Escaped-safe URL for use in attributes.
	 */
	protected function page_url( string $page, array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => $page ), $args ),
			admin_url( 'admin.php' )
		);
	}
}
