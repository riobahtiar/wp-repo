<?php
/**
 * Renders printable documents from layout templates.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_Post;
use WP_BizWit\Support\Invoice_Status;

/**
 * Applies a document template with merge context and print chrome.
 */
class Document_Renderer {

	/**
	 * Render a full invoice HTML document (standalone page).
	 *
	 * Prefers the layout-builder JSON on the default invoice template.
	 * Falls back to Gutenberg post_content, then null for legacy PHP view.
	 *
	 * @param array<string, mixed>             $invoice Invoice row.
	 * @param array<int, array<string, mixed>> $items   Line items.
	 * @param array<string, mixed>|null        $client  Client row.
	 * @param array<string, mixed>|null        $project Project row.
	 *
	 * @return string|null Full HTML document, or null when no template.
	 */
	public function render_invoice_document(
		array $invoice,
		array $items,
		?array $client,
		?array $project
	): ?string {
		$template = Template_Post_Type::get_default( 'invoice' );
		if ( ! $template instanceof WP_Post ) {
			return null;
		}

		$body = $this->render_template(
			$template,
			'invoice',
			array(
				'invoice' => $invoice,
				'items'   => $items,
				'client'  => $client,
				'project' => $project,
			)
		);

		if ( '' === trim( $body ) ) {
			return null;
		}

		$status         = (string) ( $invoice['status'] ?? '' );
		$document_title = sprintf(
			/* translators: %s: invoice number */
			__( 'Invoice %s', 'wp-bizwit' ),
			(string) ( $invoice['invoice_number'] ?? '' )
		);

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $document_title ); ?></title>
	<style><?php echo $this->print_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS. ?></style>
</head>
<body class="wp-bizwit-document wp-bizwit-document--invoice">
	<p class="no-print muted">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'wp-bizwit' ); ?></button>
		—
		<?php esc_html_e( 'Use your browser’s print dialog to save as PDF (A4).', 'wp-bizwit' ); ?>
	</p>
		<?php if ( Invoice_Status::VOID === $status ) : ?>
		<div class="void-banner"><?php esc_html_e( 'VOID', 'wp-bizwit' ); ?></div>
	<?php endif; ?>
	<div class="wp-bizwit-document__content">
		<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes field values. ?>
	</div>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render template body with document context.
	 *
	 * @param WP_Post              $template Template post.
	 * @param string               $type     Document type.
	 * @param array<string, mixed> $data     Context payload.
	 *
	 * @return string HTML fragment.
	 */
	public function render_template( WP_Post $template, string $type, array $data ): string {
		Document_Context::set( $type, $data );

		$layout         = Layout::get_for_post( (int) $template->ID );
		$has_components = false;
		foreach ( array( 'header', 'body', 'footer' ) as $zone ) {
			$comps = $layout['sections'][ $zone ]['components'] ?? array();
			if ( is_array( $comps ) && array() !== $comps ) {
				$has_components = true;
				break;
			}
		}

		if ( $has_components ) {
			$html = ( new Layout_Renderer() )->render( $layout );
		} else {
			// Legacy Gutenberg post_content fallback.
			$html = do_blocks( (string) $template->post_content );
		}

		/**
		 * Filters rendered document HTML.
		 *
		 * @param string  $html     Rendered HTML.
		 * @param WP_Post $template Template post.
		 * @param string  $type     Document type.
		 * @param array   $data     Context.
		 */
		$html = (string) apply_filters( 'wp_bizwit_rendered_document', $html, $template, $type, $data );

		Document_Context::clear();

		return $html;
	}

	/**
	 * Shared print stylesheet for A4 documents.
	 *
	 * @return string CSS.
	 */
	private function print_css(): string {
		return <<<'CSS'
@page { size: A4; margin: 16mm; }
* { box-sizing: border-box; }
body {
	font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	font-size: 11pt;
	color: #1d2327;
	margin: 0;
	padding: 12mm;
	line-height: 1.45;
}
.muted { color: #646970; }
.void-banner {
	border: 2px solid #b32d2e;
	color: #b32d2e;
	padding: 3mm;
	margin-bottom: 6mm;
	text-align: center;
	font-weight: 700;
	letter-spacing: 0.08em;
}
.wp-bizwit-doc-section { margin-bottom: 6mm; }
.wp-bizwit-doc-section--header { padding-bottom: 4mm; }
.wp-bizwit-doc-section--footer { margin-top: 6mm; }
.wp-bizwit-field-label { color: #646970; font-weight: 600; margin-right: 2mm; }
table.wp-bizwit-lines {
	width: 100%;
	border-collapse: collapse;
	margin: 4mm 0;
}
table.wp-bizwit-lines th,
table.wp-bizwit-lines td {
	border-bottom: 1px solid #dcdcde;
	padding: 2.5mm 2mm;
	text-align: left;
	vertical-align: top;
}
table.wp-bizwit-lines th {
	font-size: 9pt;
	text-transform: uppercase;
	letter-spacing: 0.02em;
	color: #646970;
}
table.wp-bizwit-lines .num,
table.wp-bizwit-totals .num { text-align: right; white-space: nowrap; }
table.wp-bizwit-totals {
	width: 70mm;
	margin-left: auto;
	margin-bottom: 4mm;
	border-collapse: collapse;
}
table.wp-bizwit-totals td { padding: 1.5mm 2mm; }
table.wp-bizwit-totals .grand td {
	font-weight: 700;
	border-top: 1px solid #1d2327;
	padding-top: 3mm;
}
.wp-bizwit-c-totals-wrap { width: 100%; }
.wp-bizwit-sign {
	display: flex;
	justify-content: space-between;
	gap: 20mm;
	margin-top: 8mm;
}
.wp-bizwit-sign-box {
	width: 45%;
	min-height: 32mm;
	border-top: 1px solid #c3c4c7;
	padding-top: 3mm;
	font-size: 9pt;
	color: #646970;
}
h1, h2, h3, h4 { margin: 0 0 2mm; }
@media print {
	body { padding: 0; }
	.no-print { display: none !important; }
}
CSS;
	}
}
