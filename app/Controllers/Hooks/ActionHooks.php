<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

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
	 * @param $directory
	 * @param $offset
	 *
	 * @return array
	 */
	public function scan_upload_directory( $directory, $offset = 0 ) {
		$scanned_files = array();
		$files = scandir( $directory );
		$exclude_file = ['.', '..' ];
		foreach ( $files as $file ) {
			if ( in_array( $file, $exclude_file ) ) {
				continue;
			}
			$path = $directory . '/' . $file;
			if ( is_file( $path ) ) {
				$scanned_files[] = $path;
			} elseif ( is_dir( $path ) ) {
				$subdirectory_files = $this->scan_upload_directory( $path );
				$scanned_files = array_unique( array_merge( $scanned_files, $subdirectory_files ) );
			}
		}
		return $scanned_files;
	}

	/**
	 * Function to scan the upload directory and search for files
	 * @return void
	 */
	public function scan_upload_directory_wrapper() {
		$upload_dir = wp_upload_dir(); // Get the upload directory path
		$directory  = $upload_dir['basedir']; // Get the base directory path
		$rabbis_offset_key = 'tsmlt_rabbis_last_processed_offset';
		$last_processed_offset = absint( get_option( $rabbis_offset_key ) ); // Initialize the offset
		$found_files = $this->scan_upload_directory( $directory, $last_processed_offset ); // Scan the directory and search for files
		// error_log( print_r( $found_files, true ) . "\n\n", 3, __DIR__ . '/the_log.txt' );
		$found_files_count = count( $found_files );

		if ( $found_files_count > 0 ) {
			// Process the found files here or perform any other actions you need
			global $wpdb;
			$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
			//error_log( print_r( $found_files, true ) . "\n\n", 3, __DIR__ . '/the_log.txt' );

			// Process the found files here or perform any other actions you need
			foreach ( $found_files as $file_path ) {

				// Check if the file_path already exists in the table using cached data
				$existing_row = wp_cache_get("tsmlt_existing_row_$file_path");
				if ($existing_row === false) {
					$existing_row = $wpdb->get_row($wpdb->prepare("SELECT file_path FROM $table_name WHERE file_path = %s", $file_path));
					// Cache the query result
					wp_cache_set("tsmlt_existing_row_$file_path", $existing_row);
				}

//				$attachment_id = $file['attachment_id'] ?? 0;
//
//				$file_type = $file['file_type'] ?? '';
//				$meta_data = $file['meta_data'] ?? '';
//				// Do something with each file, e.g., display its name
//				$wpdb->insert( $table_name, array(
//					'attachment_id' => $attachment_id,
//					'file_path' => $file_path,
//					'file_type' => $file_type,
//					'meta_data' => $meta_data,
//				));
//

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
	 * Schedule the cron job
	 * @return void
	 */
	public function schedule_rabbis_cron_job() {
		if ( ! wp_next_scheduled( 'tsmlt_upload_directory_scan' ) ) {
			wp_schedule_event( time(), 'everyminute', 'tsmlt_upload_directory_scan' );
		}
	}

}
