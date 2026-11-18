# MCP Integration (AI Assistants)

Media Library Tools lets AI assistants that speak the Model Context Protocol (MCP) search your media library, read media details and camera EXIF fields, find where media is used, find duplicate files, update media text fields, organise media into Groups, and move media that a verified usage scan found unused to the WordPress trash (and restore it) on your behalf. It can export selected media as CSV. With Media Library Tools Pro it can also build captions from EXIF data, edit EXIF fields, export the whole library to CSV files, and import media text fields from CSV. During development it has been used from Claude Code, and every tool is covered by integration tests that call it through the MCP Adapter. Project status, decisions and the roadmap are at the end of this page ([Project status, decisions and roadmap](#project-status-decisions-and-roadmap)).

## Overview

The integration lets an AI assistant:
- Search the media library (by text, file name, MIME type, Group, attached or unattached, or attachments missing alt text)
- Read the details of one attachment
- Find where media is used on the site (featured images, content, page builders, custom fields, logo, menus, widgets), from the Used Where scan — and start that scan and follow its progress, for a complete used/unused audit
- Read an image's camera, lens and exposure EXIF fields (make, model, lens, software, dates taken and digitized, ISO, aperture, shutter speed, focal length, exposure codes, flash, description)
- Update an attachment's title, alt text, caption, and description
- List, create, and rename media Groups, and change which Groups an attachment belongs to
- List the media in the WordPress trash, move media a verified Used Where scan found unused to the trash after a preview and confirmation, and restore trashed media (see [Media trash](#media-trash--list-preview-trash-restore))
- Preview renaming media files to names you type, one or up to 20 at a time, without renaming anything (see [Rename preview](#rename-preview--tsmlt-preview-media-rename))
- Scan the library's EXIF for privacy-sensitive data — location (GPS), camera, date taken, author, copyright — and list the images that hold it, presence only (see [EXIF privacy scan](#exif-privacy-scan-free))
- **Pro:** build a caption from an image's EXIF data, set or clear EXIF fields of one image or many at once, remove GPS location data, and review and undo EXIF changes — every change after a preview and confirmation (see [Pro: EXIF tools](#pro-exif-tools))
- Export selected media (up to 100 IDs) as CSV text with the columns the CSV import reads (see [CSV export and import](#csv-export-and-import))
- **Pro:** export the whole library, or everything matching the search filters, to one or more CSV files with download links, and check and then apply a CSV that updates existing media (see [CSV export and import](#csv-export-and-import))

The Free tools never change EXIF data or image files. Only the Pro EXIF tools change EXIF data, inside JPEG files: `tsmlt-update-exif-fields`, and, after a preview and confirmation, `tsmlt-apply-bulk-exif-edit`, `tsmlt-remove-gps-metadata` and `tsmlt-undo-exif-change`. No tool can rename, move, upload, or delete files, delete media permanently, or delete or move Groups; `tsmlt-create-media-group` adds Groups and `tsmlt-update-media-group` renames them. Only `tsmlt-trash-media` moves media to the WordPress trash — unused media only, after a preview and confirmation — and `tsmlt-restore-media` brings it back. The MCP Adapter default server always lists these tools, and one connection to it is the recommended setup; connected to the dedicated Media Library Tools endpoint instead, an assistant cannot reach anything outside these tools (see [Choosing an endpoint](#choosing-an-endpoint)).

**Requirements**:
- WordPress **6.9 or newer** (the Abilities API is part of WordPress core from 6.9)
- The official **MCP Adapter** plugin from [wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/) — tested with **0.7.0**. The dedicated endpoint uses the adapter's documented `create_server()` API, whose signature is unchanged since 0.3.0, but versions before 0.7.0 have not been tested. Listing the tools on the default server requires 0.5.0 or newer; with an older adapter, use the dedicated endpoint
- A WordPress **administrator** account (the `manage_options` capability)

Without the MCP Adapter, or on WordPress older than 6.9, nothing is registered and the plugin behaves exactly as before.

## Available Tools

Every tool listed here — Free, and Pro while the licence is active — is listed directly on both the MCP Adapter default server and the dedicated Media Library Tools endpoint, with the same name and the same checks (see [Choosing an endpoint](#choosing-an-endpoint)). The default server lists them always; there is no setting to turn on. Only `tsmlt-search-media` and `tsmlt-get-media-details` can also be run through the default server's generic gateway tool (`mcp-adapter-execute-ability`); call the others by name.

### Free (24)

| Tool | What it does | Changes data |
|---|---|---|
| `tsmlt-search-media` | Lists matching attachments, one page at a time (up to 50 per page), each with its file name (no folders). Optional `filename` matches part of the file name; `group_id` limits results to one Group; `attached` to media attached, or not attached, to a post; `ids` to 1–100 given media IDs (`missing_ids` lists the rest); `content_id`, `content_title` or `content_url` (a page, post or product URL of this site, never fetched) to the media of a post, page or WooCommerce product | No |
| `tsmlt-get-media-details` | Returns one attachment's title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, image sizes, attached post, and upload date | No |
| `tsmlt-get-exif-fields` | Returns one image's camera make and model, lens make and model, software, date taken and date digitized, ISO, aperture, shutter speed, focal length (mm), the exposure program, exposure mode, metering mode, white balance and flash codes defined by the EXIF standard, and the image description — read from the JPEG file, or from values saved in the EXIF editor for any image type. A field without a value is `null` | No |
| `tsmlt-get-media-usage` | Reports where up to 50 attachments are used — featured images, content and excerpts, page builders, galleries and custom fields, and site-wide (logo, site icon, header or background image, menus, widgets, term and site options) — from the records of the Used Where scan. Says whether each one is used, not used, or unknown | No |
| `tsmlt-start-usage-scan` | Starts the Used Where scan in the background, or reports the scan already running (a stalled one is resumed). A new scan replaces the previous usage results while it runs | Yes (usage scan results only) |
| `tsmlt-get-usage-scan-status` | Returns the Used Where scan state, progress, and whether it is stalled or failed. Never starts or resumes a scan | No |
| `tsmlt-list-media-usage` | Lists media by usage (`used`, `unused` or `unknown`), one page at a time (up to 100), after a completed Used Where scan: ID, title, file name, MIME type, usage status, usage types for used media, reason codes for unknown media. Never lists unknown media as unused | No |
| `tsmlt-start-exif-privacy-scan` | Starts the EXIF scan (the same scan as the EXIF Data page) in the background, which records per image whether its EXIF holds location (GPS), camera, date taken, author or copyright data; reports the scan already running, and resumes a stalled one. Never changes files or EXIF | No media (writes scan results) |
| `tsmlt-get-exif-privacy-scan-status` | Returns the EXIF scan's state (idle, running, complete, cancelled), progress and whether it is stalled. Never starts or resumes a scan | No |
| `tsmlt-list-exif-privacy-results` | Lists media by EXIF privacy result, one page at a time (up to 100), after a completed EXIF scan: media with sensitive EXIF and which categories it holds (never values or coordinates), optionally one category such as `gps`; or media without EXIF, unsupported, missing, unreadable or not scanned | No |
| `tsmlt-update-media-metadata` | Changes only the fields you send: title, alt text, caption, description. An empty string clears a field | Yes |
| `tsmlt-list-media-groups` | Lists media Groups with their ID, name, parent Group, and number of media items; filter by name or ID | No |
| `tsmlt-set-media-groups` | Adds, removes, or replaces the Groups of one attachment | Yes (Group assignments only) |
| `tsmlt-create-media-group` | Creates one Group, at the top level or under an existing Group | Yes (adds a Group) |
| `tsmlt-update-media-group` | Renames one Group; its ID, slug, parent and media stay the same | Yes (Group name) |
| `tsmlt-find-duplicate-media` | Lists groups of identical media files, largest first, from the last duplicate scan (Duplicates page), with each file's size and, per attachment, its details, parent post and usage from the Used Where scan. Never starts a scan, merges or deletes | No |
| `tsmlt-start-duplicate-scan` | Starts the duplicate scan in the background (WP-Cron), or reports the one already running; a stalled scan is resumed from where it stopped. Shares its start lock with the Duplicates page | No media (writes the scan's state and file hashes) |
| `tsmlt-get-duplicate-scan-status` | Returns the duplicate scan's state (idle, running, complete, cancelled), progress and whether it is stalled. Never starts or resumes a scan, and never purges results | No |
| `tsmlt-list-trashed-media` | Lists media in the WordPress trash, one page at a time, with when each item was trashed and the earliest date WordPress may delete it permanently | No |
| `tsmlt-preview-trash-media` | Checks whether up to 20 attachments can be moved to the trash (only media a complete, verified Used Where scan found unused, with no post usage rescan pending or running; never the site icon or logo) and, when all can, returns a one-time confirmation token | No (stores a short-lived token) |
| `tsmlt-trash-media` | Moves the previewed attachments to the WordPress trash with the token, re-checking every rule, and reports each item. Never deletes permanently; refused when the site's trash is off | Yes (moves media to the trash) |
| `tsmlt-restore-media` | Restores up to 20 attachments from the WordPress trash and reports each item | Yes (restores media) |
| `tsmlt-export-media-csv` | Exports up to 100 attachments, chosen by ID, as one CSV text in the order given, with the columns the CSV import reads; missing or unreadable IDs are listed as skipped. Never exports custom fields, file paths or metadata | No |
| `tsmlt-preview-media-rename` | Previews renaming the files of 1–20 attachments to names you type: per item the current and resulting file name (extension kept), whether sanitizing changed the name, whether a `-1`-style suffix would be added, and what a rename would affect. Renames nothing. Pro rename strategies are refused | No |

### Pro (14)

| Tool | What it does | Changes data |
|---|---|---|
| `tsmlt-get-exif-caption` | **Pro.** Builds a caption from one image's camera make and model and exposure (aperture, shutter speed, ISO) and returns it without saving | No |
| `tsmlt-update-exif-fields` | **Pro.** Sets or clears EXIF fields of one image: camera, lens, exposure, dates, artist, copyright, description. Edits JPEG files; other image types keep the values in the plugin's database only | Yes (EXIF data, in the JPEG file) |
| `tsmlt-export-media-csv-bulk` | **Pro.** Exports the whole library, or everything matching the `tsmlt-search-media` filters, as CSV files in the background: one file, or split evenly into up to 20. Returns an export job | No media (writes export files, kept 24 hours) |
| `tsmlt-get-media-csv-export` | **Pro.** An export job's progress and, for each finished file, a download link valid for 15 minutes. Read-only | No |
| `tsmlt-continue-media-csv-export` | **Pro.** Advances an export by one batch, for sites where WP-Cron does not run it. Safe to repeat | No media (writes export files) |
| `tsmlt-preview-media-csv-import` | **Pro.** Checks CSV text (up to 256 KB and 50 rows) and shows, row by row, what it would change on existing media; returns a plan to apply | No (stores a short-lived plan) |
| `tsmlt-apply-media-csv-import` | **Pro.** Applies a previewed plan once, with `confirm: true`; rows changed since the preview are skipped | Yes (title, alt text, caption, description, attached post, menu order, Groups) |
| `tsmlt-preview-gps-removal` | **Pro.** Checks up to 50 images and reports per image whether GPS location data was found (never the coordinates) or why it cannot be cleaned; returns a plan. Read-only | No (stores a short-lived plan) |
| `tsmlt-remove-gps-metadata` | **Pro.** Removes GPS location data from the images of a previewed plan, once, with `confirm: true`; other EXIF is kept. Cannot be undone | Yes (JPEG files) |
| `tsmlt-preview-bulk-exif-edit` | **Pro.** Checks setting or clearing EXIF fields on up to 50 images and shows per image and field whether the value would be added, overwrite an existing one (only with `overwrite: true`), stay unchanged or be cleared — never the values; returns a plan. Read-only | No (stores a short-lived plan) |
| `tsmlt-apply-bulk-exif-edit` | **Pro.** Saves a previewed bulk EXIF edit once, with `confirm: true`; images changed since the preview are skipped as conflicts | Yes (EXIF in JPEG files, or the plugin database for other images) |
| `tsmlt-list-exif-change-logs` | **Pro.** Lists one image's EXIF change log, newest first: action, time, user and changed field names, never the values. Read-only | No |
| `tsmlt-preview-exif-undo` | **Pro.** Shows whether one image's newest EXIF change can be undone and which fields it would restore or clear; returns a plan. Read-only | No (stores a short-lived plan) |
| `tsmlt-undo-exif-change` | **Pro.** Undoes the previewed change once, with `confirm: true`, only while it is still the newest change | Yes (EXIF) |

The fourteen Pro tools are offered only when Media Library Tools Pro is active with a valid licence. Pro needs Media Library Tools 2.5.0 or later (the first release with the CSV helper the Pro export uses); with an older free plugin Pro shows a notice on the Plugins screen and does not load, and if the free plugin lacks the helper anyway the three Pro export tools are not offered and administrators see a notice.

**What is never returned**: server file paths, raw attachment metadata, raw EXIF data, GPS coordinates or other location values, copyright, artist, camera serial numbers, or custom fields. Only whether an image's EXIF holds location, camera, date, author or copyright data is reported, by `tsmlt-list-exif-privacy-results` and `tsmlt-preview-gps-removal`; the Pro bulk edit, change log and undo tools report field names, never values. `tsmlt-get-exif-fields` returns only the fields listed above — never time zone offsets, altitude, location, copyright or artist — and `tsmlt-get-exif-caption` returns only the caption text.

**What is never changed**: the file's name or URL, or any attachment other than the one a tool is given. A usage scan (`tsmlt-start-usage-scan`) never changes an existing attached post ("Uploaded to") and sets one only for an image that has none; the only tool that changes an attached post on request is the Pro CSV import, through its `post_parent` column, after a preview and an explicit confirmation. `tsmlt-set-media-groups` changes only the attachment's Group assignments. `tsmlt-trash-media` and `tsmlt-restore-media` change only an attachment's status (to and from the trash) through WordPress's own functions; files stay on disk. The image file itself is changed only by the Pro EXIF tools `tsmlt-update-exif-fields`, `tsmlt-apply-bulk-exif-edit`, `tsmlt-remove-gps-metadata` and `tsmlt-undo-exif-change`, which edit EXIF data inside JPEG files.

## Search by file name

`tsmlt-search-media` accepts `filename`: part of a file name, for example `IMG_2041` or `.png`. It matches the name of the attachment's file, ignoring case — not its title, caption or description (that is what `search` does), and not folder names: `2026` does not match files just because they are in the `2026` upload folder. For large images WordPress scaled down, the file is the `-scaled` one, whose name still contains the original name. `%` and `_` are matched as ordinary characters.

`filename` must be 1–200 characters without `/`, `\`, or control characters; anything else is refused. It combines with every other filter and with paging. Results show the same fields as any search; no server file paths are returned.

## Attached and unattached media

`tsmlt-search-media` accepts `attached`: `true` returns only media attached to a post — WordPress's **Uploaded to** relationship, which may point to a draft, private, or trashed post — and `false` returns only unattached media. Leave it out to search both. It must be a JSON boolean (`true` or `false`); strings such as `"false"` are refused.

Attached is not the same as used. An image can appear in a page's content or a page builder without being attached to that page, and an attached image may not appear anywhere. `attached` does not check where media is used.

## Where media is used — `tsmlt-get-media-usage`

Reads the records of the plugin's **Used Where** scan (**Media Library Tools → Used Where**). It does not scan and changes nothing; start a scan there or with `tsmlt-start-usage-scan` for complete results. A post saved after a scan is re-checked shortly afterwards, by a WP-Cron event.

| Input | Type | Meaning |
|---|---|---|
| `ids` | list of integers, required (1–50, no repeats) | Attachment IDs |
| `max_usages` | integer, 1–100 (default 50) | Most usages listed per attachment; the counts are always complete |

If any ID is not an attachment you can read (missing, trashed, or private to you), the whole request is refused with an error naming that ID.

Output:
- `scan`: `state` of the last scan (`idle`, `queued`, `running`, `complete`, `cancelled` or `error`), `complete`, `processed`, `total`, and `last_update` (site time). `complete` is `true` only when the results in this response come from one completed scan **whose coverage is verified** (see `coverage` in `tsmlt-get-usage-scan-status`) — the status is read before and after the records, and a scan that started or finished in between makes it `false` (call again). A scan made while WooCommerce was inactive is complete but not verified, so its `complete` here is `false`.
- `items`, in the order requested, each with:
  - `id`, and `used`: `true` when a usage is recorded; `false` only when none is recorded, `scan.complete` is `true`, and the attachment was uploaded before that scan started (the **Used Where** page's Unused tab uses the same rules); `null` when that is unknown. Do not treat `null` as unused.
  - `total_usage` (listed plus hidden), `hidden` (usages in posts you cannot read, or of a post type that is no longer registered, and WooCommerce orders kept in WooCommerce's own order tables — counted, never listed), and `truncated` (more usages than `max_usages`).
  - `usages`: `post_id`, `post_type`, `post_status` and the post's current `title`; `usage_type` (for example `featured`, `content`, `excerpt`, `elementor`, `meta`, `woo_gallery`, `site_logo`, `widget`, `nav_menu`, `option_settings`, or `other`); and `sitewide`. Site-wide usages have `post_id` 0, `post_type` `site_settings`, and no status or title.

Usages in posts that have since been deleted are left out. No permalinks, file paths or excerpts are returned.

## Usage scan — `tsmlt-start-usage-scan` and `tsmlt-get-usage-scan-status`

Neither tool takes input (send `{}` or nothing); any argument is refused.

`tsmlt-start-usage-scan` queues the same scan as the **Start scan** button under **Used Where**. The scan runs in the background through WP-Cron in small batches, never inside the request. It returns `result` and `scan` (the status below):

| `result` | Meaning |
|---|---|
| `started` | No scan was in progress; a new one is queued. It replaces the previous usage results as it runs |
| `already_running` | A scan is already queued or running (or another request is starting one); nothing changed |
| `resumed` | A scan was stalled — no batch for over 3 minutes, usually because WP-Cron is not running — and is set to continue where it stopped |

Unlike the admin button, it never restarts a scan in progress. It never changes media files or settings. The scan replaces the usage records and keeps every image's existing attached post ("Uploaded to"); for an image that has none, it sets one to a post that owns it — preferring a post where it is the featured image, otherwise a post that uses it in its content, excerpt, gallery, page-builder data or custom fields. An image found only in a post's rendered page (for example the site logo in the header or a sidebar image) and site-wide usages (logo, menus, widgets, options) never set an attached post. The admin page's **Re-scan** and **Clear Results**, the rescan of a single post after it is saved, and front-end usage tracking follow the same rules.

`tsmlt-get-usage-scan-status` only reads. Output: `scan_id` (changes whenever a new scan starts — from these tools or the admin page; empty before the first scan), `state` (`idle` — never started or cleared; `queued`; `running`; `complete`; `cancelled`; `error`), `complete`, `processed` and `total` (posts), `percent` (0–100: `processed / total`, at most 99 until complete, 0 while the total is unknown), `started_at`, `last_tick_at` and `last_update` (site time, `Y-m-d H:i:s`, or empty), `ever_started` (false when no scan has run since the results were cleared), `stalled`, `has_error`, and `coverage` — `verified` (true when the scan could check every usage source) and `gaps`: `woocommerce_inactive` (WooCommerce was inactive during the scan, so product category images and rendered product pages could not be checked), `woocommerce_types_not_scanned` (products or variations were left out by the `tsmlt_used_where_scannable_post_types` filter), or `not_recorded` (no scan yet, or one made by an earlier plugin version — start a new scan). Without verified coverage, media without a recorded usage is unknown, never unused. The error message itself is shown only under **Used Where**.

WooCommerce products and variations are scanned from the database whether or not WooCommerce is active (their featured images, product galleries and variation images are found either way; variations are scanned although WordPress does not list them as public). What cannot be checked while WooCommerce is inactive is reported as the `woocommerce_inactive` gap, and the **Used Where** page shows a warning: *"Usage verification may be incomplete. WooCommerce is inactive, so some product and variation images may not be detected as used. These images must not be treated as unused or moved to Trash until their usage can be verified."* It goes away after a scan run with WooCommerce active. A stalled scan stays stalled until `tsmlt-start-usage-scan` resumes it (the **Used Where** page also resumes a scan whose batches stopped, while it is open).

WP-Cron must be able to run: on sites with `DISABLE_WP_CRON`, a system cron job has to call `wp-cron.php`, or the scan stays queued (reported as `stalled`).

### Audit every image as used, unused or unknown

1. Call `tsmlt-start-usage-scan`.
2. Call `tsmlt-get-usage-scan-status` every 10–30 seconds until `state` is `complete`, `cancelled` or `error`. If `stalled` is true, call `tsmlt-start-usage-scan` once to resume, then keep polling.
3. Page through `tsmlt-search-media` with `mime_type` `image`, `orderby` `id`, `order` `ASC` and `per_page` 50, from page 1 to `total_pages`. Ordering by ID keeps pages stable when media is uploaded meanwhile (deleting media during the audit can still shift later pages). Skip IDs already seen. The file name is the last part of each result's `url`.
4. Send the IDs to `tsmlt-get-media-usage` in batches of at most 50, until every ID is checked.
5. Classify each image:
   - **Used** — `used` is `true`.
   - **Unused** — `used` is `false` (this happens only when that response's `scan.complete` is `true`).
   - **Unknown** — `used` is `null`: the scan is not complete, failed, or was cancelled.
6. Check `tsmlt-get-usage-scan-status` once more at the end. If a new scan started during the audit (state no longer `complete`), treat the results as unknown and repeat.

Never treat **Unknown** as unused. An image is reported unused only from a completed scan.

### List media by usage — `tsmlt-list-media-usage`

Workflow:
1. Start the scan with `tsmlt-start-usage-scan`.
2. Poll `tsmlt-get-usage-scan-status` until `state` is `complete`.
3. List one class of media at a time with `tsmlt-list-media-usage`.

| Input | Type | Default | Meaning |
|---|---|---|---|
| `status` | `used`, `unused` or `unknown` | (required) | Which list |
| `page` | integer 1–10000 | 1 | Page, in attachment ID order |
| `per_page` | integer 1–100 | 20 | Items per page |

**Output:**
- `items`, each with `id`, `title`, `filename` (no folders), `mime_type` and `usage_status`.
  - Used items add `usage_types` (recorded usages by type, with counts) and `used_in_posts` (distinct posts). Details per item come from `tsmlt-get-media-usage`.
  - Unknown items add `unknown_reasons`: the Used Where page's reason codes, such as `not_covered`, `coverage_not_recorded` or `woocommerce_inactive`.
- `page`, `per_page`, `total` (the list's size, as the Used Where page counts it), `total_pages`.
- `hidden`: media on the page you can't read; left out.
- `excluded`: media on the page whose stored record doesn't match the list, for example a record left only for posts that no longer exist.
- `scan`: `scan_id`, `complete`, `verified`, `started_at`, `gaps`.

**Rules:**
- The lists are the Used Where page's (`UsageClassifier::query_args()`), checked per item with the rule `tsmlt-get-media-usage` uses:
  - **used:** a usage that still counts is recorded;
  - **unused:** none is recorded, and a complete, verified scan covered the item;
  - **unknown:** everything else.
- With a complete scan whose coverage isn't verified, the unused list is empty and such media is listed as unknown.
- Without a completed scan (none, running, cancelled or failed), the tool refuses with `tsmlt_usage_scan_required` and says how to run one.
- If the scan changes while a page is being read, it refuses with `tsmlt_usage_scan_changed`; list again.
- **Read-only:** it never starts a scan, changes records, or cleans up stale records, which the Used Where page removes as it lists them.
- **Paging:** pages come from SQL, so only one page of IDs is loaded.
- **Output contents:** no file paths, cached post titles or scan error texts are returned.


### Live progress and incremental results

A client can show progress and results while the scan runs. MCP does not draw progress bars; the client polls and renders. What the results mean while a scan runs:

- When a scan starts, the previous results are cleared, so every image starts as unknown.
- A recorded usage (`used: true`) appears once the scan reaches a post that uses the image. It is not removed again while the scan runs (the clean-up at the end only drops duplicate page-view records from images that keep another usage), so it can be shown as **Used (provisional)**.
- An image without a recorded usage is **Unknown** until the scan completes: the scan may not have reached the post that uses it yet.
- **Unused** exists only after the scan completed (`scan.complete` true in that `tsmlt-get-media-usage` response).

Client state to keep between polls: the `scan_id` the results belong to, and per image its ID, file name, class (`used`, `unused`, `unknown`), usage count (`total_usage`, when known) and whether it is provisional.

Each poll (every 10–30 seconds):

1. Call `tsmlt-get-usage-scan-status`. Show `state`, `processed / total` and `percent`.
2. If `scan_id` differs from the stored one, a new scan started (from MCP or the admin page): mark every image unknown, store the new `scan_id`, and carry on.
3. List images with `tsmlt-search-media` (`mime_type` `image`, `orderby` `id`, `order` `ASC`, `per_page` 50, all pages); add new IDs as unknown. Doing this every poll is optional; do it at least at the start and before the final check.
4. While `state` is `queued` or `running` (and not `stalled`): send only the **unknown** images to `tsmlt-get-media-usage`, at most 50 per call. Mark `used: true` as used (provisional, with `total_usage`); keep the rest unknown. Images already used are not sent again until the final check.
5. When `state` is `complete`: send **every** image once more (50 per call). Then call `tsmlt-get-usage-scan-status` again. The summary is **final** only if every response had `scan.complete` true and the status still shows `complete` with the same `scan_id`; otherwise mark every image unknown and keep polling.
6. When `state` is `error` or `cancelled`, or `stalled` is true: show that state, keep the results provisional, report no image as unused, and stop (or call `tsmlt-start-usage-scan` to start again or resume, and keep polling).

Example summary for display:

```
Usage scan: running — 120 / 300 posts (40%) — provisional
Used (provisional): 85   Unused: —   Unknown: 215
  #412  hero.jpg      used (3)
  #977  banner.png    unknown
```

After the final check:

```
Usage scan: complete — 300 / 300 posts (100%) — final
Used: 190   Unused: 110   Unknown: 0
```

Every call is bounded (at most 50 IDs, 50 search results); there is no single call that audits the whole library.

## Duplicate media — `tsmlt-find-duplicate-media`

Lists groups of identical files found by the duplicate scan under **Media Library Tools > Duplicates**, one page at a time. It reads that scan's results and never starts, cancels or repeats it.

### Scan, status, results

1. **`tsmlt-start-duplicate-scan`** (no input) starts the scan, the same one as the Duplicates page's **Start** (`DuplicateScanner`, WP-Cron batches of 100 files). It returns `result` and the status (`scan`).
   - `started`: a new scan started. Files already checked are not read again.
   - `already_running`: a scan was running, or another request (MCP or the Duplicates page) was starting one. Nothing changed; a running scan is never restarted.
   - `resumed`: the scan was running with no progress for over 3 minutes (WP-Cron not running its next batch). It continues from where it stopped; the next batch is scheduled and WP-Cron is asked to run.
2. **`tsmlt-get-duplicate-scan-status`** (no input, read-only) returns:
   - `state`: `idle` (never started or cleared), `running`, `complete`, or `cancelled` (cancelled on the Duplicates page; results so far are kept);
   - `complete`, `stalled`;
   - `processed` and `total` media (the total is counted when the scan starts);
   - `percent` (at most 99 until complete);
   - `started_at` and `last_update` in site time (`Y-m-d H:i:s`, or empty).

   It only reads the scan's stored state. It never schedules anything, and never calls the Duplicates page's status read, which purges rows of deleted media. Poll it every few seconds until `complete` is true.
3. **`tsmlt-find-duplicate-media`** then lists the duplicate groups.

**Starts take a lock.** Every start, from MCP or the Duplicates page, takes the same start lock (`DuplicateScanner::START_LOCK_OPTION`, through `ScanStartLock`, separate from the usage scan's lock), so two starts cannot interleave. The Duplicates page's Start keeps its behaviour (it restarts a running scan), but while another start holds the lock it returns the current progress and changes nothing.

**Not over MCP:** cancelling or clearing a scan (Duplicates page only). The scan reads media files and stores their hashes in its own table; no media file, attachment or result is changed or deleted. Hashes and file paths are never returned.

Input (both optional; anything else is refused):

| Field | Type | Default | Meaning |
|---|---|---|---|
| `page` | integer 1–10000 | 1 | Page of groups |
| `per_page` | integer 1–20 | 10 | Groups per page |

Output:

- `scan` — the duplicate scan: `state` (`idle`, `running`, `done`, `cancelled`), `processed` and `total` attachments, `started_at` and `updated_at` (ISO 8601, UTC, or empty), and `complete` (true only when `done`).
- `page`, `per_page`, `has_more` (whether there is a next page).
- `groups`, largest first (then in a fixed order, so pages are stable while the duplicate scan data does not change): `file_size` (bytes, as scanned), `item_count` (attachments in the group: the ones you can read plus `hidden`), `hidden` (attachments you cannot read, never listed), `truncated` (true when you can read more than 20), and `items` — at most 20 per group, oldest first, each with the same fields as `tsmlt-search-media` (ID, title, alt text, caption, MIME type, URL, upload date), plus `parent_id` (the parent post, or `null` when there is none or you cannot read it) and `usage`: `used`, `unused` or `unknown`, by the same rules as `tsmlt-get-media-usage` (`unused` only after a complete, verified Used Where scan that covered the file).

What to keep in mind:

- Results reflect the last duplicate scan. While it runs, or after it was cancelled, they are provisional (`scan.complete` is false); files added or changed since it ran are not covered.
- Pages are numbered, not linked: if the scan data changes between two page requests (a scan running, attachments deleted or trashed), groups can move between pages, so one may be missed or listed twice. Read all pages while the scan is complete and the library is quiet for a consistent list.
- Two files are duplicates when their bytes are identical: for a large image, the scaled file WordPress keeps. Re-encoded, resized or converted copies are not detected.
- Usage comes from the Used Where scan, not from the duplicate scan.
- Attachments deleted or moved to the trash since the scan are left out, and so are repeated rows of one attachment; the tool does not clean up those stale rows (the Duplicates page does when it loads its results).
- The tool is read-only: no file paths, file hashes or raw metadata are returned, and nothing is merged, deleted or changed (merging duplicates is a Pro feature of the Duplicates page, not an MCP tool).

## Rename preview — `tsmlt-preview-media-rename`

Shows what renaming media files to names you type would do, without renaming anything. It covers the Free renames of the Rename page: a single rename and Bulk Rename with your own names. There is no MCP tool that renames files yet.

**Workflow**:
1. Find the media with `tsmlt-search-media`. Its `filename` input matches part of a file name, case-insensitively, and each result has the attachment's `id` and `filename`. When several match, pick by `id`.
2. Call `tsmlt-preview-media-rename` with `items`: 1–20 objects `{ "id": 123, "filename": "new-name" }`.

**How the resulting name is worked out** (the same rules as the rename on the Rename page, `Fns::wp_rename_attachment()`, through the shared `Fns::rename_target_name()`):
- The typed name passes `sanitize_text_field()`. Then the part before the last dot is kept, it is sanitized with `sanitize_file_name()`, and `-scaled` and `-rotated` are removed.
- The current extension is always kept. A typed extension, or any other `.text` ending, is dropped (`extension_ignored`).
- If the name is already used in the file's folder, WordPress's `wp_unique_filename()` adds a `-1`-style suffix (`needs_unique_suffix`).

**Per item**:
- `status`:
  - `ready`: the file can be renamed to `proposed_filename`;
  - `unchanged` (the resulting name is the current one);
  - `invalid_filename` (empty after sanitizing, or it contains `/`, `\` or control characters);
  - `pro_strategy_not_supported`;
  - `invalid_id`;
  - `not_found` (missing, not readable for you, trashed, or not media);
  - `forbidden` (you may not edit it);
  - `file_missing`.
- `requested_filename`, `current_filename` and `proposed_filename`: file names only, never folders.
- `sanitized`, `extension_ignored`, `unchanged`, `needs_unique_suffix`.
- `same_name_in_request`: two items of the request would get the same name in the same folder; the second rename would get a suffix.
- `affects`:
  - `generated_sizes`: sizes a rename deletes and makes again under the new name;
  - `original_image_kept`: the full-size original WordPress keeps for scaled or rotated uploads exists, and a rename leaves it with its old name;
  - `backup_sizes`: pre-edit backups, left with their old names;
  - `content_links_updated`: a rename replaces links to the image in post content and excerpts (images only).

`summary` counts the items per status.

**Rules**:
- The tool requires `manage_options`, and for each item that you can read (`read_post`) and edit (`edit_post`) the attachment.
- It is read-only: no file, metadata, post, attached post ("Uploaded to"), GUID or content is changed. It never calls the Rename page's handler.
- Renaming by attached post title, product SKU or alt text, AI names, the prefix and suffix settings and automatic rename on upload are Pro features of the Rename page and are not available here. Their keywords (`bulkRenameByPostTitle`, `bulkRenameBySKU`, `bulkRenameByAltText`, `bulkAiFilename`) are refused, never used as a name.

**Limitations**:
- The preview is a snapshot: a file uploaded or renamed afterwards can change the suffix.
- Links in other places a rename does not update (block attributes, custom fields, other page builders, menus, options) are not reported.

## Media trash — list, preview, trash, restore

Four tools manage the WordPress trash for media. They use WordPress's own `wp_trash_post()` and `wp_untrash_post()`: there is no separate trash, table or deletion of the plugin's own, and the files stay on disk while media is in the trash.

**MCP supports Trash and Restore only.** No MCP tool deletes media permanently, empties the trash, or takes a delete-related input. Every trash tool's description and the dedicated server's instructions tell the client, word for word:

> Permanent deletion is not available through MCP. Before permanently deleting media, inspect the entire website for dependencies, create and verify a backup, then log in to WordPress Admin and carefully perform the deletion manually.

Trashing is deliberately strict. Read this before you use it:

- **Only unused media.** An attachment can be trashed only when the latest Used Where scan is complete, its coverage is verified, it covered the attachment (uploaded before the scan started), and it recorded no usage — `unused` in `tsmlt-get-media-usage`. Used, unknown, unscanned and unverifiable media is refused. If usage is unknown, run `tsmlt-start-usage-scan` and wait for it to complete.
- **Not while a post's usage rescan is pending or running.** Saving a post schedules a rescan of that post's usages through WP-Cron (about 10 seconds later, longer on a site with little traffic); saving again while one is pending keeps it, so one is pending throughout. The rescan first removes the post's usage records and then rebuilds them, so it runs marked as running. Until every pending or running rescan has finished, an image just added to a post — or used only by a post being rescanned — may not be recorded, so media without a recorded usage is reported `unknown` and cannot be trashed; preview again once WP-Cron has run. The same applies whenever this cannot be verified: the schedule or the running markers cannot be read, or another plugin handles WordPress's scheduled events (it hooks `pre_schedule_event`, `pre_get_scheduled_event` or a related filter). Usages changed outside post saves (menus, widgets, theme settings) are only picked up by the next full scan.
- **Never the site icon or logo** (`site_icon`, the theme's `custom_logo`, `site_logo`).
- **Preview, confirm, then trash.** `tsmlt-trash-media` needs a confirmation token from `tsmlt-preview-trash-media`. Show the preview to the user and trash only after they confirm.
- **Trash must be on.** When `EMPTY_TRASH_DAYS` is 0, WordPress deletes instead of trashing (and a negative value makes its cleanup purge the trash at once), so every trash request is refused before anything changes. A value that is undefined or not a whole number counts as off too. Nothing through MCP ever deletes media permanently.
- **It shows on the site at once.** The plugin hides trashed media on the front end (empty URL and image), so anything still using a trashed file stops showing it. A verified scan recorded no usage, but content added since the scan is not known.
- **WordPress deletes it later.** WordPress's scheduled daily trash cleanup (`wp_scheduled_delete`) permanently deletes trashed media `EMPTY_TRASH_DAYS` days (30 by default) after it was trashed — WordPress does this, not an MCP tool. `purge_at` is the earliest time that can happen; restore before then to keep it.
- **Changing `EMPTY_TRASH_DAYS` later affects what is already in the trash.** If it is later set to 0 or a negative number, WordPress's next cleanup can permanently delete everything already in the trash, including media trashed through MCP; a smaller number brings purge dates forward (`purge_at` is always calculated from the current setting). Restore anything you want to keep before changing it.

### List the trash — `tsmlt-list-trashed-media`

Input (both optional): `page` (1–10000, default 1) and `per_page` (1–20, default 10).

Output: `items` — most recently changed first, each with the same fields as `tsmlt-search-media` (the URL is empty while the plugin hides trashed media) plus `trashed_at` and `purge_at` (ISO 8601, UTC, or empty when WordPress did not record the trash time); `total` (trashed media in all), `total_pages`, `hidden` (trashed media on this page you cannot read, never listed), `page`, `per_page`; and `trash`: `enabled`, `empty_trash_days`, `media_trash` (for information only — on WordPress's own media screens it decides whether media gets a Trash link and whether Delete moves media to the trash or deletes it permanently; the MCP tools always use the trash and never delete, whatever its value).

### Check before trashing — `tsmlt-preview-trash-media`

Input: `ids`, 1 to 20 attachment IDs, each once.

For each ID: `eligible`, `reason` (empty when eligible), `usage` (`used`, `unused`, `unknown`), `usage_count`, `scan_verified` (a complete, verified scan covered it), `frontend_impact` (`none_recorded`, `breaks_recorded_usage`, `unknown`) and `media` (summary fields, or `null` when not available). Reasons:

| Reason | Meaning |
|---|---|
| `trash_disabled` | `EMPTY_TRASH_DAYS` is 0 or less (or a site filter turned MCP trash off) |
| `not_available` | Missing, not media, or you may not delete it — the three are reported alike |
| `already_trashed` | Already in the trash |
| `invalid_status` | Not in the media library's normal `inherit` status (for example private) |
| `protected_site_media` | The site icon or logo |
| `used` | The scan recorded a usage |
| `usage_unknown` | No complete, verified scan covered it, or a saved post's usage rescan is pending or running, or that cannot be verified |

When every ID is eligible, the result has a `confirmation_token` and `expires_at`; otherwise both are `null` — preview again with only the eligible IDs. Changes no media; the only write is the token, so the tool is not annotated read-only.

The token is 64 random hexadecimal characters, issued for the user who previewed, exactly that set of IDs (in any order), and the Used Where scan of the preview; it expires after 10 minutes and works once. Only its SHA-256 hash is stored, in a non-autoloaded option; expired ones are removed, oldest first, when a new token is issued (up to 1,000 per preview), so they cannot build up.

### Trash — `tsmlt-trash-media`

Input: `ids` and `confirmation_token`, both required.

In order, before any attachment changes:

1. Trash must be on; otherwise the call fails with `tsmlt_trash_disabled` and the token is not used.
2. The token must exist, be unexpired and match the user and IDs; otherwise `tsmlt_trash_token_invalid`. A token for other IDs or another user is not used up; an expired one is removed. A matching token is used up now, even if a later step refuses the call.
3. The Used Where scan must be the one the preview used; otherwise `tsmlt_trash_scan_changed` (preview again).
4. Usage is read again; a failed read fails closed with `tsmlt_trash_read_failed`.

Then each ID, in order, is checked again — the scan unchanged, no post usage rescan pending or running, permission to delete, status, site icon or logo, usage. An item that passes is claimed (only one MCP trash or restore request at a time can work on a given attachment), checked again under the claim, the claim renewed, and then comes its own `wp_trash_post()` call; it is read back afterwards and the claim released. Items refused before the claim write nothing. A claim is held for an hour from when it was taken or renewed, so a long trash call keeps it; a claim left by a request that died is taken over once that hour has passed. While it is being trashed, any permanent deletion of that attachment is refused, as a second safeguard.

`items` gives each ID's `result` and `reason`; `summary` counts them (`total`, `trashed`, `already_trashed`, `refused`, `blocked`, `failed`):

| Result | Meaning |
|---|---|
| `trashed` | In the trash now |
| `already_trashed` | It was already in the trash, or something else (an admin, another plugin) trashed it during the call; this call did not trash it |
| `refused` | A check failed (`reason` as in the preview; `scan_changed` when a new scan started during the call, so the items not yet trashed are refused; `in_progress` while another MCP request is trashing or restoring the same attachment, or when this request lost its claim); nothing changed |
| `blocked` | A `pre_trash_post` filter (another plugin) stopped it; still in the library |
| `failed` | WordPress could not update it (`update_failed`; still in the library, possibly with WordPress's trash records left on it, which its cleanup removes), this call trashed it but something changed it back before it was read (`changed_during_call`), it ended up in another status (`unexpected_status`), it no longer exists (`deleted`), or it could not be claimed (`claim_failed`, a database error) |

Success (`trashed`) is reported only when the attachment is in the trash and WordPress reported this call's trash (`trashed_post`).

Items are trashed one by one; nothing is rolled back if a later item is refused or fails. Read `items` to see exactly what changed.

### Restore — `tsmlt-restore-media`

Input: `ids`, 1 to 20 attachment IDs. No token: a restore only brings media back. Each ID is restored with `wp_untrash_post()` and read back; results are `restored` (only when it is back in the library and WordPress reported this call's restore, `untrashed_post`), `not_trashed` (it was not in the trash, or something else restored it during the call; nothing changed by this call, so repeating a restore is harmless), `refused` (`not_available`, or `in_progress` while another MCP request works on it — restore takes the same claim as trash), `blocked` (a `pre_untrash_post` filter stopped it) or `failed` (`update_failed`, `changed_during_call`, `unexpected_status`, `deleted`, `claim_failed`), with the same `summary` counts. When a restore fails with `update_failed`, the media stays in the trash; WordPress removes its trash records before that update, so the tool puts them back (`_wp_trash_meta_status` and `_wp_trash_meta_time`, only if missing) and it keeps its trash date and purge date. If they cannot be put back, it stays in the trash without a trash date, so WordPress's cleanup never purges it and `tsmlt-list-trashed-media` shows empty `trashed_at` and `purge_at`; restore it again. Restored media is back in the library (`inherit`) and shows on the site again.

Permissions: listing needs `read_post` for each trashed attachment; preview, trash and restore need `delete_post` for each attachment, as WordPress's own trash and restore links do.

## Working with Groups

Groups are the folder-like categories you manage under **Media → Groups**. An assistant works with them by ID: it finds a Group's ID by name with `tsmlt-list-media-groups`, then uses that ID with the other tools. A Group name is never accepted in place of an ID.

### Find Groups — `tsmlt-list-media-groups`

| Input | Type | Meaning |
|---|---|---|
| `search` | string, up to 200 characters | Part of the Group name, matched case-insensitively |
| `id` | integer | One exact Group |
| `page` | integer, from 1 (default 1) | Page number |
| `per_page` | integer, 1–100 (default 50) | Groups per page |

All inputs are optional. When both `search` and `id` are given, a Group must match both. Results are ordered by name, then ID.

Output: `groups` (each with `id`, `name`, `parent` — the parent Group's ID, or 0 for a top-level Group — and `count`, the number of media items in the Group as stored by WordPress), `total`, `page`, `total_pages`.

Group names are not unique: two Groups under different parents can share a name, and both are returned. Check `parent` before choosing an ID.

### Find media in a Group — `tsmlt-search-media` with `group_id`

Add `group_id` (an integer) to any search. Results are limited to media assigned directly to that Group; media in its sub-Groups are not included. All other filters and paging work as before. An ID that is not a Group — unknown, or a term from another taxonomy — is refused with an error naming the ID.

### Create a Group — `tsmlt-create-media-group`

| Input | Type | Meaning |
|---|---|---|
| `name` | string, required, 1–200 characters | The Group's name. Plain text: HTML is removed |
| `parent` | integer, 0 or more (default 0) | An existing Group's ID, or 0 for a top-level Group |

WordPress creates the Group and its slug. A name that already exists under the same parent (ignoring upper and lower case) is refused, and the error names the existing Group's ID so it can be used instead; the same name under a different parent is allowed, as on the **Media → Groups** screen. A parent that is not a Group — unknown, or a term of another taxonomy — is refused, as are names that are empty or only HTML.

Output: `group` (`id`, `name`, `parent`, `count`) and `created` (`true`). Use the new ID with `tsmlt-set-media-groups` to add media to it.

### Rename a Group — `tsmlt-update-media-group`

| Input | Type | Meaning |
|---|---|---|
| `id` | integer, required | The Group's ID |
| `name` | string, required, 1–200 characters | The new name. Plain text: HTML is removed |

Only the name changes. The Group keeps its ID, its slug (so its URL does not change), its parent and its media. A name that another Group already has under the same parent (ignoring upper and lower case) is refused, and the error names that Group's ID; the same name under a different parent is allowed. Renaming a Group to the name it already has changes nothing.

Output: `group` (`id`, `name`, `parent`, `count`, after the change), `previous_name`, and `changed`. To undo a rename, rename the Group back to `previous_name`. If WordPress does not save the new name as requested, the previous name is put back and an error is returned.

Groups cannot be deleted or moved to another parent through MCP; do that under **Media → Groups**.

### Change an attachment's Groups — `tsmlt-set-media-groups`

| Input | Type | Meaning |
|---|---|---|
| `id` | integer, required | Attachment ID |
| `group_ids` | list of integers, required (up to 100, no repeats) | Existing Group IDs |
| `mode` | `add`, `remove`, or `replace`, required | How to apply them (below) |

| Mode | Effect | With an empty `group_ids` |
|---|---|---|
| `add` | Keeps the attachment's Groups and adds these | Nothing changes |
| `remove` | Removes only these Groups | Nothing changes |
| `replace` | Makes these the attachment's only Groups | Removes all its Groups |

Every ID must be an existing Group. If any ID is unknown or belongs to another taxonomy, the request is refused and nothing changes, not even for the valid IDs. This tool never creates Groups; use `tsmlt-create-media-group` first.

Output: `id`, `mode`, `groups_before` and `groups_after` (each Group with `id`, `name`, `parent`, `count`), and `changed`. The result is read back from the database after saving; if it does not match the request, the attachment's previous Groups are restored and an error is returned.

## EXIF privacy scan (Free)

Finds images whose EXIF data could reveal private information. The scan is the EXIF Data page's scan (`ExifScanner`, WP-Cron batches of 100); besides the page's counts it now records a small result per image. All three are listed on the default server and the dedicated endpoint.

**Workflow**:
1. Start the scan with `tsmlt-start-exif-privacy-scan`. A running scan is not restarted (`already_running`); a stalled one (no progress for 3 minutes) is resumed (`resumed`). Starting from MCP and from the EXIF Data page share one start lock.
2. Poll `tsmlt-get-exif-privacy-scan-status` until `complete` is true. It never starts or resumes anything.
3. List the findings with `tsmlt-list-exif-privacy-results`.

| Input (list) | Type | Default | Meaning |
|---|---|---|---|
| `result` | see below | `sensitive` | Which result to list |
| `category` | `gps`, `camera`, `date_taken`, `author`, `copyright` | (none) | Only images with this category; `result` must be `sensitive` |
| `page` | integer 1–10000 | 1 | Page, in attachment ID order |
| `per_page` | integer 1–100 | 20 | Items per page |

**Results**:
- `sensitive`: the EXIF holds at least one category, listed in `categories`.
- `exif_no_sensitive`: EXIF without any of the categories.
- `no_exif`: a supported file with no EXIF.
- `unsupported`: a format whose EXIF is not read (everything except JPEG and TIFF, WebP included).
- `missing_file`: the attached file is missing.
- `unreadable`: the file cannot be read.
- `no_extension`: the server's PHP has no EXIF extension, so nothing could be checked.
- `not_scanned`: the last scan did not reach the image, for example because it was uploaded after the scan started.

Every result except `sensitive` means "not found sensitive", not "safe": unsupported, missing, unreadable, unchecked and unscanned images are reported as such, never as clean.

**Categories (presence only)**:

| Category | EXIF tags checked |
|---|---|
| `gps` | GPSLatitude or GPSLongitude |
| `camera` | Make or Model |
| `date_taken` | DateTimeOriginal, DateTimeDigitized or DateTime |
| `author` | Artist or XPAuthor |
| `copyright` | Copyright |

**Output**:
- `items`, each with `id`, `title`, `filename` (no folders), `mime_type`, `result` and `categories`.
- `page`, `per_page`, `total`, `total_pages`.
- `hidden`: images on the page you cannot read; left out.
- `excluded`: images whose result changed while the page was read.
- `scan`: the status, as the status tool returns it.

**Rules**:
- Without a completed scan run with privacy results (none, running, cancelled, or a scan from before this feature), listing is refused with `tsmlt_exif_scan_required`; if a scan starts while a page is read, with `tsmlt_exif_scan_changed`.
- Each scan run has an ID. Results of earlier runs are never listed as current; their images are `not_scanned`.
- Filtering and paging are done in SQL on the stored results; listing never reads image files.
- No coordinates, names, dates, camera models, copyright text or paths are stored or returned, only which categories are present.

**Supported formats and limitations**:
- EXIF is read with PHP's `exif_read_data()`, which reads JPEG and TIFF. Tested on PHP 8.3, 8.4 and 8.5: it does not read EXIF from WebP files, so WebP images are `unsupported`. PNG, GIF, AVIF, HEIC and other formats are `unsupported` as well.
- The image's stored MIME type decides the format, as for the EXIF Data page.
- Only the attached file is read, not generated sizes or the original WordPress keeps for scaled uploads. XMP and IPTC metadata are not checked.
- Results describe the files when the scan read them. After GPS removal, an EXIF edit or a new upload, run the scan again.
- Clear on the EXIF Data page removes the results along with the page's counts.

## Pro: EXIF tools

These tools need **Media Library Tools Pro** with an active licence. Without one they are not listed, and a call is refused. While it is active they are listed on the default server and the dedicated endpoint alike.

**Preview, then execute**: every Pro tool that changes several images, removes GPS data or undoes a change works in two steps. The preview tool changes nothing and returns a `plan_id`. Show its result to the user; only after they approve, call the apply tool with the `plan_id` and `confirm: true`. A plan belongs to the user who made it and to one tool, expires after about 15 minutes, and can be applied once. Access to every image is checked again when the plan is applied.

### Build a caption — `tsmlt-get-exif-caption`

Input: `id` (attachment ID). Output: `id`, `has_exif`, and `caption` — for example `Canon EOS R5 · f/1.8 1/250s ISO 100`, or an empty string when the image has no usable camera or exposure data. The caption is built from the image file's EXIF data or, when the file has none, from values saved in the EXIF editor. Nothing is saved; to use the caption, save it with `tsmlt-update-media-metadata`.

### Set or clear EXIF fields — `tsmlt-update-exif-fields`

| Input | Type | Meaning |
|---|---|---|
| `id` | integer, required | Attachment ID |
| `fields` | object | Fields to set, by name (below). Omitted fields are left as they are |
| `clear` | list of field names | Fields to remove from the image. A field cannot be both set and cleared |

| Field | EXIF tag | Value |
|---|---|---|
| `make`, `model`, `lens_make` | Make, Model, LensMake | Text, up to 64 characters |
| `lens_model`, `software` | LensModel, Software | Text, up to 128 characters |
| `artist`, `copyright` | Artist, Copyright | Text, up to 256 characters |
| `image_description` | ImageDescription | Text, up to 2000 bytes |
| `date_time_original`, `date_time_digitized` | DateTimeOriginal, DateTimeDigitized | `YYYY:MM:DD HH:MM:SS`, not in the future |
| `iso` | ISOSpeedRatings | Whole number, 1–65535 |
| `shutter_speed` | ExposureTime | Seconds: a fraction such as `1/250` (kept as written) or a number such as `0.5` or `30`; at most 86400 |
| `aperture` | FNumber | f-number, 0.1–128, stored to one decimal |
| `focal_length` | FocalLength | Millimetres, 0.01–10000, up to two decimals |
| `exposure_program`, `exposure_mode`, `metering_mode`, `white_balance`, `flash` | ExposureProgram, ExposureMode, MeteringMode, WhiteBalance, Flash | A code the EXIF standard defines; the allowed codes are listed in the tool's input schema |

Location (GPS position and altitude), time zone offsets, orientation, the camera maker's private data (MakerNote), serial numbers and the embedded thumbnail cannot be changed, and other field names are refused.

Output: `id`, `success`, `status`, `file_updated`, `storage`, `updated` and `cleared` (field names), `message`, and on failure `error` and, for invalid input, `errors` (a message per field).

- `status`: `updated`, `unchanged` (the values were already in place), `invalid_fields` (unknown or conflicting field names, or nothing to do), `validation_failed` (see `errors`), `unsupported_format` (the attachment is not an image), `file_not_found`, or `write_failed` (the file could not be edited safely and was not changed).
- `storage`: `file` for JPEG images, whose file is edited; `database` for other image types, whose values are kept by the plugin and whose file is never changed; `none` when nothing was saved.
- `file_updated`: whether the image file's bytes changed. It is false when only the database changed or nothing did.
- Changing `date_time_original` also changes `date_time_digitized` when the two were equal; `updated` then lists both.

Over MCP, a failure is reported as a tool error whose text starts with the status, for example `validation_failed: ISO must be a whole number between 1 and 65535.`

**Limitations**:
- Only the attached image file is edited. For large uploads that WordPress scales down, the full-size original keeps its previous EXIF data, and generated image sizes are not changed.
- XMP metadata is not updated, so applications that prefer XMP may still show earlier values.
- ShutterSpeedValue and ApertureValue, which some cameras store alongside the exposure time and f-number, are not recalculated.
- Each request changes one image.

### Remove GPS location data — `tsmlt-preview-gps-removal`, then `tsmlt-remove-gps-metadata`

1. Call `tsmlt-preview-gps-removal` with `ids` (1–50 attachment IDs). Each image gets one status:
   - `gps_found` or `no_gps_found`: the attached file and the original WordPress keeps for scaled uploads were checked.
   - `unsupported_format`: only JPEG images can be cleaned.
   - `file_not_found`, `unreadable`.
   - `not_found` (missing, or not readable for you), `forbidden` (you may not edit it).

   Every JPEG image in the first two groups goes into the plan (`in_plan`), so its generated sizes are cleaned too. Coordinates are never returned.
2. After the user approves, call `tsmlt-remove-gps-metadata` with `plan_id` and `confirm: true`. Each image is cleaned by the EXIF Data page's GPS removal (`ExifStripperModule::strip_gps_from_attachment()`):
   - every JPEG file of the image is processed (attached file, original, generated sizes and pre-edit backups);
   - only the GPS tags (and XMP location) are removed; other EXIF is kept;
   - each file is written to a temporary file, verified and only then swapped in;
   - a file without GPS is not rewritten.

   Per image the result is one of:
   - `removed`;
   - `no_gps`;
   - `partial`: the JPEG files were cleaned, but a file in another format, such as a HEIC original, may still hold location data;
   - `failed`;
   - `not_found` or `forbidden`.

   `files_changed` tells whether any file was rewritten.

GPS removal cannot be undone and is not in the change log.

### Edit EXIF fields on many images — `tsmlt-preview-bulk-exif-edit`, then `tsmlt-apply-bulk-exif-edit`

| Input (preview) | Type | Meaning |
|---|---|---|
| `ids` | 1–50 attachment IDs | Images to change |
| `fields` | object | Values to set; the fields and rules of `tsmlt-update-exif-fields` (no location, time zone offsets, orientation, MakerNote or serial numbers) |
| `clear` | list of field names | Fields to remove; not also in `fields` |
| `overwrite` | boolean, default false | false: only fill fields that are empty; true: also replace existing values |

**Preview**:
- Values are validated once with the EXIF editor's rules; invalid input is refused before any image is read.
- Each image's `status` is `will_change`, `no_changes`, `unsupported_format`, `file_not_found`, `unreadable`, `not_found` or `forbidden`.
- `storage` is `file` (JPEG) or `database` (other images, values kept by the plugin only).
- Each field gets an action:
  - `add`: the field is empty;
  - `overwrite`: an existing value is replaced, only with `overwrite: true`;
  - `skip_existing`: the field has a value and `overwrite` is false; that image keeps its value;
  - `unchanged`;
  - `clear`;
  - `already_empty`.
- No values are returned.

**Apply** (`plan_id`, `confirm: true`):
- Each image is saved with the EXIF editor (`ExifEditorModule::save_exif()`), as the Bulk Edit EXIF action does: only the changed tags are written, and the JPEG file is replaced only after a verified write.
- An image whose affected fields changed after the preview is reported as `conflict` and left alone.
- The other statuses are `updated` (with `updated`, `cleared` and `file_updated`), `unchanged`, `validation_failed`, `file_not_found`, `write_failed`, `not_found` and `forbidden`.
- Each save is logged and can be undone.

### Review EXIF changes — `tsmlt-list-exif-change-logs`

Input: `id`, `page` (1–50), `per_page` (1–50, default 20). Output: the image's log entries, newest first. The plugin keeps the last 50 per image.

Each entry has:
- `operation_id`;
- `action`: `edit`, `import`, `auto-process`, or `strip` (all EXIF was removed);
- `timestamp` in site time, and `user_id`;
- `fields`: the names of the changed fields;
- `undoable`: only the newest entry, and only when no full strip came after it.

The old and new values are not returned. Location changes and GPS removal are never logged.

### Undo the last EXIF change — `tsmlt-preview-exif-undo`, then `tsmlt-undo-exif-change`

1. `tsmlt-preview-exif-undo` with `id` returns:
   - `status`:
     - `undoable`;
     - `nothing_to_undo`;
     - `strip`: all EXIF was removed after the change, so it cannot be put back;
     - `no_field_data`.
   - `entry`: the newest log entry.
   - `fields`: each field with `restore` or `clear`, never the values.
   - A `plan_id` when undoable.
2. `tsmlt-undo-exif-change` with `plan_id` and `confirm: true` runs the EXIF editor's Undo (`ExifLogger::undo_last_change()`). It returns:
   - `restored`: the logger reported success and the entry left the log;
   - `stale`: the change is no longer the newest, for example already undone or followed by another change; nothing is done;
   - `failed`: nothing was changed.

Only the newest change of an image can be undone, one at a time. Location is never restored.

### EXIF features not available through MCP

These Pro features exist in WordPress Admin but are not offered as MCP tools, because they cannot yet be offered safely:

| Feature | Why not |
|---|---|
| EXIF export (Bulk EXIF export, CSV/JSON) | The export always includes GPS latitude and longitude, which MCP responses must not carry. It needs an option to leave location out. |
| EXIF import | It applies a file directly, with no preview or dry run, and it writes GPS coordinates. Its CSV reader also expects lowercase column names (`make`, `model`), while the export writes `Make`, `Model`, so an exported file is not read back completely. It needs a validation-only mode first. |
| EXIF ↔ WordPress metadata sync | Neither direction has a preview. WordPress → EXIF splits the attachment title into camera Make (first word) and Model (the rest) and overwrites them; EXIF → WordPress fills only an empty caption and title. It needs a preview that names the affected fields first. |
| Remove all EXIF | Bulk "remove all" re-saves the image without any EXIF (orientation, copyright and colour data included). It is irreversible and blocks undo of earlier changes; not offered until it is specifically approved. |

## CSV export and import

`tsmlt-export-media-csv` is free. The library export, its status tool and the two import tools need **Media Library Tools Pro** with an active licence. All are listed on the default server and the dedicated endpoint; the Pro ones only while the licence is active. The selected-media export and the import tools pass CSV as text inside the tool call and its result; the library export writes files and returns download links.

**Free and Pro exports.** The tool descriptions carry this guidance, so a user can simply ask to "export my media library to CSV".
- **Columns:** both exports always include every standard field (`ID`, `slug`, `url`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order`, `groups`), in the same order. The client never asks which columns to include, and narrows them only when the user names specific columns.
- **With Pro** (`tsmlt-export-media-csv-bulk`):
  - The client starts a whole-library export at once, without searching for images or asking for IDs, names, keywords, content, columns, a file count or confirmation. It passes filters only when the user asks for a subset.
  - It follows the export with the status tool (and the continue tool when stalled) without asking the user.
  - The finished files appear in the export view where the client can show it, and as links.
  - The Free tool is used only for specific media the user named.
- **Free only: search and select** (`tsmlt-search-media`, then `tsmlt-export-media-csv` with 1–100 distinct IDs):
  - The client asks which images to include. The user can give image names, file names, keywords, media IDs, or a post, page or WooCommerce product by title, ID or URL (for example "export the images on https://example.com/about-us/").
  - The client finds them with `tsmlt-search-media` (`search`, `filename`, `ids`, `content_id`, `content_title`, `content_url`) and shows the matches with title, file name, date and ID, so images with the same file name can be told apart.
  - The user chooses specific images or all of them. If nothing matches, the client says so and offers another search.
  - The client exports exactly the chosen IDs at once. It never exports images the user didn't choose and never guesses IDs.
  - More than 100 chosen images: the client asks the user to narrow the choice and doesn't split the export.
  - **Content search** (`ContentMedia`, shared with the Pro page and post export) covers media found through what is saved with the content. Every source below is read for every page, post or product, so the user never names the editor. An image found several ways is listed once with every way. Nothing is rendered, no shortcode runs, nothing is fetched, and no usage scan or parent change is involved. Each result's `associations` says how it relates to the content (`attached`, `featured`, `gallery`, `content`, `elementor`, `wpbakery`).
    - **Attached:** media attached to the post, page or product ("Uploaded to").
    - **Featured image** and the **WooCommerce product gallery**.
    - **Gutenberg:** image, gallery (including the image blocks inside it), cover and media-and-text blocks, at any nesting depth (groups, columns and so on), by their attachment ID. Cover, image and media-and-text blocks saved with only an image URL are resolved by that URL. Blocks are parsed in any post content, whatever editor saved it.
    - **Classic editor:** `wp-image-N` classes, `<img>` tags whose `src` is a file of this site's uploads (a sized copy such as `-300x200` counts as its original; the original or a size of a large image WordPress scaled down on upload counts as its `-scaled` file), and `[gallery ids]` and `[gallery include]` shortcodes.
    - **WPBakery:** every element's opening shortcode tag in the stored content, nested ones included, is read with `shortcode_parse_atts()`; shortcodes are never run.
      - `vc_single_image`: `image`, or `custom_src` when its `source` is `external_link`.
      - `vc_gallery` and `vc_images_carousel`: `images`, or the `custom_srcs` URLs (WPBakery's `#E-8_` encoding decoded) when `source` is `external_link`.
      - `vc_row`, `vc_row_inner`, `vc_section`, `vc_column` and `vc_column_inner`: the `parallax_image` ID (rows and sections) and background `url(…)` in the design `css`, whose `?id=N` is used only when that attachment is the file of the URL.
      - URLs are resolved as local uploads only. IDs saved with an external-link source are ignored, as WPBakery ignores them.
      - Not read: other elements (for example media grids, hover boxes, third-party add-ons), images in raw HTML attributes, and Frontend Editor templates.
    - **Elementor:** read from the page's saved `_elementor_data` whenever it has some, whatever `_elementor_edit_mode` says (a page switched back from Elementor still has its old data, so its images are listed too). Elementor needn't be active, nothing is rendered, and no widget code runs.
      - Every media field (`{id, url}`) and list of media fields in any element's settings is used. That covers the Image widget, the Image Gallery and Basic Gallery widgets, section and container backgrounds, and theme or add-on widgets that store images the same way, in nested sections, columns and containers.
      - A saved ID is used only when the attachment is the file of the URL saved with it. That way IDs kept from another site (for example a demo import) are never taken for unrelated local images.
      - Otherwise a URL of this site's uploads is resolved to its attachment, and other URLs are ignored.
      - Data over 5 MB or nested too deeply is skipped.
    - **Limits on URL resolution:** URLs are resolved only on this site's uploads host, port and path, with at most 200 distinct URLs per content.
  - **Not found:**
    - images output by the theme, widgets, shortcodes other than `[gallery]` and the WPBakery elements above, or page builders other than Elementor and WPBakery (for example Divi shortcodes);
    - CSS backgrounds set outside Elementor's media fields;
    - Elementor widgets that store images only as text or HTML, dynamic tags, global widgets and templates (only the page's own data is read);
    - images hosted on other sites, or not in this Media Library.

    For example, the development site's About Us page (2784) is built with Elementor from a demo import. Its saved image IDs and URLs belong to the demo site, so it correctly finds no media there.
  - **Content URLs** (`content_url`): only http(s) URLs on this site's own host and port, under its home path and without credentials, are resolved, locally with WordPress's `url_to_postid()`; the home page resolves to a static front page. A URL is never fetched (no outgoing request, no SSRF). External, unresolvable, trashed, attachment and unreadable URLs are all reported as `tsmlt_content_not_found`.
  - **Content titles:** an exact title is used first, then titles containing it. When several posts, pages or products match, no media is returned and `content_candidates` lists up to 10 for the user to choose from.
  - **Access:** content that is missing, of another type, trashed or not readable is reported alike as `tsmlt_content_not_found`. Media must be readable and not trashed, as in every search, and the export checks every ID again.
  - **Whole-library request:** the client explains that full Media Library CSV export is available in Media Library Tools Pro (the plugin's own Pro link), and that the Free version can search and choose images instead.
- **With Pro, pages and posts** (`tsmlt-export-media-csv-bulk` with `contents`):
  - `contents` lists 1–20 pages, posts or WooCommerce products, each as `url` (of this site), `id` or `title`. Each is resolved on its own with the same rules as `content_url`, `content_id` and `content_title`.
  - Their images are found as above. Nothing starts if one can't be used: the error names each such item (not found, inaccessible or external, or several matching titles), and the client asks the user.
  - **The question:** with two or more, the client asks once, "How would you like the CSV files organized?", offering exactly **Separate CSV files** (one per page or post) or **One combined CSV**. It passes the answer as `organize` and asks nothing else.
    - Without `organize`, the tool refuses with `tsmlt_export_organize_required` and the question.
    - With one page or post, no question is asked and it's exported as one file.
    - A whole-library request never uses `contents`.
  - **Separate:** one file per page or post with images, named after it (`media-export-<date>-<slug>-<id>.csv`). An image used by two pages appears in both files.
  - **Combined:** one file (`media-export-<date>-combined.csv`), each image once.
  - **Status:** the status lists every page and post with its image count and file number. Pages without images get no file and are reported with `file: null`; the export still runs.
  - **Unchanged:** columns and CSV format, the background job, encrypted storage, status, continue, signed links, export view, retention and cleanup. The source page isn't added as a CSV column, which keeps the import format unchanged.
- **Never:** the client never imitates a whole-library export with repeated Free exports and never says the Free version can export the whole library.

### CSV import and export guidelines

If an import fails, try importing smaller batches. Processing performance depends on your server's available resources.

**Accepted column headers** (the admin CSV importer):

`ID, slug, url, rename_to, title, caption, description, alt_text, post_parent, menu_order, groups, custom_meta:_key`

- **groups:** Use comma-separated group names (e.g. `Folder 1, Folder 2`). Groups that do not exist will be created automatically.
- **post_parent:** The ID of the post to which the media is attached. Use `0` to detach it.
- **menu_order:** Sets the media item's sort order.
- **custom_meta:_key:** Imports a custom metadata value using the specified meta key (Pro feature).
- **Empty cells:** Leave existing values unchanged.

**Export compatibility:** CSV exports use the same supported column names and formatting wherever applicable, so exported files can be reused for importing. Import-only columns such as `rename_to` are not included in exports.

**Through MCP**, the same column names and formatting apply, with these differences (details below):

- **Smaller batches are required:** `tsmlt-preview-media-csv-import` takes at most 50 data rows and 256 KB per call; split larger files and preview each part.
- **Accepted columns** are `ID`, `slug`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order` and `groups`. A CSV with `url`, `rename_to` or `custom_meta:*` columns is refused as a whole, naming the column: MCP never creates media, renames files or writes custom fields. To re-import an export, remove the `url` column.
- **groups:** Groups are added to the attachment's current ones. Groups that do not exist are an error unless the preview is run with `create_groups: true`; only then are they created, when the plan is applied.
- **Empty cells** leave existing values unchanged; a cell containing only spaces clears a text field.
- **Nothing changes until applied:** the preview only reports changes; `tsmlt-apply-media-csv-import` applies them, once, after confirmation.
- **Export** (`tsmlt-export-media-csv` and `tsmlt-export-media-csv-bulk`) writes the same column names (never `rename_to` or custom fields) and protects formula-like text with a leading apostrophe, which the MCP import removes again.

### Columns and format (both exports)

Columns, in this default order: `ID`, `slug`, `url`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order`, `groups`; or the ones you choose, in the order given. These are the columns the CSV import reads; `rename_to` and other import-only columns are never written, and neither are custom fields, file paths or attachment metadata. Both exports use the same code (`Modules\Csv\MediaCsv`, Free), so the same attachment gives the same row.

- RFC 4180 CSV in UTF-8 (invalid UTF-8 is removed): commas, quotes and line breaks are quoted; empty values stay empty.
- `post_parent` is the attached post's ID when it is a valid post you may read, otherwise 0. `groups` lists Group names, comma-separated.
- Text cells that begin with `=`, `+`, `-`, `@`, a tab or a carriage return — also after apostrophes, as in `'=x` — get one more leading apostrophe so a spreadsheet does not run them as formulas; the import removes exactly that apostrophe. The number columns `ID`, `post_parent` and `menu_order` are never changed.

### Export selected media — `tsmlt-export-media-csv` (Free)

| Input | Type | Meaning |
|---|---|---|
| `ids` | list of 1–100 different attachment IDs | Rows follow this order |
| `columns` | list | Columns to export, in the order wanted. Default: all |

Output: `csv` (a header row, then one row per exported attachment), `columns`, `rows`, `exported_ids` and `skipped` (`id` and `reason: not_available` for every ID that is missing, not media, trashed, private or not readable by you — reported alike, so nothing is revealed about them). Read-only. The limit of 100 keeps the response a reasonable size for an AI client (100 rows with 2 KB descriptions are about 220 KB); the export itself takes a fixed handful of queries whatever the number of IDs.

### Export the library to files — `tsmlt-export-media-csv-bulk` and `tsmlt-get-media-csv-export` (Pro)

| Input | Type | Meaning |
|---|---|---|
| `filters` | object | `search`, `filename`, `mime_type`, `missing_alt`, `group_id`, `attached` — the same as `tsmlt-search-media`; none for the whole library |
| `columns` | list | Columns to export, in the order wanted. Default: all |
| `files` | integer 1–20, default 1 | Number of CSV files to split the export into |

The export runs in the background through WP-Cron and returns at once with a `job_id`. Call `tsmlt-get-media-csv-export` (read-only) with it every few seconds until `status` is `complete`. WP-Cron only runs when the site gets visits; a real system cron that calls `wp-cron.php` is recommended for reliable background work. Where WP-Cron does not run at all (`stalled` is true), call `tsmlt-continue-media-csv-export` with the `job_id` repeatedly: each call writes one batch and returns `continued`, `busy` (another run holds the export; wait and call again) and `needs_more`. Both paths share one lock per export and its committed sizes, so they never write the same batch twice:

- `status`: `collecting` (finding the matching attachments you may read), `writing`, `complete` or `failed` (`error` says why); `percent`; `stalled` (no progress for two minutes: WP-Cron is not running; use `tsmlt-continue-media-csv-export`).
- `files_requested`, `files_planned` (as requested, but no more files than rows; at least one), `estimated_rows`, `total_rows`, `rows_written`, `skipped` (matches you cannot read), `changed` (attachments deleted, trashed or made unreadable while the export ran — left out), `created_at`, `expires_at`.
- `files`: per file `number`, `name`, `rows`, `bytes`, `complete`, and for finished files a `download_url` valid until `url_expires_at` (15 minutes; call again for a new link — an earlier link keeps its own expiry). Fetch it with a plain HTTP GET; no login is needed, the link itself is the credential.

What to expect:

- Every file starts with the same header. Rows are split as evenly as possible: sizes differ by at most one row, the first files getting the extra rows. Together the files hold every matching attachment once, in ID order; attachments added after the export started are not included.
- One export at a time per user (three per site), and each user keeps at most three exports, finished or not: starting a new one first removes that user's oldest finished or failed export, with its files (running exports are never removed; when none can be removed, the start is refused). Starts are taken one at a time on the site, so two starts at once cannot pass these limits together; a start made while another is being set up is refused with "try again in a moment". Files are kept for 24 hours after the export ends, then deleted — by WP-Cron, and also, at most once an hour, when an administrator opens a WordPress admin page. When an export fails, the files finished before the failure stay available.
- A run killed by a timeout or a fatal error is picked up by a watchdog run about three minutes later, and a batch that fails is retried from the last committed file size (after 30, 60 and 90 seconds); after four attempts of the same batch in a row the export fails. No row is written twice. A run keeps its export's lock for as long as it works: it renews the lock at the start of every batch, every 50 rows and before every write, so a slow batch is never taken over; a run that stopped renewing for over two minutes loses the lock, and finds out at its next renewal, before writing anything more. When the watchdog finds a run still renewing, it checks again later.
- Only the user who started an export sees it, continues it and gets its links; a link stops working when it expires, when the export is deleted, or when that user is no longer an administrator or the Pro licence is inactive.

**Protecting the export files (site administrators).** Files are written to `wp-content/uploads/tsmlt-csv-exports/`, in a folder and under file names that are random and never shown to anyone — not the job ID, and not the download name. Apache honours the `.htaccess` deny rule written there. Nginx (and IIS) ignore `.htaccess`; on Nginx add, inside the site's `server` block:

```nginx
location ^~ /wp-content/uploads/tsmlt-csv-exports/ {
    deny all;
    return 403;
}
```

Downloads still work: they go through `admin-ajax.php`, never through the folder itself. Moving the folder outside the web root is not offered yet; on multi-server sites the background runs and the download requests must all see the same files, as they do with the shared uploads folder.

### Check an import — `tsmlt-preview-media-csv-import`

Input: `csv` (text: a header row and at most 50 data rows, at most 256 KB) and optional `options`: `match` (`id`, the default, or `id_or_slug`) and `create_groups` (default false). Changes nothing.

- **Columns**: `ID`, `slug`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order`, `groups`. Any other column refuses the whole CSV with a message naming it: `url` (export only), `rename_to` (renaming files is not supported here), `custom_meta:*` (custom fields are not supported), `post_parents_id` (use `post_parent`), and unknown names. To re-import an export, leave out the `url` column.
- **Matching**: by `ID`, or with `id_or_slug` by `ID` and then `slug`. The slug only identifies a row; it is never changed. A second row for the same attachment is refused.
- **Values**: empty cells leave a field as it is; a cell with only spaces clears a text field. `post_parent` must be a post an attachment can belong to, or 0 to detach; `menu_order` a whole number. `groups` adds Groups by name (existing Groups are kept); Groups that do not exist are an error unless `create_groups` is true.
- **Result**: per row `row`, `attachment_id`, `status` (`valid`, `unchanged`, `invalid`, `not_found`, `unauthorized`), `fields`, `changes` (each field's `before` and `after`) and `errors`; plus `summary`. When at least one row is `valid`, `plan_id` and `expires_at` describe a plan that can be applied by you, once, for about 15 minutes.

### Apply an import — `tsmlt-apply-media-csv-import`

Input: `plan_id` and `confirm: true` — nothing else; the changes come from the stored plan. Review the preview with the user before applying.

Each row is checked again just before it is written: the attachment must still exist and be editable, and every field the row changes must still hold the value the preview showed. A row changed by someone else since the preview is skipped as `conflict`, never overwritten. Output: per row `row`, `attachment_id`, `status` (`updated`, `unchanged`, `conflict`, `failed`), `applied_fields` and `errors`, plus `summary`. Rows are applied one by one: a failed row does not stop the others, and fields already saved for a failed row are named. A plan cannot be applied a second time, also when some rows failed; preview again instead.

**Round trips**: an export, with the `url` column removed, previews as unchanged. The preview removes exactly the one apostrophe the export added before formula-like text (a genuine leading apostrophe, as in `'=x` or `'Twas`, is kept), and captions keep their line breaks, blank lines, quotes, commas and Unicode; CRLF and lone CR line endings become LF. Titles, alt text and the other fields stay single-line. Exceptions are listed under [Known limitations](#known-limitations).

## Choosing an endpoint

**Recommended setup: one connection to the MCP Adapter default server.** You normally need **only one** of the two configurations below — never both.

Once the MCP Adapter is active, your site has two MCP endpoints:

| Endpoint | URL | Exposes |
|---|---|---|
| **MCP Adapter default server (recommended)** | `https://your-site.com/wp-json/mcp/mcp-adapter-default-server` | Always: every eligible Media Library Tools tool listed directly (Free, and Pro while the licence is active), plus the MCP Adapter's gateway tools, which reach the public abilities of other plugins and WordPress core |
| Media Library Tools (alternative) | `https://your-site.com/wp-json/media-library-tools/mcp` | Only the Media Library Tools tools. Administrators only (`manage_options`) |

There is nothing to turn on: as soon as the MCP Adapter (0.5.0 or newer) is active, the default server lists the Media Library Tools tools. Reconnect your assistant after installing or updating Media Library Tools or activating the Pro licence, so it loads the current tool list.

### What the default server offers

The default server lists the same tools as the dedicated endpoint, with the same dashed names (`tsmlt-search-media`, `tsmlt-export-media-csv`, …), their own descriptions and read-only/destructive hints, and the same permission checks:

- **Free:** all the Free tools in [Available Tools](#available-tools), including `tsmlt-export-media-csv`.
- **Pro (active licence):** all the Pro tools, including the library CSV export (`tsmlt-export-media-csv-bulk`, `tsmlt-get-media-csv-export`, `tsmlt-continue-media-csv-export`) and CSV import.
- **CSV Export MCP App (Pro, active licence):** the export view (`ui://media-library-tools/media-csv-export.html`) is listed as a resource, so clients that support MCP Apps can show it for `tsmlt-get-media-csv-export` results; the view reads the finished files through the view-only tool `tsmlt-read-media-csv-export-file`. Clients without MCP Apps support use the download links from `tsmlt-get-media-csv-export` instead.

Without Pro, or without an active Pro licence, the Free tools stay available; the Pro tools and the export view are not listed, and the Pro abilities refuse every call anyway.

**Other plugins' abilities:** the default server is shared. Depending on the site, its gateway tools (`mcp-adapter-discover-abilities`, `mcp-adapter-get-ability-info`, `mcp-adapter-execute-ability`) can also reach the public abilities of other plugins and WordPress core — on a WooCommerce site, for example, abilities that create, update or delete products. Each of those abilities checks its own permissions, but an assistant connected with an administrator password can use the ones an administrator may run. No Media Library Tools ability is made public: the gateway's execute-ability reaches only `tsmlt/search-media` and `tsmlt/get-media-details`.

The default server exposes **abilities** registered through the WordPress Abilities API, not every REST API route.

### When to use the dedicated endpoint instead

The dedicated endpoint (`media-library-tools/mcp`) stays fully supported. Choose it instead of the default server when:

- you want the assistant to reach **only** Media Library Tools, and nothing from other plugins;
- the default server is turned off on your site, or another plugin removes tools from it;
- your MCP Adapter is older than 0.5.0.

The dedicated endpoint limits exposure to the Media Library Tools abilities:
- It lists only those tools. Calls to any other tool — including the MCP Adapter's own discover/execute gateway tools — are refused with "Tool not found".
- It requires an administrator account (`manage_options`) before a session can even start, so other roles cannot list the tools.
- Each tool still runs every check described under [Access Control](#access-control).

**Configure one, not both.** Connecting both servers shows every media tool twice, and the assistant may switch between them unpredictably.

**Important**: Neither choice changes or disables the default server for other plugins. If nothing on your site needs the default server, a developer can disable it with the MCP Adapter's `mcp_adapter_create_default_server` filter — check with any other integration you use first; the dedicated endpoint keeps working.

## Step-by-Step Setup

### 1. Install the MCP Adapter

1. Go to **Plugins → Add New**
2. Search for **MCP Adapter** (author: WordPress.org)
3. Click **Install Now**, then **Activate**

### 2. Create an Application Password

1. Go to **Users → Profile** for an administrator account
2. Scroll to **Application Passwords**
3. Enter a name such as `Claude Code – laptop` and click **Add New Application Password**
4. Copy the password shown — it is displayed only once

**Tips**:
- Create one password per assistant and per device, so you can revoke one without affecting the others
- Never paste the password into an AI chat; enter it only into the client's configuration
- WordPress only offers Application Passwords over HTTPS, or on sites marked as a local development environment

### 3. Connect your MCP client

Add **one** server: the recommended default server, or the dedicated endpoint as the alternative. You do not need both.

#### Claude Desktop and other clients with a JSON configuration file

These clients connect through the `@automattic/mcp-wordpress-remote` helper, documented by the MCP Adapter. It needs **Node.js** on your computer.

1. Open the client's configuration file. In Claude Desktop: **Settings → Developer → Edit Config**, which opens:
   - macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
   - Windows: `%APPDATA%\Claude\claude_desktop_config.json`
2. Add the server. If the file already has an `mcpServers` section, add only the server entry inside it.

   **The server name is a site-specific key** — the name your client shows for this connection and uses to keep the tools of several sites apart. The **Media → MCP Docs** page fills in the keys for your site: `mlt-`, then the site part built from the site address (`home_url()`), then the endpoint — `-mcp-adapter-default-server` for the default server or `-media-library-tools` for the dedicated endpoint. The examples below use `mlt-your-site-com-…` for a site at `your-site.com`.

   | Site address | Default server key | Dedicated endpoint key |
   |---|---|---|
   | `http://plugindev.local` | `mlt-plugindev-local-mcp-adapter-default-server` | `mlt-plugindev-local-media-library-tools` |
   | `https://example.com` | `mlt-example-com-mcp-adapter-default-server` | `mlt-example-com-media-library-tools` |
   | `https://www.example.com` | `mlt-www-example-com-mcp-adapter-default-server` | `mlt-www-example-com-media-library-tools` |
   | `https://staging.example.com:8443/blog` | `mlt-staging-example-com-8443-blog-mcp-adapter-default-server` | `mlt-staging-example-com-8443-blog-media-library-tools` |

   The site part is the host in lower-case ASCII (international domain names in their `xn--` form; `www` kept), plus a non-default port and a subdirectory install's path, with dots and other separators turned into `-`. Keys are at most 64 characters: when a key would be longer, the site part is cut and ends in an 8-character hash of the address, so two sites do not share a key; the endpoint part is always kept. You can rename a key in your client; developers can change the suggestion with the `tsmlt_mcp_client_server_key` filter (arguments: the key, the endpoint identifier, the site address). The keys only name the connection in your client — the endpoint URLs, credentials, WordPress server IDs and tool names stay the same. Add **one** of the two entries below for each site; the default server is recommended.

> **You only need ONE of these configurations. Choose either the recommended Default MCP Adapter or the Dedicated Media Library Tools endpoint. Do not add both unless you intentionally want separate connections.**

**Option 1: Default WordPress MCP Adapter — Recommended**

- Always provides the Media Library Tools Free abilities, and the Pro abilities while the Pro licence is active, through one connection — nothing to turn on.
- Supports CSV export (`tsmlt-export-media-csv`, and with Pro `tsmlt-export-media-csv-bulk`) and the Pro CSV Export MCP App.
- May also expose other plugins' public abilities, through the MCP Adapter's gateway tools, depending on the site (see [What the default server offers](#what-the-default-server-offers)).

```json
{
  "mcpServers": {
    "mlt-your-site-com-mcp-adapter-default-server": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"
      ],
      "env": {
        "WP_API_URL": "http://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "your application password"
      }
    }
  }
}
```

**Option 2: Dedicated Media Library Tools MCP Server — Alternative**

- Provides isolated Media Library Tools access: only the Media Library Tools tools, administrators only.
- Remains available as a fallback, for example when the default server is turned off or the MCP Adapter is older than 0.5.0 (see [When to use the dedicated endpoint instead](#when-to-use-the-dedicated-endpoint-instead)).

```json
{
  "mcpServers": {
    "mlt-your-site-com-media-library-tools": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"
      ],
      "env": {
        "WP_API_URL": "http://your-site.com/wp-json/media-library-tools/mcp",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "your application password"
      }
    }
  }
}
```

3. Replace `your-site.com` with your site's address (use `https://` on a live site), `admin` with your administrator username, and `your application password` with the Application Password from step 2. Save the file, then fully quit and reopen Claude Desktop.
4. In a new chat, open the tools menu: the Media Library Tools tools should be listed.

The **Media → MCP Docs** page shows both configurations, one after the other, with your site's keys, URL and username already filled in.

#### Claude Code

Run **one** of these in your own terminal (not inside a chat).

Option 1: Default WordPress MCP Adapter — Recommended:

```bash
read -rs WP_APP_PW   # paste the Application Password; nothing is shown
claude mcp add --transport http mlt-your-site-com-mcp-adapter-default-server \
  https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  --header "Authorization: Basic $(printf '%s:%s' 'YOUR_USERNAME' "$WP_APP_PW" | base64 | tr -d '\n')"
unset WP_APP_PW
```

Option 2: Dedicated Media Library Tools MCP Server — Alternative:

```bash
read -rs WP_APP_PW   # paste the Application Password; nothing is shown
claude mcp add --transport http mlt-your-site-com-media-library-tools \
  https://your-site.com/wp-json/media-library-tools/mcp \
  --header "Authorization: Basic $(printf '%s:%s' 'YOUR_USERNAME' "$WP_APP_PW" | base64 | tr -d '\n')"
unset WP_APP_PW
```

Restart Claude Code, then run `claude mcp list` — the server should show **Connected**.

### 4. Try it

Ask your assistant something like:
- "Find images in the media library that have no alt text."
- "Show me the details of attachment 1234."
- "Which camera and settings were used for attachment 1234?"
- "Write alt text for attachment 1234 and save it."

## The default server's gateway tools

Besides the Media Library Tools tools, the default server always offers three **gateway tools** from the MCP Adapter:

| Gateway tool | What it does |
|---|---|
| `mcp-adapter-discover-abilities` | Lists the public abilities on the site, from every plugin |
| `mcp-adapter-get-ability-info` | Shows one ability's description and input fields |
| `mcp-adapter-execute-ability` | Runs an ability by name with the given parameters |

Only the read-only `tsmlt/search-media` and `tsmlt/get-media-details` are public, so only they can be run through the gateway, by their names with a slash:

```json
{
  "ability_name": "tsmlt/search-media",
  "parameters": { "mime_type": "image", "missing_alt": true }
}
```

Call the Media Library Tools tools directly by their dashed names (`tsmlt-search-media`). Every other Media Library Tools ability (writes, trash, EXIF, Groups, usage, CSV and the Pro tools) is offered only as a direct tool, never through execute-ability, so clients keep each tool's own read-only/destructive hints and approval prompts.

### Differences to keep in mind

| | Default server (recommended) | Dedicated endpoint (alternative) |
|---|---|---|
| Media Library Tools tools | All, listed directly (Pro while licensed) | All, listed directly (Pro while licensed) |
| Through execute-ability | Only `tsmlt/search-media` and `tsmlt/get-media-details` | Not available |
| Other plugins' abilities | Reachable through the gateway tools | None |
| Who can connect | Any logged-in user; only administrators see the Media Library Tools tools listed, and only administrators can run them | Administrators only |

## Access Control

On the dedicated endpoint:

| Who | Result |
|---|---|
| Administrators | Can use all the tools |
| Editors, authors, and other roles | Refused before any tool is listed (HTTP 403) |
| Unauthenticated requests (no or invalid Application Password) | Refused (HTTP 401) |

On the MCP Adapter default server: any logged-in user can open a session (the adapter's own rule), but the Media Library Tools tools and the CSV Export MCP App are left out of the tool and resource lists for users without `manage_options`, and calling one is refused by the tool itself. Anonymous requests are refused (HTTP 401).

Every tool call also re-checks `manage_options` — inside the tool itself, so code that calls a tool's callback directly is refused too — and the permission to read (or, for updates, edit; for trash and restore, delete) the specific attachment, on either endpoint. `tsmlt-set-media-groups` also requires permission to assign Groups, `tsmlt-create-media-group` permission to create them, and `tsmlt-update-media-group` permission to edit that Group (by default, administrators and editors have all of these; the tools still require an administrator). `tsmlt-get-media-usage` lists a post only if you can read it. `tsmlt-start-usage-scan` and `tsmlt-get-usage-scan-status` need only `manage_options`. The Pro tools also check the Pro licence on every call. Tools only work with attachments in WordPress's normal `inherit` status; trashed and private attachments are reported as not found — except the trash tools, which work with trashed attachments as described in [Media trash](#media-trash--list-preview-trash-restore).

## Important Notes

### Review before saving
`tsmlt-update-media-metadata` overwrites the current value and cannot be undone. `tsmlt-set-media-groups` can remove Groups from an attachment; its `groups_before` output lists what was there, so the change can be reversed with `replace`. The Pro tool `tsmlt-update-exif-fields` rewrites EXIF data in the image file, and the previous values are not returned. These tools are marked as destructive so clients can ask before running them. `tsmlt-update-media-group` returns `previous_name` so a rename can be reversed. `tsmlt-create-media-group` adds a Group that can only be removed under **Media → Groups**. `tsmlt-start-usage-scan` replaces the previous usage results, so it is also marked destructive — keep your client's tool-approval prompts turned on. `tsmlt-trash-media` is marked destructive too: trashed media stops showing on the site at once and WordPress deletes it permanently after `EMPTY_TRASH_DAYS` days unless it is restored with `tsmlt-restore-media`.

### Revoking access
Delete the Application Password under **Users → Profile → Application Passwords**. The assistant loses access immediately.

### Multisite
Each site in a network has its own endpoints, and access requires `manage_options` on that site. Multisite has not yet been tested.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `404` from `/wp-json/media-library-tools/mcp` | MCP Adapter not active, or WordPress older than 6.9 | Activate the MCP Adapter on WordPress 6.9+ |
| `401 Unauthorized` | Missing or wrong Application Password, or wrong username | Recreate the password and update the client configuration |
| `403 Forbidden` | The account is not an administrator | Use an administrator account |
| "Tool not found" | The tool belongs to another plugin, or is an MCP Adapter gateway tool | Expected — the dedicated endpoint offers only the tools above |
| Default server lists no `tsmlt-` tools | The account is not an administrator, the MCP Adapter is older than 0.5.0, or the client still has the old tool list | Use an administrator account, update the MCP Adapter (or use the dedicated endpoint), then reconnect the client |
| Default server lists no Pro tools or CSV Export view | The Pro licence is not active, or Pro is not installed | Activate the Pro licence; the Free tools stay available |
| `404` from `/wp-json/mcp/mcp-adapter-default-server` | The MCP Adapter's default server is turned off on this site (`mcp_adapter_create_default_server`) | Use the dedicated endpoint `/wp-json/media-library-tools/mcp`, which keeps working |
| Schema warnings in MCP Inspector about `get-media-details` | Some output fields can be `null`, written as a type list (valid JSON Schema 2020-12, which MCP uses by default) | Informational; no action needed |

## Project status, decisions and roadmap

This section is the maintained record of what the MCP integration does, the decisions behind it, and what is planned. Update it when a feature is implemented and verified; a planned item stays planned until code and tests show otherwise. Before starting a roadmap item, agree its scope, dependencies, security and acceptance criteria here.

### Implemented abilities

Free (registered in `AbilitiesInit::get_abilities()`): `tsmlt/search-media`, `tsmlt/get-media-details`, `tsmlt/update-media-metadata`, `tsmlt/get-exif-fields`, `tsmlt/list-media-groups`, `tsmlt/set-media-groups`, `tsmlt/create-media-group`, `tsmlt/update-media-group`, `tsmlt/get-media-usage`, `tsmlt/start-usage-scan`, `tsmlt/get-usage-scan-status`, `tsmlt/list-media-usage`, `tsmlt/find-duplicate-media`, `tsmlt/start-duplicate-scan`, `tsmlt/get-duplicate-scan-status`, `tsmlt/list-trashed-media`, `tsmlt/preview-trash-media`, `tsmlt/trash-media`, `tsmlt/restore-media`, `tsmlt/export-media-csv`, `tsmlt/start-exif-privacy-scan`, `tsmlt/get-exif-privacy-scan-status`, `tsmlt/list-exif-privacy-results`, `tsmlt/preview-media-rename` (24). Pro (registered by `ProAbilitiesInit` in the Pro plugin, offered only with an active licence): `tsmlt/get-exif-caption`, `tsmlt/update-exif-fields`, `tsmlt/export-media-csv-bulk`, `tsmlt/get-media-csv-export`, `tsmlt/continue-media-csv-export`, `tsmlt/preview-media-csv-import`, `tsmlt/apply-media-csv-import`, `tsmlt/preview-gps-removal`, `tsmlt/remove-gps-metadata`, `tsmlt/preview-bulk-exif-edit`, `tsmlt/apply-bulk-exif-edit`, `tsmlt/list-exif-change-logs`, `tsmlt/preview-exif-undo`, `tsmlt/undo-exif-change` (14). Descriptions: [Available Tools](#available-tools).

Removed: `tsmlt/preview-trash-unused-media` and `tsmlt/trash-unused-media` (moving unused media to the trash through MCP was judged too risky, 2026-10-28). That rule — no MCP tool trashes media — was reversed by the project owner on 2026-11-15, on conditions, for the four trash tools above; the removed tools stay removed. The conditions: strict usage (only `unused` under a complete, verified scan; used, unknown, unscanned and unverifiable media refused); the site icon and logo refused; dedicated endpoint only (`meta.mcp.public` false); every trash write blocked when `EMPTY_TRASH_DAYS` is 0 or less; a single-use, user-, ID- and scan-bound confirmation token that expires after 10 minutes; restore without a token; WordPress's own `wp_trash_post()` and `wp_untrash_post()` only. Permanent deletion, emptying the trash, trashing everything unused and bulk rename stay out of scope; no MCP tool deletes media permanently.

### Architecture decisions

- MCP is the channel between an AI client and WordPress; it does not supply an AI model. Clients such as Claude Desktop or Claude Code use their own model to reason and call the tools.
- MCP tools do not depend on the plugin's AI provider settings or on OpenAI, Claude or Gemini API keys (no ability uses them today). A future feature may use server-side AI only with a stated reason.
- Prefer client-side AI workflows on top of secure, deterministic WordPress operations (for example, the client writes alt text and saves it with `tsmlt-update-media-metadata`).
- Free and Pro stay separate: Pro-only code lives in the Pro plugin, which adds its tools through the `tsmlt_mcp_server_abilities` filter and its MCP App views through the `tsmlt_mcp_server_resources` filter (2026-11-16).
- `tsmlt_mcp_server_resources` (`McpServerRegistration::RESOURCES_FILTER`) is narrow on purpose:
  - It receives an empty array and returns ability names. Free registers no resource of its own.
  - Each name must be a registered ability in the `media-library-tools` category that declares an MCP Apps view: `meta.mcp.uri` starting with `ui://` and `meta.mcp.mimeType` `text/html;profile=mcp-app`.
  - Free abilities (tools), unregistered names, other categories, other URI schemes (`https://`, `file://`) and other MIME types are skipped with a `_doing_it_wrong()` notice. A non-array return adds nothing, and duplicates are listed once.
  - Resources change neither the tool list nor the transport permission (`manage_options`). Listing a resource checks no licence: each resource ability must enforce its own permissions, as Pro's do (`manage_options` and an active licence).
  - The validated names are passed to the MCP Adapter's `create_server()` as the server's resources. Tests: `tests/Integration/Mcp/McpServerResourcesTest.php`.
- Every tool requires `manage_options`, checked by the permission callback and again at the start of the tool's own `execute()` (direct calls are refused too), plus the per-attachment or per-Group capability it needs; input schemas are closed; output is allowlisted. Tools never return secrets, absolute server paths or raw internal metadata.
- The MCP Adapter's default gateway is opt-in: `Ability::is_mcp_public()` defaults to false. Only the read-only `tsmlt/search-media` and `tsmlt/get-media-details` opt in; write tools, including `tsmlt/update-media-metadata`, cannot be run through that gateway. (Since 2026-11-18 every tool is also listed by name on the default server; the gateway rule is unchanged.)
- Default server always lists the tools (2026-11-18): the dedicated server's tools and resources are always listed on the MCP Adapter default server as well, so one client configuration reaches every Media Library Tools tool. It is the recommended setup; the dedicated endpoint stays supported as the isolated alternative and as the fallback. An opt-in setting (`mcp_default_server_enabled`, filter `tsmlt_mcp_default_server_enabled`) was added and removed the same day, before any release; a value left in `tsmlt_settings` by it is ignored.
  - `McpServerRegistration::add_to_default_server()` runs on the adapter's `mcp_adapter_default_server_config` filter (priority 20) and appends the names from `get_tool_names()` and `get_resource_names()` — the same validated lists as the dedicated server (registered abilities in the `media-library-tools` category; MCP App views only with a `ui://` URI and the MCP App MIME type), so Pro's tools and the CSV Export view appear only while Pro adds them (active licence). They are **direct tools**, with their own annotations and `_meta` (which the CSV Export MCP App needs), not entries behind execute-ability; names already listed are not added again. No ability's `meta.mcp.public` changes: the gateway still reaches only search and details.
  - Requires MCP Adapter 0.5.0 or newer (`McpAdapter::VERSION`; an adapter without it counts as older), for the tools/resources list filters and `meta.mcp.uri` resources; older adapters are left untouched.
  - The default server's transport admits any logged-in user (`read`), so `hide_tools_from_unauthorized()` and `hide_resources_from_unauthorized()` (on `mcp_adapter_tools_list` / `mcp_adapter_resources_list`, every server) drop every tool and resource of an ability in the `media-library-tools` category for users without `manage_options`, matching tool names as the adapter derives them (including its `mcp_adapter_tool_name` filter). Calls are refused by each ability's own checks either way; the dedicated server's `manage_options` transport check is unchanged.
  - Pro needs no change: it keeps adding its names through `tsmlt_mcp_server_abilities` and `tsmlt_mcp_server_resources`.
  - Limits: the default server is shared with other plugins' public abilities; a filter that runs after priority 20 can still change its lists; a site can disable the default server (`mcp_adapter_create_default_server`), in which case only the dedicated endpoint works. Configuring both servers lists every tool twice.
- Site-specific client keys (2026-11-18): the client snippets on the MCP Docs page suggest one `mcpServers` key per site and endpoint (`McpDocsPage::get_client_server_key( $endpoint )`): `mlt-` + a site part from `home_url()` (lower-case host, IDN in its ASCII form when the intl extension is available, `www` kept, a non-default port, a subdirectory path; other characters collapsed to `-`) + `-mcp-adapter-default-server` or `-media-library-tools`. Keys are at most 64 characters; when longer, or when the site part lost characters, the site part is cut and ends in an 8-character SHA-256 prefix of the normalized address, and the endpoint part is kept. The `tsmlt_mcp_client_server_key` filter (key, endpoint identifier, address) overrides it; values other than 1–64 letters, digits, `-` or `_` are ignored with a `_doing_it_wrong()` notice, because the key is printed into a shell command. The client chooses the key; WordPress only suggests it. Server IDs, routes, ability and tool names are unchanged. Tests: `tests/Integration/Admin/McpClientServerKeyTest.php`.
- A failed write never leaves a silent partial change: `tsmlt/update-media-metadata` restores the fields it saved when the alt text cannot be saved, or reports the fields that remain changed (`tsmlt_partial_update`).
- Usage scans never clear or replace an attachment's existing parent ("Uploaded to").
- Media trash through MCP (`Modules\Trash\MediaTrash`) follows the conditions under [Implemented abilities](#implemented-abilities). Tokens are options named by the token's hash and never rewritten; the request whose `delete_option()` removed the row (core reports success only when its `DELETE` affected it) is the one that used the token, and the payload is read from the database, not a cache. The site filter `tsmlt_mcp_trash_days` can lower the trash days the tools see (0 or less, or a value that is not a whole number, turns MCP trash off) but never raise them. While `wp_trash_post()` runs, `pre_delete_attachment` and `pre_delete_post` refuse to delete that attachment. A source-level test (`tests/Unit/McpTrashNoPermanentDeleteTest.php`) fails if the trash service or any trash ability calls `wp_delete_post()`, `wp_delete_attachment()`, other file or post deletion, raw deletes or SQL, or the trash cleanup. Nothing is transactional across attachments: each is trashed or restored on its own and reported as it ends up. Each trash or restore runs under a per-attachment claim (`Modules\Trash\TrashClaim`, an options row taken with a plain `INSERT`, as `ScanStartLock` does) holding a one-hour lease that its owner renews right before the native call; another request takes it over only after the lease ran out, by an `UPDATE` that must still find the old value, so concurrent MCP requests cannot both change and report one attachment. A liveness check of the owner's database connection (`GET_LOCK`) would need raw SQL, which the project rules do not allow, so a claim left by a request that died blocks that one attachment for up to an hour. Unrecorded usage is unknown while a `tsmlt_scan_post_usage` event is in the `cron` option (read straight from the database, caches untouched), while a rescan is running (`Modules\UsedWhere\PostRescanMarker`: one options row per run, random name, removed by its owner in `finally`, ignored after 30 minutes and pruned by the next run; when it cannot be stored the rescan is not run but scheduled again a minute later), and whenever either cannot be verified or another plugin hooks WordPress's event-storage filters.
- CSV through MCP: exporting selected media (`tsmlt/export-media-csv`) is Free; exporting the whole library to files and CSV import are Pro. This replaces the earlier "no Free CSV tool" decision (2026-11-16). Every CSV tool is on the dedicated endpoint only. Free owns the standard columns and encoding (`Modules\Csv\MediaCsv`), which Pro's library export reuses, so the exports cannot drift apart; Free adds no filter for extra columns, and no MCP export writes custom fields. Import updates existing media only, through a preview and a single-use, user-bound plan applied with explicit confirmation. See [CSV import and export through MCP (Pro)](#csv-import-and-export-through-mcp-pro).

### Usage classification (shared rules)

The Used Where page (Used / Unused / Unknown tabs) and `tsmlt-get-media-usage` use the same rules (`Modules\UsedWhere\UsageClassifier` with `UsageScanStatus`):

- **Used** — at least one usage is recorded, whatever the scan state. Recorded usages stay listed even when the scan's coverage is incomplete.
- **Unused** — no usage is recorded, the last scan completed with verified coverage, and the attachment was uploaded before that scan started.
- **Unknown** — anything else without a recorded usage: no scan, a scan that is running, stalled, cancelled, failed or unfinished, a scan without a coverage record, a coverage gap (`woocommerce_inactive`, `woocommerce_types_not_scanned`), or an upload after the scan started. An empty usage record alone is never proof of non-use.

The Unknown tab explains each image with these reasons, the evidence found in the database (for example, attached to a product or set as a product category image), the scan details, what information is missing, and a next step; reasons are described in [Used Where](09-used-where.md#unknown-tab). Unknown images have no bulk Trash or Delete action. Through MCP the same images have `used: null`, and `tsmlt-preview-trash-media` / `tsmlt-trash-media` refuse them (`usage_unknown`).

### Verification evidence

- **Automated tests** (WordPress test suite with the MCP Adapter): Free integration and unit suites and the Pro suite cover every tool, the classification rules, scan lifecycle, coverage gaps and the Unknown diagnostics.
- **Disposable local site** (2026-10-28, WordPress 7.1.3, WooCommerce 11.2.1, MCP Adapter 0.7.0, on 127.0.0.1): a fresh scan started through `tsmlt-start-usage-scan` completed with verified coverage; the admin endpoint and `tsmlt-get-media-usage` agreed for every attachment after the scan, for an upload after it, for stored legacy / running / stalled / cancelled / failed states, with WooCommerce inactive (product images stayed Used; the product category image became Unknown with its term as evidence) and with products filtered out. The page rendered the counts, the Unknown tab and its details. This run found one diagnostics defect (post types left out by a filter were not listed as not scanned), fixed in `UsageClassifier`.
- **Attached-post tests** (2026-10-31, extended 2026-11-12; 9 tests): with rendered-page checking on (each post's page served by a stand-in HTTP response, and the tests check the pages were fetched), the rescan after a save records the header logo as a rendered usage but leaves it unattached; an image in the post's content or set as its featured image is attached; an image uploaded to another post keeps that post; a full scan and the legacy admin batch scan record the logo as site-wide and attach only from content; captured front-end HTML records rendered usages without attaching; an attachment, a revision or a missing post never becomes a parent. Against the previous code, 6 of these tests fail and 1 errors (the logo or a rendered-only image became attached).
- **Security tests** (2026-10-28): direct `execute()` calls by users without `manage_options` are refused for every Free tool, partial-update rollback and reporting, default gateway opt-in, and attachment parents kept through admin and MCP scans.
- **Disposable local site, security remediation** (2026-10-28, a new site and database; WordPress 7.1.3, WooCommerce 11.2.1, MCP Adapter 0.7.0, PHP 8.3, on 127.0.0.1; deleted afterwards), through the real MCP transport and admin-ajax with cookie login and nonce:
  - Exposure: the dedicated endpoint lists all 11 tools; the default gateway discovers and describes only `tsmlt/search-media` and `tsmlt/get-media-details`; running `tsmlt/update-media-metadata` or `tsmlt/start-usage-scan` there is refused as not exposed and changes nothing.
  - Denial: the dedicated endpoint answers 401 to anonymous requests and 403 to an editor and a subscriber; on the gateway, editors and subscribers get "Permission denied" for search and details; direct `execute()` calls of the six tools are refused for anonymous, subscriber and editor users.
  - Scans: start, a repeated start (`already_running`, same `scan_id`), progress to complete with verified coverage, admin Re-scan, Clear Results, the legacy batch scan and a second MCP scan all kept existing attached posts (an image uploaded to post A and used on post B stayed on A); unattached images used by a post or product got that post; the logo stayed unattached. The admin Used / Unused / Unknown tabs and `tsmlt-get-media-usage` agreed for all 9 attachments after every scan (5 used, 4 unused, 0 unknown).
  - Found: the single-post rescan after a save set the logo's attached post to a post whose rendered page shows the logo. Fixed on 2026-10-31 (attached posts now come only from owning usages) and covered by the attached-post tests above; not re-run on a disposable site.
  - Metadata: success; the alt text failing after other fields saved (fields restored); the restore failing (`tsmlt_partial_update`, message names the fields left changed); a concurrent title edit kept; alt-only failure. MCP clients receive these as a tool error with the message text (HTTP 200); `applied_fields` / `failed_fields` are available only to code that calls the ability directly.
  - Without the MCP Adapter: the abilities still register, the MCP route is absent, and the admin pages and AJAX work.
- **CSV tests** (Pro, 2026-11-12 to 2026-11-13): characterization tests for the admin importer and its AJAX endpoint; regression tests for its hardening; the export, preview and apply tools (limits, cursors, filters, columns, encoding, formula neutralising, unreadable media, headers, matching, duplicates, per-row authorization, parents, Groups, before/after values, no writes in the preview, plan expiry and user binding, confirmation, single use including a request racing an already-claimed plan, conflicts, Group creation and failures, per-row failures); dedicated listing and gateway refusal through the MCP transport. Full Pro suite: 468 tests, 6390 assertions, no skips, on PHP 8.3, 8.4 and 8.5.
- **Disposable local site, CSV tools** (2026-11-13; a new site and database; WordPress 7.1.3, MCP Adapter 0.7.0, Free 2.5.0, Pro 1.6.2, PHP 8.3, on 127.0.0.1; the Pro licence provided by the test suite's licence stand-in; deleted afterwards), through the real MCP transport: export pagination (3 + 3 + 1 rows, no duplicates, last cursor null), header, filters (MIME type, file name, Group, attached), formula neutralising with numbers kept, special characters, no paths, metadata or custom fields, a tampered cursor and `limit` 101 refused; preview of an export with `url` refused naming the column; preview of an edited CSV (valid, missing Group with and without `create_groups`, missing ID, invalid parent) with exact before/after and no changes made; apply (3 rows updated, checked in the database, untouched fields kept), second apply and `confirm: false` refused; a title edited between preview and apply skipped as conflict while another row was applied; no CSV tool discoverable or runnable on the default gateway; an editor refused at the dedicated endpoint; existing tools (search, details, metadata update, Groups, Pro EXIF caption, gateway search) still working. 33 of 33 checks passed. Found: two round-trip differences (the export's apostrophe kept on import, caption line breaks turned into spaces) — fixed afterwards in Pro, see the next entry; the fix was not re-run on a site.
- **CSV round-trip fix** (Pro implementation and tests, 2026-11-13): `CsvRoundTripTest`, 7 tests, 64 assertions (codec reversibility and spreadsheet safety, cells from other sources, export → remove `url` → preview with every row unchanged, multi-line captions previewed and applied exactly, formula values restored on apply, other fields' sanitizing, admin importer unchanged); 4 of them fail against the previous export and preview code. Full Pro suite: 475 tests, 6,454 assertions, no skips or failures, on PHP 8.3, 8.4 and 8.5. PHPCS: no findings in the changed PHP files. PHP syntax checks passed on PHP 8.3, 8.4 and 8.5.
- **CSV export: selected media (Free) and library files (Pro)** (2026-11-16; WordPress test suite with MCP Adapter 0.7.0, PHP 8.3): Free `ExportMediaCsvAbilityTest` (11 tests, 83 assertions: permissions at both levels, closed input, ID order, skipped IDs reported alike, column choice, readable parent or 0, Group names, RFC 4180 and formula protection, invalid UTF-8, nothing beyond the allowlist, fixed query count, dedicated endpoint only). Pro `ExportMediaCsvBulkAbilityTest` (17 tests, 182 assertions: licence and permissions, input, one file byte-identical to the Free export, split files even with the same header and every row once, fewer rows than files, filters and the ID range fixed at the start, unreadable and changed attachments, progress across batches, a half-written batch not duplicated, files kept after a failure, signed links refused when changed, expired, for another file, after the owner lost access or the licence, status for the owner only, one export per user and three per site, job locks, cleanup), `ExportMediaCsvBulkMcpExposureTest` (start, run and download through the MCP transport; both tools refused on the default gateway) and `CsvRoundTripTest` (both exports preview as unchanged in the Pro import). Removing the read check, the formula protection, the parent read check, the link signature check, the cut-back to the committed size, the even split, the owner check, the job lock or the ID range each makes a test fail. Benchmarks: the Free export of 100 / 1,000 IDs takes 5 queries and under 0.1 s (about 220 KB / 2.2 MB of CSV with 2 KB descriptions); the Pro export of 3,000 attachments into 7 files took 0.18 s, 54 queries and one batch run (429 or 428 rows per file, every ID once, in order). Full suites: Free integration 1,009 tests, 6,623 assertions, 5 skipped (adapter-detection tests that need a stand-in for the real MCP Adapter); Free unit 135 tests, 404 assertions; Pro 485 tests, 6,556 assertions. Not run on a disposable or existing site.
- **CSV export hardening** (Pro, 2026-11-16): `CsvExportHardeningTest` (12 tests, 165 assertions: random storage folder and file names, not the job ID or download name, in no tool output or link; watchdog scheduled per run and cleared at the end; a run that died recovered by the watchdog without duplicate rows; a fresh lock blocking the watchdog, WP-Cron and the continue tool alike; batch attempts bounded at four with growing retry delays and a run that keeps dying failing too; a retried batch not duplicating rows; `tsmlt/continue-media-csv-export` committing one batch per call to the same result, refusing other users, editors and unlicensed sites, and leaving finished or failed exports alone; the status tool sending no write query; 15-minute links, refused a second after expiry and served at the expiry second, fresh ones from status; cleanup capped at 20 a run, only through export records, harmless when repeated, throttled hourly from admin requests; the CSV-helper notice and Free 2.5.0 minimum), plus updated `ExportMediaCsvBulkAbilityTest` (17 tests, 185 assertions) and `ExportMediaCsvBulkMcpExposureTest` (the continue tool listed on the dedicated endpoint, refused on the default gateway). Ten mutations (folder or file named after the job or the download name, no watchdog, no attempt cap, an unbounded continue call, no owner check on continue, no cleanup cap, no throttle, hour-long links, events not cleared) each make a test fail. Full suites: Pro 497 tests, 6,730 assertions; Free integration 1,009 tests, 6,626 assertions, 5 skipped; Free unit 135 tests, 404 assertions. Not verified on Nginx, Apache, a real site or a large library.
- **CSV export hardening, phase 2** (Pro, 2026-11-16): `CsvExportPhase2Test` (12 tests, 102 assertions: a fourth export removing the user's oldest finished one with its folder, other users' exports untouched; running exports never removed, and a start refused when no room can be made; five exports in a row leaving three; a held start lock refusing a start and changing nothing, a stale one taken over and released; expired cleanup still removing expired jobs, and a record stored under another job's name ignored; `Referrer-Policy: no-referrer` with the other headers, each once, on served and refused downloads; output buffers cleared, and a buffer that cannot be removed left without a warning (in a separate PHP process); a 6 MB file streamed byte for byte with under 1 MB of peak memory and closed; a batch that "takes" six minutes keeping its lock while another worker, WP-Cron, the watchdog and the continue tool all find it busy, and writing every row once; a run whose lock was taken over appending nothing, saving no progress, scheduling nothing and leaving the new lock alone, the new owner finishing without duplicate rows; the watchdog moving itself later while a run works). Slow batches are simulated by ageing the stored lock time, and competing workers are interleaved in one process, not run in parallel. Twelve mutations (no renewal at all, no renewal between rows, no retention, retention removing running exports, no Referrer-Policy, no start lock, watchdog not moved, the whole file read into memory in either of two ways, buffers removed without the removable check, records not checked against their name, a lost lock handled as an error) each make a test fail. Response headers are checked as `respond()` returns them; the headers PHP actually sends were not checked (the CLI sends none). Not verified on a real site or web server.
- **Plan claim fix** (Pro implementation and tests, 2026-11-13): `ImportPlanStoreTest`, 12 tests, 49 assertions — first claim succeeds and a second fails; a competing claim written just before this request's `INSERT` wins and this request is refused; `add_option()` shown reporting success in that same window; a leftover claim blocks replay; database errors fail closed; expired-claim cleanup; other users' and unknown plans refused alike. Two of them fail against the previous `add_option()` code. PHPUnit runs one request at a time, so the race is reproduced at its critical point rather than with truly parallel requests. Full Pro suite: 482 tests, 6,479 assertions, no skips or failures, on PHP 8.3, 8.4 and 8.5.
- **Media trash tests** (Free, 2026-11-15; WordPress test suite with the MCP Adapter 0.7.0, PHP 8.3): `ListTrashedAndRestoreMediaAbilityTest` (15 tests, 104 assertions), `TrashMediaAbilityTest` (29 tests, 299 assertions), `McpTrashToolsExposureTest` (4 tests, 71 assertions), `PostRescanMarkerTest` (7 tests, 22 assertions) and the source-level `McpTrashNoPermanentDeleteTest` (unit, 3 tests, 31 assertions). They cover permissions at both levels; per-item `read_post` / `delete_post`; closed input; the strict usage rule (used, unused, unknown, no, running, stalled, gapped, legacy and ID-less scans); site icon and logo; trash days of 0, −1 and unusable values (no trash or delete hook runs, the token is kept); the permanent-deletion guard; token checks including a second consumer interleaved at the critical point (simulated in one process, not parallel); scan changes after the preview and during a batch; a mixed batch with all five outcomes; a post edited to show an unused image with its usage rescan still pending (unknown, no token, a token issued before the edit refused; used once the rescan has run) and a rescan scheduled during a batch; a failed WordPress update reported as `update_failed`, not blocked; the per-attachment claim (a lease held long after 60 seconds, an expired lease taken over, renewal, a claim lost before the trash call, restore taking the same claim); pruning more than one batch of expired tokens; a running rescan (marked while it runs, unmarked afterwards and after an exception, not run when it cannot be marked, expired markers ignored and pruned) refusing preview and trash; an unreadable schedule or markers and another cron backend failing closed; saving a post keeping its pending rescan; an admin trashing or restoring during the call reported as `already_trashed` / `not_trashed` / `changed_during_call`; a failed restore keeping its trash records; native trash records, restore and the cache flush; purge dates; the guidance text in every tool description and the server instructions; no delete-related tool or input; dedicated listing, an end-to-end MCP run and default-gateway refusal. Removing the deletion guard, ignoring the token's `delete_option()` result, bypassing the trash-days gate, treating an unusable filter value as usable, adding a `wp_delete_attachment()` call, dropping either pending-rescan check, skipping the claim, reporting every unchanged item as blocked, pruning one batch or newest first, running the rescan unmarked or without removing its marker on an exception, clearing and rescheduling on save, ignoring the running markers, treating an unreadable schedule as nothing pending, ignoring another cron backend, reporting another actor's change as this call's success, not putting back a failed restore's trash records, taking a claim over after 60 seconds, or trashing after a lost claim each makes a test fail. Full Free integration suite: 988 tests, 6,421 assertions, 5 skipped (adapter-detection tests that need a stand-in for the real MCP Adapter); unit suite: 135 tests, 404 assertions. The Pro test `GetExifCaptionMcpExposureTest` still expects the earlier list of dedicated-endpoint tools and fails against this branch (since `tsmlt/find-duplicate-media`); updating it is a Pro change, not made yet. Not run on a disposable or existing site.
- **Default server always lists the tools** (Free, 2026-11-18; WordPress test suite with WordPress 7.1.3, MCP Adapter 0.7.1, PHP 8.5): `McpDefaultServerToolsTest` (13 tests, 130 assertions: no setting, constant, method or filter of the removed opt-in remains, and a value it left in `tsmlt_settings` does not turn the tools off; the hooks and priority; the 0.5.0 version gate; every Free tool, including `tsmlt/export-media-csv`, merged with the gateway tools kept first and nothing listed twice; a stand-in Pro tool and MCP App view added only while the stand-in reports a licence, and another category's ability refused; no change to any `meta.mcp.public`; list hiding for editor, author, subscriber and anonymous users, with array and object records and a tool renamed through `mcp_adapter_tool_name`; end to end on the real default server: an administrator sees every Free tool with its hints and no Pro tool, and runs `tsmlt-export-media-csv`; the dedicated endpoint lists the same `tsmlt-` tools and still runs them; editors, authors and subscribers connect but see no `tsmlt-` tool and are refused export, metadata update and trash calls, with nothing changed; execute-ability still refuses `tsmlt/update-media-metadata`, `tsmlt/export-media-csv` and `tsmlt/trash-media`). `McpDocsPageTest`, `McpClientServerKeyTest` and the unit test of the registration hooks were updated. Full Free integration suite: 1,139 tests, 28 failures, 5 skipped — the same 28 as without these changes (MCP gateway tests that expect MCP Adapter 0.7.0's "not exposed via MCP" wording, which 0.7.1 changed, and `UsedWhereBatchTransactionTest` / `UsedWhereCleanupTest`). Free unit suite: 135 tests, 407 assertions. The real Pro licence path is covered only by the stand-in here; the Pro suite was not run. Not re-run against MCP Adapter 0.7.0, and not verified in a real client or on a site.
- **Development site** (read-only, 2026-10-28): usage results for real product, gallery, variation and category images were checked against WooCommerce data. No fresh scan has been run there, so its last scan still has no coverage record.

### CSV import and export through MCP (Pro)

**Status: implemented** (2026-11-13) in the Pro plugin and verified on a disposable site (see Verification evidence). How to use the tools: [CSV export and import](#csv-export-and-import).

#### Free/Pro boundary

- **Free** owns `tsmlt/export-media-csv` (selected IDs, CSV text) and the standard columns and encoding (`MediaCsv`). Single items are also covered by `tsmlt-get-media-details`, `tsmlt-update-media-metadata` and `tsmlt-set-media-groups`. The Free media table's **Export CSV** bulk action (selected rows of the current page, built in the browser) is unchanged, as is the Pro gating of the dedicated Export and Import pages.
- **Pro** owns the library export to files and CSV import through MCP: `ExportMediaCsvBulk`, `GetMediaCsvExport`, `ContinueMediaCsvExport` (with `Modules\Export\CsvExportJob`, `CsvExportQuery` and `CsvExportDownload`), `PreviewMediaCsvImport` and `ApplyMediaCsvImport`, registered by `ProAbilitiesInit` like the EXIF tools. The three export tools are registered only when the installed Free plugin provides `MediaCsv`; Pro requires Free 2.5.0 (`Dependencies::MINIMUM_TSMLT_VERSION`).

#### Abilities

The Pro tools below and the Free `tsmlt/export-media-csv` are listed by name on the MCP Adapter's default server and on the dedicated endpoint (`/wp-json/media-library-tools/mcp`), but cannot be run through the default server's generic gateway tool (`meta.mcp.public` false, `show_in_rest` false).

| Ability | Purpose | Input | Output |
|---|---|---|---|
| `tsmlt/export-media-csv-bulk` | Changes no media. Starts a background export to CSV files. | `filters` (the `tsmlt/search-media` filters), `columns` (from the allowlist), `files` (1–20) | The export job (as below) |
| `tsmlt/get-media-csv-export` | Read-only. An export job's progress and signed download links (15 minutes). | `job_id` | `status`, `percent`, `stalled`, counts, `files` with `download_url` / `url_expires_at` |
| `tsmlt/continue-media-csv-export` | Changes no media. Runs one batch of an export (fallback when WP-Cron does not run). | `job_id` | `status`, `percent`, `continued`, `busy`, `needs_more`, `rows_written`, `total_rows`, `error` |
| `tsmlt/preview-media-csv-import` | Changes no media. Validates a CSV and shows per-row changes; stores a plan when some row is valid. | `csv` (at most 256 KB and 50 data rows), `options`: `match` (`id` or `id_or_slug`), `create_groups` (default false) | `plan_id`, `expires_at`, per-row `row`, `attachment_id`, `status`, `fields`, `changes`, `errors`; `summary` |
| `tsmlt/apply-media-csv-import` | Write. Applies a previewed plan once. | `plan_id`, `confirm: true` | Per-row `row`, `attachment_id`, `status` (`updated`, `unchanged`, `conflict`, `failed`), `applied_fields`, `errors`; `summary` |

#### CSV columns and data exposure

- Fixed column allowlists in code:
  - Export: `ID`, `slug`, `url`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order`, `groups` (comma-separated Group names).
  - Import: `ID` and `slug` identify the attachment (the slug is never written); `title`, `alt_text`, `caption`, `description`, `post_parent` (a valid post, or 0 to detach), `menu_order` and `groups` can be written. `url`, `rename_to`, `custom_meta:*`, `post_parents_id` and unknown columns refuse the CSV with a message naming them.
- No custom fields are exported or imported through MCP. Output never contains absolute server paths, raw attachment metadata or custom fields: export values come from `MediaPresenter` plus the slug, menu order and Group names.
- No ability accepts a file path or a URL. The selected-media export and the import tools pass CSV as text. The library export writes its files under `uploads/tsmlt-csv-exports/<random folder>/<random name>.csv` — the folder and file names are random and never returned (neither is the ID list file), so a leaked job ID or link does not give a direct path — behind an `index.php` and a `Require all denied` rule (Apache; Nginx needs the rule under [CSV export and import](#csv-export-and-import)). Files are served only through admin-ajax links signed with HMAC-SHA256 (a per-job random secret plus the site's auth salt, over the job, the file number, the expiry and the owner's user ID), valid for 15 minutes; a refused link gets the same answer whatever the reason; every answer is sent with `Cache-Control: no-store, private`, `X-Content-Type-Options: nosniff`, `X-Robots-Tag: noindex` and `Referrer-Policy: no-referrer` (each replacing any value WordPress already set), and a file is streamed in 64 KB chunks after any open output buffers are cleared; only finished files of the job are served, after a `realpath` check that keeps them inside the job's folder. Batches run as the owner, who must still be an administrator with an active licence. Each run schedules a watchdog first; each batch is an attempt recorded before it starts, retried from the last committed byte size (cut back first, so no duplicate rows), at most four in a row; one lock per job is shared by WP-Cron, the watchdog and `tsmlt/continue-media-csv-export`, renewed by its holder while it works and checked before every write, so two runs never write the same batch. Each user keeps at most three jobs (the oldest finished or failed one is removed when a new one starts; starts are serialised by a site-wide start lock). Expired jobs are deleted with their files, at most 20 per cleanup run, only through their own records, stored under their own job ID (never by scanning the folder), by WP-Cron and hourly from admin requests.
- Formula injection (`CsvCellCodec`, Pro): a text cell is protected when it starts with `=`, `+`, `-`, `@`, a tab or a carriage return, optionally after apostrophes; protection adds one apostrophe. The MCP preview removes exactly one apostrophe from a cell matching that protected pattern and changes nothing else, so every value the export writes is read back as it was (`=SUM(1)` → `'=SUM(1)` → `=SUM(1)`; `'=x` → `''=x` → `'=x`; `'Twas` is never changed). The number columns `ID`, `post_parent` and `menu_order` are not protected. The CSV columns and the tools' inputs and outputs are unchanged. The admin CSV export does not protect cells; changing it is a separate follow-up.
- Export pages follow attachment IDs. The cursor holds the last ID, signed (HMAC with the site's salt) and bound to the filters; a page examines at most 1000 matches. Attachments the user may not read are left out and counted in `skipped`.

#### Import: preview, then apply

- Update only: no create-from-URL and no `rename_to`; renaming files stays a separate Pro feature. Groups that do not exist are created only when the preview was made with `create_groups: true`.
- The preview (`ImportPreviewService`) only reads. It parses RFC 4180 CSV (BOM and CRLF accepted, UTF-8 required), checks the header, matches rows (a second row for the same attachment is refused), checks `edit_post` (`unauthorized` otherwise), validates values by the admin importer's rules (`ImportRowService`), and reports per-row before/after values and errors. It undoes the export's formula protection (`CsvCellCodec`) and keeps caption line breaks: captions use `sanitize_textarea_field()` with CRLF and lone CR turned into LF, and a stored caption with CRLF counts as unchanged against the same text with LF. Other fields keep their single-line sanitizing. Apply writes the validated value stored in the plan.
- When at least one row is valid, the operations are stored as a plan (`ImportPlanStore`): a transient named by a random 128-bit ID, for about 15 minutes, bound to the user, holding the validated operations, each target's values at preview time and a hash of the CSV — never the CSV or file paths. A missing, malformed, expired, used or other user's plan is answered the same way.
- Apply requires `confirm: true` and checks the licence and `manage_options` before touching the plan (a refused request does not use it up). It then loads the plan (owner and expiry checked), claims it, and deletes the stored plan. The claim is a plain `INSERT` of a claim row into the options table: the unique `option_name` key lets only one insert succeed, and the claim counts only when exactly one row was inserted, so a repeated or concurrent request cannot apply the plan again. A failed insert (a duplicate or any other database error) refuses the claim. Whether a plan is already claimed is read from the database, not the object cache, and a failed lookup counts as claimed, so a claim also stops a replay when deleting the stored plan failed. (`add_option()` is not used: it checks for the option and then runs `INSERT ... ON DUPLICATE KEY UPDATE`, which can report a second claim as successful.) Each row is then checked again (`ImportApplyService`): the attachment exists and is editable, Group permissions (`assign_terms`, and `edit_terms` plus the plan's `create_groups` for new Groups), and every changed field still holds its preview value — otherwise the row is a `conflict` and is not written. A Group created meanwhile is reused rather than created twice.
- Results are per row with a summary. Rows are applied one by one, without a transaction across rows; a failed row reports the fields it already saved, and the plan stays used.

#### Limits

- Export: at most 100 rows per call (schema and code).
- Preview: at most 256 KB of CSV and 50 data rows. The schema limits the characters; the code limits the bytes and the rows.

#### Security and permissions

- Free's `AbilityGuard` and the Pro ability pattern; `tsmlt()->has_pro()` and `manage_options` in the permission callback and again in `execute()`, which also validates its input again.
- Preview and apply check `edit_post` on each attachment; export checks `read_post` on each row.
- Closed input schemas at every level; bounded payloads; no paths, URLs or uploads accepted.

#### Admin importer hardening (Pro)

- The admin importer (`ImportApi::import_attachment_one_by_one()`) now runs on the shared `ImportRowService`, protected by characterization tests, with two hardening changes: an `edit_post` check per row in addition to `upload_files` (a refused row is answered like a missing one), and no writes to WordPress-managed (`_wp_*`) or internal (`_edit_lock`, `_edit_last`, `_thumbnail_id`, `_original_file_url`, `_tsmlt_image_usages`) meta keys through `custom_meta:` columns. The round-trip changes above are MCP-only: the admin importer removes no apostrophes and still turns caption line breaks into spaces.

#### Known limitations

- A cell from a CSV made elsewhere that starts with an apostrophe directly followed by `=`, `+`, `-`, `@`, a tab or a carriage return loses that one apostrophe on import, which is what the apostrophe means to a spreadsheet.
- An export made before the round-trip fix (2026-11-13) wrote a genuine value such as `'=x` unchanged; re-importing that file reads it as `=x`.
- Text that starts with a tab or a carriage return does not round-trip: the fields' sanitizing trims leading whitespace.
- For a row that adds Groups, Group membership is compared as a whole: any Group added to or removed from that attachment after the preview makes the row a conflict, even when the Groups the row adds are unaffected.

#### Implementation sequence

1. Architecture documented and approved — 2026-11-12.
2. Characterization tests for the Pro `ImportApi` — done.
3. Shared row service extracted and the admin importer hardened — done.
4. `tsmlt/export-media-csv-bulk` — done.
5. `tsmlt/preview-media-csv-import` — done.
6. `tsmlt/apply-media-csv-import` — done.
7. End-to-end verification on a disposable site and this document updated — 2026-11-13.

### Roadmap

| # | Task | Status | Scope and completion criteria |
|---|---|---|---|
| 1 | Fresh scan and end-to-end verification | In Progress | Verified on a disposable local site (see above); the diagnostics fix found there is committed. Remaining: a fresh scan on a development site, with permission. |
| 2 | Security and permission audit | Implemented | Audit (2026-10-28) found no critical or high issues. Fixed, covered by tests and verified on a disposable local site: scans no longer clear or replace attachments' parents (M1); `manage_options` re-checked inside every Free tool's `execute()` (L1); partial metadata updates restored or reported (L2); default gateway exposure is opt-in and `update-media-metadata` moved to the not runnable through the default gateway (L3). The M1 follow-up found on the disposable site (an attached post set from a rendered-page match during the rescan after a save) is fixed and covered by automated tests (2026-10-31), not re-run on a site. Not tested on an existing site. |
| 3 | Bulk media rename | In Progress (preview) | Preview implemented 2026-11-18: `tsmlt/preview-media-rename` (Free, read-only; typed names, 1–20 items; the Rename page's rules through `Fns::rename_target_name()`). Renaming itself is not available through MCP. Before adding it, decide how to handle the existing rename's gaps: size files deleted before the rename, the scaled-upload original and backup sizes left with old names, links updated only in post content, excerpts and Elementor data. Pro strategies stay Pro. |
| 4 | AI-assisted metadata generation | Planned | The client's own AI writes titles, alt text, captions and descriptions, saved through existing tools; no WordPress AI provider setup required. Decide what, if anything, needs a new tool. |
| 5 | Duplicate media management | Implemented (detection and scan control) | Scan control (2026-11-18): `tsmlt/start-duplicate-scan` and `tsmlt/get-duplicate-scan-status` (Free, not runnable through the default gateway) start, resume and monitor the existing duplicate scan under a start lock shared with the Duplicates page; see [Scan, status, results](#scan-status-results). Merging duplicates (Pro) is not offered over MCP. `tsmlt/find-duplicate-media` (Free, not runnable through the default gateway) lists duplicate groups from the duplicate scan's results without its stale-row cleanup on read: groups count distinct attachments still in the library, pages are ordered deterministically, and usage follows `tsmlt/get-media-usage`; see [Duplicate media](#duplicate-media--tsmlt-find-duplicate-media). Covered by integration tests. Any merge or removal through MCP needs its own safety design and is not planned. Not tested on an existing site. |
| 6 | Media library audit | Planned | Diagnostics such as missing alt text, missing files or metadata, and other library checks, read-only. |
| 7 | EXIF tools expansion | Implemented (partly) | 2026-11-18. Free: `tsmlt/start-exif-privacy-scan`, `tsmlt/get-exif-privacy-scan-status`, `tsmlt/list-exif-privacy-results` (the EXIF Data page's scan now records per-image privacy results). Pro: GPS removal (`tsmlt/preview-gps-removal`, `tsmlt/remove-gps-metadata`), bulk EXIF edit (`tsmlt/preview-bulk-exif-edit`, `tsmlt/apply-bulk-exif-edit`), change logs (`tsmlt/list-exif-change-logs`) and undo (`tsmlt/preview-exif-undo`, `tsmlt/undo-exif-change`), each change after a preview and a one-time confirmed plan. Planned, pending changes to the Pro services: EXIF export and import, EXIF ↔ WordPress sync, full EXIF removal — see [EXIF features not available through MCP](#exif-features-not-available-through-mcp). |
| 8 | CSV import/export | Implemented | Free `tsmlt/export-media-csv` (selected IDs); Pro `tsmlt/export-media-csv-bulk` (library export to files, 2026-11-16; production hardening the same day: random storage names, watchdog and retries, `tsmlt/continue-media-csv-export` for sites without WP-Cron, 15-minute links, admin-request cleanup, Free 2.5.0 required), `tsmlt/get-media-csv-export`, `tsmlt/continue-media-csv-export`, `tsmlt/preview-media-csv-import` and `tsmlt/apply-media-csv-import`; all on the dedicated endpoint. All seven steps done; covered by the Pro suite and verified on a disposable site (2026-11-13); see [CSV import and export through MCP (Pro)](#csv-import-and-export-through-mcp-pro). Round trips fixed in Pro (2026-11-13; remaining exceptions under Known limitations). Open: neutralising formulas in the admin CSV export. Not tested on an existing site. Page and post exports (2026-11-17): Free `tsmlt/search-media` `content_url` (a URL of this site, resolved locally, never fetched) for the search-and-select export; Pro `tsmlt/export-media-csv-bulk` `contents` with `organize` (separate files per page or post, or one combined file without duplicate rows). Image discovery covers attached, featured, product gallery and core block, `wp-image` and `[gallery]` references only. |
| 9 | Release readiness | Planned | Documentation, compatibility (WordPress, MCP Adapter, Pro licence states), packaging, permissions and test coverage checked before release. |
| 10 | Media trash management | Implemented | `tsmlt/list-trashed-media`, `tsmlt/preview-trash-media`, `tsmlt/trash-media` and `tsmlt/restore-media` (Free, not runnable through the default gateway), under the conditions recorded in [Implemented abilities](#implemented-abilities); see [Media trash](#media-trash--list-preview-trash-restore). Covered by integration tests (see Verification evidence). Permanent deletion, Empty Trash, Trash All and bulk rename are out of scope. Not tested on a disposable or existing site. |
| 11 | Media usage results listing | Implemented | `tsmlt/list-media-usage` (Free, 2026-11-18, not runnable through the default gateway): pages of used, unused or unknown media after a completed Used Where scan, with the Used Where page's lists and `tsmlt/get-media-usage`'s per-item rule; read-only; see [List media by usage](#list-media-by-usage--tsmlt-list-media-usage). |
| 12 | Single connection through the default server | Implemented | 2026-11-18. Every eligible Media Library Tools tool and the CSV Export MCP App are always listed directly on the MCP Adapter default server, through `mcp_adapter_default_server_config` (no setting; the same-day opt-in was removed); Pro unchanged; no ability made public; tools hidden from users without `manage_options`; dedicated endpoint kept as the alternative and fallback. Covered by `McpDefaultServerToolsTest` (see Verification evidence). Remaining: verify in Claude Desktop / Claude Code against a real site, with Pro licensed, including the CSV Export MCP App; re-run against MCP Adapter 0.7.0. |
