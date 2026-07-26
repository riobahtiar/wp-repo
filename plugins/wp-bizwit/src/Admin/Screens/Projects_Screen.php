<?php
/**
 * Projects admin screen: list, create, edit and delete.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_Error;
use WP_BizWit\Admin\Notices;
use WP_BizWit\Admin\Tables\Projects_Table;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Full CRUD for project records, including termin stages.
 */
class Projects_Screen extends Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-projects';

	/**
	 * Nonce action used by the add/edit form.
	 *
	 * @var string
	 */
	private const FORM_NONCE = 'wp_bizwit_save_project';

	/**
	 * Project repository.
	 *
	 * @var Project_Repository
	 */
	private Project_Repository $projects;

	/**
	 * Client repository (select options and labels).
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Set up the screen.
	 *
	 * @param Project_Repository $projects Project repository.
	 * @param Client_Repository  $clients  Client repository.
	 */
	public function __construct( Project_Repository $projects, Client_Repository $clients ) {
		$this->projects = $projects;
		$this->clients  = $clients;
	}

	/**
	 * Capability required to use this screen.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return Capabilities::MANAGE_PROJECTS;
	}

	/**
	 * Process form submissions and destructive links before output starts.
	 *
	 * @return void
	 */
	public function on_load(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Verified in each handler.
		if ( isset( $_POST['wp_bizwit_project_form'] ) ) {
			$this->handle_save();

			return;
		}

		if ( isset( $_POST['project_ids'] ) ) {
			$this->handle_bulk();

			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable

		if ( 'delete' === $action ) {
			$this->handle_delete();
		}
	}

	/**
	 * Render either the list or the add/edit form.
	 *
	 * @return void
	 */
	protected function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view routing.
		$action = sanitize_key( (string) ( $_GET['action'] ?? '' ) );

		if ( 'new' === $action || 'edit' === $action ) {
			$this->render_form( $action );

			return;
		}

		$this->render_list();
	}

	/**
	 * Render the projects list table.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$table = new Projects_Table( $this->projects, $this->clients );
		$table->prepare_items();

		$this->view(
			'projects-list',
			array(
				'table'    => $table,
				'new_url'  => $this->page_url( self::SLUG, array( 'action' => 'new' ) ),
				'list_url' => $this->page_url( self::SLUG ),
			)
		);
	}

	/**
	 * Render the add or edit form.
	 *
	 * @param string $action Either 'new' or 'edit'.
	 *
	 * @return void
	 */
	private function render_form( string $action ): void {
		$project = array();
		$terms   = array();

		if ( 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only record lookup.
			$id     = absint( $_GET['project'] ?? 0 );
			$record = $this->projects->find( $id );

			if ( null === $record ) {
				$this->view( 'projects-missing', array( 'list_url' => $this->page_url( self::SLUG ) ) );

				return;
			}

			$project = $record;
			$terms   = $this->projects->get_terms( $id );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Optional preselect from client screen.
			$preselect = absint( $_GET['client_id'] ?? 0 );
			if ( $preselect > 0 ) {
				$project['client_id'] = $preselect;
			}
		}

		$region = Regions::current();

		$this->view(
			'projects-form',
			array(
				'project'        => $project,
				'terms'          => $terms,
				'is_edit'        => 'edit' === $action,
				'region'         => $region,
				'client_options' => $this->clients->options( false ),
				'statuses'       => Project_Repository::statuses(),
				'billing_types'  => Project_Repository::billing_types(),
				'currencies'     => Money::currencies(),
				'defaults'       => array(
					'currency' => Settings::currency(),
					'status'   => Project_Repository::STATUS_ACTIVE,
					'billing'  => Project_Repository::BILLING_FIXED,
				),
				'nonce_field'    => self::FORM_NONCE,
				'list_url'       => $this->page_url( self::SLUG ),
			)
		);
	}

	/**
	 * Validate and persist the add/edit form.
	 *
	 * @return void
	 */
	private function handle_save(): void {
		check_admin_referer( self::FORM_NONCE );

		$id = absint( $_POST['project_id'] ?? 0 );

		// Field-level sanitisation happens in the repository.
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $id > 0 ) {
			$result = $this->projects->update( $id, $input );
		} else {
			$result = $this->projects->create( $input );
		}

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
			$this->redirect_to_form( $id );

			return;
		}

		$saved_id = $id > 0 ? $id : (int) $result;

		Notices::add(
			$id > 0
				? __( 'Project updated.', 'wp-bizwit' )
				: __( 'Project added.', 'wp-bizwit' ),
			'success'
		);

		$this->redirect_to_form( $saved_id );
	}

	/**
	 * Delete a single project from a row action link.
	 *
	 * @return void
	 */
	private function handle_delete(): void {
		$id = absint( $_GET['project'] ?? 0 );

		check_admin_referer( 'wp_bizwit_delete_project_' . $id );

		$result = $this->projects->delete( $id );

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
		} else {
			Notices::add( __( 'Project deleted.', 'wp-bizwit' ), 'success' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Apply a bulk action to the selected projects.
	 *
	 * @return void
	 */
	private function handle_bulk(): void {
		check_admin_referer( 'bulk-projects' );

		$table  = new Projects_Table( $this->projects, $this->clients );
		$action = $table->current_action();

		if ( ! in_array( $action, array( 'cancel', 'delete' ), true ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['project_ids'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids = array_filter( array_map( 'absint', (array) $raw ) );

		if ( array() === $ids ) {
			return;
		}

		$done     = 0;
		$failures = array();

		foreach ( $ids as $id ) {
			$result = 'cancel' === $action
				? $this->cancel( $id )
				: $this->projects->delete( $id );

			if ( is_wp_error( $result ) ) {
				$failures[] = $result->get_error_message();

				continue;
			}

			++$done;
		}

		$this->report_bulk_result( $action, $done, $failures );
		$this->redirect_to_list();
	}

	/**
	 * Mark a project as cancelled.
	 *
	 * @param int $id Project id.
	 *
	 * @return true|WP_Error True on success.
	 */
	private function cancel( int $id ) {
		$project = $this->projects->find( $id );

		if ( null === $project ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That project no longer exists.', 'wp-bizwit' ) );
		}

		// Preserve terms: re-load and pass back so replace_terms is not wiping.
		$terms           = $this->projects->get_terms( $id );
		$input           = $project;
		$input['status'] = Project_Repository::STATUS_CANCELLED;
		$input['terms']  = array_map(
			static function ( array $term ): array {
				return array(
					'name'         => (string) $term['name'],
					'amount_minor' => (int) $term['amount_minor'],
					'percent'      => (string) $term['percent'],
					'notes'        => (string) $term['notes'],
				);
			},
			$terms
		);
		// Money fields as minor so sanitize does not re-parse formatted strings.
		unset( $input['rate'], $input['budget'] );

		return $this->projects->update( $id, $input );
	}

	/**
	 * Queue a notice summarising a bulk action.
	 *
	 * @param string   $action   Bulk action that ran.
	 * @param int      $done     Number of records changed.
	 * @param string[] $failures Error messages for records that were skipped.
	 *
	 * @return void
	 */
	private function report_bulk_result( string $action, int $done, array $failures ): void {
		if ( $done > 0 ) {
			$message = 'cancel' === $action
				/* translators: %d: number of projects */
				? sprintf( _n( '%d project cancelled.', '%d projects cancelled.', $done, 'wp-bizwit' ), $done )
				/* translators: %d: number of projects */
				: sprintf( _n( '%d project deleted.', '%d projects deleted.', $done, 'wp-bizwit' ), $done );

			Notices::add( $message, 'success' );
		}

		if ( array() !== $failures ) {
			Notices::add( implode( ' ', array_unique( $failures ) ), 'error' );
		}
	}

	/**
	 * Redirect back to the form for a given project.
	 *
	 * @param int $id Project id, or 0 to return to the create form.
	 *
	 * @return void
	 */
	private function redirect_to_form( int $id ): void {
		$args = $id > 0
			? array(
				'action'  => 'edit',
				'project' => $id,
			)
			: array( 'action' => 'new' );

		wp_safe_redirect( $this->page_url( self::SLUG, $args ) );
		exit;
	}

	/**
	 * Redirect back to the projects list.
	 *
	 * @return void
	 */
	private function redirect_to_list(): void {
		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
	}
}
