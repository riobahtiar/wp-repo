<?php
/**
 * Custom capabilities and roles.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

/**
 * Registers the plugin's capabilities and the roles that carry them.
 *
 * Business records are gated on purpose-built capabilities rather than a
 * generic `manage_options` check. `manage_options` is a site-administration
 * capability; needing it to add a client means every bookkeeper has to be a
 * full administrator, which is a far bigger grant than the job requires.
 */
class Capabilities {

	/**
	 * Create, edit and delete client records.
	 *
	 * @var string
	 */
	public const MANAGE_CLIENTS = 'bizwit_manage_clients';

	/**
	 * Create, edit and delete projects.
	 *
	 * @var string
	 */
	public const MANAGE_PROJECTS = 'bizwit_manage_projects';

	/**
	 * Create, edit and void invoices.
	 *
	 * @var string
	 */
	public const MANAGE_INVOICES = 'bizwit_manage_invoices';

	/**
	 * Record and edit payments.
	 *
	 * @var string
	 */
	public const MANAGE_PAYMENTS = 'bizwit_manage_payments';

	/**
	 * View aggregate financial figures.
	 *
	 * @var string
	 */
	public const VIEW_REPORTS = 'bizwit_view_reports';

	/**
	 * Change plugin settings, including document numbering.
	 *
	 * @var string
	 */
	public const MANAGE_SETTINGS = 'bizwit_manage_settings';

	/**
	 * Role slug for a user who can do everything in the plugin.
	 *
	 * @var string
	 */
	public const ROLE_MANAGER = 'bizwit_manager';

	/**
	 * Role slug for a user who maintains records but sees no financial totals.
	 *
	 * @var string
	 */
	public const ROLE_STAFF = 'bizwit_staff';

	/**
	 * Every capability defined by this plugin.
	 *
	 * @return string[] Capability names.
	 */
	public static function all(): array {
		return array(
			self::MANAGE_CLIENTS,
			self::MANAGE_PROJECTS,
			self::MANAGE_INVOICES,
			self::MANAGE_PAYMENTS,
			self::VIEW_REPORTS,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Grant capabilities to administrators and create the plugin's own roles.
	 *
	 * Runs on activation. Roles are stored in the database, so this must not be
	 * called on every request.
	 *
	 * @return void
	 */
	public static function install(): void {
		$administrator = get_role( 'administrator' );

		if ( null !== $administrator ) {
			foreach ( self::all() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		// Recreate the roles so an upgrade picks up newly added capabilities.
		remove_role( self::ROLE_MANAGER );
		add_role(
			self::ROLE_MANAGER,
			__( 'BizWit Manager', 'wp-bizwit' ),
			array_merge(
				array( 'read' => true ),
				array_fill_keys( self::all(), true )
			)
		);

		remove_role( self::ROLE_STAFF );
		add_role(
			self::ROLE_STAFF,
			__( 'BizWit Staff', 'wp-bizwit' ),
			array(
				'read'                => true,
				self::MANAGE_CLIENTS  => true,
				self::MANAGE_PROJECTS => true,
			)
		);
	}

	/**
	 * Remove the plugin's roles and strip its capabilities from administrators.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$administrator = get_role( 'administrator' );

		if ( null !== $administrator ) {
			foreach ( self::all() as $capability ) {
				$administrator->remove_cap( $capability );
			}
		}

		remove_role( self::ROLE_MANAGER );
		remove_role( self::ROLE_STAFF );
	}

	/**
	 * Whether the current user holds any capability from this plugin.
	 *
	 * Used to decide whether the BizWit admin menu should appear at all.
	 *
	 * @return bool True when the user can access at least one screen.
	 */
	public static function current_user_has_any(): bool {
		foreach ( self::all() as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}
}
