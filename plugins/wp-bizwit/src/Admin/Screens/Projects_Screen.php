<?php
/**
 * Projects admin screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Support\Capabilities;

/**
 * Project management. Schema is in place; the UI lands in a later pass.
 */
class Projects_Screen extends Placeholder_Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-projects';

	/**
	 * Capability required to use this screen.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return Capabilities::MANAGE_PROJECTS;
	}

	/**
	 * Heading shown at the top of the screen.
	 *
	 * @return string Translated heading.
	 */
	protected function heading(): string {
		return __( 'Projects', 'wp-bizwit' );
	}

	/**
	 * Sentence describing what this screen will do.
	 *
	 * @return string Translated description.
	 */
	protected function description(): string {
		return __( 'Track the work you do for each client, and the terms you agreed to bill it under.', 'wp-bizwit' );
	}

	/**
	 * Bullet points describing the planned functionality.
	 *
	 * @return string[] Translated list items.
	 */
	protected function planned(): array {
		return array(
			__( 'Projects attached to a client, with a project code and status.', 'wp-bizwit' ),
			__( 'Fixed-price, hourly, milestone and retainer billing types.', 'wp-bizwit' ),
			__( 'Budget tracking against what has been invoiced so far.', 'wp-bizwit' ),
			__( 'One-click invoice creation from a project.', 'wp-bizwit' ),
		);
	}
}
