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
	 * Empty layout shell.
	 *
	 * @return array<string, mixed>
	 */
	public static function empty(): array {
		return array(
			'version'  => self::VERSION,
			'page'     => array(
				'size'     => 'A4',
				'marginMm' => 16,
			),
			'sections' => array(
				'header' => array( 'components' => array() ),
				'body'   => array( 'components' => array() ),
				'footer' => array( 'components' => array() ),
			),
		);
	}

	/**
	 * Default invoice layout (sample).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_invoice(): array {
		$layout = self::empty();

		$layout['sections']['header']['components'] = array(
			self::component(
				'columns',
				array(
					'gap'     => 24,
					'columns' => array(
						array(
							self::component(
								'field',
								array(
									'field'      => 'business_name',
									'fontSize'   => 18,
									'fontWeight' => '700',
								)
							),
							self::component(
								'field',
								array(
									'field'    => 'business_address',
									'fontSize' => 11,
									'color'    => '#646970',
								)
							),
							self::component(
								'field',
								array(
									'field'    => 'business_phone',
									'fontSize' => 11,
									'color'    => '#646970',
								)
							),
							self::component(
								'field',
								array(
									'field'    => 'business_email',
									'fontSize' => 11,
									'color'    => '#646970',
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'business_tax_id',
									'showLabel' => true,
									'fontSize'  => 11,
								)
							),
						),
						array(
							self::component(
								'field',
								array(
									'field'      => 'document_title',
									'fontSize'   => 20,
									'fontWeight' => '700',
									'align'      => 'right',
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'invoice_number',
									'showLabel' => true,
									'align'     => 'right',
									'fontSize'  => 12,
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'issue_date',
									'showLabel' => true,
									'align'     => 'right',
									'fontSize'  => 12,
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'due_date',
									'showLabel' => true,
									'align'     => 'right',
									'fontSize'  => 12,
								)
							),
							self::component(
								'field',
								array(
									'field'     => 'status_label',
									'showLabel' => true,
									'align'     => 'right',
									'fontSize'  => 12,
								)
							),
						),
					),
				)
			),
			self::component(
				'divider',
				array(
					'marginTop'    => 12,
					'marginBottom' => 12,
				)
			),
		);

		$layout['sections']['body']['components'] = array(
			self::component(
				'heading',
				array(
					'content'  => 'Bill to',
					'level'    => 3,
					'fontSize' => 12,
					'color'    => '#646970',
				)
			),
			self::component(
				'field',
				array(
					'field'      => 'client_name',
					'fontSize'   => 14,
					'fontWeight' => '700',
				)
			),
			self::component(
				'field',
				array(
					'field'    => 'client_address',
					'fontSize' => 12,
				)
			),
			self::component(
				'field',
				array(
					'field'     => 'client_tax_id',
					'showLabel' => true,
					'fontSize'  => 12,
				)
			),
			self::component(
				'field',
				array(
					'field'     => 'project_name',
					'showLabel' => true,
					'fontSize'  => 12,
				)
			),
			self::component( 'spacer', array( 'height' => 16 ) ),
			self::component( 'line_items', array( 'showTax' => true ) ),
			self::component( 'spacer', array( 'height' => 12 ) ),
			self::component(
				'totals',
				array(
					'showTerbilang' => true,
					'align'         => 'right',
				)
			),
		);

		$layout['sections']['footer']['components'] = array(
			self::component( 'spacer', array( 'height' => 16 ) ),
			self::component(
				'heading',
				array(
					'content'  => 'Payment details',
					'level'    => 3,
					'fontSize' => 12,
					'color'    => '#646970',
				)
			),
			self::component( 'bank', array() ),
			self::component(
				'field',
				array(
					'field'     => 'notes',
					'showLabel' => true,
					'fontSize'  => 11,
				)
			),
			self::component(
				'field',
				array(
					'field'     => 'terms',
					'showLabel' => true,
					'fontSize'  => 11,
				)
			),
			self::component( 'spacer', array( 'height' => 24 ) ),
			self::component( 'signature', array() ),
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
		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $clean ) );
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
			$base['page']['size']     = 'A4';
			$base['page']['marginMm'] = max( 8, min( 30, (int) ( $layout['page']['marginMm'] ?? 16 ) ) );
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
		$align  = self::align( $props['align'] ?? 'left' );
		$size   = max( 9, min( 36, (int) ( $props['fontSize'] ?? 12 ) ) );
		$raw_weight = (string) ( $props['fontWeight'] ?? '400' );
		$weight     = in_array( $raw_weight, array( '400', '600', '700' ), true ) ? $raw_weight : '400';
		$color  = self::color( (string) ( $props['color'] ?? '#1d2327' ) );

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
