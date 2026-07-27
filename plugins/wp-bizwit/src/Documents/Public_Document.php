<?php
/**
 * Public (unauthenticated) document preview via short share token.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Invoice_Status;

/**
 * Rewrites /biz-doc/public/{token} and renders noindex invoice HTML.
 */
class Public_Document {

	/**
	 * Query var for the public token.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'wp_bizwit_public_doc';

	/**
	 * Register rewrite + query var + template redirect.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
	}

	/**
	 * Pretty URL: /biz-doc/public/{token}/
	 *
	 * @return void
	 */
	public function add_rewrite(): void {
		add_rewrite_rule(
			'^biz-doc/public/([a-zA-Z0-9]{6,12})/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param string[] $vars Query vars.
	 *
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Absolute public URL for a token (empty when token blank).
	 *
	 * @param string $token Share token.
	 *
	 * @return string
	 */
	public static function url_for_token( string $token ): string {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', $token ) ?? '';
		if ( '' === $token ) {
			return '';
		}
		return home_url( user_trailingslashit( 'biz-doc/public/' . $token ) );
	}

	/**
	 * Render public invoice when the rewrite matches.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		$token = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $token ) || '' === $token ) {
			return;
		}

		$token = preg_replace( '/[^a-zA-Z0-9]/', '', $token ) ?? '';
		if ( strlen( $token ) < 6 || strlen( $token ) > 12 ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Document not found.', 'wp-bizwit' ), '', array( 'response' => 404 ) );
		}

		$invoices = new Invoice_Repository();
		$invoice  = $invoices->find_by_public_token( $token );
		if ( null === $invoice ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Document not found.', 'wp-bizwit' ), '', array( 'response' => 404 ) );
		}

		$status = (string) ( $invoice['status'] ?? '' );
		if ( Invoice_Status::DRAFT === $status || Invoice_Status::VOID === $status ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Document not found.', 'wp-bizwit' ), '', array( 'response' => 404 ) );
		}

		$items   = $invoices->get_items( (int) $invoice['id'] );
		$client  = ( new Client_Repository() )->find( (int) $invoice['client_id'] );
		$project = null;
		if ( (int) ( $invoice['project_id'] ?? 0 ) > 0 ) {
			$project = ( new Project_Repository() )->find( (int) $invoice['project_id'] );
		}

		// Privacy / crawler policy for shared business documents.
		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex, noai, noimageai', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Content-Type-Options: nosniff', true );

		$template_id = absint( $_GET['template'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only.
		$html        = ( new Document_Renderer() )->render_invoice_document(
			$invoice,
			$items,
			$client,
			$project,
			$template_id > 0 ? $template_id : null,
			array(
				'public'  => true,
				'no_print_bar' => false,
			)
		);

		if ( null === $html || '' === $html ) {
			status_header( 404 );
			wp_die( esc_html__( 'Document not found.', 'wp-bizwit' ), '', array( 'response' => 404 ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer builds full HTML document.
		echo $html;
		exit;
	}
}
