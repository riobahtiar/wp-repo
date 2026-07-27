<?php
/**
 * Markup helper for progressive searchable selects (Tom Select).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin;

/**
 * Renders a native &lt;select&gt; that JS enhances via data-bw-select.
 *
 * Option shape:
 * - Flat: [ 'value' => 'x', 'label' => 'Primary', 'meta' => 'chip', 'search' => '…' ]
 * - Groups: [ 'group' => 'Label', 'options' => [ … flat options … ] ]
 *
 * Variants:
 * - default — plain text options
 * - meta    — primary label + optional secondary chip (code/tag)
 * - bank    — same as meta; also sets data-bank-select for payment-destination hooks
 *
 * @see resources/admin/searchable-select.ts
 */
class Searchable_Select {

	/**
	 * Render a complete &lt;select&gt; element.
	 *
	 * Supported $args keys: name, id (required); selected, selected_values,
	 * variant (default|meta|bank), size (sm|md|lg), placeholder, empty_label,
	 * class, max_options, max_items, search_fields, plugins, dropdown_parent,
	 * dropdown_class, control_class, no_results, allow_empty, open_on_focus,
	 * close_after_select, hide_selected, create, disabled, required, sort_field,
	 * attrs, options (flat or grouped).
	 *
	 * Multi-select: pass max_items > 1 or null. Pass the base name without
	 * brackets (e.g. client_ids); the helper appends [] so PHP receives an array.
	 *
	 * @param array<string, mixed> $args Select configuration.
	 *
	 * @return string HTML.
	 */
	public static function render( array $args ): string {
		$name = (string) ( $args['name'] ?? '' );
		$id   = (string) ( $args['id'] ?? '' );
		if ( '' === $name || '' === $id ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Both name and id are required for Searchable_Select::render().', 'wp-bizwit' ),
				'1.1.3'
			);
			return '';
		}

		$selected       = (string) ( $args['selected'] ?? '' );
		$selected_multi = array();
		if ( isset( $args['selected_values'] ) && is_array( $args['selected_values'] ) ) {
			foreach ( $args['selected_values'] as $v ) {
				$selected_multi[] = (string) $v;
			}
		} elseif ( '' !== $selected ) {
			$selected_multi[] = $selected;
		}

		$variant     = (string) ( $args['variant'] ?? 'meta' );
		$size        = (string) ( $args['size'] ?? 'md' );
		$placeholder = (string) ( $args['placeholder'] ?? __( 'Search…', 'wp-bizwit' ) );
		$empty_label = (string) ( $args['empty_label'] ?? __( 'Select…', 'wp-bizwit' ) );
		$class       = trim( 'bw-ss ' . (string) ( $args['class'] ?? '' ) );
		$max_options = max( 1, (int) ( $args['max_options'] ?? 100 ) );
		$search      = (string) ( $args['search_fields'] ?? 'text,code,name,label,meta' );
		$plugins     = (string) ( $args['plugins'] ?? 'dropdown_input' );
		$parent      = (string) ( $args['dropdown_parent'] ?? 'body' );
		$no_results  = (string) ( $args['no_results'] ?? __( 'No matches found', 'wp-bizwit' ) );
		$allow_empty = ! array_key_exists( 'allow_empty', $args ) || ! empty( $args['allow_empty'] );
		$options     = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		$extra       = isset( $args['attrs'] ) && is_array( $args['attrs'] ) ? $args['attrs'] : array();

		if ( ! in_array( $variant, array( 'default', 'meta', 'bank' ), true ) ) {
			$variant = 'meta';
		}
		if ( ! in_array( $size, array( 'sm', 'md', 'lg' ), true ) ) {
			$size = 'md';
		}

		$max_items_attr = '1';
		if ( array_key_exists( 'max_items', $args ) ) {
			if ( null === $args['max_items'] ) {
				$max_items_attr = 'null';
			} else {
				$max_items_attr = (string) max( 1, (int) $args['max_items'] );
			}
		}

		$attrs = array_merge(
			array(
				'name'                   => $name,
				'id'                     => $id,
				'class'                  => $class,
				'data-bw-select'         => '1',
				'data-bw-select-variant' => $variant,
				'data-size'              => $size,
				'data-placeholder'       => $placeholder,
				'data-max-options'       => (string) $max_options,
				'data-max-items'         => $max_items_attr,
				'data-search-fields'     => $search,
				'data-plugins'           => $plugins,
				'data-dropdown-parent'   => $parent,
				'data-no-results'        => $no_results,
				'data-allow-empty'       => $allow_empty ? '1' : '0',
			),
			$extra
		);

		if ( ! empty( $args['dropdown_class'] ) ) {
			$attrs['data-dropdown-class'] = (string) $args['dropdown_class'];
		}
		if ( ! empty( $args['control_class'] ) ) {
			$attrs['data-control-class'] = (string) $args['control_class'];
		}
		if ( ! empty( $args['sort_field'] ) ) {
			$attrs['data-sort-field'] = (string) $args['sort_field'];
		}
		if ( array_key_exists( 'open_on_focus', $args ) ) {
			$attrs['data-open-on-focus'] = ! empty( $args['open_on_focus'] ) ? '1' : '0';
		}
		if ( array_key_exists( 'close_after_select', $args ) ) {
			$attrs['data-close-after-select'] = ! empty( $args['close_after_select'] ) ? '1' : '0';
		}
		if ( array_key_exists( 'hide_selected', $args ) ) {
			$attrs['data-hide-selected'] = ! empty( $args['hide_selected'] ) ? '1' : '0';
		}
		if ( ! empty( $args['create'] ) ) {
			$attrs['data-create'] = '1';
		}
		if ( ! empty( $args['disabled'] ) ) {
			$attrs['disabled'] = 'disabled';
		}
		if ( ! empty( $args['required'] ) ) {
			$attrs['required'] = 'required';
		}
		// Multi-select must use name[] so PHP receives every selected value.
		if ( 'null' === $max_items_attr || (int) $max_items_attr > 1 ) {
			$attrs['multiple'] = 'multiple';
			$current_name      = (string) ( $attrs['name'] ?? $name );
			if ( ! str_ends_with( $current_name, '[]' ) ) {
				$attrs['name'] = $current_name . '[]';
			}
		}

		// Bank legacy hook used by payment destinations JS (custom name field).
		if ( 'bank' === $variant ) {
			$attrs['data-bank-select'] = '1';
		}

		$html = '<select';
		foreach ( $attrs as $attr => $val ) {
			if ( null === $val || false === $val ) {
				continue;
			}
			if ( 'disabled' === $attr || 'required' === $attr || 'multiple' === $attr ) {
				$html .= ' ' . esc_attr( (string) $attr );
				continue;
			}
			$html .= sprintf( ' %s="%s"', esc_attr( (string) $attr ), esc_attr( (string) $val ) );
		}
		$html .= '>';

		if ( $allow_empty && '1' === $max_items_attr ) {
			$html .= sprintf(
				'<option value=""%s>%s</option>',
				in_array( '', $selected_multi, true ) || array() === $selected_multi ? ' selected="selected"' : '',
				esc_html( $empty_label )
			);
		}

		$html .= self::render_options( $options, $selected_multi );
		$html .= '</select>';

		return $html;
	}

	/**
	 * Render option / optgroup list.
	 *
	 * @param array<int, mixed>  $options  Option definitions.
	 * @param array<int, string> $selected Selected values.
	 *
	 * @return string HTML fragments.
	 */
	private static function render_options( array $options, array $selected ): string {
		$html = '';
		foreach ( $options as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			if ( isset( $entry['group'] ) && isset( $entry['options'] ) && is_array( $entry['options'] ) ) {
				$html .= '<optgroup label="' . esc_attr( (string) $entry['group'] ) . '">';
				$html .= self::render_options( $entry['options'], $selected );
				$html .= '</optgroup>';
				continue;
			}

			$value  = (string) ( $entry['value'] ?? '' );
			$label  = (string) ( $entry['label'] ?? $entry['name'] ?? $value );
			$meta   = (string) ( $entry['meta'] ?? $entry['code'] ?? '' );
			$search = (string) ( $entry['search'] ?? trim( $label . ' ' . $meta ) );
			$is_sel = in_array( $value, $selected, true );

			$extra_attrs = '';
			if ( isset( $entry['attrs'] ) && is_array( $entry['attrs'] ) ) {
				foreach ( $entry['attrs'] as $ak => $av ) {
					$extra_attrs .= sprintf(
						' %s="%s"',
						esc_attr( (string) $ak ),
						esc_attr( (string) $av )
					);
				}
			}

			$html .= sprintf(
				'<option value="%1$s" data-label="%2$s" data-name="%2$s" data-meta="%3$s" data-code="%3$s"%4$s%5$s>%6$s</option>',
				esc_attr( $value ),
				esc_attr( $label ),
				esc_attr( $meta ),
				$is_sel ? ' selected="selected"' : '',
				$extra_attrs,
				esc_html( $search )
			);
		}
		return $html;
	}
}
