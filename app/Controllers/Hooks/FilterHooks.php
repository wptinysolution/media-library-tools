<?php
/**
 * Main FilterHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use TinySolutions\mlt\Helpers\Fns;

defined( 'ABSPATH' ) || exit();

/**
 * Main FilterHooks class.
 */
class FilterHooks {
	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {
        // Plugins Setting Page.
        add_filter( 'plugin_action_links_' . TSMLT_BASENAME,  [ __CLASS__, 'plugins_setting_links' ] );
        add_filter( 'manage_media_columns', [ __CLASS__, 'media_custom_column' ] );
        add_filter( 'manage_upload_sortable_columns', [ __CLASS__,  'media_sortable_columns' ] );
        add_filter( 'posts_clauses', [ __CLASS__, 'media_sortable_columns_query' ], 1, 2 );
        add_filter( 'request', [ __CLASS__, 'media_sort_by_alt' ], 20, 2 );
        add_filter( 'media_row_actions', [ __CLASS__, 'filter_post_row_actions' ], 11, 2 );
		add_filter( 'default_hidden_columns', [ __CLASS__, 'hidden_columns' ], 99, 2 );

		// SVG File Permission.
		add_filter( 'mime_types', [ __CLASS__, 'add_support_mime_types' ], 99 );
        add_filter( 'wp_check_filetype_and_ext',  [ __CLASS__, 'allow_svg_upload' ], 10, 4 );

	}
	/**
	 * Check template screen
	 *
	 * @return boolean
	 */
	public static function allow_svg_upload( $data, $file, $filename, $mimes ) {

        $filetype = wp_check_filetype( $filename, $mimes );

        return [
            'ext'				=> $filetype['ext'],
            'type'				=> $filetype['type'],
            'proper_filename'	=> $data['proper_filename']
        ];

    }

	/**
	 * Check template screen
	 *
	 * @return boolean
	 */
	public static function hidden_columns( $hidden, $screen ) {
		if( ! empty( $hidden ) || empty( $screen->base ) || 'upload' !== $screen->base ){
			return $hidden;
		}
		$hidden[] = 'parent';
		$hidden[] = 'author';
		$hidden[] = 'comments';
		$hidden[] = 'date';
		return $hidden;
	}

	/**
	 * @param $mimes
	 *
	 * @return array
	 */
	public static function add_support_mime_types( $mimes ){
		$options = Fns::get_options();
		if( empty( $options['others_file_support'] ) || ! is_array( $options['others_file_support'] ) ){
			return $mimes;
		}

		if( in_array( 'svg', $options['others_file_support'] ) ){
			$mimes['svg|svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
     * Check template screen
     *
     * @return boolean
     */
    public static function is_attachment_screen() {
        global $pagenow, $typenow;
        return 'upload.php' === $pagenow && 'attachment' === $typenow;
    }
    /**
     * @param $actions
     * @return mixed
     */
    public static function filter_post_row_actions( $actions, $post ) {

        $att_title = _draft_or_post_title();
        if ( ! self::is_attachment_screen() ) {
            return $actions;
        }

        $actions['trash'] = sprintf(
            '<a href="%s" class="submitdelete aria-button-if-js" aria-label="%s">%s</a>',
            wp_nonce_url( "post.php?action=trash&amp;post=$post->ID", 'trash-post_' . $post->ID ),
            /* translators: %s: Attachment title. */
            esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $att_title ) ),
            _x( 'Trash', 'verb' )
        );
        $delete_ays        =  " onclick='return showNotice.warn();'" ;
        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete aria-button-if-js"%s aria-label="%s">%s</a>',
            wp_nonce_url( "post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID ),
            $delete_ays,
            /* translators: %s: Attachment title. */
            esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $att_title ) ),
            __( 'Delete Permanently' )
        );

        return $actions;
    }

    /**
     * Sortable column function.
     *
     * @param array $vars query var.
     * @return array
     */
    public static function media_sort_by_alt( $vars ) {
        if ( isset( $vars['orderby'] ) ) {
            if ( 'alt' === $vars['orderby'] ) {
                $vars = array_merge(
                    $vars,
                    array(
                        'orderby'    => 'meta_value',
                        'meta_query' => array(
                            'relation' => 'OR',
                            array(
                                'key'     => '_wp_attachment_image_alt',
                                'compare' => 'NOT EXISTS',
                                'value'   => '',
                            ),
                            array(
                                'key'     => '_wp_attachment_image_alt',
                                'compare' => 'EXISTS',
                            ),
                        ),
                    )
                );
            }
        }
        return $vars;
    }
    /**
     * Add new column to media table
     *
     * @param array $columns customize column.
     * @return array
     */
    public static function media_custom_column( $columns ) {
        $author   = isset( $columns['author'] ) ? $columns['author'] : '';
        $date     = isset( $columns['date'] ) ? $columns['date'] : '';
        $comments = isset( $columns['comments'] ) ? $columns['comments'] : '';
        $parent   = isset( $columns['parent'] ) ? $columns['parent'] : '';
        unset( $columns['author'] );
        unset( $columns['date'] );
        unset( $columns['comments'] );
        unset( $columns['parent'] );
        $columns['alt']         = __( 'Alt', 'media-library-helper' );
        $columns['caption']     = __( 'Caption', 'media-library-helper' );
        $columns['description'] = __( 'Description', 'media-library-helper' );
        $columns['category']    = __( 'Category', 'media-library-helper' );
        $columns['parent']      = $parent;
        $columns['author']      = $author;
        $columns['comments']    = $comments;
        $columns['date']        = $date;
        return $columns;
    }
    /**
     * SHortable column.
     *
     * @param string $columns shortable column.
     * @return array
     */
    public static function media_sortable_columns( $columns ) {
        $columns['alt']         = 'alt';
        $columns['caption']     = 'caption';
        $columns['description'] = 'description';
        return $columns;
    }

    /**
     * Undocumented function
     *
     * @param array  $pieces query.
     * @param object $query post query.
     * @return array
     */
    public static function media_sortable_columns_query( $pieces, $query ) {
        global $wpdb;
        if ( $query->is_main_query() ) {
            $orderby = $query->get( 'orderby' );
            if ( $orderby ) {
                $order = strtoupper( $query->get( 'order' ) );
                if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
                    switch ( $orderby ) {
                        case 'caption':
                            $pieces['orderby'] = " $wpdb->posts.post_excerpt $order ";
                            break;
                        case 'description':
                            $pieces['orderby'] = " $wpdb->posts.post_content $order ";
                            break;

                    }
                }
            }
        }
        return $pieces;
    }

    /**
     * @param array $links default plugin action link
     *
     * @return array [array] plugin action link
     */
    public static function plugins_setting_links( $links ) {
        $links['mediaedit_settings'] = '<a href="' . admin_url( 'upload.php?page=tsmlt-media-tools' ) . '">' . esc_html__( 'Start Editing', 'tsmlt-media-tools' ) . '</a>';
        /*
         * TODO:: Next Version
         *
         */
        if( ! Fns::is_plugins_installed('media-library-tools-pro/media-library-tools-pro.php') ){
            // $links['tsmlt_pro'] = sprintf( '<a href="#" target="_blank" style="color: #39b54a; font-weight: bold;">' . esc_html__( 'Go Pro', 'wp-media' ) . '</a>' );
        }
        return $links;
    }


}

