<?php
/**
 * Main FilterHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use enshrined\svgSanitize\Sanitizer;
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
		add_filter( 'plugin_action_links_' . TSMLT_BASENAME, [ __CLASS__, 'plugins_setting_links' ] );
		add_filter( 'manage_media_columns', [ __CLASS__, 'media_custom_column' ] );
		add_filter( 'manage_upload_sortable_columns', [ __CLASS__, 'media_sortable_columns' ] );
		add_filter( 'posts_clauses', [ __CLASS__, 'media_sortable_columns_query' ], 1, 2 );
		add_filter( 'request', [ __CLASS__, 'media_sort_by_alt' ], 20, 2 );
		add_filter( 'media_row_actions', [ __CLASS__, 'filter_post_row_actions' ], 11, 2 );
		add_filter( 'default_hidden_columns', [ __CLASS__, 'hidden_columns' ], 99, 2 );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'plugin_row_meta' ], 10, 2 );
		// Image Size.
		add_filter( 'intermediate_image_sizes_advanced', [ __CLASS__, 'custom_image_sizes' ] );
		if ( Fns::is_support_mime_type( 'svg' ) ) {
			// SVG File Permission.
			add_filter( 'mime_types', [ __CLASS__, 'add_support_mime_types' ], 99 );
			add_filter( 'wp_check_filetype_and_ext', [ __CLASS__, 'allow_svg_upload' ], 10, 4 );
			// Sanitize the SVG file before it is uploaded to the server.
			add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'sanitize_svg' ] );
			// SVG size.
			add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'svgs_generate_svg_attachment_metadata' ], 10, 3 );
		}
	}
	/**
	 * Remove the `srcset` attribute for SVG images in WordPress.
	 *
	 * WordPress automatically generates a `srcset` attribute for responsive images,
	 * but since SVGs are vector graphics and do not require different resolutions,
	 * this function disables `srcset` for SVG images.
	 *
	 * @param array|false $sources      An array of image sources, or false if no sources exist.
	 * @param array       $size_array   Image width and height in pixels.
	 * @param string      $image_src    The URL of the image.
	 *
	 * @return array|false Modified sources array or false for SVG images to disable `srcset`.
	 */
	public static function remove_srcset_for_svg( $sources, $size_array, $image_src ) {
		if ( false !== strpos( $image_src, '.svg' ) ) {
			return false; // Disable srcset for SVG.
		}
		return $sources;
	}
	/**
	 * @param array $metadata image metadata.
	 * @param int   $attachment_id image id.
	 *
	 * @return array
	 */
	public static function svgs_generate_svg_attachment_metadata( $metadata, $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );
		if ( 'image/svg+xml' === $mime ) {
			$svg_path      = get_attached_file( $attachment_id );
			$upload_dir    = wp_upload_dir();
			$relative_path = $svg_path ? str_replace( $upload_dir['basedir'] . '/', '', $svg_path ) : '';
			// Get the path relative to /uploads/.
			$filename   = basename( $svg_path );
			$dimensions = self::svgs_get_dimensions( $svg_path );
			$metadata   = [
				'width'  => intval( $dimensions->width ),
				'height' => intval( $dimensions->height ),
				'file'   => $relative_path,
			];
			$height     = intval( $dimensions->height );
			$width      = intval( $dimensions->width );
			// Generate sizes array for future implementations, if needed.
			$sizes = [];

			foreach ( get_intermediate_image_sizes() as $s ) {
				$sizes[ $s ] = [
					'width'  => '',
					'height' => '',
					'crop'   => false,
				];
				if ( 0 !== $width && 0 !== $height ) {
					if ( isset( $_wp_additional_image_sizes[ $s ]['width'] ) ) {
						$width_current_size = intval( $_wp_additional_image_sizes[ $s ]['width'] );
					} else {
						$width_current_size = get_option( "{$s}_size_w" );
					}
					if ( $width > $height ) {
						$ratio      = round( $width / $height, 2 );
						$new_height = round( $width_current_size / $ratio );
					} else {
						$ratio      = round( $height / $width, 2 );
						$new_height = round( $width_current_size * $ratio );
					}
					$sizes[ $s ]['width']  = $width_current_size;
					$sizes[ $s ]['height'] = $new_height;
					$sizes[ $s ]['crop']   = false;
				} else {
					if ( isset( $_wp_additional_image_sizes[ $s ]['width'] ) ) {
						$sizes[ $s ]['width'] = intval( $_wp_additional_image_sizes[ $s ]['width'] );
					} else {
						$sizes[ $s ]['width'] = get_option( "{$s}_size_w" );
					}
					if ( isset( $_wp_additional_image_sizes[ $s ]['height'] ) ) {
						$sizes[ $s ]['height'] = intval( $_wp_additional_image_sizes[ $s ]['height'] );
					} else {
						$sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
					}
					if ( isset( $_wp_additional_image_sizes[ $s ]['crop'] ) ) {
						$sizes[ $s ]['crop'] = intval( $_wp_additional_image_sizes[ $s ]['crop'] );
					} else {
						$sizes[ $s ]['crop'] = get_option( "{$s}_crop" );
					}
				}
				$sizes[ $s ]['file']      = $filename;
				$sizes[ $s ]['mime-type'] = 'image/svg+xml';
			}
			$metadata['sizes'] = $sizes;
		}
		return $metadata;
	}

	/**
	 * @param $svg
	 *
	 * @return object
	 */
	private static function svgs_get_dimensions( $svg ) {
		$svg_content = '';
		// Check if $svg is a URL or a local file path.
		if ( filter_var( $svg, FILTER_VALIDATE_URL ) ) {
			// For remote SVGs, use wp_remote_get().
			$response = wp_remote_get( $svg );
			if ( is_wp_error( $response ) ) {
				return (object) [
					'width'  => 0,
					'height' => 0,
				];
			}
			$svg_content = wp_remote_retrieve_body( $response );
		} else {
			// For local files, use WP_Filesystem to read the file content.
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			global $wp_filesystem;
			WP_Filesystem();
			$svg_content = $wp_filesystem->get_contents( $svg );
		}
		if ( empty( $svg_content ) ) {
			return (object) [
				'width'  => 0,
				'height' => 0,
			];
		}
		$svg = simplexml_load_string( $svg_content );
		if ( false === $svg ) {
			$width  = '0';
			$height = '0';
		} else {
			$attributes = $svg->attributes();
			$width      = (string) $attributes->width;
			$height     = (string) $attributes->height;
		}
		return (object) [
			'width'  => $width,
			'height' => $height,
		];
	}

	/**
	 * @param array $sizes images size.
	 *
	 * @return array
	 */
	public static function custom_image_sizes( $sizes ) {
		$options = Fns::get_options();
		// add your image sizes, i.e.
		if ( ! empty( $options['deregistered_image_sizes'] ) ) {
			foreach ( $options['deregistered_image_sizes'] as $size ) {
				unset( $sizes[ $size ] );
			}
		}
		return $sizes;
	}
	/**
	 * Sanitize an uploaded SVG file.
	 *
	 * @param array $file Uploaded file information.
	 *
	 * @return array
	 * @since 1.1.3
	 */
	public static function sanitize_svg( $file ) {
		// Only proceed if the file is an SVG.
		if ( 'image/svg+xml' !== $file['type'] ) {
			return $file;
		}
		// Set maximum file size (500KB max).
		$max_file_size = apply_filters( 'tsmlt_upload_max_svg_file_size', 500 * 1024 );
		$size_in_kb    = $max_file_size / 1024;
		$size_in_mb    = $size_in_kb / 1024;
		$size_message  = ( $size_in_kb < 1024 ) ? $size_in_kb . 'KB' : number_format( $size_in_mb, 2 ) . 'MB';
		// Validate file size.
		if ( $file['size'] > $max_file_size ) {
			$file['error'] = sprintf(
			/* translators: file size */
				esc_html__( 'The uploaded SVG exceeds the maximum allowed file size of %s.', 'media-library-tools' ),
				esc_html( $size_message )
			);
			return $file;
		}
		// Sanitize the SVG file.
		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		$sanitizer->removeXMLTag( true );
		$sanitizer->minify( true );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg_content = file_get_contents( $file['tmp_name'] );
		$clean_svg   = $sanitizer->sanitize( $svg_content );
		// If the file is not safe, return an error.
		if ( false === $clean_svg ) {
			$file['error'] = esc_html__( 'This SVG file contains unsafe content and cannot be uploaded.', 'media-library-tools' );
			return $file;
		}
		// Write sanitized SVG content back to the file.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file['tmp_name'], $clean_svg );
		return $file;
	}
	/**
	 * Fix SVG size.
	 *
	 * @param array|boolean $out output.
	 * @param int           $id image id.
	 *
	 * @return array|mixed
	 */
	public static function fix_svg_size_attributes( $out, $id ) {
		if ( ! is_admin() ) {
			return $out;
		}
		$image_url = wp_get_attachment_url( $id );
		$file_ext  = pathinfo( $image_url, PATHINFO_EXTENSION );

		if ( 'svg' !== $file_ext ) {
			return $out;
		}
		return [ $image_url, null, null, false ];
	}

	/**
	 * Check template screen
	 *
	 * @return array
	 */
	public static function allow_svg_upload( $data, $file, $filename, $mimes ) {
		$filetype = wp_check_filetype( $filename, $mimes );

		return [
			'ext'             => $filetype['ext'],
			'type'            => $filetype['type'],
			'proper_filename' => $data['proper_filename'],
		];
	}

	/**
	 * Check template screen
	 *
	 * @return boolean
	 */
	public static function hidden_columns( $hidden, $screen ) {
		if ( ! empty( $hidden ) || empty( $screen->base ) || 'upload' !== $screen->base ) {
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
	public static function add_support_mime_types( $mimes ) {
		$mimes['svg|svgz'] = 'image/svg+xml';
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
	 *
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
			esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash', 'media-library-tools' ), $att_title ) ),
			_x( 'Trash', 'verb' )
		);
		$delete_ays        = " onclick='return showNotice.warn();'";
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete aria-button-if-js"%s aria-label="%s">%s</a>',
			wp_nonce_url( "post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID ),
			$delete_ays,
			/* translators: %s: Attachment title. */
			esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $att_title ) ),
			__( 'Delete Permanently', 'media-library-tools' )
		);

		return $actions;
	}

	/**
	 * Sortable column function.
	 *
	 * @param array $vars query var.
	 *
	 * @return array
	 */
	public static function media_sort_by_alt( $vars ) {

		if ( ! isset( $vars['orderby'] ) ) {
			return $vars;
		}

		if ( 'alt' !== $vars['orderby'] ) {
			return $vars;
		}
		// TODO:: IF key is not exist then ignoting the items. Ii need to fix.
		$vars = array_merge(
			$vars,
			[
				'orderby'    => 'meta_value',
				'meta_query' => [
					'relation' => 'OR',
					[
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					],
					[
						'relation' => 'OR', // Add a nested "OR" relation to handle empty alt text.
						[
							'key'     => '_wp_attachment_image_alt',
							'compare' => 'EXISTS',
							'value'   => '',
						],
						[
							'key'     => '_wp_attachment_image_alt',
							'compare' => 'EXISTS',
						],
					],
				],
			]
		);
		return $vars;
	}

	/**
	 * Add new column to media table
	 *
	 * @param array $columns customize column.
	 *
	 * @return array
	 */
	public static function media_custom_column( $columns ) {
		$author   = $columns['author'] ?? '';
		$date     = $columns['date'] ?? '';
		$comments = $columns['comments'] ?? '';
		$parent   = $columns['parent'] ?? '';
		unset( $columns['author'] );
		unset( $columns['date'] );
		unset( $columns['comments'] );
		unset( $columns['parent'] );
		$columns['alt']         = __( 'Alt', 'media-library-tools' );
		$columns['caption']     = __( 'Caption', 'media-library-tools' );
		$columns['description'] = __( 'Description', 'media-library-tools' );
		$columns['category']    = __( 'Groups', 'media-library-tools' );
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
	 *
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
	 *
	 * @return array
	 */
	public static function media_sortable_columns_query( $pieces, $query ) {
		global $wpdb;
		if ( ! $query->is_main_query() ) {
			return $pieces;
		}
		$orderby = $query->get( 'orderby' );
		if ( ! $orderby ) {
			return $pieces;
		}
		$order = strtoupper( $query->get( 'order' ) );
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			return $pieces;
		}
		switch ( $orderby ) {
			case 'caption':
				$pieces['orderby'] = " $wpdb->posts.post_excerpt $order ";
				break;
			case 'description':
				$pieces['orderby'] = " $wpdb->posts.post_content $order ";
				break;

		}

		return $pieces;
	}

	/**
	 * @param array $links default plugin action link
	 *
	 * @return array [array] plugin action link
	 */
	public static function plugins_setting_links( $links ) {
		$new_links                       = [];
		$new_links['mediaedit_settings'] = '<a href="' . admin_url( 'upload.php?page=media-library-tools' ) . '">' . esc_html__( 'Settings', 'media-library-tools' ) . '</a>';
		if ( ! tsmlt()->has_pro() ) {
			$links['tsmlt_pro'] = '<a href="' . esc_url( tsmlt()->pro_version_link() ) . '" style="color: #39b54a; font-weight: bold;" target="_blank">' . esc_html__( 'Go Pro', 'media-library-tools' ) . '</a>';
		}

		return array_merge( $new_links, $links );
	}

	/**
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( $file == TSMLT_BASENAME ) {
			$report_url         = 'https://www.wptinysolutions.com/contact';
			$row_meta['issues'] = sprintf( '%2$s <a target="_blank" href="%1$s">%3$s</a>', esc_url( $report_url ), esc_html__( 'Facing issue?', 'media-library-tools' ), '<span style="color: red">' . esc_html__( 'Please open a support ticket.', 'media-library-tools' ) . '</span>' );

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}
}
