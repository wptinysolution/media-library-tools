<?php

namespace TinySolutions\mlt\Controllers\Admin;

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Query;

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
		return current_user_can( 'upload_files' );
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
		$date_query = $wpdb->prepare( "SELECT DISTINCT DATE_FORMAT( post_date, '%Y-%m') AS YearMonth FROM $wpdb->posts WHERE post_type = %s", 'attachment' );
		$key        = 'tsmlt_date_query_' . date( '_m_Y' );
		$dates      = get_transient( $key );

		if ( empty( $dates ) ) {
			delete_transient( $key );
			$get_date = $wpdb->get_col( $date_query );
			if ( $get_date ) {
				$dates = [];
				foreach ( $get_date as $date ) {
					$dates[] = [
						'value' => $date,
						'label' => date( 'M Y', strtotime( $date ) ),
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
		$result = [
			'updated' => false,
			'message' => esc_html__( 'Rename failed. Please try to fix', 'media-library-tools' ),
		];
		if ( ! tsmlt()->has_pro() && in_array( $parameters['newname'], [ 'bulkRenameByPostTitle', 'bulkRenameBySKU' ], true ) ) {
			$result['message'] = esc_html__( 'Please activate the license key.', 'media-library-tools' );
			return $result;
		}
		$attachment = get_post( $parameters['ID'] );
		$new_name   = $parameters['newname'];
		$rename_to  = '';
		if ( $attachment ) {
			$post_id = $attachment->post_parent ?: Fns::set_thumbnail_parent_id( $parameters['ID'] );
			if ( $post_id && 'bulkRenameByPostTitle' === $new_name ) {
				$rename_to = Fns::add_filename_prefix_suffix( get_the_title( $post_id ) );
			} elseif ( $post_id && 'bulkRenameBySKU' === $new_name ) {
				$rename_to = Fns::add_filename_prefix_suffix( get_post_meta( $post_id, '_sku', true ) );
			} elseif ( ! in_array( $new_name, [ 'bulkRenameByPostTitle', 'bulkRenameBySKU' ], true ) ) {
				$rename_to = $new_name;
			}
		}
		if ( ! empty( $rename_to ) && Fns::wp_rename_attachment( $parameters['ID'], $rename_to ) ) {
			$result['updated'] = true;
			$result['message'] = esc_html__( 'Renamed.', 'media-library-tools' );
		} else {
			$result['message'] = esc_html__( 'Rename failed. Maybe file permission mismatch or the file doesn’t exist.', 'media-library-tools' );
		}
		return $result;
	}

	/**
	 * @param $parameters
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
	 * @param $parameters
	 * @param $result
	 *
	 * @return mixed
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
			$args['meta_query'] = [
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
			$args['tax_query'] = [
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
			} else {
				/*
				$parent_id = Fns::set_thumbnail_parent_id( $post->ID );
				if ( $parent_id ) {
					$parent_title     = get_the_title( $parent_id );
					$parent_permalink = get_the_permalink( $parent_id );
				}
				*/
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

	/***
	 * @param $request_data
	 *
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

		$ids = $parameters['ids'] ?? [];
		switch ( $parameters['type'] ) {
			case 'searchUses':
				foreach ( $ids as $id ) {
					Fns::set_thumbnail_parent_id( $id );
				}
				$result['updated'] = true;
				$result['message'] = $result['updated'] ? esc_html__( 'Updated. Be happy.', 'media-library-tools' ) : esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );
				break;
			case 'trash':
			case 'inherit':
				$query   = $wpdb->prepare(
					"UPDATE $wpdb->posts SET post_status = %s WHERE post_type = 'attachment' AND ID IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					$parameters['type'],
					...$ids
				);
				$updated = wp_cache_get( md5( $query ), 'attachment-query' );
				if ( false === $updated ) {
					$updated = $wpdb->query( $query );
					wp_cache_set( md5( $query ), $updated, 'attachment-query' );
				}
				$result['updated'] = (bool) $updated;
				$result['message'] = $updated ? esc_html__( 'Done. Be happy.', 'media-library-tools' ) : esc_html__( 'Failed. Please try to fix', 'media-library-tools' );
				break;
			case 'delete':
				$delete = [];
				foreach ( $ids as $id ) {
					$delete[] = wp_delete_attachment( $id, true );
				}

				$result['updated'] = count( $delete ) === count( $ids );
				$result['message'] = $result['updated'] ? esc_html__( 'Deleted. Be happy.', 'media-library-tools' ) : esc_html__( 'Deleted failed. Please try to fix', 'media-library-tools' );
				break;
			case 'bulkedit':
				$data       = $parameters['data'];
				$categories = $parameters['post_categories'];
				$set_data   = '';
				if ( ! empty( $data['post_title'] ) ) {
					$set_data .= "post_title= '{$data['post_title']}', ";
				}
				if ( ! empty( $data['caption'] ) ) {
					$set_data .= "post_excerpt='{$data['caption']}', ";
				}
				if ( ! empty( $data['post_description'] ) ) {
					$set_data .= "post_content ='{$data['post_description']}', ";
				}
				$update   = false;
				$set_data = rtrim( $set_data, ', ' );
				if ( ! empty( $set_data ) ) {
					$query  = $wpdb->prepare(
						"UPDATE $wpdb->posts SET $set_data WHERE post_type = 'attachment' AND ID IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
						...$ids
					);
					$update = wp_cache_get( md5( $query ), 'attachment-query' );
					if ( false === $update ) {
						$update = $wpdb->query( $query );
						wp_cache_set( md5( $query ), $update, 'attachment-query' );
					}
				}

				$alt = ! empty( $data['alt_text'] ) ? $data['alt_text'] : null;
				foreach ( $ids as $id ) {
					if ( $alt ) {
						$update = update_post_meta( $id, '_wp_attachment_image_alt', trim( $alt ) );
					}
					if ( ! empty( $categories ) ) {
						$update = wp_set_object_terms( $id, $categories, tsmlt()->category );
					}
				}
				$result['updated'] = (bool) $update;
				$result['message'] = $update ? esc_html__( 'Updated. Be happy.', 'media-library-tools' ) : esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );

				break;
				$result = apply_filters( 'tsmlt/bulk/rename', $result, $parameters );
				break;
			default:
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
		$cache_key  = 'tsmlt_unlisted_filetypes';
		$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
		// Check if the file_path already exists in the table using cached data.
		$types = wp_cache_get( $cache_key );
		if ( ! $types ) {
			$types = $wpdb->get_col( "SELECT DISTINCT file_type FROM $table_name" );
			// Cache the query result.
			wp_cache_set( $cache_key, $types );
		}
		$rubbish_data = [
			'fileTypes' => is_array( $types ) ? $types : [],
		];
		return wp_json_encode( $rubbish_data );
	}

	/**
	 * @return false|string
	 */
	public function get_rubbish_file( $request_data ) {
		global $wpdb;
		$parameters = $request_data->get_params();

		$options = get_option( 'tsmlt_settings' );
		$limit   = absint( $options['rubbish_per_page'] ?? 20 );

		$status = $parameters['fileStatus'] ?? 'show';

		$extensions = ! empty( $parameters['filterExtension'] ) ? [ $parameters['filterExtension'] ] : Fns::default_file_extensions();

		// Add single quotes around each status value.
		$extensions = array_map(
			function ( $extension ) {
				return "'" . esc_sql( $extension ) . "'";
			},
			$extensions
		);

		$extensions = implode( ', ', $extensions );

		$page   = $parameters['paged'] ?? 1;
		$offset = ( $page - 1 ) * $limit; // Calculate the offset based on the page number.

		$cache_key   = 'tsmlt_unlisted_file';
		$table_name  = $wpdb->prefix . 'tsmlt_unlisted_file';
		$in_statuses = [ $status ]; // Add the status values to exclude.

		// Add single quotes around each status value.
		$in_statuses = array_map(
			function ( $status ) {
				return "'" . esc_sql( $status ) . "'";
			},
			$in_statuses
		);

		$placeholders_status = implode( ', ', $in_statuses );

		// Check if the file_path already exists in the table using cached data.
		$existing_row = wp_cache_get( $cache_key );
		if ( ! $existing_row ) {
			$query        = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE status IN ( $placeholders_status ) AND file_type IN ( $extensions ) LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
			$existing_row = $wpdb->get_results( $query );
			// Cache the query result.
			wp_cache_set( $cache_key, $existing_row );
		}

		// Media File Count.
		$total_file_cache = $cache_key . '_total';
		// Check if the file_path already exists in the table using cached data.
		$total_file = wp_cache_get( $total_file_cache );
		if ( ! $total_file ) {
			// Query to retrieve total number of posts.
			$total_query = $wpdb->prepare( "SELECT COUNT(*) as total_count FROM $table_name WHERE status IN ( $placeholders_status ) AND file_type IN ( $extensions )", $table_name );
			$total_file  = $wpdb->get_var( $total_query );
			wp_cache_set( $total_file_cache, $total_file );
		}

		$rubbish_data = [
			'mediaFile'    => is_array( $existing_row ) ? $existing_row : [],
			'paged'        => absint( $page ),
			'totalPost'    => absint( $total_file ),
			'postsPerPage' => absint( $limit ),
		];

		return wp_json_encode( $rubbish_data );
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			// Execute the DELETE query to remove all rows.
			$result = $wpdb->query( "DELETE FROM `$table_name`" );
			$wpdb->query( "ALTER TABLE `$table_name` AUTO_INCREMENT = 1" );
			// Return true if the query succeeded, false otherwise.
		}
		update_option( 'tsmlt_get_directory_list', [] );
		// Table does not exist, return false.
		return true;
	}
}
