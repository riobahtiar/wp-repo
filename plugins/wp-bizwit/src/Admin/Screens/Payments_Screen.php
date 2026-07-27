<?php
/**
 * Payments admin screen: list, record, edit, print kwitansi.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Admin\Notices;
use WP_BizWit\Admin\Tables\Payments_Table;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Payment_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Record payments that already happened; issue printable receipts.
 */
class Payments_Screen extends Screen {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-payments';

	/**
	 * Form nonce action.
	 *
	 * @var string
	 */
	private const FORM_NONCE = 'wp_bizwit_save_payment';

	/**
	 * Payment repository.
	 *
	 * @var Payment_Repository
	 */
	private Payment_Repository $payments;

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
	 * Constructor.
	 *
	 * @param Payment_Repository $payments Payment repository.
	 * @param Invoice_Repository $invoices Invoice repository.
	 * @param Client_Repository  $clients  Client repository.
	 */
	public function __construct(
		Payment_Repository $payments,
		Invoice_Repository $invoices,
		Client_Repository $clients
	) {
		$this->payments = $payments;
		$this->invoices = $invoices;
		$this->clients  = $clients;
	}

	/**
	 * Required capability.
	 *
	 * @return string
	 */
	public function capability(): string {
		return Capabilities::MANAGE_PAYMENTS;
	}

	/**
	 * Handle POSTs and actions before output.
	 *
	 * @return void
	 */
	public function on_load(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		if ( isset( $_POST['wp_bizwit_payment_form'] ) ) {
			$this->handle_save();

			return;
		}

		if ( isset( $_POST['payment_ids'] ) ) {
			$this->handle_bulk();

			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable

		if ( 'delete' === $action ) {
			$this->handle_delete();
		} elseif ( 'print' === $action ) {
			$this->handle_print();
		}
	}

	/**
	 * Route list vs form.
	 *
	 * @return void
	 */
	protected function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( (string) ( $_GET['action'] ?? '' ) );

		if ( 'new' === $action || 'edit' === $action ) {
			$this->render_form( $action );

			return;
		}

		$this->render_list();
	}

	/**
	 * Render the payments list.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$table = new Payments_Table( $this->payments, $this->clients );
		$table->prepare_items();

		$this->view(
			'payments-list',
			array(
				'table'    => $table,
				'new_url'  => $this->page_url( self::SLUG, array( 'action' => 'new' ) ),
				'list_url' => $this->page_url( self::SLUG ),
			)
		);
	}

	/**
	 * Render add/edit form.
	 *
	 * @param string $action new|edit.
	 *
	 * @return void
	 */
	private function render_form( string $action ): void {
		$payment = array();
		$invoice = null;

		if ( 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id     = absint( $_GET['payment'] ?? 0 );
			$record = $this->payments->find( $id );

			if ( null === $record ) {
				$this->view( 'payments-missing', array( 'list_url' => $this->page_url( self::SLUG ) ) );

				return;
			}

			$payment = $record;
			$invoice = $this->invoices->find( (int) $record['invoice_id'] );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$invoice_id = absint( $_GET['invoice_id'] ?? 0 );
			if ( 0 < $invoice_id ) {
				$invoice = $this->invoices->find( $invoice_id );
				if ( null !== $invoice ) {
					$payment['invoice_id'] = $invoice_id;
					$payment['client_id']  = (int) $invoice['client_id'];
					$payment['currency']   = (string) $invoice['currency'];
					// Suggest remaining balance as received amount.
					$balance = Invoice_Totals::balance_minor(
						(int) $invoice['total_minor'],
						(int) $invoice['paid_minor']
					);
					// Prefer net of invoice withholding when still open.
					$inv_wht = (int) ( $invoice['withholding_minor'] ?? 0 );
					if ( 0 < $inv_wht && 0 === (int) $invoice['paid_minor'] ) {
						$payment['amount_minor']   = max( 0, (int) $invoice['total_minor'] - $inv_wht );
						$payment['withheld_minor'] = $inv_wht;
					} elseif ( 0 < $balance ) {
						$payment['amount_minor'] = $balance;
					}
				}
			}
		}

		$region      = Regions::current();
		$client_name = '';
		if ( is_array( $invoice ) && ! empty( $invoice['client_id'] ) ) {
			$client = $this->clients->find( (int) $invoice['client_id'] );
			if ( is_array( $client ) ) {
				$client_name = (string) ( $client['display_name'] ?? '' );
			}
		}

		$this->view(
			'payments-form',
			array(
				'payment'         => $payment,
				'invoice'         => $invoice,
				'client_name'     => $client_name,
				'is_edit'         => 'edit' === $action,
				'region'          => $region,
				'invoice_options' => $this->invoices->open_select_options(),
				'methods'         => $region->payment_methods(),
				'handles_tax'     => Settings::handles_tax(),
				'wht_label'       => (string) Settings::get( 'withholding_label', 'PPh 23' ),
				'defaults'        => array(
					'paid_on' => (string) current_time( 'Y-m-d' ),
					'method'  => 'bank_transfer',
				),
				'nonce_field'     => self::FORM_NONCE,
				'list_url'        => $this->page_url( self::SLUG ),
			)
		);
	}

	/**
	 * Persist the payment form.
	 *
	 * @return void
	 */
	private function handle_save(): void {
		check_admin_referer( self::FORM_NONCE );

		$id    = absint( $_POST['payment_id'] ?? 0 );
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $id > 0 ) {
			$result = $this->payments->update( $id, $input );
		} else {
			$result = $this->payments->create( $input );
		}

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
			$this->redirect_to_form( $id );

			return;
		}

		$saved_id = $id > 0 ? $id : (int) $result;

		// Overpayment notice.
		$payment = $this->payments->find( $saved_id );
		if ( null !== $payment ) {
			$invoice = $this->invoices->find( (int) $payment['invoice_id'] );
			if ( null !== $invoice && (int) $invoice['paid_minor'] > (int) $invoice['total_minor'] ) {
				$credit = (int) $invoice['paid_minor'] - (int) $invoice['total_minor'];
				Notices::add(
					sprintf(
						/* translators: %s: overpaid amount */
						__( 'Payment saved. This invoice is overpaid by %s — treat the excess as credit.', 'wp-bizwit' ),
						Money::format( $credit, (string) $invoice['currency'] )
					),
					'warning'
				);
			} else {
				Notices::add(
					$id > 0
						? __( 'Payment updated.', 'wp-bizwit' )
						: __( 'Payment recorded.', 'wp-bizwit' ),
					'success'
				);
			}
		}

		$this->redirect_to_form( $saved_id );
	}

	/**
	 * Delete a single payment.
	 *
	 * @return void
	 */
	private function handle_delete(): void {
		$id = absint( $_GET['payment'] ?? 0 );
		check_admin_referer( 'wp_bizwit_delete_payment_' . $id );

		$result = $this->payments->delete( $id );

		if ( is_wp_error( $result ) ) {
			Notices::add( $result->get_error_message(), 'error' );
		} else {
			Notices::add( __( 'Payment deleted. Invoice balance recalculated.', 'wp-bizwit' ), 'success' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Bulk delete selected payments.
	 *
	 * @return void
	 */
	private function handle_bulk(): void {
		check_admin_referer( 'bulk-payments' );

		$table  = new Payments_Table( $this->payments, $this->clients );
		$action = $table->current_action();

		if ( 'delete' !== $action ) {
			return;
		}

		$raw = wp_unslash( $_POST['payment_ids'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids = array_filter( array_map( 'absint', (array) $raw ) );

		$done     = 0;
		$failures = array();

		foreach ( $ids as $id ) {
			$result = $this->payments->delete( $id );
			if ( is_wp_error( $result ) ) {
				$failures[] = $result->get_error_message();
				continue;
			}
			++$done;
		}

		if ( $done > 0 ) {
			Notices::add(
				sprintf(
					/* translators: %d: number of payments */
					_n( '%d payment deleted.', '%d payments deleted.', $done, 'wp-bizwit' ),
					$done
				),
				'success'
			);
		}

		if ( array() !== $failures ) {
			Notices::add( implode( ' ', array_unique( $failures ) ), 'error' );
		}

		$this->redirect_to_list();
	}

	/**
	 * Print kwitansi HTML and exit.
	 *
	 * @return void
	 */
	private function handle_print(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; capability checked.
		$id      = absint( $_GET['payment'] ?? 0 );
		$payment = $this->payments->find( $id );

		if ( null === $payment ) {
			wp_die( esc_html__( 'That payment no longer exists.', 'wp-bizwit' ) );
		}

		$invoice = $this->invoices->find( (int) $payment['invoice_id'] );
		$client  = $this->clients->find( (int) $payment['client_id'] );

		status_header( 200 );
		nocache_headers();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes.
		include WP_BIZWIT_PATH . 'src/Admin/Views/payments-print.php';
		exit;
	}

	/**
	 * PRG redirect to the form.
	 *
	 * @param int $id Payment id.
	 *
	 * @return void
	 */
	private function redirect_to_form( int $id ): void {
		$args = $id > 0
			? array(
				'action'  => 'edit',
				'payment' => $id,
			)
			: array( 'action' => 'new' );

		wp_safe_redirect( $this->page_url( self::SLUG, $args ) );
		exit;
	}

	/**
	 * PRG redirect to the list.
	 *
	 * @return void
	 */
	private function redirect_to_list(): void {
		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
	}
}
