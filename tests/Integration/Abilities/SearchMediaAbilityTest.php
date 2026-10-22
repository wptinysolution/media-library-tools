<?php
/**
 * Tests for the `tsmlt/search-media` ability.
 *
 * Executed through WP_Ability::execute(), so core input validation, the
 * permission callback and output-schema validation all run exactly as they do
 * for an MCP client.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;

/**
 * SearchMediaAbilityTest
 */
class SearchMediaAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/search-media';

	/**
	 * Fields every returned item must have, and no others.
	 *
	 * @var string[]
	 */
	const ITEM_KEYS = [ 'id', 'title', 'alt_text', 'caption', 'mime_type', 'url', 'date' ];

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Set up an administrator for each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Create an attachment.
	 *
	 * @param array $args Post arguments, plus optional `alt`.
	 *
	 * @return int
	 */
	private function make_attachment( array $args = [] ): int {
		$alt = $args['alt'] ?? null;
		unset( $args['alt'] );

		$id = self::factory()->attachment->create(
			array_merge(
				[
					'post_mime_type' => 'image/jpeg',
					'post_status'    => 'inherit',
					'file'           => '2026/10/image-' . wp_generate_password( 6, false ) . '.jpg',
				],
				$args
			)
		);
		if ( null !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		return $id;
	}

	/**
	 * Execute the ability as the current user.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return mixed
	 */
	private function run_ability( $input = null ) {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Execute and return the IDs of the returned items.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return int[]
	 */
	private function run_ids( $input = null ): array {
		$result = $this->run_ability( $input );
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() : '' );
		return wp_list_pluck( $result['items'], 'id' );
	}

	/**
	 * Run a callback while read_post is denied for some attachments, as a
	 * plugin's map_meta_cap filter might do.
	 *
	 * @param int[]    $hidden   Attachment IDs to deny.
	 * @param callable $callback Callback.
	 *
	 * @return mixed The callback's return value.
	 */
	private function with_read_denied( array $hidden, callable $callback ) {
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $hidden ) {
			if ( 'read_post' === $cap && isset( $args[0] ) && in_array( (int) $args[0], $hidden, true ) ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		try {
			return $callback();
		} finally {
			remove_filter( 'map_meta_cap', $deny, 10 );
		}
	}

	/**
	 * Create attachments in a known ID order, titled for a shared search term.
	 *
	 * @param int $count Number of attachments.
	 *
	 * @return int[] IDs in ascending order.
	 */
	private function make_attachments( int $count ): array {
		$ids = [];
		for ( $i = 1; $i <= $count; $i++ ) {
			$ids[] = $this->make_attachment( [ 'post_title' => 'Permission probe ' . $i ] );
		}
		return $ids;
	}

	/**
	 * Assert the input is rejected by schema validation.
	 *
	 * @param mixed $input Ability input.
	 */
	private function assert_invalid_input( $input ) {
		$result = $this->run_ability( $input );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * The category and ability are registered with the verified metadata.
	 */
	public function test_registration_and_meta() {
		$this->assertTrue( AbilitiesInit::is_supported() );
		$this->assertTrue( wp_has_ability_category( AbilitiesInit::CATEGORY ) );

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
	}

	/**
	 * With show_in_rest false the core REST run route refuses the ability.
	 */
	public function test_not_exposed_through_core_rest() {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . self::NAME . '/run' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Logged-out requests are refused.
	 */
	public function test_unauthenticated_is_refused() {
		$this->make_attachment();
		wp_set_current_user( 0 );

		$result = $this->run_ability();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Logged-in users without manage_options are refused.
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string $role Role name.
	 */
	public function test_users_without_manage_options_are_refused( string $role ) {
		$this->make_attachment();
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );

		$result = $this->run_ability();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
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
	 * No input at all uses the defaults.
	 */
	public function test_no_input_uses_defaults() {
		$this->make_attachment();
		$this->make_attachment();

		foreach ( [ null, [] ] as $input ) {
			$result = $this->run_ability( $input );
			$this->assertIsArray( $result );
			$this->assertCount( 2, $result['items'] );
			$this->assertSame( 2, $result['total'] );
			$this->assertSame( 1, $result['page'] );
			$this->assertSame( 1, $result['total_pages'] );
		}
	}

	/**
	 * Output contains only the allowlisted fields and values.
	 */
	public function test_output_shape_and_values() {
		$id = $this->make_attachment(
			[
				'post_title'   => 'Usage &amp; Unused',
				'post_excerpt' => 'A caption',
				'post_date'    => '2025-05-06 07:08:09',
				'file'         => '2025/05/usage.jpg',
				'alt'          => 'Alt words',
			]
		);

		$result = $this->run_ability();

		$this->assertSame( [ 'items', 'total', 'page', 'total_pages' ], array_keys( $result ) );
		$item = $result['items'][0];
		$this->assertSame( self::ITEM_KEYS, array_keys( $item ) );
		$this->assertSame( $id, $item['id'] );
		$this->assertSame( 'Usage & Unused', $item['title'] );
		$this->assertSame( 'Alt words', $item['alt_text'] );
		$this->assertSame( 'A caption', $item['caption'] );
		$this->assertSame( 'image/jpeg', $item['mime_type'] );
		$this->assertSame( wp_get_attachment_url( $id ), $item['url'] );
		$this->assertSame( get_post_time( DATE_ATOM, true, $id ), $item['date'] );
		$this->assertStringStartsWith( '2025-05-06T', $item['date'] );
	}

	/**
	 * No filesystem paths, raw metadata or custom meta leak into the output.
	 */
	public function test_output_does_not_disclose_paths_or_meta() {
		$id = $this->make_attachment( [ 'file' => '2026/10/secret.jpg' ] );
		wp_update_attachment_metadata(
			$id,
			[
				'file'       => '2026/10/secret.jpg',
				'width'      => 10,
				'height'     => 10,
				'image_meta' => [ 'camera' => 'CAMERA-MARKER' ],
			]
		);
		update_post_meta( $id, '_private_api_key', 'SECRET-META-MARKER' );
		update_post_meta( $id, 'public_custom_field', 'CUSTOM-META-MARKER' );

		$json = wp_json_encode( $this->run_ability() );

		$this->assertStringNotContainsString( ABSPATH, $json );
		$this->assertStringNotContainsString( wp_upload_dir()['basedir'], $json );
		$this->assertStringNotContainsString( 'CAMERA-MARKER', $json );
		$this->assertStringNotContainsString( 'SECRET-META-MARKER', $json );
		$this->assertStringNotContainsString( 'CUSTOM-META-MARKER', $json );
		foreach ( [ 'mainfilepath', 'metadata', 'custom_meta', 'thefile', 'guid', 'image_meta' ] as $key ) {
			$this->assertStringNotContainsString( '"' . $key . '"', $json );
		}
	}

	/**
	 * Only `inherit` attachments are returned; trashed and private ones are not.
	 */
	public function test_only_inherit_status() {
		$listed = $this->make_attachment();
		$this->make_attachment( [ 'post_status' => 'trash' ] );
		$this->make_attachment( [ 'post_status' => 'private' ] );

		$this->assertSame( [ $listed ], $this->run_ids() );
	}

	/**
	 * Only attachments are returned, never other post types.
	 */
	public function test_only_attachments() {
		$attachment = $this->make_attachment( [ 'post_title' => 'Findme attachment' ] );
		self::factory()->post->create( [ 'post_title' => 'Findme post' ] );
		self::factory()->post->create(
			[
				'post_title' => 'Findme page',
				'post_type'  => 'page',
			]
		);

		$this->assertSame( [ $attachment ], $this->run_ids( [ 'search' => 'Findme' ] ) );
	}

	/**
	 * Search phrase.
	 */
	public function test_search() {
		$match = $this->make_attachment( [ 'post_title' => 'Sunset over the harbour' ] );
		$this->make_attachment( [ 'post_title' => 'Office desk' ] );

		$this->assertSame( [ $match ], $this->run_ids( [ 'search' => 'harbour' ] ) );
	}

	/**
	 * Markup in the search phrase is stripped, not matched literally.
	 */
	public function test_search_is_sanitized() {
		$match = $this->make_attachment( [ 'post_title' => 'harbour' ] );

		$this->assertSame( [ $match ], $this->run_ids( [ 'search' => '<script>x</script>harbour' ] ) );
	}

	/**
	 * Top-level and full MIME filters.
	 */
	public function test_mime_type_filter() {
		$jpeg = $this->make_attachment();
		$png  = $this->make_attachment( [ 'post_mime_type' => 'image/png' ] );
		$pdf  = $this->make_attachment(
			[
				'post_mime_type' => 'application/pdf',
				'file'           => '2026/10/doc.pdf',
			]
		);

		$images = $this->run_ids( [ 'mime_type' => 'image' ] );
		sort( $images );
		$this->assertSame( [ $jpeg, $png ], $images );
		$this->assertSame( [ $png ], $this->run_ids( [ 'mime_type' => 'image/png' ] ) );
		$this->assertSame( [ $pdf ], $this->run_ids( [ 'mime_type' => 'application/pdf' ] ) );
	}

	/**
	 * A well-formed but unknown MIME type is refused by the execution code.
	 */
	public function test_unknown_mime_type_is_refused() {
		$result = $this->run_ability( [ 'mime_type' => 'image/not-a-real-type' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_invalid_input', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Missing-alt filter treats absent and empty alt text the same.
	 */
	public function test_missing_alt_filter() {
		$this->make_attachment( [ 'alt' => 'Has alt' ] );
		$absent = $this->make_attachment();
		$empty  = $this->make_attachment( [ 'alt' => '' ] );
		// The plugin copies the title into the alt text on upload by default
		// (`default_alt_text` = image_name_to_alt), so remove it explicitly.
		delete_post_meta( $absent, '_wp_attachment_image_alt' );

		$ids = $this->run_ids( [ 'missing_alt' => true ] );
		sort( $ids );
		$this->assertSame( [ $absent, $empty ], $ids );

		$this->assertCount( 3, $this->run_ids( [ 'missing_alt' => false ] ) );
	}

	/**
	 * Attachments with a parent post (draft, private and trashed parents
	 * included) and attachments without one.
	 *
	 * @return array<string, int>
	 */
	private function make_parented_attachments(): array {
		$other = self::factory()->user->create( [ 'role' => 'editor' ] );
		$posts = [
			'published' => self::factory()->post->create(),
			'draft'     => self::factory()->post->create( [ 'post_status' => 'draft' ] ),
			'private'   => self::factory()->post->create(
				[
					'post_status' => 'private',
					'post_author' => $other,
				]
			),
			'trashed'   => self::factory()->post->create(),
		];
		$ids = [ 'unattached' => $this->make_attachment() ];
		foreach ( $posts as $key => $post_id ) {
			$ids[ $key ] = $this->make_attachment( [ 'post_parent' => $post_id ] );
		}
		wp_trash_post( $posts['trashed'] );
		return $ids;
	}

	/**
	 * attached true returns media with a parent post, false media without
	 * one, and omitting it returns both, as before.
	 */
	public function test_attached_filter() {
		$ids = $this->make_parented_attachments();
		$attached = [ $ids['published'], $ids['draft'], $ids['private'], $ids['trashed'] ];
		sort( $attached );

		$with = $this->run_ids( [ 'attached' => true ] );
		sort( $with );
		$this->assertSame( $attached, $with );

		$this->assertSame( [ $ids['unattached'] ], $this->run_ids( [ 'attached' => false ] ) );

		$all = $this->run_ids();
		sort( $all );
		$expected = array_values( $ids );
		sort( $expected );
		$this->assertSame( $expected, $all, 'Without attached, nothing is filtered.' );
	}

	/**
	 * Attached is the parent relationship only, not usage: an unattached
	 * image used in a post's content is still unattached, and an attached
	 * image the post never shows is still attached.
	 */
	public function test_attached_is_not_usage() {
		$unattached_but_used = $this->make_attachment();
		$post                = self::factory()->post->create(
			[
				'post_content' => '<img src="' . esc_url( wp_get_attachment_url( $unattached_but_used ) ) . '" class="wp-image-' . $unattached_but_used . '">',
			]
		);
		$attached_not_used   = $this->make_attachment( [ 'post_parent' => $post ] );

		$this->assertSame( [ $unattached_but_used ], $this->run_ids( [ 'attached' => false ] ) );
		$this->assertSame( [ $attached_not_used ], $this->run_ids( [ 'attached' => true ] ) );
	}

	/**
	 * attached combines with the other filters and with paging, and the
	 * items keep the same fields (no parent post is disclosed).
	 */
	public function test_attached_combines_with_filters_and_paging() {
		$parent = self::factory()->post->create();
		$jpegs  = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$jpegs[] = $this->make_attachment( [ 'post_parent' => $parent ] );
		}
		$pdf = $this->make_attachment(
			[
				'post_parent'    => $parent,
				'post_mime_type' => 'application/pdf',
				'file'           => '2026/10/doc-' . wp_generate_password( 6, false ) . '.pdf',
			]
		);
		$this->make_attachment();

		$page = $this->run_ability(
			[
				'attached'  => true,
				'mime_type' => 'image',
				'per_page'  => 2,
				'page'      => 2,
				'orderby'   => 'id',
				'order'     => 'ASC',
			]
		);

		$this->assertSame( 3, $page['total'] );
		$this->assertSame( 2, $page['total_pages'] );
		$this->assertSame( [ max( $jpegs ) ], wp_list_pluck( $page['items'], 'id' ) );
		$this->assertSame( self::ITEM_KEYS, array_keys( $page['items'][0] ) );
		$this->assertNotContains( $pdf, wp_list_pluck( $page['items'], 'id' ) );
	}

	/**
	 * Core's schema check accepts "true", "false", "1", "0", 1 and 0 for a
	 * boolean; the ability accepts only a real boolean, so a string "false"
	 * can never select attached media.
	 *
	 * @dataProvider data_loose_booleans
	 *
	 * @param mixed $value Value.
	 */
	public function test_attached_requires_a_real_boolean( $value ) {
		$this->make_attachment();

		$result = $this->run_ability( [ 'attached' => $value ] );

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_invalid_input', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Values core treats as booleans that the ability refuses.
	 *
	 * @return array
	 */
	public function data_loose_booleans(): array {
		return [
			'string false' => [ 'false' ],
			'string true'  => [ 'true' ],
			'string 0'     => [ '0' ],
			'integer 1'    => [ 1 ],
		];
	}

	/**
	 * An attachment stored at a given path in the uploads folder.
	 *
	 * @param string $file Path relative to uploads.
	 * @param array  $args Other post arguments.
	 *
	 * @return int
	 */
	private function make_file( string $file, array $args = [] ): int {
		return $this->make_attachment( array_merge( [ 'file' => $file ], $args ) );
	}

	/**
	 * filename matches part of the file name, case-insensitively — not
	 * folders, titles or other fields — and omitting it changes nothing.
	 */
	public function test_filename_filter() {
		$beach    = $this->make_file( '2026/10/sunset-beach.jpg' );
		$mountain = $this->make_file( '2026/10/Sunset-Mountain.JPG' );
		$house    = $this->make_file( '2026/10/beach/house.jpg' );
		$titled   = $this->make_file( '2026/10/other.jpg', [ 'post_title' => 'Sunset beach title' ] );

		$sunset = $this->run_ids( [ 'filename' => 'SUNSET' ] );
		sort( $sunset );
		$this->assertSame( [ $beach, $mountain ], $sunset, 'Case-insensitive; the title is not a file name.' );
		$this->assertSame( [ $beach ], $this->run_ids( [ 'filename' => 'beach' ] ), 'A folder named "beach" does not match.' );
		$this->assertSame( [ $house ], $this->run_ids( [ 'filename' => 'house.jpg' ] ) );
		$this->assertSame( [], $this->run_ids( [ 'filename' => 'desert' ] ) );
		$this->assertSame( [], $this->run_ids( [ 'filename' => '2026' ] ), 'Year/month folders are not matched.' );

		$all = $this->run_ids();
		sort( $all );
		$expected = [ $beach, $mountain, $house, $titled ];
		sort( $expected );
		$this->assertSame( $expected, $all, 'Without filename, nothing is filtered.' );
		$this->assertSame( [ $titled ], $this->run_ids( [ 'search' => 'Sunset beach' ] ), 'search still ignores file names.' );
	}

	/**
	 * % and _ are matched literally, not as SQL wildcards.
	 */
	public function test_filename_wildcards_are_literal() {
		$percent    = $this->make_file( '2026/10/100%-real.jpg' );
		$underscore = $this->make_file( '2026/10/a_b.jpg' );
		$this->make_file( '2026/10/axb.jpg' );
		$this->make_file( '2026/10/plain.jpg' );

		$this->assertSame( [ $percent ], $this->run_ids( [ 'filename' => '%' ] ) );
		$this->assertSame( [ $underscore ], $this->run_ids( [ 'filename' => 'a_b' ] ) );
		$this->assertSame( [ $underscore ], $this->run_ids( [ 'filename' => '_' ] ) );
	}

	/**
	 * filename combines with attached, mime_type, missing_alt and paging,
	 * and items keep the same fields (no path is returned).
	 */
	public function test_filename_combines_with_filters_and_paging() {
		$parent = self::factory()->post->create();
		$match  = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$match[] = $this->make_file( "2026/10/product-{$i}.jpg", [ 'post_parent' => $parent ] );
		}
		$unattached = $this->make_file( '2026/10/product-loose.jpg' );
		$with_alt   = $this->make_file( '2026/10/product-alt.jpg', [ 'post_parent' => $parent, 'alt' => 'Has alt' ] );
		$pdf        = $this->make_file(
			'2026/10/product-sheet.pdf',
			[
				'post_parent'    => $parent,
				'post_mime_type' => 'application/pdf',
			]
		);
		foreach ( array_merge( $match, [ $unattached ] ) as $id ) {
			delete_post_meta( $id, '_wp_attachment_image_alt' );
		}

		$page = $this->run_ability(
			[
				'filename'    => 'product',
				'attached'    => true,
				'mime_type'   => 'image',
				'missing_alt' => true,
				'per_page'    => 2,
				'page'        => 2,
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertSame( 3, $page['total'], 'Only attached JPEGs without alt text whose name contains "product".' );
		$this->assertSame( 2, $page['total_pages'] );
		$this->assertSame( [ max( $match ) ], wp_list_pluck( $page['items'], 'id' ) );
		$this->assertSame( self::ITEM_KEYS, array_keys( $page['items'][0] ) );
		foreach ( [ $unattached, $with_alt, $pdf ] as $excluded ) {
			$this->assertNotContains( $excluded, $this->run_ids( [ 'filename' => 'product', 'attached' => true, 'mime_type' => 'image', 'missing_alt' => true ] ) );
		}
	}

	/**
	 * Attachments the user may not read stay out of filename results and
	 * totals.
	 */
	public function test_filename_respects_read_permission() {
		$visible = $this->make_file( '2026/10/secret-plan.jpg' );
		$hidden  = $this->make_file( '2026/10/secret-hidden.jpg' );

		$result = $this->with_read_denied(
			[ $hidden ],
			function () {
				return $this->run_ability( [ 'filename' => 'secret' ] );
			}
		);

		$this->assertSame( [ $visible ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( 1, $result['total'] );
	}

	/**
	 * Values the ability refuses after the schema check (it trims, and
	 * rejects control characters).
	 *
	 * @dataProvider data_refused_filenames
	 *
	 * @param string $value Value.
	 */
	public function test_filename_refused_by_the_ability( string $value ) {
		$result = $this->run_ability( [ 'filename' => $value ] );

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_invalid_input', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Refused file names.
	 *
	 * @return array
	 */
	public function data_refused_filenames(): array {
		return [
			'spaces only'       => [ '   ' ],
			'tab'               => [ "a\tb" ],
			'NUL byte'          => [ "a\0b" ],
		];
	}

	/**
	 * Paging and the page-size cap.
	 */
	public function test_paging() {
		$this->make_attachment();
		$this->make_attachment();
		$this->make_attachment();

		$first = $this->run_ability( [ 'per_page' => 2 ] );
		$this->assertCount( 2, $first['items'] );
		$this->assertSame( 3, $first['total'] );
		$this->assertSame( 2, $first['total_pages'] );

		$second = $this->run_ability(
			[
				'per_page' => 2,
				'page'     => 2,
			]
		);
		$this->assertCount( 1, $second['items'] );
		$this->assertSame( 2, $second['page'] );

		$beyond = $this->run_ability(
			[
				'per_page' => 2,
				'page'     => 9,
			]
		);
		$this->assertSame( [], $beyond['items'] );
		$this->assertSame( 9, $beyond['page'] );
	}

	/**
	 * The page size is capped at 50 even when a smaller site setting exists.
	 */
	public function test_per_page_maximum_is_accepted() {
		update_option( 'tsmlt_settings', [ 'media_per_page' => 5 ] );
		for ( $i = 0; $i < 7; $i++ ) {
			$this->make_attachment();
		}

		$this->assertCount( 7, $this->run_ids( [ 'per_page' => 50 ] ) );
		$this->assertCount( 7, $this->run_ids() );
	}

	/**
	 * Ordering by ID, title and date, both directions.
	 */
	public function test_ordering() {
		$b = $this->make_attachment(
			[
				'post_title' => 'Bravo',
				'post_date'  => '2024-01-01 00:00:00',
			]
		);
		$a = $this->make_attachment(
			[
				'post_title' => 'Alpha',
				'post_date'  => '2025-01-01 00:00:00',
			]
		);
		$c = $this->make_attachment(
			[
				'post_title' => 'Charlie',
				'post_date'  => '2023-01-01 00:00:00',
			]
		);

		$this->assertSame(
			[ $b, $a, $c ],
			$this->run_ids(
				[
					'orderby' => 'id',
					'order'   => 'ASC',
				]
			)
		);
		$this->assertSame(
			[ $a, $b, $c ],
			$this->run_ids(
				[
					'orderby' => 'title',
					'order'   => 'ASC',
				]
			)
		);
		// Default: newest first.
		$this->assertSame( [ $a, $b, $c ], $this->run_ids() );
		$this->assertSame(
			[ $c, $b, $a ],
			$this->run_ids(
				[
					'orderby' => 'date',
					'order'   => 'ASC',
				]
			)
		);
	}

	/**
	 * Items the user may not read are left out.
	 */
	public function test_items_without_read_post_are_excluded() {
		$visible = $this->make_attachment();
		$hidden  = $this->make_attachment();

		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $hidden ) {
			if ( 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === $hidden ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$ids = $this->run_ids();
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertSame( [ $visible ], $ids );
	}

	/**
	 * total and total_pages count only attachments the user may read, so they
	 * reveal nothing about hidden ones.
	 */
	public function test_totals_exclude_unreadable_attachments() {
		$ids    = $this->make_attachments( 5 );
		$hidden = [ $ids[1], $ids[3] ];

		$result = $this->with_read_denied(
			$hidden,
			function () {
				return $this->run_ability(
					[
						'search'   => 'Permission probe',
						'per_page' => 2,
						'orderby'  => 'id',
						'order'    => 'ASC',
					]
				);
			}
		);

		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 2, $result['total_pages'] );
		$this->assertSame( [ $ids[0], $ids[2] ], wp_list_pluck( $result['items'], 'id' ) );
	}

	/**
	 * Pages are filled from readable attachments only: every page but the
	 * last is full, the pages together list each readable attachment exactly
	 * once in order, and pages past the end are empty.
	 */
	public function test_pages_are_full_and_consistent_with_hidden_attachments() {
		$ids     = $this->make_attachments( 7 );
		$hidden  = [ $ids[0], $ids[2], $ids[5] ];
		$visible = array_values( array_diff( $ids, $hidden ) );

		$pages = $this->with_read_denied(
			$hidden,
			function () {
				$pages = [];
				foreach ( [ 1, 2, 3 ] as $page ) {
					$pages[ $page ] = $this->run_ability(
						[
							'search'   => 'Permission probe',
							'per_page' => 2,
							'page'     => $page,
							'orderby'  => 'id',
							'order'    => 'ASC',
						]
					);
				}
				return $pages;
			}
		);

		$this->assertSame( [ $visible[0], $visible[1] ], wp_list_pluck( $pages[1]['items'], 'id' ) );
		$this->assertSame( [ $visible[2], $visible[3] ], wp_list_pluck( $pages[2]['items'], 'id' ) );
		$this->assertSame( [], $pages[3]['items'] );
		foreach ( $pages as $number => $page ) {
			$this->assertSame( 4, $page['total'], "Page {$number} total" );
			$this->assertSame( 2, $page['total_pages'], "Page {$number} total_pages" );
			$this->assertSame( $number, $page['page'] );
			foreach ( $hidden as $id ) {
				$this->assertNotContains( $id, wp_list_pluck( $page['items'], 'id' ) );
			}
		}
	}

	/**
	 * The response with hidden attachments is identical to the response if
	 * those attachments did not exist, so their number cannot be inferred.
	 */
	public function test_hidden_attachments_are_indistinguishable_from_absent_ones() {
		$ids    = $this->make_attachments( 4 );
		$hidden = [ $ids[1], $ids[2] ];
		$input  = [
			'search'   => 'Permission probe',
			'per_page' => 1,
			'orderby'  => 'id',
			'order'    => 'ASC',
		];

		$with_hidden = $this->with_read_denied(
			$hidden,
			function () use ( $input ) {
				return $this->run_ability( $input );
			}
		);
		foreach ( $hidden as $id ) {
			wp_delete_attachment( $id, true );
		}
		$without = $this->run_ability( $input );

		$this->assertSame( $without, $with_hidden );
		$this->assertSame( 2, $with_hidden['total'] );
		$this->assertSame( 2, $with_hidden['total_pages'] );
	}

	/**
	 * When nothing matching is readable, the totals are zero.
	 */
	public function test_totals_are_zero_when_nothing_is_readable() {
		$ids = $this->make_attachments( 3 );

		$result = $this->with_read_denied(
			$ids,
			function () {
				return $this->run_ability( [ 'search' => 'Permission probe' ] );
			}
		);

		$this->assertSame( [], $result['items'] );
		$this->assertSame( 0, $result['total'] );
		$this->assertSame( 0, $result['total_pages'] );
		$this->assertSame( 1, $result['page'] );
	}

	/**
	 * Filters and permissions combine: the totals count readable attachments
	 * that also match the MIME and missing-alt filters.
	 */
	public function test_totals_combine_filters_and_permissions() {
		$readable_match = $this->make_attachment( [ 'post_title' => 'Permission probe A' ] );
		$hidden_match   = $this->make_attachment( [ 'post_title' => 'Permission probe B' ] );
		$this->make_attachment(
			[
				'post_title'     => 'Permission probe C',
				'post_mime_type' => 'application/pdf',
			]
		);
		$with_alt = $this->make_attachment(
			[
				'post_title' => 'Permission probe D',
				'alt'        => 'Has alt',
			]
		);
		// The plugin copies the title into alt text on upload; clear it for the matches.
		delete_post_meta( $readable_match, '_wp_attachment_image_alt' );
		delete_post_meta( $hidden_match, '_wp_attachment_image_alt' );

		$result = $this->with_read_denied(
			[ $hidden_match ],
			function () {
				return $this->run_ability(
					[
						'search'      => 'Permission probe',
						'mime_type'   => 'image',
						'missing_alt' => true,
					]
				);
			}
		);

		$this->assertSame( [ $readable_match ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 1, $result['total_pages'] );
		$this->assertNotContains( $with_alt, wp_list_pluck( $result['items'], 'id' ) );
	}

	/**
	 * Without any hidden attachments, totals still match WordPress's own count.
	 */
	public function test_totals_match_query_count_when_all_readable() {
		$this->make_attachments( 5 );

		$result = $this->run_ability(
			[
				'search'   => 'Permission probe',
				'per_page' => 2,
			]
		);
		$query  = new WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				's'              => 'Permission probe',
				'posts_per_page' => 2,
			]
		);

		$this->assertSame( (int) $query->found_posts, $result['total'] );
		$this->assertSame( (int) $query->max_num_pages, $result['total_pages'] );
	}

	/**
	 * Unknown properties are rejected, including query arguments.
	 *
	 * @dataProvider data_unknown_properties
	 *
	 * @param array $input Input.
	 */
	public function test_unknown_properties_are_rejected( array $input ) {
		$this->assert_invalid_input( $input );
	}

	/**
	 * @return array
	 */
	public function data_unknown_properties(): array {
		return [
			'post_status'    => [ [ 'post_status' => 'trash' ] ],
			'status'         => [ [ 'status' => 'private' ] ],
			'filtering'      => [ [ 'filtering' => true ] ],
			'post_type'      => [ [ 'post_type' => 'post' ] ],
			'meta_query'     => [ [ 'meta_query' => [] ] ],
			'searchKeyWords' => [ [ 'searchKeyWords' => 'x' ] ],
		];
	}

	/**
	 * Out-of-range and wrongly typed values are rejected.
	 *
	 * @dataProvider data_invalid_values
	 *
	 * @param mixed $input Input.
	 */
	public function test_invalid_values_are_rejected( $input ) {
		$this->assert_invalid_input( $input );
	}

	/**
	 * @return array
	 */
	public function data_invalid_values(): array {
		return [
			'not an object'      => [ 'search' ],
			'search too long'    => [ [ 'search' => str_repeat( 'a', 201 ) ] ],
			'search not string'  => [ [ 'search' => [ 'a' ] ] ],
			'page zero'          => [ [ 'page' => 0 ] ],
			'page negative'      => [ [ 'page' => -1 ] ],
			'page not integer'   => [ [ 'page' => 1.5 ] ],
			'per_page zero'      => [ [ 'per_page' => 0 ] ],
			'per_page above max' => [ [ 'per_page' => 51 ] ],
			'orderby unknown'    => [ [ 'orderby' => 'menu_order' ] ],
			'orderby raw column' => [ [ 'orderby' => 'post_title' ] ],
			'order lowercase'    => [ [ 'order' => 'asc' ] ],
			'order injection'    => [ [ 'order' => 'DESC; DROP TABLE' ] ],
			'mime bad top level' => [ [ 'mime_type' => 'evil' ] ],
			'mime wildcard'      => [ [ 'mime_type' => 'image/*' ] ],
			'mime path chars'    => [ [ 'mime_type' => 'image/../x' ] ],
			'missing_alt string' => [ [ 'missing_alt' => 'maybe' ] ],
			'attached string'    => [ [ 'attached' => 'maybe' ] ],
			'attached array'     => [ [ 'attached' => [ true ] ] ],
			'attached null'      => [ [ 'attached' => null ] ],
			'filename empty'     => [ [ 'filename' => '' ] ],
			'filename too long'  => [ [ 'filename' => str_repeat( 'a', 201 ) ] ],
			'filename folder'    => [ [ 'filename' => '2026/10/x.jpg' ] ],
			'filename backslash' => [ [ 'filename' => 'a\\b' ] ],
			'filename traversal' => [ [ 'filename' => '../wp-config.php' ] ],
			'filename not text'  => [ [ 'filename' => 5 ] ],
		];
	}

	/**
	 * A search at exactly the maximum length is accepted.
	 */
	public function test_search_at_max_length_is_accepted() {
		$result = $this->run_ability( [ 'search' => str_repeat( 'a', 200 ) ] );

		$this->assertIsArray( $result );
		$this->assertSame( [], $result['items'] );
	}
}
