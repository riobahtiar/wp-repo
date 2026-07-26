<?php
/**
 * Render a layout JSON document to HTML.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Turns layout components into print-ready HTML with locale-aware labels.
 */
class Layout_Renderer {

	/**
	 * Render full layout body (all sections).
	 *
	 * @param array<string, mixed> $layout Layout document.
	 *
	 * @return string HTML.
	 */
	public function render( array $layout ): string {
		$layout = Layout::sanitize( $layout );
		$margin = (int) $layout['page']['marginMm'];
		$parts  = array();

		foreach ( array( 'header', 'body', 'footer' ) as $zone ) {
			$comps   = $layout['sections'][ $zone ]['components'] ?? array();
			$inner   = $this->render_components( is_array( $comps ) ? $comps : array() );
			$parts[] = sprintf(
				'<section class="wp-bizwit-doc-section wp-bizwit-doc-section--%1$s" data-area="%1$s">%2$s</section>',
				esc_attr( $zone ),
				$inner
			);
		}

		return sprintf(
			'<div class="wp-bizwit-layout" style="--bw-page-margin:%dmm">%s</div>',
			$margin,
			implode( '', $parts )
		);
	}

	/**
	 * Render a list of components.
	 *
	 * @param array<int, array<string, mixed>> $components Components.
	 *
	 * @return string
	 */
	public function render_components( array $components ): string {
		$html = '';
		foreach ( $components as $comp ) {
			$html .= $this->render_component( $comp );
		}
		return $html;
	}

	/**
	 * Render one component.
	 *
	 * @param array<string, mixed> $comp Component node.
	 *
	 * @return string
	 */
	public function render_component( array $comp ): string {
		$type  = (string) ( $comp['type'] ?? '' );
		$props = isset( $comp['props'] ) && is_array( $comp['props'] ) ? $comp['props'] : array();

		return match ( $type ) {
			'heading'    => $this->render_heading( $props ),
			'text'       => $this->render_text( $props ),
			'field'      => $this->render_field( $props ),
			'line_items' => $this->render_line_items( $props ),
			'totals'     => $this->render_totals( $props ),
			'bank'       => $this->render_bank( $props ),
			'signature'  => $this->render_signature( $props ),
			'spacer'     => $this->render_spacer( $props ),
			'divider'    => $this->render_divider( $props ),
			'columns'    => $this->render_columns( $props ),
			default      => '',
		};
	}

	/**
	 * Render a heading component.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_heading( array $props ): string {
		$level   = max( 1, min( 4, (int) ( $props['level'] ?? 3 ) ) );
		$content = Document_I18n::chrome( (string) ( $props['content'] ?? '' ) );
		return sprintf(
			'<h%1$d class="wp-bizwit-c-heading" style="%2$s">%3$s</h%1$d>',
			$level,
			esc_attr( $this->text_style( $props ) ),
			esc_html( $content )
		);
	}

	/**
	 * Render a free-text component.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_text( array $props ): string {
		// Free text is user-authored; only translate when it matches known chrome.
		$raw     = (string) ( $props['content'] ?? '' );
		$content = in_array( trim( $raw ), Document_I18n::chrome_msgids(), true )
			? Document_I18n::chrome( $raw )
			: $raw;
		return sprintf(
			'<div class="wp-bizwit-c-text" style="%1$s">%2$s</div>',
			esc_attr( $this->text_style( $props ) ),
			nl2br( esc_html( $content ) )
		);
	}

	/**
	 * Render a merge-field component.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_field( array $props ): string {
		$field = (string) ( $props['field'] ?? '' );
		if ( ! Document_Context::is_rendering() ) {
			$label = Merge_Fields::catalogue()[ $field ] ?? $field;
			$ph    = ! empty( $props['showLabel'] ) ? $label . ': {' . $label . '}' : '{' . $label . '}';
			return sprintf(
				'<div class="wp-bizwit-c-field wp-bizwit-c-field--preview" style="%1$s">%2$s</div>',
				esc_attr( $this->text_style( $props ) ),
				esc_html( $ph )
			);
		}

		$value = Merge_Fields::resolve( $field );
		if ( '' === $value ) {
			return '';
		}

		if ( ! empty( $props['showLabel'] ) && array_key_exists( $field, Merge_Fields::catalogue() ) ) {
			return sprintf(
				'<div class="wp-bizwit-c-field" style="%1$s"><span class="wp-bizwit-field-label">%2$s</span> %3$s</div>',
				esc_attr( $this->text_style( $props ) ),
				esc_html( Merge_Fields::catalogue()[ $field ] ),
				$value
			);
		}

		return sprintf(
			'<div class="wp-bizwit-c-field" style="%1$s">%2$s</div>',
			esc_attr( $this->text_style( $props ) ),
			$value
		);
	}

	/**
	 * Render the line-items table.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_line_items( array $props ): string {
		$margin = $this->margin_style( $props );

		if ( ! Document_Context::is_rendering() ) {
			return '<div class="wp-bizwit-c-lines wp-bizwit-c-preview" style="' . esc_attr( $margin ) . '">'
				. esc_html__( 'Line items table', 'wp-bizwit' ) . '</div>';
		}

		$items     = Document_Context::get( 'items', array() );
		$invoice   = Document_Context::get( 'invoice', array() );
		$items     = is_array( $items ) ? $items : array();
		$invoice   = is_array( $invoice ) ? $invoice : array();
		$currency  = (string) ( $invoice['currency'] ?? 'IDR' );
		$show_tax  = ! isset( $props['showTax'] ) || ! empty( $props['showTax'] );
		$tax_on    = $show_tax && ( Settings::charges_sales_tax() || (int) ( $invoice['tax_minor'] ?? 0 ) > 0 );
		$tax_label = (string) Settings::get( 'tax_label', 'PPN' );

		ob_start();
		?>
		<table class="wp-bizwit-lines" style="<?php echo esc_attr( $margin ); ?>">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Description', 'wp-bizwit' ); ?></th>
					<th class="num"><?php esc_html_e( 'Qty', 'wp-bizwit' ); ?></th>
					<th><?php esc_html_e( 'Unit', 'wp-bizwit' ); ?></th>
					<th class="num"><?php esc_html_e( 'Unit price', 'wp-bizwit' ); ?></th>
					<?php if ( $tax_on ) : ?>
						<th class="num"><?php echo esc_html( $tax_label . ' %' ); ?></th>
					<?php endif; ?>
					<th class="num"><?php esc_html_e( 'Amount', 'wp-bizwit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $item['description'] ?? '' ) ); ?></td>
						<td class="num"><?php echo esc_html( rtrim( rtrim( (string) ( $item['quantity'] ?? '' ), '0' ), '.' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['unit'] ?? '' ) ); ?></td>
						<td class="num"><?php echo esc_html( Money::format( (int) ( $item['unit_price_minor'] ?? 0 ), $currency ) ); ?></td>
						<?php if ( $tax_on ) : ?>
							<td class="num"><?php echo esc_html( rtrim( rtrim( (string) ( $item['tax_rate'] ?? '' ), '0' ), '.' ) ); ?></td>
						<?php endif; ?>
						<td class="num"><?php echo esc_html( Money::format( (int) ( $item['line_total_minor'] ?? 0 ), $currency ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the totals block.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_totals( array $props ): string {
		$margin = $this->margin_style( $props );
		$align  = (string) ( $props['align'] ?? 'right' );

		if ( ! Document_Context::is_rendering() ) {
			return '<div class="wp-bizwit-c-totals wp-bizwit-c-preview" style="' . esc_attr( $margin ) . '">'
				. esc_html__( 'Totals', 'wp-bizwit' ) . '</div>';
		}

		$invoice    = Document_Context::get( 'invoice', array() );
		$invoice    = is_array( $invoice ) ? $invoice : array();
		$currency   = (string) ( $invoice['currency'] ?? 'IDR' );
		$subtotal   = (int) ( $invoice['subtotal_minor'] ?? 0 );
		$discount   = (int) ( $invoice['discount_minor'] ?? 0 );
		$tax        = (int) ( $invoice['tax_minor'] ?? 0 );
		$total      = (int) ( $invoice['total_minor'] ?? 0 );
		$paid       = (int) ( $invoice['paid_minor'] ?? 0 );
		$wht        = (int) ( $invoice['withholding_minor'] ?? 0 );
		$balance    = Invoice_Totals::balance_minor( $total, $paid );
		$tax_label  = (string) Settings::get( 'tax_label', 'PPN' );
		$wht_label  = (string) Settings::get( 'withholding_label', 'PPh 23' );
		$tax_on     = Settings::charges_sales_tax() || $tax > 0;
		$show_words = ! isset( $props['showTerbilang'] ) || ! empty( $props['showTerbilang'] );
		$wrap_style = $margin . ';text-align:' . $align;

		ob_start();
		?>
		<div class="wp-bizwit-c-totals-wrap" style="<?php echo esc_attr( $wrap_style ); ?>">
			<table class="wp-bizwit-totals">
				<tr>
					<td><?php esc_html_e( 'Subtotal', 'wp-bizwit' ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( $subtotal, $currency ) ); ?></td>
				</tr>
				<?php if ( $discount > 0 ) : ?>
					<tr>
						<td><?php esc_html_e( 'Discount', 'wp-bizwit' ); ?></td>
						<td class="num">− <?php echo esc_html( Money::format( $discount, $currency ) ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $tax_on ) : ?>
					<tr>
						<td><?php echo esc_html( $tax_label ); ?></td>
						<td class="num"><?php echo esc_html( Money::format( $tax, $currency ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="grand">
					<td><?php esc_html_e( 'Total', 'wp-bizwit' ); ?></td>
					<td class="num"><?php echo esc_html( Money::format( $total, $currency ) ); ?></td>
				</tr>
				<?php if ( $wht > 0 ) : ?>
					<tr>
						<td><?php echo esc_html( $wht_label ); ?></td>
						<td class="num">− <?php echo esc_html( Money::format( $wht, $currency ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Net expected', 'wp-bizwit' ); ?></td>
						<td class="num"><?php echo esc_html( Money::format( $total - $wht, $currency ) ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $paid > 0 ) : ?>
					<tr>
						<td><?php esc_html_e( 'Paid', 'wp-bizwit' ); ?></td>
						<td class="num"><?php echo esc_html( Money::format( $paid, $currency ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Balance', 'wp-bizwit' ); ?></td>
						<td class="num"><?php echo esc_html( Money::format( $balance, $currency ) ); ?></td>
					</tr>
				<?php endif; ?>
			</table>
			<?php if ( $show_words && 'IDR' === $currency && $total > 0 ) : ?>
				<p class="wp-bizwit-terbilang muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: amount in words */
							__( 'In words: %s', 'wp-bizwit' ),
							Money::in_words( $total, $currency )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render bank details.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_bank( array $props ): string {
		$margin = $this->margin_style( $props );
		$block  = Merge_Fields::resolve( 'bank_block' );
		if ( '' === $block && Document_Context::is_rendering() ) {
			return '';
		}
		if ( ! Document_Context::is_rendering() ) {
			$block = esc_html__( 'Bank details', 'wp-bizwit' );
		}
		$title = '';
		if ( ! empty( $props['showTitle'] ) ) {
			$title = '<div class="wp-bizwit-field-label">' . esc_html__( 'Payment details', 'wp-bizwit' ) . '</div>';
		}
		return '<div class="wp-bizwit-c-bank" style="' . esc_attr( $margin ) . '">' . $title . $block . '</div>';
	}

	/**
	 * Render signature area.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_signature( array $props ): string {
		$margin = $this->margin_style( $props );
		$biz    = (string) Settings::get( 'business_name', get_bloginfo( 'name' ) );
		ob_start();
		?>
		<div class="wp-bizwit-sign" style="<?php echo esc_attr( $margin ); ?>">
			<div class="wp-bizwit-sign-box">
				<strong><?php esc_html_e( 'Received by', 'wp-bizwit' ); ?></strong>
				<span class="muted"><?php esc_html_e( 'Name & signature', 'wp-bizwit' ); ?></span>
			</div>
			<div class="wp-bizwit-sign-box">
				<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: business name */
						__( 'For %s', 'wp-bizwit' ),
						$biz
					)
				);
				?>
				</strong>
				<span class="muted"><?php esc_html_e( 'Signature and company stamp', 'wp-bizwit' ); ?></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render vertical spacer.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_spacer( array $props ): string {
		$h = max( 4, min( 120, (int) ( $props['height'] ?? 16 ) ) );
		return '<div class="wp-bizwit-c-spacer" style="height:' . esc_attr( (string) $h ) . 'px"></div>';
	}

	/**
	 * Render a horizontal divider.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_divider( array $props ): string {
		$color  = (string) ( $props['color'] ?? '#c3c4c7' );
		$margin = $this->margin_style( $props );
		return '<hr class="wp-bizwit-c-divider" style="border:0;border-top:1px solid ' . esc_attr( $color ) . ';' . esc_attr( $margin ) . '" />';
	}

	/**
	 * Render a multi-column row.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function render_columns( array $props ): string {
		$cols   = isset( $props['columns'] ) && is_array( $props['columns'] ) ? $props['columns'] : array( array(), array() );
		$gap    = max( 8, min( 48, (int) ( $props['gap'] ?? 24 ) ) );
		$margin = $this->margin_style( $props );
		$html   = '<div class="wp-bizwit-c-columns" style="display:flex;gap:' . esc_attr( (string) $gap ) . 'px;flex-wrap:wrap;' . esc_attr( $margin ) . '">';
		foreach ( $cols as $col ) {
			$inner = is_array( $col ) ? $this->render_components( $col ) : '';
			$html .= '<div class="wp-bizwit-c-column" style="flex:1;min-width:40%">' . $inner . '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Inline text styles from props.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function text_style( array $props ): string {
		$parts = array(
			'text-align:' . (string) ( $props['align'] ?? 'left' ),
			'font-size:' . (int) ( $props['fontSize'] ?? 12 ) . 'px',
			'font-weight:' . (string) ( $props['fontWeight'] ?? '400' ),
			'color:' . (string) ( $props['color'] ?? '#1d2327' ),
			$this->margin_style( $props ),
		);
		return implode( ';', array_filter( $parts ) );
	}

	/**
	 * Margin CSS fragment.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	private function margin_style( array $props ): string {
		$mt = (int) ( $props['marginTop'] ?? 0 );
		$mb = (int) ( $props['marginBottom'] ?? 4 );
		return 'margin-top:' . $mt . 'px;margin-bottom:' . $mb . 'px';
	}
}
