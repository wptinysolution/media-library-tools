# Tests

Integration tests run inside the official WordPress PHPUnit test suite
(`wp-phpunit/wp-phpunit`), with this plugin loaded as a must-use plugin.

Test dependencies are dev-only (`require-dev`). They are not scoped by
PHP-Scoper and are not included in the release zip (`build.js` ships an explicit
file list).

## One-time setup

1. **Install the dev dependencies.**

   Pass `--no-scripts` so Composer does not run the `post-autoload-dump`
   PHP-Scoper step, which wipes and regenerates `vendor_prefixed/`.

   ```bash
   composer install --no-scripts
   ```

2. **Download a WordPress core copy for the suite to load.**

   Use a separate directory. The suite installs into its own database, but
   `wp-content` resolves inside this directory.

   ```bash
   wp core download --path=/tmp/wordpress-tests --version=7.1.3 --skip-content
   ```

3. **Create an empty, dedicated test database.**

   > ⚠️ The suite **drops and recreates every table** in this database on every
   > run. Never point it at a site's own database.

   With a Local (localwp.com) site running, use its MySQL socket:

   ```bash
   SOCK="$HOME/Library/Application Support/Local/run/<site-id>/mysql/mysqld.sock"
   "$HOME/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql" \
     --socket="$SOCK" -uroot -proot -e "CREATE DATABASE IF NOT EXISTS mlt_wp_tests"
   ```

   Local's MySQL only accepts socket connections from `localhost`, not
   `127.0.0.1` over TCP.

## Running the tests

```bash
export WP_CORE_DIR=/tmp/wordpress-tests
export WP_TESTS_DB_NAME=mlt_wp_tests
export WP_TESTS_DB_USER=root
export WP_TESTS_DB_PASSWORD=root
export WP_TESTS_DB_HOST="localhost:$SOCK"   # or "127.0.0.1:3306" for a normal MySQL

vendor/bin/phpunit
```

Run one class or one test with `--filter`:

```bash
vendor/bin/phpunit --filter SearchMediaAbilityTest
vendor/bin/phpunit --filter test_missing_alt_filter
```

### End-to-end tests through the MCP Adapter

`tests/Integration/Mcp/McpAdapterTest.php` sends real MCP JSON-RPC messages to
the official MCP Adapter plugin's default server route. It is skipped unless
`MCP_ADAPTER_DIR` points to an unpacked copy of the plugin:

```bash
curl -sL -o /tmp/mcp-adapter.zip https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip
unzip -q -o /tmp/mcp-adapter.zip -d /tmp
MCP_ADAPTER_DIR=/tmp/mcp-adapter vendor/bin/phpunit
```

## Test suites

| File | Covers |
|---|---|
| `Integration/SmokeTest.php` | Plugin boots inside WordPress |
| `Integration/GetMediaAjaxTest.php` | Regression: `tsmlt_get_media` AJAX response format and query semantics |
| `Integration/BuildMediaQueryArgsTest.php` | `Api::build_media_query_args()` argument mapping |
| `Integration/Abilities/AbilityGuardTest.php` | Base capability, attachment ID validation, per-attachment capability |
| `Integration/Abilities/SearchMediaAbilityTest.php` | `tsmlt/search-media`: registration, permissions, input validation, filters, output allowlist |
| `Integration/Abilities/GetMediaDetailsAbilityTest.php` | `tsmlt/get-media-details`: registration (once), permissions, ID validation, not-found handling, missing/malformed metadata, output schema, no path/meta/EXIF leaks |
| `Integration/Abilities/UpdateMediaMetadataAbilityTest.php` | `tsmlt/update-media-metadata`: per-field persistence and isolation, empty-string clearing, unchanged detection, sanitization, slashing, failure handling, input validation, read/edit authorization, output |
| `Integration/Mcp/McpAdapterTest.php` | Discovery and execution through MCP Adapter 0.7.0 (optional) |
| `Integration/Mcp/McpScopedServerTest.php` | Dedicated `/media-library-tools/mcp` server: lists only the plugin's tools, refuses unlisted and gateway tools, `manage_options` transport check, default server unchanged (optional) |

## Coding standards

On PHP 8.5, the globally installed PHPCSUtils raises a deprecation that PHPCS
reports as an internal error and aborts the file. Suppress deprecations when
running it:

```bash
php -d 'error_reporting=E_ALL & ~E_DEPRECATED' "$(command -v phpcs)" --standard=phpcs.xml app/
```

`phpcs.xml` has two known mismatches that affect every file, both new and
existing:

- It exempts short array syntax through `Generic.Arrays.DisallowShortArraySyntax`,
  but WPCS 3 reports it as `Universal.Arrays.DisallowShortArraySyntax`.
- It sets the text domain to `tsmlt-media-tools`; the plugin's real text domain
  is `media-library-tools`.

To see only the remaining violations:

```bash
php -d 'error_reporting=E_ALL & ~E_DEPRECATED' "$(command -v phpcs)" --standard=phpcs.xml \
  --exclude=Universal.Arrays.DisallowShortArraySyntax,WordPress.WP.I18n app/Abs app/Abilities
```

`phpcs.xml` excludes `tests/`.
