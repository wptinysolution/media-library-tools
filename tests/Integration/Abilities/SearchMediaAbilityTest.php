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
