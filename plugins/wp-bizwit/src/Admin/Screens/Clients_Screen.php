<?php
/**
 * Clients admin screen: list, create, edit and delete.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_Error;
use WP_BizWit\Admin\Notices;
use WP_BizWit\Admin\Tables\Clients_Table;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Full CRUD for client records.
 */
class Clients_Screen extends Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-clients';

	/**
	 * Nonce action used by the add/edit form.
	 *
	 * @var string
	 */
	private const FORM_NONCE = 'wp_bizwit_save_client';

	/**
	 * Repository backing this screen.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Projects repository for the related list on the edit screen.
	 *
	 * @var Project_Repository
	 */
	private Project_Repository $projects;

	/**
	 * Set up the screen.
	 *
	 * @param Client_Repository  $clients  Client repository.
	 * @param Project_Repository $projects Project repository.
	 */
	public function __construct( Client_Repository $clients, Project_Repository $projects ) {
		$this->clients  = $clients;
		$this->projects = $projects;
	}

	/**
	 * Capability required to use this screen.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return Capabilities::MANAGE_CLIENTS;
	}

	/**
	 * Process form submissions and destructive links before output starts.
	 *
	 * This method only routes. Every branch it dispatches to verifies its own
	 * nonce with check_admin_referer() as its first statement, which is why the
	 * nonce sniff is suppressed on the routing checks below rather than here
	 * being taken as an exemption from verification.
	 *
	 * @return void
	 */
	public function on_load(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Verified in each handler.
		if ( isset( $_POST['wp_bizwit_client_form'] ) ) {
			$this->handle_save();

			return;
		}

		if ( isset( $_POST['client_ids'] ) ) {
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
	 * Render the clients list table.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$table = new Clients_Table( $this->clients );
		$table->prepare_items();

		$this->view(
			'clients-list',
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
		$client = array();

		if ( 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only record lookup.
			$id     = absint( $_GET['client'] ?? 0 );
			$record = $this->clients->find( $id );

			if ( null === $record ) {
				$this->view( 'clients-missing', array( 'list_url' => $this->page_url( self::SLUG ) ) );

				return;
			}

			$client = $record;
		}

		$region   = Regions::current();
		$projects = array();

		if ( 'edit' === $action && isset( $client['id'] ) ) {
			$projects = $this->projects->query(
				array(
					'client_id' => (int) $client['id'],
					'per_page'  => 100,
					'page'      => 1,
					'orderby'   => 'name',
					'order'     => 'ASC',
				)
			)['items'];
		}

		$this->view(
			'clients-form',
			array(
				'client'          => $client,
				'meta'            => Client_Repository::meta( $client ),
				'is_edit'         => 'edit' === $action,
				'region'          => $region,
				'handles_tax'     => Settings::handles_tax(),
				'meta_fields'     => $region->meta_fields(),
				'provinces'       => $region->provinces(),
				'types'           => Client_Repository::types(),
				'statuses'        => Client_Repository::statuses(),
				'currencies'      => Money::currencies(),
				'client_projects' => $projects,
				'defaults'        => array(
					'currency'           => Settings::currency(),
					'payment_terms_days' => (int) Settings::get( 'payment_terms_days', 30 ),
				),
				'nonce_field'     => self::FORM_NONCE,
				'list_url'        => $this->page_url( self::SLUG ),
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

		$id = absint( $_POST['client_id'] ?? 0 );

		// Field-level sanitisation happens in the repository, which is the only
		// place that knows the real column list and their widths.
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $id > 0 ) {
			$result = $this->clients->update( $id, $input );
		} else {
			$result = $this->clients->create( $input );
		}

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
			$this->redirect_to_form( $id );

			return;
		}

		$saved_id = $id > 0 ? $id : (int) $result;

		Notices::add(
			$id > 0
				? __( 'Client updated.', 'wp-bizwit' )
				: __( 'Client added.', 'wp-bizwit' ),
			'success'
		);

		$this->redirect_to_form( $saved_id );
	}

	/**
	 * Delete a single client from a row action link.
	 *
	 * @return void
	 */
	private function handle_delete(): void {
		$id = absint( $_GET['client'] ?? 0 );

		check_admin_referer( 'wp_bizwit_delete_client_' . $id );

		$result = $this->clients->delete( $id );

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
		} else {
			Notices::add( __( 'Client deleted.', 'wp-bizwit' ), 'success' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Apply a bulk action to the selected clients.
	 *
	 * @return void
	 */
	private function handle_bulk(): void {
		check_admin_referer( 'bulk-clients' );

		$table  = new Clients_Table( $this->clients );
		$action = $table->current_action();

		if ( ! in_array( $action, array( 'archive', 'delete' ), true ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['client_ids'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids = array_filter( array_map( 'absint', (array) $raw ) );

		if ( array() === $ids ) {
			return;
		}

		$done     = 0;
		$failures = array();

		foreach ( $ids as $id ) {
			$result = 'archive' === $action
				? $this->archive( $id )
				: $this->clients->delete( $id );

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
	 * Move a client to the archived status.
	 *
	 * @param int $id Client id.
	 *
	 * @return true|WP_Error True on success.
	 */
	private function archive( int $id ) {
		$client = $this->clients->find( $id );

		if ( null === $client ) {
			return new WP_Error( 'wp_bizwit_not_found', __( 'That client no longer exists.', 'wp-bizwit' ) );
		}

		$client['status'] = Client_Repository::STATUS_ARCHIVED;

		return $this->clients->update( $id, $client );
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
			$message = 'archive' === $action
				/* translators: %d: number of clients */
				? sprintf( _n( '%d client archived.', '%d clients archived.', $done, 'wp-bizwit' ), $done )
				/* translators: %d: number of clients */
				: sprintf( _n( '%d client deleted.', '%d clients deleted.', $done, 'wp-bizwit' ), $done );

			Notices::add( $message, 'success' );
		}

		if ( array() !== $failures ) {
			Notices::add( implode( ' ', array_unique( $failures ) ), 'error' );
		}
	}

	/**
	 * Redirect back to the form for a given client.
	 *
	 * @param int $id Client id, or 0 to return to the create form.
	 *
	 * @return void
	 */
	private function redirect_to_form( int $id ): void {
		$args = $id > 0
			? array(
				'action' => 'edit',
				'client' => $id,
			)
			: array( 'action' => 'new' );

		wp_safe_redirect( $this->page_url( self::SLUG, $args ) );
		exit;
	}

	/**
	 * Redirect back to the clients list.
	 *
	 * @return void
	 */
	private function redirect_to_list(): void {
		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
	}
}
