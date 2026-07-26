<?php
/**
 * Seeds the default invoice document template (layout builder JSON).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

/**
 * Creates a sample default invoice template once per site.
 */
class Default_Templates {

	/**
	 * Option flag so we only seed once (until deleted).
	 *
	 * @var string
	 */
	public const SEEDED_OPTION = 'wp_bizwit_default_invoice_template_id';

	/**
	 * Ensure a default invoice template exists and has a layout.
	 *
	 * @return void
	 */
	public function maybe_seed(): void {
		$existing = (int) get_option( self::SEEDED_OPTION, 0 );
		if ( $existing > 0 ) {
			$post = get_post( $existing );
			if ( $post instanceof \WP_Post ) {
				$this->ensure_layout( $existing );
				return;
			}
		}

		$found = Template_Post_Type::get_default( 'invoice' );
		if ( $found instanceof \WP_Post ) {
			update_option( self::SEEDED_OPTION, $found->ID, false );
			$this->ensure_layout( (int) $found->ID );
			return;
		}

		$this->seed_invoice_template();
	}

	/**
	 * Insert the sample invoice template with default layout.
	 *
	 * @return int New post id, or 0.
	 */
	public function seed_invoice_template(): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => Template_Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Default invoice',
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
		update_post_meta( $id, Template_Post_Type::META_IS_DEFAULT, 1 );
		Layout::save_for_post( $id, Layout::default_invoice() );
		update_option( self::SEEDED_OPTION, $id, false );

		return $id;
	}

	/**
	 * Write default layout when meta is empty (migrates older templates).
	 *
	 * @param int $post_id Template id.
	 *
	 * @return void
	 */
	private function ensure_layout( int $post_id ): void {
		$layout = Layout::get_for_post( $post_id );
		foreach ( array( 'header', 'body', 'footer' ) as $zone ) {
			if ( ! empty( $layout['sections'][ $zone ]['components'] ) ) {
				return;
			}
		}
		Layout::save_for_post( $post_id, Layout::default_invoice() );
	}
}
