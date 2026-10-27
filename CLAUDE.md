# Media Library Tools (Free)

## Overview
WordPress plugin for media library management: rename files, bulk edit metadata, AI content generation, SVG support, rubbish file cleanup, CSV export/import, and more.

- **Namespace:** `TinySolutions\mlt`
- **Version:** 2.5.0
- **PHP:** 7.4+
- **WP:** 6.5+ (MCP / Abilities API features: 6.9+)
- **License:** GPLv3
- **Text Domain:** `media-library-tools`

## Tech Stack
- **Frontend:** React 19 + Vite + Tailwind CSS v4 + React Router (hash) + Zustand (state) + react-hot-toast
- **Backend:** PHP 7.4+, WordPress AJAX (`admin-ajax.php`) for the admin UI; WordPress Abilities API + MCP Adapter (REST) for AI assistants
- **Vendor Prefixing:** PHP-Scoper (prefix: `TinySolutions\mlt\Vendor`)
- **Dependencies:** `enshrined/svg-sanitize`, `codesvault/howdy-qb` (query builder)

## Project Structure
```
media-library-tools.php        # Plugin entry point, constants, autoloader
autoload.php                   # Auto-generated PSR-4 autoloader (no vendor/ needed in production)
generate-autoload.php          # Script to regenerate autoload.php
scoper.inc.php                 # PHP-Scoper config
app/                           # PHP source (PSR-4: TinySolutions\mlt\)
  Controllers/
    Admin/Api.php              # Core media API (get_media, bulk actions, image sizes, plugin list)
    Admin/SubMenu.php          # Admin menu registration
    Hooks/Ajax.php             # AJAX action registrations + security, delegates to Api/Modules
    Hooks/ActionHooks.php      # WordPress action hooks
    Hooks/FilterHooks.php      # WordPress filter hooks
    Hooks/CronJobHooks.php     # Cron scheduling (dir scan, rubbish scan, thumbnail)
    AI/AiApi.php               # AI provider integration (ChatGPT, Gemini, Claude)
    Installation.php           # Activation/deactivation, DB table creation
  Helpers/Fns.php              # Static helpers, DB shortcuts, rename logic, filesystem
  Modules/
    ModuleInit.php             # Registers all modules (DownloadMedia, RubbishScanner)
    DownloadMedia.php          # Download media feature
    Rubbish/RubbishScanner.php # Rubbish (unlisted) file finder: dir scanning, file queries, scheduling
    Duplicate/DuplicateScanner.php # Duplicate file detection via MD5 hashing
  Traits/SingletonTrait.php    # Singleton pattern used by all classes
  Abs/Ability.php              # Base class for Abilities API abilities (shared registration + MCP meta)
  Abilities/
    AbilitiesInit.php          # Registers the ability category + abilities (WP 6.9+ only)
    AbilityGuard.php           # manage_options check, attachment validation, stable WP_Error codes
    MediaPresenter.php         # Allowlisted attachment output (no paths, raw metadata, EXIF or custom meta)
    UsageScanStatus.php        # Side-effect-free read of the Used Where scan status (never get_scan_status(), which heals stalled scans)
    TrashEligibility.php       # Server-side checks for moving confirmed-unused media to the trash
    McpServerRegistration.php  # Dedicated MCP server at /wp-json/media-library-tools/mcp
    Media/SearchMedia.php, GetMediaDetails.php, UpdateMediaMetadata.php, GetExifFields.php, ListMediaGroups.php, SetMediaGroups.php, CreateMediaGroup.php, UpdateMediaGroup.php, GetMediaUsage.php, StartUsageScan.php, GetUsageScanStatus.php, PreviewTrashUnusedMedia.php, TrashUnusedMedia.php
src/js/                        # React frontend source
  Component/
    DataTable/                 # Main media table (list, edit, bulk actions)
    Renamer/                   # File rename table + bulk rename modal + header
    ExportImport/              # CSV export/import UI
    Rubbish/                   # Rubbish file scanner/cleaner
    Settings/                  # Settings page (alt, caption, desc, AI, rename)
    ImageSize/                 # Disable/register image sizes
    MediaDownload/             # Download shortcode info page
  Utils/
    store.ts                   # Zustand store (global state)
    Data.ts                    # ajaxPost() helper, all API call functions
vendor_prefixed/               # PHP-Scoper output (shipped in production)
assets/                        # Built frontend assets (Vite output)
```

## Coding Standards
- Always follow **PHPCS** (PHP CodeSniffer) and **WPCS** (WordPress Coding Standards) when writing or modifying PHP code.
- Use tabs for indentation, Yoda conditions, proper escaping (`esc_html__`, `esc_attr`, `wp_kses`), and sanitization (`sanitize_text_field`, `absint`, etc.).
- Follow WordPress naming conventions: `snake_case` for functions/variables, `PascalCase` for classes.
- **Never use raw SQL queries** (`$wpdb->query()`, `$wpdb->get_var()`, `$wpdb->get_results()`). Always use the query builder via `Fns::DB()`:
  - `Fns::DB()->select(...)->from(...)->where(...)->get()` — fetch rows
  - `Fns::DB()->insert(...)->execute()` — insert rows
  - `Fns::DB()->delete(...)->execute()` — delete rows
  - `Fns::DB()->update(...)->where(...)->execute()` — update rows
  - `Fns::DB()->select()->count(...)->from(...)->get()` — count queries
  - Query builder is `codesvault/howdy-qb` (Howdy QB) with automatic escaping & parameter binding

## Key Patterns

### Singleton
All PHP classes use `SingletonTrait`. Access via `ClassName::instance()`.

### AJAX for the admin UI
The admin React UI talks to the server only through `admin-ajax.php`, with actions prefixed `tsmlt_*`. The plugin registers no REST routes of its own for the UI. The exception is the MCP integration below, which is served over REST by the official MCP Adapter.

**Security on every AJAX handler:**
1. `wp_doing_ajax()` check
2. POST-only
3. `check_ajax_referer(Fns::NONCE_ID, 'nonce')`
4. `current_user_can('manage_options')` or `upload_files`
5. JSON params decoded from `$_POST['params']`

**Frontend AJAX pattern:**
```ts
// src/js/Utils/Data.ts
ajaxPost('tsmlt_action_name', { key: value })
// Sends: action, nonce (tsmltParams.tsmlt_wpnonce), params (JSON string)
```

### MCP / Abilities API
AI assistants reach thirteen abilities — `tsmlt/search-media` (optional `filename`, `group_id` and `attached` filters; `filename` matches the attached file's basename via a `_wp_attached_file` meta LIKE plus a basename check; `attached` is the `post_parent` relationship, not usage), `tsmlt/get-media-details`, `tsmlt/update-media-metadata`, `tsmlt/get-exif-fields`, `tsmlt/list-media-groups`, `tsmlt/set-media-groups`, `tsmlt/create-media-group`, `tsmlt/update-media-group`, `tsmlt/get-media-usage` (read-only; up to 50 IDs; reads `UsedWhereScanner::get_usage_stats()` and the `tsmlt_used_where_scan_status` option directly — never `get_scan_status()`, which can re-schedule a stalled scan; `used` is null until a scan completes), `tsmlt/start-usage-scan` (queues `UsedWhereScanner::start_scheduled_scan()` + `spawn_cron()` like the admin button, but never restarts a queued/running scan; resumes a stalled one; an `add_option()` start lock stops concurrent starts) and `tsmlt/get-usage-scan-status` (read-only; `scan_id` changes per run, `percent` ≤ 99 until complete). Clients build live/incremental used-unused-unknown results by polling these (workflow in `docs/12-mcp-integration.md`, "Live progress"): recorded usage is provisional while running, missing usage is unknown until complete. `get-media-usage` reads the status before and after the records and reports `used: false` / `scan.complete: true` only for one verified complete scan. `start_scheduled_scan()` writes the queued status (with `scan_id`) before wiping records, and `clear_all_usage_meta()` clears the object cache for rows it deletes with direct queries. The scanner always scans `product` / `product_variation` rows (`WOOCOMMERCE_POST_TYPES`, counted in the table because `wp_count_posts()` ignores unregistered types) and records per-run coverage in the status option (`coverage_version`, `coverage_gaps` — `woocommerce_inactive`, `woocommerce_types_not_scanned` — merged by each batch, reset on each start). `UsageScanStatus` exposes `coverage {verified, gaps}` (`not_recorded` without a record); `get-media-usage` reports unknown and the trash tools refuse (`tsmlt_scan_coverage_incomplete`) unless verified; `get_scan_status()` adds `coverage_gaps`/`coverage_notice` (last scan + live check) for the Used Where page warning. `tsmlt/preview-trash-unused-media` (read-only) and `tsmlt/trash-unused-media` (destructive; exact `ids`, max 50) share `Abilities\TrashEligibility`: refuse unless the scan is complete with a `scan_id`, not stalled, its coverage verified, no `tsmlt_scan_post_usage` re-check pending and `EMPTY_TRASH_DAYS` is on (otherwise `wp_trash_post()` deletes); per ID: readable attachment, not trashed, `inherit`, `delete_post`, uploaded before the scan started, no usage record at all. The trash tool re-reads the status after validating and before each move, re-checks each ID, uses only `wp_trash_post()`, and reads each result back. No server-enforced human confirmation exists (adapter elicitation is not available to abilities) — through the WordPress Abilities API (core, WP 6.9+) and the official MCP Adapter plugin. Everything is skipped when either is missing.
- **Endpoints:** `McpServerRegistration` creates a dedicated server at `/wp-json/media-library-tools/mcp` that exposes only these tools and requires `manage_options` at the transport. The adapter's default server (`/wp-json/mcp/mcp-adapter-default-server`) also lists the three MCP-public ones (`get-exif-fields`, `list-media-groups`, `set-media-groups`, `create-media-group`, `update-media-group`, `get-media-usage`, `start-usage-scan`, `get-usage-scan-status`, `preview-trash-unused-media` and `trash-unused-media` set `meta.mcp.public` false via `Ability::is_mcp_public()`), alongside every other plugin's public abilities — recommend the dedicated endpoint (see `docs/12-mcp-integration.md`).
- **Permissions:** every ability requires `manage_options`, plus `read_post` (and `edit_post` for updates) on the specific attachment. Inaccessible attachments are reported as not found. Permission callbacks return `bool`; core turns a `WP_Error` there into `_doing_it_wrong`.
- **Input/output:** schemas set `additionalProperties: false`; execution code still sanitizes. Output goes only through `MediaPresenter`, except `GetMediaUsage` (live title, status and type of readable referring posts only; others counted as `hidden`) and `GetExifFields`, which builds its own allowlist of the editor's non-private fields (make, model, lens make/model, software, dates taken/digitized, ISO, aperture, shutter speed, focal length, exposure program/mode, metering mode, white balance, flash, description — never GPS/altitude, time zone offsets, copyright, artist or raw EXIF).
- **Writes:** `UpdateMediaMetadata` uses `wp_update_post()` / `update_post_meta()` with `wp_slash()`. Never route ability writes through `RenameModule::update_single_media()` — it dispatches to rename or bulk-edit depending on the keys present.
- **Groups:** `tsmlt_category` terms. `SetMediaGroups` validates IDs with `AbilityGuard::resolve_groups()`, also requires the taxonomy's `assign_terms` capability, writes through `Api::assign_groups()` (shared with the media table's bulk edit), and reads the result back before reporting success. `CreateMediaGroup` uses `wp_insert_term()` (WordPress refuses a duplicate name under the same parent, mapped to `tsmlt_group_exists`), validates the parent with `resolve_groups()` because `wp_insert_term()` accepts any taxonomy's term as a parent, requires the taxonomy's `edit_terms`, and reads the new term back (deleting it on mismatch). `UpdateMediaGroup` renames only (ID, slug, parent and media unchanged): it checks siblings itself because `wp_update_term()` refuses only duplicate slugs, requires `edit_terms` and `edit_term` on the group, passes the name slashed, and restores the previous name if the read-back does not match.
- **Adding an ability:** extend `Abs\Ability`, use `SingletonTrait`, add it to `AbilitiesInit::get_abilities()` (the dedicated server picks it up automatically), then run `php generate-autoload.php`.

### Free-Pro Communication
Pro features are gated via:
- PHP: `tsmlt()->has_pro()`
- JS: `tsmltParams.hasExtended`

Pro plugin hooks into free via WordPress hooks/filters (no direct coupling):
- `tsmlt/settings/before/save` — Pro extends settings save
- `tsmlt_ai_*` filters — Pro adds AI image support
- `tsmlt_attachment_rename_to` — Pro adds rename strategies (by post title, SKU, alt text)
- `tsmlt/add/more/submenu` — Pro adds menu items

### Settings
All settings stored in `tsmlt_settings` WordPress option. Extended by pro via filter.

## Tests
PHPUnit integration tests run inside the WordPress test suite; setup and commands are in `tests/README.md`. Install dev dependencies with `composer install --no-scripts` so the PHP-Scoper step does not run.

Unit tests for the AI providers, encrypted credentials and the model catalogue live in `tests/Unit/`. They stub WordPress and need no database: `vendor/bin/phpunit -c phpunit-unit.xml.dist`. The two suites cannot run in one process (the unit bootstrap defines WordPress function stand-ins).

## Build Commands
```bash
# Development
npm run dev          # Vite dev server
npm run build        # Production build

# Vendor prefixing (after composer install)
composer prefix-vendor   # Runs PHP-Scoper + generates autoload.php

# Release
npm run zip          # Build + make-pot + create versioned zip in dist/
```

## Important Notes
- `vendor/` is dev-only; production uses `vendor_prefixed/` + `autoload.php`
- `autoload.php` is auto-generated by `generate-autoload.php` — do not edit manually
- The `--force` flag in `composer prefix-vendor` wipes `vendor_prefixed/` each time
- WordPress global classes (`wpdb`, `WP_Error`, etc.) are excluded from scoping in `scoper.inc.php`
- `build.js` handles packaging; includes list: `app/`, `assets/`, `languages/`, `vendor_prefixed/`, `autoload.php`, `index.php`, `README.txt`, main plugin file
