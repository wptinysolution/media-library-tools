# Media Library Tools (Free)

## Overview
WordPress plugin for media library management: rename files, bulk edit metadata, AI content generation, SVG support, rubbish file cleanup, CSV export/import, and more.

- **Namespace:** `TinySolutions\mlt`
- **Version:** 2.5.0-rc-2
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
    McpServerRegistration.php  # Dedicated MCP server at /wp-json/media-library-tools/mcp
    Media/SearchMedia.php, GetMediaDetails.php, UpdateMediaMetadata.php
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
AI assistants reach three abilities — `tsmlt/search-media`, `tsmlt/get-media-details`, `tsmlt/update-media-metadata` — through the WordPress Abilities API (core, WP 6.9+) and the official MCP Adapter plugin. Everything is skipped when either is missing.
- **Endpoints:** `McpServerRegistration` creates a dedicated server at `/wp-json/media-library-tools/mcp` that exposes only these three tools and requires `manage_options` at the transport. The adapter's default server (`/wp-json/mcp/mcp-adapter-default-server`) also lists them, alongside every other plugin's public abilities — recommend the dedicated endpoint (see `docs/12-mcp-integration.md`).
- **Permissions:** every ability requires `manage_options`, plus `read_post` (and `edit_post` for updates) on the specific attachment. Inaccessible attachments are reported as not found. Permission callbacks return `bool`; core turns a `WP_Error` there into `_doing_it_wrong`.
- **Input/output:** schemas set `additionalProperties: false`; execution code still sanitizes. Output goes only through `MediaPresenter`.
- **Writes:** `UpdateMediaMetadata` uses `wp_update_post()` / `update_post_meta()` with `wp_slash()`. Never route ability writes through `RenameModule::update_single_media()` — it dispatches to rename or bulk-edit depending on the keys present.
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
