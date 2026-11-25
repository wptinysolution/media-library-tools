<?php
/**
 * Rename strategies in tsmlt/preview-media-rename and tsmlt/rename-media,
 * Free side (Pro is not active in this suite; the Pro suite's
 * RenameStrategyMcpProTest covers the names Pro makes).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpAccess;
use TinySolutions\mlt\Abilities\Media\PreviewMediaRename;
use TinySolutions\mlt\Modules\Rename\McpRename;
use TinySolutions\mlt\Modules\Rename\RenameJob;

/**
 * RenameStrategyMcpTest
 */
class RenameStrategyMcpTest extends WP_UnitTestCase {

	/**
	 * Attachments made.
	 *
	 * @var int[]
	 */
	private $ids = [];

	/**
	 * Temporary files.
	 *
	 * @var string[]
	 */
	private $files = [];

	/**
	 * Administrator; Pro not active.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/rename-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$this->assertFalse( tsmlt()->has_pro(), 'Precondition: Pro is not active in this suite.' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RenameJob::STATE_OPTION );
		McpAccess::set_disabled( false );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		McpAccess::set_disabled( false );
		delete_option( RenameJob::STATE_OPTION );
		foreach ( $this->ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		parent::tear_down();
	}

	/**
	 * A JPEG attachment, optionally attached to a post.
	 *
	 * @param string $name   File name part.
	 * @param int    $parent Parent post ID.
	 *
	 * @return int
	 */
	private function jpeg( string $name, int $parent = 0 ): int {
		$temp = trailingslashit( get_temp_dir() ) . 'mcpstrat-' . $name . '.jpg';
		file_put_contents( $temp, file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$this->files[] = $temp;
		$id            = (int) self::factory()->attachment->create_upload_object( $temp, $parent );
		$this->ids[]   = $id;
		return $id;
	}

	/**
	 * Run the preview.
	 *
	 * @param array $items Items.
	 *
	 * @return array|WP_Error
	 */
	private static function preview( array $items ) {
		return wp_get_ability( 'tsmlt/preview-media-rename' )->execute( [ 'items' => $items ] );
	}

	/**
	 * Every strategy is accepted as input; without Pro each item is
	 * pro_required and no token is issued.
	 */
	public function test_strategies_need_pro() {
		$id = $this->jpeg( 'nopro', self::factory()->post->create( [ 'post_title' => 'Summer Sale' ] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Red shoe' );
		foreach ( array_keys( McpRename::STRATEGIES ) as $strategy ) {
			$result = self::preview(
				[
					[
						'id'       => $id,
						'strategy' => $strategy,
					],
				]
			);
			$this->assertIsArray( $result, $strategy );
			$this->assertSame( 'pro_required', $result['items'][0]['status'], $strategy );
			$this->assertSame( $strategy, $result['items'][0]['strategy'] );
			$this->assertSame( '', $result['items'][0]['requested_filename'] );
			$this->assertNull( $result['confirmation_token'], $strategy );
			$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( 'tsmlt/preview-media-rename' )->get_output_schema(), 'result' ), $strategy );
		}
		$this->assertSame( [ 'post_title', 'sku', 'alt_text' ], wp_get_ability( 'tsmlt/preview-media-rename' )->get_input_schema()['properties']['items']['items']['properties']['strategy']['enum'] );
	}

	/**
	 * filename or strategy, exactly one; only the three strategies.
	 */
	public function test_input_rules() {
		$id      = $this->jpeg( 'input' );
		$invalid = [
			[
				'id'       => $id,
				'strategy' => 'ai',
			],
			[
				'id'       => $id,
				'strategy' => 'POST_TITLE',
			],
			[
				'id'       => $id,
				'strategy' => [ 'post_title' ],
			],
			[
				'id'       => $id,
				'filename' => 'new-name',
				'strategy' => 'post_title',
			],
			[ 'id' => $id ],
			[
				'id'      => $id,
				'pattern' => '{title}',
			],
		];
		foreach ( $invalid as $item ) {
			// The tool's own check, and the Abilities API's schema check before it.
			$error = PreviewMediaRename::instance()->execute( [ 'items' => [ $item ] ] );
			$this->assertWPError( $error, wp_json_encode( $item ) );
			$this->assertSame( 'tsmlt_invalid_input', $error->get_error_code(), wp_json_encode( $item ) );
			$this->assertWPError( self::preview( [ $item ] ), wp_json_encode( $item ) );
		}
	}

	/**
	 * Typed names work as before, alongside strategy items, and their token
	 * records no strategy.
	 */
	public function test_typed_names_unchanged() {
		$id     = $this->jpeg( 'typed' );
		$other  = $this->jpeg( 'typed-other' );
		$result = self::preview(
			[
				[
					'id'       => $id,
					'filename' => 'mcpstrat-typed-new',
				],
				[
					'id'       => $other,
					'strategy' => 'alt_text',
				],
			]
		);
		$this->assertSame( [ 'ready', 'pro_required' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( [ '', 'alt_text' ], array_column( $result['items'], 'strategy' ) );
		$this->assertSame( 'mcpstrat-typed-new', $result['items'][0]['requested_filename'] );
		$this->assertSame(
			[
				[
					'id'       => $id,
					'name'     => 'mcpstrat-typed-new',
					'strategy' => '',
				],
			],
			McpRename::consume_token( $result['confirmation_token'] )['items']
		);
	}

	/**
	 * Previewing a strategy never touches the attached post, whether the
	 * stored parent is missing, deleted or real (the Rename page's handler
	 * looks a parent up and saves it; the preview does not).
	 */
	public function test_preview_never_changes_the_parent() {
		$post     = self::factory()->post->create( [ 'post_title' => 'Owner' ] );
		$attached = $this->jpeg( 'attached', $post );
		$loose    = $this->jpeg( 'loose' );
		$orphan   = $this->jpeg( 'orphan', self::factory()->post->create() );
		wp_delete_post( (int) get_post_field( 'post_parent', $orphan ), true );
		// The loose image is the featured image of a post: the Rename page
		// would attach it there.
		set_post_thumbnail( self::factory()->post->create(), $loose );

		$before = [];
		foreach ( [ $attached, $loose, $orphan ] as $id ) {
			clean_post_cache( $id );
			$before[ $id ] = (int) get_post_field( 'post_parent', $id );
		}
		$this->assertSame( $post, $before[ $attached ] );
		$this->assertSame( 0, $before[ $loose ], 'Precondition: not attached.' );
		foreach ( array_keys( McpRename::STRATEGIES ) as $strategy ) {
			self::preview(
				array_map(
					static function ( $id ) use ( $strategy ) {
						return [
							'id'       => $id,
							'strategy' => $strategy,
						];
					},
					array_keys( $before )
				)
			);
		}
		foreach ( $before as $id => $parent ) {
			clean_post_cache( $id );
			$this->assertSame( $parent, (int) get_post_field( 'post_parent', $id ), "Parent of $id unchanged." );
		}
	}

	/**
	 * A token made for a strategy while Pro was active is refused at
	 * execution once Pro is not: nothing is renamed, and the name is never
	 * made again.
	 */
	public function test_execution_needs_pro_for_strategy_items() {
		$id    = $this->jpeg( 'exec' );
		$file  = get_attached_file( $id );
		$token = McpRename::issue_token(
			[
				$id => [
					'name'     => 'Summer Sale',
					'strategy' => 'post_title',
				],
			]
		);

		$result = wp_get_ability( 'tsmlt/rename-media' )->execute(
			[
				'confirmation_token' => $token['token'],
				'confirm'            => true,
			]
		);
		$this->assertSame( 'none', $result['mode'], wp_json_encode( $result ) );
		$this->assertSame( 'pro_required', $result['items'][0]['status'] );
		$this->assertSame( $file, get_attached_file( $id ), 'Not renamed.' );
		$this->assertFileExists( $file );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( 'tsmlt/rename-media' )->get_output_schema(), 'result' ) );
		$this->assertWPError( McpRename::consume_token( $token['token'] ), 'The token was used up.' );
	}

	/**
	 * Permissions and the access switch apply to strategy items too.
	 */
	public function test_permissions_and_access_switch() {
		$id   = $this->jpeg( 'perm' );
		$item = [
			[
				'id'       => $id,
				'strategy' => 'alt_text',
			],
		];
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertNotTrue( wp_get_ability( 'tsmlt/preview-media-rename' )->check_permissions( [ 'items' => $item ] ) );
		$this->assertWPError( self::preview( $item ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		McpAccess::set_disabled( true );
		$this->assertNotTrue( wp_get_ability( 'tsmlt/preview-media-rename' )->check_permissions( [ 'items' => $item ] ) );
		$this->assertSame( 'tsmlt_mcp_disabled', PreviewMediaRename::instance()->execute( [ 'items' => $item ] )->get_error_code() );
	}
}
