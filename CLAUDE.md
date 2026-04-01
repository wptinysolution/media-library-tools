# Media Library Tools (Free)

## Overview
WordPress plugin for media library management: rename files, bulk edit metadata, AI content generation, SVG support, rubbish file cleanup, CSV export/import, and more.

- **Namespace:** `TinySolutions\mlt`
- **Version:** 2.1.1
- **PHP:** 7.4+
- **WP:** 5.5+
- **License:** GPLv3
- **Text Domain:** `media-library-tools`

## Tech Stack
- **Frontend:** React 19 + Vite + Tailwind CSS v4 + React Router (hash) + Zustand (state) + react-hot-toast
- **Backend:** PHP 7.4+, WordPress AJAX (`admin-ajax.php`)
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
src/js/                        # React frontend source
  Component/
    DataTable/                 # Main media table (list, edit, bulk actions)
    Rename/                    # File rename table
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

### AJAX (not REST API)
All API calls go through `admin-ajax.php`. Actions prefixed `tsmlt_*`.

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

### Free-Pro Communication
Pro features are gated via:
- PHP: `tsmlt()->has_pro()`
- JS: `tsmltParams.hasExtended`

Pro plugin hooks into free via WordPress hooks/filters (no direct coupling):
- `tsmlt/settings/before/save` — Pro extends settings save
- `tsmlt_ai_*` filters — Pro adds AI image support
- `tsmlt_attachment_rename_to` — Pro adds rename strategies
- `tsmlt/add/more/submenu` — Pro adds menu items

### Settings
All settings stored in `tsmlt_settings` WordPress option. Extended by pro via filter.

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
