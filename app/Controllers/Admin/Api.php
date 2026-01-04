<?php

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Query;
use WP_REST_Request;

/**
 * Class Api
 */
class Api {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * @var string
	 */
	private $namespace = 'TinySolutions/mlt/v1';
	/**
	 * @var string
	 */
	private $resource_name = '/media';
	/**
	 * Construct
	 */
	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register our routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->resource_name,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_media' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$this->resource_name . '/mediaCount',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'media_count' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$this->resource_name . '/update',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_single_media' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/bulk/submit',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'media_submit_bulk_action' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/filter/getdates',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_dates' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getterms',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_terms' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getoptions',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_options' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/updateoptins',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_option' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getRubbishFileType',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_rubbish_filetype' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getRubbishFile',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_rubbish_file' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getDirList',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_dir_list' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/rescanDir',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rescan_dir' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/searchFileBySingleDir',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'immediately_search_rubbish_file' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$this->resource_name . '/clearSchedule',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'clear_schedule' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getRegisteredImageSizes',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_registered_image_size' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->resource_name . '/getPluginList',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_plugin_list' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$this->resource_name . '/truncateUnlistedFile',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'delete_all_rows_in_unlisted_file' ],
				'permission_callback' => [ $this, 'login_permission_callback' ],
			]
		);
	}

	/**
	 * @return true
	 */
	public function login_permission_callback() {
		return current_user_can( 'manage_options' );
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
	public function update_option( $request_data ) {
		$result     = [
			'message' => esc_html__( 'Update failed. Maybe change not found. ', 'media-library-tools' ),
		];
		$parameters = $request_data->get_params();

		$total_count = absint( $parameters['media_per_page'] ?? 20 );

		$tsmlt_media = get_option( 'tsmlt_settings', [] );

		$tsmlt_media['media_per_page'] = Fns::maximum_media_per_page() < $total_count ? Fns::maximum_media_per_page() : $total_count;

		$total_rabbis_count = absint( $parameters['rubbish_per_page'] ?? 20 );

		$tsmlt_media['rubbish_per_page'] = Fns::maximum_media_per_page() < $total_rabbis_count ? Fns::maximum_media_per_page() : $total_rabbis_count;

		$tsmlt_media['media_table_column'] = $parameters['media_table_column'] ?? [];

		$tsmlt_media['default_alt_text'] = $parameters['default_alt_text'] ?? '';

		$tsmlt_media['default_caption_text'] = $parameters['default_caption_text'] ?? '';

		$tsmlt_media['default_desc_text'] = $parameters['default_desc_text'] ?? '';

		$tsmlt_media['media_default_alt'] = $parameters['media_default_alt'] ?? '';

		$tsmlt_media['media_default_caption'] = $parameters['media_default_caption'] ?? '';

		$tsmlt_media['media_default_desc'] = $parameters['media_default_desc'] ?? '';

		$tsmlt_media['others_file_support'] = $parameters['others_file_support'] ?? [];

		$tsmlt_media['deregistered_image_sizes'] = $parameters['deregistered_image_sizes'] ?? [];

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
				'taxonomy'   => tsmlt()->category,
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
	 * @param $request_data
	 *
	 * @return array
	 */
	public function update_single_media( $request_data ) {
		$parameters = $request_data->get_params();
		$result     = [
			'updated' => false,
			'message' => esc_html__( 'Update failed. Please try to fix', 'media-library-tools' ),
		];
		if ( empty( $parameters['ID'] ) ) {
			return $result;
		}
		// Handle Rename.
		if ( isset( $parameters['newname'] ) ) {
			return $this->handle_rename( $parameters );
		}
		// Handle Bulk Edit.
		if ( ! empty( $parameters['bulkEditPostTitle'] ) ) {
			return $this->handle_bulk_edit( $parameters );
		}
		// Handle Single Field Update.
		return $this->handle_single_updates( $parameters, $result );
	}

	/**
	 * @param $parameters
	 *
	 * @return array
	 */
	private function handle_rename( $parameters ) {
		$result     = [
			'updated' => false,
			'message' => esc_html__( 'Rename failed. Please try again.', 'media-library-tools' ),
		];
		$attachment = get_post( (int) $parameters['ID'] );
		if ( ! $attachment || empty( $parameters['newname'] ) ) {
			return $result;
		}
		$new_name  = sanitize_text_field( $parameters['newname'] );
		$rename_to = $new_name; // default behavior (direct rename).
		$post_id   = $attachment->post_parent ?: Fns::set_thumbnail_parent_id( $attachment->ID );
		/**
		 * Filter rename target filename.
		 *
		 * @param string   $rename_to  Final filename to rename to.
		 * @param string   $new_name   Rename action or raw filename.
		 * @param int      $post_id    Parent post ID (if exists).
		 * @param \WP_Post  $attachment Attachment object.
		 */
		$rename_to = apply_filters(
			'tsmlt_attachment_rename_to',
			$rename_to,
			$new_name,
			$post_id,
			$attachment
		);
		if ( ! empty( $rename_to ) && Fns::wp_rename_attachment( $attachment->ID, $rename_to ) ) {
			$result['updated'] = true;
			$result['message'] = esc_html__( 'Renamed.', 'media-library-tools' );
		} else {
			$result['message'] = esc_html__(
				'Rename failed. The file may not exist or file permissions may be incorrect.',
				'media-library-tools'
			);
		}
		return $result;
	}

	/**
	 * @param array $parameters pram.
	 *
	 * @return array
	 */
	private function handle_bulk_edit( $parameters ) {
		$result     = [
			'updated' => false,
			'message' => esc_html__( 'Update failed.', 'media-library-tools' ),
		];
		$attachment = get_post( $parameters['ID'] );
		$new_text   = '';
		if ( $attachment && $attachment->post_parent ) {
			$new_text = get_the_title( $attachment->post_parent );
		}
		if ( empty( $new_text ) ) {
			return $result;
		}
		$submit = [];
		if ( in_array( 'post_title', $parameters['bulkEditPostTitle'], true ) ) {
			$submit['post_title'] = $new_text;
		}
		if ( in_array( 'alt_text', $parameters['bulkEditPostTitle'], true ) ) {
			$result['updated'] = update_post_meta( $parameters['ID'], '_wp_attachment_image_alt', trim( $new_text ) );
			$result['message'] = esc_html__( 'Saved.', 'media-library-tools' );
		}
		if ( in_array( 'caption', $parameters['bulkEditPostTitle'], true ) ) {
			$submit['post_excerpt'] = $new_text;
		}
		if ( in_array( 'post_description', $parameters['bulkEditPostTitle'], true ) ) {
			$submit['post_content'] = $new_text;
		}
		if ( ! empty( $submit ) ) {
			$submit['ID']      = $parameters['ID'];
			$result['updated'] = wp_update_post( $submit );
			$result['message'] = $result['updated'] ? $result['message'] : esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );
		}
		return $result;
	}

	/**
	 * Handles a single update operation and processes the result.
	 *
	 * @param array $parameters Data required to perform the update.
	 * @param mixed $result     Result returned from the update process.
	 *
	 * @return mixed Modified or original result after processing.
	 */
	private function handle_single_updates( $parameters, $result ) {
		$post_fields = [
			'post_title'   => esc_html__( 'The Title has been saved.', 'media-library-tools' ),
			'post_excerpt' => esc_html__( 'The Caption has been saved.', 'media-library-tools' ),
			'post_content' => esc_html__( 'Content has been saved.', 'media-library-tools' ),
			'alt_text'     => esc_html__( 'Saved.', 'media-library-tools' ),
		];
		if ( isset( $parameters['title'] ) ) {
			$parameters['post_title'] = sanitize_text_field( $parameters['title'] );
			unset( $parameters['title'] );
		}
		if ( isset( $parameters['caption'] ) ) {
			$parameters['post_excerpt'] = sanitize_text_field( $parameters['caption'] );
			unset( $parameters['caption'] );
		}
		if ( isset( $parameters['description'] ) ) {
			$parameters['post_content'] = sanitize_text_field( $parameters['description'] );
			unset( $parameters['description'] );
		}
		$submit = [];
		foreach ( $post_fields as $field => $message ) {
			if ( isset( $parameters[ $field ] ) ) {
				if ( 'alt_text' === $field ) {
					$result['updated'] = update_post_meta( $parameters['ID'], '_wp_attachment_image_alt', trim( $parameters[ $field ] ) );
				} else {
					$submit[ $field ] = trim( $parameters[ $field ] );
				}
				$result['message'] = $message;
			}
		}
		if ( ! empty( $submit ) ) {
			$submit['ID']      = $parameters['ID'];
			$result['updated'] = wp_update_post( $submit );
			$result['message'] = $result['updated']
				? $result['message']
				: esc_html__( 'Update failed. Please try to fix.', 'media-library-tools' );
		}
		return $result;
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
	 * @param $request_data
	 *
	 * @return false|string|WP_Error
	 */
	public function get_media( $request_data ) {

		$parameters = $request_data->get_params();

		$options = get_option( 'tsmlt_settings' );
		$limit   = absint( ! empty( $options['media_per_page'] ) ? $options['media_per_page'] : 20 );
		$limit   = Fns::maximum_media_per_page() < $limit ? Fns::maximum_media_per_page() : $limit;

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
					'taxonomy' => tsmlt()->category,
					'field'    => 'term_id',
					'terms'    => $parameters['categories'],
				],
			];
		}
		add_filter( 'posts_clauses', [ Fns::class, 'custom_orderby_post_excerpt_content' ], 10, 2 );

		$_posts_query = new WP_Query( $args );
		$get_posts    = [];
		foreach ( $_posts_query->posts as $post ) {
			// Set Thumbnail Uploaded to.
			$parent_title     = '';
			$parent_permalink = '';
			if ( $post->post_parent ) {
				$parent_title     = get_the_title( $post->post_parent );
				$parent_permalink = get_the_permalink( $post->post_parent );
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

			$terms          = get_the_terms( $post->ID, tsmlt()->category );
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
					'title'     => esc_attr( $parent_title ) ,
					'permalink' => $parent_permalink,
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
	 * @param $request_data
	 * @return array|WP_Error
	 */
	public function media_submit_bulk_action( $request_data ) {
		global $wpdb;
		$parameters = $request_data->get_params();
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
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->posts,
						[ 'post_status' => $status ],
						[
							'ID'        => $id,
							'post_type' => 'attachment',
						],
						[ '%s' ],
						[ '%d', '%s' ]
					);
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
				$update_format = [];
				if ( ! empty( $data['post_title'] ) ) {
					$update_fields['post_title'] = sanitize_text_field( $data['post_title'] );
					$update_format[]             = '%s';
				}
				if ( ! empty( $data['caption'] ) ) {
					$update_fields['post_excerpt'] = sanitize_text_field( $data['caption'] );
					$update_format[]               = '%s';
				}
				if ( ! empty( $data['post_description'] ) ) {
					$update_fields['post_content'] = wp_kses_post( $data['post_description'] );
					$update_format[]               = '%s';
				}
				$updated = false;
				// Safe SQL updates.
				if ( ! empty( $update_fields ) ) {
					foreach ( $ids as $id ) {
						$res = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$wpdb->posts,
							$update_fields,
							[
								'ID'        => $id,
								'post_type' => 'attachment',
							],
							$update_format,
							[ '%d', '%s' ]
						);
						if ( false !== $res ) {
							$updated = true;
						}
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
						wp_set_object_terms( $id, $categories, tsmlt()->category );
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
	 * @return false|string
	 */
	public function get_dir_list() {

		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );

		$directory_list = get_option( 'tsmlt_get_directory_list', [] );

		// Get the timestamp of the next scheduled event.
		$next_scheduled_timestamp = wp_next_scheduled( 'tsmlt_upload_dir_scan' );

		// Get WordPress timezone.
		$wordpress_timezone = get_option( 'timezone_string' );

		// Set a default timezone in case the WordPress timezone is not set or invalid.
		$timezone = $wordpress_timezone ? new \DateTimeZone( $wordpress_timezone ) : new \DateTimeZone( 'UTC' );

		// Create a DateTime object with the scheduled timestamp and set the timezone.
		$next_scheduled_datetime = new \DateTime( "@$next_scheduled_timestamp" );
		$next_scheduled_datetime->setTimezone( $timezone );

		$data = [
			'dirList'      => $directory_list,
			'nextSchedule' => $next_scheduled_datetime->format( 'Y-m-d h:i:sa' ),
		];
		return json_encode( $data );
	}

	/**
	 * @return array
	 */
	public function rescan_dir( $request_data ) {
		$parameters     = $request_data->get_params();
		$dir            = $parameters['dir'] ?? 'all';
		$directory_list = [];
		$message        = esc_html__( 'Schedule Will Execute Soon.', 'media-library-tools' );
		if ( 'all' === $dir ) {
			Fns::get_directory_list_cron_job( true );
			$message = esc_html__( 'Schedule Will Execute Soon For Directory List.', 'media-library-tools' );
		} elseif ( empty( $directory_list[ $dir ] ) ) {
			$directory_list = get_option( 'tsmlt_get_directory_list', [] );
			if ( ! empty( $directory_list[ $dir ] ) ) {
				$directory_list[ $dir ] = [
					'total_items' => 0,
					'counted'     => 0,
					'status'      => 'available',
				];
				update_option( 'tsmlt_get_directory_list', $directory_list );
			}
		}
		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );
		wp_clear_scheduled_hook( 'tsmlt_upload_dir_scan' );
		return [
			'updated'    => true,
			'thedirlist' => get_option( 'tsmlt_get_directory_list', [] ),
			'message'    => $message,
		];
	}
	/**
	 * @return array
	 */
	public function immediately_search_rubbish_file( $request_data ) {
		$parameters = $request_data->get_params();
		$result     = [
			'updated' => false,
			'data'    => [],
			'message' => esc_html__( 'Update failed. Please try to fix', 'media-library-tools' ),
		];

		$directory = $parameters['directory'] ?? '';

		if ( empty( $directory ) ) {
			return $result;
		}
		$updated = Fns::update_rubbish_file_to_database( $directory );
		$dirlist = get_option( 'tsmlt_get_directory_list', [] );

		if ( ! empty( $dirlist[ $directory ] ) ) {
			if ( isset( $dirlist[ $directory ]['total_items'] ) && isset( $dirlist[ $directory ]['counted'] ) ) {
				$directory = absint( $dirlist[ $directory ]['total_items'] ) > absint( $dirlist[ $directory ]['counted'] ) ? $directory : 'nextDir';
			}
		}
		$result['updated'] = (bool) $updated;
		$result['nextDir'] = $directory;
		$result['dirlist'] = $dirlist;
		$result['message'] = $result['updated'] ? esc_html__( 'Done, Be happy.', 'media-library-tools' ) : esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );
		return $result;
	}

	/**
	 * @return array
	 */
	public function clear_schedule() {
		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );
		wp_clear_scheduled_hook( 'tsmlt_upload_dir_scan' );
		return [
			'updated' => true,
			'dirlist' => get_option( 'tsmlt_get_directory_list', [] ),
			'message' => esc_html__( 'Schedule Cleared. Will Execute Soon.', 'media-library-tools' ),
		];
	}

	/**
	 * @return false|string
	 */
	public function get_rubbish_filetype() {
		global $wpdb;
		$cache_key = 'tsmlt_unlisted_filetypes';
		// Table name is fully controlled by the plugin.
		$table_name = esc_sql( $wpdb->prefix . 'tsmlt_unlisted_file' );
		$types      = wp_cache_get( $cache_key );
		if ( false === $types ) {
			$types = $wpdb->get_col( "SELECT DISTINCT file_type FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( $cache_key, $types );
		}
		$rubbish_data = [
			'fileTypes' => is_array( $types ) ? $types : [],
		];
		return wp_json_encode( $rubbish_data );
	}

	/**
	 * Retrieve rubbish files with pagination and filtering.
	 *
	 * @param WP_REST_Request $request_data REST request object.
	 *
	 * @return false|string JSON-encoded response.
	 */
	public function get_rubbish_file( $request_data ) {
		global $wpdb;
		$parameters = $request_data->get_params();
		$options    = get_option( 'tsmlt_settings' );
		$limit      = absint( $options['rubbish_per_page'] ?? 20 );
		$page       = max( 1, absint( $parameters['paged'] ?? 1 ) );
		$offset     = ( $page - 1 ) * $limit;
		$status     = sanitize_text_field( $parameters['fileStatus'] ?? 'show' );
		$statuses   = [ $status ];
		$extensions = ! empty( $parameters['filterExtension'] )
			? [ sanitize_text_field( $parameters['filterExtension'] ) ]
			: Fns::default_file_extensions();
		$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
		// Build placeholders once (must exist for both queries).
		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$type_placeholders   = implode( ',', array_fill( 0, count( $extensions ), '%s' ) );

		$cache_key = 'tsmlt_unlisted_file_' . md5( serialize( [ $statuses, $extensions, $page ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Safe use.

		$existing_row = wp_cache_get( $cache_key );

		if ( false === $existing_row ) {
			$sql = "
			SELECT *
			FROM {$table_name}
			WHERE status IN ($status_placeholders)
			  AND file_type IN ($type_placeholders)
			LIMIT %d OFFSET %d";
			$existing_row = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $statuses, $extensions, [ $limit, $offset ] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
			wp_cache_set( $cache_key, $existing_row );
		}

		/* ---------- COUNT QUERY ---------- */

		$total_cache_key = $cache_key . '_total';
		$total_file      = wp_cache_get( $total_cache_key );

		if ( false === $total_file ) {
			$count_sql = "
			SELECT COUNT(*)
			FROM {$table_name}
			WHERE status IN ($status_placeholders)
			  AND file_type IN ($type_placeholders)
		";
			$total_file = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( $statuses, $extensions ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
			wp_cache_set( $total_cache_key, $total_file );
		}

		return wp_json_encode(
			[
				'mediaFile'    => is_array( $existing_row ) ? $existing_row : [],
				'paged'        => $page,
				'totalPost'    => $total_file,
				'postsPerPage' => $limit,
			]
		);
	}
	
	/**
	 * @return array
	 */
	public function get_registered_image_size() {
		$image_sizes = wp_get_registered_image_subsizes();
		$size        = [];
		foreach ( $image_sizes as $key => $val ) {
			$size[ $key ] = $key . ' (' . $val['width'] . 'x' . $val['height'] . ')';
		}
		return $size;
	}

	/**
	 * Truncate the 'tsmlt_unlisted_file' table.
	 *
	 * This function clears all data from the 'tsmlt_unlisted_file' table.
	 *
	 * @return bool True if the query succeeds, false otherwise.
	 */
	public function delete_all_rows_in_unlisted_file() {
		global $wpdb;
		// Get the table name with prefix.
		$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
		// Ensure the table exists before deleting rows.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching  -- Prepared above.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			// Execute the DELETE query to remove all rows.
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query for truncation.
			$result = $wpdb->query( "DELETE FROM `$table_name`" );
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE `$table_name` AUTO_INCREMENT = 1" ); // Reset auto-increment.
			// Return true if the query succeeded, false otherwise.
		}
		update_option( 'tsmlt_get_directory_list', [] );
		// Table does not exist, return false.
		return true;
	}
}
