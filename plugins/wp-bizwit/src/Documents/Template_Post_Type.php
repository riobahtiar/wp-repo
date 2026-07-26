<?php
/**
 * Document template custom post type + layout builder admin.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_BizWit\Support\Capabilities;
use WP_Post;

/**
 * Registers the Template CPT and the Vue layout-builder edit UI.
 */
class Template_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'bizwit_template';

	/**
	 * Meta key: document type (invoice, receipt, …).
	 *
	 * @var string
	 */
	public const META_DOC_TYPE = '_wp_bizwit_doc_type';

	/**
	 * Meta key: whether this is the default for its document type.
	 *
	 * @var string
	 */
	public const META_IS_DEFAULT = '_wp_bizwit_is_default';

	/**
	 * Wire hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_builder' ) );
	}

	/**
	 * Register the CPT (classic title + layout builder meta box).
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Templates', 'wp-bizwit' ),
			'singular_name'      => __( 'Template', 'wp-bizwit' ),
			'add_new'            => __( 'Add template', 'wp-bizwit' ),
			'add_new_item'       => __( 'Add template', 'wp-bizwit' ),
			'edit_item'          => __( 'Edit template', 'wp-bizwit' ),
			'new_item'           => __( 'New template', 'wp-bizwit' ),
			'view_item'          => __( 'View template', 'wp-bizwit' ),
			'search_items'       => __( 'Search templates', 'wp-bizwit' ),
			'not_found'          => __( 'No templates found.', 'wp-bizwit' ),
			'not_found_in_trash' => __( 'No templates found in Trash.', 'wp-bizwit' ),
			'menu_name'          => __( 'Template', 'wp-bizwit' ),
			'all_items'          => __( 'Template', 'wp-bizwit' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'rest_base'           => 'bizwit-templates',
				'capability_type'     => array( 'bizwit_template', 'bizwit_templates' ),
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				// Title only — layout is edited in the Vue builder meta box.
				'supports'            => array( 'title', 'custom-fields' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Never use the block editor for templates.
	 *
	 * @param bool   $use       Whether to use block editor.
	 * @param string $post_type Post type.
	 *
	 * @return bool
	 */
	public function disable_block_editor( bool $use, string $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : $use;
	}

	/**
	 * Register post meta for REST / settings.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			self::POST_TYPE,
			self::META_DOC_TYPE,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => 'invoice',
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( Capabilities::MANAGE_TEMPLATES );
				},
				'sanitize_callback' => static function ( $value ): string {
					$value = sanitize_key( (string) $value );
					return in_array( $value, array( 'invoice', 'receipt', 'general' ), true ) ? $value : 'invoice';
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_IS_DEFAULT,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( Capabilities::MANAGE_TEMPLATES );
				},
				'sanitize_callback' => static function ( $value ): bool {
					return (bool) $value;
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			Layout::META_KEY,
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => false,
				'auth_callback' => static function (): bool {
					return current_user_can( Capabilities::MANAGE_TEMPLATES );
				},
			)
		);
	}

	/**
	 * Enqueue the Vue layout builder on template edit screens.
	 *
	 * @param string $hook Admin hook.
	 *
	 * @return void
	 */
	public function enqueue_builder( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_TEMPLATES ) ) {
			return;
		}

		$assets = new \WP_BizWit\Admin\Assets();
		$assets->enqueue_entry( 'template-builder' );
	}

	/**
	 * Meta boxes: settings + layout builder mount.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'wp-bizwit-template-settings',
			__( 'Template settings', 'wp-bizwit' ),
			array( $this, 'render_settings_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wp-bizwit-layout-builder',
			__( 'Document layout', 'wp-bizwit' ),
			array( $this, 'render_builder_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Settings side box.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_settings_box( WP_Post $post ): void {
		wp_nonce_field( 'wp_bizwit_template_meta', 'wp_bizwit_template_meta_nonce' );

		$doc_type   = (string) get_post_meta( $post->ID, self::META_DOC_TYPE, true );
		$is_default = (bool) get_post_meta( $post->ID, self::META_IS_DEFAULT, true );
		if ( '' === $doc_type ) {
			$doc_type = 'invoice';
		}

		$types = array(
			'invoice' => __( 'Invoice', 'wp-bizwit' ),
			'receipt' => __( 'Receipt / kwitansi', 'wp-bizwit' ),
			'general' => __( 'General', 'wp-bizwit' ),
		);
		?>
		<p>
			<label for="wp-bizwit-doc-type"><strong><?php esc_html_e( 'Document type', 'wp-bizwit' ); ?></strong></label><br />
			<select name="wp_bizwit_doc_type" id="wp-bizwit-doc-type" class="widefat">
				<?php foreach ( $types as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $doc_type, $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label>
				<input type="checkbox" name="wp_bizwit_is_default" value="1" <?php checked( $is_default ); ?> />
				<?php esc_html_e( 'Use as default for this document type', 'wp-bizwit' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Build the layout on the canvas. Field labels follow the site language when the document is printed.', 'wp-bizwit' ); ?>
		</p>
		<?php
	}

	/**
	 * Layout builder mount point + hidden JSON field.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_builder_box( WP_Post $post ): void {
		$layout = Layout::get_for_post( (int) $post->ID );
		// New posts get the sample invoice layout so the canvas is never empty.
		$has = false;
		foreach ( array( 'header', 'body', 'footer' ) as $zone ) {
			if ( ! empty( $layout['sections'][ $zone ]['components'] ) ) {
				$has = true;
				break;
			}
		}
		if ( ! $has ) {
			$layout = Layout::default_invoice();
		}

		// Sample merge values + chrome strings for the active locale (admin user language).
		Document_Context::set( 'invoice', Document_Renderer::sample_context() );
		$sample_values = array();
		foreach ( array_keys( Merge_Fields::catalogue() ) as $field_key ) {
			$sample_values[ $field_key ] = wp_strip_all_tags(
				str_replace(
					array( '<br />', '<br/>', '<br>' ),
					"\n",
					Merge_Fields::resolve( $field_key )
				)
			);
		}
		$sample_values['_item1']  = __( 'Website redesign — discovery & UI', 'wp-bizwit' );
		$sample_values['_item2']  = __( 'Implementation & training', 'wp-bizwit' );
		$sample_values['_price1'] = \WP_BizWit\Support\Money::format( 10000000, \WP_BizWit\Support\Settings::currency() );
		$sample_values['_price2'] = \WP_BizWit\Support\Money::format( 5000000, \WP_BizWit\Support\Settings::currency() );
		Document_Context::clear();

		$chrome = array();
		foreach ( Document_I18n::chrome_msgids() as $msgid ) {
			$chrome[ $msgid ] = Document_I18n::chrome( $msgid );
		}

		$payload = array(
			'layout'        => $layout,
			'fields'        => Merge_Fields::catalogue(),
			'sampleValues'  => $sample_values,
			'chrome'        => $chrome,
			'locale'        => determine_locale(),
			'documentCss'   => Document_Styles::css(),
			'componentMeta' => self::component_palette(),
			'zones'         => array(
				'header' => __( 'Header', 'wp-bizwit' ),
				'body'   => __( 'Body', 'wp-bizwit' ),
				'footer' => __( 'Footer', 'wp-bizwit' ),
			),
			'i18n'          => array(
				'studioTitle'       => __( 'Document studio', 'wp-bizwit' ),
				'studioHint'        => __( 'Design the layout · preview as printed', 'wp-bizwit' ),
				'modeDesign'        => __( 'Design', 'wp-bizwit' ),
				'modePreview'       => __( 'Print preview', 'wp-bizwit' ),
				'palette'           => __( 'Components', 'wp-bizwit' ),
				'paletteHint'       => __( 'Click to add into the active section', 'wp-bizwit' ),
				'properties'        => __( 'Properties', 'wp-bizwit' ),
				'selectHint'        => __( 'Select a block on the page to edit it.', 'wp-bizwit' ),
				'remove'            => __( 'Remove', 'wp-bizwit' ),
				'moveUp'            => __( 'Up', 'wp-bizwit' ),
				'moveDown'          => __( 'Down', 'wp-bizwit' ),
				'duplicate'         => __( 'Duplicate', 'wp-bizwit' ),
				'emptyZone'         => __( 'Add components to this section', 'wp-bizwit' ),
				'field'             => __( 'Data field', 'wp-bizwit' ),
				'showLabel'         => __( 'Show label', 'wp-bizwit' ),
				'align'             => __( 'Alignment', 'wp-bizwit' ),
				'fontSize'          => __( 'Font size', 'wp-bizwit' ),
				'fontWeight'        => __( 'Weight', 'wp-bizwit' ),
				'color'             => __( 'Colour', 'wp-bizwit' ),
				'marginTop'         => __( 'Space above', 'wp-bizwit' ),
				'marginBottom'      => __( 'Space below', 'wp-bizwit' ),
				'content'           => __( 'Text', 'wp-bizwit' ),
				'height'            => __( 'Height', 'wp-bizwit' ),
				'showTax'           => __( 'Show tax column', 'wp-bizwit' ),
				'showTerbilang'     => __( 'Show amount in words', 'wp-bizwit' ),
				'gap'               => __( 'Column gap', 'wp-bizwit' ),
				'left'              => __( 'Left', 'wp-bizwit' ),
				'center'            => __( 'Center', 'wp-bizwit' ),
				'right'             => __( 'Right', 'wp-bizwit' ),
				'resetDefault'      => __( 'Reset default', 'wp-bizwit' ),
				'addField'          => __( 'Field', 'wp-bizwit' ),
				'bankPlaceholder'   => __( 'Bank transfer details', 'wp-bizwit' ),
				'colDescription'    => __( 'Description', 'wp-bizwit' ),
				'colQty'            => __( 'Qty', 'wp-bizwit' ),
				'colUnit'           => __( 'Unit', 'wp-bizwit' ),
				'colUnitPrice'      => __( 'Unit price', 'wp-bizwit' ),
				'colAmount'         => __( 'Amount', 'wp-bizwit' ),
				'labelSubtotal'     => __( 'Subtotal', 'wp-bizwit' ),
				'labelTotal'        => __( 'Total', 'wp-bizwit' ),
				'labelReceivedBy'   => __( 'Received by', 'wp-bizwit' ),
				'labelNameSign'     => __( 'Name & signature', 'wp-bizwit' ),
				'labelStamp'        => __( 'Signature and company stamp', 'wp-bizwit' ),
				/* translators: %s: business name */
				'labelFor'          => __( 'For %s', 'wp-bizwit' ),
				/* translators: %s: amount in words */
				'labelInWords'      => __( 'In words: %s', 'wp-bizwit' ),
			),
			'defaultLayout' => Layout::default_invoice(),
		);
		$config_json = (string) wp_json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
		?>
		<input type="hidden" name="wp_bizwit_layout_json" id="wp-bizwit-layout-json" value="<?php echo esc_attr( (string) wp_json_encode( $layout ) ); ?>" />
		<?php // JSON config as a script node so large CSS/strings are not mangled in a data- attribute. ?>
		<script type="application/json" id="wp-bizwit-template-builder-config"><?php echo $config_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG|AMP escapes HTML-sensitive chars. ?></script>
		<div
			id="wp-bizwit-template-builder"
			class="wp-bizwit"
			data-locale="<?php echo esc_attr( (string) $payload['locale'] ); ?>"
		></div>
		<?php
	}

	/**
	 * Component catalogue for the builder palette.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function component_palette(): array {
		return array(
			'heading'    => array(
				'label'       => __( 'Heading', 'wp-bizwit' ),
				'description' => __( 'Static title text', 'wp-bizwit' ),
			),
			'text'       => array(
				'label'       => __( 'Text', 'wp-bizwit' ),
				'description' => __( 'Free paragraph', 'wp-bizwit' ),
			),
			'field'      => array(
				'label'       => __( 'Data field', 'wp-bizwit' ),
				'description' => __( 'Invoice / business value', 'wp-bizwit' ),
			),
			'line_items' => array(
				'label'       => __( 'Line items', 'wp-bizwit' ),
				'description' => __( 'Invoice rows table', 'wp-bizwit' ),
			),
			'totals'     => array(
				'label'       => __( 'Totals', 'wp-bizwit' ),
				'description' => __( 'Subtotal, tax, total', 'wp-bizwit' ),
			),
			'bank'       => array(
				'label'       => __( 'Bank details', 'wp-bizwit' ),
				'description' => __( 'Payment account block', 'wp-bizwit' ),
			),
			'signature'  => array(
				'label'       => __( 'Signature', 'wp-bizwit' ),
				'description' => __( 'Sign and stamp area', 'wp-bizwit' ),
			),
			'spacer'     => array(
				'label'       => __( 'Spacer', 'wp-bizwit' ),
				'description' => __( 'Vertical space', 'wp-bizwit' ),
			),
			'divider'    => array(
				'label'       => __( 'Divider', 'wp-bizwit' ),
				'description' => __( 'Horizontal rule', 'wp-bizwit' ),
			),
			'columns'    => array(
				'label'       => __( 'Columns', 'wp-bizwit' ),
				'description' => __( 'Two side-by-side columns', 'wp-bizwit' ),
			),
		);
	}

	/**
	 * Persist settings + layout JSON.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 *
	 * @return void
	 */
	public function save_meta( int $post_id, WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! isset( $_POST['wp_bizwit_template_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_bizwit_template_meta_nonce'] ) ), 'wp_bizwit_template_meta' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_TEMPLATES ) ) {
			return;
		}

		$doc_type = isset( $_POST['wp_bizwit_doc_type'] )
			? sanitize_key( wp_unslash( $_POST['wp_bizwit_doc_type'] ) )
			: 'invoice';
		if ( ! in_array( $doc_type, array( 'invoice', 'receipt', 'general' ), true ) ) {
			$doc_type = 'invoice';
		}

		$is_default = ! empty( $_POST['wp_bizwit_is_default'] );

		update_post_meta( $post_id, self::META_DOC_TYPE, $doc_type );
		update_post_meta( $post_id, self::META_IS_DEFAULT, $is_default ? 1 : 0 );

		if ( $is_default ) {
			$this->clear_other_defaults( $post_id, $doc_type );
		}

		if ( isset( $_POST['wp_bizwit_layout_json'] ) ) {
			$raw = wp_unslash( $_POST['wp_bizwit_layout_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					Layout::save_for_post( $post_id, $decoded );
				}
			}
		}
	}

	/**
	 * Ensure only one default template per document type.
	 *
	 * @param int    $keep_id  Template to keep as default.
	 * @param string $doc_type Document type.
	 *
	 * @return void
	 */
	private function clear_other_defaults( int $keep_id, string $doc_type ): void {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'post__not_in'   => array( $keep_id ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_DOC_TYPE,
						'value' => $doc_type,
					),
					array(
						'key'   => self::META_IS_DEFAULT,
						'value' => '1',
					),
				),
			)
		);

		foreach ( $query->posts as $id ) {
			update_post_meta( (int) $id, self::META_IS_DEFAULT, 0 );
		}
	}

	/**
	 * List table columns.
	 *
	 * @param array<string, string> $columns Columns.
	 *
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['doc_type']   = __( 'Document type', 'wp-bizwit' );
				$new['is_default'] = __( 'Default', 'wp-bizwit' );
			}
		}

		return $new;
	}

	/**
	 * List table column cells.
	 *
	 * @param string $column  Column id.
	 * @param int    $post_id Post id.
	 *
	 * @return void
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'doc_type' === $column ) {
			$type = (string) get_post_meta( $post_id, self::META_DOC_TYPE, true );
			$map  = array(
				'invoice' => __( 'Invoice', 'wp-bizwit' ),
				'receipt' => __( 'Receipt / kwitansi', 'wp-bizwit' ),
				'general' => __( 'General', 'wp-bizwit' ),
			);
			echo esc_html( $map[ $type ] ?? $type );
		}

		if ( 'is_default' === $column ) {
			echo get_post_meta( $post_id, self::META_IS_DEFAULT, true )
				? esc_html__( 'Yes', 'wp-bizwit' )
				: '—';
		}
	}

	/**
	 * Title field placeholder.
	 *
	 * @param string       $text Placeholder.
	 * @param WP_Post|null $post Post being edited.
	 *
	 * @return string
	 */
	public function title_placeholder( string $text, $post = null ): string {
		if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
			return __( 'Template name', 'wp-bizwit' );
		}

		return $text;
	}

	/**
	 * Find the default published template for a document type.
	 *
	 * @param string $doc_type Document type.
	 *
	 * @return WP_Post|null
	 */
	public static function get_default( string $doc_type = 'invoice' ): ?WP_Post {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_DOC_TYPE,
						'value' => $doc_type,
					),
					array(
						'key'   => self::META_IS_DEFAULT,
						'value' => '1',
					),
				),
			)
		);

		if ( ! empty( $query->posts[0] ) && $query->posts[0] instanceof WP_Post ) {
			return $query->posts[0];
		}

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => self::META_DOC_TYPE,
						'value' => $doc_type,
					),
				),
			)
		);

		return ( ! empty( $query->posts[0] ) && $query->posts[0] instanceof WP_Post )
			? $query->posts[0]
			: null;
	}
}
