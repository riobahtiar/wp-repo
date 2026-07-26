<?php
/**
 * Register all actions, filters, shortcodes and WP-CLI commands for the plugin.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit;

/**
 * Collects WordPress hooks during boot and registers them in one place.
 *
 * Prefer `$loader->add_action()` over calling `add_action()` inside constructors
 * so wiring stays visible and testable.
 */
class Loader {

	/**
	 * Actions to register with WordPress.
	 *
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}>
	 */
	protected array $actions;

	/**
	 * Filters to register with WordPress.
	 *
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}>
	 */
	protected array $filters;

	/**
	 * Shortcodes to register with WordPress.
	 *
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}>
	 */
	protected array $shortcodes;

	/**
	 * WP-CLI commands keyed by command name.
	 *
	 * @var array<string, array{instance:object, args:array<string,mixed>}>
	 */
	protected array $cli;

	/**
	 * Initialise empty collections.
	 */
	public function __construct() {
		$this->actions    = array();
		$this->filters    = array();
		$this->shortcodes = array();
		$this->cli        = array();
	}

	/**
	 * Queue an action for registration.
	 *
	 * @param string $hook          WordPress action name.
	 * @param object $component     Instance that defines the callback.
	 * @param string $callback      Method name on $component.
	 * @param int    $priority      Priority (default 10).
	 * @param int    $accepted_args Number of accepted arguments (default 1).
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Queue a shortcode for registration.
	 *
	 * @param string $hook      Shortcode tag.
	 * @param object $component Instance that defines the callback.
	 * @param string $callback  Method name on $component.
	 */
	public function add_shortcode( string $hook, object $component, string $callback ): void {
		$this->shortcodes = $this->add( $this->shortcodes, $hook, $component, $callback );
	}

	/**
	 * Queue a WP-CLI command for registration.
	 *
	 * @param string              $name     Command name.
	 * @param object              $instance Command instance or callable object.
	 * @param array<string,mixed> $args     Extra registration arguments for WP_CLI::add_command().
	 */
	public function add_cli( string $name, object $instance, array $args = array() ): void {
		$this->cli[ $name ] = array(
			'instance' => $instance,
			'args'     => $args,
		);
	}

	/**
	 * Queue a filter for registration.
	 *
	 * @param string $hook          WordPress filter name.
	 * @param object $component     Instance that defines the callback.
	 * @param string $callback      Method name on $component.
	 * @param int    $priority      Priority (default 10).
	 * @param int    $accepted_args Number of accepted arguments (default 1).
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Append one hook entry to a collection.
	 *
	 * @param array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}> $hooks         Existing collection.
	 * @param string                                                                                             $hook          Hook name.
	 * @param object                                                                                             $component     Callback object.
	 * @param string                                                                                             $callback      Method name.
	 * @param int                                                                                                $priority      Priority.
	 * @param int                                                                                                $accepted_args Accepted args.
	 * @return array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}>
	 */
	private function add( array $hooks, string $hook, object $component, string $callback, int $priority = -1, int $accepted_args = -1 ): array {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register everything queued with WordPress (and WP-CLI when present).
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array(
					$hook['component'],
					$hook['callback'],
				),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array(
					$hook['component'],
					$hook['callback'],
				),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->shortcodes as $hook ) {
			add_shortcode(
				$hook['hook'],
				array(
					$hook['component'],
					$hook['callback'],
				)
			);
		}

		if ( ! empty( $this->cli ) && class_exists( 'WP_CLI' ) ) {
			foreach ( $this->cli as $name => $data ) {
				\WP_CLI::add_command( $name, $data['instance'], $data['args'] );
			}
		}
	}
}
