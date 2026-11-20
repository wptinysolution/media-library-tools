<?php
/**
 * Conversion service for the MCP conversion abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\ConfirmationToken;
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use WP_Error;
use WP_Post;

/**
 * Shared rules of the MCP conversion abilities: input checks, Pro-only
 * options (refused without a licence, never dropped silently), per-image
 * status (the engine's own validation, locks and file ownership), the
 * one-time confirmation token and the effective settings.
 *
 * Conversion itself is never done here: the abilities start the existing
 * asynchronous conversion job (CompressionJob::start_conversion()) for 1 to
 * 20 chosen images. Whole-library conversion, deleting and regenerating
 * copies are not offered through MCP. Nothing here returns file paths,
 * file names or stored error texts.
 */
class McpConversion {

	/**
	 * Most IDs in one preview.
	 *
	 * @var int
	 */
	const MAX_IDS = 20;

	/**
	 * Seconds a confirmation token stays valid.
	 *
	 * @var int
	 */
	const TOKEN_TTL = 600;

	/**
	 * Option-name prefix of stored tokens.
	 *
	 * @var string
	 */
	const TOKEN_OPTION_PREFIX = 'tsmlt_conversion_token_';

	/**
	 * Input keys of a preview.
	 *
	 * @var string[]
	 */
	const PREVIEW_KEYS = [ 'ids', 'formats', 'quality', 'generated_sizes', 'include_converted' ];

	/**
	 * Per-image preview statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'ready', 'already_converted', 'same_format', 'unsupported', 'file_missing', 'name_conflict', 'locked', 'not_found', 'forbidden' ];

	/**
	 * Recorded conversion statuses that count as converted.
	 *
	 * @var string[]
	 */
	const DONE_STATUSES = [ 'completed', 'skipped' ];

	/**
	 * A Pro-only option was requested without an active Pro licence.
	 *
	 * @var string
	 */
	const ERROR_PRO_REQUIRED = 'tsmlt_pro_required';

	/**
	 * The server cannot produce a requested format.
	 *
	 * @var string
	 */
	const ERROR_FORMAT_UNAVAILABLE = 'tsmlt_conversion_format_unavailable';

	/**
	 * The confirmation token is missing, unknown, used, expired or another user's.
	 *
	 * @var string
	 */
	const ERROR_TOKEN = 'tsmlt_conversion_token_invalid';

	/**
	 * A compression or conversion job is running.
	 *
	 * @var string
	 */
	const ERROR_JOB_RUNNING = 'tsmlt_conversion_job_running';

	/**
	 * The effective settings changed since the preview.
	 *
	 * @var string
	 */
	const ERROR_SETTINGS_CHANGED = 'tsmlt_conversion_settings_changed';

	/**
	 * None of the previewed images is ready any more.
	 *
	 * @var string
	 */
	const ERROR_NOTHING = 'tsmlt_conversion_nothing_to_do';

	/**
	 * The job could not be started.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_conversion_start_failed';

	/**
	 * The job ID is not the current job's.
	 *
	 * @var string
	 */
	const ERROR_JOB_MISMATCH = 'tsmlt_conversion_job_mismatch';

	/**
	 * The current job is not a conversion job.
	 *
	 * @var string
	 */
	const ERROR_NOT_CONVERSION = 'tsmlt_conversion_not_conversion_job';

	/**
	 * The job is not running.
	 *
	 * @var string
	 */
	const ERROR_NOT_RUNNING = 'tsmlt_conversion_job_not_running';

	/**
	 * Validate a preview's input.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array{ids: int[], request: array, include_converted: bool}|WP_Error
	 */
	public static function sanitize_preview( $input ) {
		if ( ! is_array( $input ) || [] !== array_diff( array_keys( $input ), self::PREVIEW_KEYS ) ) {
			return self::invalid( __( 'Unknown input. Allowed: ids, formats, quality, generated_sizes, include_converted. The whole library cannot be converted through MCP.', 'media-library-tools' ) );
		}
		$ids = $input['ids'] ?? null;
		$ok  = is_array( $ids ) && [] !== $ids && count( $ids ) <= self::MAX_IDS && array_values( $ids ) === $ids;
		foreach ( $ok ? $ids : [] as $id ) {
			$ok = $ok && is_int( $id ) && $id > 0;
		}
		if ( ! $ok || count( array_unique( $ids ) ) !== count( $ids ) ) {
			return self::invalid( __( 'ids must be a list of 1 to 20 distinct attachment IDs (positive integers).', 'media-library-tools' ) );
		}

		$formats = $input['formats'] ?? [ 'webp' ];
		$valid   = is_array( $formats ) && [] !== $formats && array_values( $formats ) === $formats && count( array_unique( $formats ) ) === count( $formats );
		foreach ( $valid ? $formats : [] as $format ) {
			$valid = $valid && in_array( $format, ConversionCapabilities::FORMATS, true );
		}
		if ( ! $valid ) {
			return self::invalid( __( 'formats must be a list of distinct formats: webp and/or avif.', 'media-library-tools' ) );
		}

		$request = [ 'formats' => $formats ];
		if ( array_key_exists( 'quality', $input ) ) {
			$quality = $input['quality'];
			$valid   = is_array( $quality ) && [] !== $quality && [] === array_diff( array_keys( $quality ), $formats );
			foreach ( $valid ? $quality : [] as $value ) {
				$valid = $valid && is_int( $value ) && $value >= 1 && $value <= 100;
			}
			if ( ! $valid ) {
				return self::invalid( __( 'quality must map requested formats to whole numbers from 1 to 100, for example {"webp": 75}.', 'media-library-tools' ) );
			}
			$request['quality'] = $quality;
		}
		foreach ( [ 'generated_sizes', 'include_converted' ] as $key ) {
			if ( array_key_exists( $key, $input ) && ! is_bool( $input[ $key ] ) ) {
				/* translators: %s: input name. */
				return self::invalid( sprintf( __( '%s must be true or false.', 'media-library-tools' ), $key ) );
			}
		}
		if ( array_key_exists( 'generated_sizes', $input ) ) {
			$request['generated_sizes'] = $input['generated_sizes'];
		}

		return [
			'ids'               => $ids,
			'request'           => $request,
			'include_converted' => ! empty( $input['include_converted'] ),
		];
	}

	/**
	 * Refuse what the site cannot do: Pro-only options without an active
	 * Pro licence, and formats this server cannot produce. Asking for a Pro
	 * option to be off is always allowed. Nothing is ever dropped silently.
	 *
	 * @param array $request Requested run settings.
	 *
	 * @return WP_Error|null
	 */
	public static function refusal( array $request ): ?WP_Error {
		$access = CompressionAccess::instance();
		$denied = [];
		if ( in_array( 'avif', $request['formats'], true ) && ! $access->can_convert_avif() ) {
			$denied[] = 'avif';
		}
		if ( ! empty( $request['quality'] ) && ! $access->can_use_custom_conversion_quality() ) {
			$denied[] = 'quality';
		}
		if ( ! empty( $request['generated_sizes'] ) && ! $access->can_convert_generated_sizes() ) {
			$denied[] = 'generated_sizes';
		}
		if ( $denied ) {
			return AbilityGuard::error(
				self::ERROR_PRO_REQUIRED,
				sprintf(
					/* translators: %s: comma-separated option names. */
					__( 'These options need an active Media Library Tools Pro licence: %s. Nothing was changed; leave them out to convert to WebP with the preset quality.', 'media-library-tools' ),
					implode( ', ', $denied )
				),
				403
			);
		}
		$capabilities = ConversionCapabilities::instance();
		if ( ! $capabilities->is_available() ) {
			return AbilityGuard::error( self::ERROR_FORMAT_UNAVAILABLE, __( 'This server cannot produce WebP or AVIF images.', 'media-library-tools' ), 503 );
		}
		foreach ( $request['formats'] as $format ) {
			if ( ! $capabilities->supports_format( $format ) ) {
				return AbilityGuard::error(
					self::ERROR_FORMAT_UNAVAILABLE,
					/* translators: %s: format name, e.g. AVIF. */
					sprintf( __( 'This server cannot produce %s images. Nothing was changed.', 'media-library-tools' ), strtoupper( $format ) ),
					503
				);
			}
		}
		return null;
	}

	/**
	 * Effective run settings by the Convert feature's own rules. Called only
	 * after refusal() passed, so every requested format and option applies;
	 * a difference would be a silent change and is reported as an error.
	 *
	 * @param array $request Requested run settings.
	 *
	 * @return array|WP_Error
	 */
	public static function run_settings( array $request ) {
		$settings     = ConversionSettings::instance()->resolve_run_settings( $request );
		$settings     = [
			'formats'         => array_values( (array) $settings['formats'] ),
			'quality'         => array_map( 'intval', (array) $settings['quality'] ),
			'generated_sizes' => (bool) $settings['generated_sizes'],
		];
		$quality_kept = true;
		foreach ( (array) ( $request['quality'] ?? [] ) as $format => $value ) {
			$quality_kept = $quality_kept && (int) ( $settings['quality'][ $format ] ?? 0 ) === (int) $value;
		}
		$formats_kept = array_values( $request['formats'] ) === $settings['formats'];
		$sizes_kept   = ! isset( $request['generated_sizes'] ) || $request['generated_sizes'] === $settings['generated_sizes'];
		if ( ! $formats_kept || ! $quality_kept || ! $sizes_kept ) {
			return AbilityGuard::error( self::ERROR_FORMAT_UNAVAILABLE, __( 'The requested conversion settings cannot be applied on this site. Nothing was changed.', 'media-library-tools' ), 409 );
		}
		return $settings;
	}

	/**
	 * Preview status of one image for the current user. Read-only.
	 *
	 * Images the user may not read, trashed and deleted ones are not_found;
	 * readable but not editable images are forbidden. Otherwise the engine's
	 * validation, the operation locks and file ownership decide.
	 *
	 * @param int   $id                Attachment ID.
	 * @param array $formats           Requested formats.
	 * @param bool  $include_converted Whether converted images are converted again.
	 *
	 * @return string One of STATUSES.
	 */
	public static function item_status( int $id, array $formats, bool $include_converted ): string {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return 'not_found';
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			return 'forbidden';
		}
		$converter = AttachmentConverter::instance();
		$valid     = $converter->validate_attachment_file( $id );
		if ( is_wp_error( $valid ) ) {
			$statuses = [
				'tsmlt_conversion_unsupported_source' => 'unsupported',
				'tsmlt_conversion_missing_source'     => 'file_missing',
			];
			return $statuses[ $valid->get_error_code() ] ?? 'not_found';
		}
		if ( $converter->is_locked( $id ) || $converter->is_busy_elsewhere( $id ) ) {
			return 'locked';
		}

		$mime    = (string) get_post_mime_type( $id );
		$targets = [];
		foreach ( $formats as $format ) {
			if ( strtolower( $mime ) !== 'image/' . $format ) {
				$targets[] = $format;
			}
		}
		if ( [] === $targets ) {
			return 'same_format';
		}
		$file = (string) get_attached_file( $id );
		foreach ( $targets as $format ) {
			if ( 'conflict' === ConversionOwnership::target_state( $id, $converter->build_output_path( $file, $format ), $format ) ) {
				return 'name_conflict';
			}
		}
		if ( ! $include_converted ) {
			$data = ConversionMetadata::instance()->get( $id );
			$done = true;
			foreach ( $targets as $format ) {
				$done = $done && in_array( (string) ( $data['formats'][ $format ]['status'] ?? '' ), self::DONE_STATUSES, true );
			}
			if ( $done ) {
				return 'already_converted';
			}
		}
		return 'ready';
	}

	/**
	 * Issue a confirmation token for these images and settings, for the
	 * current user.
	 *
	 * @param int[] $ids      Ready attachment IDs.
	 * @param array $request  Requested run settings.
	 * @param array $settings Effective settings.
	 *
	 * @return array{token: string, expires: int}|null
	 */
	public static function issue_token( array $ids, array $request, array $settings ): ?array {
		$ids = array_map( 'intval', $ids );
		sort( $ids );
		return ConfirmationToken::issue(
			self::TOKEN_OPTION_PREFIX,
			[
				'ids'      => $ids,
				'request'  => $request,
				'settings' => $settings,
			],
			self::TOKEN_TTL
		);
	}

	/**
	 * Check a confirmation token and, if it is valid, use it up.
	 *
	 * @param mixed $token Token from the client.
	 *
	 * @return array|WP_Error
	 */
	public static function consume_token( $token ) {
		return ConfirmationToken::consume( self::TOKEN_OPTION_PREFIX, $token, 'tsmlt/preview-media-conversion', self::ERROR_TOKEN );
	}

	/**
	 * Invalid-input error.
	 *
	 * @param string $message Message.
	 *
	 * @return WP_Error
	 */
	private static function invalid( string $message ): WP_Error {
		return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, $message, 400 );
	}
}
