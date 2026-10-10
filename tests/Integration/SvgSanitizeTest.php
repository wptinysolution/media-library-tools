<?php
/**
 * Security regression tests for SVG upload sanitization.
 *
 * Exercises the plugin's real integration — FilterHooks::sanitize_svg(), the
 * `wp_handle_upload_prefilter` callback — with the proof-of-concept inputs from
 * the enshrined/svg-sanitize advisories fixed in 1.0.0, plus the restrictions the
 * plugin already applied (opt-in SVG support, file-size limit, non-SVG
 * passthrough). See tests/fixtures/svg/README.md for fixture provenance.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Hooks\FilterHooks;
use TinySolutions\mlt\Helpers\Fns;

/**
 * SvgSanitizeTest
 */
class SvgSanitizeTest extends WP_UnitTestCase {

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $tmp_files = [];

	/**
	 * Remove temporary files.
	 */
	public function tear_down() {
		foreach ( $this->tmp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		$this->tmp_files = [];
		parent::tear_down();
	}

	/**
	 * Read a fixture.
	 *
	 * @param string $name File name in tests/fixtures/svg.
	 *
	 * @return string
	 */
	private function fixture( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/svg/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Build an upload array, as WordPress passes it to `wp_handle_upload_prefilter`.
	 *
	 * @param string $contents File contents.
	 * @param string $type     MIME type reported for the upload.
	 *
	 * @return array
	 */
	private function upload( string $contents, string $type = 'image/svg+xml' ): array {
		$tmp = wp_tempnam( 'tsmlt-svg-test' );
		file_put_contents( $tmp, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->tmp_files[] = $tmp;

		return [
			'name'     => 'upload.svg',
			'type'     => $type,
			'tmp_name' => $tmp,
			'size'     => strlen( $contents ),
			'error'    => 0,
		];
	}

	/**
	 * Run the plugin's sanitizer; fail the test on any exception or PHP error.
	 *
	 * @param array $file Upload array.
	 *
	 * @return array{0: array, 1: string} Filtered upload array and the file contents afterwards.
	 */
	private function sanitize( array $file ): array {
		try {
			$result = FilterHooks::sanitize_svg( $file );
		} catch ( \Throwable $e ) {
			$this->fail( 'sanitize_svg() threw ' . get_class( $e ) . ': ' . $e->getMessage() );
		}
		return [ $result, (string) file_get_contents( $file['tmp_name'] ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Assert the upload was refused as unsafe.
	 *
	 * @param array $result Filtered upload array.
	 */
	private function assert_rejected( array $result ) {
		$this->assertSame( 'This SVG file contains unsafe content and cannot be uploaded.', $result['error'] ?? null );
	}

	/**
	 * Assert the upload was accepted and the written file is well-formed SVG.
	 *
	 * @param array  $result Filtered upload array.
	 * @param string $output File contents after sanitizing.
	 */
	private function assert_accepted( array $result, string $output ) {
		$this->assertSame( 0, $result['error'], 'Upload should not carry an error.' );
		$previous = libxml_use_internal_errors( true );
		$this->assertNotFalse( simplexml_load_string( $output ), 'Sanitized output must be well-formed XML.' );
		libxml_use_internal_errors( $previous );
		$this->assertStringContainsString( '<svg', $output );
	}

	// ---------------------------------------------------------------------
	// Normal input
	// ---------------------------------------------------------------------

	/**
	 * A normal SVG is accepted; dangerous parts are removed and the drawing kept.
	 */
	public function test_valid_svg_is_accepted_and_cleaned() {
		$svg = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" onload="alert(1)">'
			. '<!-- comment --><script>alert(2)</script>'
			. '<rect id="r" width="10" height="10" fill="#c00" onclick="alert(3)"/>'
			. '<circle cx="5" cy="5" r="4"/>'
			. '<use xlink:href="#r" x="10"/>'
			. '<image href="https://evil.example/x.png" width="1" height="1"/>'
			. '<a xlink:href="javascript:alert(4)"><text>t</text></a>'
			. '</svg>';

		list( $result, $output ) = $this->sanitize( $this->upload( $svg ) );

		$this->assert_accepted( $result, $output );
		$this->assertStringContainsString( '<rect', $output );
		$this->assertStringContainsString( '<circle', $output );
		$this->assertStringContainsString( '<use', $output, 'Local references must survive.' );
		foreach ( [ '<script', 'onload', 'onclick', 'alert(', 'javascript:', 'evil.example', '<?xml' ] as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $output, $forbidden );
		}
	}

	/**
	 * A typical editor export with a PUBLIC DOCTYPE (no custom entities) is still
	 * accepted; 1.0.0 strips the DOCTYPE instead of rejecting the file.
	 */
	public function test_public_doctype_without_entities_is_accepted() {
		$svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
			. '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';

		list( $result, $output ) = $this->sanitize( $this->upload( $svg ) );

		$this->assert_accepted( $result, $output );
		$this->assertStringContainsString( '<rect', $output );
		$this->assertStringNotContainsStringIgnoringCase( '<!DOCTYPE', $output );
	}

	// ---------------------------------------------------------------------
	// Advisory proofs of concept
	// ---------------------------------------------------------------------

	/**
	 * GHSA-9rjx-3jch-6vjf (CVE-2026-107380): a custom entity colliding with an HTML5
	 * named character reference smuggled `javascript:` past the href check. With
	 * 0.22.0 the output kept `&Tab;javascript:alert(1)`; now the upload is refused.
	 */
	public function test_ghsa_9rjx_entity_href_bypass_is_rejected() {
		$file     = $this->upload( $this->fixture( 'entityHrefBypassTest.svg' ) );
		$original = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		list( $result, $output ) = $this->sanitize( $file );

		$this->assert_rejected( $result );
		// Nothing was written back: WordPress aborts the upload on the error.
		$this->assertSame( $original, $output );
	}

	/**
	 * GHSA-v383-3rw5-q8rf (CVE-2026-107379): a DTD `#FIXED` attribute default crashed
	 * PHP (SIGABRT) on older libxml. The DTD is now stripped before parsing, so the
	 * attribute never materialises. On modern libxml this guards against regression.
	 */
	public function test_ghsa_v383_attlist_fixed_default_is_removed_without_crashing() {
		list( $result, $output ) = $this->sanitize( $this->upload( $this->fixture( 'attlistFixedDosTest.svg' ) ) );

		$this->assert_accepted( $result, $output );
		$this->assertStringContainsString( '<rect', $output );
		$this->assertStringNotContainsString( 'badhref', $output );
		$this->assertStringNotContainsString( 'javascript:', $output );
		$this->assertStringNotContainsStringIgnoringCase( 'ATTLIST', $output );
		$this->assertStringNotContainsStringIgnoringCase( '<!DOCTYPE', $output );
	}

	/**
	 * GHSA-m9xh-6747-9r6f (CVE-2026-107381): a `<use>` nesting bomb written with
	 * mixed-case href attribute names was invisible to the nesting check and came
	 * back intact (201 `<use>` elements with 0.22.0). It must now be neutralised
	 * exactly like the canonical-case bomb.
	 *
	 * @dataProvider data_href_attribute_spellings
	 *
	 * @param string $attribute Attribute name replacing `xlink:href`.
	 */
	public function test_ghsa_m9xh_mixed_case_use_nesting_bomb_is_neutralised( string $attribute ) {
		$bomb = str_replace( 'xlink:href', $attribute, $this->fixture( 'useDosTest.svg' ) );
		$this->assertSame( 201, substr_count( $bomb, '<use' ), 'Fixture sanity check.' );

		$start                   = microtime( true );
		list( $result, $output ) = $this->sanitize( $this->upload( $bomb ) );

		$this->assert_accepted( $result, $output );
		$this->assertSame( 0, substr_count( $output, '<use' ), 'Every nested <use> must be removed.' );
		$this->assertLessThan( 5, microtime( true ) - $start, 'Sanitizing must not hang.' );
	}

	/**
	 * @return array
	 */
	public function data_href_attribute_spellings(): array {
		return [
			'canonical xlink:href (control)' => [ 'xlink:href' ],
			'xlink:HrEf'                     => [ 'xlink:HrEf' ],
			'xlink:HREF'                     => [ 'xlink:HREF' ],
		];
	}

	// ---------------------------------------------------------------------
	// Robustness
	// ---------------------------------------------------------------------

	/**
	 * Hostile and malformed inputs never throw, never leak file contents or
	 * scripts, and always end in either a refusal or a clean SVG.
	 *
	 * @dataProvider data_hostile_inputs
	 *
	 * @param string $svg Input.
	 */
	public function test_hostile_inputs_fail_safe( string $svg ) {
		list( $result, $output ) = $this->sanitize( $this->upload( $svg ) );

		if ( 0 === $result['error'] ) {
			$this->assert_accepted( $result, $output );
			foreach ( [ 'root:', 'javascript:', '<script', 'onload', 'LOL-LOL-LOL' ] as $forbidden ) {
				$this->assertStringNotContainsString( $forbidden, $output, $forbidden );
			}
		} else {
			$this->assert_rejected( $result );
		}
	}

	/**
	 * @return array
	 */
	public function data_hostile_inputs(): array {
		return [
			'external entity (XXE)'     => [ '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>' ],
			'entity expansion (laughs)' => [ '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "LOL-LOL-LOL"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;"><!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;">]><svg xmlns="http://www.w3.org/2000/svg"><text>&c;</text></svg>' ],
			'DTD hidden in comment'     => [ '<?xml version="1.0"?><!DOCTYPE svg [<!-- ] --><!ATTLIST svg onload CDATA #FIXED "alert(1)">]><svg xmlns="http://www.w3.org/2000/svg"/>' ],
			'malformed XML'             => [ '<svg xmlns="http://www.w3.org/2000/svg"><rect></svg' ],
			'empty file'                => [ '' ],
			'not XML at all'            => [ '<?php echo "x"; ?>' ],
			'script in CDATA'           => [ '<svg xmlns="http://www.w3.org/2000/svg"><script><![CDATA[alert(1)]]></script></svg>' ],
		];
	}

	// ---------------------------------------------------------------------
	// Existing restrictions are unchanged
	// ---------------------------------------------------------------------

	/**
	 * The 500 KB limit still applies before any parsing, and remains filterable.
	 */
	public function test_file_size_limit_is_unchanged() {
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
		$file = $this->upload( $svg );

		$file['size']            = 500 * 1024 + 1;
		list( $result, $output ) = $this->sanitize( $file );
		$this->assertSame( 'The uploaded SVG exceeds the maximum allowed file size of 500KB.', $result['error'] );
		$this->assertSame( $svg, $output, 'An oversized file is not parsed or rewritten.' );

		$file['size'] = 500 * 1024;
		$this->assertSame( 0, $this->sanitize( $file )[0]['error'], 'Exactly 500KB is still allowed.' );

		$limit = static function () {
			return 10;
		};
		add_filter( 'tsmlt_upload_max_svg_file_size', $limit );
		$small = $this->sanitize( $this->upload( $svg ) )[0];
		remove_filter( 'tsmlt_upload_max_svg_file_size', $limit );
		$this->assertStringContainsString( 'exceeds the maximum allowed file size', $small['error'] );
	}

	/**
	 * Non-SVG uploads pass through untouched.
	 */
	public function test_non_svg_uploads_are_untouched() {
		$png  = "\x89PNG\r\n\x1a\n<svg onload=alert(1)>";
		$file = $this->upload( $png, 'image/png' );

		list( $result, $output ) = $this->sanitize( $file );

		$this->assertSame( $file, $result );
		$this->assertSame( $png, $output );
	}

	/**
	 * SVG uploads stay opt-in: unless the site owner enables SVG support, WordPress
	 * does not allow the file type at all.
	 */
	public function test_svg_support_remains_opt_in() {
		delete_option( 'tsmlt_settings' );

		$this->assertFalse( Fns::is_support_mime_type( 'svg' ) );
		$this->assertNotContains( 'image/svg+xml', get_allowed_mime_types() );
		$this->assertFalse( wp_check_filetype( 'upload.svg' )['type'] );

		update_option( 'tsmlt_settings', [ 'others_file_support' => [ 'svg' ] ] );
		$this->assertTrue( Fns::is_support_mime_type( 'svg' ) );
		$this->assertSame( 'image/svg+xml', FilterHooks::add_support_mime_types( [] )['svg|svgz'] );
	}
}
