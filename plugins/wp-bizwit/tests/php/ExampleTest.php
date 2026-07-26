<?php
/**
 * Smoke test verifying the plugin bootstrap works correctly.
 *
 * @package WP_BizWit
 */

/**
 * Confirms the plugin main class is available after the bootstrap loads it.
 * Replace or extend this class with real feature tests.
 */
class ExampleTest extends WP_UnitTestCase {

	/**
	 * Verifies the plugin class is loaded by the bootstrap.
	 */
	public function test_plugin_main_class_is_loaded(): void {
		$this->assertTrue( class_exists( \WP_BizWit\WP_BizWit::class ) );
	}
}
