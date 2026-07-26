<?php
/**
 * Base for screens whose tables exist but whose UI is not built yet.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

/**
 * Renders an honest "not built yet" panel.
 *
 * These screens are registered from the start so the information architecture
 * and capability model are settled before the features land, rather than being
 * retrofitted around whatever the first implementation happened to need.
 */
abstract class Placeholder_Screen extends Screen {

	/**
	 * Heading shown at the top of the screen.
	 *
	 * @return string Translated heading.
	 */
	abstract protected function heading(): string;

	/**
	 * Sentence describing what this screen will do.
	 *
	 * @return string Translated description.
	 */
	abstract protected function description(): string;

	/**
	 * Bullet points describing the planned functionality.
	 *
	 * @return string[] Translated list items.
	 */
	abstract protected function planned(): array;

	/**
	 * Render the placeholder panel.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->view(
			'placeholder',
			array(
				'heading'     => $this->heading(),
				'description' => $this->description(),
				'planned'     => $this->planned(),
			)
		);
	}
}
