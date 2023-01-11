<?php
/**
 * Main ActionHooks class.
 *
 * @package RadiusTheme\SB
 */

namespace RadiusTheme\SB\Controllers\Hooks;

use RadiusTheme\SB\Helpers\Fns;

defined( 'ABSPATH' ) || exit();

/**
 * Main ActionHooks class.
 */
class ActionHooks {
	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {
		add_action( 'wp_head', [ __CLASS__, 'og_metatags_for_sharing' ], 1 );
	}

	/**
	 * Meta tags for social sharing.
	 *
	 * @return void
	 */
	public static function og_metatags_for_sharing() {
		global $post;

		if ( ! isset( $post ) ) {
			return;
		}

		if ( ! is_singular( 'product' ) ) {
			return;
		}

		Fns::print_html( '<meta property="og:url" content="' . get_the_permalink() . '" />', true );
		Fns::print_html( '<meta property="og:type" content="article" />', true );
		Fns::print_html( '<meta property="og:title" content="' . $post->post_title . '" />', true );
		Fns::print_html( '<meta property="og:description" content="' . wp_trim_words( $post->post_content, 150 ) . '" />' );

		$attachment = get_the_post_thumbnail_url();

		if ( ! empty( $attachment ) ) {
			Fns::print_html( '<meta property="og:image" content="' . $attachment . '" />', true );
		}

		Fns::print_html( '<meta property="og:site_name" content="' . get_bloginfo( 'name' ) . '" />', true );
		Fns::print_html( '<meta name="twitter:card" content="summary" />', true );
	}
}
