<?php
/**
 * Tests for the `tsmlt/get-media-details` ability.
 *
 * Executed through WP_Ability::execute(), so core input validation, the
 * permission callback and output-schema validation run exactly as they do for
 * an MCP client.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\MediaPresenter;

/**
 * GetMediaDetailsAbilityTest
 */
class GetMediaDetailsAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-media-details';

	/**
	 * Every output field, in order.
	 *
	 * @var string[]
	 */
	const KEYS = [ 'id', 'title', 'alt_text', 'caption', 'description', 'url', 'mime_type', 'filename', 'width', 'height', 'filesize', 'sizes', 'parent', 'date' ];

	/**
	 * Set up an administrator for each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Create an attachment.
	 *
	 * @param array $args Post arguments.
	 *
	 * @return int
	 */
	private function make_attachment( array $args = [] ): int {
		return self::factory()->attachment->create(
			array_merge(
				[
					'post_mime_type' => 'image/jpeg',
					'post_status'    => 'inherit',
					'file'           => '2026/10/photo.jpg',
				],
				$args
			)
		);
	}

	/**
	 * Create an attachment with full metadata, EXIF (including GPS) and custom meta.
	 *
	 * @param int $parent_id Parent post ID.
	 *
	 * @return int
	 */
	private function make_rich_attachment( int $parent_id = 0 ): int {
		$id = $this->make_attachment(
			[
				'post_title'   => 'Harbour &amp; boats',
				'post_excerpt' => 'Evening caption',
				'post_content' => '<p>Long description</p>',
				'post_date'    => '2025-05-06 07:08:09',
				'post_parent'  => $parent_id,
				'file'         => '2025/05/secret-dir-marker/harbour.jpg',
			]
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'Boats in a harbour' );
		update_post_meta( $id, 'private_api_key', 'SECRET-META-MARKER' );
		update_post_meta( $id, '_tsmlt_exif_gps_lat', '51.5007-GPS-MARKER' );
		wp_update_attachment_metadata(
			$id,
			[
				'width'      => 1600,
				'height'     => 900,
				'file'       => '2025/05/secret-dir-marker/harbour.jpg',
				'filesize'   => 123456,
				'sizes'      => [
					'thumbnail' => [
						'file'      => 'harbour-150x150-FILE-MARKER.jpg',
						'width'     => 150,
						'height'    => 150,
						'mime-type' => 'image/jpeg',
						'filesize'  => 4000,
					],
					'medium'    => [
						'file'      => 'harbour-300x169.jpg',
						'width'     => 300,
						'height'    => 169,
						'mime-type' => 'image/jpeg',
					],
				],
				'image_meta' => [
					'camera'    => 'CAMERA-MARKER',
					'copyright' => 'COPYRIGHT-MARKER',
					'latitude'  => '51.5007-GPS-MARKER',
					'longitude' => '-0.1246-GPS-MARKER',
				],
			]
		);
		return $id;
	}

	/**
	 * Execute the ability as the current user.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return mixed
	 */
	private function run_ability( $input ) {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Assert a result is the "attachment not found" error.
	 *
	 * @param mixed $result Ability result.
	 */
	private function assert_not_found( $result ) {
		$this->assertWPError( $result );
		$this->assertSame( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, $result->get_error_code() );
		$this->assertSame( 'No attachment exists with that ID.', $result->get_error_message() );
		$this->assertSame( [ 'status' => 404 ], $result->get_error_data() );
	}

	/**
	 * Assert an output validates against the registered output schema.
	 *
	 * @param mixed $output Ability output.
	 */
	private function assert_matches_schema( $output ) {
		$schema = wp_get_ability( self::NAME )->get_output_schema();
		$this->assertTrue( rest_validate_value_from_schema( $output, $schema, 'output' ) );
	}

	/**
	 * The ability is registered once, with the shared metadata.
	 */
	public function test_registration_and_meta() {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( AbilitiesInit::CATEGORY, $ability->get_category() );

		$meta = $ability->get_meta();
		$this->assertFalse( $meta['show_in_rest'] );
		$this->assertSame(
			[
				'public' => true,
				'type'   => 'tool',
			],
			$meta['mcp']
		);
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertFalse( $meta['annotations']['destructive'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );

		$this->assertSame( MediaPresenter::details_schema(), $ability->get_output_schema() );
		$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
		$this->assertSame( [ 'id' ], $ability->get_input_schema()['required'] );
	}

	/**
	 * Every plugin ability is registered, each exactly once.
	 */
	public function test_registered_once() {
		$names = array_values(
			array_filter(
				array_keys( wp_get_abilities() ),
				static function ( $name ) {
					return 0 === strpos( $name, 'tsmlt/' );
				}
			)
		);
		sort( $names );
		$this->assertSame( [ 'tsmlt/get-media-details', 'tsmlt/search-media', 'tsmlt/update-media-metadata' ], $names );

		$hooked = 0;
		foreach ( $GLOBALS['wp_filter']['wp_abilities_api_init']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof AbilitiesInit ) {
					++$hooked;
				}
			}
		}
		$this->assertSame( 1, $hooked );
	}

	/**
	 * Full details for a well-formed image attachment.
	 */
	public function test_valid_attachment_details() {
		$parent_id = self::factory()->post->create( [ 'post_title' => 'Travel &amp; Boats' ] );
		$id        = $this->make_rich_attachment( $parent_id );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() : '' );
		$this->assertSame( self::KEYS, array_keys( $result ) );
		$this->assertSame(
			[
				'id'          => $id,
				'title'       => 'Harbour & boats',
				'alt_text'    => 'Boats in a harbour',
				'caption'     => 'Evening caption',
				'description' => '<p>Long description</p>',
				'url'         => wp_get_attachment_url( $id ),
				'mime_type'   => 'image/jpeg',
				'filename'    => 'harbour.jpg',
				'width'       => 1600,
				'height'      => 900,
				'filesize'    => 123456,
				'sizes'       => [
					[
						'name'   => 'thumbnail',
						'width'  => 150,
						'height' => 150,
					],
					[
						'name'   => 'medium',
						'width'  => 300,
						'height' => 169,
					],
				],
				'parent'      => [
					'id'    => $parent_id,
					'title' => 'Travel & Boats',
				],
				'date'        => get_post_time( DATE_ATOM, true, $id ),
			],
			$result
		);
		$this->assertStringStartsWith( '2025-05-06T', $result['date'] );
		$this->assert_matches_schema( $result );
	}

	/**
	 * A numeric-string ID is accepted and resolves to the same attachment.
	 */
	public function test_numeric_string_id() {
		$id = $this->make_attachment();

		$this->assertSame( $id, $this->run_ability( [ 'id' => (string) $id ] )['id'] );
	}

	/**
	 * No paths, raw metadata, custom meta or EXIF data leak into the output.
	 */
	public function test_output_does_not_disclose_sensitive_data() {
		$id = $this->make_rich_attachment();

		$result = $this->run_ability( [ 'id' => $id ] );
		$json   = wp_json_encode( $result );

		$this->assertStringNotContainsString( wp_json_encode( ABSPATH ), $json );
		$this->assertStringNotContainsString( wp_json_encode( wp_upload_dir()['basedir'] ), $json );
		foreach ( [ 'secret-dir-marker', 'FILE-MARKER', 'SECRET-META-MARKER', 'GPS-MARKER', 'CAMERA-MARKER', 'COPYRIGHT-MARKER' ] as $marker ) {
			// The URL legitimately contains the upload directory, so check every field but that one.
			$without_url = $result;
			unset( $without_url['url'] );
			$this->assertStringNotContainsString( $marker, wp_json_encode( $without_url ), $marker );
		}
		$this->assertStringNotContainsString( 'GPS-MARKER', $json );
		$this->assertStringNotContainsString( 'FILE-MARKER', $json );
		foreach ( [ 'image_meta', 'latitude', 'longitude', 'metadata', 'custom_meta', 'thefile', 'mainfilepath', 'file', 'guid', 'mime-type' ] as $key ) {
			$this->assertStringNotContainsString( '"' . $key . '"', $json, $key );
		}
	}

	/**
	 * An attachment with no metadata at all reports nulls and empty lists.
	 */
	public function test_missing_optional_metadata() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			]
		);
		delete_post_meta( $id, '_wp_attached_file' );
		delete_post_meta( $id, '_wp_attachment_metadata' );
		delete_post_meta( $id, '_wp_attachment_image_alt' );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assertIsArray( $result );
		$this->assertSame( self::KEYS, array_keys( $result ) );
		$this->assertSame( '', $result['alt_text'] );
		$this->assertNull( $result['filename'] );
		$this->assertNull( $result['width'] );
		$this->assertNull( $result['height'] );
		$this->assertNull( $result['filesize'] );
		$this->assertSame( [], $result['sizes'] );
		$this->assertNull( $result['parent'] );
		$this->assert_matches_schema( $result );
	}

	/**
	 * Malformed metadata values are dropped, never guessed.
	 */
	public function test_malformed_metadata() {
		$id = $this->make_attachment();
		update_post_meta(
			$id,
			'_wp_attachment_metadata',
			[
				'width'    => 'wide',
				'height'   => -5,
				'filesize' => [ 'not', 'a', 'number' ],
				'sizes'    => [
					'good'      => [
						'width'  => '80',
						'height' => '60',
					],
					'no-height' => [ 'width' => 10 ],
					'scalar'    => 'thumb.jpg',
					7           => [
						'width'  => 1,
						'height' => 1,
					],
				],
			]
		);

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assertNull( $result['width'] );
		$this->assertNull( $result['height'] );
		$this->assertNull( $result['filesize'] );
		$this->assertSame(
			[
				[
					'name'   => 'good',
					'width'  => 80,
					'height' => 60,
				],
			],
			$result['sizes']
		);
		$this->assert_matches_schema( $result );
	}

	/**
	 * Metadata stored as a non-array is treated as missing.
	 */
	public function test_non_array_metadata() {
		$id = $this->make_attachment();
		update_post_meta( $id, '_wp_attachment_metadata', 'corrupted' );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assertNull( $result['width'] );
		$this->assertSame( [], $result['sizes'] );
		$this->assert_matches_schema( $result );
	}

	/**
	 * Malformed IDs are rejected by schema validation.
	 *
	 * @dataProvider data_invalid_input
	 *
	 * @param mixed $input Input.
	 */
	public function test_invalid_input_is_rejected( $input ) {
		$result = $this->run_ability( $input );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * @return array
	 */
	public function data_invalid_input(): array {
		return [
			'no input'         => [ null ],
			'empty object'     => [ [] ],
			'not an object'    => [ 5 ],
			'zero'             => [ [ 'id' => 0 ] ],
			'negative'         => [ [ 'id' => -3 ] ],
			'float'            => [ [ 'id' => 1.5 ] ],
			'non numeric'      => [ [ 'id' => 'abc' ] ],
			'numeric prefix'   => [ [ 'id' => '12abc' ] ],
			'array'            => [ [ 'id' => [ 1 ] ] ],
			'null id'          => [ [ 'id' => null ] ],
			'unknown property' => [
				[
					'id'          => 1,
					'post_status' => 'trash',
				],
			],
			'only unknown'     => [ [ 'ID' => 1 ] ],
		];
	}

	/**
	 * An ID with no post behind it is reported as not found.
	 */
	public function test_missing_attachment() {
		$this->assert_not_found( $this->run_ability( [ 'id' => 999999 ] ) );
	}

	/**
	 * A post that is not an attachment is reported as not found.
	 */
	public function test_non_attachment_post() {
		$post_id = self::factory()->post->create();
		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$this->assert_not_found( $this->run_ability( [ 'id' => $post_id ] ) );
		$this->assert_not_found( $this->run_ability( [ 'id' => $page_id ] ) );
	}

	/**
	 * Trashed and private attachments are not reported, matching search-media.
	 */
	public function test_non_inherit_status_is_not_found() {
		$trashed = $this->make_attachment( [ 'post_status' => 'trash' ] );
		$private = $this->make_attachment( [ 'post_status' => 'private' ] );

		$this->assert_not_found( $this->run_ability( [ 'id' => $trashed ] ) );
		$this->assert_not_found( $this->run_ability( [ 'id' => $private ] ) );
	}

	/**
	 * Logged-out requests are refused before any lookup.
	 */
	public function test_unauthenticated_is_refused() {
		$id = $this->make_attachment();
		wp_set_current_user( 0 );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Logged-in users without manage_options are refused, for existing and
	 * missing IDs alike.
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string $role Role name.
	 */
	public function test_users_without_manage_options_are_refused( string $role ) {
		$id = $this->make_attachment();
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );

		$existing = $this->run_ability( [ 'id' => $id ] );
		$missing  = $this->run_ability( [ 'id' => 999999 ] );

		$this->assertWPError( $existing );
		$this->assertSame( 'ability_invalid_permissions', $existing->get_error_code() );
		$this->assertEquals( $existing, $missing );
	}

	/**
	 * @return array
	 */
	public function data_roles_without_manage_options(): array {
		return [
			'editor'      => [ 'editor' ],
			'author'      => [ 'author' ],
			'contributor' => [ 'contributor' ],
			'subscriber'  => [ 'subscriber' ],
		];
	}

	/**
	 * An attachment that fails read_post is indistinguishable from a missing one.
	 */
	public function test_read_post_denied_is_reported_as_not_found() {
		$id   = $this->make_attachment();
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			if ( 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};

		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$denied = $this->run_ability( [ 'id' => $id ] );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assert_not_found( $denied );
		$this->assertEquals( $this->run_ability( [ 'id' => 999999 ] ), $denied );
	}

	/**
	 * A parent the user may not read is reported as null.
	 */
	public function test_unreadable_parent_is_null() {
		$parent_id = self::factory()->post->create();
		$id        = $this->make_attachment( [ 'post_parent' => $parent_id ] );
		$deny      = static function ( $caps, $cap, $user_id, $args ) use ( $parent_id ) {
			if ( 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === $parent_id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};

		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$result = $this->run_ability( [ 'id' => $id ] );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertNull( $result['parent'] );
	}

	/**
	 * Trashed, deleted and plumbing parents are reported as null.
	 */
	public function test_invalid_parents_are_null() {
		$trashed = self::factory()->post->create( [ 'post_status' => 'trash' ] );
		$deleted = self::factory()->post->create();
		$block   = self::factory()->post->create( [ 'post_type' => 'wp_block' ] );
		wp_delete_post( $deleted, true );

		foreach ( [ $trashed, $deleted, $block ] as $parent_id ) {
			$id = $this->make_attachment( [ 'post_parent' => $parent_id ] );
			$this->assertNull( $this->run_ability( [ 'id' => $id ] )['parent'], (string) $parent_id );
		}
	}

	/**
	 * The search-media ability is unaffected and its results can be fed in.
	 */
	public function test_search_results_resolve_to_details() {
		$id = $this->make_rich_attachment();

		$search = wp_get_ability( 'tsmlt/search-media' )->execute( [ 'search' => 'Harbour' ] );
		$this->assertSame( [ $id ], wp_list_pluck( $search['items'], 'id' ) );

		$details = $this->run_ability( [ 'id' => $search['items'][0]['id'] ] );
		$this->assertSame( $search['items'][0]['title'], $details['title'] );
		$this->assertSame( $search['items'][0]['alt_text'], $details['alt_text'] );
		$this->assertSame( $search['items'][0]['url'], $details['url'] );
	}
}
