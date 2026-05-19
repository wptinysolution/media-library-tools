<?php
/**
 * Used-Where module — tracks where images are used across the website.
 *
 * Stores usage data as attachment post meta (`_tsmlt_image_usages`) and sets
 * `post_parent` on each attachment for the "Attached Post" column.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\UsedWhere;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * UsedWhereScanner
 */
class UsedWhereScanner {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Meta key for storing image usage data on attachments.
	 */
	const META_KEY = '_tsmlt_image_usages';

	/**
	 * Usage types that indicate a post genuinely "owns" the image.
	 *
	 * When any of these appear in an attachment's usage list, the post
	 * concretely references the image via a structured field — featured
	 * image, post content, gallery, builder data, custom meta. Any
	 * `permalink` or `rendered` records on OTHER posts (not in this owning
	 * set) are almost certainly contamination from related-products widgets,
	 * schema markup, lazy-load placeholders, sidebar / footer / header
	 * fragments, etc. — and get dropped during reconciliation.
	 *
	 * `meta` is included because it covers everything the meta scanner
	 * walks (ACF, Woo product galleries via _product_image_gallery, plugin
	 * meta blobs like size charts, etc.) — far stronger than a permalink
	 * regex hit.
	 */
	const OWNING_USAGE_TYPES = [
		'featured',
		'content',
		'excerpt',
		'woo_gallery',
		'elementor',
		'beaver_builder',
		'divi',
		'brizy',
		'wpbakery',
		'meta',
		'term_meta',
	];

	/**
	 * Site-wide usage types — image is owned by the site itself, not by a
	 * specific post. When ANY of these are recorded for an attachment, every
	 * `permalink` / `rendered` hit on that attachment is by definition
	 * contamination from chrome (logo, favicon, widget, menu image, header
	 * image, background image) repeating on every public page. Reconciliation
	 * drops all per-post permalink/rendered records in that case.
	 */
	const SITEWIDE_USAGE_TYPES = [
		'site_logo',
		'site_icon',
		'header_image',
		'background_image',
		'nav_menu',
		'widget',
	];

	/**
	 * Buffer: accumulates usages per attachment_id during a batch scan.
	 *
	 * @var array<int, array>
	 */
	private $usages_buffer = [];

	/**
	 * Site-wide URL→attachment_id lookup map, built once per batch.
	 * Keys are relative paths (after /uploads/), values are attachment IDs.
	 *
	 * @var array<string, int>|null
	 */
	private $url_lookup_map = null;

	/**
	 * Set of valid attachment IDs derived from the URL lookup map. Used by
	 * the recursive walker to validate numeric `id` / `image` / `attachment_id`
	 * keys without rebuilding the set on every recursion level.
	 *
	 * Built lazily by `get_known_attachment_ids()` and reset alongside
	 * `url_lookup_map`.
	 *
	 * @var array<int, true>|null Map keyed by attachment ID for O(1) checks.
	 */
	private $known_attachment_ids = null;

	/**
	 * Cached uploads base URL (with trailing slash). Avoids repeated
	 * wp_upload_dir() calls inside hot extraction loops.
	 *
	 * @var string|null
	 */
	private $upload_base_url = null;

	/**
	 * Attachment IDs that show up as automatic fallbacks in rendered HTML
	 * (e.g. WooCommerce's `woocommerce-placeholder.webp` for products without
	 * a featured image). Suppressed during permalink / rendered scans so they
	 * don't get marked as "used" on every post; still recorded if genuinely
	 * referenced via featured image / content / gallery.
	 *
	 * @var array<int, true>|null Map keyed by attachment ID.
	 */
	private $fallback_attachment_ids = null;

	/**
	 * Attachment ID → owning post_id for featured images, built once per batch.
	 *
	 * Used by `record_usages_from_urls()` to drop `permalink` / `rendered` hits
	 * that come from a *different* post's featured image being shown in a
	 * related-posts / recent-posts / popular-posts sidebar widget on the
	 * currently-scanned page. The thumbnail is rendered HTML on this post but
	 * is actually owned by the featured-image post — recording it here would
	 * incorrectly mark the image as "used" on every post that runs the widget.
	 *
	 * Map only — we only need to know whether *some other* post owns the
	 * attachment via featured image, not the complete list of owners.
	 *
	 * @var array<int, int>|null Map keyed by attachment ID → first post_id seen.
	 */
	private $featured_image_owners = null;

	/**
	 * Set of attachment IDs that are attributed as site-wide (post_id=0).
	 *
	 * Built once per batch from the current `usages_buffer` plus already-persisted
	 * `_tsmlt_image_usages` meta with `post_id=0` rows. Used by HTML-scan paths
	 * (`permalink` / `rendered`) to suppress per-post hits for images that render
	 * on every page because they live in an option / theme mod / widget / term
	 * meta (site logo, header, custom CSS image, etc.). Recording such a hit on
	 * every post visited would falsely list the whole site as a user of the image.
	 *
	 * @var array<int, bool>|null Set keyed by attachment ID.
	 */
	private $sitewide_attachment_ids = null;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Return the uploads base URL (with trailing slash), cached per scan batch.
	 *
	 * @return string
	 */
	private function get_uploads_base_url(): string {
		if ( null === $this->upload_base_url ) {
			$upload_dir            = wp_upload_dir();
			$this->upload_base_url = trailingslashit( $upload_dir['baseurl'] );
		}
		return $this->upload_base_url;
	}

	/**
	 * Return the set of valid attachment IDs as an O(1)-lookup map.
	 *
	 * Built once per batch and reset alongside the URL lookup map. Used by
	 * the recursive array walker (`extract_attachment_ids_from_array`) and
	 * other detection paths that need to validate "is this number actually
	 * an attachment ID?" without hitting the DB.
	 *
	 * Replaces the previous pattern of `array_flip(array_values($map))`
	 * inside hot loops, which rebuilt the same set hundreds of times per
	 * builder-heavy post.
	 *
	 * @return array<int, true>
	 */
	private function get_known_attachment_ids(): array {
		if ( null !== $this->known_attachment_ids ) {
			return $this->known_attachment_ids;
		}
		$this->known_attachment_ids = array_flip( array_values( $this->url_lookup_map ?? [] ) );
		return $this->known_attachment_ids;
	}

	/**
	 * Return the set of attachment IDs treated as automatic fallbacks.
	 *
	 * Built once per batch and reset alongside the other batch-scoped caches.
	 * Currently includes WooCommerce's placeholder image; extensible via the
	 * `tsmlt_used_where_fallback_attachment_ids` filter so themes/plugins can
	 * register their own fallback IDs (logo placeholders, etc.).
	 *
	 * @return array<int, true> Map keyed by attachment ID for O(1) checks.
	 */
	private function get_fallback_attachment_ids(): array {
		if ( null !== $this->fallback_attachment_ids ) {
			return $this->fallback_attachment_ids;
		}

		$ids = [];

		// WooCommerce placeholder. Stored as an attachment ID in this option
		// since Woo 3.0+. Older versions stored the URL; ignore those.
		$woo_id = (int) get_option( 'woocommerce_placeholder_image', 0 );
		if ( $woo_id > 0 ) {
			$ids[ $woo_id ] = true;
		}

		/**
		 * Filter the set of attachment IDs that should be ignored during
		 * permalink / rendered-HTML scans because they appear as automatic
		 * fallbacks (Woo placeholder, theme logo placeholders, etc.).
		 *
		 * @param array<int, true> $ids Map keyed by attachment ID.
		 */
		$ids = apply_filters( 'tsmlt_used_where_fallback_attachment_ids', $ids );

		$this->fallback_attachment_ids = is_array( $ids ) ? $ids : [];
		return $this->fallback_attachment_ids;
	}

	/**
	 * Return the list of post types we scan for image usage.
	 *
	 * Returns every registered post type minus `attachment` (the thing we're
	 * scanning FOR, not OF). Builder template CPTs (Elementor, Bricks, Divi,
	 * Oxygen, Brizy, Beaver, Breakdance, …) and reusable blocks (`wp_block`)
	 * are picked up automatically — no per-builder allowlist needed.
	 *
	 * Revisions are excluded by `get_posts()` itself; statuses are filtered at
	 * the query level (`publish, draft, pending, private, future`), so log /
	 * queue / cache CPTs that store data under unusual statuses contribute
	 * little or nothing to scan time.
	 *
	 * Extensible via the `tsmlt_used_where_scannable_post_types` filter so
	 * sites can remove specific CPTs (e.g. high-volume log post types).
	 *
	 * @return string[] Post type slugs.
	 */
	private function get_scannable_post_types(): array {
		$types = get_post_types( [], 'names' );

		// `attachment` is the target of the scan, not the subject. The rest
		// are WordPress / FSE plumbing CPTs that never legitimately reference
		// uploads URLs in user-authored content — including them just inflates
		// the progress denominator without adding signal.
		unset(
			$types['attachment'],
			$types['revision'],
			$types['wp_font_family'],
			$types['wp_font_face']
		);

		// When WooCommerce HPOS is active, orders live in `wp_wc_orders` and
		// are scanned via the dedicated HPOS pass. Skip the legacy post-table
		// `shop_order*` CPTs to avoid double-scanning every order in HPOS sync
		// mode (and to dodge orphan rows left by the HPOS migration).
		if ( $this->is_hpos_active() ) {
			unset(
				$types['shop_order'],
				$types['shop_order_refund'],
				$types['shop_subscription']
			);
		}

		$types = array_values( $types );

		/**
		 * Filter the post types scanned for image usage.
		 *
		 * Default includes every registered post type except `attachment`.
		 *
		 * @param string[] $types List of post type slugs.
		 */
		$types = apply_filters( 'tsmlt_used_where_scannable_post_types', $types );
		return array_values( array_unique( array_filter( (array) $types, 'is_string' ) ) );
	}

	/**
	 * Whether WooCommerce HPOS (custom orders table) is the authoritative
	 * order store on this site.
	 *
	 * When true, orders no longer live in `wp_posts` / `wp_postmeta` — they
	 * live in `wp_wc_orders` and `wp_wc_orders_meta`. Our standard post-table
	 * iteration won't see them, so we run a separate HPOS pass after the
	 * normal post batches finish.
	 *
	 * Both Woo presence and HPOS activation are checked at runtime — no-op
	 * on non-Woo or HPOS-disabled sites.
	 *
	 * @return bool
	 */
	private function is_hpos_active(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}
		if ( ! method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return false;
		}
		return (bool) \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Total number of HPOS orders eligible for scanning.
	 *
	 * Excludes drafts and trashed orders. Cached per batch via the static
	 * to avoid re-querying for both the count step and the iteration step.
	 *
	 * @return int
	 */
	private function get_hpos_order_total(): int {
		global $wpdb;
		if ( ! $this->is_hpos_active() ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE status NOT IN ('trash', 'auto-draft')"
		);
		return (int) $count;
	}

	/**
	 * Fetch a slice of HPOS order IDs ordered by id ASC.
	 *
	 * @param int $offset Offset within the orders table.
	 * @param int $limit  Number of order IDs to return.
	 *
	 * @return int[] Order IDs.
	 */
	private function get_hpos_order_ids( int $offset, int $limit ): array {
		global $wpdb;
		if ( ! $this->is_hpos_active() || $limit < 1 ) {
			return [];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wc_orders WHERE status NOT IN ('trash', 'auto-draft') ORDER BY id ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Scan a single HPOS order for image references.
	 *
	 * Pulls the order's meta from `wp_wc_orders_meta` and feeds each value
	 * through the same `scan_meta_value_deep` pipeline used for normal post
	 * meta. Synthesises a minimal WP_Post stub so `record_usage` keeps the
	 * `{post_id, post_title, post_type}` shape every consumer already
	 * expects — no signature changes ripple through the codebase.
	 *
	 * @param int $order_id HPOS order ID.
	 *
	 * @return void
	 */
	private function detect_usage_in_hpos_order( int $order_id ): void {
		if ( ! class_exists( '\WC_Order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Synthesise a stub WP_Post so existing record_usage / get_post_link
		// helpers work unchanged. The fields consumers actually read are ID,
		// post_type, and post_title.
		$stub             = new \stdClass();
		$stub->ID         = $order_id;
		$stub->post_type  = 'shop_order';
		$stub->post_title = sprintf(
			/* translators: %d: order number */
			esc_html__( 'Order #%d', 'media-library-tools' ),
			$order_id
		);
		$stub->post_status = (string) $order->get_status();
		$stub_post         = new \WP_Post( $stub );

		$attachment_ids = $this->get_known_attachment_ids();

		// Walk the order's meta. wc_get_order returns a hydrated object; pull
		// the meta keys via the public API so plugins that store data via
		// get_meta() / update_meta_data() are visible too.
		foreach ( $order->get_meta_data() as $meta ) {
			$value = $meta->value;
			if ( is_string( $value ) ) {
				$this->scan_meta_value_deep( $value, $stub_post, $attachment_ids );
			} elseif ( is_array( $value ) ) {
				$this->extract_attachment_ids_from_array( $value, $stub_post, 'meta' );
			} elseif ( is_numeric( $value ) ) {
				$id = absint( $value );
				if ( $id && isset( $attachment_ids[ $id ] ) ) {
					$this->record_usage( $id, $stub_post, 'meta' );
				}
			}
		}
	}

	/**
	 * Scan all posts and detect where images (attachments) are used.
	 *
	 * Processes in batches to avoid timeouts. Stores results as post meta
	 * on each attachment and sets post_parent.
	 *
	 * @param int $offset Batch offset.
	 * @param int $batch_size Number of posts per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool}
	 */
	public function scan_batch( int $offset = 0, int $batch_size = 20 ): array {
		// Legacy AJAX-driven path: clear old usage meta on first batch.
		// Cron-driven scans clear once at start and call process_batch() instead.
		if ( 0 === $offset ) {
			$this->clear_all_usage_meta();
		}
		return $this->process_batch( $offset, $batch_size, 0 === $offset );
	}

	/**
	 * Scan one batch of posts. Pure batch worker — caller decides when to clear.
	 *
	 * Used by both the legacy AJAX path (via scan_batch) and the cron-driven
	 * tick handler. Does not touch the scan status option; the caller writes
	 * status so the polling UI sees consistent state.
	 *
	 * @param int  $offset           Offset into the post list.
	 * @param int  $batch_size       Max posts to process this tick.
	 * @param bool $detect_sitewide  Whether to run sitewide detection (favicon, logo) — only the first batch.
	 *
	 * @return array{processed: int, total: int, complete: bool}
	 */
	public function process_batch( int $offset, int $batch_size, bool $detect_sitewide ): array {
		$post_types = $this->get_scannable_post_types();

		// Compute totals up front so the progress denominator includes both
		// post-table posts and HPOS orders (when active). The two queues are
		// scanned sequentially: posts first, then HPOS orders once posts run out.
		$post_total = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$post_total += (int) ( $counts->publish ?? 0 );
			$post_total += (int) ( $counts->draft ?? 0 );
			$post_total += (int) ( $counts->pending ?? 0 );
			$post_total += (int) ( $counts->private ?? 0 );
			$post_total += (int) ( $counts->future ?? 0 );
		}
		$hpos_total  = $this->get_hpos_order_total();
		$total_count = $post_total + $hpos_total;

		// Build the site-wide URL→ID lookup map once per batch.
		$this->build_url_lookup_map();
		$this->usages_buffer = [];

		if ( $detect_sitewide ) {
			$this->detect_sitewide_usage();
		}

		// ── Phase A: post-table posts ────────────────────────────────────────
		$processed_in_batch = 0;
		if ( $offset < $post_total ) {
			$posts = get_posts( [
				'post_type'      => $post_types,
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'orderby'        => 'ID',
				'order'          => 'ASC',
			] );

			foreach ( $posts as $post ) {
				$this->detect_usage_in_post( $post );
				$processed_in_batch++;
			}
		}

		// ── Phase B: HPOS orders ────────────────────────────────────────────
		// Kicks in once the post queue is exhausted. The scan offset becomes
		// `post_total + hpos_offset`, so the polling UI sees one continuous
		// progress curve regardless of which queue is currently being worked.
		if ( $hpos_total > 0 && $processed_in_batch < $batch_size ) {
			$remaining   = $batch_size - $processed_in_batch;
			$hpos_offset = max( 0, ( $offset + $processed_in_batch ) - $post_total );
			if ( $hpos_offset < $hpos_total ) {
				$order_ids = $this->get_hpos_order_ids( $hpos_offset, $remaining );
				foreach ( $order_ids as $order_id ) {
					$this->detect_usage_in_hpos_order( $order_id );
					$processed_in_batch++;
				}
			}
		}

		// Flush buffer: save usages to post meta and set post_parent.
		// On the first batch the start handler already wiped existing usage
		// meta, so skip the per-attachment read-merge step entirely.
		$this->flush_usages_buffer( 0 === $offset );

		// Free batch-scoped caches; they'll be rebuilt on the next batch.
		$this->url_lookup_map = null;
		$this->known_attachment_ids = null;
		$this->upload_base_url = null;
		$this->fallback_attachment_ids = null;
		$this->featured_image_owners = null;
		$this->sitewide_attachment_ids = null;

		$next_offset = $offset + $processed_in_batch;
		return [
			'processed' => $next_offset,
			'total'     => $total_count,
			'complete'  => $next_offset >= $total_count,
		];
	}

	/**
	 * Detect where images are used in a specific post.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_usage_in_post( \WP_Post $post ): void {
		// Elementor "kit" posts (post_type=elementor_library, _elementor_template_type=kit)
		// store the site's GLOBAL DEFAULT styles — default page background, header
		// logo, theme color images, body background, etc. They are not pages that
		// actually use those images; Elementor applies the values dynamically on
		// other pages. Scanning the kit records every default image as
		// `usage_type='elementor'` on the kit post itself, producing phantom
		// "used in: Default Kit" entries for images that are merely global
		// settings. The WP-level sidewide scanner already captures the real
		// owners (custom_logo / site_icon / background_image), so we can skip
		// the kit entirely without losing signal.
		if ( $this->is_elementor_kit( $post ) ) {
			return;
		}

		// 1. Featured image.
		$featured_id = get_post_thumbnail_id( $post->ID );
		if ( $featured_id ) {
			$this->record_usage( $featured_id, $post, 'featured' );
		}

		// On Elementor-built pages, `post_content` is just a render-cache
		// fallback Elementor maintains for SEO crawlers — the URLs in it are
		// auto-generated copies of what `_elementor_data` already holds, not
		// independent evidence of usage. Skip the content/excerpt scans and
		// let the Elementor meta walk be authoritative for these posts.
		$is_elementor_built = (string) get_post_meta( $post->ID, '_elementor_edit_mode', true ) === 'builder';

		if ( ! $is_elementor_built ) {
			// 2. Images in post content (URLs + Gutenberg block IDs).
			$this->detect_images_in_content( $post->post_content, $post, 'content' );

			// 3. Images in post excerpt.
			if ( ! empty( $post->post_excerpt ) ) {
				$this->detect_images_in_content( $post->post_excerpt, $post, 'excerpt' );
			}
		}

		// 4. WooCommerce product gallery (comma-separated IDs in _product_image_gallery).
		$this->detect_woo_gallery( $post );

		// 5. Elementor (meta-based). Authoritative for Elementor-built posts.
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! empty( $elementor_data ) ) {
			$this->detect_images_in_elementor( $elementor_data, $post );
		}

		// 6. Other page builders (Beaver Builder, Divi, Brizy, etc.).
		$this->detect_images_in_builders( $post );

		// 7. Custom meta fields — includes _prefixed keys with serialized data,
		//     HTML stored in meta-box fields (e.g. size-chart data-image attributes),
		//     and any uploads URL embedded in serialized/JSON/HTML meta values.
		$this->detect_images_in_meta( $post );

		// 8. Fetch the public permalink and extract every image URL from the
		//    fully rendered HTML. Catches images injected by themes, plugins,
		//    shortcodes, meta-box buttons, and source-code-rendered output that
		//    isn't reachable from post_content / post_meta inspection alone.
		$this->detect_images_in_permalink( $post );
	}

	/**
	 * Fetch the post's public permalink and scan the rendered HTML for image URLs.
	 *
	 * Off by default — gated on the `scan_permalink_fetch` setting. When enabled,
	 * each scanned post triggers a loopback HTTP request that holds a PHP-FPM
	 * worker for the duration of a full page render; we don't impose that cost
	 * on visitors of busy stores unless the admin explicitly opts in.
	 *
	 * Skipped for non-published posts (drafts don't render publicly) and when
	 * the `tsmlt_scan_permalink_enabled` per-post filter returns false. Failures
	 * are silent — the rest of the scan still records what it found.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_permalink( \WP_Post $post ): void {
		// Setting-level gate — default off. Operators flip this on when they
		// specifically need to detect images injected by themes/plugins/shortcodes.
		if ( ! self::is_permalink_fetch_enabled() ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Skip post types that have no public single template (e.g. attachment-like CPTs).
		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		// Per-post override hook — lets users disable the fetch for specific
		// post types or post IDs even when the setting is on.
		if ( ! apply_filters( 'tsmlt_scan_permalink_enabled', true, $post ) ) {
			return;
		}

		// Avoid loopback recursion: if this request was itself spawned by us,
		// don't fetch again.
		if ( ! empty( $_SERVER['HTTP_X_TSMLT_SCAN'] ) ) {
			return;
		}

		// Skip the HTTP fetch when nothing on this post has changed since the
		// last successful permalink scan. The fingerprint covers the post's
		// content, modified-time, and the size-chart-style meta blobs that the
		// permalink fetch is designed to catch in the first place.
		$fingerprint = $this->build_permalink_fingerprint( $post );
		$last_print  = (string) get_post_meta( $post->ID, '_tsmlt_permalink_fp', true );
		if ( $fingerprint && $fingerprint === $last_print ) {
			return;
		}

		$permalink = get_permalink( $post );
		if ( empty( $permalink ) ) {
			return;
		}

		$response = wp_remote_get(
			$permalink,
			[
				'timeout'     => 10,
				'redirection' => 3,
				'sslverify'   => false,
				'headers'     => [
					'X-TSMLT-Scan' => '1',
				],
				'user-agent'  => 'TSMLT/UsedWhereScanner',
			]
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return;
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return;
		}

		$this->extract_image_urls_from_html( $html, $post, 'permalink' );

		// Stamp the fingerprint only after a successful extraction so a
		// transient HTTP failure doesn't poison future scans.
		if ( $fingerprint ) {
			update_post_meta( $post->ID, '_tsmlt_permalink_fp', $fingerprint );
		}
	}

	/**
	 * Build a short fingerprint of the post state that can change rendered output.
	 *
	 * Used to skip the permalink HTTP fetch when nothing has changed. Includes
	 * post_modified_gmt (covers content, title, status edits) plus the meta
	 * keys most likely to inject extra images on render.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return string md5 fingerprint or empty string on failure.
	 */
	private function build_permalink_fingerprint( \WP_Post $post ): string {
		$parts = [
			$post->post_modified_gmt,
			(string) get_post_meta( $post->ID, '_thumbnail_id', true ),
			(string) get_post_meta( $post->ID, '_product_image_gallery', true ),
		];
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Extract every uploads-image URL from an HTML blob.
	 *
	 * Phase 1 of the two-phase scan pipeline. Returns a deduplicated set of
	 * relative paths (keys), suitable for batch resolution against the
	 * url_lookup_map. Pure string work — no DB / no record writes.
	 *
	 * @param string $html HTML to scan.
	 *
	 * @return array<string, true> Map keyed by relative path (after /uploads/).
	 */
	private function extract_uploads_urls_from_html( string $html ): array {
		$pattern = '/\/wp-content\/uploads\/([^\s"\'<>)\\\;,]+\.(?:jpg|jpeg|png|gif|webp|svg|bmp|ico))/i';
		$seen    = [];

		// 1. Raw HTML pass — the common case, no decode cost.
		if ( preg_match_all( $pattern, $html, $matches ) ) {
			foreach ( $matches[1] as $rel ) {
				$rel = rtrim( $rel, ").,;:!?" );
				if ( '' !== $rel ) {
					$seen[ $rel ] = true;
				}
			}
		}

		// 2. Entity-decoded pass — only when body contains encoded markup
		//    (`&lt;`, `&quot;`, `&amp;`). Catches URLs hidden inside encoded
		//    attribute payloads like data-image="&lt;img src=&quot;...&quot;&gt;".
		if ( false !== strpos( $html, '&lt;' ) || false !== strpos( $html, '&quot;' ) || false !== strpos( $html, '&amp;' ) ) {
			$decoded = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( $decoded !== $html && preg_match_all( $pattern, $decoded, $dec_matches ) ) {
				foreach ( $dec_matches[1] as $rel ) {
					$rel = rtrim( $rel, ").,;:!?" );
					if ( '' !== $rel ) {
						$seen[ $rel ] = true;
					}
				}
			}
		}

		return $seen;
	}

	/**
	 * Resolve a set of relative uploads paths to attachment IDs and record usages.
	 *
	 * Phase 2 of the two-phase scan pipeline. Looks up each URL in the cached
	 * url_lookup_map, dedupes by attachment_id (so /file.jpg and
	 * /file-300x200.jpg don't both record), and writes one usage per ID.
	 *
	 * @param array<string, true> $relative_paths Output of extract_uploads_urls_from_html().
	 * @param \WP_Post            $post           Post being scanned.
	 * @param string              $type           Usage type label.
	 *
	 * @return void
	 */
	private function record_usages_from_urls( array $relative_paths, \WP_Post $post, string $type ): void {
		if ( empty( $relative_paths ) ) {
			return;
		}

		$base_url     = $this->get_uploads_base_url();
		$resolved_ids = [];

		// Suppress automatic fallbacks (e.g. Woo's woocommerce-placeholder.webp)
		// only on HTML-scan paths. Featured-image / content / gallery detection
		// is unaffected — if a post genuinely references the placeholder, it
		// still records.
		$is_html_scan = ( 'permalink' === $type || 'rendered' === $type );
		$fallbacks    = $is_html_scan ? $this->get_fallback_attachment_ids() : [];

		// On HTML-scan paths, also suppress thumbnails of *other* posts being
		// shown by related-posts / recent-posts widgets, schema markup, etc.
		// If the matched attachment is the featured image of a different post,
		// the hit on this post is incidental and should not be recorded.
		// Additionally suppress site-wide images (logo/header/option/term_meta)
		// that render on every page — recording each visit would falsely list
		// every post as a user of the image.
		if ( $is_html_scan ) {
			$this->build_featured_image_owners();
			$this->build_sitewide_attachment_ids();
		}

		foreach ( array_keys( $relative_paths ) as $relative_path ) {
			$attachment_id = $this->get_attachment_id_by_url( $base_url . $relative_path );
			if ( ! $attachment_id ) {
				continue;
			}
			if ( isset( $fallbacks[ $attachment_id ] ) ) {
				continue;
			}
			if ( $is_html_scan ) {
				if ( isset( $this->sitewide_attachment_ids[ $attachment_id ] ) ) {
					continue; // site-wide image (logo/header/option) — already recorded as Site Settings
				}
				$owner = $this->featured_image_owners[ $attachment_id ] ?? 0;
				if ( $owner && $owner !== $post->ID ) {
					continue; // related-posts widget thumbnail — belongs to $owner, not $post
				}
			}
			if ( ! isset( $resolved_ids[ $attachment_id ] ) ) {
				$resolved_ids[ $attachment_id ] = true;
			}
		}

		foreach ( array_keys( $resolved_ids ) as $attachment_id ) {
			$this->record_usage( (int) $attachment_id, $post, $type );
		}
	}

	/**
	 * Convenience wrapper: extract URLs from HTML, then resolve+record in one call.
	 *
	 * Used by both `detect_images_in_permalink()` and `scan_rendered_html()`.
	 *
	 * @param string   $html HTML to scan.
	 * @param \WP_Post $post Post being scanned.
	 * @param string   $type Usage type label.
	 *
	 * @return void
	 */
	private function extract_image_urls_from_html( string $html, \WP_Post $post, string $type ): void {
		$urls = $this->extract_uploads_urls_from_html( $html );
		$this->record_usages_from_urls( $urls, $post, $type );
	}

	/**
	 * Detect image attachments in HTML content.
	 *
	 * @param string   $content Content to search.
	 * @param \WP_Post $post Post object.
	 * @param string   $type Usage type ('content', 'excerpt', etc.).
	 *
	 * @return void
	 */
	private function detect_images_in_content( string $content, \WP_Post $post, string $type ): void {
		if ( empty( $content ) ) {
			return;
		}

		// Build a set of known attachment IDs for quick validation.
		$known_ids = $this->get_known_attachment_ids();

		// 1. Gutenberg blocks — recursively walk parsed block tree so nested galleries
		// and blocks with nested attribute objects (style/layout/etc.) are handled.
		if ( function_exists( 'parse_blocks' ) && false !== strpos( $content, '<!-- wp:' ) ) {
			$this->collect_block_attachment_ids( parse_blocks( $content ), $known_ids, $post, $type );
		}

		// 2. wp-image-{ID} CSS class (both Gutenberg and Classic editor).
		if ( preg_match_all( '/wp-image-(\d+)/i', $content, $class_matches ) ) {
			foreach ( $class_matches[1] as $class_id ) {
				$class_id = absint( $class_id );
				if ( $class_id && isset( $known_ids[ $class_id ] ) ) {
					$this->record_usage( $class_id, $post, $type );
				}
			}
		}

		// 3. Image URLs in content (/wp-content/uploads/...).
		// Excludes whitespace, quotes, angle brackets, and CSS/HTML delimiters
		// (`)`, `;`, `,`) so inline CSS like `url(.../file.jpg)` is captured cleanly.
		if ( preg_match_all( '/\/wp-content\/uploads\/([^\s"\'<>)\\\;,]+)/i', $content, $matches ) ) {
			$base_url = $this->get_uploads_base_url();

			foreach ( $matches[1] as $relative_path ) {
				// Trim any stray trailing punctuation that survived the character class.
				$relative_path = rtrim( $relative_path, ").,;:!?" );
				if ( '' === $relative_path ) {
					continue;
				}
				$full_url      = $base_url . $relative_path;
				$attachment_id = $this->get_attachment_id_by_url( $full_url );
				if ( $attachment_id ) {
					$this->record_usage( $attachment_id, $post, $type );
				}
			}
		}
	}

	/**
	 * Walk a parsed block tree and record any attachment IDs referenced by block attrs.
	 *
	 * Handles single-image blocks (`id`), gallery blocks (`ids[]`), and any nested
	 * inner blocks (e.g. wp:gallery wrapping individual wp:image blocks in WP 5.9+).
	 *
	 * @param array    $blocks    Parsed blocks from parse_blocks().
	 * @param array    $known_ids Map of valid attachment IDs (id => any).
	 * @param \WP_Post $post      Post being scanned.
	 * @param string   $type      Usage type.
	 *
	 * @return void
	 */
	private function collect_block_attachment_ids( array $blocks, array $known_ids, \WP_Post $post, string $type ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];

			if ( ! empty( $attrs['id'] ) ) {
				$bid = absint( $attrs['id'] );
				if ( $bid && isset( $known_ids[ $bid ] ) ) {
					$this->record_usage( $bid, $post, $type );
				}
			}

			if ( ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
				foreach ( $attrs['ids'] as $gid ) {
					$gid = absint( $gid );
					if ( $gid && isset( $known_ids[ $gid ] ) ) {
						$this->record_usage( $gid, $post, $type );
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collect_block_attachment_ids( $block['innerBlocks'], $known_ids, $post, $type );
			}
		}
	}

	/**
	 * Detect images in Elementor meta data.
	 *
	 * @param string   $elementor_data JSON data from Elementor.
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_elementor( string $elementor_data, \WP_Post $post ): void {
		$data = json_decode( $elementor_data, true );
		if ( ! is_array( $data ) ) {
			return;
		}

		$this->extract_attachment_ids_from_array( $data, $post, 'elementor' );
	}

	/**
	 * Detect WooCommerce product gallery images.
	 *
	 * The _product_image_gallery meta stores comma-separated attachment IDs.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_woo_gallery( \WP_Post $post ): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		$gallery = get_post_meta( $post->ID, '_product_image_gallery', true );
		if ( empty( $gallery ) ) {
			return;
		}

		$known_ids = $this->get_known_attachment_ids();

		$ids = explode( ',', $gallery );
		foreach ( $ids as $id ) {
			$id = absint( trim( $id ) );
			if ( $id && isset( $known_ids[ $id ] ) ) {
				$this->record_usage( $id, $post, 'woo_gallery' );
			}
		}
	}

	/**
	 * Detect images stored by other page builders.
	 *
	 * Checks known meta keys used by Beaver Builder, Divi, Brizy, and
	 * other popular builders. Uses the same recursive array search
	 * as Elementor detection.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_builders( \WP_Post $post ): void {
		$builder_keys = [
			'_fl_builder_data'       => 'beaver_builder',   // Beaver Builder.
			'_et_builder_settings'   => 'divi',             // Divi (JSON).
			'brizy_post_uid'         => 'brizy',            // Brizy stores data in content, but check meta too.
			'_wpb_shortcodes_custom_css' => 'wpbakery',     // WPBakery (CSS may have bg images).
		];

		foreach ( $builder_keys as $meta_key => $builder_name ) {
			$meta_value = get_post_meta( $post->ID, $meta_key, true );
			if ( empty( $meta_value ) ) {
				continue;
			}

			if ( is_string( $meta_value ) ) {
				// Try JSON decode first.
				$decoded = json_decode( $meta_value, true );
				if ( is_array( $decoded ) ) {
					$this->extract_attachment_ids_from_array( $decoded, $post, $builder_name );
					continue;
				}
				// Try unserialized.
				$unserialized = maybe_unserialize( $meta_value );
				if ( is_array( $unserialized ) ) {
					$this->extract_attachment_ids_from_array( $unserialized, $post, $builder_name );
					continue;
				}
				// Search for upload URLs in raw string.
				$this->detect_images_in_content( $meta_value, $post, $builder_name );
			} elseif ( is_array( $meta_value ) ) {
				$this->extract_attachment_ids_from_array( $meta_value, $post, $builder_name );
			}
		}
	}

	/**
	 * Recursively extract attachment IDs from nested arrays.
	 *
	 * The numeric-key extraction (`id`/`image`/`attachment_id` => 123) only
	 * fires when the walk is inside a known builder/block context — either
	 * because the caller declared one via `$type` (Elementor, Beaver, Divi,
	 * Brizy, WPBakery), or because an ancestor array in the walk carried a
	 * builder marker (`widgetType`, `elType`, `blockName`, `settings`, etc).
	 *
	 * Without this gate, every CPT meta whose generic `id` field happens
	 * to match an attachment ID gets recorded as a usage — that's how a
	 * freshly uploaded file ends up "used" across unrelated posts.
	 *
	 * @param array    $data               Array to search.
	 * @param \WP_Post $post               Post object.
	 * @param string   $type               Usage type label.
	 * @param int      $depth              Current recursion depth (max 10).
	 * @param bool     $in_builder_context Whether the walk is already inside a builder/block structure.
	 *
	 * @return void
	 */
	private function extract_attachment_ids_from_array( array $data, \WP_Post $post, string $type, int $depth = 0, bool $in_builder_context = false ): void {
		if ( $depth > 10 ) {
			return;
		}

		// Use the lookup map to verify attachment IDs without DB queries.
		$attachment_ids = $this->get_known_attachment_ids();

		// At the top level, callers that explicitly target a builder (Elementor,
		// Beaver, Divi, Brizy, WPBakery) opt into the numeric-key extraction
		// for free — their input shape is guaranteed to be widget data. The
		// generic `'meta'` and `'elementor'` legacy paths must earn it by
		// matching a structural marker below.
		if ( ! $in_builder_context && $this->is_builder_caller_type( $type ) ) {
			$in_builder_context = true;
		}

		// Promote to builder context when this array itself carries a builder
		// marker. We look once per array, not per key, so the cost is fixed.
		if ( ! $in_builder_context && $this->array_has_builder_marker( $data ) ) {
			$in_builder_context = true;
		}

		foreach ( $data as $key => $value ) {
			if ( $in_builder_context && is_numeric( $value ) && in_array( $key, [ 'id', 'image', 'attachment_id' ], true ) ) {
				$attachment_id = absint( $value );
				if ( $attachment_id && isset( $attachment_ids[ $attachment_id ] ) ) {
					$this->record_usage( $attachment_id, $post, $type );
				}
			}

			if ( is_string( $value ) && strpos( $value, '/wp-content/uploads/' ) !== false ) {
				// Try the value as a single URL first (covers ACF image URL, plain URL fields).
				// Meta values store full uploads paths verbatim — disable the
				// basename fallback so a value of `.../another/audio.mp3` cannot
				// be pinned onto an unrelated attachment with the same basename.
				$attachment_id = $this->get_attachment_id_by_url( $value, false );
				if ( $attachment_id ) {
					$this->record_usage( $attachment_id, $post, $type );
				}

				// Decode HTML entities when the value is encoded HTML — covers
				// the case where a serialized array contains a string like
				// `data-image="&lt;img src=&quot;...&quot;&gt;"`. Without decoding
				// the regex would capture trailing `&quot` fragments and the
				// lookup would miss. Conditional check avoids the cost on
				// already-clean strings (CSS url(...), plain URLs, etc.).
				$haystack = $value;
				if ( false !== strpos( $value, '&lt;' ) || false !== strpos( $value, '&quot;' ) || false !== strpos( $value, '&amp;' ) ) {
					$haystack = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				}

				// Then scan the value as a content blob — catches CSS `background-image:url(...)`,
				// rich-text fields, encoded-attribute payloads, and any string
				// holding multiple uploads URLs. Each match must end in a known
				// media extension; see extract_upload_paths_from_string().
				$rel_paths = $this->extract_upload_paths_from_string( $haystack );
				if ( ! empty( $rel_paths ) ) {
					$base_url = $this->get_uploads_base_url();
					foreach ( $rel_paths as $rel ) {
						$blob_id = $this->get_attachment_id_by_url( $base_url . $rel, false );
						if ( $blob_id ) {
							$this->record_usage( $blob_id, $post, $type );
						}
					}
				}
			}

			if ( is_array( $value ) ) {
				$this->extract_attachment_ids_from_array( $value, $post, $type, $depth + 1, $in_builder_context );
			}
		}
	}

	/**
	 * Whether the caller-provided `$type` label opts the walk straight into
	 * builder context. These are the entry points that hand us shape-checked
	 * widget data (Elementor JSON, builder meta blobs) — never generic post
	 * meta — so the numeric-key extraction is safe from the first level.
	 *
	 * @param string $type Caller-provided usage type.
	 * @return bool
	 */
	/**
	 * Whether a post is an Elementor "kit" — the global-defaults store.
	 *
	 * Kit posts live in the `elementor_library` CPT with template type `kit`
	 * (set via the `_elementor_template_type` meta). They hold the site's
	 * default styles and references to images used as defaults (page
	 * background, header logo, etc.). Scanning them produces false positives
	 * because the kit doesn't "use" those images — it just stores them as
	 * defaults that Elementor applies elsewhere.
	 *
	 * @param \WP_Post $post Post to inspect.
	 *
	 * @return bool
	 */
	private function is_elementor_kit( \WP_Post $post ): bool {
		if ( 'elementor_library' !== $post->post_type ) {
			return false;
		}
		$template_type = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
		return 'kit' === $template_type;
	}

	private function is_builder_caller_type( string $type ): bool {
		return in_array(
			$type,
			[ 'elementor', 'beaver_builder', 'divi', 'brizy', 'wpbakery' ],
			true
		);
	}

	/**
	 * Whether an array's own keys identify it as a builder/block structure.
	 *
	 * Used to promote a generic-meta walk into builder context once it
	 * descends into a widget/block payload. Markers are intentionally
	 * structural identifiers used by the major page builders and the
	 * Gutenberg block parser — not generic field names — so that we don't
	 * mis-promote arbitrary CPT meta that happens to use words like
	 * `'image'` or `'id'`.
	 *
	 * @param array $data Array to inspect.
	 * @return bool
	 */
	/**
	 * Extract uploads-relative paths from a string blob.
	 *
	 * Tighter than a bare `/wp-content/uploads/...` regex: each match is
	 * trimmed of trailing punctuation and required to end in a known media
	 * extension before being returned. This prevents partial URL fragments
	 * inside encoded HTML / backup blobs / log meta from being resolved as
	 * real attachments (a known source of false-positive "used" records).
	 *
	 * @param string $haystack String to scan.
	 *
	 * @return array<int,string> Uploads-relative paths (without leading `/wp-content/uploads/`).
	 */
	private function extract_upload_paths_from_string( string $haystack ): array {
		if ( '' === $haystack || false === strpos( $haystack, '/wp-content/uploads/' ) ) {
			return [];
		}

		if ( ! preg_match_all( '/\/wp-content\/uploads\/([^\s"\'<>)\\\;,]+)/i', $haystack, $matches ) ) {
			return [];
		}

		$exts = 'jpg|jpeg|jpe|gif|png|webp|svg|bmp|ico|tif|tiff|heic|heif|avif|mp3|wav|ogg|m4a|mp4|m4v|mov|avi|wmv|flv|webm|mkv|pdf|doc|docx|xls|xlsx|ppt|pptx|csv|txt|zip|rar|7z|psd|ai|eps';
		$out  = [];

		foreach ( $matches[1] as $rel ) {
			$rel = rtrim( $rel, ").,;:!?" );
			if ( '' === $rel ) {
				continue;
			}
			// Require a real media extension at the tail of the path.
			if ( ! preg_match( '/\.(' . $exts . ')$/i', $rel ) ) {
				continue;
			}
			$out[] = $rel;
		}

		return $out;
	}

	private function array_has_builder_marker( array $data ): bool {
		// Markers are looked up by isset() — cheap, no full key walk.
		// Only structural identifiers go here. We deliberately avoid generic
		// names like `settings` or `type` because ACF / random CPT meta use
		// them too, and a false promote here would re-introduce the noise
		// this gate exists to suppress.
		// Beaver Builder is intentionally not detected by the generic
		// `type` + `node` combo — that pair appears in plenty of unrelated
		// serialized meta (relation lists, ACF flexible content, etc.) and
		// promoting on it re-introduces false positives. Beaver entry points
		// already opt in via `is_builder_caller_type( 'beaver_builder' )`.
		return isset( $data['widgetType'] )         // Elementor widget.
			|| isset( $data['elType'] )             // Elementor element.
			|| isset( $data['blockName'] )          // Gutenberg parsed block.
			|| isset( $data['et_pb_module_type'] ); // Divi module marker.
	}

	/**
	 * Detect images in custom post meta fields.
	 *
	 * Scans all meta keys including _prefixed ones. For _prefixed keys, only
	 * checks serialized arrays/JSON (where ACF, WooCommerce, etc. store IDs).
	 * For non-prefixed keys, also checks plain numeric values and URLs.
	 *
	 * Uses the preloaded lookup map — zero DB queries per value.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_meta( \WP_Post $post ): void {
		$meta = get_post_meta( $post->ID );
		if ( empty( $meta ) ) {
			return;
		}

		// Build a set of known attachment IDs from the lookup map for O(1) checks.
		$attachment_ids = $this->get_known_attachment_ids();

		// Keys already handled by dedicated methods — skip to avoid duplicates.
		$skip_keys = [
			'_thumbnail_id',
			'_elementor_data',
			'_product_image_gallery',
			'_fl_builder_data',
			'_et_builder_settings',
			'brizy_post_uid',
			'_wpb_shortcodes_custom_css',
			'_tsmlt_image_usages',
			'_tsmlt_usage_tracked',
		];

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}

			$is_private        = strpos( $key, '_' ) === 0;
			$is_image_like_key = $this->is_image_like_meta_key( $key );

			foreach ( (array) $values as $value ) {
				// For _prefixed keys: only scan serialized arrays and JSON (not plain values).
				if ( $is_private ) {
					$this->scan_meta_value_deep( $value, $post, $attachment_ids );
					continue;
				}

				// For non-prefixed keys: only treat a bare numeric value as an
				// attachment reference when the key name itself signals an image
				// field. Otherwise a numeric meta (related-post ID, term ID,
				// price, quantity, sort order, etc.) that happens to match an
				// attachment ID is recorded as a false-positive usage.
				if ( is_numeric( $value ) ) {
					if ( ! $is_image_like_key ) {
						continue;
					}
					$id = absint( $value );
					if ( $id && isset( $attachment_ids[ $id ] ) ) {
						$this->record_usage( $id, $post, 'meta' );
					}
				} elseif ( is_string( $value ) ) {
					$this->scan_meta_value_deep( $value, $post, $attachment_ids );
				}
			}
		}
	}

	/**
	 * Whether a meta key name looks like it stores an image/attachment reference.
	 *
	 * Gate for treating bare numeric meta values as attachment IDs. Without
	 * this gate, any numeric meta (related-post ID, term ID, price, quantity,
	 * sort order) that happens to match an attachment ID gets recorded as a
	 * usage — the dominant source of false positives in the unused finder.
	 *
	 * @param string $key Meta key.
	 *
	 * @return bool
	 */
	private function is_image_like_meta_key( string $key ): bool {
		$key = strtolower( $key );

		// Common image/attachment field naming patterns.
		$needles = [
			'image',
			'thumbnail',
			'thumb',
			'photo',
			'picture',
			'attachment',
			'gallery',
			'media',
			'logo',
			'icon',
			'avatar',
			'banner',
			'cover',
			'featured',
			'background',
			'poster',
		];

		foreach ( $needles as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deeply scan a meta value for attachment IDs and URLs.
	 *
	 * Handles serialized PHP arrays, JSON strings, comma-separated IDs,
	 * and plain URLs. Used for both _prefixed and non-prefixed meta keys.
	 *
	 * @param mixed    $value          Meta value to scan.
	 * @param \WP_Post $post           Post object.
	 * @param array    $attachment_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function scan_meta_value_deep( $value, \WP_Post $post, array $attachment_ids ): void {
		if ( ! is_string( $value ) || strlen( $value ) < 2 ) {
			return;
		}

		// 1. Try unserialized array.
		$unserialized = maybe_unserialize( $value );
		if ( is_array( $unserialized ) ) {
			$this->extract_attachment_ids_from_array( $unserialized, $post, 'meta' );
			return;
		}

		// 2. Try JSON.
		if ( '{' === $value[0] || '[' === $value[0] ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$this->extract_attachment_ids_from_array( $decoded, $post, 'meta' );
				return;
			}
		}

		// 3. Comma-separated IDs (e.g. "123,456,789").
		if ( preg_match( '/^\d+(?:,\s*\d+)+$/', $value ) ) {
			$ids = explode( ',', $value );
			foreach ( $ids as $id ) {
				$id = absint( trim( $id ) );
				if ( $id && isset( $attachment_ids[ $id ] ) ) {
					$this->record_usage( $id, $post, 'meta' );
				}
			}
			return;
		}

		// 4. URL or content containing /wp-content/uploads/.
		if ( strpos( $value, '/wp-content/uploads/' ) !== false ) {
			// 4a. Try the value as a single direct URL first (plain URL meta fields).
			// Meta values store full uploads paths verbatim — disable basename
			// fallback so unrelated entries that share a common filename
			// (e.g. `audio.mp3`, `logo.png`) can't be pinned onto this post.
			$attachment_id = $this->get_attachment_id_by_url( $value, false );
			if ( $attachment_id ) {
				$this->record_usage( $attachment_id, $post, 'meta' );
			}

			// 4b. Decode HTML entities so URLs inside encoded attributes
			//     (e.g. data-image="&lt;img src=&quot;...&quot;&gt;") are reachable,
			//     then extract every uploads URL from the blob. Each match must
			//     end in a known media extension to keep encoded HTML fragments
			//     from being mis-resolved.
			$decoded   = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$rel_paths = $this->extract_upload_paths_from_string( $decoded );
			if ( ! empty( $rel_paths ) ) {
				$base_url = $this->get_uploads_base_url();
				foreach ( $rel_paths as $rel ) {
					$blob_id = $this->get_attachment_id_by_url( $base_url . $rel, false );
					if ( $blob_id ) {
						$this->record_usage( $blob_id, $post, 'meta' );
					}
				}
			}
		}
	}

	/**
	 * Buffer a usage record. Deduplicated by attachment+post+type.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param \WP_Post $post Post where the image is used.
	 * @param string   $usage_type Type of usage.
	 *
	 * @return void
	 */
	private function record_usage( int $attachment_id, \WP_Post $post, string $usage_type ): void {
		$key = $attachment_id . ':' . $post->ID . ':' . $usage_type;

		if ( ! isset( $this->usages_buffer[ $attachment_id ] ) ) {
			$this->usages_buffer[ $attachment_id ] = [];
		}

		// Avoid duplicates within the buffer.
		if ( isset( $this->usages_buffer[ $attachment_id ][ $key ] ) ) {
			return;
		}

		$this->usages_buffer[ $attachment_id ][ $key ] = [
			'post_id'    => $post->ID,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'usage_type' => $usage_type,
		];
	}

	/**
	 * Flush the usages buffer to post meta and set post_parent.
	 *
	 * @param bool $is_first_batch When true, skip the read-merge step. The
	 *                             scan-start handler clears all usage meta
	 *                             before the first batch runs, so there's
	 *                             nothing to merge — saving one DB read per
	 *                             affected attachment on the first batch.
	 *
	 * @return void
	 */
	private function flush_usages_buffer( bool $is_first_batch = false ): void {
		foreach ( $this->usages_buffer as $attachment_id => $entries ) {
			$new_usages = array_values( $entries );

			// Merge with any existing meta from previous batches. Skipped on
			// the first batch because clear_all_usage_meta() just wiped the
			// keyspace — the read would always come back empty.
			if ( ! $is_first_batch ) {
				$existing = get_post_meta( $attachment_id, self::META_KEY, true );
				if ( ! empty( $existing ) && is_array( $existing ) ) {
					// Deduplicate by key.
					$existing_keys = [];
					foreach ( $existing as $item ) {
						$existing_keys[ $item['post_id'] . ':' . $item['usage_type'] ] = true;
					}
					foreach ( $new_usages as $item ) {
						$k = $item['post_id'] . ':' . $item['usage_type'];
						if ( ! isset( $existing_keys[ $k ] ) ) {
							$existing[] = $item;
						}
					}
					$new_usages = $existing;
				}
			}

			update_post_meta( $attachment_id, self::META_KEY, $new_usages );

			// Set post_parent if not already set. Prefer a 'featured' usage
			// (the image is the post's thumbnail) over any other usage type,
			// since featured-image attachment is the strongest signal of ownership.
			$current_parent = (int) get_post_field( 'post_parent', $attachment_id );
			if ( ! $current_parent ) {
				$parent_post_id = 0;
				foreach ( $new_usages as $usage ) {
					if ( ( $usage['usage_type'] ?? '' ) === 'featured' && ! empty( $usage['post_id'] ) ) {
						$parent_post_id = (int) $usage['post_id'];
						break;
					}
				}
				if ( ! $parent_post_id && ! empty( $new_usages[0]['post_id'] ) ) {
					$parent_post_id = (int) $new_usages[0]['post_id'];
				}
				if ( $parent_post_id ) {
					wp_update_post( [
						'ID'          => $attachment_id,
						'post_parent' => $parent_post_id,
					] );
				}
			}
		}

		$this->usages_buffer = [];
	}

	/**
	 * Build a site-wide relative-path → attachment_id lookup map.
	 *
	 * Loads all _wp_attached_file meta values (relative paths stored by WP, e.g.
	 * "2024/01/photo.jpg") in a single query and builds a map keyed by the
	 * basename (photo.jpg) pointing to the attachment ID. A second query loads
	 * full GUIDs as a fallback for unusual attachment configurations.
	 *
	 * Called once per scan_batch() — eliminates per-URL DB queries.
	 *
	 * @return void
	 */
	private function build_url_lookup_map(): void {
		if ( null !== $this->url_lookup_map ) {
			return;
		}

		$this->url_lookup_map = [];

		// Single query: load all _wp_attached_file entries (relative path → post_id).
		// This covers all standard WordPress attachments — one query instead of two.
		$meta_rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->get();

		$base_url = $this->get_uploads_base_url();

		// Two-pass build so we can detect ambiguous keys.
		//
		// Pass 1: relative path + full URL. Both are unique per attachment and
		// always safe to record.
		// Pass 2: basename + size-stripped basename. These can collide between
		// attachments (e.g. two `hero.jpg` files in different year/month
		// folders). When they collide we mark the slot with a sentinel `0`
		// so the lookup refuses to guess — better to miss a match than
		// pin the URL on the wrong attachment and inflate "used" counts
		// across the site.
		$basename_seen = [];          // basename => first post_id seen
		$basename_amb  = [];          // basename => true once any collision detected
		$stripped_seen = [];
		$stripped_amb  = [];

		foreach ( ( $meta_rows ?: [] ) as $row ) {
			$post_id  = absint( $row['post_id'] );
			$rel_path = $row['meta_value'] ?? '';
			if ( ! $post_id || ! $rel_path ) {
				continue;
			}

			// Pass 1: unique paths.
			$this->url_lookup_map[ $rel_path ]              = $post_id;
			$this->url_lookup_map[ $base_url . $rel_path ]  = $post_id;

			// Pass 2 collision tracking — basename.
			$basename = basename( $rel_path );
			if ( '' === $basename ) {
				continue;
			}
			if ( isset( $basename_seen[ $basename ] ) && $basename_seen[ $basename ] !== $post_id ) {
				$basename_amb[ $basename ] = true;
			} else {
				$basename_seen[ $basename ] = $post_id;
			}

			// Pass 2 collision tracking — size-stripped basename
			// (so `hero-300x200.jpg` and `hero-1024x768.jpg` both fold
			// into `hero.jpg`).
			$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $basename );
			if ( $stripped !== $basename ) {
				if ( isset( $stripped_seen[ $stripped ] ) && $stripped_seen[ $stripped ] !== $post_id ) {
					$stripped_amb[ $stripped ] = true;
				} else {
					$stripped_seen[ $stripped ] = $post_id;
				}
			}
		}

		// Commit only unambiguous basenames. Ambiguous slots get a sentinel
		// `0` which `get_attachment_id_by_url` treats as a no-match (refuses
		// to guess between candidates).
		foreach ( $basename_seen as $basename => $post_id ) {
			$this->url_lookup_map[ $basename ] = isset( $basename_amb[ $basename ] ) ? 0 : $post_id;
		}
		foreach ( $stripped_seen as $stripped => $post_id ) {
			// Don't overwrite an unambiguous direct basename hit.
			if ( ! isset( $this->url_lookup_map[ $stripped ] ) ) {
				$this->url_lookup_map[ $stripped ] = isset( $stripped_amb[ $stripped ] ) ? 0 : $post_id;
			}
		}
	}

	/**
	 * Build attachment_id → owning post_id map for featured images.
	 *
	 * Single `_thumbnail_id` postmeta query, joined against published posts.
	 * Used by `record_usages_from_urls()` to drop `permalink` / `rendered`
	 * hits where the matched attachment is the featured image of a *different*
	 * post — i.e. a related-posts / recent-posts widget rendering another
	 * post's thumbnail on this page.
	 *
	 * @return void
	 */
	private function build_featured_image_owners(): void {
		if ( null !== $this->featured_image_owners ) {
			return;
		}

		$this->featured_image_owners = [];

		$rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_thumbnail_id' )
			->get();

		foreach ( ( $rows ?: [] ) as $row ) {
			$post_id       = absint( $row['post_id'] ?? 0 );
			$attachment_id = absint( $row['meta_value'] ?? 0 );
			if ( ! $post_id || ! $attachment_id ) {
				continue;
			}
			// First-seen wins — we only need to know that *some* post owns the
			// attachment via featured image, not the full list.
			if ( ! isset( $this->featured_image_owners[ $attachment_id ] ) ) {
				$this->featured_image_owners[ $attachment_id ] = $post_id;
			}
		}
	}

	/**
	 * Build the set of attachment IDs known to be site-wide for this batch.
	 *
	 * Sources, in order:
	 *  - The current `usages_buffer` — covers batch 1, where `detect_sitewide_usage()`
	 *    has just populated `post_id=0` entries that haven't been flushed yet.
	 *  - Persisted `_tsmlt_image_usages` postmeta — covers batches 2+ and rendered-HTML
	 *    scans, where the site-wide entries were saved in a prior flush.
	 *
	 * An attachment is considered site-wide as soon as it has *any* entry with
	 * `post_id=0` (site_settings, term_meta, widget, option, etc.).
	 *
	 * @return void
	 */
	private function build_sitewide_attachment_ids(): void {
		if ( null !== $this->sitewide_attachment_ids ) {
			return;
		}

		$this->sitewide_attachment_ids = [];

		// 1. Current in-memory buffer (batch 1 path — site-wide just detected).
		foreach ( $this->usages_buffer as $attachment_id => $entries ) {
			foreach ( $entries as $entry ) {
				if ( 0 === (int) ( $entry['post_id'] ?? 0 ) ) {
					$this->sitewide_attachment_ids[ (int) $attachment_id ] = true;
					break;
				}
			}
		}

		// 2. Persisted meta (batches 2+ and scan_rendered_html() path).
		$rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', self::META_KEY )
			->get();

		foreach ( ( $rows ?: [] ) as $row ) {
			$attachment_id = absint( $row['post_id'] ?? 0 );
			if ( ! $attachment_id || isset( $this->sitewide_attachment_ids[ $attachment_id ] ) ) {
				continue;
			}
			$usages = maybe_unserialize( $row['meta_value'] ?? '' );
			if ( ! is_array( $usages ) ) {
				continue;
			}
			foreach ( $usages as $usage ) {
				if ( 0 === (int) ( $usage['post_id'] ?? 0 ) ) {
					$this->sitewide_attachment_ids[ $attachment_id ] = true;
					break;
				}
			}
		}
	}

	/**
	 * Get attachment ID by its URL using the preloaded lookup map.
	 *
	 * Falls back to basename lookup for scaled/sized variants (e.g., image-300x200.jpg).
	 *
	 * Path-anchored lookups are always safe — relative paths are unique per
	 * attachment in the map. The basename fallback exists for HTML where
	 * size-suffixed image URLs (`hero-300x200.jpg`) need to fold onto the
	 * original. Callers operating on user-set or builder-stored single-value
	 * URLs (meta, theme mods, widget options, term meta) should pass
	 * `$allow_basename_fallback = false` — those sources record full paths
	 * verbatim, so a basename-only hit there is always a false positive
	 * pinning unrelated content onto an attachment that happens to share a
	 * common filename (`audio.mp3`, `logo.png`, etc.).
	 *
	 * @param string $url                     Attachment URL or partial path.
	 * @param bool   $allow_basename_fallback Allow basename / stripped-basename matches.
	 *
	 * @return int Attachment ID, or 0 if not found.
	 */
	private function get_attachment_id_by_url( string $url, bool $allow_basename_fallback = true ): int {
		if ( null === $this->url_lookup_map ) {
			// Safety fallback if called outside a batch context.
			$this->build_url_lookup_map();
		}

		// 1. Exact GUID match.
		if ( isset( $this->url_lookup_map[ $url ] ) ) {
			return $this->url_lookup_map[ $url ];
		}

		// 2. Extract the relative path after /uploads/ and try that.
		// Path-based slots are unique per attachment (built unconditionally
		// in pass 1 of build_url_lookup_map) so a hit here is always trusted.
		$pos = strpos( $url, '/uploads/' );
		if ( false !== $pos ) {
			$rel_path = ltrim( substr( $url, $pos + strlen( '/uploads/' ) ), '/' );
			if ( isset( $this->url_lookup_map[ $rel_path ] ) && $this->url_lookup_map[ $rel_path ] > 0 ) {
				return $this->url_lookup_map[ $rel_path ];
			}

			// 5. Strip size suffix from relative path (e.g. 2026/04/image-300x200.jpg → 2026/04/image.jpg).
			// Try this BEFORE the basename fallback because a path-anchored
			// stripped match is unambiguous, while a basename match is not.
			$stripped_rel = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $rel_path );
			if ( $stripped_rel !== $rel_path && isset( $this->url_lookup_map[ $stripped_rel ] ) && $this->url_lookup_map[ $stripped_rel ] > 0 ) {
				return $this->url_lookup_map[ $stripped_rel ];
			}

			if ( ! $allow_basename_fallback ) {
				return 0;
			}

			// 3. Basename fallback. The map stores `0` for any basename that
			// collides between attachments (e.g. two `hero.jpg` files in
			// different folders). A `0` value means "refuse to guess" — we
			// return no match rather than silently pinning the URL on the
			// wrong attachment and inflating usage counts site-wide.
			$basename = basename( $rel_path );
			if ( ! empty( $this->url_lookup_map[ $basename ] ) ) {
				return $this->url_lookup_map[ $basename ];
			}

			// 4. Strip WP size suffix (e.g. image-300x200.jpg → image.jpg)
			//    to match the original attachment file. Same collision-safe
			//    semantics — sentinel 0 falls through.
			$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $basename );
			if ( $stripped !== $basename && ! empty( $this->url_lookup_map[ $stripped ] ) ) {
				return $this->url_lookup_map[ $stripped ];
			}
		}

		return 0;
	}

	/**
	 * Get usage statistics for a specific attachment from post meta.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{total_usage: int, by_type: array, by_post: array}
	 */
	public function get_usage_stats( int $attachment_id ): array {
		$result = [
			'total_usage' => 0,
			'by_type'     => [],
			'by_post'     => [],
		];

		$usages = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( empty( $usages ) || ! is_array( $usages ) ) {
			return $result;
		}

		$result['total_usage'] = count( $usages );

		$by_type = [];
		$by_post = [];

		foreach ( $usages as $usage ) {
			$type    = $usage['usage_type'] ?? 'unknown';
			$post_id = $usage['post_id'] ?? 0;
			$by_type[ $type ] = ( $by_type[ $type ] ?? 0 ) + 1;

			$by_post[] = [
				'post_id'    => $post_id,
				'post_title' => $usage['post_title'] ?? '',
				'post_type'  => $usage['post_type'] ?? '',
				'post_link'  => $post_id ? get_permalink( $post_id ) : '',
				'usage_type' => $type,
			];
		}

		$result['by_type'] = $by_type;
		$result['by_post'] = $by_post;

		return $result;
	}

	/**
	 * Scan a single post for image usage on save.
	 *
	 * Removes old usage records for this post from affected attachments only,
	 * then re-detects and records current usages.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function scan_single_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return;
		}

		$this->remove_post_usages( $post_id );

		// Build the lookup map and detect usages in this post.
		$this->build_url_lookup_map();
		$this->usages_buffer = [];
		$this->detect_usage_in_post( $post );
		$this->flush_usages_buffer();
		$this->url_lookup_map = null;
		$this->known_attachment_ids = null;
		$this->upload_base_url = null;
		$this->fallback_attachment_ids = null;
		$this->featured_image_owners = null;
		$this->sitewide_attachment_ids = null;
	}

	/**
	 * Remove old usage records for a specific post from all affected attachments.
	 *
	 * Uses a targeted LIKE query to find only attachments that reference this post_id,
	 * instead of loading ALL usage meta rows.
	 *
	 * WordPress stores arrays via `update_post_meta()` as PHP serialized strings.
	 * The post_id inside looks like: `s:7:"post_id";i:123;`
	 *
	 * @param int $post_id Post ID to remove.
	 *
	 * @return void
	 */
	private function remove_post_usages( int $post_id ): void {
		global $wpdb;

		// Match serialized format: s:7:"post_id";i:{ID};
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_KEY,
				'%' . $wpdb->esc_like( '"post_id";i:' . $post_id . ';' ) . '%'
			),
			ARRAY_A
		);

		foreach ( ( $affected_rows ?: [] ) as $row ) {
			$att_id   = absint( $row['post_id'] );
			$existing = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $existing ) ) {
				continue;
			}

			$filtered = array_filter(
				$existing,
				fn( $item ) => (int) ( $item['post_id'] ?? 0 ) !== $post_id
			);

			if ( count( $filtered ) !== count( $existing ) ) {
				if ( empty( $filtered ) ) {
					delete_post_meta( $att_id, self::META_KEY );
				} else {
					update_post_meta( $att_id, self::META_KEY, array_values( $filtered ) );
				}
			}
		}
	}

	/**
	 * Cron hook name for the self-rescheduling scan tick.
	 */
	const SCAN_TICK_HOOK = 'tsmlt_used_where_scan_tick';

	/**
	 * Transient key used to serialise concurrent tick handlers. WP-Cron can fire
	 * the same scheduled event twice on simultaneous incoming requests; the
	 * lock makes the second one bail.
	 */
	const SCAN_LOCK_KEY = 'tsmlt_used_where_scan_lock';

	/**
	 * Batch size for cron-driven scans when the permalink fetch is OFF.
	 * 10 posts per tick is a good balance between progress and worker hold time.
	 */
	const SCAN_TICK_BATCH_FAST = 10;

	/**
	 * Batch size when the permalink fetch is ON. Halved because each post may
	 * trigger a loopback HTTP request, which holds a PHP-FPM worker for the
	 * duration of a full page render — typically 0.5–3s on Woo stores.
	 */
	const SCAN_TICK_BATCH_DEEP = 5;

	/**
	 * Delay between ticks. Gives PHP-FPM workers breathing room between scan
	 * batches so concurrent visitor requests don't queue behind back-to-back
	 * loopback fetches. 30s spaces out the worker pressure enough that even
	 * tiny hosts (2-worker pools) keep capacity for real visitors during a
	 * scan window.
	 */
	const SCAN_TICK_DELAY = 20;

	/**
	 * Whether deep permalink-fetch scanning is enabled.
	 *
	 * Off by default: catches the common cases (post content, gallery, meta,
	 * builders) without firing a loopback HTTP request per post. Users who need
	 * to detect images injected by themes, plugins, shortcodes, or meta-box
	 * buttons (e.g. size-chart popups) opt in via the setting or the
	 * `tsmlt_scan_permalink_enabled` filter.
	 *
	 * @return bool
	 */
	public static function is_permalink_fetch_enabled(): bool {
		$options = Fns::get_options();
		// Default on: when the setting is absent, deep scan runs. An admin can
		// explicitly turn it off by saving `scan_permalink_fetch = 0`.
		$setting = ! isset( $options['scan_permalink_fetch'] ) || ! empty( $options['scan_permalink_fetch'] );
		return (bool) apply_filters( 'tsmlt_scan_permalink_enabled_default', $setting );
	}

	/**
	 * Resolve the batch size based on whether deep scanning is on.
	 *
	 * @return int
	 */
	private function tick_batch_size(): int {
		return self::is_permalink_fetch_enabled() ? self::SCAN_TICK_BATCH_DEEP : self::SCAN_TICK_BATCH_FAST;
	}

	/**
	 * Get the scan status surface used by the polling UI.
	 *
	 * Returns a richer shape than the legacy contract: `state` is the source of
	 * truth for the frontend (idle | queued | running | complete | cancelled |
	 * error), with `scanned` / `total` / `complete` retained for backwards
	 * compatibility with callers that haven't migrated to `state` yet.
	 *
	 * @return array
	 */
	public function get_scan_status(): array {
		$status = get_option( 'tsmlt_used_where_scan_status', [] );

		$state     = (string) ( $status['state'] ?? ( $status['complete'] ?? false ? 'complete' : 'idle' ) );
		$processed = (int) ( $status['processed'] ?? 0 );
		$total     = (int) ( $status['total'] ?? 0 );

		// Stall recovery. If the scan is meant to be active but no tick has
		// run for a while (server restart, worker crash, cron not firing,
		// loopback blocked), heal it: drop any stale lock and re-arm the
		// chain with a fresh single event. Cheap because get_scan_status is
		// called by the polling UI every few seconds — recovery happens
		// automatically the next time the admin has the page open.
		if ( in_array( $state, [ 'queued', 'running' ], true ) && ! empty( $status['last_tick_at'] ) ) {
			$last_tick_ts = (int) strtotime( get_gmt_from_date( (string) $status['last_tick_at'] ) );
			$stalled_secs = $last_tick_ts > 0 ? ( time() - $last_tick_ts ) : 0;
			// 3 minutes without progress is well past any healthy tick.
			if ( $stalled_secs > 180 ) {
				delete_transient( self::SCAN_LOCK_KEY );
				$next_offset = (int) ( $status['next_offset'] ?? 0 );
				wp_schedule_single_event( time() + 1, self::SCAN_TICK_HOOK, [ $next_offset ] );
				if ( function_exists( 'spawn_cron' ) ) {
					spawn_cron();
				}
			}
		}

		// `notified` tracks whether the user has been shown a "scan finished"
		// toast for this run. Set false when a scan is started, flipped true
		// when the frontend acknowledges. Only meaningful in terminal states
		// (complete / cancelled / error). Absent on legacy rows — treated as
		// already-notified so we don't fire notices for scans that finished
		// before this feature shipped.
		$notified = array_key_exists( 'notified', $status )
			? (bool) $status['notified']
			: true;

		return [
			'state'        => $state,
			'scanned'      => $processed,
			'total'        => $total,
			'complete'     => 'complete' === $state,
			'resumable'    => in_array( $state, [ 'queued', 'running' ], true ) && $processed > 0,
			'notified'     => $notified,
			'last_update'  => (string) ( $status['timestamp'] ?? '' ),
			'last_tick_at' => (string) ( $status['last_tick_at'] ?? '' ),
			'started_at'   => (string) ( $status['started_at'] ?? '' ),
			'last_error'   => (string) ( $status['last_error'] ?? '' ),
			'next_offset'  => (int) ( $status['next_offset'] ?? 0 ),
		];
	}

	/**
	 * Mark the latest terminal scan state as acknowledged by the user.
	 *
	 * The polling UI calls this after showing a "scan complete" / "scan
	 * cancelled" / "scan failed" toast on first visit, so the toast doesn't
	 * fire again on subsequent page loads.
	 *
	 * @return array Status snapshot.
	 */
	public function acknowledge_scan_status(): array {
		$this->update_scan_status( [ 'notified' => true ] );
		return $this->get_scan_status();
	}

	/**
	 * Persist the scan status row used by polling UI and tick handlers.
	 *
	 * Always merges into the existing row so callers only need to pass changed
	 * fields. Stamps `timestamp` on every write so we can detect stalled scans.
	 *
	 * @param array $changes Fields to merge in.
	 *
	 * @return void
	 */
	private function update_scan_status( array $changes ): void {
		$current = get_option( 'tsmlt_used_where_scan_status', [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}
		$next              = array_merge( $current, $changes );
		$next['timestamp'] = current_time( 'mysql' );
		update_option( 'tsmlt_used_where_scan_status', $next, false );
	}

	/**
	 * Start a cron-driven full scan.
	 *
	 * Wipes existing usage data, resets the status row to `queued`, and
	 * schedules the first tick. Subsequent ticks self-reschedule until the
	 * scan completes or is cancelled. Safe to call when a scan is already
	 * running — it will hard-restart from offset 0.
	 *
	 * @return array Status snapshot for the response.
	 */
	public function start_scheduled_scan(): array {
		// Cancel any in-flight scan so we don't race the old tick chain.
		$this->cancel_scheduled_scan();

		// Wipe old usage data once, here. The tick handler must NOT clear.
		$this->clear_all_usage_meta();
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', '_tsmlt_usage_tracked' )
			->execute();

		// Reset the per-post permalink fingerprints. Without this, the deep
		// scan would short-circuit on every post that was scanned previously
		// (the fingerprint compares post_modified_gmt + thumbnail_id +
		// _product_image_gallery — none of which change between scans), so
		// the rendered-HTML pass would silently skip everything. Wiping the
		// fingerprints forces the next scan to actually fetch each permalink.
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', '_tsmlt_permalink_fp' )
			->execute();

		// Compute the initial total so the progress bar has a denominator
		// from the very first poll, before any tick has run. Mirrors the
		// total computed inside process_batch() — posts first, then HPOS
		// orders when WooCommerce HPOS is active.
		$post_types = $this->get_scannable_post_types();
		$total = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total += (int) ( $counts->publish ?? 0 );
			$total += (int) ( $counts->draft ?? 0 );
			$total += (int) ( $counts->pending ?? 0 );
			$total += (int) ( $counts->private ?? 0 );
			$total += (int) ( $counts->future ?? 0 );
		}
		$total += $this->get_hpos_order_total();

		$now_mysql = current_time( 'mysql' );
		update_option( 'tsmlt_used_where_scan_status', [
			'state'        => 'queued',
			'processed'    => 0,
			'total'        => $total,
			'complete'     => false,
			'next_offset'  => 0,
			'started_at'   => $now_mysql,
			'last_tick_at' => '',
			'last_error'   => '',
			'notified'     => false, // arm a "scan finished" toast for whenever this run terminates.
			'timestamp'    => $now_mysql,
		], false );

		// Schedule the first tick. Args are passed by reference to identify
		// the event for cancellation later, so always pass them explicitly.
		wp_schedule_single_event( time() + 1, self::SCAN_TICK_HOOK, [ 0 ] );

		return $this->get_scan_status();
	}

	/**
	 * Run one batch tick and reschedule the next.
	 *
	 * Invoked by WP-Cron for the SCAN_TICK_HOOK action. Uses a transient lock
	 * to prevent two simultaneous workers from racing on the same offset, and
	 * bails cleanly if the user has cancelled the scan.
	 *
	 * @param int $offset Offset to process this tick.
	 *
	 * @return void
	 */
	public function run_tick_batch( $offset = 0 ): void {
		$offset = absint( $offset );

		// Bail if the user cancelled or cleared mid-scan.
		$status = get_option( 'tsmlt_used_where_scan_status', [] );
		$state  = (string) ( $status['state'] ?? 'idle' );
		if ( ! in_array( $state, [ 'queued', 'running' ], true ) ) {
			return;
		}

		// Concurrency guard. The lock value is the unix timestamp it was set
		// at, so we can age-check it: if the lock is older than 90s it almost
		// certainly belongs to a worker that died (server restart, OOM,
		// timeout) — proceed and overwrite. Catches the case where a normal
		// transient TTL expiration would still leave us blocked for up to
		// 120s after a real worker died.
		$lock = get_transient( self::SCAN_LOCK_KEY );
		if ( $lock && ( time() - (int) $lock ) < 90 ) {
			return; // another worker is genuinely running
		}
		set_transient( self::SCAN_LOCK_KEY, time(), 120 );

		try {
			$this->update_scan_status( [
				'state'        => 'running',
				'last_tick_at' => current_time( 'mysql' ),
			] );

			$result = $this->process_batch( $offset, $this->tick_batch_size(), 0 === $offset );

			$next_offset = (int) $result['processed'];
			$total       = (int) $result['total'];
			$complete    = (bool) $result['complete'];

			// Final-batch reconciliation pass. Removes incidental
			// `permalink` / `rendered` usages on posts that don't actually
			// own the image — these come from related-products widgets,
			// schema markup, lazy-load placeholders, sidebar widgets, etc.
			// Runs once when the last batch finishes; cheap because it
			// touches only attachments that have an owning + incidental mix.
			if ( $complete ) {
				$this->reconcile_incidental_usages();
			}

			$this->update_scan_status( [
				'processed'   => $next_offset,
				'total'       => $total,
				'next_offset' => $next_offset,
				'state'       => $complete ? 'complete' : 'running',
				'complete'    => $complete,
			] );

			if ( ! $complete ) {
				// Schedule the next tick. Small delay lets WP-Cron interleave
				// other work and prevents tight-loop pile-ups on busy sites.
				wp_schedule_single_event( time() + self::SCAN_TICK_DELAY, self::SCAN_TICK_HOOK, [ $next_offset ] );

				// Self-perpetuate: trigger WP-Cron immediately instead of
				// waiting for the next incoming visitor. Without this, scans
				// stall on quiet sites between ticks. spawn_cron() does a
				// non-blocking loopback POST to wp-cron.php with a 0.01s
				// timeout — cheap, and silent on hosts that block loopback
				// (we'd just fall back to visitor-triggered cron, no worse
				// than before).
				if ( function_exists( 'spawn_cron' ) ) {
					spawn_cron();
				}
			}
		} catch ( \Throwable $e ) {
			$this->update_scan_status( [
				'state'      => 'error',
				'last_error' => $e->getMessage(),
			] );
		} finally {
			delete_transient( self::SCAN_LOCK_KEY );
		}
	}

	/**
	 * Cancel an in-progress cron-driven scan.
	 *
	 * Marks the status row as `cancelled` (any running tick will bail on its
	 * next status read) and unschedules every queued tick instance regardless
	 * of its offset argument.
	 *
	 * @return array Status snapshot for the response.
	 */
	public function cancel_scheduled_scan(): array {
		// Remove every queued tick for this hook, regardless of args.
		wp_unschedule_hook( self::SCAN_TICK_HOOK );

		// Drop the lock so a stuck worker can't keep the scan "running".
		delete_transient( self::SCAN_LOCK_KEY );

		$status = get_option( 'tsmlt_used_where_scan_status', [] );
		$state  = (string) ( $status['state'] ?? 'idle' );
		if ( in_array( $state, [ 'queued', 'running' ], true ) ) {
			$this->update_scan_status( [
				'state' => 'cancelled',
			] );
		}

		return $this->get_scan_status();
	}

	/**
	 * Clear all scan results — removes meta from all attachments and resets post_parent.
	 *
	 * @return array
	 */
	public function clear_scan(): array {
		// Stop any cron-driven scan in flight before wiping data.
		wp_unschedule_hook( self::SCAN_TICK_HOOK );
		delete_transient( self::SCAN_LOCK_KEY );

		$this->clear_all_usage_meta();
		delete_option( 'tsmlt_used_where_scan_status' );

		// Clear frontend visit tracking flags so posts get re-scanned on next visit.
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', '_tsmlt_usage_tracked' )
			->execute();

		// Reset permalink-fingerprints so the next scan's deep pass actually
		// re-fetches each post (otherwise the fingerprint short-circuit would
		// skip every post that was scanned before).
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', '_tsmlt_permalink_fp' )
			->execute();

		return [
			'updated' => true,
			'message' => esc_html__( 'Scan cleared successfully.', 'media-library-tools' ),
		];
	}

	/**
	 * Delete _tsmlt_image_usages meta from all attachments and reset post_parent to 0.
	 *
	 * Optimized: uses two bulk queries instead of loading all attachment IDs into
	 * PHP and looping. The query builder does not support JOINs in UPDATE, so we:
	 * 1. Fetch the affected attachment IDs in one SELECT.
	 * 2. Bulk-delete the meta rows in one DELETE.
	 * 3. If there are affected IDs, bulk-reset post_parent via one UPDATE with whereIn.
	 *
	 * @return void
	 */
	/**
	 * Drop incidental `permalink` / `rendered` usages when the image is
	 * already owned by stronger signals on a different set of posts.
	 *
	 * Final-batch reconciliation pass. For each attachment that has BOTH:
	 *   - at least one "owning" usage (featured / content / gallery / meta /
	 *     builder), AND
	 *   - one or more `permalink` / `rendered` usages on posts NOT in the
	 *     owning set,
	 * we strip the latter. Those records are almost always artifacts of:
	 *   - related-products widgets ("you may also like…") rendering an image
	 *     belonging to another product on this product's permalink,
	 *   - JSON-LD product schema / OpenGraph meta tags repeating an image URL
	 *     in `<head>` of every page,
	 *   - lazy-load placeholders, sidebar widgets, recently-viewed sections,
	 *   - cart / checkout / account pages displaying the user's basket items.
	 *
	 * The owning signals are unambiguous: the post's own structured fields
	 * actually reference the image. So when those exist, an HTML hit on a
	 * different post is incidental, not real usage.
	 *
	 * Cheap to run because it touches only attachments that have any meta
	 * row at all, and most attachments either have no usages or have only
	 * owning usages (nothing to drop).
	 *
	 * @return void
	 */
	private function reconcile_incidental_usages(): void {
		$attached_rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', self::META_KEY )
			->get();

		if ( empty( $attached_rows ) ) {
			return;
		}

		$incidental_types = [ 'permalink', 'rendered' ];
		$owning_types     = array_flip( self::OWNING_USAGE_TYPES );
		$sitewide_types   = array_flip( self::SITEWIDE_USAGE_TYPES );

		foreach ( $attached_rows as $row ) {
			$attachment_id = absint( $row['post_id'] ?? 0 );
			$usages        = maybe_unserialize( $row['meta_value'] ?? '' );

			if ( ! $attachment_id || ! is_array( $usages ) || empty( $usages ) ) {
				continue;
			}

			// First pass: collect post_ids that have an owning usage on this
			// attachment, and detect any sitewide ownership (logo/widget/etc.).
			$owning_post_ids = [];
			$has_incidental  = false;
			$has_sitewide    = false;

			foreach ( $usages as $usage ) {
				$type = (string) ( $usage['usage_type'] ?? '' );
				$pid  = (int) ( $usage['post_id'] ?? 0 );
				if ( isset( $sitewide_types[ $type ] ) ) {
					$has_sitewide = true;
					continue;
				}
				if ( ! $pid ) {
					continue;
				}
				if ( isset( $owning_types[ $type ] ) ) {
					$owning_post_ids[ $pid ] = true;
				} elseif ( in_array( $type, $incidental_types, true ) ) {
					$has_incidental = true;
				}
			}

			// Nothing to do if there are no incidental records to drop.
			if ( ! $has_incidental ) {
				continue;
			}

			// Nothing to do if there's no owning signal at all (no structural
			// per-post owner AND no sitewide owner) — we trust the permalink
			// hits as the only evidence we have.
			if ( empty( $owning_post_ids ) && ! $has_sitewide ) {
				continue;
			}

			// Second pass: keep every owning / sitewide usage; for incidental
			// usages, the rule is:
			//   - if the image is owned sitewide (logo / widget / etc.), drop
			//     every permalink / rendered record — they're chrome
			//     repeating on every page, not real per-post usage.
			//   - otherwise keep an incidental record only when it's on a post
			//     that already owns the image (then it's confirmation).
			$cleaned = [];
			foreach ( $usages as $usage ) {
				$type = (string) ( $usage['usage_type'] ?? '' );
				$pid  = (int) ( $usage['post_id'] ?? 0 );

				if ( in_array( $type, $incidental_types, true ) ) {
					if ( $has_sitewide ) {
						continue; // drop all incidental — image is chrome
					}
					if ( ! isset( $owning_post_ids[ $pid ] ) ) {
						continue; // drop incidental on non-owning post
					}
				}
				$cleaned[] = $usage;
			}

			if ( count( $cleaned ) === count( $usages ) ) {
				continue; // nothing changed
			}

			if ( empty( $cleaned ) ) {
				delete_post_meta( $attachment_id, self::META_KEY );
			} else {
				update_post_meta( $attachment_id, self::META_KEY, array_values( $cleaned ) );
			}
		}
	}

	private function clear_all_usage_meta(): void {
		// 1. Find which attachment IDs have our meta key.
		$affected_rows = Fns::DB()->select( 'post_id' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', self::META_KEY )
			->get();

		// 2. Bulk-delete all meta rows for our key.
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', self::META_KEY )
			->execute();

		if ( empty( $affected_rows ) ) {
			return;
		}

		// 3. Collect affected attachment IDs and bulk-reset post_parent.
		$affected_ids = array_unique(
			array_map( fn( $r ) => absint( $r['post_id'] ), $affected_rows )
		);

		Fns::DB()->update( 'posts', [ 'post_parent' => 0 ] )
			->whereIn( 'ID', ...$affected_ids )
			->execute();

		// Also reset the URL lookup map to avoid stale data.
		$this->url_lookup_map = null;
		$this->known_attachment_ids = null;
		$this->upload_base_url = null;
		$this->fallback_attachment_ids = null;
		$this->featured_image_owners = null;
		$this->sitewide_attachment_ids = null;
	}

	/**
	 * Record frontend image usage (passive tracking).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param int    $post_id Post ID.
	 * @param string $usage_type Usage type.
	 *
	 * @return void
	 */
	public function record_frontend_usage( int $attachment_id, int $post_id, string $usage_type ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$existing = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		// If the image is already attributed as site-wide (site_settings, term_meta,
		// widget, logo, favicon, header, background, option, etc. — all stored with
		// post_id=0), it renders on many pages by design. Do not record a per-post
		// entry for every visitor page-hit — that would falsely list every post as
		// a user of the image.
		foreach ( $existing as $item ) {
			if ( 0 === (int) ( $item['post_id'] ?? 0 ) ) {
				return;
			}
		}

		// Check for duplicate.
		$key = $post_id . ':' . $usage_type;
		foreach ( $existing as $item ) {
			if ( ( $item['post_id'] . ':' . $item['usage_type'] ) === $key ) {
				return;
			}
		}

		$existing[] = [
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'usage_type' => $usage_type,
		];

		update_post_meta( $attachment_id, self::META_KEY, $existing );

		// Set post_parent if not set.
		$current_parent = (int) get_post_field( 'post_parent', $attachment_id );
		if ( ! $current_parent ) {
			wp_update_post( [
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			] );
		}
	}

	/**
	 * Detect site-wide image usage (favicon, site logo).
	 *
	 * Checks WordPress site settings:
	 * - site_icon: site favicon
	 * - site_logo: block theme logo
	 * - custom_logo: classic theme logo
	 *
	 * Skips duplicates (e.g., if custom_logo and site_logo point to same ID).
	 *
	 * @return void
	 */
	private function detect_sitewide_usage(): void {
		$known_ids = $this->get_known_attachment_ids();

		// 1. Site icon (favicon).
		$site_icon_id = absint( get_option( 'site_icon', 0 ) );
		if ( $site_icon_id && isset( $known_ids[ $site_icon_id ] ) ) {
			$this->record_sitewide_usage( $site_icon_id, 'site_icon' );
		}

		// 2. Site logo (block theme).
		$site_logo_id = absint( get_option( 'site_logo', 0 ) );
		if ( $site_logo_id && isset( $known_ids[ $site_logo_id ] ) ) {
			$this->record_sitewide_usage( $site_logo_id, 'site_logo' );
		}

		// 3. Custom logo (classic theme).
		$custom_logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
		if ( $custom_logo_id && $custom_logo_id !== $site_logo_id && isset( $known_ids[ $custom_logo_id ] ) ) {
			$this->record_sitewide_usage( $custom_logo_id, 'site_logo' );
		}

		// 4. Header image (customizer).
		$header_image_data = get_custom_header();
		if ( ! empty( $header_image_data->attachment_id ) ) {
			$header_id = absint( $header_image_data->attachment_id );
			if ( $header_id && isset( $known_ids[ $header_id ] ) ) {
				$this->record_sitewide_usage( $header_id, 'header_image' );
			}
		}

		// 5. Background image (customizer).
		$bg_image_id = absint( get_theme_mod( 'background_image_thumb_id', 0 ) );
		if ( ! $bg_image_id ) {
			// Try to resolve from URL.
			$bg_url = get_theme_mod( 'background_image', '' );
			if ( $bg_url ) {
				$bg_image_id = $this->get_attachment_id_by_url( $bg_url, false );
			}
		}
		if ( $bg_image_id && isset( $known_ids[ $bg_image_id ] ) ) {
			$this->record_sitewide_usage( $bg_image_id, 'background_image' );
		}

		// 6. Navigation menu images (Menu Image plugin, etc.).
		$this->detect_nav_menu_images( $known_ids );

		// 7. Widget images (scan active widget options for upload URLs/IDs).
		$this->detect_widget_images( $known_ids );

		// 8. Taxonomy term meta images (category thumbnails, featured images, SEO images).
		$this->detect_term_meta_images( $known_ids );
	}

	/**
	 * Detect images used in navigation menus.
	 *
	 * @param array $known_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function detect_nav_menu_images( array $known_ids ): void {
		$nav_menus = wp_get_nav_menus();
		if ( empty( $nav_menus ) ) {
			return;
		}

		foreach ( $nav_menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			if ( empty( $menu_items ) ) {
				continue;
			}
			foreach ( $menu_items as $item ) {
				// Menu Image plugin stores thumbnail ID in _menu_item_image_id or _thumbnail_id.
				$img_id = absint( get_post_meta( $item->ID, '_menu_item_image_id', true ) );
				if ( ! $img_id ) {
					$img_id = absint( get_post_meta( $item->ID, '_thumbnail_id', true ) );
				}
				if ( $img_id && isset( $known_ids[ $img_id ] ) ) {
					$this->record_sitewide_usage( $img_id, 'nav_menu' );
				}
			}
		}
	}

	/**
	 * Detect images used in active widgets.
	 *
	 * Scans widget option values for upload URLs.
	 *
	 * @param array $known_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function detect_widget_images( array $known_ids ): void {
		$sidebars = get_option( 'sidebars_widgets', [] );
		if ( empty( $sidebars ) || ! is_array( $sidebars ) ) {
			return;
		}

		// Collect all active widget IDs.
		$active_widgets = [];
		foreach ( $sidebars as $sidebar_id => $widgets ) {
			if ( 'wp_inactive_widgets' === $sidebar_id || ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget_id ) {
				// Extract widget type: e.g. "media_image-2" → "media_image".
				$type = preg_replace( '/-\d+$/', '', $widget_id );
				$active_widgets[ $type ][] = $widget_id;
			}
		}

		// Check widget options for known image-related widgets.
		foreach ( $active_widgets as $type => $widget_ids ) {
			$option = get_option( 'widget_' . $type, [] );
			if ( empty( $option ) || ! is_array( $option ) ) {
				continue;
			}

			foreach ( $option as $instance ) {
				if ( ! is_array( $instance ) ) {
					continue;
				}
				// Check attachment_id field (Media Image, Media Gallery widgets).
				if ( ! empty( $instance['attachment_id'] ) ) {
					$id = absint( $instance['attachment_id'] );
					if ( $id && isset( $known_ids[ $id ] ) ) {
						$this->record_sitewide_usage( $id, 'widget' );
					}
				}
				// Check ids field (Gallery widget).
				if ( ! empty( $instance['ids'] ) && is_string( $instance['ids'] ) ) {
					$ids = explode( ',', $instance['ids'] );
					foreach ( $ids as $id ) {
						$id = absint( trim( $id ) );
						if ( $id && isset( $known_ids[ $id ] ) ) {
							$this->record_sitewide_usage( $id, 'widget' );
						}
					}
				}
				// Check for upload URLs in text/HTML content fields. Widget
				// option strings store full URLs verbatim, so disable the
				// basename fallback to keep generic filenames from collapsing
				// onto unrelated attachments.
				foreach ( [ 'text', 'content', 'url' ] as $field ) {
					if ( ! empty( $instance[ $field ] ) && is_string( $instance[ $field ] ) && strpos( $instance[ $field ], '/wp-content/uploads/' ) !== false ) {
						$att_id = $this->get_attachment_id_by_url( $instance[ $field ], false );
						if ( $att_id ) {
							$this->record_sitewide_usage( $att_id, 'widget' );
						}
					}
				}
			}
		}
	}

	/**
	 * Detect images stored as taxonomy term meta.
	 *
	 * Covers category/tag/custom taxonomy images set by plugins like:
	 * - WooCommerce (`thumbnail_id` term meta for product categories)
	 * - Categories Images / Category Image plugins (`category_image_id`, `term_image_id`)
	 * - ACF term fields (numeric attachment IDs in term meta)
	 * - Yoast SEO (`wpseo_taxonomy_meta` option with `wpseo_opengraph-image-id`)
	 * - Rank Math (`rank_math_facebook_image_id`, `rank_math_twitter_image_id`)
	 *
	 * Also scans all term meta values for upload URLs to catch URL-based
	 * category image implementations.
	 *
	 * @param array<int, true> $known_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function detect_term_meta_images( array $known_ids ): void {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
		if ( empty( $taxonomies ) ) {
			return;
		}

		// Well-known term meta keys that store attachment IDs.
		$id_meta_keys = [
			'thumbnail_id',                   // WooCommerce product categories.
			'category_image_id',              // Categories Images plugin.
			'term_image_id',                  // Category Image plugin variants.
			'brand_thumbnail_id',             // WooCommerce Brands.
			'rank_math_facebook_image_id',    // Rank Math SEO.
			'rank_math_twitter_image_id',     // Rank Math SEO.
			'_flavor_term_image',             // flavor theme.
		];

		/**
		 * Filter the list of term meta keys that are known to store attachment IDs.
		 *
		 * Plugins that store category/term images can add their meta key here so
		 * the used-where scanner picks them up.
		 *
		 * @param string[] $id_meta_keys Meta key names.
		 */
		$id_meta_keys = apply_filters( 'tsmlt_used_where_term_image_meta_keys', $id_meta_keys );

		// Well-known term meta keys that store image URLs.
		$url_meta_keys = [
			'category_image',     // Categories Images plugin (URL variant).
			'term_image',         // Generic category image plugins.
		];

		/**
		 * Filter the list of term meta keys that are known to store image URLs.
		 *
		 * @param string[] $url_meta_keys Meta key names.
		 */
		$url_meta_keys = apply_filters( 'tsmlt_used_where_term_image_url_meta_keys', $url_meta_keys );

		$terms = get_terms(
			[
				'taxonomy'   => array_values( $taxonomies ),
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		foreach ( $terms as $term_id ) {
			$term_id = absint( $term_id );

			// Check ID-based meta keys.
			foreach ( $id_meta_keys as $meta_key ) {
				$img_id = absint( get_term_meta( $term_id, $meta_key, true ) );
				if ( $img_id && isset( $known_ids[ $img_id ] ) ) {
					$this->record_sitewide_usage( $img_id, 'term_meta' );
				}
			}

			// Check URL-based meta keys. Term meta stores full URLs verbatim
			// — disable basename fallback to avoid pinning unrelated entries
			// onto attachments with common filenames.
			foreach ( $url_meta_keys as $meta_key ) {
				$url = get_term_meta( $term_id, $meta_key, true );
				if ( ! empty( $url ) && is_string( $url ) && strpos( $url, '/wp-content/uploads/' ) !== false ) {
					$att_id = $this->get_attachment_id_by_url( $url, false );
					if ( $att_id && isset( $known_ids[ $att_id ] ) ) {
						$this->record_sitewide_usage( $att_id, 'term_meta' );
					}
				}
			}
		}

		// Yoast SEO stores taxonomy OG/Twitter images in a single option blob.
		$yoast_tax_meta = get_option( 'wpseo_taxonomy_meta', [] );
		if ( is_array( $yoast_tax_meta ) ) {
			foreach ( $yoast_tax_meta as $taxonomy => $terms_data ) {
				if ( ! is_array( $terms_data ) ) {
					continue;
				}
				foreach ( $terms_data as $tid => $meta ) {
					if ( ! is_array( $meta ) ) {
						continue;
					}
					foreach ( [ 'wpseo_opengraph-image-id', 'wpseo_twitter-image-id' ] as $yoast_key ) {
						if ( ! empty( $meta[ $yoast_key ] ) ) {
							$img_id = absint( $meta[ $yoast_key ] );
							if ( $img_id && isset( $known_ids[ $img_id ] ) ) {
								$this->record_sitewide_usage( $img_id, 'term_meta' );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Scan the fully rendered HTML output of a page to detect all image usages.
	 *
	 * Captures every image URL from the entire <html>...</html> output, including
	 * header, footer, sidebars, widgets, hardcoded images, and inline CSS backgrounds.
	 *
	 * @param string $html Full rendered HTML of the page.
	 * @param int    $post_id The current post ID.
	 *
	 * @return void
	 */
	public function scan_rendered_html( string $html, int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return;
		}

		$this->build_url_lookup_map();
		$this->usages_buffer = [];

		// 1. Extract all image URLs from <img> src and srcset attributes.
		if ( preg_match_all( '/<img\s[^>]*>/is', $html, $img_matches ) ) {
			foreach ( $img_matches[0] as $img_tag ) {
				// src attribute.
				if ( preg_match( '/\bsrc=["\']([^"\']+)/i', $img_tag, $src_match ) ) {
					$this->match_url_to_attachment( $src_match[1], $post, 'rendered' );
				}
				// srcset attribute (multiple URLs).
				if ( preg_match( '/\bsrcset=["\']([^"\']+)/i', $img_tag, $srcset_match ) ) {
					$srcset_parts = explode( ',', $srcset_match[1] );
					foreach ( $srcset_parts as $part ) {
						$url = trim( explode( ' ', trim( $part ) )[0] );
						if ( $url ) {
							$this->match_url_to_attachment( $url, $post, 'rendered' );
						}
					}
				}
			}
		}

		// 2. Extract image URLs from CSS background-image: url(...).
		if ( preg_match_all( '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $html, $bg_matches ) ) {
			foreach ( $bg_matches[1] as $bg_url ) {
				$this->match_url_to_attachment( $bg_url, $post, 'rendered' );
			}
		}

		// 3. Extract image URLs from <source> tags (picture element, video poster).
		if ( preg_match_all( '/<source\s[^>]*srcset=["\']([^"\']+)/i', $html, $source_matches ) ) {
			foreach ( $source_matches[1] as $srcset_val ) {
				$srcset_parts = explode( ',', $srcset_val );
				foreach ( $srcset_parts as $part ) {
					$url = trim( explode( ' ', trim( $part ) )[0] );
					if ( $url ) {
						$this->match_url_to_attachment( $url, $post, 'rendered' );
					}
				}
			}
		}

		// 4-5. Catch-all uploads-URL extraction (covers <a> hrefs, JSON blobs,
		//      and entity-encoded attribute payloads like data-image="&lt;img...&gt;").
		//      Shared helper handles dedup, conditional entity decode, and base URL caching.
		$this->extract_image_urls_from_html( $html, $post, 'rendered' );

		$this->flush_usages_buffer();
		$this->url_lookup_map = null;
		$this->known_attachment_ids = null;
		$this->upload_base_url = null;
		$this->fallback_attachment_ids = null;
		$this->featured_image_owners = null;
		$this->sitewide_attachment_ids = null;
	}

	/**
	 * Try to match a URL to an attachment and record usage.
	 *
	 * @param string   $url URL to match.
	 * @param \WP_Post $post Post object.
	 * @param string   $usage_type Usage type.
	 *
	 * @return void
	 */
	private function match_url_to_attachment( string $url, \WP_Post $post, string $usage_type ): void {
		if ( strpos( $url, '/wp-content/uploads/' ) === false ) {
			return;
		}

		$attachment_id = $this->get_attachment_id_by_url( $url );
		if ( ! $attachment_id ) {
			return;
		}

		// Same rule as record_usages_from_urls(): on HTML-scan paths, drop
		// thumbnails of *other* posts being shown by related-posts widgets,
		// schema markup, etc. The image is owned by the featured-image post,
		// not by whatever post happens to render the widget. Also drop
		// site-wide images (logo/header/option/term_meta) that render on
		// every page.
		if ( 'permalink' === $usage_type || 'rendered' === $usage_type ) {
			$this->build_featured_image_owners();
			$this->build_sitewide_attachment_ids();
			if ( isset( $this->sitewide_attachment_ids[ $attachment_id ] ) ) {
				return;
			}
			$owner = $this->featured_image_owners[ $attachment_id ] ?? 0;
			if ( $owner && $owner !== $post->ID ) {
				return;
			}
		}

		$this->record_usage( $attachment_id, $post, $usage_type );
	}

	/**
	 * Record a site-wide usage entry (favicon, logo, etc.).
	 *
	 * Similar to record_usage() but for non-post contexts. Uses post_id=0
	 * since there is no WP_Post associated with site settings.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $usage_type Type of site-wide usage.
	 *
	 * @return void
	 */
	private function record_sitewide_usage( int $attachment_id, string $usage_type ): void {
		$key = $attachment_id . ':0:' . $usage_type;

		if ( ! isset( $this->usages_buffer[ $attachment_id ] ) ) {
			$this->usages_buffer[ $attachment_id ] = [];
		}

		if ( isset( $this->usages_buffer[ $attachment_id ][ $key ] ) ) {
			return;
		}

		$this->usages_buffer[ $attachment_id ][ $key ] = [
			'post_id'    => 0,
			'post_title' => esc_html__( 'Site Settings', 'media-library-tools' ),
			'post_type'  => 'site_settings',
			'usage_type' => $usage_type,
		];
	}
}
