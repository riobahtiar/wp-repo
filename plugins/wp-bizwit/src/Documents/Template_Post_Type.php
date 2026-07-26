<?php
/**
 * Document template custom post type (Gutenberg).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

use WP_BizWit\Admin\Screens\Dashboard_Screen;
use WP_BizWit\Support\Capabilities;
use WP_Post;

/**
 * Registers the Template CPT under the BizWit menu and editor behaviour.
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
		add_action( 'init', array( $this, 'register_block_category' ) );
		add_filter( 'block_categories_all', array( $this, 'filter_block_categories' ), 10, 2 );
		add_filter( 'block_editor_settings_all', array( $this, 'editor_settings' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Register the CPT.
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
				// Menu entry is added explicitly in Admin\Menu so it always
				// appears under BizWit when the user can manage settings.
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'rest_base'           => 'bizwit-templates',
				// IMPORTANT: do not reuse an existing primitive (e.g.
				// bizwit_manage_invoices) as a meta capability name while
				// map_meta_cap is true — WordPress then treats that string as
				// edit_post and maps has_cap() to do_not_allow without a post ID.
				// Use a dedicated capability type so meta caps are unique.
				'capability_type'     => array( 'bizwit_template', 'bizwit_templates' ),
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'delete_with_user'    => false,
				'template'            => self::default_block_template(),
				'template_lock'       => false,
			)
		);
	}

	/**
	 * Default Gutenberg structure: Header, Body, Footer sections.
	 *
	 * @return array<int, array<int, mixed>>
	 */
	public static function default_block_template(): array {
		return array(
			array(
				'wp-bizwit/section',
				array( 'area' => 'header' ),
				array(
					array(
						'core/heading',
						array(
							'level'   => 2,
							'content' => __( 'Header', 'wp-bizwit' ),
						),
					),
				),
			),
			array(
				'wp-bizwit/section',
				array( 'area' => 'body' ),
				array(
					array(
						'core/heading',
						array(
							'level'   => 2,
							'content' => __( 'Body', 'wp-bizwit' ),
						),
					),
				),
			),
			array(
				'wp-bizwit/section',
				array( 'area' => 'footer' ),
				array(
					array(
						'core/heading',
						array(
							'level'   => 2,
							'content' => __( 'Footer', 'wp-bizwit' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Register post meta for REST / sidebar.
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
	}

	/**
	 * Register block category (legacy filter still used by some WP versions).
	 *
	 * @return void
	 */
	public function register_block_category(): void {
		// Category is added via filter_block_categories.
	}

	/**
	 * Add BizWit category for document blocks.
	 *
	 * @param array<int, array<string, mixed>> $categories Categories.
	 * @param mixed                            $context    Editor context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function filter_block_categories( array $categories, $context = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$categories[] = array(
			'slug'  => 'wp-bizwit',
			'title' => __( 'BizWit documents', 'wp-bizwit' ),
			'icon'  => 'portfolio',
		);

		return $categories;
	}

	/**
	 * Encourage the three-section structure for templates.
	 *
	 * @param array<string, mixed> $settings Editor settings.
	 * @param mixed                $context  Editor context.
	 *
	 * @return array<string, mixed>
	 */
	public function editor_settings( array $settings, $context ): array {
		$post = null;
		if ( is_object( $context ) && isset( $context->post ) && $context->post instanceof WP_Post ) {
			$post = $context->post;
		}

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return $settings;
		}

		// Soft guidance: new posts get the default template from CPT args.
		$settings['bizwitDocumentTemplate'] = true;

		return $settings;
	}

	/**
	 * Side meta box for document type and default flag.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'wp-bizwit-template-settings',
			__( 'Template settings', 'wp-bizwit' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Meta box markup.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {
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
			<?php esc_html_e( 'Design the Header, Body and Footer with Gutenberg. Field labels follow the site language when the document is printed.', 'wp-bizwit' ); ?>
		</p>
		<?php
	}

	/**
	 * Persist meta box fields.
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

		// Fallback: any published template of that type.
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
