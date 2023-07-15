<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Filesystem;
use WP_Filesystem_Direct;
use WP_Filesystem_Base;

defined( 'ABSPATH' ) || exit();

/**
 * Main ActionHooks class.
 */
class ActionHooks {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	private function __construct() {
        add_action( 'manage_media_custom_column', [ $this, 'display_column_value' ], 10, 2 );
		add_action( 'add_attachment', [ $this, 'add_image_info_to' ]  );
		// Hook the function to a cron job
		add_action( 'init', [ $this, 'schedule_rabbis_cron_job' ] );
		add_action( 'tsmlt_upload_directory_scan', [ $this, 'scan_upload_directory_wrapper' ] );
	}

	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function add_image_info_to( $post_id ) {
		$options = Fns::get_options();
		$image_title = get_the_title( $post_id ) ;

		if( ! empty( $options['default_alt_text'] ) && 'image_name_to_alt' === $options['default_alt_text'] ){
			update_post_meta( $post_id, '_wp_attachment_image_alt', $image_title );
		} else if ( ! empty( $options['media_default_alt'] ) && 'custom_text_to_alt' === $options['default_alt_text'] ){
			update_post_meta( $post_id, '_wp_attachment_image_alt', $options['media_default_alt'] );
		}

		$image_meta = [];

		if( ! empty( $options['default_caption_text'] ) && 'image_name_to_caption' === $options['default_caption_text'] ){
			$image_meta['post_excerpt' ] = $image_title;
		} else if( ! empty( $options['media_default_caption'] ) && 'custom_text_to_caption' === $options['default_caption_text'] ){
			$image_meta['post_excerpt' ] = $options['media_default_caption'];
		}

		if( ! empty( $options['default_desc_text'] ) && 'image_name_to_desc' === $options['default_desc_text'] ){
			$image_meta['post_content' ] = $image_title;
		} else if( ! empty( $options['media_default_desc'] ) && 'custom_text_to_desc' === $options['default_desc_text'] ){
			$image_meta['post_content' ] = $options['media_default_desc'];
		}

		if( ! empty( $image_meta ) ){
			$image_meta['ID'] = $post_id;
			wp_update_post( $image_meta );
		}
	}
    /**
     * @param $column
     * @param $post_id
     * @return void
     */
    public function display_column_value( $column, $post_id ) {
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

	/**
	 * Function to scan the upload directory and search for files
	 */
	public function scan_upload_directory_wrapper() {
		global $wp_filesystem;

		// Initialize the WP filesystem
		if ( empty( $wp_filesystem ) ) {
			// Include the file.php for WP filesystem functions
			include_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload_dir = wp_upload_dir(); // Get the upload directory path

		$directory = $upload_dir['basedir']; // Get the base directory path

		$rabbis_offset_key = 'tsmlt_rabbis_last_processed_offset';

		$last_processed_offset = absint( get_option( $rabbis_offset_key ) ); // Initialize the offset

		$filesystem = $this->get_wp_filesystem_instance(); // Get the proper WP_Filesystem instance

		$found_files = $this->scan_upload_directory( $filesystem, $directory, $last_processed_offset ); // Scan the directory and search for files

		$found_files_count = count( $found_files );

		if ( $found_files_count > 0 ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
			foreach ( $found_files as $file_path ) {
				// Check if the file_path already exists in the table using cached data
				$existing_row = wp_cache_get( "tsmlt_existing_row_$file_path" );
				if ( $existing_row === false ) {
					$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT file_path FROM $table_name WHERE file_path = %s", $file_path ) );
					// Cache the query result
					wp_cache_set( "tsmlt_existing_row_$file_path", $existing_row );
				}

			}
			$next_offset = $last_processed_offset + $found_files_count;
			// Store the next offset for the next run
			update_option( $rabbis_offset_key, $next_offset );
		} else {
			// No new files found, clear the last processed offset
			update_option( $rabbis_offset_key, 0 );
		}
	}

	/**
	 * Get the WP_Filesystem instance
	 *
	 * @return WP_Filesystem|WP_Filesystem_Direct The WP_Filesystem instance
	 */
	private function get_wp_filesystem_instance() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}
		// Check if WP_Filesystem_Direct is already instantiated
		if ( $wp_filesystem instanceof WP_Filesystem_Base && $wp_filesystem instanceof WP_Filesystem_Direct ) {
			if ( method_exists( $wp_filesystem, 'request_filesystem_credentials' ) ) {
				$wp_filesystem = new WP_Filesystem_Direct( null );
			}
		}

		return $wp_filesystem;
	}

	/**
	 * Function to scan the upload directory and search for files.
	 *
	 * @param WP_Filesystem|WP_Filesystem_Direct $filesystem The WP_Filesystem instance.
	 * @param string $directory The directory to scan.
	 * @param int $offset The offset to start scanning from.
	 * @return array The list of found files.
	 */
	public function scan_upload_directory( $filesystem, $directory, $offset = 0 ) {
		$scanned_files = [];
		// Ensure the directory exists before scanning
		if ( $filesystem->is_dir( $directory ) ) {
			$files = $filesystem->dirlist( $directory );
			foreach ( $files as $file ) {
				$file_path = trailingslashit( $directory ) . $file['name'];
				if ( $filesystem->is_dir( $file_path ) ) {
					$subdirectory_files = $this->scan_upload_directory( $filesystem, $file_path );
					$scanned_files = array_merge( $scanned_files, $subdirectory_files );
				} else {
					$scanned_files[] = $file_path;
				}
			}
		}
		return $scanned_files;
	}
	/**
	 * Schedule the cron job
	 * @return void
	 */
	public function schedule_rabbis_cron_job() {
		if ( ! wp_next_scheduled( 'tsmlt_upload_directory_scan' ) ) {
			wp_schedule_event( time(), 'everyminute', 'tsmlt_upload_directory_scan' );
		}
	}






}
