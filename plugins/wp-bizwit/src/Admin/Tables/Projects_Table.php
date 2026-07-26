<?php
/**
 * List table for project records.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Tables;

use WP_List_Table;
use WP_BizWit\Admin\Screens\Projects_Screen;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; // @phpstan-ignore requireOnce.fileNotFound
}

/**
 * Renders the projects index using core's list table chrome.
 */
class Projects_Table extends WP_List_Table {

	/**
	 * Repository used to load rows.
	 *
	 * @var Project_Repository
	 */
	private Project_Repository $projects;

	/**
	 * Client repository for filter dropdown options.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Counts per status, used to render the filter links above the table.
	 *
	 * @var array<string, int>
	 */
	private array $status_counts = array();

	/**
	 * Set up the table.
	 *
	 * @param Project_Repository $projects Project repository.
	 * @param Client_Repository  $clients  Client repository.
	 */
	public function __construct( Project_Repository $projects, Client_Repository $clients ) {
		$this->projects = $projects;
		$this->clients  = $clients;

		parent::__construct(
			array(
				'singular' => 'project',
				'plural'   => 'projects',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns rendered by the table.
	 *
	 * @return array<string, string> Column id mapped to heading.
	 */
	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox" />',
			'name'         => __( 'Name', 'wp-bizwit' ),
			'client'       => __( 'Client', 'wp-bizwit' ),
			'status'       => __( 'Status', 'wp-bizwit' ),
			'billing_type' => __( 'Billing', 'wp-bizwit' ),
			'budget'       => __( 'Budget', 'wp-bizwit' ),
			'updated_at'   => __( 'Last updated', 'wp-bizwit' ),
		);
	}

	/**
	 * Columns the user can sort by.
	 *
	 * @return array<string, array{0: string, 1: bool}> Column id mapped to orderby key and initial direction.
	 */
	public function get_sortable_columns(): array {
		return array(
			'name'         => array( 'name', false ),
			'client'       => array( 'client_name', false ),
			'status'       => array( 'status', false ),
			'billing_type' => array( 'billing_type', false ),
			'budget'       => array( 'budget_minor', false ),
			'updated_at'   => array( 'updated_at', true ),
		);
	}

	/**
	 * Status filter links shown above the table.
	 *
	 * @return array<string, string> View id mapped to link markup.
	 */
	protected function get_views(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$base    = add_query_arg( array( 'page' => Projects_Screen::SLUG ), admin_url( 'admin.php' ) );
		$total   = array_sum( $this->status_counts );

		// Preserve client filter on status views.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
		if ( $client_id > 0 ) {
			$base = add_query_arg( 'client_id', $client_id, $base );
		}

		$views = array(
			'all' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $base ),
				'' === $current ? ' class="current"' : '',
				esc_html__( 'All', 'wp-bizwit' ),
				(int) $total
			),
		);

		foreach ( Project_Repository::statuses() as $status => $label ) {
			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$current === $status ? ' class="current"' : '',
				esc_html( $label ),
				(int) ( $this->status_counts[ $status ] ?? 0 )
			);
		}

		return $views;
	}

	/**
	 * Bulk actions offered above and below the table.
	 *
	 * @return array<string, string> Action id mapped to label.
	 */
	protected function get_bulk_actions(): array {
		if ( ! current_user_can( Capabilities::MANAGE_PROJECTS ) ) {
			return array();
		}

		return array(
			'cancel' => __( 'Mark as cancelled', 'wp-bizwit' ),
			'delete' => __( 'Delete permanently', 'wp-bizwit' ),
		);
	}

	/**
	 * Client options for the filter dropdown in the list view.
	 *
	 * @return array<int, string> Client id mapped to display name.
	 */
	public function client_options(): array {
		return $this->clients->options( false );
	}

	/**
	 * Load rows for the current page, applying search, filters and sorting.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = (int) $this->get_items_per_page( 'wp_bizwit_projects_per_page', 20 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list state, not a state change.
		$args = array(
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'client_id' => isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0,
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'updated_at',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			'per_page'  => $per_page,
			'page'      => $this->get_pagenum(),
		);
		// phpcs:enable

		$result = $this->projects->query( $args );

		$this->status_counts = $this->projects->counts_by_status();
		$this->items         = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	/**
	 * Message shown when no projects match.
	 *
	 * @return void
	 */
	public function no_items(): void {
		echo '<div class="wp-bizwit-empty">';
		echo '<p>' . esc_html__( 'No projects yet. Record the work you are doing for a client so you can invoice from it later.', 'wp-bizwit' ) . '</p>';
		if ( current_user_can( Capabilities::MANAGE_PROJECTS ) ) {
			$url = add_query_arg(
				array(
					'page'   => Projects_Screen::SLUG,
					'action' => 'new',
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<p><a class="button button-primary" href="%s">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'Add project', 'wp-bizwit' )
			);
		}
		echo '</div>';
	}

	/**
	 * Row selection checkbox.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Checkbox markup.
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="project_ids[]" value="%d" />',
			(int) $item['id']
		);
	}

	/**
	 * Name column, including the row action links.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_name( array $item ): string {
		$id       = (int) $item['id'];
		$base     = add_query_arg( array( 'page' => Projects_Screen::SLUG ), admin_url( 'admin.php' ) );
		$edit_url = add_query_arg(
			array(
				'action'  => 'edit',
				'project' => $id,
			),
			$base
		);

		$name = (string) $item['name'];
		$code = (string) ( $item['code'] ?? '' );

		if ( '' !== $code ) {
			$name_html = esc_html( $name ) . ' <span class="description">(' . esc_html( $code ) . ')</span>';
		} else {
			$name_html = esc_html( $name );
		}

		if ( ! current_user_can( Capabilities::MANAGE_PROJECTS ) ) {
			return '<strong>' . $name_html . '</strong>';
		}

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'delete',
					'project' => $id,
				),
				$base
			),
			'wp_bizwit_delete_project_' . $id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'wp-bizwit' ) ),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url( $delete_url ),
				esc_js( wp_json_encode( __( 'Permanently delete this project? This cannot be undone.', 'wp-bizwit' ) ) ),
				esc_html__( 'Delete', 'wp-bizwit' )
			),
		);

		return sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>%3$s',
			esc_url( $edit_url ),
			$name_html,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Client column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
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
	 * @return string Cell markup.
	 */
	public function column_status( array $item ): string {
		$statuses = Project_Repository::statuses();
		$status   = (string) $item['status'];

		return sprintf(
			'<span class="wp-bizwit-status wp-bizwit-status--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $statuses[ $status ] ?? $status )
		);
	}

	/**
	 * Billing type column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_billing_type( array $item ): string {
		$types = Project_Repository::billing_types();
		$type  = (string) $item['billing_type'];

		return esc_html( $types[ $type ] ?? $type );
	}

	/**
	 * Budget column, formatted with currency.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_budget( array $item ): string {
		$minor    = (int) ( $item['budget_minor'] ?? 0 );
		$currency = (string) ( $item['currency'] ?? 'IDR' );

		if ( 0 === $minor ) {
			return '&mdash;';
		}

		return esc_html( Money::format( $minor, $currency ) );
	}

	/**
	 * Last updated column, rendered relative to now.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_updated_at( array $item ): string {
		$updated = (string) $item['updated_at'];
		$stamp   = strtotime( $updated );

		if ( false === $stamp ) {
			return '&mdash;';
		}

		return sprintf(
			'<span title="%1$s">%2$s</span>',
			esc_attr( $updated ),
			esc_html(
				sprintf(
					/* translators: %s: human readable time difference, e.g. "2 hours" */
					__( '%s ago', 'wp-bizwit' ),
					human_time_diff( $stamp, (int) current_time( 'timestamp' ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				)
			)
		);
	}

	/**
	 * Fallback renderer for columns without a dedicated method.
	 *
	 * @param array<string, mixed> $item        Row data.
	 * @param string               $column_name Column id.
	 *
	 * @return string Cell markup.
	 */
	public function column_default( $item, $column_name ): string {
		$value = isset( $item[ $column_name ] ) ? (string) $item[ $column_name ] : '';

		return '' === $value ? '&mdash;' : esc_html( $value );
	}
}
