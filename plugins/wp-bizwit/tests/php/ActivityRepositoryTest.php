<?php
/**
 * Tests for the activity audit trail and stats cache busting.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Database\Schema;
use WP_BizWit\Repositories\Activity_Repository;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Support\Settings;

/**
 * Activity log writes and retention.
 */
class ActivityRepositoryTest extends WP_UnitTestCase {

	/**
	 * Activity repository under test.
	 *
	 * @var Activity_Repository
	 */
	private Activity_Repository $activity;

	/**
	 * Client repository for fixtures.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Install schema and wire hooks.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		delete_transient( Stats_Repository::CACHE_KEY );
		( new Installer() )->install();

		$this->activity = new Activity_Repository();
		$this->activity->register_hooks();
		$this->clients = new Client_Repository();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Creating a client writes an activity row and busts the stats cache.
	 *
	 * @return void
	 */
	public function test_client_create_writes_activity_and_busts_cache(): void {
		// Seed a cached value that should disappear after write.
		set_transient( Stats_Repository::CACHE_KEY, array( 'clients_active' => 99 ), HOUR_IN_SECONDS );

		$id = $this->clients->create(
			array(
				'display_name' => 'PT Audit Trail',
				'type'         => 'company',
				'status'       => Client_Repository::STATUS_ACTIVE,
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$recent = $this->activity->recent( 5 );
		$this->assertNotEmpty( $recent );
		$this->assertSame( Activity_Repository::ENTITY_CLIENT, $recent[0]['entity_type'] );
		$this->assertSame( 'created', $recent[0]['action'] );
		$this->assertSame( (int) $id, (int) $recent[0]['entity_id'] );
		$this->assertStringContainsString( 'PT Audit Trail', (string) $recent[0]['summary'] );
		$this->assertSame( get_current_user_id(), (int) $recent[0]['actor_id'] );

		$this->assertFalse( get_transient( Stats_Repository::CACHE_KEY ) );
	}

	/**
	 * Stats remember() returns the same value without re-querying until busted.
	 *
	 * @return void
	 */
	public function test_stats_cache_hit_then_bust(): void {
		$stats = new Stats_Repository();

		$this->clients->create(
			array(
				'display_name' => 'Client A',
				'type'         => 'company',
				'status'       => Client_Repository::STATUS_ACTIVE,
			)
		);

		// First read populates cache.
		$count1 = $stats->clients( Client_Repository::STATUS_ACTIVE );
		$this->assertSame( 1, $count1 );

		$bag = get_transient( Stats_Repository::CACHE_KEY );
		$this->assertIsArray( $bag );
		$this->assertArrayHasKey( 'clients_active', $bag );

		// Second read still 1 from cache even if we could have more — prove cache works
		// by manually poisoning the bag and ensuring the method returns the poisoned value.
		$bag['clients_active'] = 42;
		set_transient( Stats_Repository::CACHE_KEY, $bag, HOUR_IN_SECONDS );
		$this->assertSame( 42, $stats->clients( Client_Repository::STATUS_ACTIVE ) );

		Stats_Repository::bust_cache();
		$this->assertFalse( get_transient( Stats_Repository::CACHE_KEY ) );

		// After bust, live count is 1 again.
		$this->assertSame( 1, $stats->clients( Client_Repository::STATUS_ACTIVE ) );
	}

	/**
	 * Prune removes rows older than the retention window.
	 *
	 * @return void
	 */
	public function test_prune_deletes_old_rows(): void {
		global $wpdb;

		$id = $this->activity->record(
			Activity_Repository::ENTITY_CLIENT,
			1,
			'created',
			'Old event'
		);
		$this->assertGreaterThan( 0, $id );

		$table = Schema::table( Schema::ACTIVITY );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			$table,
			array( 'created_at' => '2020-01-01 00:00:00' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		$deleted = $this->activity->prune( 30 );
		$this->assertGreaterThanOrEqual( 1, $deleted );
		$this->assertSame( array(), $this->activity->recent( 5 ) );
	}

	/**
	 * Activity table is created by the installer.
	 *
	 * @return void
	 */
	public function test_activity_table_exists(): void {
		global $wpdb;
		$table = Schema::table( Schema::ACTIVITY );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $found );
	}
}
