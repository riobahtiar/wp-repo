<?php
/**
 * Seeds the default invoice document template (Gutenberg markup).
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
	 * Ensure a default invoice template exists.
	 *
	 * @return void
	 */
	public function maybe_seed(): void {
		$existing = (int) get_option( self::SEEDED_OPTION, 0 );
		if ( $existing > 0 && get_post( $existing ) instanceof \WP_Post ) {
			return;
		}

		$found = Template_Post_Type::get_default( 'invoice' );
		if ( $found instanceof \WP_Post ) {
			update_option( self::SEEDED_OPTION, $found->ID, false );

			return;
		}

		$this->seed_invoice_template();
	}

	/**
	 * Insert the sample invoice template.
	 *
	 * @return int New post id, or 0.
	 */
	public function seed_invoice_template(): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => Template_Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Default invoice',
				'post_content' => $this->invoice_block_markup(),
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
		update_option( self::SEEDED_OPTION, $id, false );

		return $id;
	}

	/**
	 * Gutenberg markup: Header / Body / Footer with dynamic fields.
	 *
	 * Static headings stay English (source language). Dynamic block labels
	 * translate at print time via the site locale.
	 *
	 * @return string Serialized blocks.
	 */
	private function invoice_block_markup(): string {
		$field = static function ( string $key, bool $show_label = false ): array {
			return array(
				'blockName'    => 'wp-bizwit/field',
				'attrs'        => array(
					'field'     => $key,
					'showLabel' => $show_label,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		};

		$heading = static function ( string $text, int $level = 3 ): array {
			$tag = 'h' . $level;

			return array(
				'blockName'    => 'core/heading',
				'attrs'        => array(
					'level'   => $level,
					'content' => $text,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '<' . $tag . ' class="wp-block-heading">' . $text . '</' . $tag . '>',
				'innerContent' => array( '<' . $tag . ' class="wp-block-heading">' . $text . '</' . $tag . '>' ),
			);
		};

		$dynamic = static function ( string $name ): array {
			return array(
				'blockName'    => $name,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		};

		$section = static function ( string $area, array $inner ) {
			$n = count( $inner );

			return array(
				'blockName'    => 'wp-bizwit/section',
				'attrs'        => array( 'area' => $area ),
				'innerBlocks'  => $inner,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, $n, null ),
			);
		};

		$tree = array(
			$section(
				'header',
				array(
					$field( 'business_name' ),
					$field( 'business_address' ),
					$field( 'business_phone' ),
					$field( 'business_email' ),
					$field( 'business_tax_id', true ),
					$field( 'document_title' ),
					$field( 'invoice_number', true ),
					$field( 'issue_date', true ),
					$field( 'due_date', true ),
				)
			),
			$section(
				'body',
				array(
					$heading( 'Bill to', 3 ),
					$field( 'client_name' ),
					$field( 'client_address' ),
					$field( 'client_tax_id', true ),
					$field( 'project_name', true ),
					$dynamic( 'wp-bizwit/line-items' ),
					$dynamic( 'wp-bizwit/totals' ),
				)
			),
			$section(
				'footer',
				array(
					$heading( 'Payment details', 3 ),
					$field( 'bank_block' ),
					$field( 'notes', true ),
					$field( 'terms', true ),
					$dynamic( 'wp-bizwit/signature' ),
				)
			),
		);

		return serialize_blocks( $tree );
	}
}
