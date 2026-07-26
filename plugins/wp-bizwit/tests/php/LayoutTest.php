<?php
/**
 * Tests for document layout sanitisation and rendering.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Documents\Document_Context;
use WP_BizWit\Documents\Layout;
use WP_BizWit\Documents\Layout_Renderer;
use WP_BizWit\Support\Settings;

/**
 * Layout builder data integrity.
 */
class LayoutTest extends WP_UnitTestCase {

	/**
	 * Clean settings between tests.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
	}

	/**
	 * Default invoice layout has all three zones populated.
	 */
	public function test_default_invoice_has_sections(): void {
		$layout = Layout::default_invoice();
		$this->assertNotEmpty( $layout['sections']['header']['components'] );
		$this->assertNotEmpty( $layout['sections']['body']['components'] );
		$this->assertNotEmpty( $layout['sections']['footer']['components'] );
	}

	/**
	 * Unknown component types are stripped.
	 */
	public function test_sanitize_drops_unknown_types(): void {
		$raw                                   = Layout::empty();
		$raw['sections']['body']['components'] = array(
			array(
				'id'    => 'x',
				'type'  => 'malware',
				'props' => array(),
			),
			array(
				'id'    => 'y',
				'type'  => 'field',
				'props' => array( 'field' => 'invoice_number' ),
			),
		);

		$clean = Layout::sanitize( $raw );
		$this->assertCount( 1, $clean['sections']['body']['components'] );
		$this->assertSame( 'field', $clean['sections']['body']['components'][0]['type'] );
	}

	/**
	 * Invalid field keys fall back to invoice_number.
	 */
	public function test_sanitize_field_key(): void {
		$raw                                   = Layout::empty();
		$raw['sections']['body']['components'] = array(
			array(
				'id'    => 'f1',
				'type'  => 'field',
				'props' => array( 'field' => 'not_a_real_field' ),
			),
		);
		$clean                                 = Layout::sanitize( $raw );
		$this->assertSame( 'invoice_number', $clean['sections']['body']['components'][0]['props']['field'] );
	}

	/**
	 * Layout round-trips through post meta.
	 */
	public function test_save_and_load_for_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$layout  = Layout::default_invoice();
		Layout::save_for_post( $post_id, $layout );
		$loaded = Layout::get_for_post( $post_id );
		$this->assertSame( Layout::VERSION, $loaded['version'] );
		$this->assertNotEmpty( $loaded['sections']['body']['components'] );
	}

	/**
	 * Renderer outputs line items when context is set.
	 */
	public function test_renderer_line_items_with_context(): void {
		Document_Context::set(
			'invoice',
			array(
				'invoice' => array(
					'currency'          => 'IDR',
					'total_minor'       => 1000,
					'tax_minor'         => 0,
					'paid_minor'        => 0,
					'subtotal_minor'    => 1000,
					'discount_minor'    => 0,
					'withholding_minor' => 0,
				),
				'items'   => array(
					array(
						'description'      => 'Service',
						'quantity'         => '1.0000',
						'unit'             => '',
						'unit_price_minor' => 1000,
						'tax_rate'         => '0.0000',
						'line_total_minor' => 1000,
					),
				),
				'client'  => array( 'display_name' => 'Acme' ),
				'project' => null,
			)
		);

		$layout                                   = Layout::empty();
		$layout['sections']['body']['components'] = array(
			Layout::component( 'line_items', array( 'showTax' => false ) ),
			Layout::component( 'field', array( 'field' => 'client_name' ) ),
		);

		$html = ( new Layout_Renderer() )->render( $layout );
		Document_Context::clear();

		$this->assertStringContainsString( 'Service', $html );
		$this->assertStringContainsString( 'Acme', $html );
		$this->assertStringContainsString( 'wp-bizwit-lines', $html );
	}
}
