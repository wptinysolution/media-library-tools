<?php

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Rename\RenameModule;
use TinySolutions\mlt\Modules\ImageSize\ImageSizeModule;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Query;

/**
 * Class Api
 */
class Api {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Accept a plain parameter array (all callers pass arrays via AJAX).
	 *
	 * @param array $request_data Plain parameter array.
	 *
	 * @return array
	 */
	private function parse_params( array $request_data ): array {
		return $request_data;
	}
	/**
	 * @return false|string
	 */
	public function get_plugin_list() {
		// Define a unique key for the transient.
		$transient_key = 'get_plugin_list_use_cache_' . TSMLT_VERSION;
		// Try to get the cached data.
		$cached_data = get_transient( $transient_key );
		if ( ! empty( $cached_data ) ) {
			$is_empty = json_decode( $cached_data, true );
			// Return the cached data if it exists.
			if ( ! empty( $is_empty ) ) {
				return $cached_data;
			}
		}
		// Initialize the result array.
		$result = [];
		try {
			// Fetch data from the API.
			$response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[author]=tinysolution' );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$responseBody = json_decode( $response['body'], true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $responseBody['plugins'] ) ) {
					foreach ( $responseBody['plugins'] as $plugin ) {
						$result[] = [
							'plugin_name'       => $plugin['name'],
							'slug'              => $plugin['slug'],
							'author'            => $plugin['author'],
							'homepage'          => $plugin['homepage'],
							'download_link'     => $plugin['download_link'],
							'author_profile'    => $plugin['author_profile'],
							'icons'             => $plugin['icons'],
							'short_description' => $plugin['short_description'],
							'TB_iframe'         => esc_url( self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin['slug'] . '&TB_iframe=true&width=772&height=700' ) ),
						];
					}
				}
			}
		} catch ( \Exception $ex ) {
			// Handle exception (optional logging or error handling can be added here).
		}

		// Encode the result to JSON.
		$json_result = wp_json_encode( $result );

		// Cache the result for 1 day (24 hours * 60 minutes * 60 seconds).
		set_transient( $transient_key, $json_result, 7 * DAY_IN_SECONDS );

		return $json_result;
	}


	/**
	 * @return array
	 */
	public function update_option( array $request_data ) {
		$result     = [
			'message' => esc_html__( 'Update failed. Maybe change not found. ', 'media-library-tools' ),
		];
		$parameters = $this->parse_params( $request_data );

		$total_count = absint( $parameters['media_per_page'] ?? 20 );

		$tsmlt_media = get_option( 'tsmlt_settings', [] );

		$tsmlt_media['media_per_page'] = Fns::maximum_media_per_page() < $total_count ? Fns::maximum_media_per_page() : $total_count;

		$total_rabbis_count = absint( $parameters['rubbish_per_page'] ?? 20 );

		$tsmlt_media['rubbish_per_page'] = Fns::maximum_media_per_page() < $total_rabbis_count ? Fns::maximum_media_per_page() : $total_rabbis_count;

		$tsmlt_media['default_alt_text'] = $parameters['default_alt_text'] ?? '';

		$tsmlt_media['default_caption_text'] = $parameters['default_caption_text'] ?? '';

		$tsmlt_media['default_desc_text'] = $parameters['default_desc_text'] ?? '';

		$tsmlt_media['media_default_alt'] = $parameters['media_default_alt'] ?? '';

		$tsmlt_media['media_default_caption'] = $parameters['media_default_caption'] ?? '';

		$tsmlt_media['media_default_desc'] = $parameters['media_default_desc'] ?? '';

		$tsmlt_media['others_file_support'] = $parameters['others_file_support'] ?? [];

		$tsmlt_media['deregistered_image_sizes'] = $parameters['deregistered_image_sizes'] ?? [];

		$tsmlt_media['ai_provider']      = in_array( $parameters['ai_provider'] ?? '', [ 'chatgpt', 'gemini', 'claude' ], true ) ? $parameters['ai_provider'] : 'gemini';
		$ai_max                          = max( 1, (int) apply_filters( 'tsmlt_ai_max_suggestion_count', 1 ) );
		$tsmlt_media['ai_send_image']    = $ai_max > 1 && ! empty( $parameters['ai_send_image'] );
		$tsmlt_media['ai_suggestion_count'] = min( $ai_max, max( 1, (int) ( $parameters['ai_suggestion_count'] ?? 1 ) ) );
		$tsmlt_media['ai_chatgpt_key']   = sanitize_text_field( $parameters['ai_chatgpt_key']   ?? '' );
		$tsmlt_media['ai_chatgpt_model'] = sanitize_text_field( $parameters['ai_chatgpt_model'] ?? '' );
		$tsmlt_media['ai_gemini_key']    = sanitize_text_field( $parameters['ai_gemini_key']    ?? '' );
		$tsmlt_media['ai_gemini_model']  = sanitize_text_field( $parameters['ai_gemini_model']  ?? '' );
		$tsmlt_media['ai_claude_key']    = sanitize_text_field( $parameters['ai_claude_key']    ?? '' );
		$tsmlt_media['ai_claude_model']  = sanitize_text_field( $parameters['ai_claude_model']  ?? '' );

		$tsmlt_media = apply_filters( 'tsmlt/settings/before/save', $tsmlt_media, $parameters );

		$options = update_option( 'tsmlt_settings', $tsmlt_media );

		$result['updated'] = boolval( $options );

		$result['message'] = ! $result['updated'] ? $result['message'] . esc_html__( 'Please try to fix', 'media-library-tools' ) : esc_html__( 'Updated. Be happy', 'media-library-tools' );

		return $result;
	}

	/**
	 * @return false|string
	 */
	public function get_options() {
		return wp_json_encode( Fns::get_options() );
	}

	/**
	 * @return false|string
	 */
	public function get_terms() {
		$terms       = get_terms(
			[
				'taxonomy'   => Fns::CATEGORY,
				'hide_empty' => false,
			]
		);
		$terms_array = [];
		if ( ! is_wp_error( $terms ) && $terms ) {
			foreach ( $terms as $term ) {
				$terms_array[] = [
					'value' => $term->term_id,
					'label' => $term->name,
				];
			}
		}
		return wp_json_encode( $terms_array );
	}

	/**
	 * @return false|string
	 */
	public function get_dates() {
		global $wpdb;
		$date_query = $wpdb->prepare( "SELECT DISTINCT DATE_FORMAT(post_date, '%%Y-%%m') AS YearMonth FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' );
		$key        = 'tsmlt_date_query_' . gmdate( '_m_Y' );
		$dates      = get_transient( $key );

		if ( empty( $dates ) ) {
			delete_transient( $key );
			$get_date = $wpdb->get_col( $date_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared above.
			if ( $get_date ) {
				$dates = [];
				foreach ( $get_date as $date ) {
					$dates[] = [
						'value' => $date,
						'label' => gmdate( 'M Y', strtotime( $date ) ),
					];
				}
			}
			set_transient( $key, $dates, HOUR_IN_SECONDS );
		}

		$dates = ! empty( $dates ) ? $dates : [];

		return wp_json_encode( $dates );
	}

	/**
	 * @param array $request_data
	 *
	 * @return array
	 */
	public function update_single_media( array $request_data ): array {
		return RenameModule::instance()->update_single_media( $this->parse_params( $request_data ) );
	}
	/**
	 * @return array
	 */
	public function media_count() {
		$media_query = new WP_Query(
			[
				'post_type'      => 'attachment', // Media files are attachments in WordPress.
				'posts_per_page' => 30,
				'post_status'    => 'any',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			]
		);

		$totalPage = $media_query->max_num_pages; // Assuming 50 media files per page.

		return [
			'fileCount' => $media_query->found_posts,
			'totalPage' => $totalPage,
		];
	}


	/**
	 * @param array $request_data
	 *
	 * @return false|string
	 */
	public function get_media( array $request_data ) {

		$parameters = $this->parse_params( $request_data );
		$options    = get_option( 'tsmlt_settings' );
		$limit      = absint( ! empty( $parameters['media_per_page'] ) ? $parameters['media_per_page'] : ( ! empty( $options['media_per_page'] ) ? $options['media_per_page'] : 20 ) );
		$limit      = Fns::maximum_media_per_page() < $limit ? Fns::maximum_media_per_page() : $limit;

		$orderby = 'menu_order';
		$status  = 'inherit';
		if ( ! empty( $parameters['filtering'] ) && boolval( $parameters['filtering'] ) ) {
			$status = ! empty( $parameters['status'] ) ? $parameters['status'] : $status;
		}

		$searchKeyWords = $parameters['searchKeyWords'] ?? false;
		$order          = ! empty( $parameters['order'] ) ? $parameters['order'] : 'DESC';
		$paged          = ! empty( $parameters['paged'] ) ? $parameters['paged'] : 1;

		if ( ! empty( $parameters['orderby'] ) ) {
			switch ( $parameters['orderby'] ) {
				case 'id':
					$orderby = 'ID';
					break;
				case 'title':
					$orderby = 'post_title';
					break;
				case 'post_parents':
					$orderby = 'post_parent';
					break;
				case 'description':
					$orderby = 'post_content';
					break;
				case 'caption':
					$orderby = 'post_excerpt';
					break;
				case 'alt':
					$orderby = 'meta_query';
					break;
				default:
					$orderby = 'menu_order';
			}
		}

		$args = [
			'post_type'      => 'attachment',  // Retrieve only attachments.
			'posts_per_page' => $limit,
			'post_status'    => $status,
			'orderby'        => $orderby,
			'order'          => $order,
			'paged'          => absint( $paged ),
		];
		if ( $searchKeyWords ) {
			$args['s'] = sanitize_text_field( $searchKeyWords );
		}

		if ( 'meta_query' === $orderby ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary query.
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'EXISTS',
				],
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				],
			];
			$args['orderby']    = 'meta_value'; // Order by meta value.
		}
		if ( ! empty( $parameters['categories'] ) ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Necessary query.
				[
					'taxonomy' => Fns::CATEGORY,
					'field'    => 'term_id',
					'terms'    => $parameters['categories'],
				],
			];
		}
		if ( ! empty( $parameters['date'] ) ) {
			$date_parts = explode( '-', sanitize_text_field( $parameters['date'] ) );
			if ( count( $date_parts ) === 2 ) {
				$args['date_query'] = [
					[
						'year'  => (int) $date_parts[0],
						'month' => (int) $date_parts[1],
					],
				];
			}
		}
		// Used/Unused image filter.
		if ( ! empty( $parameters['usage_filter'] ) ) {
			$usage_filter = sanitize_text_field( $parameters['usage_filter'] );
			if ( 'used' === $usage_filter ) {
				// Images that have a parent post set (used somewhere).
				$args['post_parent__not_in'] = [ 0 ];
			} elseif ( 'unused' === $usage_filter ) {
				// Images with no parent post (not used anywhere).
				$args['post_parent'] = 0;
			}
		}
		add_filter( 'posts_clauses', [ Fns::class, 'custom_orderby_post_excerpt_content' ], 10, 2 );
		$_posts_query = new WP_Query( $args );
		$get_posts    = [];
		foreach ( $_posts_query->posts as $post ) {
			// Set Thumbnail Uploaded to.
			$parent_title     = '';
			$parent_permalink = '';
			$parent_sku       = '';
			if ( $post->post_parent ) {
				$parent_title     = get_the_title( $post->post_parent );
				$parent_permalink = get_the_permalink( $post->post_parent );
				$parent_sku       = get_post_meta( $post->post_parent, '_sku', true );
			}
			$thefile       = [];
			$metadata      = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
			$attached_file = get_attached_file( $post->ID );
			if ( ! empty( $metadata['file'] ) ) {
				$thefile['mainfilepath']  = dirname( $attached_file );
				$thefile['mainfilename']  = basename( $attached_file );
				$thefile['fileextension'] = pathinfo( $metadata['file'], PATHINFO_EXTENSION );
				$thefile['filebasename']  = basename( $metadata['file'], '.' . $thefile['fileextension'] );
				$thefile['originalname']  = basename( $metadata['file'], '.' . $thefile['fileextension'] );
			}

			if ( empty( $thefile['mainfilename'] ) ) {
				$thefile['mainfilename']  = basename( $attached_file );
				$thefile['fileextension'] = pathinfo( $attached_file, PATHINFO_EXTENSION );
				$thefile['filebasename']  = basename( $attached_file, '.' . $thefile['fileextension'] );
				$thefile['originalname']  = basename( $attached_file, '.' . $thefile['fileextension'] );
			}
			$upload_dir      = wp_upload_dir();
			$uploaddir       = $upload_dir['baseurl'] ?? home_url( '/wp-content/uploads' );
			$thefile['file'] = _wp_relative_upload_path( $attached_file );

			$terms          = get_the_terms( $post->ID, Fns::CATEGORY );
			$tsmlt_category = [];
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$tsmlt_category[] = [
						'id'   => $term->term_id,
						'name' => $term->name,
					];
				}
			}

			$get_meta = get_post_meta( $post->ID );
			// Remove unwanted meta keys.
			$remove_keys = Fns::skip_meta_keys();
			$get_meta    = array_diff_key( $get_meta, array_flip( $remove_keys ) );

			$all_meta_keys = Fns::get_all_necessary_meta_keys();
			$custom_meta   = [];
			if ( ! empty( $all_meta_keys ) ) {
				foreach ( $all_meta_keys as $name ) {
					$_value = $get_meta[ $name ][0] ?? '';
					if ( ! is_array( $_value ) ) {
						$custom_meta[ 'custom_meta:' . $name ] = esc_attr( $_value );
					}
				}
			}
			$get_posts[] = [
				'ID'             => $post->ID,
				'url'            => wp_get_attachment_url( $post->ID ),
				'title'          => esc_attr( $post->post_title ),
				'post_parents'   => [
					'title'     => esc_attr( $parent_title ),
					'permalink' => $parent_permalink,
					'sku'       => esc_attr( $parent_sku ),
				],
				'caption'        => esc_attr( $post->post_excerpt ),
				'description'    => esc_attr( $post->post_content ),
				'slug'           => esc_attr( $post->post_name ),
				'guid'           => $post->guid,
				'uploaddir'      => $uploaddir,
				'alt_text'       => esc_attr( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ),
				'categories'     => wp_json_encode( $tsmlt_category ),
				'metadata'       => $metadata,
				'thefile'        => $thefile,
				'post_mime_type' => $post->post_mime_type,
				'custom_meta'	 => $custom_meta,
			];

		}
		$query_data = [
			'posts'          => $get_posts,
			'posts_per_page' => absint( $limit ),
			'total_post'     => $_posts_query->found_posts,
			'paged'          => absint( $paged ),
			'total_page'     => $_posts_query->max_num_pages,
		];
		wp_reset_postdata();
		remove_filter( 'posts_clauses', [ Fns::class, 'custom_orderby_post_excerpt_content' ], 10, 2 );

		return wp_json_encode( $query_data );
	}

	/**
	 * @param array $request_data
	 *
	 * @return array
	 */
	public function media_submit_bulk_action( array $request_data ) {
		$parameters = $this->parse_params( $request_data );
		$result     = [
			'updated' => false,
			'message' => esc_html__( 'Update failed. Please try to fix', 'media-library-tools' ),
		];
		if ( empty( $parameters['type'] ) || empty( $parameters['ids'] ) ) {
			return $result;
		}
		// Sanitize IDs.
		$ids = array_map( 'absint', (array) $parameters['ids'] );
		switch ( $parameters['type'] ) {
			/**
			 * Search Uses
			 */
			case 'searchUses':
				foreach ( $ids as $id ) {
					Fns::set_thumbnail_parent_id( $id );
				}
				$result['updated'] = true;
				$result['message'] = esc_html__( 'Updated. Be happy.', 'media-library-tools' );
				break;
			/**
			 * Trash or Inherit
			 */
			case 'trash':
			case 'inherit':
				$status = sanitize_key( $parameters['type'] );
				foreach ( $ids as $id ) {
					Fns::DB()->update( 'posts', [ 'post_status' => $status ] )
						->where( 'ID', '=', $id )
						->andWhere( 'post_type', '=', 'attachment' )
						->execute();
				}
				$result['updated'] = true;
				$result['message'] = esc_html__( 'Done. Be happy.', 'media-library-tools' );
				break;
			/**
			 * Delete attachments
			 */
			case 'delete':
				$deleted = 0;
				foreach ( $ids as $id ) {
					if ( wp_delete_attachment( $id, true ) ) {
						$deleted++;
					}
				}
				$result['updated'] = ( count( $ids ) === $deleted );
				$result['message'] = $result['updated']
					? esc_html__( 'Deleted. Be happy.', 'media-library-tools' )
					: esc_html__( 'Deleted failed. Please try to fix', 'media-library-tools' );
				break;
			/**
			 * BULK EDIT (The vulnerable part — fully fixed)
			 */
			case 'bulkedit':
				$data       = isset( $parameters['data'] ) ? (array) $parameters['data'] : [];
				$categories = isset( $parameters['post_categories'] ) ? (array) $parameters['post_categories'] : [];
				// Prepare safe fields.
				$update_fields = [];
				if ( ! empty( $data['post_title'] ) ) {
					$update_fields['post_title'] = sanitize_text_field( $data['post_title'] );
				}
				if ( ! empty( $data['caption'] ) ) {
					$update_fields['post_excerpt'] = sanitize_text_field( $data['caption'] );
				}
				if ( ! empty( $data['post_description'] ) ) {
					$update_fields['post_content'] = wp_kses_post( $data['post_description'] );
				}
				$updated = false;
				// Safe SQL updates.
				if ( ! empty( $update_fields ) ) {
					foreach ( $ids as $id ) {
						Fns::DB()->update( 'posts', $update_fields )
							->where( 'ID', '=', $id )
							->andWhere( 'post_type', '=', 'attachment' )
							->execute();
						$updated = true;
					}
				}
				// ALT TEXT update.
				if ( ! empty( $data['alt_text'] ) ) {
					$alt_text = sanitize_text_field( $data['alt_text'] );
					foreach ( $ids as $id ) {
						update_post_meta( $id, '_wp_attachment_image_alt', $alt_text );
					}
					$updated = true;
				}
				// Categories.
				if ( ! empty( $categories ) ) {
					foreach ( $ids as $id ) {
						wp_set_object_terms( $id, $categories, Fns::CATEGORY );
					}
					$updated = true;
				}
				$result['updated'] = $updated;
				$result['message'] = $updated
					? esc_html__( 'Updated. Be happy.', 'media-library-tools' )
					: esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );
				break;
			default:
				// Unknown operation.
				break;
		}
		return $result;
	}

	/**
	 * @return array
	 */
	public function get_registered_image_size(): array {
		return ImageSizeModule::instance()->get_registered_image_size();
	}

}
