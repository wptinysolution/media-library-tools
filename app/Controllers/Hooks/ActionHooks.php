<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use TinySolutions\mlt\Helpers\Fns;

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
        add_action( 'manage_media_custom_column', [ __CLASS__, 'display_column_value' ], 10, 2 );
		add_action( 'add_attachment', [ __CLASS__, 'add_image_info_to' ]  );
	}

	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public static function add_image_info_to( $post_id ) {
		$options = Fns::get_options();
		$image_title = get_the_title( $post_id ) ;

		if( ! empty( $options['default_alt_text'] ) && 'image_name_to_alt' === $options['default_alt_text'] ){
			update_post_meta( $post_id, '_wp_attachment_image_alt', $image_title );
		} elseif ( ! empty( $options['media_default_alt'] ) && 'custom_text_to_alt' === $options['default_alt_text'] ){
			update_post_meta( $post_id, '_wp_attachment_image_alt', $options['media_default_alt'] );
		}

	}
    /**
     * @param $column
     * @param $post_id
     * @return void
     */
    public static function display_column_value( $column, $post_id ) {
        $image = Fns::wp_get_attachment( $post_id );
        switch ( $column ) {
            case 'alt':
                echo esc_html( wp_strip_all_tags( $image['alt'] ) );
                break;
            case 'caption':
                echo esc_html( $image['caption'] );
                break;
            case 'description':
                echo esc_html( $image['description'] );
                break;
            case 'category':
                $taxonomy_object = get_taxonomy( tsmlt()->category );

                if ( $terms = get_the_terms( $post_id, tsmlt()->category ) ) {
                    $out = array();
                    foreach ( $terms as $t ) {
                        $posts_in_term_qv = array();
                        $posts_in_term_qv['post_type'] = get_post_type($post_id);

                        if ( $taxonomy_object->query_var ) {
                            $posts_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
                        } else {
                            $posts_in_term_qv['taxonomy'] = tsmlt()->category;
                            $posts_in_term_qv['term'] = $t->slug;
                        }

                        $out[] = sprintf( '<a href="%s">%s</a>',
                            esc_url( add_query_arg( $posts_in_term_qv, 'upload.php' ) ),
                            esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, tsmlt()->category, 'display' ) )
                        );
                    }

                    /* translators: used between list items, there is a space after the comma */
                    echo join( __( ', ' ), $out );
                };
                break;
            default:
                break;
        }
    }


}
