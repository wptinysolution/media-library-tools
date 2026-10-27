# MCP Plan — Media Library Tools

Working plan for the MCP / Abilities API integration (Free and Pro, `MCP` branches). Not shipped in release zips (`build.js` packages an explicit include list).

Last updated: 2026-10-28.

## Current tools

Dedicated server: `/wp-json/media-library-tools/mcp` (requires `manage_options`). "Gateway" means the MCP Adapter's default server also offers the tool.

| Tool | Repo | Type | Gateway |
|---|---|---|---|
| `tsmlt-search-media` | Free | Read | Yes |
| `tsmlt-get-media-details` | Free | Read | Yes |
| `tsmlt-update-media-metadata` | Free | Write | Yes |
| `tsmlt-get-exif-fields` | Free | Read | No |
| `tsmlt-list-media-groups` | Free | Read | No |
| `tsmlt-set-media-groups` | Free | Write | No |
| `tsmlt-create-media-group` | Free | Write | No |
| `tsmlt-update-media-group` | Free | Write (rename) | No |
| `tsmlt-get-media-usage` | Free | Read | No |
| `tsmlt-start-usage-scan` | Free | Write (replaces scan results) | No |
| `tsmlt-get-usage-scan-status` | Free | Read | No |
| `tsmlt-get-exif-caption` | Pro (licensed) | Read | No |
| `tsmlt-update-exif-fields` | Pro (licensed) | Write | No |

## Decisions

- **No MCP tool moves media to the trash or deletes it.** `tsmlt-preview-trash-unused-media` and `tsmlt-trash-unused-media` were removed (2026-10-28) as too risky. Do not add trash, delete or bulk-cleanup tools without an explicit decision. The admin Used Where page keeps its own trash and restore.
- **"Unused" is never assumed.** `tsmlt-get-media-usage` reports `used: false` only for a completed scan with verified coverage (`coverage.verified`); otherwise media without a recorded usage is unknown (`null`).
- **WooCommerce rows are always scanned** (`product`, `product_variation`), even when WooCommerce is inactive. Gaps that remain are recorded as `coverage.gaps` (`woocommerce_inactive`, `woocommerce_types_not_scanned`, or `not_recorded` for scans without a coverage record).
- **Pro code stays in Pro.** Free provides hooks only (`tsmlt_mcp_server_abilities`).

## Open items

1. **Pro test tool list.** `GetExifCaptionMcpExposureTest::test_listed_and_callable_on_dedicated_server` (Pro) hard-codes the dedicated server's tools and fails against current Free. Needs a test-only Pro update listing `get-media-usage`, `start-usage-scan` and `get-usage-scan-status` (authorization required to touch Pro).
2. **Admin notice for unverified scans.** The Used Where page warns only about WooCommerce gaps. After a scan without a coverage record (`not_recorded`), its Unused tab still says "No uses found" while MCP reports unknown. Consider a "run a new scan to verify" notice.
3. **Rebuild admin JS** (`npm run build`) wherever the Used Where warning must appear.
4. **plugindev verification.** The last scan there (2026-10-27) predates coverage recording, so coverage is `not_recorded`. A new scan there (only with permission) should show `coverage.verified: true`. Product, gallery, variation and category images 10631–10652 and 10266–10285 were confirmed Used (read-only check, 2026-10-28); 10280 and 10282–10284 are not attachments.
5. **MCP client schemas.** The `mlt-ip` client still has the old tool list; it must reconnect to see the usage and scan tools (only with permission).
6. **Existing admin risk (not MCP).** The admin Used Where trash action calls `wp_trash_post()` without checking `EMPTY_TRASH_DAYS`; with it at 0, WordPress deletes permanently. Fix separately if wanted.
7. **Admin Unused tab and WooCommerce.** The tab lists media without usage records and does not mark them unknown when coverage has gaps (page-level warning only).

## Candidate next features (read-only first)

Ranked by value and risk; none started.

1. **`tsmlt-search-media` filters:** `used` (from usage records, only after a verified scan), upload date range, camera, has-GPS (presence only). Free.
2. **`tsmlt-list-duplicate-media`:** read-only duplicate groups from the duplicate scanner, with a read path that does not delete stale rows. Free.
3. **Duplicate merge** (Pro) and **file rename**: high value but write operations with URL-reference risk; need a separate safety design and an explicit go-ahead.

## Verification checklist for any new tool

- Extend `Abs\Ability`, register in `AbilitiesInit::get_abilities()`, run `php generate-autoload.php`.
- Closed input schema, `manage_options` re-checked in `execute()`, attachment-level capability checks, stable `AbilityGuard` error codes.
- Dedicated server only unless there is a reason for the gateway (`is_mcp_public()`).
- Update the ability-name lists in tests, `docs/12-mcp-integration.md`, the admin MCP docs page (`McpDocsPage.php`) and `CLAUDE.md`.
- Tests: input, permissions, no unintended writes, MCP Adapter tool list and output-schema validation, default gateway absence.
- Run the Free integration and unit suites, the Pro suite and PHPCS (PHP 8.3).
