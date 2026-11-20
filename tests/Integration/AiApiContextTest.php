<?php
/**
 * The context AiApi sends with a generation request includes the image's
 * current alt text from `_wp_attachment_image_alt`, and leaves the rest of
 * the context as it was.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\AI\AiApi;

/**
 * AiApiContextTest
 */
class AiApiContextTest extends WP_UnitTestCase {

	/**
	 * Fixed site name and tagline.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'blogname', 'Test Site' );
		update_option( 'blogdescription', 'Just testing' );
	}

	/**
	 * AiApi::build_context() for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 *
	 * @return string
	 */
	private static function context( int $attachment_id, string $file_path ): string {
		$method = new ReflectionMethod( AiApi::class, 'build_context' );
		if ( PHP_VERSION_ID < 80100 ) {
			// Needed before PHP 8.1 to call a private method; deprecated since 8.5.
			$method->setAccessible( true );
		}
		return (string) $method->invoke( new AiApi(), $attachment_id, $file_path );
	}

	/**
	 * An attachment with a parent post.
	 *
	 * @return int
	 */
	private function attachment(): int {
		$parent = (int) self::factory()->post->create(
			[
				'post_title'   => 'Parent Post',
				'post_content' => 'Parent body text.',
			]
		);
		return (int) self::factory()->attachment->create_object(
			[
				'file'           => '2026/11/bike.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Bike title',
				'post_excerpt'   => 'Bike caption',
				'post_content'   => 'Bike description',
				'post_parent'    => $parent,
			]
		);
	}

	/**
	 * The current alt text is part of the context.
	 */
	public function test_includes_current_alt_text() {
		$id = $this->attachment();
		update_post_meta( $id, '_wp_attachment_image_alt', 'A red bicycle' );

		$this->assertStringContainsString( ' Current alt text: "A red bicycle".', self::context( $id, '/uploads/2026/11/bike.jpg' ) );
	}

	/**
	 * Without alt text, or with an empty one, there is no alt line.
	 */
	public function test_missing_or_empty_alt_text() {
		$id = $this->attachment();
		// The plugin fills alt text in on upload; remove it for this case.
		delete_post_meta( $id, '_wp_attachment_image_alt' );
		$this->assertStringNotContainsString( 'Current alt text', self::context( $id, '/uploads/2026/11/bike.jpg' ) );

		update_post_meta( $id, '_wp_attachment_image_alt', '' );
		$this->assertStringNotContainsString( 'Current alt text', self::context( $id, '/uploads/2026/11/bike.jpg' ) );
	}

	/**
	 * The whole context, in order: unchanged apart from the alt line.
	 */
	public function test_full_context_structure() {
		$id = $this->attachment();
		update_post_meta( $id, '_wp_attachment_image_alt', 'A red bicycle' );

		$this->assertSame(
			' Context: Site: "Test Site". Tagline: "Just testing". Filename: "bike.jpg". Current title: "Bike title". Current alt text: "A red bicycle". Current caption: "Bike caption". Attached to post: "Parent Post". Parent content summary: "Parent body text.".',
			self::context( $id, '/uploads/2026/11/bike.jpg' )
		);
	}
}
