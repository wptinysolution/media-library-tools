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
		add_action( 'add_attachment', [ $this, 'add_image_info_to' ] );

		// Hook the function to a cron job.
		add_action( 'init', [ $this, 'schedule_directory_cron_job' ] );
		add_action( 'tsmlt_upload_dir_scan', [ Fns::class, 'get_directory_list_cron_job' ] );

		// Rubbish Cron Job.
		add_action( 'init', [ $this, 'schedule_rubbish_file_cron_job' ] );
		add_action( 'tsmlt_upload_inner_file_scan', [ $this, 'scan_rubbish_file_cron_job' ] );
		add_action( 'in_admin_header', [ $this, 'remove_all_notices' ], 99 );
	}
	
	/**
	 * @return void
	 */
	public function remove_all_notices() {
		$screen = get_current_screen();
		if ( in_array( $screen->base, [ 'media_page_tsmlt-media-tools', 'media_page_tsmlt-get-pro', 'media_page_tsmlt-pricing-pro' ] ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}
	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function add_image_info_to( $attachment_ID ) {
		$options     = Fns::get_options();
		$image_title = get_the_title( $attachment_ID );

		$post_id = absint( $_REQUEST['post_id'] ?? 0 );
		// TODO::  Auto Add Alt text is not working.

		if ( ! $post_id || empty( $options['alt_text_by_post_title'] ) ) {
			if ( ! empty( $options['default_alt_text'] ) && 'image_name_to_alt' === $options['default_alt_text'] ) {
				update_post_meta( $attachment_ID, '_wp_attachment_image_alt', $image_title );
			} elseif ( ! empty( $options['media_default_alt'] ) && 'custom_text_to_alt' === $options['default_alt_text'] ) {
				update_post_meta( $attachment_ID, '_wp_attachment_image_alt', $options['media_default_alt'] );
			}
		}

		$image_meta = [];
		if ( ! empty( $options['default_caption_text'] ) && 'image_name_to_caption' === $options['default_caption_text'] ) {
			$image_meta['post_excerpt'] = $image_title;
		} elseif ( ! empty( $options['media_default_caption'] ) && 'custom_text_to_caption' === $options['default_caption_text'] ) {
			$image_meta['post_excerpt'] = $options['media_default_caption'];
		}

		if ( ! empty( $options['default_desc_text'] ) && 'image_name_to_desc' === $options['default_desc_text'] ) {
			$image_meta['post_content'] = $image_title;
		} elseif ( ! empty( $options['media_default_desc'] ) && 'custom_text_to_desc' === $options['default_desc_text'] ) {
			$image_meta['post_content'] = $options['media_default_desc'];
		}

		$image_meta = apply_filters( 'tsmlt/before/add/image/info', $image_meta, $options, $attachment_ID, $post_id );

		if ( ! empty( $image_meta ) ) {
			$image_meta['ID'] = $attachment_ID;
			wp_update_post( $image_meta );
		}
	}

	/**
	 * @param $column
	 * @param $post_id
	 *
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
					$out = [];
					foreach ( $terms as $t ) {
						$posts_in_term_qv              = [];
						$posts_in_term_qv['post_type'] = get_post_type( $post_id );

						if ( $taxonomy_object->query_var ) {
							$posts_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
						} else {
							$posts_in_term_qv['taxonomy'] = tsmlt()->category;
							$posts_in_term_qv['term']     = $t->slug;
						}

						$out[] = sprintf(
							'<a href="%s">%s</a>',
							esc_url( add_query_arg( $posts_in_term_qv, 'upload.php' ) ),
							esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, tsmlt()->category, 'display' ) )
						);
					}

					/* translators: used between list items, there is a space after the comma */
					echo join( __( ', ' ), $out );
				}
				break;
			default:
				break;
		}
	}

	/**
	 * Schedule the cron job
	 *
	 * @return void
	 */
	public function schedule_rubbish_file_cron_job() {
		$event_hook = 'tsmlt_upload_inner_file_scan';
		// Check if the cron job is already scheduled.
		$is_scheduled = wp_next_scheduled( $event_hook );
		if ( $is_scheduled ) {
			return; // Cron job is already scheduled, no need to proceed further.
		}
		// Clear any existing scheduled events with the same hook.
		wp_clear_scheduled_hook( $event_hook );
		$schedule = 'weekly';
		// Schedule the cron job to run every minute.
		wp_schedule_event( time(), $schedule, $event_hook );
	}

	/**
	 * Function to scan the upload directory and search for files
	 */
	public function scan_rubbish_file_cron_job() {

		$dis_list = get_option( 'tsmlt_get_directory_list', [] );
		if ( ! count( $dis_list ) ) {
			return;
		}
		$directory = '';
		foreach ( $dis_list as $key => $item ) {
			if ( absint( $item['total_items'] ) && ( absint( $item['total_items'] ) <= absint( $item['counted'] ) ) ) {
				continue;
			}
			if ( 'available' !== ( $item['status'] ?? 'available' ) ) {
				continue;
			}
			$directory = $key;
		}

		Fns::update_rubbish_file_to_database( $directory );
	}

	/**
	 * Schedule the cron job
	 *
	 * @return void
	 * Schedule the cron job
	 */
	public function schedule_directory_cron_job() {
		$event_hook = 'tsmlt_upload_dir_scan';
		// Check if the cron job is already scheduled.
		$is_scheduled = wp_next_scheduled( $event_hook );

		if ( $is_scheduled ) {
			return; // Cron job is already scheduled, no need to proceed further.
		}
		// Clear any existing scheduled events with the same hook.
		wp_clear_scheduled_hook( $event_hook );
		wp_schedule_event( time(), 'weekly', $event_hook );
	}
	
}
