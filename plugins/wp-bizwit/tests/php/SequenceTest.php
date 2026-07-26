<?php
/**
 * Tests for atomic document number sequences.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Database\Sequence;

/**
 * Sequence allocation must never hand out the same number twice.
 */
class SequenceTest extends WP_UnitTestCase {

	/**
	 * Install schema.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		( new Installer() )->install();
	}

	/**
	 * Sequential calls return strictly increasing unique values.
	 *
	 * @return void
	 */
	public function test_next_is_unique_and_increasing(): void {
		$key    = 'test:seq:' . wp_generate_password( 8, false );
		$values = array();

		for ( $i = 0; $i < 50; $i++ ) {
			$n = Sequence::next( $key );
			$this->assertGreaterThan( 0, $n );
			$values[] = $n;
		}

		$this->assertCount( 50, array_unique( $values ) );
		$sorted = $values;
		sort( $sorted, SORT_NUMERIC );
		$this->assertSame( $sorted, $values, 'Values must be allocated in ascending order' );
		$this->assertSame( range( 1, 50 ), $values );
	}

	/**
	 * Peek does not consume the counter.
	 *
	 * @return void
	 */
	public function test_peek_does_not_consume(): void {
		$key = 'test:peek:' . wp_generate_password( 8, false );
		$this->assertSame( 1, Sequence::peek( $key ) );
		$this->assertSame( 1, Sequence::peek( $key ) );
		$this->assertSame( 1, Sequence::next( $key ) );
		$this->assertSame( 2, Sequence::peek( $key ) );
		$this->assertSame( 2, Sequence::next( $key ) );
	}

	/**
	 * Reset sets the next value that will be allocated.
	 *
	 * @return void
	 */
	public function test_reset_continues_series(): void {
		$key = 'test:reset:' . wp_generate_password( 8, false );
		$this->assertTrue( Sequence::reset( $key, 100 ) );
		$this->assertSame( 100, Sequence::next( $key ) );
		$this->assertSame( 101, Sequence::next( $key ) );
	}

	/**
	 * Many alternating keys stay independent.
	 *
	 * @return void
	 */
	public function test_keys_are_independent(): void {
		$a = 'test:a:' . wp_generate_password( 6, false );
		$b = 'test:b:' . wp_generate_password( 6, false );

		$this->assertSame( 1, Sequence::next( $a ) );
		$this->assertSame( 1, Sequence::next( $b ) );
		$this->assertSame( 2, Sequence::next( $a ) );
		$this->assertSame( 2, Sequence::next( $b ) );
	}
}
