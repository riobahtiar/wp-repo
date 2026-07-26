<?php
/**
 * Tests for the setup checklist.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Documents\Default_Templates;
use WP_BizWit\Documents\Layout;
use WP_BizWit\Documents\Template_Post_Type;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Support\Onboarding;
use WP_BizWit\Support\Settings;

/**
 * Onboarding step evaluation and dismissal.
 */
class OnboardingTest extends WP_UnitTestCase {

	/**
	 * Fresh install state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		delete_option( Default_Templates::SEEDED_OPTION );
		( new Installer() )->install();

		// Remove any leftover templates.
		$q = new WP_Query(
			array(
				'post_type'      => Template_Post_Type::POST_TYPE,
				'posts_per_page' => 50,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);
		foreach ( $q->posts as $id ) {
			wp_delete_post( (int) $id, true );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_user_meta( get_current_user_id(), Onboarding::DISMISS_META );
	}

	/**
	 * Empty site has incomplete business/client/invoice/template steps.
	 */
	public function test_empty_site_has_incomplete_steps(): void {
		Settings::update(
			array(
				'business_name'   => '',
				'bank_name'       => '',
				'bank_account_no' => '',
			)
		);

		$steps = Onboarding::steps(
			array(
				'settings'    => '#s',
				'new_client'  => '#c',
				'new_invoice' => '#i',
				'templates'   => '#t',
			)
		);

		$this->assertTrue( Onboarding::has_incomplete( $steps ) );
		$by_id = array();
		foreach ( $steps as $step ) {
			$by_id[ $step['id'] ] = $step['done'];
		}
		$this->assertFalse( $by_id['business'] );
		$this->assertFalse( $by_id['bank'] );
		$this->assertFalse( $by_id['client'] );
		$this->assertFalse( $by_id['invoice'] );
	}

	/**
	 * Completing identity and bank marks those steps done.
	 */
	public function test_settings_complete_steps(): void {
		Settings::update(
			array(
				'business_name'   => 'PT Contoh',
				'bank_name'       => 'BCA',
				'bank_account_no' => '123',
			)
		);
		$steps = Onboarding::steps(
			array(
				'settings'    => '#s',
				'new_client'  => '#c',
				'new_invoice' => '#i',
				'templates'   => '#t',
			)
		);
		$by_id = array();
		foreach ( $steps as $step ) {
			$by_id[ $step['id'] ] = $step['done'];
		}
		$this->assertTrue( $by_id['business'] );
		$this->assertTrue( $by_id['bank'] );
	}

	/**
	 * Adding a client completes the client step.
	 */
	public function test_client_step(): void {
		Settings::update( array( 'business_name' => 'Biz' ) );
		( new Client_Repository() )->create( array( 'display_name' => 'Client A' ) );

		$steps = Onboarding::steps(
			array(
				'settings'    => '#s',
				'new_client'  => '#c',
				'new_invoice' => '#i',
				'templates'   => '#t',
			)
		);
		foreach ( $steps as $step ) {
			if ( 'client' === $step['id'] ) {
				$this->assertTrue( $step['done'] );
			}
		}
	}

	/**
	 * Dismiss is stored per user.
	 */
	public function test_dismiss(): void {
		$this->assertFalse( Onboarding::is_dismissed() );
		Onboarding::dismiss();
		$this->assertTrue( Onboarding::is_dismissed() );
	}

	/**
	 * Template step completes when a default template exists.
	 */
	public function test_template_step_after_seed(): void {
		$id = ( new Default_Templates() )->seed_invoice_template();
		$this->assertGreaterThan( 0, $id );
		Layout::save_for_post( $id, Layout::default_invoice() );

		$steps = Onboarding::steps(
			array(
				'settings'    => '#s',
				'new_client'  => '#c',
				'new_invoice' => '#i',
				'templates'   => '#t',
			)
		);
		foreach ( $steps as $step ) {
			if ( 'template' === $step['id'] ) {
				$this->assertTrue( $step['done'] );
			}
		}
	}
}
