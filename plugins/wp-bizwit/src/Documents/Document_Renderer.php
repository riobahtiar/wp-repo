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
	<style><?php echo Document_Styles::css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS. ?></style>
</head>
<body class="wp-bizwit-document wp-bizwit-document--invoice">
	<div class="wp-bizwit-print-bar no-print">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'wp-bizwit' ); ?></button>
		<span class="hint"><?php esc_html_e( 'Use A4 paper · browser print dialog', 'wp-bizwit' ); ?></span>
	</div>
	<div class="wp-bizwit-document-sheet">
		<?php if ( Invoice_Status::VOID === $status ) : ?>
			<div class="void-banner"><?php esc_html_e( 'VOID', 'wp-bizwit' ); ?></div>
		<?php endif; ?>
		<div class="wp-bizwit-document__content">
			<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes field values. ?>
		</div>
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
	 * Sample invoice context for the template builder live preview.
	 *
	 * @return array<string, mixed>
	 */
	public static function sample_context(): array {
		$currency = \WP_BizWit\Support\Settings::currency();
		$biz      = (string) \WP_BizWit\Support\Settings::get( 'business_name', get_bloginfo( 'name' ) );

		return array(
			'invoice' => array(
				'invoice_number'    => '007/INV/BW/VII/2026',
				'status'            => 'sent',
				'issue_date'        => '2026-07-15',
				'due_date'          => '2026-08-14',
				'currency'          => $currency,
				'subtotal_minor'    => 15000000,
				'discount_minor'    => 0,
				'tax_minor'         => 0,
				'total_minor'       => 15000000,
				'paid_minor'        => 0,
				'withholding_minor' => 0,
				'withholding_rate'  => '0',
				'notes'             => '',
				'terms'             => __( 'Payment within 30 days via bank transfer.', 'wp-bizwit' ),
			),
			'items'   => array(
				array(
					'description'      => __( 'Website redesign — discovery & UI', 'wp-bizwit' ),
					'quantity'         => '1.0000',
					'unit'             => __( 'package', 'wp-bizwit' ),
					'unit_price_minor' => 10000000,
					'tax_rate'         => '0.0000',
					'line_total_minor' => 10000000,
				),
				array(
					'description'      => __( 'Implementation & training', 'wp-bizwit' ),
					'quantity'         => '1.0000',
					'unit'             => __( 'package', 'wp-bizwit' ),
					'unit_price_minor' => 5000000,
					'tax_rate'         => '0.0000',
					'line_total_minor' => 5000000,
				),
			),
			'client'  => array(
				'display_name' => __( 'PT Nusantara Maju', 'wp-bizwit' ),
				'address'      => "Jl. Sudirman No. 12\nJakarta Selatan 12190",
				'tax_id'       => '01.234.567.8-901.000',
			),
			'project' => array(
				'name' => __( 'Corporate website 2026', 'wp-bizwit' ),
			),
			'_sample' => true,
			'_biz'    => $biz,
		);
	}
}
