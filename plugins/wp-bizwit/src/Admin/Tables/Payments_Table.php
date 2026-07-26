<?php
/**
 * List table for payment records.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Tables;

use WP_List_Table;
use WP_BizWit\Admin\Screens\Payments_Screen;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Payment_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; // @phpstan-ignore requireOnce.fileNotFound
}

/**
 * Renders the payments index.
 */
class Payments_Table extends WP_List_Table {

	/**
	 * Payment repository.
	 *
	 * @var Payment_Repository
	 */
	private Payment_Repository $payments;

	/**
	 * Client repository.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Payment repository.
	 *
	 * @param Payment_Repository $payments Payment repository.
	 * @param Client_Repository  $clients  Client repository.
	 */
	public function __construct( Payment_Repository $payments, Client_Repository $clients ) {
		$this->payments = $payments;
		$this->clients  = $clients;

		parent::__construct(
			array(
				'singular' => 'payment',
				'plural'   => 'payments',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'receipt_number' => __( 'Receipt', 'wp-bizwit' ),
			'client'         => __( 'Client', 'wp-bizwit' ),
			'invoice'        => __( 'Invoice', 'wp-bizwit' ),
			'paid_on'        => __( 'Date', 'wp-bizwit' ),
			'method'         => __( 'Method', 'wp-bizwit' ),
			'amount'         => __( 'Received', 'wp-bizwit' ),
			'withheld'       => __( 'Withheld', 'wp-bizwit' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'receipt_number' => array( 'receipt_number', false ),
			'paid_on'        => array( 'paid_on', true ),
			'method'         => array( 'method', false ),
			'amount'         => array( 'amount_minor', false ),
			'client'         => array( 'client_name', false ),
			'invoice'        => array( 'invoice_number', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		if ( ! current_user_can( Capabilities::MANAGE_PAYMENTS ) ) {
			return array();
		}

		return array(
			'delete' => __( 'Delete', 'wp-bizwit' ),
		);
	}

	/**
	 * Client filter options.
	 *
	 * @return array<int, string>
	 */
	public function client_options(): array {
		return $this->clients->options( false );
	}

	/**
	 * Payment method filter options.
	 *
	 * @return array<string, string>
	 */
	public function method_options(): array {
		return Regions::current()->payment_methods();
	}

	/**
	 * Load page rows.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = (int) $this->get_items_per_page( 'wp_bizwit_payments_per_page', 20 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list state.
		$args = array(
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'client_id'  => isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0,
			'invoice_id' => isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0,
			'method'     => isset( $_GET['method'] ) ? sanitize_key( wp_unslash( $_GET['method'] ) ) : '',
			'orderby'    => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'paid_on',
			'order'      => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			'per_page'   => $per_page,
			'page'       => $this->get_pagenum(),
		);
		// phpcs:enable

		$result      = $this->payments->query( $args );
		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'receipt_number' );
	}

	/**
	 * Empty state message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		echo '<div class="wp-bizwit-empty" role="status">';
		echo '<p>' . esc_html__( 'No payments recorded yet. When money arrives in the bank, record it here against an invoice.', 'wp-bizwit' ) . '</p>';
		if ( current_user_can( Capabilities::MANAGE_PAYMENTS ) ) {
			$url = add_query_arg(
				array(
					'page'   => Payments_Screen::SLUG,
					'action' => 'new',
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<p><a class="button button-primary" href="%s">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'Record payment', 'wp-bizwit' )
			);
		}
		echo '</div>';
	}

	/**
	 * Row checkbox.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="payment_ids[]" value="%d" />',
			(int) $item['id']
		);
	}

	/**
	 * Receipt number column.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_receipt_number( array $item ): string {
		$id    = (int) $item['id'];
		$base  = add_query_arg( array( 'page' => Payments_Screen::SLUG ), admin_url( 'admin.php' ) );
		$edit  = add_query_arg(
			array(
				'action'  => 'edit',
				'payment' => $id,
			),
			$base
		);
		$print = add_query_arg(
			array(
				'action'  => 'print',
				'payment' => $id,
			),
			$base
		);

		$number = (string) $item['receipt_number'];

		if ( ! current_user_can( Capabilities::MANAGE_PAYMENTS ) ) {
			return '<strong>' . esc_html( $number ) . '</strong>';
		}

		$delete = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'delete',
					'payment' => $id,
				),
				$base
			),
			'wp_bizwit_delete_payment_' . $id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'wp-bizwit' ) ),
			'print'  => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $print ),
				esc_html__( 'Print receipt', 'wp-bizwit' )
			),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url( $delete ),
				esc_js( wp_json_encode( __( 'Delete this payment? The invoice balance will be recalculated.', 'wp-bizwit' ) ) ),
				esc_html__( 'Delete', 'wp-bizwit' )
			),
		);

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
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_client( array $item ): string {
		$name = (string) ( $item['client_name'] ?? '' );

		return '' === $name ? '&mdash;' : esc_html( $name );
	}

	/**
	 * Invoice column.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_invoice( array $item ): string {
		$num = (string) ( $item['invoice_number'] ?? '' );
		$id  = (int) ( $item['invoice_id'] ?? 0 );

		if ( '' === $num || $id <= 0 ) {
			return '&mdash;';
		}

		$url = add_query_arg(
			array(
				'page'    => 'wp-bizwit-invoices',
				'action'  => 'edit',
				'invoice' => $id,
			),
			admin_url( 'admin.php' )
		);

		return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $num ) );
	}

	/**
	 * Date column.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_paid_on( array $item ): string {
		$date = (string) ( $item['paid_on'] ?? '' );
		if ( '' === $date || '0000-00-00' === $date ) {
			return '&mdash;';
		}
		$ts = strtotime( $date );

		return false === $ts ? esc_html( $date ) : esc_html( wp_date( get_option( 'date_format' ), $ts ) );
	}

	/**
	 * Method column.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_method( array $item ): string {
		$methods = Regions::current()->payment_methods();
		$method  = (string) $item['method'];

		return esc_html( $methods[ $method ] ?? $method );
	}

	/**
	 * Received amount column.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_amount( array $item ): string {
		return esc_html( Money::format( (int) $item['amount_minor'], (string) $item['currency'] ) );
	}

	/**
	 * Withheld amount column.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string
	 */
	public function column_withheld( array $item ): string {
		$w = (int) ( $item['withheld_minor'] ?? 0 );
		if ( 0 === $w ) {
			return '&mdash;';
		}

		return esc_html( Money::format( $w, (string) $item['currency'] ) );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		$value = isset( $item[ $column_name ] ) ? (string) $item[ $column_name ] : '';

		return '' === $value ? '&mdash;' : esc_html( $value );
	}
}
