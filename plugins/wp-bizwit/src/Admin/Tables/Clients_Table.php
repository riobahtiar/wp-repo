<?php
/**
 * List table for client records.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Tables;

use WP_List_Table;
use WP_BizWit\Admin\Screens\Clients_Screen;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Support\Capabilities;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; // @phpstan-ignore requireOnce.fileNotFound
}

/**
 * Renders the clients index using core's list table chrome.
 *
 * Extending WP_List_Table rather than hand-rolling a table buys sortable
 * columns, bulk actions, search, pagination and screen options that already
 * match the rest of wp-admin - including keyboard and screen reader behaviour
 * that is easy to get wrong from scratch.
 */
class Clients_Table extends WP_List_Table {

	/**
	 * Repository used to load rows.
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
	 * @param Client_Repository $clients Client repository.
	 */
	public function __construct( Client_Repository $clients ) {
		$this->clients = $clients;

		parent::__construct(
			array(
				'singular' => 'client',
				'plural'   => 'clients',
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
			'display_name' => __( 'Name', 'wp-bizwit' ),
			'type'         => __( 'Type', 'wp-bizwit' ),
			'status'       => __( 'Status', 'wp-bizwit' ),
			'email'        => __( 'Email', 'wp-bizwit' ),
			'phone'        => __( 'Phone', 'wp-bizwit' ),
			'location'     => __( 'Location', 'wp-bizwit' ),
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
			'display_name' => array( 'display_name', true ),
			'type'         => array( 'type', false ),
			'status'       => array( 'status', false ),
			'email'        => array( 'email', false ),
			'updated_at'   => array( 'updated_at', false ),
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
		$base    = add_query_arg( array( 'page' => Clients_Screen::SLUG ), admin_url( 'admin.php' ) );
		$total   = array_sum( $this->status_counts );

		$views = array(
			'all' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $base ),
				'' === $current ? ' class="current"' : '',
				esc_html__( 'All', 'wp-bizwit' ),
				(int) $total
			),
		);

		foreach ( Client_Repository::statuses() as $status => $label ) {
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
		if ( ! current_user_can( Capabilities::MANAGE_CLIENTS ) ) {
			return array();
		}

		return array(
			'archive' => __( 'Archive', 'wp-bizwit' ),
			'delete'  => __( 'Delete permanently', 'wp-bizwit' ),
		);
	}

	/**
	 * Load rows for the current page, applying search, filters and sorting.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = (int) $this->get_items_per_page( 'wp_bizwit_clients_per_page', 20 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list state, not a state change.
		$args = array(
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'type'     => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'   => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'display_name',
			'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc',
			'per_page' => $per_page,
			'page'     => $this->get_pagenum(),
		);
		// phpcs:enable

		$result = $this->clients->query( $args );

		$this->status_counts = $this->clients->counts_by_status();
		$this->items         = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'display_name' );
	}

	/**
	 * Message shown when no clients match.
	 *
	 * @return void
	 */
	public function no_items(): void {
		echo '<div class="wp-bizwit-empty">';
		echo '<p>' . esc_html__( 'No clients yet. Add the people and companies you work with — a name is enough to start.', 'wp-bizwit' ) . '</p>';
		if ( current_user_can( Capabilities::MANAGE_CLIENTS ) ) {
			$url = add_query_arg(
				array(
					'page'   => Clients_Screen::SLUG,
					'action' => 'new',
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<p><a class="button button-primary" href="%s">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'Add client', 'wp-bizwit' )
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
			'<input type="checkbox" name="client_ids[]" value="%d" />',
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
	public function column_display_name( array $item ): string {
		$id       = (int) $item['id'];
		$base     = add_query_arg( array( 'page' => Clients_Screen::SLUG ), admin_url( 'admin.php' ) );
		$edit_url = add_query_arg(
			array(
				'action' => 'edit',
				'client' => $id,
			),
			$base
		);

		$name = (string) $item['display_name'];

		if ( ! current_user_can( Capabilities::MANAGE_CLIENTS ) ) {
			return '<strong>' . esc_html( $name ) . '</strong>';
		}

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'delete',
					'client' => $id,
				),
				$base
			),
			'wp_bizwit_delete_client_' . $id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'wp-bizwit' ) ),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url( $delete_url ),
				esc_js( wp_json_encode( __( 'Permanently delete this client? This cannot be undone.', 'wp-bizwit' ) ) ),
				esc_html__( 'Delete', 'wp-bizwit' )
			),
		);

		return sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>%3$s',
			esc_url( $edit_url ),
			esc_html( $name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Client type column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_type( array $item ): string {
		$types = Client_Repository::types();
		$type  = (string) $item['type'];

		return esc_html( $types[ $type ] ?? $type );
	}

	/**
	 * Status column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_status( array $item ): string {
		$statuses = Client_Repository::statuses();
		$status   = (string) $item['status'];

		return sprintf(
			'<span class="wp-bizwit-status wp-bizwit-status--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $statuses[ $status ] ?? $status )
		);
	}

	/**
	 * Email column, rendered as a mailto link.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_email( array $item ): string {
		$email = (string) $item['email'];

		if ( '' === $email ) {
			return '&mdash;';
		}

		return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
	}

	/**
	 * Phone column, linked to the region's messaging app where there is one.
	 *
	 * A phone number in an Indonesian client record is far more useful as a
	 * WhatsApp link than as text, because that is where the conversation about
	 * an unpaid invoice actually happens.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_phone( array $item ): string {
		$phone = (string) $item['phone'];

		if ( '' === $phone ) {
			return '&mdash;';
		}

		$messaging = Regions::current()->messaging_url( $phone );

		if ( '' === $messaging ) {
			return sprintf( '<a href="tel:%1$s">%2$s</a>', esc_attr( $phone ), esc_html( $phone ) );
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" title="%2$s">%3$s</a>',
			esc_url( $messaging ),
			esc_attr__( 'Open in WhatsApp', 'wp-bizwit' ),
			esc_html( $phone )
		);
	}

	/**
	 * Combined city and country column.
	 *
	 * @param array<string, mixed> $item Row data.
	 *
	 * @return string Cell markup.
	 */
	public function column_location( array $item ): string {
		$parts = array_filter(
			array(
				(string) $item['city'],
				(string) $item['country'],
			),
			static fn( string $part ): bool => '' !== $part
		);

		return '' === implode( '', $parts ) ? '&mdash;' : esc_html( implode( ', ', $parts ) );
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
