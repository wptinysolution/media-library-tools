<?php
/**
 * EXIF Date Manager module.
 *
 * Reads date/metadata from any media file:
 *  - JPEG/TIFF:          exif_read_data() → DateTimeOriginal, DateTimeDigitized, Make, Model
 *  - PNG/WebP/HEIC/etc.: wp_read_image_metadata() → DateTimeOriginal where available
 *  - All other files:    filesystem filemtime() as the "file date"
 *
 * Stores result in post meta and supports syncing to WordPress post date.
 * Works on every attachment type — images, video, audio, PDF, documents, etc.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifDate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ExifManager module — EXIF / file-date feature for all media types.
 */
class ExifManager {

	use SingletonTrait;

	/**
	 * Post-meta key used to store date/metadata for any attachment.
	 */
	const META_KEY = '_tsmlt_exif_data';

	/**
	 * MIME types that carry real EXIF data readable by exif_read_data().
	 */
	const EXIF_MIME_TYPES = [ 'image/jpeg', 'image/tiff' ];

	/**
	 * MIME types supported by wp_read_image_metadata() (partial EXIF).
	 */
	const WP_META_MIME_TYPES = [ 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/avif' ];

	/**
	 * Class Constructor.
	 */
	private function __construct() {
		add_action( 'add_attachment', [ $this, 'maybe_auto_read_on_upload' ] );
		add_filter( 'tsmlt/settings/before/save', [ __CLASS__, 'settings_before_save' ], 10, 2 );
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_exif_attachment_field' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Date / EXIF Reading — supports all file types
	// -------------------------------------------------------------------------

	/**
	 * Read date/metadata from a file.
	 *
	 * Strategy by MIME type:
	 *  1. JPEG / TIFF         → exif_read_data() (full EXIF: dates + camera)
	 *  2. PNG / WebP / HEIC   → wp_read_image_metadata() (partial EXIF where present)
	 *  3. Everything else     → filesystem filemtime() as "file_date"; no camera info
	 *
	 * Returns an array with the keys:
	 *   date_original   — EXIF DateTimeOriginal or filesystem date
	 *   date_digitized  — EXIF DateTimeDigitized (JPEG/TIFF only, else empty)
	 *   camera_make     — camera manufacturer (JPEG/TIFF only, else empty)
	 *   camera_model    — camera model (JPEG/TIFF only, else empty)
	 *   source          — 'exif' | 'wp_meta' | 'filesystem'
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 *
	 * @return array
	 */
	public function read_exif( int $attachment_id ): array {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return [];
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		// ── 1. Full EXIF (JPEG / TIFF) ─────────────────────────────────────
		if ( in_array( $mime, self::EXIF_MIME_TYPES, true ) && function_exists( 'exif_read_data' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- exif_read_data emits warnings on corrupt files.
			$exif = @exif_read_data( $file, 'EXIF', false, false ); // phpcs:ignore
			if ( is_array( $exif ) ) {
				return [
					'date_original'  => isset( $exif['DateTimeOriginal'] ) ? sanitize_text_field( $exif['DateTimeOriginal'] ) : '',
					'date_digitized' => isset( $exif['DateTimeDigitized'] ) ? sanitize_text_field( $exif['DateTimeDigitized'] ) : '',
					'camera_make'    => isset( $exif['Make'] ) ? sanitize_text_field( $exif['Make'] ) : '',
					'camera_model'   => isset( $exif['Model'] ) ? sanitize_text_field( $exif['Model'] ) : '',
					'source'         => 'exif',
				];
			}
		}

		// ── 2. Partial EXIF via WP (PNG / WebP / HEIC / AVIF) ─────────────
		if ( in_array( $mime, self::WP_META_MIME_TYPES, true ) && function_exists( 'wp_read_image_metadata' ) ) {
			$meta = @wp_read_image_metadata( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $meta ) && ! empty( $meta['created_timestamp'] ) && $meta['created_timestamp'] > 0 ) {
				$date_str = gmdate( 'Y:m:d H:i:s', (int) $meta['created_timestamp'] );
				return [
					'date_original'  => $date_str,
					'date_digitized' => '',
					'camera_make'    => sanitize_text_field( $meta['camera'] ?? '' ),
					'camera_model'   => '',
					'source'         => 'wp_meta',
				];
			}
		}

		// ── 3. Filesystem date for all other files (video, audio, PDF, etc.) ─
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mtime = @filemtime( $file ); // phpcs:ignore
		if ( $mtime && $mtime > 0 ) {
			return [
				'date_original'  => gmdate( 'Y:m:d H:i:s', $mtime ),
				'date_digitized' => '',
				'camera_make'    => '',
				'camera_model'   => '',
				'source'         => 'filesystem',
			];
		}

		return [];
	}

	/**
	 * Read date/metadata and store in post meta.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array Stored data.
	 */
	public function read_and_store( int $attachment_id ): array {
		$data = $this->read_exif( $attachment_id );
		update_post_meta( $attachment_id, self::META_KEY, $data );
		return $data;
	}

	/**
	 * Get stored metadata from post meta (does not re-read the file).
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array
	 */
	public function get_stored_exif( int $attachment_id ): array {
		$raw = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return $raw;
	}

	// -------------------------------------------------------------------------
	// Paginated list — all attachment types
	// -------------------------------------------------------------------------

	/**
	 * Fetch a paginated list of ANY attachments with their stored date metadata.
	 *
	 * @param array $params {
	 *     @type int    $paged    Current page (1-based).
	 *     @type int    $per_page Items per page.
	 *     @type string $search   Search keyword.
	 *     @type string $filter   'all'|'has_exif'|'no_exif'.
	 * }
	 *
	 * @return array{items: array, total: int, paged: int, per_page: int}
	 */
	public function get_exif_list( array $params ): array {
		$paged    = max( 1, absint( $params['paged'] ?? 1 ) );
		$per_page = max( 1, absint( $params['per_page'] ?? 20 ) );
		$search   = sanitize_text_field( $params['search'] ?? '' );
		$filter   = sanitize_key( $params['filter'] ?? 'all' );

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			// No post_mime_type restriction — all file types.
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $search ) {
			$query_args['s'] = $search;
		}

		if ( 'has_exif' === $filter ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => self::META_KEY,
					'compare' => 'EXISTS',
				],
			];
		} elseif ( 'no_exif' === $filter ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				],
			];
		}

		$query = new \WP_Query( $query_args );
		$items = [];

		foreach ( $query->posts as $post ) {
			$items[] = $this->build_list_item( $post );
		}

		return [
			'items'    => $items,
			'total'    => $query->found_posts,
			'paged'    => $paged,
			'per_page' => $per_page,
		];
	}

	/**
	 * Build a single list item array for a given post.
	 *
	 * @param \WP_Post $post Attachment post.
	 *
	 * @return array
	 */
	private function build_list_item( \WP_Post $post ): array {
		$exif      = $this->get_stored_exif( $post->ID );
		$thumb_url = wp_get_attachment_image_url( $post->ID, 'thumbnail' );
		if ( ! $thumb_url ) {
			$thumb_url = wp_get_attachment_url( $post->ID );
		}

		$has_date  = ! empty( $exif['date_original'] ) || ! empty( $exif['date_digitized'] );
		$mime      = (string) get_post_mime_type( $post->ID );
		$mime_type = explode( '/', $mime );

		return [
			'attachment_id' => $post->ID,
			'title'         => $post->post_title,
			'url'           => $thumb_url,
			'wp_date'       => $post->post_date,
			'mime_type'     => $mime,
			'file_type'     => $mime_type[0] ?? 'other', // 'image', 'video', 'audio', 'application', etc.
			'exif'          => $exif,
			'has_exif'      => $has_date,
		];
	}

	// -------------------------------------------------------------------------
	// Read EXIF for a single attachment (AJAX)
	// -------------------------------------------------------------------------

	/**
	 * Read, store, and return metadata for a single attachment of any type.
	 *
	 * @param array $params {
	 *     @type int $attachment_id
	 * }
	 *
	 * @return array
	 */
	public function read_single( array $params ): array {
		$id = absint( $params['attachment_id'] ?? 0 );
		if ( ! $id ) {
			return [ 'updated' => false, 'message' => esc_html__( 'Invalid attachment ID.', 'media-library-tools' ) ];
		}
		$data = $this->read_and_store( $id );
		$has_date = ! empty( $data['date_original'] ) || ! empty( $data['date_digitized'] );
		return [
			'updated'  => true,
			'has_date' => $has_date,
			'exif'     => $data,
		];
	}

	// -------------------------------------------------------------------------
	// Sync date → WordPress post date (any file type)
	// -------------------------------------------------------------------------

	/**
	 * Resolve the date to use based on the setting preference.
	 *
	 * @param array  $exif   Stored metadata array.
	 * @param string $prefer 'DateTimeOriginal'|'DateTimeDigitized'.
	 *
	 * @return string Date string (EXIF "YYYY:MM:DD HH:II:SS" or filesystem "YYYY:MM:DD HH:II:SS").
	 */
	private function resolve_exif_date( array $exif, string $prefer ): string {
		if ( 'DateTimeDigitized' === $prefer ) {
			return ! empty( $exif['date_digitized'] ) ? $exif['date_digitized'] : ( $exif['date_original'] ?? '' );
		}
		return ! empty( $exif['date_original'] ) ? $exif['date_original'] : ( $exif['date_digitized'] ?? '' );
	}

	/**
	 * Convert EXIF/filesystem date "YYYY:MM:DD HH:II:SS" → MySQL "YYYY-MM-DD HH:II:SS".
	 *
	 * @param string $exif_date Raw date string.
	 *
	 * @return string MySQL-compatible datetime or empty on failure.
	 */
	private function exif_date_to_mysql( string $exif_date ): string {
		$converted = preg_replace( '/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', trim( $exif_date ) );
		if ( ! $converted ) {
			return '';
		}
		$ts = strtotime( $converted );
		if ( ! $ts || $ts <= 0 ) {
			return '';
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Sync the stored date to the WordPress attachment date.
	 *
	 * For WordPress attachments, post_date is the upload/published date — the date
	 * shown in the Media Library. post_modified is the last-edit timestamp and is
	 * not meaningful to sync.
	 *
	 * Sync options:
	 *  'post_date'  — update post_date (upload date shown in Media Library)
	 *  'post_modified' — update post_modified (last-edited timestamp)
	 *  'both'       — update both post_date and post_modified
	 *
	 * @param array $params {
	 *     @type int    $attachment_id
	 *     @type string $sync_type     'post_date'|'post_modified'|'both'
	 * }
	 *
	 * @return array
	 */
	public function sync_single( array $params ): array {
		$id        = absint( $params['attachment_id'] ?? 0 );
		$sync_type = sanitize_key( $params['sync_type'] ?? 'post_date' );
		$options   = Fns::get_options();
		$prefer    = sanitize_text_field( $options['exif_prefer_field'] ?? 'DateTimeOriginal' );

		if ( ! $id ) {
			return [ 'updated' => false, 'message' => esc_html__( 'Invalid attachment ID.', 'media-library-tools' ) ];
		}

		// Ensure the attachment actually exists.
		if ( ! get_post( $id ) ) {
			return [ 'updated' => false, 'message' => esc_html__( 'Attachment not found.', 'media-library-tools' ) ];
		}

		$exif = $this->get_stored_exif( $id );
		if ( empty( $exif ) ) {
			$exif = $this->read_and_store( $id );
		}

		$raw_date = $this->resolve_exif_date( $exif, $prefer );
		if ( empty( $raw_date ) ) {
			return [ 'updated' => false, 'message' => esc_html__( 'No date found for this file. Please click "Read Date" first.', 'media-library-tools' ) ];
		}

		$mysql_date = $this->exif_date_to_mysql( $raw_date );
		if ( empty( $mysql_date ) ) {
			return [ 'updated' => false, 'message' => esc_html__( 'Could not parse the file date.', 'media-library-tools' ) ];
		}

		$mysql_date_gmt = get_gmt_from_date( $mysql_date );

		$update_data = [ 'ID' => $id ];

		// post_date = the "Uploaded On" date shown in the Media Library.
		if ( in_array( $sync_type, [ 'post_date', 'both' ], true ) ) {
			$update_data['post_date']     = $mysql_date;
			$update_data['post_date_gmt'] = $mysql_date_gmt;
		}

		// post_modified = last-edited timestamp (separate from upload date).
		if ( in_array( $sync_type, [ 'post_modified', 'both' ], true ) ) {
			$update_data['post_modified']     = $mysql_date;
			$update_data['post_modified_gmt'] = $mysql_date_gmt;
		}

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return [ 'updated' => false, 'message' => $result->get_error_message() ];
		}

		return [
			'updated'   => true,
			'message'   => esc_html__( 'Date synced successfully.', 'media-library-tools' ),
			'exif_date' => $mysql_date,
			'sync_type' => $sync_type,
			'new_wp_date' => $mysql_date,
		];
	}

	// -------------------------------------------------------------------------
	// Missing date detector — all file types
	// -------------------------------------------------------------------------

	/**
	 * Return paginated list of attachments (any type) that have no stored date metadata.
	 *
	 * @param array $params {
	 *     @type int $paged    Page number (1-based).
	 *     @type int $per_page Items per page.
	 * }
	 *
	 * @return array{items: array, total: int, paged: int, per_page: int}
	 */
	public function get_missing_exif( array $params ): array {
		$paged    = max( 1, absint( $params['paged'] ?? 1 ) );
		$per_page = max( 1, absint( $params['per_page'] ?? 20 ) );

		$query = new \WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			// No MIME restriction — all file types.
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		$items = [];
		foreach ( $query->posts as $post ) {
			$items[] = $this->build_list_item( $post );
		}

		return [
			'items'    => $items,
			'total'    => $query->found_posts,
			'paged'    => $paged,
			'per_page' => $per_page,
		];
	}

	// -------------------------------------------------------------------------
	// Auto-read on upload
	// -------------------------------------------------------------------------

	/**
	 * Automatically read and store date metadata on upload if the setting is enabled.
	 * Works for all file types.
	 *
	 * @param int $attachment_id Newly uploaded attachment ID.
	 *
	 * @return void
	 */
	public function maybe_auto_read_on_upload( int $attachment_id ): void {
		$options = Fns::get_options();
		if ( empty( $options['exif_auto_read_on_upload'] ) ) {
			return;
		}
		$this->read_and_store( $attachment_id );
	}

	// -------------------------------------------------------------------------
	// Settings save hook
	// -------------------------------------------------------------------------

	/**
	 * Persist EXIF-related settings keys when settings are saved.
	 *
	 * @param array $tsmlt_media Settings array being saved.
	 * @param array $parameters  Raw params from AJAX.
	 *
	 * @return array
	 */
	public static function settings_before_save( array $tsmlt_media, array $parameters ): array {
		$tsmlt_media['exif_prefer_field']       = sanitize_key( $parameters['exif_prefer_field'] ?? 'DateTimeOriginal' );
		$tsmlt_media['exif_auto_read_on_upload'] = ! empty( $parameters['exif_auto_read_on_upload'] ) ? '1' : '';
		$tsmlt_media['exif_date_display_format'] = sanitize_text_field( $parameters['exif_date_display_format'] ?? 'Y-m-d H:i' );
		return $tsmlt_media;
	}

	// -------------------------------------------------------------------------
	// Attachment modal field — all file types
	// -------------------------------------------------------------------------

	/**
	 * Show date/EXIF data in the media attachment edit screen / modal.
	 * Shown for all attachment types, not just images.
	 *
	 * @param array    $form_fields Existing fields.
	 * @param \WP_Post $post        Attachment post.
	 *
	 * @return array
	 */
	public function add_exif_attachment_field( array $form_fields, \WP_Post $post ): array {
		$exif    = $this->get_stored_exif( $post->ID );
		$options = Fns::get_options();
		$format  = sanitize_text_field( $options['exif_date_display_format'] ?? 'Y-m-d H:i' );
		$prefer  = sanitize_text_field( $options['exif_prefer_field'] ?? 'DateTimeOriginal' );

		$raw_date    = $this->resolve_exif_date( $exif, $prefer );
		$mysql_date  = $raw_date ? $this->exif_date_to_mysql( $raw_date ) : '';
		$exif_date   = $mysql_date ? date_i18n( $format, strtotime( $mysql_date ) ) : '';
		$wp_date     = date_i18n( $format, strtotime( $post->post_date ) );
		$camera_make  = esc_html( $exif['camera_make'] ?? '' );
		$camera_model = esc_html( $exif['camera_model'] ?? '' );
		$source       = esc_html( $exif['source'] ?? '' );
		$nonce        = wp_create_nonce( Fns::NONCE_ID );

		// Label varies by source.
		$date_label = __( 'File Date:', 'media-library-tools' );
		if ( 'exif' === $source ) {
			$date_label = __( 'Date Taken:', 'media-library-tools' );
		} elseif ( 'wp_meta' === $source ) {
			$date_label = __( 'Date Created:', 'media-library-tools' );
		}

		ob_start();
		?>
		<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:10px 12px;font-size:13px;">
			<?php if ( $exif_date ) : ?>
				<div style="margin-bottom:6px;">
					<strong><?php echo esc_html( $date_label ); ?></strong>
					<?php echo esc_html( $exif_date ); ?>
					<?php if ( $source ) : ?>
						<span style="margin-left:6px;font-size:11px;color:#8c8f94;">(<?php echo esc_html( $source ); ?>)</span>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div style="margin-bottom:6px;color:#a7aaad;">
					<?php esc_html_e( 'No date metadata found. Click "Read Date" to scan the file.', 'media-library-tools' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $camera_make || $camera_model ) : ?>
				<div style="margin-bottom:6px;">
					<strong><?php esc_html_e( 'Camera:', 'media-library-tools' ); ?></strong>
					<?php echo esc_html( trim( $camera_make . ' ' . $camera_model ) ); ?>
				</div>
			<?php endif; ?>
			<div style="margin-bottom:6px;">
				<strong><?php esc_html_e( 'WP Date:', 'media-library-tools' ); ?></strong>
				<?php echo esc_html( $wp_date ); ?>
			</div>
			<div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<button
					type="button"
					data-attachment-id="<?php echo absint( $post->ID ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					data-action="read"
					style="background:#f0f0f1;color:#1d2327;border:1px solid #8c8f94;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:12px;"
					onclick="tsmltExifAction(this,'read')"
				>
					<?php esc_html_e( 'Read Date', 'media-library-tools' ); ?>
				</button>
				<?php if ( $mysql_date ) : ?>
				<button
					type="button"
					data-attachment-id="<?php echo absint( $post->ID ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					data-action="sync"
					style="background:#2271b1;color:#fff;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:12px;"
					onclick="tsmltExifAction(this,'sync')"
				>
					<?php esc_html_e( 'Sync to WP Date', 'media-library-tools' ); ?>
				</button>
				<?php endif; ?>
				<span id="tsmlt-exif-msg-<?php echo absint( $post->ID ); ?>" style="font-size:12px;"></span>
			</div>
			<script>
			function tsmltExifAction(btn, action) {
				var id    = btn.getAttribute('data-attachment-id');
				var nonce = btn.getAttribute('data-nonce');
				var msg   = document.getElementById('tsmlt-exif-msg-' + id);
				var orig  = btn.textContent;
				btn.disabled = true;
				btn.textContent = '<?php echo esc_js( __( 'Working...', 'media-library-tools' ) ); ?>';
				var ajaxAction = action === 'sync' ? 'tsmlt_exif_sync_single' : 'tsmlt_exif_read_single';
				var params = action === 'sync'
					? { attachment_id: parseInt(id, 10), sync_type: 'both' }
					: { attachment_id: parseInt(id, 10) };
				var body = new URLSearchParams({
					action: ajaxAction,
					nonce: nonce,
					params: JSON.stringify(params)
				});
				fetch(ajaxurl, { method: 'POST', body: body })
					.then(function(r){ return r.json(); })
					.then(function(res){
						if (res.success) {
							msg.style.color = '#00a32a';
							msg.textContent = action === 'sync'
								? '<?php echo esc_js( __( 'Synced!', 'media-library-tools' ) ); ?>'
								: '<?php echo esc_js( __( 'Done!', 'media-library-tools' ) ); ?>';
						} else {
							msg.style.color = '#d63638';
							msg.textContent = (res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Error.', 'media-library-tools' ) ); ?>';
						}
						btn.disabled = false;
						btn.textContent = orig;
					})
					.catch(function(){
						msg.style.color = '#d63638';
						msg.textContent = '<?php echo esc_js( __( 'Request failed.', 'media-library-tools' ) ); ?>';
						btn.disabled = false;
						btn.textContent = orig;
					});
			}
			</script>
		</div>
		<?php
		$html = ob_get_clean();

		$form_fields['tsmlt_exif_data'] = [
			'label' => __( 'File Date Info', 'media-library-tools' ),
			'input' => 'html',
			'html'  => $html,
		];

		return $form_fields;
	}
}
