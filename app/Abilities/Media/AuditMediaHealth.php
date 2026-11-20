<?php
/**
 * Ability: tsmlt/audit-media-health.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Library-wide media health audit, one window at a time: the next `limit`
 * library attachments after `after_id`, in ID order (keyset pagination),
 * each checked with GetMediaHealth::inspect(). Only attachments with issues
 * are listed; healthy ones are counted, and inaccessible ones are counted
 * (hidden, forbidden) without their IDs.
 *
 * Read-only and stateless: one SELECT for the window, one COUNT, the
 * existing read-only checks. No cursor or audit state is stored; nothing is
 * scheduled, repaired or changed.
 *
 * Registered with `meta.mcp.public` false: listed by name on the dedicated
 * server and the default server, not run through the default gateway.
 */
class AuditMediaHealth extends Ability {

	/**
	 * Largest window.
	 *
	 * @var int
	 */
	const MAX_LIMIT = 100;

	/**
	 * Default window.
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 50;

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/audit-media-health';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Audit media health', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Audit the whole media library for file and record problems, one window at a time, with the checks of tsmlt-get-media-health (no_attached_file, file_missing, file_unreadable, mime_mismatch, metadata_missing, filesize_mismatch, dimensions_mismatch, invalid_parent). Each call examines the next limit (1-100, default 50) attachments after after_id, in ID order, and lists only those with issues; issue keeps only items with that code. Start with after_id 0, then pass next_after_id until done is true. Counters (examined, healthy, hidden for attachments you cannot read, forbidden for those you cannot edit) and issues_summary cover every examined attachment, whatever the issue filter. Media restored from the trash behind the cursor is not seen by an audit in progress. Read-only; stores nothing and repairs nothing.', 'media-library-tools' );
	}

	/**
	 * Listed by name only; not run through the default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'default'              => [],
			'properties'           => [
				'after_id' => [
					'type'        => 'integer',
					'minimum'     => 0,
					'default'     => 0,
					'description' => 'Examine attachments with a higher ID than this; 0 to start, then next_after_id.',
				],
				'limit'    => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => self::MAX_LIMIT,
					'default' => self::DEFAULT_LIMIT,
				],
				'issue'    => [
					'type'        => 'string',
					'enum'        => GetMediaHealth::ISSUES,
					'description' => 'List only attachments with this issue.',
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'examined', 'healthy', 'hidden', 'forbidden', 'issues_summary', 'next_after_id', 'done', 'total_attachments' ],
			'properties'           => [
				'items'             => [
					'type'  => 'array',
					'items' => GetMediaHealth::item_schema(),
				],
				'examined'          => [ 'type' => 'integer' ],
				'healthy'           => [ 'type' => 'integer' ],
				'hidden'            => [
					'type'        => 'integer',
					'description' => 'Attachments in this window you cannot read; not listed.',
				],
				'forbidden'         => [
					'type'        => 'integer',
					'description' => 'Attachments in this window you can read but not edit; not listed.',
				],
				'issues_summary'    => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
					'description'          => 'Count per issue code over every examined attachment.',
				],
				'next_after_id'     => [
					'type'        => [ 'integer', 'null' ],
					'description' => 'The last examined ID, for the next call; null when nothing was examined.',
				],
				'done'              => [
					'type'        => 'boolean',
					'description' => 'No attachment exists after this window.',
				],
				'total_attachments' => [
					'type'        => 'integer',
					'description' => 'Library attachments now, for progress.',
				],
			],
		];
	}

	/**
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * Examine one window.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$criteria = self::sanitize_input( $input );
		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		// One more than the window, to know whether another attachment follows.
		$rows   = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->andWhere( 'ID', '>', (string) $criteria['after_id'] )
			->orderBy( 'ID', 'ASC' )
			->limit( $criteria['limit'] + 1 )
			->get();
		$ids    = array_map( 'intval', array_column( is_array( $rows ) ? $rows : [], 'ID' ) );
		$done   = count( $ids ) <= $criteria['limit'];
		$window = array_slice( $ids, 0, $criteria['limit'] );

		_prime_post_caches( $window, false, true );
		$parents = array_filter( array_map( 'wp_get_post_parent_id', $window ) );
		if ( [] !== $parents ) {
			_prime_post_caches( array_values( array_unique( $parents ) ), false, false );
		}

		$result = [
			'items'             => [],
			'examined'          => count( $window ),
			'healthy'           => 0,
			'hidden'            => 0,
			'forbidden'         => 0,
			'issues_summary'    => array_fill_keys( GetMediaHealth::ISSUES, 0 ),
			'next_after_id'     => [] === $window ? null : (int) end( $window ),
			'done'              => $done,
			'total_attachments' => self::total(),
		];
		foreach ( $window as $id ) {
			$item = GetMediaHealth::inspect( $id );
			if ( 'not_found' === $item['status'] ) {
				++$result['hidden'];
			} elseif ( 'forbidden' === $item['status'] ) {
				++$result['forbidden'];
			} elseif ( 'healthy' === $item['status'] ) {
				++$result['healthy'];
			} else {
				foreach ( $item['issues'] as $issue ) {
					++$result['issues_summary'][ $issue ];
				}
				if ( '' === $criteria['issue'] || in_array( $criteria['issue'], $item['issues'], true ) ) {
					$result['items'][] = $item;
				}
			}
		}
		return $result;
	}

	/**
	 * Validate and normalise the input.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array{after_id: int, limit: int, issue: string}|WP_Error
	 */
	private static function sanitize_input( $input ) {
		$input = null === $input ? [] : $input;
		$after = is_array( $input ) ? ( $input['after_id'] ?? 0 ) : null;
		$limit = is_array( $input ) ? ( $input['limit'] ?? self::DEFAULT_LIMIT ) : null;
		$issue = is_array( $input ) ? ( $input['issue'] ?? '' ) : null;
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'after_id', 'limit', 'issue' ] )
			|| ! is_int( $after ) || $after < 0
			|| ! is_int( $limit ) || $limit < 1 || $limit > self::MAX_LIMIT
			|| ( '' !== $issue && ! in_array( $issue, GetMediaHealth::ISSUES, true ) ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'after_id must be an integer of 0 or more; limit 1 to 100; issue one of the media health issue codes.', 'media-library-tools' ), 400 );
		}
		return [
			'after_id' => $after,
			'limit'    => $limit,
			'issue'    => $issue,
		];
	}

	/**
	 * Library attachments now.
	 *
	 * @return int
	 */
	private static function total(): int {
		$rows = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		return (int) ( $rows[0]['total'] ?? 0 );
	}
}
