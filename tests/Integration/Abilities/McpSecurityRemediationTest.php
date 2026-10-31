<?php
/**
 * Security remediation of the MCP abilities:
 * - L1: every ability checks manage_options inside execute(), so code that
 *   calls the callback directly (bypassing the Abilities API permission
 *   callback) gets nothing and changes nothing;
 * - L2: tsmlt/update-media-metadata restores what it saved when a later write
 *   fails, and reports truthfully what remains changed;
 * - L3: the MCP Adapter's default gateway is opt-in per ability.
 *
 * Every attachment, post and group is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\GetExifFields;
use TinySolutions\mlt\Abilities\Media\GetMediaDetails;
use TinySolutions\mlt\Abilities\Media\ListMediaGroups;
use TinySolutions\mlt\Abilities\Media\SearchMedia;
use TinySolutions\mlt\Abilities\Media\SetMediaGroups;
use TinySolutions\mlt\Abilities\Media\UpdateMediaMetadata;
use TinySolutions\mlt\Helpers\Fns;

/**
 * McpSecurityRemediationTest
 */
class McpSecurityRemediationTest extends WP_UnitTestCase {

	/**
	 * Attachment fixture.
	 *
	 * @var int
	 */
	private $attachment;

	/**
	 * Group fixture.
	 *
	 * @var int
	 */
	private $group;

	/**
	 * Fixtures, as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->attachment = (int) self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Original title',
				'post_excerpt'   => 'Original caption',
				'file'           => 'sec/original.jpg',
			]
		);
		update_post_meta( $this->attachment, '_wp_attachment_image_alt', 'Original alt' );
		$this->group = (int) self::factory()->term->create(
			[
				'taxonomy' => Fns::CATEGORY,
				'name'     => 'Private group name',
			]
		);
	}

	// ---------------------------------------------------------------------
	// L1: execute-time capability check
	// ---------------------------------------------------------------------

	/**
	 * The six abilities and valid input for each.
	 *
	 * @return array
	 */
	private function calls(): array {
		return [
			'search-media'          => [ SearchMedia::class, [] ],
			'get-media-details'     => [ GetMediaDetails::class, [ 'id' => $this->attachment ] ],
			'get-exif-fields'       => [ GetExifFields::class, [ 'id' => $this->attachment ] ],
			'list-media-groups'     => [ ListMediaGroups::class, [] ],
			'set-media-groups'      => [
				SetMediaGroups::class,
				[
					'id'        => $this->attachment,
					'group_ids' => [ $this->group ],
					'mode'      => 'add',
				],
			],
			'update-media-metadata' => [
				UpdateMediaMetadata::class,
				[
					'id'       => $this->attachment,
					'title'    => 'Changed by a direct call',
					'alt_text' => 'Changed alt',
				],
			],
		];
	}

	/**
	 * Users without manage_options.
	 *
	 * @return array
	 */
	public function data_refused_users(): array {
		return [
			'logged out' => [ '', AbilityGuard::ERROR_UNAUTHENTICATED ],
			'subscriber' => [ 'subscriber', AbilityGuard::ERROR_FORBIDDEN ],
			'author'     => [ 'author', AbilityGuard::ERROR_FORBIDDEN ],
			'editor'     => [ 'editor', AbilityGuard::ERROR_FORBIDDEN ],
		];
	}

	/**
	 * Calling execute() directly, without the Abilities API permission
	 * callback, returns only the guard's error: no data, no change.
	 *
	 * @dataProvider data_refused_users
	 *
	 * @param string $role Role ('' for logged out).
	 * @param string $code Expected error code.
	 */
	public function test_direct_execute_refuses_users_without_manage_options( string $role, string $code ) {
		$calls = $this->calls();
		$snapshot = $this->snapshot();
		wp_set_current_user( $role ? self::factory()->user->create( [ 'role' => $role ] ) : 0 );

		foreach ( $calls as $name => [ $class, $input ] ) {
			$result = $class::instance()->execute( $input );
			$this->assertWPError( $result, $name );
			$this->assertSame( $code, $result->get_error_code(), $name );
			$json = (string) wp_json_encode( $result );
			$this->assertStringNotContainsString( 'Original', $json, "$name: no data." );
			$this->assertStringNotContainsString( 'Private group name', $json, "$name: no data." );
		}

		$this->assertSame( $snapshot, $this->snapshot(), 'Nothing changed.' );
	}

	/**
	 * Administrators keep the intended behaviour through direct calls.
	 */
	public function test_direct_execute_still_works_for_administrators() {
		foreach ( $this->calls() as $name => [ $class, $input ] ) {
			$result = $class::instance()->execute( $input );
			$this->assertIsArray( $result, $name . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		}
		clean_post_cache( $this->attachment );
		$this->assertSame( 'Changed by a direct call', get_post( $this->attachment )->post_title );
		$this->assertContains( $this->group, wp_get_object_terms( $this->attachment, Fns::CATEGORY, [ 'fields' => 'ids' ] ) );
	}

	/**
	 * Attachment, alt text and group state.
	 *
	 * @return array
	 */
	private function snapshot(): array {
		clean_post_cache( $this->attachment );
		$post = get_post( $this->attachment );
		return [
			$post->post_title,
			$post->post_excerpt,
			get_post_meta( $this->attachment, '_wp_attachment_image_alt', true ),
			wp_get_object_terms( $this->attachment, Fns::CATEGORY, [ 'fields' => 'ids' ] ),
		];
	}

	// ---------------------------------------------------------------------
	// L2: partial metadata updates
	// ---------------------------------------------------------------------

	/**
	 * Make the alt text write fail.
	 *
	 * @return callable The filter, for removal.
	 */
	private static function fail_alt_write(): callable {
		$filter = static function ( $check, $object_id, $meta_key ) {
			return '_wp_attachment_image_alt' === $meta_key ? false : $check;
		};
		add_filter( 'update_post_metadata', $filter, 10, 3 );
		return $filter;
	}

	/**
	 * Update through the Abilities API.
	 *
	 * @param array $input Input.
	 *
	 * @return mixed
	 */
	private function update( array $input ) {
		return wp_get_ability( 'tsmlt/update-media-metadata' )->execute( array_merge( [ 'id' => $this->attachment ], $input ) );
	}

	/**
	 * The alt text write fails after the title and caption were saved: they
	 * are put back and the error says nothing was changed, which is true.
	 */
	public function test_failed_alt_write_restores_saved_fields() {
		$filter = self::fail_alt_write();

		$result = $this->update(
			[
				'title'    => 'New title',
				'caption'  => 'New caption',
				'alt_text' => 'New alt',
			]
		);

		remove_filter( 'update_post_metadata', $filter, 10 );
		$this->assertWPError( $result );
		$this->assertSame( UpdateMediaMetadata::ERROR_UPDATE_FAILED, $result->get_error_code() );
		$this->assertStringContainsString( 'nothing was changed', $result->get_error_message() );
		clean_post_cache( $this->attachment );
		$this->assertSame( 'Original title', get_post( $this->attachment )->post_title );
		$this->assertSame( 'Original caption', get_post( $this->attachment )->post_excerpt );
		$this->assertSame( 'Original alt', get_post_meta( $this->attachment, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * When the saved fields cannot be put back, the error names exactly the
	 * fields that remain changed, which match what is stored.
	 */
	public function test_unrestorable_fields_are_reported_as_partial() {
		$filter = self::fail_alt_write();
		$calls  = 0;
		$block  = static function ( $maybe_empty ) use ( &$calls ) {
			++$calls;
			// The first post write is this request's; the second is the restore.
			return 2 === $calls ? true : $maybe_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $block );

		$result = $this->update(
			[
				'title'    => 'New title',
				'caption'  => 'New caption',
				'alt_text' => 'New alt',
			]
		);

		remove_filter( 'update_post_metadata', $filter, 10 );
		remove_filter( 'wp_insert_post_empty_content', $block );
		$this->assertWPError( $result );
		$this->assertSame( UpdateMediaMetadata::ERROR_PARTIAL_UPDATE, $result->get_error_code() );
		$this->assertSame( [ 'title', 'caption' ], $result->get_error_data()['applied_fields'] );
		$this->assertSame( [ 'alt_text' ], $result->get_error_data()['failed_fields'] );
		$this->assertStringContainsString( 'title, caption', $result->get_error_message() );
		clean_post_cache( $this->attachment );
		$this->assertSame( 'New title', get_post( $this->attachment )->post_title, 'Reported as changed, and it is.' );
		$this->assertSame( 'New caption', get_post( $this->attachment )->post_excerpt );
		$this->assertSame( 'Original alt', get_post_meta( $this->attachment, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * A field changed by another edit between this request's write and the
	 * failure is not overwritten by the restore.
	 */
	public function test_restore_keeps_a_concurrent_edit() {
		$filter = self::fail_alt_write();
		$once   = false;
		$other  = function ( $check, $object_id, $meta_key ) use ( &$once ) {
			if ( ! $once && '_wp_attachment_image_alt' === $meta_key ) {
				$once = true;
				global $wpdb;
				$wpdb->update( $wpdb->posts, [ 'post_title' => 'Edited by someone else' ], [ 'ID' => $this->attachment ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulates a concurrent edit.
				clean_post_cache( $this->attachment );
			}
			return $check;
		};
		add_filter( 'update_post_metadata', $other, 5, 3 );

		$result = $this->update(
			[
				'title'    => 'New title',
				'caption'  => 'New caption',
				'alt_text' => 'New alt',
			]
		);

		remove_filter( 'update_post_metadata', $other, 5 );
		remove_filter( 'update_post_metadata', $filter, 10 );
		$this->assertWPError( $result );
		clean_post_cache( $this->attachment );
		$this->assertSame( 'Edited by someone else', get_post( $this->attachment )->post_title, 'Not overwritten.' );
		$this->assertSame( 'Original caption', get_post( $this->attachment )->post_excerpt, 'This request\'s caption was put back.' );
	}

	/**
	 * The successful path is unchanged: everything is saved and reported.
	 */
	public function test_successful_update_is_unchanged() {
		$result = $this->update(
			[
				'title'    => 'New title',
				'alt_text' => 'New alt',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ 'id', 'updated_fields', 'previous', 'unchanged_fields', 'media' ], array_keys( $result ) );
		$this->assertSame( [ 'title', 'alt_text' ], $result['updated_fields'] );
		$this->assertSame( 'Original title', $result['previous']['title'] );
		$this->assertSame( 'New alt', get_post_meta( $this->attachment, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Only the alt text changes and its write fails: nothing changed.
	 */
	public function test_failed_alt_only_update_changes_nothing() {
		$filter = self::fail_alt_write();

		$result = $this->update( [ 'alt_text' => 'New alt' ] );

		remove_filter( 'update_post_metadata', $filter, 10 );
		$this->assertSame( UpdateMediaMetadata::ERROR_UPDATE_FAILED, $result->get_error_code() );
		$this->assertStringContainsString( 'Nothing was changed', $result->get_error_message() );
		$this->assertSame( 'Original alt', get_post_meta( $this->attachment, '_wp_attachment_image_alt', true ) );
	}

	// ---------------------------------------------------------------------
	// L3: default gateway is opt-in
	// ---------------------------------------------------------------------

	/**
	 * An ability that does not opt in is not public on the default gateway.
	 */
	public function test_gateway_exposure_is_opt_in_by_default() {
		$ability = new class() extends Ability {
			/**
			 * @return string
			 */
			public function get_name(): string {
				return 'tsmlt/test-default-exposure';
			}
			/**
			 * @return string
			 */
			protected function get_label(): string {
				return 'Test';
			}
			/**
			 * @return string
			 */
			protected function get_description(): string {
				return 'Test';
			}
			/**
			 * @return array
			 */
			protected function get_input_schema(): array {
				return [ 'type' => 'object' ];
			}
			/**
			 * @return array
			 */
			protected function get_output_schema(): array {
				return [ 'type' => 'object' ];
			}
			/**
			 * @return array
			 */
			protected function get_annotations(): array {
				return [];
			}
			/**
			 * @param mixed $input Input.
			 *
			 * @return array
			 */
			public function execute( $input = null ) {
				return [];
			}
		};
		$method  = new ReflectionMethod( $ability, 'is_mcp_public' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $ability ) );
	}

	/**
	 * Only the two read tools opt in; every other Free ability, including the
	 * write tool update-media-metadata, is dedicated-endpoint only.
	 */
	public function test_registered_gateway_exposure() {
		$public = [];
		foreach ( wp_get_abilities() as $ability ) {
			if ( AbilitiesInit::CATEGORY === $ability->get_category() && 0 === strpos( $ability->get_name(), 'tsmlt/' ) ) {
				$public[ $ability->get_name() ] = (bool) ( $ability->get_meta()['mcp']['public'] ?? false );
			}
		}

		$opted_in = array_keys( array_filter( $public ) );
		sort( $opted_in );
		$this->assertSame( [ 'tsmlt/get-media-details', 'tsmlt/search-media' ], $opted_in );
		$this->assertFalse( $public['tsmlt/update-media-metadata'] );
		$this->assertCount( 11, $public );
	}
}
