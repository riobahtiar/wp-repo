<?php
/**
 * Document layout schema (JSON stored on template posts).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

/**
 * Validates and normalises layout builder documents.
 *
 * Layout is a visual A4 document with three zones (header, body, footer).
 * Each zone holds ordered components with per-component style settings.
 */
class Layout {

	/**
	 * Post meta key for the layout JSON.
	 *
	 * @var string
	 */
	public const META_KEY = '_wp_bizwit_layout';

	/**
	 * Schema version embedded in saved layouts.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Allowed component type slugs.
	 *
	 * @return string[]
	 */
	public static function component_types(): array {
		return array(
			'heading',
			'text',
			'field',
			'logo',
			'line_items',
			'totals',
			'bank',
			'signature',
			'spacer',
			'divider',
			'columns',
		);
	}

	/**
	 * Gallery of named invoice layout themes (slug → title + layout factory).
	 *
	 * @return array<string, array{title: string, description: string}>
	 */
	public static function gallery_meta(): array {
		return array(
			'classic'      => array(
				'title'       => __( 'Classic', 'wp-bizwit' ),
				'description' => __( 'Clean two-column header with balanced typography.', 'wp-bizwit' ),
			),
			'modern'       => array(
				'title'       => __( 'Modern', 'wp-bizwit' ),
				'description' => __( 'Bold accent title, open spacing, minimal chrome.', 'wp-bizwit' ),
			),
			'professional' => array(
				'title'       => __( 'Professional', 'wp-bizwit' ),
				'description' => __( 'Corporate band header with logo and formal structure.', 'wp-bizwit' ),
			),
			'minimal'      => array(
				'title'       => __( 'Minimal', 'wp-bizwit' ),
				'description' => __( 'Sparse layout focused on line items and totals.', 'wp-bizwit' ),
			),
			'elegant'      => array(
				'title'       => __( 'Elegant', 'wp-bizwit' ),
				'description' => __( 'Serif-like hierarchy with centered brand mark.', 'wp-bizwit' ),
			),
			'compact'      => array(
				'title'       => __( 'Compact', 'wp-bizwit' ),
				'description' => __( 'Dense A4 packing for long line lists.', 'wp-bizwit' ),
			),
		);
	}

	/**
	 * Build layout JSON for a gallery theme slug.
	 *
	 * @param string $slug Theme slug.
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_for_theme( string $slug ): array {
		return match ( $slug ) {
			'modern'       => self::layout_modern(),
			'professional' => self::layout_professional(),
			'minimal'      => self::layout_minimal(),
			'elegant'      => self::layout_elegant(),
			'compact'      => self::layout_compact(),
			default        => self::default_invoice(),
		};
	}

	/**
	 * Empty layout shell.
	 *
	 * @return array<string, mixed>
	 */
	public static function empty(): array {
		return array(
			'version'  => self::VERSION,
			'page'     => array_merge(
				array(
					'size'     => 'A4',
					'marginMm' => 16,
				),
				self::theme_tokens( 'classic' )
			),
			'sections' => array(
				'header' => array( 'components' => array() ),
				'body'   => array( 'components' => array() ),
				'footer' => array( 'components' => array() ),
			),
		);
	}

	/**
	 * Design tokens that drive document CSS variables per theme.
	 *
	 * These are what make gallery templates look different — not just component order.
	 *
	 * @param string $slug Theme slug.
	 *
	 * @return array<string, mixed>
	 */
	public static function theme_tokens( string $slug ): array {
		$presets = array(
			'classic'      => array(
				'theme'       => 'classic',
				'accent'      => '#1e4d6b',
				'ink'         => '#1a2332',
				'muted'       => '#5c6570',
				'soft'        => '#f4f7f9',
				'totalBg'     => '#f0f5f8',
				'line'        => '#e2e6ea',
				// No double-quotes inside fontFamily — they break JSON in post meta.
				'fontFamily'  => 'Segoe UI, Helvetica Neue, Arial, sans-serif',
				'tableStyle'  => 'filled',
				'headerStyle' => 'rule',
				'band'        => false,
			),
			'modern'       => array(
				'theme'       => 'modern',
				'accent'      => '#0f766e',
				'ink'         => '#134e4a',
				'muted'       => '#5b7c78',
				'soft'        => '#f0fdfa',
				'totalBg'     => '#ccfbf1',
				'line'        => '#99f6e4',
				'fontFamily'  => 'system-ui, Segoe UI, sans-serif',
				'tableStyle'  => 'underline',
				'headerStyle' => 'open',
				'band'        => false,
			),
			'professional' => array(
				'theme'       => 'professional',
				'accent'      => '#0b1f3a',
				'ink'         => '#0f172a',
				'muted'       => '#64748b',
				'soft'        => '#f1f5f9',
				'totalBg'     => '#e2e8f0',
				'line'        => '#cbd5e1',
				'fontFamily'  => 'Georgia, Times New Roman, serif',
				'tableStyle'  => 'filled',
				'headerStyle' => 'band',
				'band'        => true,
			),
			'minimal'      => array(
				'theme'       => 'minimal',
				'accent'      => '#18181b',
				'ink'         => '#18181b',
				'muted'       => '#71717a',
				'soft'        => '#fafafa',
				'totalBg'     => '#f4f4f5',
				'line'        => '#e4e4e7',
				'fontFamily'  => 'Helvetica Neue, Helvetica, Arial, sans-serif',
				'tableStyle'  => 'hairline',
				'headerStyle' => 'open',
				'band'        => false,
			),
			'elegant'      => array(
				'theme'       => 'elegant',
				'accent'      => '#7c2d12',
				'ink'         => '#292524',
				'muted'       => '#78716c',
				'soft'        => '#faf7f5',
				'totalBg'     => '#f5e6d8',
				'line'        => '#e7d5c4',
				'fontFamily'  => 'Georgia, Palatino Linotype, Palatino, serif',
				'tableStyle'  => 'double',
				'headerStyle' => 'centered',
				'band'        => false,
			),
			'compact'      => array(
				'theme'       => 'compact',
				'accent'      => '#334155',
				'ink'         => '#0f172a',
				'muted'       => '#64748b',
				'soft'        => '#f8fafc',
				'totalBg'     => '#e2e8f0',
				'line'        => '#cbd5e1',
				'fontFamily'  => 'Segoe UI, Tahoma, sans-serif',
				'tableStyle'  => 'dense',
				'headerStyle' => 'rule',
				'band'        => false,
			),
		);

		return $presets[ $slug ] ?? $presets['classic'];
	}

	/**
	 * Apply theme tokens onto a layout page object.
	 *
	 * @param array<string, mixed> $layout Layout.
	 * @param string               $slug   Theme slug.
	 * @param int                  $margin Margin mm.
	 *
	 * @return array<string, mixed>
	 */
	public static function with_theme( array $layout, string $slug, int $margin = 14 ): array {
		$layout['page'] = array_merge(
			is_array( $layout['page'] ?? null ) ? $layout['page'] : array(),
			self::theme_tokens( $slug ),
			array(
				'size'     => 'A4',
				'marginMm' => $margin,
			)
		);
		return $layout;
	}

	/**
	 * Default invoice layout (sample).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_invoice(): array {
		$layout = self::with_theme( self::empty(), 'classic', 14 );

		$layout['sections']['header']['components'] = array(
			self::component(
				'columns',
				array(
					'gap'     => 32,
					'columns' => array(
						array(
							self::component(
								'field',
								array(
									'field'        => 'business_name',
									'fontSize'     => 20,
									'fontWeight'   => '700',
									'color'        => '#1e4d6b',
									'marginBottom' => 6,
								)
							),
							self::component(
								'field',
								array(
									'field'        => 'business_address',
									'fontSize'     => 10,
									'color'        => '#5c6570',
									'marginBottom' => 2,
								)
							),
							self::component(
								'field',
								array(
									'field'        => 'business_phone',
									'fontSize'     => 10,
									'color'        => '#5c6570',
									'marginBottom' => 2,
								)
							),
							self::component(
								'field',
								array(
									'field'        => 'business_email',
									'fontSize'     => 10,
									'color'        => '#5c6570',
									'marginBottom' => 4,
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'business_tax_id',
									'showLabel' => true,
									'fontSize'  => 10,
									'color'     => '#5c6570',
								)
							),
						),
						array(
							self::component(
								'field',
								array(
									'field'        => 'document_title',
									'fontSize'     => 26,
									'fontWeight'   => '700',
									'align'        => 'right',
									'color'        => '#1a2332',
									'marginBottom' => 10,
								)
							),
							self::component(
								'field',
								array(
									'field'        => 'invoice_number',
									'showLabel'    => true,
									'align'        => 'right',
									'fontSize'     => 11,
									'fontWeight'   => '600',
									'marginBottom' => 3,
								)
							),
							self::component(
								'field',
								array(
									'field'        => 'issue_date',
									'showLabel'    => true,
									'align'        => 'right',
									'fontSize'     => 11,
									'marginBottom' => 3,
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'due_date',
									'showLabel' => true,
									'align'     => 'right',
									'fontSize'  => 11,
								)
							),
						),
					),
				)
			),
		);

		$layout['sections']['body']['components'] = array(
			self::component(
				'heading',
				array(
					'content'      => 'Bill to',
					'level'        => 3,
					'fontSize'     => 10,
					'fontWeight'   => '600',
					'color'        => '#5c6570',
					'marginBottom' => 6,
				)
			),
			self::component(
				'field',
				array(
					'field'        => 'client_name',
					'fontSize'     => 13,
					'fontWeight'   => '700',
					'marginBottom' => 3,
				)
			),
			self::component(
				'field',
				array(
					'field'        => 'client_address',
					'fontSize'     => 11,
					'color'        => '#5c6570',
					'marginBottom' => 3,
				)
			),
			self::component(
				'field',
				array(
					'field'        => 'client_tax_id',
					'showLabel'    => true,
					'fontSize'     => 10,
					'color'        => '#5c6570',
					'marginBottom' => 3,
				)
			),
			self::component(
				'field',
				array(
					'field'        => 'project_name',
					'showLabel'    => true,
					'fontSize'     => 10,
					'color'        => '#5c6570',
					'marginBottom' => 14,
				)
			),
			self::component(
				'line_items',
				array(
					'showTax'      => true,
					'marginBottom' => 8,
				)
			),
			self::component(
				'totals',
				array(
					'showTerbilang' => true,
					'align'         => 'right',
					'marginTop'     => 4,
				)
			),
		);

		$layout['sections']['footer']['components'] = array(
			self::component(
				'heading',
				array(
					'content'      => 'Payment details',
					'level'        => 3,
					'fontSize'     => 10,
					'fontWeight'   => '600',
					'color'        => '#5c6570',
					'marginTop'    => 8,
					'marginBottom' => 6,
				)
			),
			self::component( 'bank', array( 'marginBottom' => 10 ) ),
			self::component(
				'field',
				array(
					'field'        => 'terms',
					'showLabel'    => true,
					'fontSize'     => 10,
					'color'        => '#5c6570',
					'marginBottom' => 16,
				)
			),
			self::component( 'signature', array( 'marginTop' => 8 ) ),
		);

		// Prefer logo when configured (falls back to empty in renderer).
		array_unshift(
			$layout['sections']['header']['components'],
			self::component(
				'logo',
				array(
					'align'        => 'left',
					'maxHeight'    => 48,
					'marginBottom' => 10,
				)
			)
		);

		return $layout;
	}

	/**
	 * Modern theme: bold accent, open spacing.
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_modern(): array {
		$layout = self::with_theme( self::empty(), 'modern', 18 );
		// Stacked brand left, huge teal title — open, editorial feel.
		$layout['sections']['header']['components'] = array(
			self::component( 'logo', array( 'align' => 'left', 'maxHeight' => 40, 'marginBottom' => 14 ) ),
			self::component(
				'field',
				array(
					'field'        => 'document_title',
					'fontSize'     => 36,
					'fontWeight'   => '700',
					'color'        => '#0f766e',
					'marginBottom' => 2,
				)
			),
			self::component(
				'field',
				array(
					'field'        => 'business_name',
					'fontSize'     => 11,
					'fontWeight'   => '600',
					'color'        => '#0f766e',
					'marginBottom' => 18,
				)
			),
			self::component(
				'columns',
				array(
					'gap'     => 40,
					'columns' => array(
						array(
							self::component( 'field', array( 'field' => 'invoice_number', 'showLabel' => true, 'fontSize' => 10, 'color' => '#134e4a', 'marginBottom' => 6 ) ),
							self::component( 'field', array( 'field' => 'issue_date', 'showLabel' => true, 'fontSize' => 10, 'color' => '#5b7c78', 'marginBottom' => 6 ) ),
							self::component( 'field', array( 'field' => 'due_date', 'showLabel' => true, 'fontSize' => 10, 'color' => '#5b7c78' ) ),
						),
						array(
							self::component( 'field', array( 'field' => 'business_address', 'fontSize' => 9, 'color' => '#5b7c78', 'align' => 'right', 'marginBottom' => 3 ) ),
							self::component( 'field', array( 'field' => 'business_email', 'fontSize' => 9, 'color' => '#5b7c78', 'align' => 'right', 'marginBottom' => 3 ) ),
							self::component( 'field', array( 'field' => 'business_phone', 'fontSize' => 9, 'color' => '#5b7c78', 'align' => 'right' ) ),
						),
					),
				)
			),
			self::component( 'spacer', array( 'height' => 20 ) ),
		);
		$layout['sections']['body']['components'] = array(
			self::component( 'heading', array( 'content' => 'Bill to', 'level' => 3, 'fontSize' => 9, 'fontWeight' => '700', 'color' => '#0f766e', 'marginBottom' => 8 ) ),
			self::component( 'field', array( 'field' => 'client_name', 'fontSize' => 15, 'fontWeight' => '700', 'color' => '#134e4a', 'marginBottom' => 4 ) ),
			self::component( 'field', array( 'field' => 'client_address', 'fontSize' => 10, 'color' => '#5b7c78', 'marginBottom' => 18 ) ),
			self::component( 'line_items', array( 'showTax' => true, 'marginBottom' => 12 ) ),
			self::component( 'totals', array( 'showTerbilang' => true, 'align' => 'right' ) ),
		);
		$layout['sections']['footer']['components'] = array(
			self::component( 'spacer', array( 'height' => 16 ) ),
			self::component( 'heading', array( 'content' => 'Payment details', 'level' => 3, 'fontSize' => 9, 'fontWeight' => '700', 'color' => '#0f766e', 'marginBottom' => 8 ) ),
			self::component( 'bank', array( 'marginBottom' => 12 ) ),
			self::component( 'field', array( 'field' => 'terms', 'fontSize' => 9, 'color' => '#5b7c78', 'marginBottom' => 16 ) ),
			self::component( 'signature', array() ),
		);
		return $layout;
	}

	/**
	 * Professional theme: corporate band with logo.
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_professional(): array {
		// Dark navy band header (CSS --headerStyle: band) + formal serif body.
		$layout = self::with_theme( self::empty(), 'professional', 12 );
		$layout['sections']['header']['components'] = array(
			self::component(
				'columns',
				array(
					'gap'     => 20,
					'columns' => array(
						array(
							self::component( 'logo', array( 'align' => 'left', 'maxHeight' => 52, 'marginBottom' => 8 ) ),
							self::component( 'field', array( 'field' => 'business_name', 'fontSize' => 17, 'fontWeight' => '700', 'color' => '#ffffff', 'marginBottom' => 4 ) ),
							self::component( 'field', array( 'field' => 'business_tax_id', 'showLabel' => true, 'fontSize' => 9, 'color' => '#cbd5e1' ) ),
						),
						array(
							self::component( 'field', array( 'field' => 'document_title', 'fontSize' => 24, 'fontWeight' => '700', 'align' => 'right', 'color' => '#ffffff', 'marginBottom' => 10 ) ),
							self::component( 'field', array( 'field' => 'invoice_number', 'showLabel' => true, 'align' => 'right', 'fontSize' => 10, 'fontWeight' => '600', 'color' => '#e2e8f0', 'marginBottom' => 4 ) ),
							self::component( 'field', array( 'field' => 'issue_date', 'showLabel' => true, 'align' => 'right', 'fontSize' => 10, 'color' => '#cbd5e1', 'marginBottom' => 4 ) ),
							self::component( 'field', array( 'field' => 'due_date', 'showLabel' => true, 'align' => 'right', 'fontSize' => 10, 'color' => '#cbd5e1' ) ),
						),
					),
				)
			),
		);
		$layout['sections']['body']['components'] = array(
			self::component( 'field', array( 'field' => 'business_address', 'fontSize' => 9, 'color' => '#64748b', 'marginTop' => 10, 'marginBottom' => 2 ) ),
			self::component( 'field', array( 'field' => 'business_email', 'fontSize' => 9, 'color' => '#64748b', 'marginBottom' => 14 ) ),
			self::component( 'heading', array( 'content' => 'Bill to', 'level' => 3, 'fontSize' => 9, 'fontWeight' => '700', 'color' => '#0b1f3a', 'marginBottom' => 6 ) ),
			self::component( 'field', array( 'field' => 'client_name', 'fontSize' => 13, 'fontWeight' => '700', 'marginBottom' => 3 ) ),
			self::component( 'field', array( 'field' => 'client_address', 'fontSize' => 10, 'color' => '#64748b', 'marginBottom' => 3 ) ),
			self::component( 'field', array( 'field' => 'client_tax_id', 'showLabel' => true, 'fontSize' => 9, 'color' => '#64748b', 'marginBottom' => 3 ) ),
			self::component( 'field', array( 'field' => 'project_name', 'showLabel' => true, 'fontSize' => 9, 'color' => '#64748b', 'marginBottom' => 12 ) ),
			self::component( 'line_items', array( 'showTax' => true, 'marginBottom' => 8 ) ),
			self::component( 'totals', array( 'showTerbilang' => true, 'align' => 'right' ) ),
		);
		$layout['sections']['footer']['components'] = array(
			self::component( 'heading', array( 'content' => 'Payment details', 'level' => 3, 'fontSize' => 9, 'fontWeight' => '700', 'color' => '#0b1f3a', 'marginTop' => 10, 'marginBottom' => 6 ) ),
			self::component( 'bank', array( 'marginBottom' => 10 ) ),
			self::component( 'field', array( 'field' => 'terms', 'showLabel' => true, 'fontSize' => 9, 'color' => '#64748b', 'marginBottom' => 14 ) ),
			self::component( 'signature', array() ),
		);
		return $layout;
	}

	/**
	 * Minimal theme — almost no chrome, monochrome.
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_minimal(): array {
		$layout = self::with_theme( self::empty(), 'minimal', 20 );
		$layout['sections']['header']['components'] = array(
			self::component(
				'columns',
				array(
					'gap'     => 24,
					'columns' => array(
						array(
							self::component( 'logo', array( 'maxHeight' => 28, 'marginBottom' => 8 ) ),
							self::component( 'field', array( 'field' => 'business_name', 'fontSize' => 11, 'fontWeight' => '600', 'color' => '#18181b' ) ),
						),
						array(
							self::component( 'field', array( 'field' => 'document_title', 'fontSize' => 14, 'fontWeight' => '600', 'align' => 'right', 'color' => '#18181b', 'marginBottom' => 8 ) ),
							self::component( 'field', array( 'field' => 'invoice_number', 'align' => 'right', 'fontSize' => 10, 'color' => '#18181b', 'marginBottom' => 2 ) ),
							self::component( 'field', array( 'field' => 'issue_date', 'align' => 'right', 'fontSize' => 9, 'color' => '#71717a' ) ),
						),
					),
				)
			),
			self::component( 'spacer', array( 'height' => 28 ) ),
		);
		$layout['sections']['body']['components'] = array(
			self::component( 'field', array( 'field' => 'client_name', 'fontSize' => 12, 'fontWeight' => '600', 'marginBottom' => 2 ) ),
			self::component( 'field', array( 'field' => 'client_address', 'fontSize' => 9, 'color' => '#71717a', 'marginBottom' => 20 ) ),
			self::component( 'line_items', array( 'showTax' => true, 'marginBottom' => 10 ) ),
			self::component( 'totals', array( 'showTerbilang' => false, 'align' => 'right' ) ),
		);
		$layout['sections']['footer']['components'] = array(
			self::component( 'spacer', array( 'height' => 24 ) ),
			self::component( 'bank', array( 'marginBottom' => 8 ) ),
			self::component( 'signature', array( 'marginTop' => 28 ) ),
		);
		return $layout;
	}

	/**
	 * Elegant centered theme — warm brown, centered brand.
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_elegant(): array {
		$layout = self::with_theme( self::empty(), 'elegant', 18 );
		$layout['sections']['header']['components'] = array(
			self::component( 'logo', array( 'align' => 'center', 'maxHeight' => 56, 'marginBottom' => 12 ) ),
			self::component( 'field', array( 'field' => 'business_name', 'fontSize' => 16, 'fontWeight' => '700', 'align' => 'center', 'color' => '#7c2d12', 'marginBottom' => 4 ) ),
			self::component( 'field', array( 'field' => 'business_address', 'fontSize' => 9, 'align' => 'center', 'color' => '#78716c', 'marginBottom' => 14 ) ),
			self::component( 'divider', array( 'color' => '#7c2d12', 'marginBottom' => 14 ) ),
			self::component( 'field', array( 'field' => 'document_title', 'fontSize' => 22, 'fontWeight' => '700', 'align' => 'center', 'color' => '#292524', 'marginBottom' => 12 ) ),
			self::component(
				'columns',
				array(
					'gap'     => 24,
					'columns' => array(
						array(
							self::component( 'field', array( 'field' => 'invoice_number', 'showLabel' => true, 'fontSize' => 10, 'color' => '#292524', 'marginBottom' => 4 ) ),
							self::component( 'field', array( 'field' => 'issue_date', 'showLabel' => true, 'fontSize' => 10, 'color' => '#78716c' ) ),
						),
						array(
							self::component( 'field', array( 'field' => 'due_date', 'showLabel' => true, 'align' => 'right', 'fontSize' => 10, 'color' => '#78716c', 'marginBottom' => 4 ) ),
							self::component( 'field', array( 'field' => 'status_label', 'showLabel' => true, 'align' => 'right', 'fontSize' => 10, 'color' => '#78716c' ) ),
						),
					),
				)
			),
		);
		$layout['sections']['body']['components'] = array(
			self::component( 'heading', array( 'content' => 'Bill to', 'level' => 3, 'fontSize' => 9, 'fontWeight' => '700', 'color' => '#7c2d12', 'marginTop' => 16, 'marginBottom' => 8 ) ),
			self::component( 'field', array( 'field' => 'client_name', 'fontSize' => 13, 'fontWeight' => '700', 'color' => '#292524', 'marginBottom' => 3 ) ),
			self::component( 'field', array( 'field' => 'client_address', 'fontSize' => 10, 'color' => '#78716c', 'marginBottom' => 16 ) ),
			self::component( 'line_items', array( 'showTax' => true, 'marginBottom' => 10 ) ),
			self::component( 'totals', array( 'showTerbilang' => true, 'align' => 'right' ) ),
		);
		$layout['sections']['footer']['components'] = array(
			self::component( 'divider', array( 'color' => '#e7d5c4', 'marginTop' => 12, 'marginBottom' => 12 ) ),
			self::component( 'heading', array( 'content' => 'Payment details', 'level' => 3, 'fontSize' => 9, 'fontWeight' => '700', 'color' => '#7c2d12', 'marginBottom' => 8 ) ),
			self::component( 'bank', array( 'marginBottom' => 12 ) ),
			self::component( 'field', array( 'field' => 'terms', 'fontSize' => 9, 'color' => '#78716c', 'marginBottom' => 18 ) ),
			self::component( 'signature', array() ),
		);
		return $layout;
	}

	/**
	 * Compact theme — dense slate packing for long line lists.
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_compact(): array {
		$layout = self::with_theme( self::empty(), 'compact', 9 );
		$layout['sections']['header']['components'] = array(
			self::component(
				'columns',
				array(
					'gap'     => 10,
					'columns' => array(
						array(
							self::component( 'logo', array( 'maxHeight' => 28, 'marginBottom' => 3 ) ),
							self::component( 'field', array( 'field' => 'business_name', 'fontSize' => 10, 'fontWeight' => '700', 'marginBottom' => 1 ) ),
							self::component( 'field', array( 'field' => 'business_phone', 'fontSize' => 8, 'color' => '#64748b' ) ),
						),
						array(
							self::component( 'field', array( 'field' => 'document_title', 'fontSize' => 14, 'fontWeight' => '700', 'align' => 'right', 'color' => '#334155', 'marginBottom' => 3 ) ),
							self::component( 'field', array( 'field' => 'invoice_number', 'showLabel' => true, 'align' => 'right', 'fontSize' => 8, 'marginBottom' => 1 ) ),
							self::component( 'field', array( 'field' => 'issue_date', 'showLabel' => true, 'align' => 'right', 'fontSize' => 8, 'marginBottom' => 1 ) ),
							self::component( 'field', array( 'field' => 'due_date', 'showLabel' => true, 'align' => 'right', 'fontSize' => 8 ) ),
						),
					),
				)
			),
			self::component( 'divider', array( 'marginTop' => 4, 'marginBottom' => 4 ) ),
		);
		$layout['sections']['body']['components'] = array(
			self::component( 'field', array( 'field' => 'client_name', 'fontSize' => 10, 'fontWeight' => '700', 'marginBottom' => 1 ) ),
			self::component( 'field', array( 'field' => 'client_address', 'fontSize' => 8, 'color' => '#64748b', 'marginBottom' => 5 ) ),
			self::component( 'line_items', array( 'showTax' => true, 'marginBottom' => 3 ) ),
			self::component( 'totals', array( 'showTerbilang' => true, 'align' => 'right' ) ),
		);
		$layout['sections']['footer']['components'] = array(
			self::component( 'bank', array( 'marginTop' => 4, 'marginBottom' => 4 ) ),
			self::component( 'signature', array( 'marginTop' => 2 ) ),
		);
		return $layout;
	}

	/**
	 * Build one component node with a stable id.
	 *
	 * @param string               $type    Component type.
	 * @param array<string, mixed> $props   Component props.
	 *
	 * @return array<string, mixed>
	 */
	public static function component( string $type, array $props = array() ): array {
		return array(
			'id'    => self::new_id(),
			'type'  => $type,
			'props' => $props,
		);
	}

	/**
	 * Generate a short unique id for drag-and-drop keys.
	 *
	 * @return string
	 */
	public static function new_id(): string {
		return 'c_' . wp_generate_password( 8, false, false );
	}

	/**
	 * Read layout from a template post (meta first, then empty shell).
	 *
	 * @param int $post_id Template post id.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_for_post( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::sanitize( $decoded );
			}
		}
		if ( is_array( $raw ) ) {
			return self::sanitize( $raw );
		}

		return self::empty();
	}

	/**
	 * Persist layout JSON on a template post.
	 *
	 * @param int                  $post_id Post id.
	 * @param array<string, mixed> $layout  Layout document.
	 *
	 * @return void
	 */
	public static function save_for_post( int $post_id, array $layout ): void {
		$clean = self::sanitize( $layout );
		// JSON_UNESCAPED_UNICODE keeps readable copy; do not use unescaped slashes
		// in a way that can break if font stacks ever reintroduce quotes.
		$json = wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			return;
		}
		// Validate round-trip so we never persist unreadable layout JSON.
		$check = json_decode( $json, true );
		if ( ! is_array( $check ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_KEY, $json );
	}

	/**
	 * Sanitize an arbitrary layout payload.
	 *
	 * @param array<string, mixed> $layout Raw layout.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $layout ): array {
		$base = self::empty();

		if ( isset( $layout['page'] ) && is_array( $layout['page'] ) ) {
			$page   = $layout['page'];
			$theme  = sanitize_key( (string) ( $page['theme'] ?? 'classic' ) );
			$tokens = self::theme_tokens( $theme );
			// Allow overrides from saved layout, but always keep a known theme slug.
			$allowed_table = array( 'filled', 'underline', 'hairline', 'double', 'dense' );
			$allowed_header = array( 'rule', 'open', 'band', 'centered' );
			$table_style    = (string) ( $page['tableStyle'] ?? $tokens['tableStyle'] );
			$header_style   = (string) ( $page['headerStyle'] ?? $tokens['headerStyle'] );
			if ( ! in_array( $table_style, $allowed_table, true ) ) {
				$table_style = (string) $tokens['tableStyle'];
			}
			if ( ! in_array( $header_style, $allowed_header, true ) ) {
				$header_style = (string) $tokens['headerStyle'];
			}

			$base['page'] = array(
				'size'        => 'A4',
				'marginMm'    => max( 8, min( 30, (int) ( $page['marginMm'] ?? 16 ) ) ),
				'theme'       => $tokens['theme'],
				'accent'      => self::color( (string) ( $page['accent'] ?? $tokens['accent'] ) ),
				'ink'         => self::color( (string) ( $page['ink'] ?? $tokens['ink'] ) ),
				'muted'       => self::color( (string) ( $page['muted'] ?? $tokens['muted'] ) ),
				'soft'        => self::color( (string) ( $page['soft'] ?? $tokens['soft'] ) ),
				'totalBg'     => self::color( (string) ( $page['totalBg'] ?? $tokens['totalBg'] ) ),
				'line'        => self::color( (string) ( $page['line'] ?? $tokens['line'] ) ),
				// Strip quotes so meta JSON never becomes invalid when re-saved.
				'fontFamily'  => str_replace(
					array( '"', "'" ),
					'',
					sanitize_text_field( (string) ( $page['fontFamily'] ?? $tokens['fontFamily'] ) )
				),
				'tableStyle'  => $table_style,
				'headerStyle' => $header_style,
				'band'        => ! empty( $page['band'] ) || ! empty( $tokens['band'] ),
			);
		}

		foreach ( array( 'header', 'body', 'footer' ) as $zone ) {
			$section                                 = $layout['sections'][ $zone ] ?? null;
			$comps                                   = ( is_array( $section ) && isset( $section['components'] ) && is_array( $section['components'] ) )
				? $section['components']
				: array();
			$base['sections'][ $zone ]['components'] = self::sanitize_components( $comps );
		}

		return $base;
	}

	/**
	 * Sanitize a list of components (recursive for columns).
	 *
	 * @param array<int, mixed> $components Raw list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_components( array $components ): array {
		$out   = array();
		$types = self::component_types();

		foreach ( $components as $comp ) {
			if ( ! is_array( $comp ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $comp['type'] ?? '' ) );
			if ( ! in_array( $type, $types, true ) ) {
				continue;
			}
			$id    = sanitize_key( (string) ( $comp['id'] ?? '' ) );
			$props = isset( $comp['props'] ) && is_array( $comp['props'] ) ? $comp['props'] : array();
			$props = self::sanitize_props( $type, $props );

			$out[] = array(
				'id'    => '' !== $id ? $id : self::new_id(),
				'type'  => $type,
				'props' => $props,
			);
		}

		return $out;
	}

	/**
	 * Sanitize props for one component type.
	 *
	 * @param string               $type  Type.
	 * @param array<string, mixed> $props Props.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_props( string $type, array $props ): array {
		$align      = self::align( $props['align'] ?? 'left' );
		$size       = max( 9, min( 36, (int) ( $props['fontSize'] ?? 12 ) ) );
		$raw_weight = (string) ( $props['fontWeight'] ?? '400' );
		$weight     = in_array( $raw_weight, array( '400', '600', '700' ), true ) ? $raw_weight : '400';
		$color      = self::color( (string) ( $props['color'] ?? '#1d2327' ) );

		$common = array(
			'align'        => $align,
			'fontSize'     => $size,
			'fontWeight'   => $weight,
			'color'        => $color,
			'marginTop'    => max( 0, min( 48, (int) ( $props['marginTop'] ?? 0 ) ) ),
			'marginBottom' => max( 0, min( 48, (int) ( $props['marginBottom'] ?? 4 ) ) ),
		);

		switch ( $type ) {
			case 'heading':
				return array_merge(
					$common,
					array(
						'content' => sanitize_text_field( (string) ( $props['content'] ?? '' ) ),
						'level'   => max( 1, min( 4, (int) ( $props['level'] ?? 3 ) ) ),
					)
				);
			case 'text':
				return array_merge(
					$common,
					array(
						'content' => sanitize_textarea_field( (string) ( $props['content'] ?? '' ) ),
					)
				);
			case 'field':
				$field = sanitize_key( (string) ( $props['field'] ?? 'invoice_number' ) );
				if ( ! array_key_exists( $field, Merge_Fields::catalogue() ) ) {
					$field = 'invoice_number';
				}
				return array_merge(
					$common,
					array(
						'field'     => $field,
						'showLabel' => ! empty( $props['showLabel'] ),
					)
				);
			case 'logo':
				return array(
					'align'        => $align,
					'maxHeight'    => max( 24, min( 120, (int) ( $props['maxHeight'] ?? 56 ) ) ),
					'marginTop'    => $common['marginTop'],
					'marginBottom' => $common['marginBottom'],
				);
			case 'line_items':
				return array(
					'showTax'      => ! isset( $props['showTax'] ) || ! empty( $props['showTax'] ),
					'marginTop'    => $common['marginTop'],
					'marginBottom' => $common['marginBottom'],
				);
			case 'totals':
				return array(
					'showTerbilang' => ! isset( $props['showTerbilang'] ) || ! empty( $props['showTerbilang'] ),
					'align'         => $align,
					'marginTop'     => $common['marginTop'],
					'marginBottom'  => $common['marginBottom'],
				);
			case 'bank':
				return array(
					'showTitle'    => ! empty( $props['showTitle'] ),
					'marginTop'    => $common['marginTop'],
					'marginBottom' => $common['marginBottom'],
				);
			case 'signature':
				return array(
					'marginTop'    => max( 0, min( 64, (int) ( $props['marginTop'] ?? 24 ) ) ),
					'marginBottom' => $common['marginBottom'],
				);
			case 'spacer':
				return array(
					'height' => max( 4, min( 120, (int) ( $props['height'] ?? 16 ) ) ),
				);
			case 'divider':
				return array(
					'color'        => self::color( (string) ( $props['color'] ?? '#c3c4c7' ) ),
					'marginTop'    => $common['marginTop'],
					'marginBottom' => $common['marginBottom'],
				);
			case 'columns':
				$cols_in = isset( $props['columns'] ) && is_array( $props['columns'] ) ? $props['columns'] : array( array(), array() );
				$cols    = array();
				foreach ( array_slice( $cols_in, 0, 3 ) as $col ) {
					$cols[] = self::sanitize_components( is_array( $col ) ? $col : array() );
				}
				if ( count( $cols ) < 2 ) {
					$cols[] = array();
				}
				return array(
					'gap'          => max( 8, min( 48, (int) ( $props['gap'] ?? 24 ) ) ),
					'columns'      => $cols,
					'marginTop'    => $common['marginTop'],
					'marginBottom' => $common['marginBottom'],
				);
			default:
				return $common;
		}
	}

	/**
	 * Sanitize text alignment.
	 *
	 * @param mixed $value Raw.
	 *
	 * @return string
	 */
	private static function align( $value ): string {
		$value = (string) $value;
		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'left';
	}

	/**
	 * Sanitize a hex colour.
	 *
	 * @param string $value Raw colour.
	 *
	 * @return string
	 */
	private static function color( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return $value;
		}
		return '#1d2327';
	}
}
