<?php
/**
 * Regenerate Thumbnails — rebuilds the image sizes of one image, chosen
 * images or the whole library, in background batches.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Regenerate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Regenerates WordPress image sizes.
 *
 * A job is a queue of attachment IDs fixed when it starts (one image,
 * chosen images or every image in the library), so media added or deleted
 * meanwhile cannot shift it. WP-Cron works through it in batches; each job
 * has an ID, and a second job cannot start while one is running.
 *
 * Each image is rebuilt by regenerate_attachment(): under its own lock
 * (RegenerateLock), only when no compression, conversion or EXIF removal is
 * running on it, by the job owner's edit_post permission checked at that
 * moment. Sizes are made from the full-size original when WordPress keeps
 * one (scaled and rotated uploads), into temporary files next to the image;
 * existing files are replaced only after every size was made and checked.
 * Sizes disabled in Media Library Tools are not generated and their files
 * are kept. While an image is rebuilt, is_regenerating() is true and the
 * on-upload automation settings read as off, so regeneration never acts
 * like a new upload.
 */
class RegenerateThumbnails {

	/**
	 * Option key for persistent regen state. Survives page reloads and tab
	 * closes so background ticks can resume across sessions.
	 */
	const STATE_OPTION = 'tsmlt_regenerate_state';

	/**
	 * Single-event hook fired by WP-Cron between batches. Each invocation
	 * processes one batch and self-reschedules until the run completes
	 * or the user cancels.
	 */
	const TICK_HOOK = 'tsmlt_regenerate_tick';

	/**
	 * Seconds between background ticks. Kept to 1 second so WP-Cron fires
	 * the next batch on the very next visitor request — there's no benefit
	 * to idling between batches, and WP-Cron's own locking prevents overlap.
	 */
	const TICK_INTERVAL = 1;

	/**
	 * Per-tick batch size for background runs.
	 *
	 * Sized to finish a tick comfortably under typical `max_execution_time`
	 * (30s) on a mid-range host with Imagick. Sites with very large source
	 * images can lower this via the `tsmlt_regenerate_tick_batch_size` filter.
	 */
	const TICK_BATCH_SIZE = 50;

	/**
	 * Cap on retained recent-error / recent-success entries in the state
	 * row. Prevents the option payload from growing unbounded on huge runs.
	 */
	const RECENT_CAP = 50;

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Seconds after which a running job without a scheduled tick or any
	 * progress counts as stalled and may be replaced by a new start.
	 */
	const STALE_AFTER = 900;

	/**
	 * Name prefix of the temporary files a rebuild writes next to the image.
	 */
	const TEMP_PREFIX = '.tsmlt-regen-';

	/**
	 * Seconds after which a leftover temporary file (from a request that
	 * died) is removed by the next rebuild in its folder.
	 */
	const TEMP_MAX_AGE = 3600;

	/**
	 * Job type: every image in the library.
	 */
	const JOB_LIBRARY = 'library';

	/**
	 * Job type: chosen attachment IDs.
	 */
	const JOB_SELECTED = 'selected';

	/**
	 * Result codes that mean the image was left alone (not attempted).
	 */
	const SKIP_CODES = [ 'not_found', 'trashed', 'not_image', 'forbidden', 'locked' ];

	/**
	 * Attachment being rebuilt in this request, or 0.
	 *
	 * @var int
	 */
	private static $current = 0;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Whether an image's sizes are being rebuilt in this request; with an
	 * ID, whether that image is. Code reacting to the metadata hooks (as on
	 * upload) can use it to stay out of a regeneration.
	 *
	 * @param int $attachment_id Attachment ID, or 0 for any.
	 *
	 * @return bool
	 */
	public static function is_regenerating( int $attachment_id = 0 ): bool {
		return 0 !== self::$current && ( 0 === $attachment_id || self::$current === $attachment_id );
	}

	/**
	 * Return the total number of image attachments in the media library.
	 *
	 * @return int
	 */
	public function get_total(): int {
		$result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_mime_type', 'LIKE', 'image/%' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();

		return (int) ( $result[0]['total'] ?? 0 );
	}

	/**
	 * IDs of every image in the library, lowest first.
	 *
	 * @param int $offset Rows to skip.
	 * @param int $limit  Most rows, or 0 for all.
	 *
	 * @return int[]
	 */
	public function library_ids( int $offset = 0, int $limit = 0 ): array {
		$query = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_mime_type', 'LIKE', 'image/%' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' );
		if ( $limit > 0 ) {
			$query->limit( $limit )->offset( $offset );
		}
		$rows = $query->get();
		return array_map( 'intval', array_column( is_array( $rows ) ? $rows : [], 'ID' ) );
	}

	/**
	 * Rebuild one batch of the library by position, for the page-driven
	 * `tsmlt_regenerate_batch` action, as the current user. Each image goes
	 * through regenerate_attachment(). Positions shift when media is added or
	 * deleted meanwhile; background jobs use a fixed ID queue instead.
	 *
	 * @param int $offset     Zero-based offset into the library.
	 * @param int $batch_size Number of images.
	 *
	 * @return array{processed: int, total: int, complete: bool, deleted_total: int, errors: array, succeeded: array}
	 */
	public function regenerate_batch( int $offset = 0, int $batch_size = 10 ): array {
		$total     = $this->get_total();
		$ids       = $this->library_ids( $offset, max( 1, $batch_size ) );
		$errors    = [];
		$succeeded = [];
		$deleted   = 0;

		foreach ( $ids as $attachment_id ) {
			$result = $this->regenerate_attachment( $attachment_id, get_current_user_id() );
			if ( 'regenerated' === $result['status'] ) {
				$succeeded[] = self::done_entry( $result );
				$deleted    += count( $result['deleted_sizes'] );
			} else {
				$errors[] = self::error_entry( $result );
			}
		}

		$new_offset = $offset + count( $ids );
		return [
			'processed'     => $new_offset,
			'total'         => $total,
			'complete'      => $new_offset >= $total || [] === $ids,
			'deleted_total' => $deleted,
			'errors'        => $errors,
			'succeeded'     => $succeeded,
		];
	}

	/**
	 * Rebuild the image sizes of one attachment, for a user.
	 *
	 * The user must still have manage_options and edit_post on the image;
	 * trashed, missing and non-image attachments are skipped, and so is an
	 * image being compressed, converted, regenerated or having its EXIF
	 * removed. Nothing is changed unless every size was made and checked.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       User the work is done for.
	 *
	 * @return array{id: int, file: string, status: string, code: string, deleted_sizes: string[]}
	 *               status: regenerated, skipped or failed; code: '' or a reason.
	 */
	public function regenerate_attachment( int $attachment_id, int $user_id ): array {
		$result         = [
			'id'            => $attachment_id,
			'file'          => '',
			'status'        => 'skipped',
			'code'          => '',
			'deleted_sizes' => [],
		];
		$file           = '';
		$refusal        = self::refusal( $attachment_id, $user_id, $file );
		$result['file'] = '' !== $file ? wp_basename( $file ) : '';
		if ( '' !== $refusal ) {
			return self::outcome( $result, $refusal );
		}

		// Own lock first, then the others: an operation starting meanwhile
		// sees this lock and stays out.
		$lock = RegenerateLock::acquire( $attachment_id );
		if ( null === $lock ) {
			return self::outcome( $result, 'locked' );
		}
		try {
			if ( AttachmentProcessor::instance()->is_locked( $attachment_id )
				|| AttachmentConverter::instance()->is_locked( $attachment_id )
				|| ScanStartLock::instance()->is_held( ExifStripper::LOCK_PREFIX . $attachment_id ) ) {
				return self::outcome( $result, 'locked' );
			}
			self::$current = $attachment_id;
			try {
				$rebuilt = $this->rebuild( $attachment_id, $file );
			} catch ( \Throwable $e ) {
				$rebuilt = [
					'code'          => 'generation_failed',
					'deleted_sizes' => [],
				];
			}
			$result['deleted_sizes'] = $rebuilt['deleted_sizes'];
			return '' === $rebuilt['code'] ? array_merge( $result, [ 'status' => 'regenerated' ] ) : self::outcome( $result, $rebuilt['code'] );
		} finally {
			self::$current = 0;
			RegenerateLock::release( $attachment_id, $lock );
		}
	}

	/**
	 * Why an attachment cannot be regenerated for a user before any lock or
	 * file work, or ''. Shared by regenerate_attachment() and preflight().
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param int    $user_id       User.
	 * @param string $file          Set to the attached file when there is one.
	 *
	 * @return string Result code or ''.
	 */
	private static function refusal( int $attachment_id, int $user_id, string &$file ): string {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return 'not_found';
		}
		if ( 'trash' === $post->post_status ) {
			return 'trashed';
		}
		if ( 'inherit' !== $post->post_status ) {
			return 'not_found';
		}
		if ( 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			return 'not_image';
		}
		if ( ! user_can( $user_id, 'manage_options' ) || ! user_can( $user_id, 'edit_post', $attachment_id ) ) {
			return 'forbidden';
		}
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file ) {
			return 'file_missing';
		}
		if ( false !== strpos( $file, '://' ) || wp_is_stream( $file ) ) {
			return 'remote_storage';
		}
		return '';
	}

	/**
	 * Rebuild an attachment's sizes while the caller already holds its
	 * regeneration lock (a media rename, which renames the files first and
	 * holds the lock for the whole operation). Same pipeline as
	 * regenerate_attachment(): from the full-size original, into temporary
	 * files, the on-upload automation off, old files kept until replaced.
	 * Permissions and the other operations' locks are the caller's job.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{code: string, deleted_sizes: string[]} code '' on success.
	 */
	public function rebuild_held( int $attachment_id ): array {
		if ( ! RegenerateLock::is_held( $attachment_id ) ) {
			return [
				'code'          => 'locked',
				'deleted_sizes' => [],
			];
		}
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || false !== strpos( $file, '://' ) || wp_is_stream( $file ) ) {
			return [
				'code'          => 'file_missing',
				'deleted_sizes' => [],
			];
		}
		self::$current = $attachment_id;
		try {
			return $this->rebuild( $attachment_id, $file );
		} catch ( \Throwable $e ) {
			return [
				'code'          => 'generation_failed',
				'deleted_sizes' => [],
			];
		} finally {
			self::$current = 0;
		}
	}

	/**
	 * Whether an attachment could be regenerated now for a user, by the same
	 * rules regenerate_attachment() applies, without changing anything: no
	 * lock is taken, no file written. Also checks that the attached file is
	 * readable and, for scaled and rotated uploads, that the recorded
	 * full-size original is.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       User.
	 *
	 * @return string '' when ready, otherwise a result code.
	 */
	public function preflight( int $attachment_id, int $user_id ): string {
		$file    = '';
		$refusal = self::refusal( $attachment_id, $user_id, $file );
		if ( '' !== $refusal ) {
			return $refusal;
		}
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return 'file_missing';
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( null === self::source_file( $attachment_id, $file, is_array( $meta ) ? $meta : [] ) ) {
			return 'original_missing';
		}
		if ( RegenerateLock::is_held( $attachment_id )
			|| AttachmentProcessor::instance()->is_locked( $attachment_id )
			|| AttachmentConverter::instance()->is_locked( $attachment_id )
			|| ScanStartLock::instance()->is_held( ExifStripper::LOCK_PREFIX . $attachment_id ) ) {
			return 'locked';
		}
		return '';
	}

	/**
	 * Whether a job is running and not stalled (a new start would be refused).
	 *
	 * @return bool
	 */
	public function has_active_job(): bool {
		$state = $this->get_state();
		return 'running' === $state['status'] && ! $this->is_stale( $state );
	}

	/**
	 * A fixed, user-facing message for a result code (never a raw error).
	 *
	 * @param string $code Result code.
	 *
	 * @return string
	 */
	public static function message( string $code ): string {
		$messages = [
			'not_found'          => __( 'Not found in the media library.', 'media-library-tools' ),
			'trashed'            => __( 'The image is in the trash.', 'media-library-tools' ),
			'not_image'          => __( 'Not an image.', 'media-library-tools' ),
			'forbidden'          => __( 'You are not allowed to edit this image.', 'media-library-tools' ),
			'locked'             => __( 'The image is being compressed, converted, regenerated or having its EXIF removed. Try again later.', 'media-library-tools' ),
			'remote_storage'     => __( 'The image is stored off this server, which is not supported.', 'media-library-tools' ),
			'file_missing'       => __( 'File not found on disk.', 'media-library-tools' ),
			'original_missing'   => __( 'The full-size original recorded for this image is missing or unreadable. Nothing was changed.', 'media-library-tools' ),
			'editor_unavailable' => __( 'No image editor on this server can open this file. Nothing was changed.', 'media-library-tools' ),
			'copy_failed'        => __( 'A temporary copy of the image could not be made. Nothing was changed.', 'media-library-tools' ),
			'generation_failed'  => __( 'An image size could not be created. Nothing was changed.', 'media-library-tools' ),
			'replace_failed'     => __( 'Some image size files could not be replaced. The image\'s records match the files on disk.', 'media-library-tools' ),
		];
		return $messages[ $code ] ?? __( 'The image could not be regenerated.', 'media-library-tools' );
	}

	/**
	 * Set the result's code and status (skipped or failed).
	 *
	 * @param array  $result Result.
	 * @param string $code   Code.
	 *
	 * @return array
	 */
	private static function outcome( array $result, string $code ): array {
		$result['code']   = $code;
		$result['status'] = in_array( $code, self::SKIP_CODES, true ) ? 'skipped' : 'failed';
		return $result;
	}

	/**
	 * Rebuild an image's sizes into temporary files, then replace the old
	 * files and records.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file          Attached file.
	 *
	 * @return array{code: string, deleted_sizes: string[]} code '' on success.
	 */
	private function rebuild( int $attachment_id, string $file ): array {
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return self::failure( 'file_missing' );
		}
		if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$dir = dirname( $file );
		self::remove_stale_temp_files( $dir );

		$meta = self::base_metadata( $attachment_id, $file );
		if ( null === $meta ) {
			return self::failure( 'editor_unavailable' );
		}
		$source = self::source_file( $attachment_id, $file, $meta );
		if ( null === $source ) {
			return self::failure( 'original_missing' );
		}

		$registered = wp_get_registered_image_subsizes();
		$wanted     = apply_filters( 'intermediate_image_sizes_advanced', $registered, $meta, $attachment_id );
		$wanted     = is_array( $wanted ) ? $wanted : [];
		$disabled   = array_diff( array_keys( $registered ), array_keys( $wanted ) );

		$temp_base   = self::TEMP_PREFIX . strtolower( wp_generate_password( 12, false, false ) );
		$extension   = pathinfo( $source, PATHINFO_EXTENSION );
		$temp_source = $dir . '/' . $temp_base . ( '' !== $extension ? '.' . $extension : '' );
		$temps       = [ $temp_source ];

		try {
			if ( ! @copy( $source, $temp_source ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Same-folder working copy; WP_Filesystem may need credentials in cron. Failure is reported.
				return self::failure( 'copy_failed' );
			}
			$editor = wp_get_image_editor( $temp_source );
			if ( is_wp_error( $editor ) ) {
				return self::failure( 'editor_unavailable' );
			}
			// As WordPress does: with stored EXIF data, rotate the source first.
			if ( ! empty( $meta['image_meta'] ) && is_wp_error( $editor->maybe_exif_rotate() ) ) {
				return self::failure( 'generation_failed' );
			}
			$made = self::make_sizes( $editor, $wanted, $temp_base, pathinfo( $source, PATHINFO_FILENAME ), $dir, $temps );
			if ( null === $made ) {
				return self::failure( 'generation_failed' );
			}

			list( $replaced, $failed ) = self::replace_files( $made, $dir );
			$old_sizes                 = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : [];
			$sizes                     = self::merge_sizes( $old_sizes, $replaced, $wanted, $disabled, $failed );
			$deleted                   = $failed ? [] : self::remove_replaced_files( $dir, $old_sizes, $replaced, $sizes, $disabled, $file, $meta, $attachment_id );

			$meta['sizes'] = $sizes;
			if ( is_file( $file ) ) {
				$meta['filesize'] = wp_filesize( $file );
			}
			/** This filter is documented in wp-admin/includes/image.php */
			$filtered = apply_filters( 'wp_generate_attachment_metadata', $meta, $attachment_id, 'update' );
			wp_update_attachment_metadata( $attachment_id, is_array( $filtered ) ? $filtered : $meta );

			return [
				'code'          => $failed ? 'replace_failed' : '',
				'deleted_sizes' => $deleted,
			];
		} finally {
			foreach ( $temps as $temp ) {
				if ( is_file( $temp ) ) {
					wp_delete_file( $temp );
				}
			}
		}
	}

	/**
	 * A rebuild failure with a code.
	 *
	 * @param string $code Result code.
	 *
	 * @return array{code: string, deleted_sizes: string[]}
	 */
	private static function failure( string $code ): array {
		return [
			'code'          => $code,
			'deleted_sizes' => [],
		];
	}

	/**
	 * The image's stored metadata, or for an image without any, the basics
	 * read from its file.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file          Attached file.
	 *
	 * @return array|null Null when the file is not a readable image.
	 */
	private static function base_metadata( int $attachment_id, string $file ): ?array {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) && [] !== $meta ) {
			return $meta;
		}
		$dimensions = wp_getimagesize( $file );
		if ( ! $dimensions ) {
			return null;
		}
		$image_meta = wp_read_image_metadata( $file );
		return [
			'width'      => (int) $dimensions[0],
			'height'     => (int) $dimensions[1],
			'file'       => _wp_relative_upload_path( $file ),
			'sizes'      => [],
			'image_meta' => is_array( $image_meta ) ? $image_meta : [],
		];
	}

	/**
	 * The file sizes are made from: the full-size original WordPress records
	 * for scaled and rotated uploads, or the attached file. Never a guessed
	 * file: a recorded original that is missing, unreadable or elsewhere
	 * refuses the image.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file          Attached file.
	 * @param array  $meta          Attachment metadata.
	 *
	 * @return string|null
	 */
	private static function source_file( int $attachment_id, string $file, array $meta ): ?string {
		if ( empty( $meta['original_image'] ) ) {
			return $file;
		}
		$source = (string) wp_get_original_image_path( $attachment_id );
		if ( '' === $source || wp_basename( $source ) !== (string) $meta['original_image'] || dirname( $source ) !== dirname( $file ) || ! is_file( $source ) || ! is_readable( $source ) ) {
			return null;
		}
		return $source;
	}

	/**
	 * Make every wanted size into temporary files and check each one.
	 *
	 * @param object $editor    Image editor loaded with the temporary source.
	 * @param array  $wanted    Sizes to make.
	 * @param string $temp_base Temporary name base.
	 * @param string $stem      Final name base (the source's name).
	 * @param string $dir       Folder.
	 * @param array  $temps     Temporary files made, for clean-up.
	 *
	 * @return array|null Size name => [temp, meta]; null when a size failed.
	 */
	private static function make_sizes( $editor, array $wanted, string $temp_base, string $stem, string $dir, array &$temps ): ?array {
		$made = [];
		foreach ( $wanted as $name => $size_data ) {
			$size = $editor->make_subsize( $size_data );
			if ( is_wp_error( $size ) ) {
				// A size larger than the image is not made, as on upload.
				if ( 'error_getting_dimensions' === $size->get_error_code() ) {
					continue;
				}
				return null;
			}
			$temp_file = $dir . '/' . (string) $size['file'];
			$temps[]   = $temp_file;
			$check     = wp_getimagesize( $temp_file );
			if ( 0 !== strpos( (string) $size['file'], $temp_base ) || ! $check || (int) $check[0] !== (int) $size['width'] || (int) $check[1] !== (int) $size['height'] ) {
				return null;
			}
			$size['file']  = $stem . substr( (string) $size['file'], strlen( $temp_base ) );
			$made[ $name ] = [
				'temp' => $temp_file,
				'meta' => $size,
			];
		}
		return $made;
	}

	/**
	 * Move each made size over its final name, by a rename in the folder. If
	 * one fails, the rest are not moved.
	 *
	 * @param array  $made Made sizes.
	 * @param string $dir  Folder.
	 *
	 * @return array{0: array, 1: bool} Records of the sizes now on disk, and whether a move failed.
	 */
	private static function replace_files( array $made, string $dir ): array {
		$replaced = [];
		$moved    = [];
		foreach ( $made as $name => $item ) {
			if ( ! isset( $moved[ $item['temp'] ] ) ) {
				if ( ! @rename( $item['temp'], $dir . '/' . $item['meta']['file'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Atomic same-folder replacement; failure is reported.
					return [ $replaced, true ];
				}
				$moved[ $item['temp'] ] = true;
			}
			$replaced[ $name ] = $item['meta'];
		}
		return [ $replaced, false ];
	}

	/**
	 * The size records after a rebuild: disabled sizes dropped (their files
	 * are kept), regenerated sizes replaced, sizes no longer registered kept;
	 * after a failed move, wanted sizes not moved keep their old record.
	 *
	 * @param array $old_sizes Records before.
	 * @param array $replaced  Records of the sizes moved into place.
	 * @param array $wanted    Sizes that were to be made.
	 * @param array $disabled  Disabled size names.
	 * @param bool  $failed    Whether a move failed.
	 *
	 * @return array
	 */
	private static function merge_sizes( array $old_sizes, array $replaced, array $wanted, array $disabled, bool $failed ): array {
		$sizes = [];
		foreach ( $old_sizes as $name => $record ) {
			if ( in_array( $name, $disabled, true ) ) {
				continue;
			}
			if ( isset( $wanted[ $name ] ) && ( ! $failed || isset( $replaced[ $name ] ) ) ) {
				continue;
			}
			$sizes[ $name ] = $record;
		}
		return array_merge( $sizes, $replaced );
	}

	/**
	 * Delete the old file of each size that was regenerated under a new file
	 * name (its dimensions changed), unless any record, the image itself, its
	 * original or a pre-edit backup still uses that name.
	 *
	 * @param string $dir           Folder of the image.
	 * @param array  $old_sizes     Size records before.
	 * @param array  $replaced      Regenerated size records.
	 * @param array  $sizes         Size records after.
	 * @param array  $disabled      Disabled size names.
	 * @param string $file          Attached file.
	 * @param array  $meta          Attachment metadata.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return string[] Size names whose old file was deleted.
	 */
	private static function remove_replaced_files( string $dir, array $old_sizes, array $replaced, array $sizes, array $disabled, string $file, array $meta, int $attachment_id ): array {
		$keep = [ wp_basename( $file ), (string) ( $meta['original_image'] ?? '' ) ];
		foreach ( $sizes as $record ) {
			$keep[] = (string) ( $record['file'] ?? '' );
		}
		foreach ( $disabled as $name ) {
			$keep[] = (string) ( $old_sizes[ $name ]['file'] ?? '' );
		}
		$backups = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		foreach ( is_array( $backups ) ? $backups : [] as $backup ) {
			$keep[] = (string) ( $backup['file'] ?? '' );
		}
		$deleted = [];
		foreach ( $replaced as $name => $record ) {
			$old = (string) ( $old_sizes[ $name ]['file'] ?? '' );
			if ( '' === $old || wp_basename( $old ) !== $old || in_array( $old, $keep, true ) ) {
				continue;
			}
			if ( is_file( $dir . '/' . $old ) ) {
				wp_delete_file( $dir . '/' . $old );
				$deleted[] = (string) $name;
			}
		}
		return $deleted;
	}

	/**
	 * Remove this feature's temporary files older than TEMP_MAX_AGE from a
	 * folder: leftovers of a request that ended without its own clean-up.
	 *
	 * @param string $dir Folder.
	 *
	 * @return void
	 */
	private static function remove_stale_temp_files( string $dir ): void {
		$pattern = preg_replace( '/([*?\[])/', '[$1]', $dir ) . '/' . self::TEMP_PREFIX . '*';
		foreach ( (array) glob( $pattern ) as $leftover ) {
			if ( is_string( $leftover ) && is_file( $leftover ) && ( time() - (int) filemtime( $leftover ) ) > self::TEMP_MAX_AGE ) {
				wp_delete_file( $leftover );
			}
		}
	}

	/**
	 * Entry for recent_done.
	 *
	 * @param array $result Result.
	 *
	 * @return array
	 */
	private static function done_entry( array $result ): array {
		return [
			'id'            => $result['id'],
			'file'          => $result['file'],
			'deleted_sizes' => $result['deleted_sizes'],
		];
	}

	/**
	 * Entry for recent_errors: file name and a fixed message, no paths or
	 * raw errors.
	 *
	 * @param array $result Result.
	 *
	 * @return array
	 */
	private static function error_entry( array $result ): array {
		return [
			'id'    => $result['id'],
			'file'  => '' !== $result['file'] ? $result['file'] : 'ID:' . $result['id'],
			'error' => self::message( $result['code'] ),
			'code'  => $result['code'],
		];
	}

	// -------------------------------------------------------------------------
	// Background jobs
	// -------------------------------------------------------------------------

	/**
	 * Default shape for the persisted state row.
	 *
	 * @return array
	 */
	private function default_state(): array {
		return [
			'job_id'        => '',
			'job_type'      => '',
			'user_id'       => 0,
			'status'        => 'idle',
			'queue'         => [],
			'offset'        => 0,
			'total'         => 0,
			'started_at'    => 0,
			'updated_at'    => 0,
			'cancelled_at'  => 0,
			'errors_count'  => 0,
			'success_count' => 0,
			'skipped_count' => 0,
			'failed_count'  => 0,
			'deleted_total' => 0,
			'recent_errors' => [],
			'recent_done'   => [],
		];
	}

	/**
	 * The stored job, merged over the defaults, read past the object cache.
	 *
	 * @return array
	 */
	public function get_state(): array {
		wp_cache_delete( self::STATE_OPTION, 'options' );
		$stored = get_option( self::STATE_OPTION, [] );
		return array_merge( $this->default_state(), is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Store the job.
	 *
	 * @param array $state State.
	 *
	 * @return void
	 */
	private function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Progress for the Regenerate page: the job without its queue or owner,
	 * plus whether a tick is scheduled and whether the job stalled.
	 * `offset` is the number of images processed.
	 *
	 * @return array
	 */
	public function get_progress(): array {
		$state = $this->get_state();
		unset( $state['queue'], $state['user_id'] );
		$state['tick_scheduled'] = (bool) wp_next_scheduled( self::TICK_HOOK );
		$state['stale']          = $this->is_stale( $this->get_state() );
		return $state;
	}

	/**
	 * Whether a running job stalled: no tick scheduled and no progress for
	 * STALE_AFTER seconds (for example WP-Cron stopped or a request died).
	 *
	 * @param array $state State.
	 *
	 * @return bool
	 */
	public function is_stale( array $state ): bool {
		return 'running' === $state['status']
			&& ! wp_next_scheduled( self::TICK_HOOK )
			&& ( time() - (int) $state['updated_at'] ) > self::STALE_AFTER;
	}

	/**
	 * Start a job: every image in the library, or the given attachment IDs.
	 * Refused while a job is running, unless it stalled.
	 *
	 * @param int[]|null $attachment_ids IDs, or null for the whole library.
	 *
	 * @return array|WP_Error Progress after start.
	 */
	public function start( ?array $attachment_ids = null ) {
		$current = $this->get_state();
		if ( 'running' === $current['status'] && ! $this->is_stale( $current ) ) {
			return new WP_Error( 'tsmlt_regenerate_running', __( 'A thumbnail regeneration is already running. Wait for it to finish or stop it first.', 'media-library-tools' ) );
		}
		if ( null === $attachment_ids ) {
			$queue = $this->library_ids();
			$type  = self::JOB_LIBRARY;
		} else {
			$queue = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );
			$type  = self::JOB_SELECTED;
			if ( [] === $queue ) {
				return new WP_Error( 'tsmlt_regenerate_no_images', __( 'Select at least one image.', 'media-library-tools' ) );
			}
		}

		wp_clear_scheduled_hook( self::TICK_HOOK );
		$now   = time();
		$state = array_merge(
			$this->default_state(),
			[
				'job_id'     => 'tsmlt_regen_' . bin2hex( random_bytes( 8 ) ),
				'job_type'   => $type,
				'user_id'    => get_current_user_id(),
				'status'     => [] === $queue ? 'done' : 'running',
				'queue'      => $queue,
				'total'      => count( $queue ),
				'started_at' => $now,
				'updated_at' => $now,
			]
		);
		$this->save_state( $state );
		if ( [] !== $queue ) {
			wp_schedule_single_event( $now + 1, self::TICK_HOOK );
		}
		return $this->get_progress();
	}

	/**
	 * Cancel the running job, keeping its results. With a job ID, only that
	 * job; a finished job is left as it is.
	 *
	 * @param string $job_id Job ID, or '' for the current job.
	 *
	 * @return array|WP_Error Progress after cancel.
	 */
	public function cancel( string $job_id = '' ) {
		$state = $this->get_state();
		if ( '' !== $job_id && $job_id !== $state['job_id'] ) {
			return new WP_Error( 'tsmlt_regenerate_job_mismatch', __( 'That is not the current regeneration job.', 'media-library-tools' ) );
		}
		if ( 'running' === $state['status'] ) {
			wp_clear_scheduled_hook( self::TICK_HOOK );
			$state['status']       = 'cancelled';
			$state['queue']        = [];
			$state['cancelled_at'] = time();
			$state['updated_at']   = time();
			$this->save_state( $state );
		}
		return $this->get_progress();
	}

	/**
	 * Process the next batch of the running job.
	 *
	 * The batch is taken off the queue and saved before any image is
	 * touched, so another driver never takes the same images; the job is
	 * re-read before each image, and a cancelled or replaced job stops the
	 * batch. Counters are added to the job only if it is still the same job.
	 *
	 * @return array Progress after the batch.
	 */
	public function run_batch(): array {
		$state = $this->get_state();
		if ( 'running' !== $state['status'] ) {
			return $this->get_progress();
		}
		$job_id              = (string) $state['job_id'];
		$batch_size          = (int) apply_filters( 'tsmlt_regenerate_tick_batch_size', self::TICK_BATCH_SIZE );
		$batch               = array_splice( $state['queue'], 0, max( 1, min( 200, $batch_size ) ) );
		$state['updated_at'] = time();
		$this->save_state( $state );

		$tally = [
			'done'    => [],
			'errors'  => [],
			'success' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'deleted' => 0,
		];
		foreach ( $batch as $attachment_id ) {
			$now = $this->get_state();
			if ( $now['job_id'] !== $job_id || 'running' !== $now['status'] ) {
				break;
			}
			$result = $this->regenerate_attachment( (int) $attachment_id, (int) $state['user_id'] );
			if ( 'regenerated' === $result['status'] ) {
				++$tally['success'];
				$tally['deleted'] += count( $result['deleted_sizes'] );
				$tally['done'][]   = self::done_entry( $result );
			} else {
				++$tally[ 'skipped' === $result['status'] ? 'skipped' : 'failed' ];
				$tally['errors'][] = self::error_entry( $result );
			}
		}

		$fresh = $this->get_state();
		if ( $fresh['job_id'] !== $job_id ) {
			return $this->get_progress();
		}
		$processed              = $tally['success'] + $tally['skipped'] + $tally['failed'];
		$fresh['offset']        = (int) $fresh['offset'] + $processed;
		$fresh['success_count'] = (int) $fresh['success_count'] + $tally['success'];
		$fresh['skipped_count'] = (int) $fresh['skipped_count'] + $tally['skipped'];
		$fresh['failed_count']  = (int) $fresh['failed_count'] + $tally['failed'];
		$fresh['errors_count']  = (int) $fresh['errors_count'] + $tally['skipped'] + $tally['failed'];
		$fresh['deleted_total'] = (int) $fresh['deleted_total'] + $tally['deleted'];
		$fresh['recent_done']   = array_slice( array_merge( (array) $fresh['recent_done'], $tally['done'] ), -self::RECENT_CAP );
		$fresh['recent_errors'] = array_slice( array_merge( (array) $fresh['recent_errors'], $tally['errors'] ), -self::RECENT_CAP );
		$fresh['updated_at']    = time();
		if ( 'running' === $fresh['status'] && [] === $fresh['queue'] ) {
			$fresh['status'] = 'done';
		}
		$this->save_state( $fresh );
		return $this->get_progress();
	}

	/**
	 * Background tick handler: one batch, then the next tick while the job
	 * is still running. Registered against `self::TICK_HOOK` in `CronJobHooks`.
	 *
	 * @return void
	 */
	public static function run_tick(): void {
		$self = self::instance();
		if ( 'running' !== $self->get_state()['status'] ) {
			return;
		}
		// Best effort: some hosts ignore it.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		$self->run_batch();
		if ( 'running' === $self->get_state()['status'] && ! wp_next_scheduled( self::TICK_HOOK ) ) {
			wp_schedule_single_event( time() + self::TICK_INTERVAL, self::TICK_HOOK );
		}
	}
}
