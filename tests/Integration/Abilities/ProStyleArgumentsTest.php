<?php
/**
 * Tests that the Free abilities refuse arguments that drive Pro-only or
 * admin-only behaviour elsewhere in the plugin — the media table's status
 * filter, EXIF filters, bulk edit by post title, file renaming, groups and
 * CSV-style custom meta — and that nothing runs or changes when they do.
 *
 * Executed through WP_Ability::execute(), the path the MCP Adapter uses, so
 * core input validation runs exactly as it does for an MCP client.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * ProStyleArgumentsTest
 */
class ProStyleArgumentsTest extends WP_UnitTestCase {

	/**
	 * Fixture attachment ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Group (tsmlt_category) term ID the fixture is not assigned to.
	 *
	 * @var int
	 */
	private $term_id;

	/**
	 * What ran while the ability was called.
	 *
	 * @var array<string, array>
	 */
	private $spied = [];

	/**
	 * Spy callbacks, kept so they can be removed.
	 *
	 * @var array<int, array{0: string, 1: callable, 2: int}>
	 */
	private $spies = [];

	/**
	 * Create an administrator, the fixture and the spies.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$parent_id = self::factory()->post->create( [ 'post_title' => 'Parent post' ] );

		$this->id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Probe fixture',
				'post_excerpt'   => 'Original caption',
				'post_content'   => 'Original description',
				'post_name'      => 'probe-fixture',
				'post_parent'    => $parent_id,
				'file'           => '2026/10/probe-fixture.jpg',
			]
		);
		update_post_meta( $this->id, '_wp_attachment_image_alt', 'Original alt' );
		wp_update_attachment_metadata(
			$this->id,
			[
				'width'  => 800,
				'height' => 600,
				'file'   => '2026/10/probe-fixture.jpg',
			]
		);

		$this->term_id = (int) self::factory()->term->create(
			[
				'taxonomy' => Fns::CATEGORY,
				'name'     => 'Probe group',
			]
		);

		$this->spied = [];
		$this->spy( 'wp_before_execute_ability', 'executed', 10, 1 );
		$this->spy( 'tsmlt_attachment_rename_to', 'rename', 10, 1 );
		$this->spy( 'attachment_updated', 'post_update', 10, 1 );
		$this->spy( 'set_object_terms', 'terms', 10, 1 );
		$this->spy( 'added_post_meta', 'meta', 10, 2 );
		$this->spy( 'updated_post_meta', 'meta', 10, 2 );
		$this->spy( 'deleted_post_meta', 'meta', 10, 2 );
	}

	/**
	 * Remove the spies.
	 */
	public function tear_down() {
		foreach ( $this->spies as $spy ) {
			remove_filter( $spy[0], $spy[1], $spy[2] );
		}
		$this->spies = [];
		parent::tear_down();
	}

	/**
	 * Record calls to a hook under a label. Filters get their value back.
	 *
	 * @param string $hook     Hook name.
	 * @param string $label    Label to record under.
	 * @param int    $priority Priority.
	 * @param int    $args     Accepted arguments.
	 */
	private function spy( string $hook, string $label, int $priority, int $args ) {
		$callback = function ( ...$received ) use ( $hook, $label ) {
			$this->spied[ $label ][] = [ $hook, $received ];
			return $received[0] ?? null;
		};
		add_filter( $hook, $callback, $priority, $args );
		$this->spies[] = [ $hook, $callback, $priority ];
	}

	/**
	 * Everything about the fixture an ability could change.
	 *
	 * @return array
	 */
	private function snapshot(): array {
		clean_post_cache( $this->id );
		wp_cache_delete( $this->id, 'post_meta' );
		$post = get_post( $this->id );
		$meta = get_post_meta( $this->id );
		unset( $meta['_edit_lock'], $meta['_edit_last'] );
		ksort( $meta );

		return [
			'post_title'     => $post->post_title,
			'post_excerpt'   => $post->post_excerpt,
			'post_content'   => $post->post_content,
			'post_name'      => $post->post_name,
			'post_status'    => $post->post_status,
			'post_parent'    => $post->post_parent,
			'post_mime_type' => $post->post_mime_type,
			'guid'           => $post->guid,
			'attached_file'  => get_attached_file( $this->id ),
			'metadata'       => wp_get_attachment_metadata( $this->id ),
			'meta'           => $meta,
			'groups'         => wp_get_object_terms( $this->id, Fns::CATEGORY, [ 'fields' => 'ids' ] ),
		];
	}

	/**
	 * Replace placeholders with fixture IDs.
	 *
	 * @param array $input Input with `{id}` / `{term}` placeholders.
	 *
	 * @return array
	 */
	private function resolve( array $input ): array {
		array_walk_recursive(
			$input,
			function ( &$value ) {
				if ( '{id}' === $value ) {
					$value = $this->id;
				} elseif ( '{term}' === $value ) {
					$value = $this->term_id;
				}
			}
		);
		return $input;
	}

	/**
	 * Valid input for each ability.
	 *
	 * The update baseline writes a caption, so if an extra property were
	 * silently ignored rather than refused, the caption change would show.
	 *
	 * @return array<string, array>
	 */
	private static function baselines(): array {
		return [
			'tsmlt/search-media'          => [
				'search'   => 'Probe fixture',
				'per_page' => 5,
			],
			'tsmlt/get-media-details'     => [ 'id' => '{id}' ],
			'tsmlt/update-media-metadata' => [
				'id'      => '{id}',
				'caption' => 'Changed by a Pro-style probe',
			],
		];
	}

	/**
	 * Arguments that select Pro-only or admin-only behaviour elsewhere.
	 *
	 * @return array<string, mixed>
	 */
	private static function pro_style_properties(): array {
		return [
			'bulkEditPostTitle'         => [ 'post_title', 'alt_text', 'caption', 'post_description' ],
			'newname'                   => 'renamed-by-probe',
			'post_categories'           => [ '{term}' ],
			'custom_meta:_photographer' => 'Probe photographer',
			'exif_camera'               => 'Probe camera',
			'exif_has_gps'              => 'yes',
			'status'                    => 'trash',
			'filtering'                 => true,
		];
	}

	/**
	 * Execute an ability as the current user.
	 *
	 * @param string $name  Ability name.
	 * @param array  $input Input.
	 *
	 * @return mixed
	 */
	private function run_ability( string $name, array $input ) {
		$ability = wp_get_ability( $name );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Each baseline is valid on its own, so a rejection below is caused by the
	 * added property alone.
	 *
	 * @dataProvider data_abilities
	 *
	 * @param string $name Ability name.
	 */
	public function test_baseline_is_accepted( string $name ) {
		$result = $this->run_ability( $name, $this->resolve( self::baselines()[ $name ] ) );

		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		// The execution spy works: it saw exactly this ability run.
		$executed = array_map(
			static function ( $call ) {
				return $call[1][0];
			},
			$this->spied['executed'] ?? []
		);
		$this->assertSame( [ $name ], $executed );
	}

	/**
	 * @return array
	 */
	public function data_abilities(): array {
		$cases = [];
		foreach ( array_keys( self::baselines() ) as $name ) {
			$cases[ $name ] = [ $name ];
		}
		return $cases;
	}

	/**
	 * A Pro-style property is refused by input validation, before the ability
	 * runs, and nothing about the attachment changes.
	 *
	 * @dataProvider data_pro_style_arguments
	 *
	 * @param string $name     Ability name.
	 * @param string $property Unexpected property.
	 * @param mixed  $value    Its value.
	 */
	public function test_pro_style_argument_is_rejected( string $name, string $property, $value ) {
		$input              = self::baselines()[ $name ];
		$input[ $property ] = $value;
		$input              = $this->resolve( $input );
		$before             = $this->snapshot();

		$result = $this->run_ability( $name, $input );

		// 1. Rejected by core schema validation, naming the property.
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), $result->get_error_message() );
		$this->assertStringContainsString( $property, $result->get_error_message() );

		// 2. The attachment and its metadata are unchanged.
		$this->assertSame( $before, $this->snapshot() );

		// 3. Nothing ran: not the ability, a rename, a post update, a group
		// change or any meta write on the attachment.
		$this->assertArrayNotHasKey( 'executed', $this->spied, 'The ability callback must not run.' );
		$this->assertArrayNotHasKey( 'rename', $this->spied, 'No rename may be attempted.' );
		$this->assertArrayNotHasKey( 'post_update', $this->spied, 'No post update may run.' );
		$this->assertArrayNotHasKey( 'terms', $this->spied, 'No group may be assigned.' );
		foreach ( $this->spied['meta'] ?? [] as $call ) {
			$this->assertNotSame( $this->id, (int) $call[1][1], 'No meta of the attachment may be written: ' . $call[0] );
		}
	}

	/**
	 * Every ability crossed with every Pro-style property.
	 *
	 * @return array
	 */
	public function data_pro_style_arguments(): array {
		$cases = [];
		foreach ( array_keys( self::baselines() ) as $name ) {
			foreach ( self::pro_style_properties() as $property => $value ) {
				$cases[ $name . ' + ' . $property ] = [ $name, $property, $value ];
			}
		}
		return $cases;
	}
}
