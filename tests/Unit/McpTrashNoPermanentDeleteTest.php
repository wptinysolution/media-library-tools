<?php
/**
 * Source-level guard: the MCP trash service and the four trash abilities
 * must never call permanent deletion or trash emptying. The files are read
 * with PHP's tokenizer, so comments, docblocks and description text are
 * never mistaken for code; only real calls, method calls and exact
 * callback or hook names in string literals count.
 *
 * @package TinySolutions\mlt
 */

use PHPUnit\Framework\TestCase;

/**
 * McpTrashNoPermanentDeleteTest
 */
class McpTrashNoPermanentDeleteTest extends TestCase {

	/**
	 * Files of the MCP trash implementation, relative to the plugin root.
	 *
	 * @var string[]
	 */
	const FILES = [
		'app/Modules/Trash/MediaTrash.php',
		'app/Abilities/Media/ListTrashedMedia.php',
		'app/Abilities/Media/PreviewTrashMedia.php',
		'app/Abilities/Media/TrashMedia.php',
		'app/Abilities/Media/RestoreMedia.php',
	];

	/**
	 * Functions that delete posts, attachments or files, or empty the trash.
	 *
	 * @var string[]
	 */
	const FUNCTIONS = [ 'wp_delete_post', 'wp_delete_attachment', 'wp_delete_attachment_files', 'wp_delete_file', 'wp_delete_file_from_directory', 'wp_scheduled_delete', 'unlink', 'rmdir' ];

	/**
	 * Methods that run raw deletes or raw SQL ($wpdb->delete(), ->query(),
	 * the query builder's delete()).
	 *
	 * @var string[]
	 */
	const METHODS = [ 'delete', 'query' ];

	/**
	 * Callback or hook names that would reach deletion indirectly
	 * (call_user_func( 'wp_delete_post' ), do_action( 'wp_scheduled_delete' )).
	 *
	 * @var string[]
	 */
	const LITERALS = [ 'wp_delete_post', 'wp_delete_attachment', 'wp_delete_attachment_files', 'wp_delete_file', 'wp_scheduled_delete', 'unlink' ];

	/**
	 * Forbidden uses in PHP source: "line: what".
	 *
	 * @param string $source PHP source.
	 *
	 * @return string[]
	 */
	private static function violations( string $source ): array {
		$tokens = array_values(
			array_filter(
				token_get_all( $source ),
				static function ( $token ) {
					return ! is_array( $token ) || ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true );
				}
			)
		);
		$names = [ T_STRING ];
		foreach ( [ 'T_NAME_FULLY_QUALIFIED', 'T_NAME_QUALIFIED' ] as $constant ) {
			if ( defined( $constant ) ) {
				$names[] = constant( $constant );
			}
		}

		$found = [];
		foreach ( $tokens as $index => $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}
			if ( T_CONSTANT_ENCAPSED_STRING === $token[0] && in_array( strtolower( trim( $token[1], '\'"' ) ), self::LITERALS, true ) ) {
				$found[] = $token[2] . ': string ' . $token[1];
				continue;
			}
			if ( ! in_array( $token[0], $names, true ) || '(' !== ( $tokens[ $index + 1 ] ?? null ) ) {
				continue;
			}
			$name     = strtolower( ltrim( (string) strrchr( '\\' . $token[1], '\\' ), '\\' ) );
			$previous = $tokens[ $index - 1 ] ?? null;
			$member   = is_array( $previous ) && in_array( $previous[0], [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ? T_NULLSAFE_OBJECT_OPERATOR : T_OBJECT_OPERATOR ], true );
			if ( is_array( $previous ) && T_FUNCTION === $previous[0] ) {
				continue;
			}
			if ( $member ? in_array( $name, self::METHODS, true ) : in_array( $name, self::FUNCTIONS, true ) ) {
				$found[] = $token[2] . ': ' . ( $member ? '->' : '' ) . $name . '()';
			}
		}
		return $found;
	}

	/**
	 * No trash file calls permanent deletion, raw deletes or trash emptying.
	 */
	public function test_trash_implementation_never_deletes_permanently() {
		$root = dirname( __DIR__, 2 );
		foreach ( self::FILES as $file ) {
			$this->assertFileExists( $root . '/' . $file );
			$this->assertSame( [], self::violations( (string) file_get_contents( $root . '/' . $file ) ), $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a source file in a test.
		}
	}

	/**
	 * The scanned files are the real implementation: the service makes the
	 * native trash and restore calls, and the abilities go through it.
	 */
	public function test_scan_sees_the_real_calls() {
		$root    = dirname( __DIR__, 2 );
		$service = (string) file_get_contents( $root . '/app/Modules/Trash/MediaTrash.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a source file in a test.
		$this->assertMatchesRegularExpression( '/\bwp_trash_post\(\s*\$attachment_id\s*\)/', $service );
		$this->assertMatchesRegularExpression( '/\bwp_untrash_post\(\s*\$attachment_id\s*\)/', $service );
		$this->assertStringContainsString( 'MediaTrash::trash( $id )', (string) file_get_contents( $root . '/app/Abilities/Media/TrashMedia.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a source file in a test.
		$this->assertStringContainsString( 'MediaTrash::restore( $id )', (string) file_get_contents( $root . '/app/Abilities/Media/RestoreMedia.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a source file in a test.
	}

	/**
	 * The scanner flags real calls in any form and ignores comments,
	 * docblocks, descriptions and similar names.
	 */
	public function test_scanner_has_no_false_positives_or_negatives() {
		$clean = <<<'PHP'
<?php
/**
 * Never calls wp_delete_post() or wp_delete_attachment( $id, true ).
 */
// unlink( $file ); $wpdb->delete( 'posts' );
$text = __( 'Permanent deletion is not available through MCP; wp_delete_post is never used.', 'x' );
add_filter( 'pre_delete_attachment', [ __CLASS__, 'refuse_permanent_delete' ], 1, 2 );
current_user_can( 'delete_post', $id );
delete_option( $name );
wp_trash_post( $id );
$query = new WP_Query( [] );
function delete() {}
PHP;
		$this->assertSame( [], self::violations( $clean ) );

		$cases = [
			'wp_delete_post( $id, true );',
			'\wp_delete_attachment( $id, true );',
			'WP_DELETE_POST( $id );',
			'wp_delete_file( $path );',
			'unlink( $path );',
			'$wpdb->delete( $wpdb->posts, [ "ID" => 1 ] );',
			'$wpdb->query( "DELETE FROM x" );',
			'Fns::DB()->delete();',
			'call_user_func( "wp_delete_post", $id );',
			'do_action( \'wp_scheduled_delete\' );',
			'wp_scheduled_delete();',
		];
		foreach ( $cases as $code ) {
			$this->assertNotEmpty( self::violations( "<?php\n" . $code ), $code );
		}
	}
}
