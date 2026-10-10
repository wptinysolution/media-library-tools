<?php
/**
 * Tests for the `tsmlt/update-media-metadata` ability.
 *
 * Executed through WP_Ability::execute(), so core input validation, the
 * permission callback and output-schema validation run exactly as they do for
 * an MCP client.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\UpdateMediaMetadata;

/**
 * UpdateMediaMetadataAbilityTest
 */
class UpdateMediaMetadataAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/update-media-metadata';

	/**
	 * Parent post ID of the fixture attachment.
	 *
	 * @var int
	 */
	private $parent_id;

	/**
	 * Set up an administrator for each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Create an attachment with known values for every field, plus data the
	 * ability must never touch.
	 *
	 * @param array $args Post arguments.
	 *
	 * @return int
	 */
	private function make_attachment( array $args = [] ): int {
		$this->parent_id = self::factory()->post->create( [ 'post_title' => 'Parent post' ] );

		$id = self::factory()->attachment->create(
			array_merge(
				[
					'post_mime_type' => 'image/jpeg',
					'post_status'    => 'inherit',
					'post_title'     => 'Old title',
					'post_excerpt'   => 'Old caption',
					'post_content'   => 'Old description',
					'post_parent'    => $this->parent_id,
					'file'           => '2026/10/original.jpg',
				],
				$args
			)
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'Old alt' );
		update_post_meta( $id, 'unrelated_custom_field', 'CUSTOM-META-MARKER' );
		wp_update_attachment_metadata(
			$id,
			[
				'width'      => 800,
				'height'     => 600,
				'file'       => '2026/10/original.jpg',
				'sizes'      => [
					'thumbnail' => [
						'file'   => 'original-150x150.jpg',
						'width'  => 150,
						'height' => 150,
					],
				],
				'image_meta' => [
					'latitude'  => 'GPS-MARKER',
					'longitude' => 'GPS-MARKER',
					'camera'    => 'CAMERA-MARKER',
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
	 * Run the ability and assert it succeeded.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array
	 */
	private function run_ok( array $input ): array {
		$result = $this->run_ability( $input );
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( self::NAME )->get_output_schema(), 'output' ) );
		return $result;
	}

	/**
	 * Stored values of the four editable fields.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array<string, string>
	 */
	private function stored( int $id ): array {
		clean_post_cache( $id );
		$post = get_post( $id );
		return [
			'title'       => $post->post_title,
			'alt_text'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
		];
	}

	/**
	 * Everything about the attachment the ability must never change.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private function untouched_state( int $id ): array {
		clean_post_cache( $id );
		$post = get_post( $id );
		$meta = get_post_meta( $id );
		unset( $meta['_wp_attachment_image_alt'], $meta['_edit_lock'], $meta['_edit_last'] );

		return [
			'post_parent'    => $post->post_parent,
			'guid'           => $post->guid,
			'post_name'      => $post->post_name,
			'post_status'    => $post->post_status,
			'post_mime_type' => $post->post_mime_type,
			'post_author'    => $post->post_author,
			'post_date'      => $post->post_date,
			'menu_order'     => $post->menu_order,
			'attached_file'  => get_attached_file( $id ),
			'metadata'       => wp_get_attachment_metadata( $id ),
			'other_meta'     => $meta,
		];
	}

	/**
	 * Assert an error code and status.
	 *
	 * @param mixed  $result Ability result.
	 * @param string $code   Expected code.
	 * @param int    $status Expected status, or 0 to skip.
	 */
	private function assert_error( $result, string $code, int $status = 0 ) {
		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code(), $result->get_error_message() );
		if ( $status ) {
			$this->assertSame( $status, $result->get_error_data()['status'] );
		}
	}

	// ---------------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------------

	/**
	 * Registered with the shared metadata and write annotations.
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
		$this->assertFalse( $meta['annotations']['readonly'] );
		$this->assertTrue( $meta['annotations']['destructive'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );

		$schema = $ability->get_input_schema();
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertSame( [ 'id', 'title', 'alt_text', 'caption', 'description' ], array_keys( $schema['properties'] ) );
	}

	/**
	 * Every plugin ability is registered exactly once.
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
	}

	// ---------------------------------------------------------------------
	// Persistence
	// ---------------------------------------------------------------------

	/**
	 * Each field saves to its WordPress storage, leaving the others alone.
	 *
	 * @dataProvider data_single_fields
	 *
	 * @param string $field Field name.
	 * @param string $value New value.
	 */
	public function test_each_field_saves_and_preserves_others( string $field, string $value ) {
		$id     = $this->make_attachment();
		$before = $this->stored( $id );

		$result = $this->run_ok(
			[
				'id'   => $id,
				$field => $value,
			]
		);

		$expected           = $before;
		$expected[ $field ] = $value;
		$this->assertSame( $expected, $this->stored( $id ) );
		$this->assertSame( [ $field ], $result['updated_fields'] );
		$this->assertSame( [], $result['unchanged_fields'] );
	}

	/**
	 * @return array
	 */
	public function data_single_fields(): array {
		return [
			'title'       => [ 'title', 'New title' ],
			'alt_text'    => [ 'alt_text', 'New alt' ],
			'caption'     => [ 'caption', 'New caption' ],
			'description' => [ 'description', 'New description' ],
		];
	}

	/**
	 * Every field at once, with the summary reflecting the new values.
	 */
	public function test_all_fields_in_one_request() {
		$id = $this->make_attachment();

		$result = $this->run_ok(
			[
				'id'          => $id,
				'title'       => 'T2',
				'alt_text'    => 'A2',
				'caption'     => 'C2',
				'description' => 'D2',
			]
		);

		$this->assertSame(
			[
				'title'       => 'T2',
				'alt_text'    => 'A2',
				'caption'     => 'C2',
				'description' => 'D2',
			],
			$this->stored( $id )
		);
		$this->assertSame( $id, $result['id'] );
		$this->assertSame( [ 'title', 'alt_text', 'caption', 'description' ], $result['updated_fields'] );
		$this->assertSame( 'T2', $result['media']['title'] );
		$this->assertSame( 'A2', $result['media']['alt_text'] );
		$this->assertSame( 'C2', $result['media']['caption'] );
	}

	/**
	 * Empty strings clear fields; omitted fields stay.
	 */
	public function test_explicit_empty_strings_clear_fields() {
		$id = $this->make_attachment();

		$result = $this->run_ok(
			[
				'id'       => $id,
				'alt_text' => '',
				'caption'  => '',
			]
		);

		$this->assertSame(
			[
				'title'       => 'Old title',
				'alt_text'    => '',
				'caption'     => '',
				'description' => 'Old description',
			],
			$this->stored( $id )
		);
		$this->assertSame( [ 'alt_text', 'caption' ], $result['updated_fields'] );
	}

	/**
	 * Every field, including the title, can be cleared on an attachment.
	 */
	public function test_all_fields_can_be_cleared() {
		$id = $this->make_attachment();

		$result = $this->run_ok(
			[
				'id'          => $id,
				'title'       => '',
				'alt_text'    => '',
				'caption'     => '',
				'description' => '',
			]
		);

		$this->assertSame( array_fill_keys( [ 'title', 'alt_text', 'caption', 'description' ], '' ), $this->stored( $id ) );
		$this->assertCount( 4, $result['updated_fields'] );
	}

	/**
	 * Values already stored are reported as unchanged and not rewritten.
	 */
	public function test_unchanged_values_are_reported_as_unchanged() {
		$id = $this->make_attachment();

		$result = $this->run_ok(
			[
				'id'       => $id,
				'title'    => 'Old title',
				'alt_text' => 'Old alt',
				'caption'  => 'New caption',
			]
		);

		$this->assertSame( [ 'caption' ], $result['updated_fields'] );
		$this->assertSame( [ 'title', 'alt_text' ], $result['unchanged_fields'] );

		// A request with nothing to change writes nothing.
		$noop = $this->run_ok(
			[
				'id'    => $id,
				'title' => 'Old title',
			]
		);
		$this->assertSame( [], $noop['updated_fields'] );
		$this->assertSame( [ 'title' ], $noop['unchanged_fields'] );
	}

	/**
	 * Repeating a request gives the same stored state.
	 */
	public function test_idempotent() {
		$id    = $this->make_attachment();
		$input = [
			'id'    => $id,
			'title' => 'Same',
		];

		$first  = $this->run_ok( $input );
		$state  = $this->stored( $id );
		$second = $this->run_ok( $input );

		$this->assertSame( [ 'title' ], $first['updated_fields'] );
		$this->assertSame( [], $second['updated_fields'] );
		$this->assertSame( $state, $this->stored( $id ) );
	}

	/**
	 * Parent, file, GUID, slug, raw metadata and other meta are untouched.
	 */
	public function test_unrelated_data_is_untouched() {
		$id     = $this->make_attachment();
		$before = $this->untouched_state( $id );
		$other  = $this->make_attachment( [ 'post_title' => 'Other attachment' ] );
		$peer   = $this->stored( $other );

		$this->run_ok(
			[
				'id'          => $id,
				'title'       => 'Changed title that could affect a slug',
				'alt_text'    => 'Changed',
				'caption'     => 'Changed',
				'description' => 'Changed',
			]
		);

		$this->assertSame( $before, $this->untouched_state( $id ) );
		$this->assertSame( $peer, $this->stored( $other ) );
		$this->assertSame( 'Parent post', get_post( $this->parent_id )->post_title );
	}

	/**
	 * Markup is stripped from plain-text fields; the description keeps safe HTML.
	 */
	public function test_sanitization() {
		$id = $this->make_attachment();

		$this->run_ok(
			[
				'id'          => $id,
				'title'       => '<b>Bold</b> <script>alert(1)</script>title',
				'alt_text'    => "  Alt <img src=x onerror=alert(1)>\n text  ",
				'caption'     => '<a href="https://example.com">Link</a> caption',
				'description' => '<p onclick="x()">Para <strong>bold</strong></p><script>alert(1)</script>',
			]
		);

		$stored = $this->stored( $id );
		$this->assertSame( 'Bold title', $stored['title'] );
		$this->assertSame( 'Alt text', $stored['alt_text'] );
		$this->assertSame( 'Link caption', $stored['caption'] );
		$this->assertSame( '<p>Para <strong>bold</strong></p>alert(1)', $stored['description'] );
	}

	/**
	 * Backslashes and quotes survive the slashing WordPress expects.
	 */
	public function test_backslashes_and_quotes_are_preserved() {
		$id = $this->make_attachment();

		$this->run_ok(
			[
				'id'       => $id,
				'title'    => 'C:\\path "quoted" it\'s',
				'alt_text' => 'back\\slash',
			]
		);

		$stored = $this->stored( $id );
		$this->assertSame( 'C:\\path "quoted" it\'s', $stored['title'] );
		$this->assertSame( 'back\\slash', $stored['alt_text'] );
	}

	/**
	 * Fields at the maximum length are accepted.
	 */
	public function test_maximum_lengths_are_accepted() {
		$id    = $this->make_attachment();
		$input = [ 'id' => $id ];
		foreach ( UpdateMediaMetadata::MAX_LENGTHS as $field => $max ) {
			$input[ $field ] = str_repeat( 'a', $max );
		}

		$this->run_ok( $input );

		foreach ( UpdateMediaMetadata::MAX_LENGTHS as $field => $max ) {
			$this->assertSame( $max, strlen( $this->stored( $id )[ $field ] ), $field );
		}
	}

	// ---------------------------------------------------------------------
	// Failure handling
	// ---------------------------------------------------------------------

	/**
	 * A WordPress post-update error is returned, not reported as success.
	 */
	public function test_post_update_error_is_reported() {
		$id     = $this->make_attachment();
		$before = $this->stored( $id );
		// Makes wp_insert_post() return a WP_Error, as it does for any refused save.
		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$result = $this->run_ability(
			[
				'id'    => $id,
				'title' => 'Will not save',
			]
		);
		remove_filter( 'wp_insert_post_empty_content', '__return_true' );

		$this->assert_error( $result, UpdateMediaMetadata::ERROR_UPDATE_FAILED, 500 );
		$this->assertStringNotContainsString( 'empty', strtolower( $result->get_error_message() ) );
		$this->assertSame( $before, $this->stored( $id ) );
	}

	/**
	 * A metadata write failure for alt text is returned, not reported as success.
	 */
	public function test_alt_text_update_error_is_reported() {
		$id     = $this->make_attachment();
		$before = $this->stored( $id );
		$block  = static function ( $check, $object_id, $meta_key ) {
			return '_wp_attachment_image_alt' === $meta_key ? false : $check;
		};
		add_filter( 'update_post_metadata', $block, 10, 3 );

		$result = $this->run_ability(
			[
				'id'       => $id,
				'alt_text' => 'Will not save',
			]
		);
		remove_filter( 'update_post_metadata', $block, 10 );

		$this->assert_error( $result, UpdateMediaMetadata::ERROR_UPDATE_FAILED, 500 );
		$this->assertSame( $before, $this->stored( $id ) );
	}

	// ---------------------------------------------------------------------
	// Input validation
	// ---------------------------------------------------------------------

	/**
	 * Invalid input is rejected by schema validation before anything is written.
	 *
	 * @dataProvider data_invalid_input
	 *
	 * @param mixed $input Input; `{id}` is replaced with a real attachment ID.
	 */
	public function test_invalid_input_is_rejected( $input ) {
		$id = $this->make_attachment();
		if ( is_array( $input ) && isset( $input['id'] ) && '{id}' === $input['id'] ) {
			$input['id'] = $id;
		}
		$before = $this->stored( $id );

		$this->assert_error( $this->run_ability( $input ), 'ability_invalid_input' );
		$this->assertSame( $before, $this->stored( $id ) );
	}

	/**
	 * @return array
	 */
	public function data_invalid_input(): array {
		return [
			'no input'               => [ null ],
			'not an object'          => [ 'title' ],
			'missing id'             => [ [ 'title' => 'x' ] ],
			'zero id'                => [
				[
					'id'    => 0,
					'title' => 'x',
				],
			],
			'negative id'            => [
				[
					'id'    => -1,
					'title' => 'x',
				],
			],
			'malformed id'           => [
				[
					'id'    => '12abc',
					'title' => 'x',
				],
			],
			'float id'               => [
				[
					'id'    => 1.5,
					'title' => 'x',
				],
			],
			'no fields'              => [ [ 'id' => '{id}' ] ],
			'unknown property'       => [
				[
					'id'    => '{id}',
					'title' => 'x',
					'slug'  => 'y',
				],
			],
			'filename property'      => [
				[
					'id'       => '{id}',
					'filename' => 'evil.php',
				],
			],
			'parent property'        => [
				[
					'id'     => '{id}',
					'parent' => 1,
				],
			],
			'custom_meta property'   => [
				[
					'id'          => '{id}',
					'custom_meta' => [ 'k' => 'v' ],
				],
			],
			'rename_to property'     => [
				[
					'id'        => '{id}',
					'rename_to' => 'x',
				],
			],
			'metadata property'      => [
				[
					'id'       => '{id}',
					'metadata' => [],
				],
			],
			'post_title raw key'     => [
				[
					'id'         => '{id}',
					'post_title' => 'x',
				],
			],
			'title not string'       => [
				[
					'id'    => '{id}',
					'title' => 5,
				],
			],
			'alt_text array'         => [
				[
					'id'       => '{id}',
					'alt_text' => [ 'x' ],
				],
			],
			'caption null'           => [
				[
					'id'      => '{id}',
					'caption' => null,
				],
			],
			'description bool'       => [
				[
					'id'          => '{id}',
					'description' => true,
				],
			],
			'title too long'         => [
				[
					'id'    => '{id}',
					'title' => str_repeat( 'a', 256 ),
				],
			],
			'alt_text too long'      => [
				[
					'id'       => '{id}',
					'alt_text' => str_repeat( 'a', 501 ),
				],
			],
			'caption too long'       => [
				[
					'id'      => '{id}',
					'caption' => str_repeat( 'a', 2001 ),
				],
			],
			'description too long'   => [
				[
					'id'          => '{id}',
					'description' => str_repeat( 'a', 10001 ),
				],
			],
		];
	}

	// ---------------------------------------------------------------------
	// Authorization
	// ---------------------------------------------------------------------

	/**
	 * Logged-out requests are refused and write nothing.
	 */
	public function test_unauthenticated_is_refused() {
		$id     = $this->make_attachment();
		$before = $this->stored( $id );
		wp_set_current_user( 0 );

		$this->assert_error(
			$this->run_ability(
				[
					'id'    => $id,
					'title' => 'Hacked',
				]
			),
			'ability_invalid_permissions'
		);
		$this->assertSame( $before, $this->stored( $id ) );
	}

	/**
	 * Users without manage_options are refused, even those who may edit the attachment.
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string $role Role name.
	 */
	public function test_users_without_manage_options_are_refused( string $role ) {
		$id     = $this->make_attachment();
		$before = $this->stored( $id );
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );

		$this->assert_error(
			$this->run_ability(
				[
					'id'    => $id,
					'title' => 'Hacked',
				]
			),
			'ability_invalid_permissions'
		);
		$this->assertSame( $before, $this->stored( $id ) );
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
	 * Missing attachments, other post types and hidden statuses are not found.
	 */
	public function test_missing_and_non_attachment_targets() {
		$post_id = self::factory()->post->create( [ 'post_title' => 'A post' ] );
		$trashed = $this->make_attachment( [ 'post_status' => 'trash' ] );

		foreach ( [ 999999, $post_id, $trashed ] as $id ) {
			$result = $this->run_ability(
				[
					'id'    => $id,
					'title' => 'x',
				]
			);
			$this->assert_error( $result, AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, 404 );
		}
		$this->assertSame( 'A post', get_post( $post_id )->post_title );
		$this->assertSame( 'Old title', get_post( $trashed )->post_title );
	}

	/**
	 * A user who may read but not edit the attachment is refused, and nothing changes.
	 */
	public function test_edit_post_denied_is_refused() {
		$id     = $this->make_attachment();
		$before = $this->stored( $id );
		$deny   = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			if ( 'edit_post' === $cap && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};

		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$result = $this->run_ability(
			[
				'id'       => $id,
				'title'    => 'Blocked',
				'alt_text' => 'Blocked',
			]
		);
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assert_error( $result, AbilityGuard::ERROR_FORBIDDEN, 403 );
		$this->assertSame( $before, $this->stored( $id ) );
	}

	/**
	 * A user who may not even read the attachment gets "not found".
	 */
	public function test_read_post_denied_is_reported_as_not_found() {
		$id   = $this->make_attachment();
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			if ( in_array( $cap, [ 'read_post', 'edit_post' ], true ) && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};

		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$result = $this->run_ability(
			[
				'id'    => $id,
				'title' => 'Blocked',
			]
		);
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assert_error( $result, AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, 404 );
		$this->assertSame( 'Old title', get_post( $id )->post_title );
	}

	// ---------------------------------------------------------------------
	// Output
	// ---------------------------------------------------------------------

	/**
	 * Output is exactly the documented shape, with no sensitive data.
	 */
	public function test_output_shape_and_no_leaks() {
		$id = $this->make_attachment();

		$result = $this->run_ok(
			[
				'id'    => $id,
				'title' => 'Shape',
			]
		);
		$json = wp_json_encode( $result );

		$this->assertSame( [ 'id', 'updated_fields', 'unchanged_fields', 'media' ], array_keys( $result ) );
		$this->assertSame( [ 'id', 'title', 'alt_text', 'caption', 'mime_type', 'url', 'date' ], array_keys( $result['media'] ) );
		$this->assertStringNotContainsString( wp_json_encode( ABSPATH ), $json );
		$this->assertStringNotContainsString( wp_json_encode( wp_upload_dir()['basedir'] ), $json );
		foreach ( [ 'GPS-MARKER', 'CAMERA-MARKER', 'CUSTOM-META-MARKER', 'original-150x150' ] as $marker ) {
			$this->assertStringNotContainsString( $marker, $json, $marker );
		}
		foreach ( [ 'metadata', 'image_meta', 'guid', 'post_content', 'custom_meta' ] as $key ) {
			$this->assertStringNotContainsString( '"' . $key . '"', $json, $key );
		}
	}

	/**
	 * The read abilities see the new values.
	 */
	public function test_read_abilities_reflect_update() {
		$id = $this->make_attachment();

		$this->run_ok(
			[
				'id'          => $id,
				'description' => 'Visible elsewhere',
				'alt_text'    => 'Fresh alt',
			]
		);

		$details = wp_get_ability( 'tsmlt/get-media-details' )->execute( [ 'id' => $id ] );
		$this->assertSame( 'Visible elsewhere', $details['description'] );
		$this->assertSame( 'Fresh alt', $details['alt_text'] );

		$search = wp_get_ability( 'tsmlt/search-media' )->execute( [ 'search' => 'Old title' ] );
		$this->assertSame( 'Fresh alt', $search['items'][0]['alt_text'] );
	}
}
