<?php
/**
 * List table for invoice records.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Tables;

use WP_List_Table;
use WP_BizWit\Admin\Screens\Invoices_Screen;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; // @phpstan-ignore requireOnce.fileNotFound
}

/**
 * Renders the invoices index using core's list table chrome.
 */
class Invoices_Table extends WP_List_Table {

	/**
	 * Invoice repository.
	 *
	 * @var Invoice_Repository
	 */
	private Invoice_Repository $invoices;

	/**
	 * Client repository for filter options.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Counts per status.
	 *
	 * @var array<string, int>
	 */
	private array $status_counts = array();

	/**
	 * Set up the table.
	 *
	 * @param Invoice_Repository $invoices Invoice repository.
	 * @param Client_Repository  $clients  Client repository.
	 */
	public function __construct( Invoice_Repository $invoices, Client_Repository $clients ) {
		$this->invoices = $invoices;
		$this->clients  = $clients;

		parent::__construct(
			array(
				'singular' => 'invoice',
				'plural'   => 'invoices',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns rendered by the table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'invoice_number' => __( 'Number', 'wp-bizwit' ),
			'client'         => __( 'Client', 'wp-bizwit' ),
			'status'         => __( 'Status', 'wp-bizwit' ),
			'issue_date'     => __( 'Issue date', 'wp-bizwit' ),
			'due_date'       => __( 'Due date', 'wp-bizwit' ),
			'total'          => __( 'Total', 'wp-bizwit' ),
			'balance'        => __( 'Balance', 'wp-bizwit' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'invoice_number' => array( 'invoice_number', false ),
			'client'         => array( 'client_name', false ),
			'status'         => array( 'status', false ),
			'issue_date'     => array( 'issue_date', true ),
			'due_date'       => array( 'due_date', false ),
			'total'          => array( 'total_minor', false ),
		);
	}

	/**
	 * Status filter views.
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$view_overdue = isset( $_GET['overdue'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['overdue'] ) );

		$base = add_query_arg( array( 'page' => Invoices_Screen::SLUG ), admin_url( 'admin.php' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
		if ( $client_id > 0 ) {
			$base = add_query_arg( 'client_id', $client_id, $base );
		}

		$total = array_sum( $this->status_counts );

		$views = array(
			'all' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $base ),
				( '' === $current && ! $view_overdue ) ? ' class="current"' : '',
				esc_html__( 'All', 'wp-bizwit' ),
				(int) $total
			),
		);

		foreach ( Invoice_Status::labels() as $status => $label ) {
			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				( $current === $status && ! $view_overdue ) ? ' class="current"' : '',
				esc_html( $label ),
				(int) ( $this->status_counts[ $status ] ?? 0 )
			);
		}

		$views['overdue_view'] = sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( add_query_arg( 'overdue', '1', $base ) ),
			$view_overdue ? ' class="current"' : '',
			esc_html__( 'Overdue', 'wp-bizwit' )
		);

		return $views;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		if ( ! current_user_can( Capabilities::MANAGE_INVOICES ) ) {
			return array();
		}

		return array(
			'mark_sent' => __( 'Mark as sent', 'wp-bizwit' ),
			'void'      => __( 'Void', 'wp-bizwit' ),
			'delete'    => __( 'Delete drafts', 'wp-bizwit' ),
		);
	}

	/**
	 * Client options for the filter dropdown.
	 *
	 * @return array<int, string>
	 */
	public function client_options(): array {
		return $this->clients->options( false );
	}

	/**
	 * Load rows for the current page.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = (int) $this->get_items_per_page( 'wp_bizwit_invoices_per_page', 20 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list state.
		$args = array(
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'client_id' => isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0,
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'overdue'   => isset( $_GET['overdue'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['overdue'] ) ),
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'issue_date',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			'per_page'  => $per_page,
			'page'      => $this->get_pagenum(),
		);
		// phpcs:enable

		// Status and overdue views are mutually exclusive for clarity.
		if ( ! empty( $args['overdue'] ) ) {
			$args['status'] = '';
		}

		$result = $this->invoices->query( $args );

		$this->status_counts = $this->invoices->counts_by_status();
		$this->items         = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'invoice_number' );
	}

	/**
	 * Empty state message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No invoices found. Issue your first invoice to get started.', 'wp-bizwit' );
	}

	/**
	 * Row checkbox.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="invoice_ids[]" value="%d" />',
			(int) $item['id']
		);
	}

	/**
	 * Number column with row actions.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_invoice_number( array $item ): string {
		$id    = (int) $item['id'];
		$base  = add_query_arg( array( 'page' => Invoices_Screen::SLUG ), admin_url( 'admin.php' ) );
		$edit  = add_query_arg(
			array(
				'action'  => 'edit',
				'invoice' => $id,
			),
			$base
		);
		$print = add_query_arg(
			array(
				'action'  => 'print',
				'invoice' => $id,
			),
			$base
		);

		$number = (string) $item['invoice_number'];
		$status = (string) $item['status'];

		if ( ! current_user_can( Capabilities::MANAGE_INVOICES ) ) {
			return '<strong>' . esc_html( $number ) . '</strong>';
		}

		$actions = array(
			'edit'  => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'wp-bizwit' ) ),
			'print' => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $print ),
				esc_html__( 'Print', 'wp-bizwit' )
			),
		);

		if ( Invoice_Status::DRAFT === $status ) {
			$send            = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'send',
						'invoice' => $id,
					),
					$base
				),
				'wp_bizwit_send_invoice_' . $id
			);
			$actions['send'] = sprintf( '<a href="%s">%s</a>', esc_url( $send ), esc_html__( 'Mark as sent', 'wp-bizwit' ) );

			$delete            = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'delete',
						'invoice' => $id,
					),
					$base
				),
				'wp_bizwit_delete_invoice_' . $id
			);
			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url( $delete ),
				esc_js( wp_json_encode( __( 'Permanently delete this draft invoice?', 'wp-bizwit' ) ) ),
				esc_html__( 'Delete', 'wp-bizwit' )
			);
		} elseif ( Invoice_Status::VOID !== $status ) {
			$void            = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'void',
						'invoice' => $id,
					),
					$base
				),
				'wp_bizwit_void_invoice_' . $id
			);
			$actions['void'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url( $void ),
				esc_js( wp_json_encode( __( 'Void this invoice? The number is kept for your records.', 'wp-bizwit' ) ) ),
				esc_html__( 'Void', 'wp-bizwit' )
			);
		}

		return sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>%3$s',
			esc_url( $edit ),
			esc_html( $number ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Client column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_client( array $item ): string {
		$name = (string) ( $item['client_name'] ?? '' );

		return '' === $name ? '&mdash;' : esc_html( $name );
	}

	/**
	 * Status column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_status( array $item ): string {
		$labels = Invoice_Status::labels();
		$status = (string) $item['status'];

		return sprintf(
			'<span class="wp-bizwit-status wp-bizwit-status--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $labels[ $status ] ?? $status )
		);
	}

	/**
	 * Issue date column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_issue_date( array $item ): string {
		return $this->format_date( (string) ( $item['issue_date'] ?? '' ) );
	}

	/**
	 * Due date column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_due_date( array $item ): string {
		return $this->format_date( (string) ( $item['due_date'] ?? '' ) );
	}

	/**
	 * Total column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_total( array $item ): string {
		return esc_html(
			Money::format( (int) $item['total_minor'], (string) $item['currency'] )
		);
	}

	/**
	 * Outstanding balance column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string
	 */
	public function column_balance( array $item ): string {
		$balance = Invoice_Totals::balance_minor(
			(int) $item['total_minor'],
			(int) $item['paid_minor']
		);

		if ( 0 === $balance ) {
			return '&mdash;';
		}

		return esc_html( Money::format( $balance, (string) $item['currency'] ) );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array<string, mixed> $item        Row data.
	 * @param string               $column_name Column id.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		$value = isset( $item[ $column_name ] ) ? (string) $item[ $column_name ] : '';

		return '' === $value ? '&mdash;' : esc_html( $value );
	}

	/**
	 * Format a Y-m-d date for the list.
	 *
	 * @param string $date Date string.
	 *
	 * @return string
	 */
	private function format_date( string $date ): string {
		if ( '' === $date || '0000-00-00' === $date ) {
			return '&mdash;';
		}

		$ts = strtotime( $date );

		return false === $ts ? esc_html( $date ) : esc_html( wp_date( get_option( 'date_format' ), $ts ) );
	}
}
