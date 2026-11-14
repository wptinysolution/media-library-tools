<?php
/**
 * The usage scan's conditional post meta writes (BatchTransaction) behave
 * like core's metadata functions for other plugins: the short-circuit
 * filters decide, the actions before and after the write fire with core's
 * arguments (after the write: only once committed), and caches are
 * cleared. Their condition compares the stored value byte for byte, so a
 * change of letter case or accent only is not overwritten.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\BatchTransaction;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereMetaWriteTest
 */
class UsedWhereMetaWriteTest extends WP_UnitTestCase {

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	const KEY = UsedWhereScanner::META_KEY;

	/**
	 * Hooks fired, in order: [ hook, args ].
	 *
	 * @var array[]
	 */
	private $fired = [];

	/**
	 * Attachment.
	 *
	 * @var int
	 */
	private $image = 0;

	/**
	 * Core meta hooks observed.
	 *
	 * @var string[]
	 */
	const HOOKS = [ 'add_post_meta', 'added_post_meta', 'update_post_meta', 'update_postmeta', 'updated_post_meta', 'updated_postmeta', 'delete_post_meta', 'delete_postmeta', 'deleted_post_meta', 'deleted_postmeta' ];

	/**
	 * An attachment and hook recorders.
	 */
	public function set_up() {
		parent::set_up();
		$this->image = (int) self::factory()->attachment->create(
			[
				'file'           => 'meta/image.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		foreach ( self::HOOKS as $hook ) {
			add_action( $hook, [ $this, 'record' ], 10, 4 );
		}
		$this->fired = [];
	}

	/**
	 * Remove recorders and filters.
	 */
	public function tear_down() {
		foreach ( self::HOOKS as $hook ) {
			remove_action( $hook, [ $this, 'record' ], 10 );
		}
		remove_filter( 'update_post_metadata', [ $this, 'take_over' ] );
		remove_filter( 'delete_post_metadata', [ $this, 'take_over' ] );
		remove_filter( 'add_post_metadata', [ $this, 'take_over' ] );
		parent::tear_down();
	}

	/**
	 * Record a hook (meta key hooks of this key only).
	 *
	 * @param mixed ...$args Arguments.
	 */
	public function record( ...$args ) {
		$this->fired[] = [ current_filter(), $args ];
	}

	/**
	 * A filter that stores the meta its own way.
	 *
	 * @return bool
	 */
	public function take_over() {
		return true;
	}

	/**
	 * Names of the hooks fired, in order.
	 *
	 * @return string[]
	 */
	private function names(): array {
		return array_column( $this->fired, 0 );
	}

	/**
	 * The stored row of the key.
	 *
	 * @return array|null meta_id and raw value.
	 */
	private function row(): ?array {
		return BatchTransaction::instance()->get_meta_row( $this->image, self::KEY );
	}

	/**
	 * Usages with a title.
	 *
	 * @param string $title Title.
	 *
	 * @return array
	 */
	private static function usages( string $title ): array {
		return [
			[
				'post_id'    => 5,
				'post_title' => $title,
				'post_type'  => 'post',
				'usage_type' => 'content',
			],
		];
	}

	/**
	 * Outside a transaction, a conditional update fires core's actions
	 * before and after the write, with core's arguments, and clears the
	 * meta cache.
	 */
	public function test_update_fires_core_hooks_in_order() {
		add_post_meta( $this->image, self::KEY, self::usages( 'Old' ) );
		$row = $this->row();
		get_post_meta( $this->image, self::KEY, true );
		$this->fired = [];

		$written = BatchTransaction::instance()->replace_meta_if( $row['meta_id'], $this->image, self::KEY, $row['raw'], self::usages( 'New' ) );

		$this->assertTrue( $written );
		$this->assertSame( [ 'update_post_meta', 'update_postmeta', 'updated_post_meta', 'updated_postmeta' ], $this->names() );
		$this->assertSame( [ $row['meta_id'], $this->image, self::KEY, self::usages( 'New' ) ], $this->fired[0][1] );
		$this->assertSame( [ $row['meta_id'], $this->image, self::KEY, maybe_serialize( self::usages( 'New' ) ) ], $this->fired[3][1], 'Legacy hook: the serialized value, as core.' );
		$this->assertSame( self::usages( 'New' ), get_post_meta( $this->image, self::KEY, true ), 'The cache was cleared.' );
	}

	/**
	 * In a transaction, the actions before the write fire at once and the
	 * actions after it only once committed; a rollback fires none of them.
	 */
	public function test_after_write_hooks_wait_for_commit() {
		add_post_meta( $this->image, self::KEY, self::usages( 'Old' ) );
		$row         = $this->row();
		$this->fired = [];
		$transaction = BatchTransaction::instance();

		$this->assertTrue( $transaction->begin() );
		$transaction->replace_meta_if( $row['meta_id'], $this->image, self::KEY, $row['raw'], self::usages( 'New' ) );
		$this->assertSame( [ 'update_post_meta', 'update_postmeta' ], $this->names() );
		$transaction->rollback();
		$this->assertSame( [ 'update_post_meta', 'update_postmeta' ], $this->names(), 'No after-write action on rollback.' );
		$this->assertSame( self::usages( 'Old' ), get_post_meta( $this->image, self::KEY, true ) );

		$this->fired = [];
		$this->assertTrue( $transaction->begin() );
		$transaction->replace_meta_if( $row['meta_id'], $this->image, self::KEY, $row['raw'], self::usages( 'New' ) );
		$transaction->commit();
		$this->assertSame( [ 'update_post_meta', 'update_postmeta', 'updated_post_meta', 'updated_postmeta' ], $this->names() );
	}

	/**
	 * A conditional delete fires core's delete actions.
	 */
	public function test_delete_fires_core_hooks_in_order() {
		add_post_meta( $this->image, self::KEY, self::usages( 'Old' ) );
		$row         = $this->row();
		$this->fired = [];

		$deleted = BatchTransaction::instance()->delete_meta_if( $row['meta_id'], $this->image, self::KEY, $row['raw'] );

		$this->assertTrue( $deleted );
		$this->assertSame( [ 'delete_post_meta', 'delete_postmeta', 'deleted_post_meta', 'deleted_postmeta' ], $this->names() );
		$this->assertSame( [ [ $row['meta_id'] ] ], $this->fired[1][1] );
		$this->assertNull( $this->row() );
	}

	/**
	 * A batch insert fires core's add actions, the one after only once
	 * committed.
	 */
	public function test_insert_fires_core_add_hooks() {
		$transaction = BatchTransaction::instance();

		$this->assertTrue( $transaction->begin() );
		$transaction->put_meta( $this->image, self::KEY, self::usages( 'New' ) );
		$this->assertSame( [ 'add_post_meta' ], $this->names() );
		$transaction->commit();

		$this->assertSame( [ 'add_post_meta', 'added_post_meta' ], $this->names() );
		$this->assertSame( self::usages( 'New' ), get_post_meta( $this->image, self::KEY, true ) );
	}

	/**
	 * A short-circuit filter decides, as with core: nothing is written and
	 * the write counts as done.
	 */
	public function test_short_circuit_filters_are_honoured() {
		add_post_meta( $this->image, self::KEY, self::usages( 'Old' ) );
		$row = $this->row();
		add_filter( 'update_post_metadata', [ $this, 'take_over' ] );
		add_filter( 'delete_post_metadata', [ $this, 'take_over' ] );
		add_filter( 'add_post_metadata', [ $this, 'take_over' ] );
		$this->fired = [];
		$transaction = BatchTransaction::instance();

		$this->assertTrue( $transaction->replace_meta_if( $row['meta_id'], $this->image, self::KEY, $row['raw'], self::usages( 'New' ) ) );
		$this->assertTrue( $transaction->delete_meta_if( $row['meta_id'], $this->image, self::KEY, $row['raw'] ) );
		$this->assertTrue( $transaction->begin() );
		$transaction->put_meta( $this->image + 1, self::KEY, self::usages( 'New' ) );
		$transaction->commit();

		$this->assertSame( [], $this->names() );
		$this->assertSame( $row, $this->row(), 'Nothing written.' );
		$this->assertNull( $transaction->get_meta_row( $this->image + 1, self::KEY ) );
	}

	/**
	 * A stored value that differs from the value read in letter case only,
	 * or in an accent only, is not overwritten or deleted (the column's
	 * collation would treat them as equal).
	 */
	public function test_case_or_accent_only_change_is_not_overwritten() {
		$transaction = BatchTransaction::instance();
		foreach ( [ [ 'POST FIVE', 'post five' ], [ 'Café', 'Cafe' ] ] as $pair ) {
			delete_post_meta( $this->image, self::KEY );
			add_post_meta( $this->image, self::KEY, self::usages( $pair[0] ) );
			$row = $this->row();
			$old = str_replace( $pair[0], $pair[1], $row['raw'] );
			if ( strlen( $pair[0] ) !== strlen( $pair[1] ) ) {
				// Keep the serialized length right for the older value.
				$old = maybe_serialize( self::usages( $pair[1] ) );
			}

			$this->assertFalse( $transaction->replace_meta_if( $row['meta_id'], $this->image, self::KEY, $old, self::usages( 'Other' ) ), $pair[0] );
			$this->assertFalse( $transaction->delete_meta_if( $row['meta_id'], $this->image, self::KEY, $old ), $pair[0] );
			$this->assertSame( $row, $this->row(), $pair[0] . ' kept.' );
		}
	}
}
