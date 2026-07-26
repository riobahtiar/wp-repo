<?php
/**
 * Invoices admin screen: list, create, edit, print and status actions.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_Error;
use WP_BizWit\Admin\Notices;
use WP_BizWit\Admin\Tables\Invoices_Table;
use WP_BizWit\Documents\Document_Renderer;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Full CRUD for invoices with line items and printable output.
 */
class Invoices_Screen extends Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-invoices';

	/**
	 * Nonce action for the add/edit form.
	 *
	 * @var string
	 */
	private const FORM_NONCE = 'wp_bizwit_save_invoice';

	/**
	 * Invoice repository.
	 *
	 * @var Invoice_Repository
	 */
	private Invoice_Repository $invoices;

	/**
	 * Client repository.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Project repository.
	 *
	 * @var Project_Repository
	 */
	private Project_Repository $projects;

	/**
	 * Set up the screen.
	 *
	 * @param Invoice_Repository $invoices Invoice repository.
	 * @param Client_Repository  $clients  Client repository.
	 * @param Project_Repository $projects Project repository.
	 */
	public function __construct(
		Invoice_Repository $invoices,
		Client_Repository $clients,
		Project_Repository $projects
	) {
		$this->invoices = $invoices;
		$this->clients  = $clients;
		$this->projects = $projects;
	}

	/**
	 * Capability required to use this screen.
	 *
	 * @return string
	 */
	public function capability(): string {
		return Capabilities::MANAGE_INVOICES;
	}

	/**
	 * Process form submissions and actions before output.
	 *
	 * @return void
	 */
	public function on_load(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Verified per handler.
		if ( isset( $_POST['wp_bizwit_invoice_form'] ) ) {
			$this->handle_save();

			return;
		}

		if ( isset( $_POST['invoice_ids'] ) ) {
			$this->handle_bulk();

			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable

		switch ( $action ) {
			case 'delete':
				$this->handle_delete();
				break;
			case 'void':
				$this->handle_void();
				break;
			case 'send':
				$this->handle_send();
				break;
			case 'print':
				$this->handle_print();
				break;
		}
	}

	/**
	 * Render list, form, or let print exit early.
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
	 * Render the invoices list table.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$table = new Invoices_Table( $this->invoices, $this->clients );
		$table->prepare_items();

		$this->view(
			'invoices-list',
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
		$invoice = array();
		$items   = array();
		$locked  = false;

		if ( 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only record lookup.
			$id     = absint( $_GET['invoice'] ?? 0 );
			$record = $this->invoices->find( $id );

			if ( null === $record ) {
				$this->view( 'invoices-missing', array( 'list_url' => $this->page_url( self::SLUG ) ) );

				return;
			}

			$invoice = $record;
			$items   = $this->invoices->get_items( $id );
			$locked  = ! Invoice_Status::is_editable( (string) $record['status'] );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Optional prefill from project/client.
			$project_id = absint( $_GET['project_id'] ?? 0 );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$term_id = absint( $_GET['term_id'] ?? 0 );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$client_id = absint( $_GET['client_id'] ?? 0 );

			if ( $project_id > 0 ) {
				$prefill = $this->invoices->prefill_from_project( $project_id, $term_id );
				if ( ! is_wp_error( $prefill ) ) {
					$invoice['client_id']  = $prefill['client_id'];
					$invoice['project_id'] = $prefill['project_id'];
					$invoice['currency']   = $prefill['currency'];
					$items                 = $prefill['items'];
				}
			} elseif ( $client_id > 0 ) {
				$invoice['client_id'] = $client_id;
			}
		}

		$client_id_for_projects = (int) ( $invoice['client_id'] ?? 0 );
		$project_options        = $client_id_for_projects > 0
			? $this->projects->options_for_client( $client_id_for_projects )
			: array();

		$region = Regions::current();

		$this->view(
			'invoices-form',
			array(
				'invoice'           => $invoice,
				'items'             => $items,
				'is_edit'           => 'edit' === $action,
				'locked'            => $locked,
				'region'            => $region,
				'client_options'    => $this->clients->options( false ),
				'project_options'   => $project_options,
				'statuses'          => Invoice_Status::labels(),
				'currencies'        => Money::currencies(),
				'charges_sales_tax' => Settings::charges_sales_tax(),
				'handles_tax'       => Settings::handles_tax(),
				'tax_label'         => (string) Settings::get( 'tax_label', 'PPN' ),
				'default_tax_rate'  => Settings::effective_tax_rate(),
				'withholding_label' => (string) Settings::get( 'withholding_label', 'PPh 23' ),
				'withholding_rate'  => (string) Settings::get( 'withholding_rate', '2' ),
				'defaults'          => array(
					'currency'   => Settings::currency(),
					'status'     => Invoice_Status::DRAFT,
					'issue_date' => (string) current_time( 'Y-m-d' ),
				),
				'nonce_field'       => self::FORM_NONCE,
				'list_url'          => $this->page_url( self::SLUG ),
			)
		);
	}

	/**
	 * Save the invoice form.
	 *
	 * @return void
	 */
	private function handle_save(): void {
		check_admin_referer( self::FORM_NONCE );

		$id    = absint( $_POST['invoice_id'] ?? 0 );
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $id > 0 ) {
			$result = $this->invoices->update( $id, $input );
		} else {
			$result = $this->invoices->create( $input );
		}

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
			$this->redirect_to_form( $id );

			return;
		}

		$saved_id = $id > 0 ? $id : (int) $result;

		Notices::add(
			$id > 0
				? __( 'Invoice updated.', 'wp-bizwit' )
				: __( 'Invoice created.', 'wp-bizwit' ),
			'success'
		);

		$this->redirect_to_form( $saved_id );
	}

	/**
	 * Delete a draft invoice.
	 *
	 * @return void
	 */
	private function handle_delete(): void {
		$id = absint( $_GET['invoice'] ?? 0 );
		check_admin_referer( 'wp_bizwit_delete_invoice_' . $id );

		$result = $this->invoices->delete( $id );

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
		} else {
			Notices::add( __( 'Invoice deleted.', 'wp-bizwit' ), 'success' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Void an invoice.
	 *
	 * @return void
	 */
	private function handle_void(): void {
		$id = absint( $_GET['invoice'] ?? 0 );
		check_admin_referer( 'wp_bizwit_void_invoice_' . $id );

		$result = $this->invoices->void( $id );

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
		} else {
			Notices::add( __( 'Invoice voided. The number is kept for your records.', 'wp-bizwit' ), 'success' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Mark a draft invoice as sent.
	 *
	 * @return void
	 */
	private function handle_send(): void {
		$id = absint( $_GET['invoice'] ?? 0 );
		check_admin_referer( 'wp_bizwit_send_invoice_' . $id );

		$result = $this->invoices->transition( $id, Invoice_Status::SENT );

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
		} else {
			Notices::add( __( 'Invoice marked as sent.', 'wp-bizwit' ), 'success' );
		}

		$this->redirect_to_form( $id );
	}

	/**
	 * Bulk actions on selected invoices.
	 *
	 * @return void
	 */
	private function handle_bulk(): void {
		check_admin_referer( 'bulk-invoices' );

		$table  = new Invoices_Table( $this->invoices, $this->clients );
		$action = $table->current_action();

		if ( ! in_array( $action, array( 'mark_sent', 'void', 'delete' ), true ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['invoice_ids'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids = array_filter( array_map( 'absint', (array) $raw ) );

		if ( array() === $ids ) {
			return;
		}

		$done     = 0;
		$failures = array();

		foreach ( $ids as $id ) {
			if ( 'mark_sent' === $action ) {
				$result = $this->invoices->transition( $id, Invoice_Status::SENT );
			} elseif ( 'void' === $action ) {
				$result = $this->invoices->void( $id );
			} else {
				$result = $this->invoices->delete( $id );
			}

			if ( is_wp_error( $result ) ) {
				$failures[] = $result->get_error_message();

				continue;
			}

			++$done;
		}

		if ( $done > 0 ) {
			if ( 'mark_sent' === $action ) {
				/* translators: %d: number of invoices */
				$msg = sprintf( _n( '%d invoice marked as sent.', '%d invoices marked as sent.', $done, 'wp-bizwit' ), $done );
			} elseif ( 'void' === $action ) {
				/* translators: %d: number of invoices */
				$msg = sprintf( _n( '%d invoice voided.', '%d invoices voided.', $done, 'wp-bizwit' ), $done );
			} else {
				/* translators: %d: number of invoices */
				$msg = sprintf( _n( '%d draft deleted.', '%d drafts deleted.', $done, 'wp-bizwit' ), $done );
			}
			Notices::add( $msg, 'success' );
		}

		if ( array() !== $failures ) {
			Notices::add( implode( ' ', array_unique( $failures ) ), 'error' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Render a printable HTML invoice and exit (outside admin chrome).
	 *
	 * @return void
	 */
	private function handle_print(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only print; capability already checked.
		$id      = absint( $_GET['invoice'] ?? 0 );
		$invoice = $this->invoices->find( $id );

		if ( null === $invoice ) {
			wp_die( esc_html__( 'That invoice no longer exists.', 'wp-bizwit' ) );
		}

		$items   = $this->invoices->get_items( $id );
		$client  = $this->clients->find( (int) $invoice['client_id'] );
		$project = null;
		if ( (int) $invoice['project_id'] > 0 ) {
			$project = $this->projects->find( (int) $invoice['project_id'] );
		}

		// Standalone print document — no admin chrome.
		status_header( 200 );
		nocache_headers();

		// Prefer Gutenberg document template (labels follow site language).
		$rendered = ( new Document_Renderer() )->render_invoice_document(
			$invoice,
			$items,
			$client,
			$project
		);

		if ( null !== $rendered && '' !== $rendered ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes field values.
			echo $rendered;
			exit;
		}

		// Legacy fallback when no template is published yet.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its own output.
		include WP_BIZWIT_PATH . 'src/Admin/Views/invoices-print.php';
		exit;
	}

	/**
	 * Redirect back to the form for an invoice.
	 *
	 * @param int $id Invoice id, or 0 for create.
	 *
	 * @return void
	 */
	private function redirect_to_form( int $id ): void {
		$args = $id > 0
			? array(
				'action'  => 'edit',
				'invoice' => $id,
			)
			: array( 'action' => 'new' );

		wp_safe_redirect( $this->page_url( self::SLUG, $args ) );
		exit;
	}

	/**
	 * Redirect to the invoices list.
	 *
	 * @return void
	 */
	private function redirect_to_list(): void {
		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
	}
}
