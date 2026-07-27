<?php
/**
 * Seeds default + gallery invoice document templates (layout builder JSON).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

/**
 * Creates sample invoice templates once per site (classic default + gallery).
 */
class Default_Templates {

	/**
	 * Option flag so we only seed the primary default once.
	 *
	 * @var string
	 */
	public const SEEDED_OPTION = 'wp_bizwit_default_invoice_template_id';

	/**
	 * Gallery pack version — bump to force-refresh layout JSON on all themed templates.
	 *
	 * @var string
	 */
	public const GALLERY_OPTION = 'wp_bizwit_gallery_templates_v7';

	/**
	 * Ensure default + gallery invoice templates exist and layouts match the current design pack.
	 *
	 * @return void
	 */
	public function maybe_seed(): void {
		$existing = (int) get_option( self::SEEDED_OPTION, 0 );
		if ( $existing > 0 ) {
			$post = get_post( $existing );
			if ( $post instanceof \WP_Post ) {
				// Keep user's custom default unless still empty / has theme slug classic.
				$slug = (string) get_post_meta( $existing, '_wp_bizwit_theme_slug', true );
				if ( '' === $slug || 'classic' === $slug ) {
					update_post_meta( $existing, '_wp_bizwit_theme_slug', 'classic' );
					Layout::save_for_post( $existing, Layout::layout_for_theme( 'classic' ) );
				}
			} else {
				$this->seed_invoice_template( 'classic', true );
			}
		} else {
			$found = Template_Post_Type::get_default( 'invoice' );
			if ( $found instanceof \WP_Post ) {
				update_option( self::SEEDED_OPTION, $found->ID, false );
				update_post_meta( (int) $found->ID, '_wp_bizwit_theme_slug', 'classic' );
				Layout::save_for_post( (int) $found->ID, Layout::layout_for_theme( 'classic' ) );
			} else {
				$this->seed_invoice_template( 'classic', true );
			}
		}

		// Always ensure gallery templates exist.
		foreach ( array( 'modern', 'professional', 'minimal', 'elegant', 'compact' ) as $slug ) {
			$this->seed_invoice_template( $slug, false );
		}

		// Force-refresh themed layouts when gallery pack version changes.
		if ( 'v7' !== (string) get_option( self::GALLERY_OPTION, '' ) ) {
			$this->refresh_all_theme_layouts();
			update_option( self::GALLERY_OPTION, 'v7', false );
		}
	}

	/**
	 * Overwrite layout JSON for every template that has a known theme slug.
	 *
	 * @return void
	 */
	public function refresh_all_theme_layouts(): void {
		$posts = get_posts(
			array(
				'post_type'      => Template_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $id ) {
			$id   = (int) $id;
			$slug = (string) get_post_meta( $id, '_wp_bizwit_theme_slug', true );
			if ( '' === $slug ) {
				// Default invoice without slug → treat as classic when it is the default.
				$is_default = (int) get_post_meta( $id, Template_Post_Type::META_IS_DEFAULT, true );
				if ( $is_default ) {
					$slug = 'classic';
					update_post_meta( $id, '_wp_bizwit_theme_slug', 'classic' );
				} else {
					continue;
				}
			}
			if ( ! array_key_exists( $slug, Layout::gallery_meta() ) && 'classic' !== $slug ) {
				continue;
			}
			Layout::save_for_post( $id, Layout::layout_for_theme( $slug ) );
		}
	}

	/**
	 * Insert an invoice template for a gallery theme.
	 *
	 * @param string $theme_slug Theme slug from Layout::gallery_meta().
	 * @param bool   $is_default Whether this is the site default.
	 *
	 * @return int New post id, or 0.
	 */
	public function seed_invoice_template( string $theme_slug = 'classic', bool $is_default = false ): int {
		$meta  = Layout::gallery_meta();
		$title = isset( $meta[ $theme_slug ] )
			? sprintf(
				/* translators: %s: template style name */
				__( 'Invoice — %s', 'wp-bizwit' ),
				$meta[ $theme_slug ]['title']
			)
			: __( 'Default invoice', 'wp-bizwit' );

		if ( 'classic' === $theme_slug && $is_default ) {
			$title = __( 'Default invoice', 'wp-bizwit' );
		}

		// Find by theme slug first (stable across renames).
		$by_slug = get_posts(
			array(
				'post_type'      => Template_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_wp_bizwit_theme_slug',
				'meta_value'     => $theme_slug,
			)
		);
		if ( isset( $by_slug[0] ) ) {
			$id = (int) $by_slug[0];
			Layout::save_for_post( $id, Layout::layout_for_theme( $theme_slug ) );
			if ( $is_default ) {
				update_post_meta( $id, Template_Post_Type::META_IS_DEFAULT, 1 );
				update_option( self::SEEDED_OPTION, $id, false );
			}
			return $id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Template_Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '',
				'post_author'  => get_current_user_id() > 0 ? get_current_user_id() : 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		$id = (int) $post_id;

		update_post_meta( $id, Template_Post_Type::META_DOC_TYPE, 'invoice' );
		update_post_meta( $id, Template_Post_Type::META_IS_DEFAULT, $is_default ? 1 : 0 );
		update_post_meta( $id, '_wp_bizwit_theme_slug', $theme_slug );
		Layout::save_for_post( $id, Layout::layout_for_theme( $theme_slug ) );

		if ( $is_default ) {
			update_option( self::SEEDED_OPTION, $id, false );
		}

		return $id;
	}
}
