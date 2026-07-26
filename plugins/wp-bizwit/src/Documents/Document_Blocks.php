<?php
/**
 * Gutenberg blocks for document templates.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;
use WP_BizWit\WP_BizWit;

/**
 * Registers section, field, line-items and totals blocks.
 *
 * Editor UI: resources/blocks/document-blocks.js (wp.element, no Vue).
 * Render: PHP callbacks so labels follow the active WordPress locale.
 */
class Document_Blocks {

	/**
	 * Script handle for the editor registration file.
	 *
	 * @var string
	 */
	public const EDITOR_SCRIPT = 'wp-bizwit-document-blocks';

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'init', array( $this, 'register_block_types' ), 10 );
	}

	/**
	 * Register the editor script.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$rel  = 'resources/blocks/document-blocks.js';
		$path = WP_BIZWIT_PATH . $rel;
		$url  = WP_BIZWIT_URL . $rel;

		wp_register_script(
			self::EDITOR_SCRIPT,
			$url,
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-data',
			),
			is_readable( $path ) ? (string) filemtime( $path ) : WP_BizWit::PLUGIN_VERSION,
			true
		);

		wp_set_script_translations( self::EDITOR_SCRIPT, 'wp-bizwit', WP_BIZWIT_PATH . 'languages' );

		// Field catalogue for the editor select control (labels in current locale).
		wp_localize_script(
			self::EDITOR_SCRIPT,
			'wpBizwitDocumentBlocks',
			array(
				'fields' => Merge_Fields::catalogue(),
				'areas'  => array(
					'header' => __( 'Header', 'wp-bizwit' ),
					'body'   => __( 'Body', 'wp-bizwit' ),
					'footer' => __( 'Footer', 'wp-bizwit' ),
				),
			)
		);
	}

	/**
	 * Register block types with server render callbacks.
	 *
	 * @return void
	 */
	public function register_block_types(): void {
		register_block_type(
			'wp-bizwit/section',
			array(
				'api_version'     => '2',
				'title'           => __( 'Document section', 'wp-bizwit' ),
				'category'        => 'wp-bizwit',
				'icon'            => 'layout',
				'editor_script'   => self::EDITOR_SCRIPT,
				'attributes'      => array(
					'area' => array(
						'type'    => 'string',
						'default' => 'body',
					),
				),
				'supports'        => array(
					'html'   => false,
					'anchor' => true,
				),
				'render_callback' => array( $this, 'render_section' ),
			)
		);

		register_block_type(
			'wp-bizwit/field',
			array(
				'api_version'     => '2',
				'title'           => __( 'Document field', 'wp-bizwit' ),
				'category'        => 'wp-bizwit',
				'icon'            => 'editor-textcolor',
				'editor_script'   => self::EDITOR_SCRIPT,
				'attributes'      => array(
					'field'     => array(
						'type'    => 'string',
						'default' => 'invoice_number',
					),
					'showLabel' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'supports'        => array(
					'html' => false,
				),
				'render_callback' => array( $this, 'render_field' ),
			)
		);

		register_block_type(
			'wp-bizwit/line-items',
			array(
				'api_version'     => '2',
				'title'           => __( 'Invoice line items', 'wp-bizwit' ),
				'category'        => 'wp-bizwit',
				'icon'            => 'editor-table',
				'editor_script'   => self::EDITOR_SCRIPT,
				'attributes'      => array(),
				'supports'        => array(
					'html' => false,
				),
				'render_callback' => array( $this, 'render_line_items' ),
			)
		);

		register_block_type(
			'wp-bizwit/totals',
			array(
				'api_version'     => '2',
				'title'           => __( 'Invoice totals', 'wp-bizwit' ),
				'category'        => 'wp-bizwit',
				'icon'            => 'money-alt',
				'editor_script'   => self::EDITOR_SCRIPT,
				'attributes'      => array(),
				'supports'        => array(
					'html' => false,
				),
				'render_callback' => array( $this, 'render_totals' ),
			)
		);

		register_block_type(
			'wp-bizwit/signature',
			array(
				'api_version'     => '2',
				'title'           => __( 'Signature block', 'wp-bizwit' ),
				'category'        => 'wp-bizwit',
				'icon'            => 'edit',
				'editor_script'   => self::EDITOR_SCRIPT,
				'attributes'      => array(),
				'supports'        => array(
					'html' => false,
				),
				'render_callback' => array( $this, 'render_signature' ),
			)
		);
	}

	/**
	 * Wrapper for header/body/footer sections.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks HTML.
	 *
	 * @return string
	 */
	public function render_section( array $attributes, string $content ): string {
		$area = sanitize_key( (string) ( $attributes['area'] ?? 'body' ) );
		if ( ! in_array( $area, array( 'header', 'body', 'footer' ), true ) ) {
			$area = 'body';
		}

		return sprintf(
			'<section class="wp-bizwit-doc-section wp-bizwit-doc-section--%1$s" data-area="%1$s">%2$s</section>',
			esc_attr( $area ),
			$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks already rendered/escaped.
		);
	}

	/**
	 * Single merge field.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return string
	 */
	public function render_field( array $attributes ): string {
		$field = sanitize_key( (string) ( $attributes['field'] ?? '' ) );
		if ( '' === $field || ! array_key_exists( $field, Merge_Fields::catalogue() ) ) {
			return '';
		}

		// In the editor (no context) show a placeholder.
		if ( ! Document_Context::is_rendering() ) {
			$label = Merge_Fields::catalogue()[ $field ];
			return '<span class="wp-bizwit-field-placeholder">{' . esc_html( $label ) . '}</span>';
		}

		$value = Merge_Fields::resolve( $field );
		if ( '' === $value ) {
			return '';
		}

		$show_label = ! empty( $attributes['showLabel'] );
		if ( ! $show_label ) {
			return '<span class="wp-bizwit-field wp-bizwit-field--' . esc_attr( $field ) . '">' . $value . '</span>';
		}

		return sprintf(
			'<div class="wp-bizwit-field-row"><span class="wp-bizwit-field-label">%1$s</span> <span class="wp-bizwit-field wp-bizwit-field--%2$s">%3$s</span></div>',
			esc_html( Merge_Fields::catalogue()[ $field ] ),
			esc_attr( $field ),
			$value
		);
	}

	/**
	 * Line items table.
	 *
	 * @return string
	 */
	public function render_line_items(): string {
		if ( ! Document_Context::is_rendering() ) {
			return '<p class="wp-bizwit-field-placeholder">' . esc_html__( 'Line items table', 'wp-bizwit' ) . '</p>';
		}

		$items     = Document_Context::get( 'items', array() );
		$invoice   = Document_Context::get( 'invoice', array() );
		$items     = is_array( $items ) ? $items : array();
		$invoice   = is_array( $invoice ) ? $invoice : array();
		$currency  = (string) ( $invoice['currency'] ?? 'IDR' );
		$tax_on    = Settings::charges_sales_tax() || (int) ( $invoice['tax_minor'] ?? 0 ) > 0;
		$tax_label = (string) Settings::get( 'tax_label', 'PPN' );

		ob_start();
		?>
		<table class="wp-bizwit-lines">
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
	 * Totals table.
	 *
	 * @return string
	 */
	public function render_totals(): string {
		if ( ! Document_Context::is_rendering() ) {
			return '<p class="wp-bizwit-field-placeholder">' . esc_html__( 'Totals', 'wp-bizwit' ) . '</p>';
		}

		$invoice   = Document_Context::get( 'invoice', array() );
		$invoice   = is_array( $invoice ) ? $invoice : array();
		$currency  = (string) ( $invoice['currency'] ?? 'IDR' );
		$subtotal  = (int) ( $invoice['subtotal_minor'] ?? 0 );
		$discount  = (int) ( $invoice['discount_minor'] ?? 0 );
		$tax       = (int) ( $invoice['tax_minor'] ?? 0 );
		$total     = (int) ( $invoice['total_minor'] ?? 0 );
		$paid      = (int) ( $invoice['paid_minor'] ?? 0 );
		$wht       = (int) ( $invoice['withholding_minor'] ?? 0 );
		$balance   = Invoice_Totals::balance_minor( $total, $paid );
		$tax_label = (string) Settings::get( 'tax_label', 'PPN' );
		$wht_label = (string) Settings::get( 'withholding_label', 'PPh 23' );
		$tax_on    = Settings::charges_sales_tax() || $tax > 0;

		ob_start();
		?>
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
		<?php if ( 'IDR' === $currency && $total > 0 ) : ?>
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
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Signature / stamp area.
	 *
	 * @return string
	 */
	public function render_signature(): string {
		$biz = (string) Settings::get( 'business_name', get_bloginfo( 'name' ) );

		ob_start();
		?>
		<div class="wp-bizwit-sign">
			<div class="wp-bizwit-sign-box">
				<?php esc_html_e( 'Received by', 'wp-bizwit' ); ?>
			</div>
			<div class="wp-bizwit-sign-box">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: business name */
						__( 'For %s', 'wp-bizwit' ),
						$biz
					)
				);
				?>
				<br />
				<span class="muted"><?php esc_html_e( 'Signature and company stamp', 'wp-bizwit' ); ?></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
