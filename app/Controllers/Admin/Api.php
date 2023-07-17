<?php

namespace TinySolutions\mlt\Controllers\Admin;

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

class Api {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {
		$this->namespace     = 'TinySolutions/mlt/v1';
		$this->resource_name = '/media';
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register our routes.
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, $this->resource_name, array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_media' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
		register_rest_route( $this->namespace, $this->resource_name . '/update', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'update_single_media' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
		register_rest_route( $this->namespace, $this->resource_name . '/bulk/submit', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'media_submit_bulk_action' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
		register_rest_route( $this->namespace, $this->resource_name . '/filter/getdates', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_dates' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
		register_rest_route( $this->namespace, $this->resource_name . '/getterms', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_terms' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
		register_rest_route( $this->namespace, $this->resource_name . '/getoptions', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_options' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
		register_rest_route( $this->namespace, $this->resource_name . '/updateoptins', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'update_option' ],
			'permission_callback' => [ $this, 'login_permission_callback' ],
		) );
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
	public function update_option( $request_data ) {

		$result = [
			'updated' => false,
			'message' => esc_html__( 'Update failed. Maybe change not found. ', 'tsmlt-media-tools' )
		];

		$parameters = $request_data->get_params();

		// error_log( print_r( $parameters , true) . "\n\n", 3, __DIR__.'/logg.txt');

		$limit = absint( get_user_option( 'upload_per_page', get_current_user_id() ) );

		$limit = $limit ? $limit : 20;

		$tsmlt_media = get_option( 'tsmlt_settings', [] );

		$media_per_page = ! empty( $parameters['media_per_page'] ) ? $parameters['media_per_page'] : $limit;

		if ( ! empty( $parameters['media_per_page'] ) ) {
			$tsmlt_media['media_per_page'] = absint( $media_per_page );
		}

		if ( ! empty( $parameters['media_table_column'] ) ) {
			$tsmlt_media['media_table_column'] = $parameters['media_table_column'];
		}

		$tsmlt_media['default_alt_text'] = ! empty( $parameters['default_alt_text'] ) ? $parameters['default_alt_text'] : '';

		$tsmlt_media['default_caption_text'] = ! empty( $parameters['default_caption_text'] ) ? $parameters['default_caption_text'] : '';

		$tsmlt_media['default_desc_text'] = ! empty( $parameters['default_desc_text'] ) ? $parameters['default_desc_text'] : '';

		$tsmlt_media['media_default_alt'] = ! empty( $parameters['media_default_alt'] ) ? $parameters['media_default_alt'] : '';

		$tsmlt_media['media_default_caption'] = ! empty( $parameters['media_default_caption'] ) ? $parameters['media_default_caption'] : '';

		$tsmlt_media['media_default_desc'] = ! empty( $parameters['media_default_desc'] ) ? $parameters['media_default_desc'] : '';

		$tsmlt_media['others_file_support'] = ! empty( $parameters['others_file_support'] ) ? $parameters['others_file_support'] : [];

		$options = update_option( 'tsmlt_settings', $tsmlt_media );

		$result['updated'] = boolval( $options );

		$result['message'] = ! $result['updated'] ? $result['message'] . esc_html__( 'Please try to fix', 'tsmlt-media-tools' ) : esc_html__( 'Updated. Be happy', 'tsmlt-media-tools' );

		//error_log( print_r( $result , true) . "\n\n", 3, __DIR__.'/logg.txt');

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
		$terms       = get_terms( array(
			'taxonomy'   => 'tsmlt_category',
			'hide_empty' => false,
		) );
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
		$key        = 'tsmlt_date_query_' . date( "_m_Y" );
		$dates      = get_transient( $key );

		if ( empty( $dates ) ) {
			delete_transient( $key );
			$get_date = $wpdb->get_col( $date_query );
			if ( $get_date ) {
				$dates = [];
				foreach ( $get_date as $date ) {
					$dates[] = [
						'value' => $date,
						'label' => date( 'F Y', strtotime( $date ) ),
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
	 * @return array|WP_Error
	 */
	public function update_single_media( $request_data ) {
		$parameters = $request_data->get_params();
		$result     = [
			'updated' => false,
			'message' => esc_html__( 'Update failed. Please try to fix', 'tsmlt-media-tools' )
		];
		$submit     = [];

		if ( empty( $parameters['ID'] ) ) {
			return $result;
		}

		if ( ! empty( $parameters['post_title'] ) ) {
			$submit['post_title'] = trim( $parameters['post_title'] );
			$result['message']    = esc_html__( 'The Title has been saved.', 'tsmlt-media-tools' );
		}

		if ( isset( $parameters['post_excerpt'] ) ) {
			$submit['post_excerpt'] = trim( $parameters['post_excerpt'] );
			$result['message']      = esc_html__( 'The Caption has been saved.', 'tsmlt-media-tools' );
		}

		if ( isset( $parameters['post_content'] ) ) {
			$submit['post_content'] = trim( $parameters['post_content'] );
			$result['message']      = esc_html__( 'Content has been saved.', 'tsmlt-media-tools' );
		}

		if ( isset( $parameters['alt_text'] ) ) {
			$result['updated'] = update_post_meta( $parameters['ID'], '_wp_attachment_image_alt', trim( $parameters['alt_text'] ) );
			$result['message'] = esc_html__( 'Saved.', 'tsmlt-media-tools' );
		}

		if ( isset( $parameters['newname'] ) ) {
			$new_file_name = $parameters['newname'] . '.' . $parameters['postsdata']['fileextension'];
			if ( Fns::wp_rename_attachment( $parameters['ID'], $new_file_name ) ) {
				$result['updated'] = true;
				$result['message'] = esc_html__( 'Saved.', 'tsmlt-media-tools' );
			}
		}

		if ( ! empty( $submit ) ) {
			$submit['ID']      = $parameters['ID'];
			$result['updated'] = wp_update_post( $submit );
		}
		$result['message'] = $result['updated'] ? $result['message'] : esc_html__( 'Update failed. Please try to fix', 'tsmlt-media-tools' );

		return $result;
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

		$orderby = 'menu_order';
		$status  = 'inherit';
		if ( ! empty( $parameters['filtering'] ) && boolval( $parameters['filtering'] ) ) {
			$status = ! empty( $parameters['status'] ) ? $parameters['status'] : $status;
		}

		$order = ! empty( $parameters['order'] ) ? $parameters['order'] : 'DESC';
		$paged = ! empty( $parameters['paged'] ) ? $parameters['paged'] : 1;

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
			'post_type'      => 'attachment',  // Retrieve only attachments
			'posts_per_page' => $limit,
			'post_status'    => $status,
			'orderby'        => $orderby,
			'order'          => $order,
			'paged'          => absint( $paged ),
		];

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
			$args['orderby']    = 'meta_value'; // Order by meta value
			$args['meta_key']   = '_wp_attachment_image_alt'; // Meta key to use for ordering
		}
		if ( ! empty( $parameters['categories'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'tsmlt_category',
					'field'    => 'term_id',
					'terms'    => $parameters['categories'],
				),
			);
		}
		add_filter( 'posts_clauses', [ Fns::class, 'custom_orderby_post_excerpt_content' ], 10, 2 );

		$_posts_query = new \WP_Query( $args );
		$get_posts    = [];
		foreach ( $_posts_query->posts as $post ) {
			$thefile       = [];
			$metadata      = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
			$attached_file = get_attached_file( $post->ID );
			if ( ! empty( $metadata['file'] ) ) {
				$thefile['mainfilepath']  = dirname( $attached_file );
				$thefile['mainfilename']  = basename( $metadata['file'] );
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

			$terms          = get_terms( 'tsmlt_category' );
			$tsmlt_category = [];
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$tsmlt_category[] = array(
						'id'   => $term->term_id,
						'name' => $term->name
					);
				}
			}

			$get_posts[] = [
				'ID'             => $post->ID,
				'post_title'     => $post->post_title,
				'post_excerpt'   => $post->post_excerpt,
				'post_content'   => $post->post_content,
				'post_name'      => $post->post_name,
				'guid'           => $post->guid,
				'uploaddir'      => $uploaddir,
				'alt_text'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				'categories'     => wp_json_encode( $tsmlt_category ),
				'metadata'       => $metadata,
				'thefile'        => $thefile,
				'post_mime_type' => $post->post_mime_type
			];
		}
		$query_data = [
			'posts'          => $get_posts,
			'posts_per_page' => absint( $limit ),
			'total_post'     => $_posts_query->found_posts,
			'paged'          => absint( $paged ),
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
			'message' => esc_html__( 'Update failed. Please try to fix', 'tsmlt-media-tools' )
		];
		if ( empty( $parameters['type'] ) || empty( $parameters['ids'] ) ) {
			return $result;
		}

		$ids = $parameters['ids'];
		switch ( $parameters['type'] ) {
			case 'trash':
			case 'inherit':
				$query   = $wpdb->prepare( "UPDATE $wpdb->posts SET post_status = %s WHERE post_type = 'attachment' AND ID IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ")",
					$parameters['type'],
					...$ids
				);
				$updated = wp_cache_get( md5( $query ), 'attachment-query' );
				if ( false === $updated ) {
					$updated = $wpdb->query( $query );
					wp_cache_set( md5( $query ), $updated, 'attachment-query' );
				}
				$result['updated'] = (bool) $updated;
				$result['message'] = $updated ? esc_html__( 'Done. Be happy.', 'tsmlt-media-tools' ) : esc_html__( 'Failed. Please try to fix', 'tsmlt-media-tools' );
				break;
			case 'delete':
				$delete = [];
				foreach ( $ids as $id ) {
					$delete[] = wp_delete_attachment( $id, true );
				}

				$result['updated'] = count( $delete ) === count( $ids );
				$result['message'] = $result['updated'] ? esc_html__( 'Deleted. Be happy.', 'tsmlt-media-tools' ) : esc_html__( 'Deleted failed. Please try to fix', 'tsmlt-media-tools' );
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
				$set_data = rtrim( $set_data, ", " );
				if ( ! empty( $set_data ) ) {
					$query  = $wpdb->prepare( "UPDATE $wpdb->posts SET $set_data WHERE post_type = 'attachment' AND ID IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ")",
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
				$result['message'] = $update ? esc_html__( 'Updated. Be happy.', 'tsmlt-media-tools' ) : esc_html__( 'Update failed. Please try to fix', 'tsmlt-media-tools' );

				break;
			default:
		}

		return $result;
	}

}




