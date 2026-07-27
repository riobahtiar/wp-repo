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
	 * @param array<string, mixed>             $invoice     Invoice row.
	 * @param array<int, array<string, mixed>> $items       Line items.
	 * @param array<string, mixed>|null        $client      Client row.
	 * @param array<string, mixed>|null        $project     Project row.
	 * @param int|null                         $template_id Optional template post id (default when null).
	 * @param array<string, mixed>             $options     public, no_print_bar, template_options.
	 *
	 * @return string|null Full HTML document, or null when no template.
	 */
	public function render_invoice_document(
		array $invoice,
		array $items,
		?array $client,
		?array $project,
		?int $template_id = null,
		array $options = array()
	): ?string {
		$template = null;
		if ( null !== $template_id && $template_id > 0 ) {
			$post = get_post( $template_id );
			if ( $post instanceof WP_Post && Template_Post_Type::POST_TYPE === $post->post_type && 'publish' === $post->post_status ) {
				$template = $post;
			}
		}
		if ( ! $template instanceof WP_Post ) {
			$template = Template_Post_Type::get_default( 'invoice' );
		}
		if ( ! $template instanceof WP_Post ) {
			return null;
		}

		// Resolve layout first so theme tokens can style the whole document (body + sheet).
		$layout = Layout::get_for_post( (int) $template->ID );
		$page   = is_array( $layout['page'] ?? null ) ? $layout['page'] : array();
		$theme  = sanitize_key( (string) ( $page['theme'] ?? 'classic' ) );

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
		$is_public   = ! empty( $options['public'] );
		$show_bar    = empty( $options['no_print_bar'] );
		$tpl_options = isset( $options['template_options'] ) && is_array( $options['template_options'] )
			? $options['template_options']
			: array();
		$current_tpl = (int) $template->ID;

		// Theme CSS variables on <body> so tables/bank/totals always pick them up.
		// marginMm drives left/right sheet padding (defaults to 10mm).
		$margin_mm  = max( 8, min( 30, (int) ( $page['marginMm'] ?? 10 ) ) );
		$body_style = sprintf(
			'--doc-pad-x:%1$dmm;--doc-accent:%2$s;--doc-ink:%3$s;--doc-muted:%4$s;--doc-soft:%5$s;--doc-total-bg:%6$s;--doc-line:%7$s;--doc-line-strong:%3$s;',
			$margin_mm,
			esc_attr( (string) ( $page['accent'] ?? '#1e4d6b' ) ),
			esc_attr( (string) ( $page['ink'] ?? '#1a2332' ) ),
			esc_attr( (string) ( $page['muted'] ?? '#5c6570' ) ),
			esc_attr( (string) ( $page['soft'] ?? '#f4f7f9' ) ),
			esc_attr( (string) ( $page['totalBg'] ?? '#f0f5f8' ) ),
			esc_attr( (string) ( $page['line'] ?? '#e2e6ea' ) )
		);
		if ( ! empty( $page['fontFamily'] ) ) {
			$body_style .= 'font-family:' . esc_attr( (string) $page['fontFamily'] ) . ';';
		}

		$theme_label  = $tpl_options[ $current_tpl ] ?? $template->post_title;
		$table_style  = sanitize_key( (string) ( $page['tableStyle'] ?? 'filled' ) );
		$header_style = sanitize_key( (string) ( $page['headerStyle'] ?? 'rule' ) );

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php if ( $is_public ) : ?>
		<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, noai, noimageai" />
		<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
		<meta name="referrer" content="no-referrer" />
	<?php endif; ?>
	<title><?php echo esc_html( $document_title ); ?></title>
	<style><?php echo Document_Styles::css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS. ?></style>
</head>
<body
	class="wp-bizwit-document wp-bizwit-document--invoice wp-bizwit-theme-<?php echo esc_attr( $theme ); ?> wp-bizwit-table--<?php echo esc_attr( $table_style ); ?> wp-bizwit-header--<?php echo esc_attr( $header_style ); ?><?php echo $is_public ? ' wp-bizwit-document--public' : ''; ?>"
	data-theme="<?php echo esc_attr( $theme ); ?>"
	style="<?php echo esc_attr( $body_style ); ?>"
>
		<?php if ( $show_bar ) : ?>
	<div class="wp-bizwit-print-bar no-print">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'wp-bizwit' ); ?></button>
		<span class="hint"><?php esc_html_e( 'Use A4 paper · browser print dialog', 'wp-bizwit' ); ?></span>
		<span class="wp-bizwit-theme-badge" title="<?php echo esc_attr__( 'Active template', 'wp-bizwit' ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: template name */
					__( 'Template: %s', 'wp-bizwit' ),
					(string) $theme_label
				)
			);
			?>
		</span>
			<?php if ( array() !== $tpl_options && ! $is_public ) : ?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="wp-bizwit-template-switch" id="wp-bizwit-template-form">
				<?php
				// Always re-assert the print route (do not rely on ambient query state).
				printf( '<input type="hidden" name="page" value="%s" />', esc_attr( 'wp-bizwit-invoices' ) );
				printf( '<input type="hidden" name="action" value="%s" />', esc_attr( 'print' ) );
				printf( '<input type="hidden" name="invoice" value="%s" />', esc_attr( (string) (int) ( $invoice['id'] ?? 0 ) ) );
				?>
				<label for="wp-bizwit-template-select">
					<span><?php esc_html_e( 'Change template', 'wp-bizwit' ); ?></span>
					<select name="template" id="wp-bizwit-template-select" onchange="this.form.submit()">
						<?php foreach ( $tpl_options as $tid => $tlabel ) : ?>
							<option value="<?php echo esc_attr( (string) $tid ); ?>" <?php selected( $current_tpl, (int) $tid ); ?>>
								<?php echo esc_html( (string) $tlabel ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="button-print"><?php esc_html_e( 'Apply', 'wp-bizwit' ); ?></button>
			</form>
			<nav class="wp-bizwit-template-chips" aria-label="<?php esc_attr_e( 'Templates', 'wp-bizwit' ); ?>">
				<?php
				$invoice_id = (int) ( $invoice['id'] ?? 0 );
				foreach ( $tpl_options as $tid => $tlabel ) :
					$url       = add_query_arg(
						array(
							'page'     => 'wp-bizwit-invoices',
							'action'   => 'print',
							'invoice'  => $invoice_id,
							'template' => (int) $tid,
						),
						admin_url( 'admin.php' )
					);
					$is_active = ( (int) $tid === $current_tpl );
					?>
					<a
						class="wp-bizwit-template-chip<?php echo $is_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? ' aria-current="true"' : ''; ?>
					><?php echo esc_html( (string) $tlabel ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php endif; ?>
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
					'description'      => __( 'Website maintenance & support', 'wp-bizwit' ),
					'quantity'         => '1.0000',
					// Canonical satuan preset (stored-data vocabulary, not translated).
					'unit'             => 'bulan',
					'unit_price_minor' => 10000000,
					'tax_rate'         => '0.0000',
					'line_total_minor' => 10000000,
					'item_kind'        => 'service',
					'billing_period'   => 'monthly',
					'period_count'     => 0,
					'period_unit'      => '',
				),
				array(
					'description'      => __( 'Implementation & training', 'wp-bizwit' ),
					'quantity'         => '1.0000',
					'unit'             => 'paket',
					'unit_price_minor' => 5000000,
					'tax_rate'         => '0.0000',
					'line_total_minor' => 5000000,
					'item_kind'        => '',
					'billing_period'   => 'one_time',
					'period_count'     => 0,
					'period_unit'      => '',
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
