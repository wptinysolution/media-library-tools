# MCP Integration (AI Assistants)

Media Library Tools lets AI assistants that speak the Model Context Protocol (MCP) search your media library, read media details and camera EXIF fields, find where media is used, update media text fields, and organise media into Groups on your behalf. With Media Library Tools Pro it can also build captions from EXIF data, edit EXIF fields, and export and import media text fields as CSV. During development it has been used from Claude Code, and every tool is covered by integration tests that call it through the MCP Adapter. Project status, decisions and the roadmap are at the end of this page ([Project status, decisions and roadmap](#project-status-decisions-and-roadmap)).

## Overview

The integration lets an AI assistant:
- Search the media library (by text, file name, MIME type, Group, attached or unattached, or attachments missing alt text)
- Read the details of one attachment
- Find where media is used on the site (featured images, content, page builders, custom fields, logo, menus, widgets), from the Used Where scan — and start that scan and follow its progress, for a complete used/unused audit
- Read an image's camera, lens and exposure EXIF fields (make, model, lens, software, dates taken and digitized, ISO, aperture, shutter speed, focal length, exposure codes, flash, description)
- Update an attachment's title, alt text, caption, and description
- List, create, and rename media Groups, and change which Groups an attachment belongs to
- **Pro:** build a caption from an image's EXIF data, and set or clear EXIF fields of a JPEG image (see [Pro: EXIF tools](#pro-exif-tools))
- **Pro:** export media as CSV, and check and then apply a CSV that updates existing media (see [Pro: CSV export and import](#pro-csv-export-and-import))

The Free tools never change EXIF data or image files. Only the Pro tool `tsmlt-update-exif-fields` changes EXIF data, inside JPEG files. No tool can rename, move, upload, or delete files, or move them to the trash, or delete or move Groups;  `tsmlt-create-media-group` adds Groups and `tsmlt-update-media-group` renames them. Connected to the recommended dedicated endpoint, an assistant cannot reach anything outside these tools.

**Requirements**:
- WordPress **6.9 or newer** (the Abilities API is part of WordPress core from 6.9)
- The official **MCP Adapter** plugin from [wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/) — tested with **0.7.0**. The dedicated endpoint uses the adapter's documented `create_server()` API, whose signature is unchanged since 0.3.0, but versions before 0.7.0 have not been tested
- A WordPress **administrator** account (the `manage_options` capability)

Without the MCP Adapter, or on WordPress older than 6.9, nothing is registered and the plugin behaves exactly as before.

## Available Tools

### Free (11)

| Tool | What it does | Changes data |
|---|---|---|
| `tsmlt-search-media` | Lists matching attachments, one page at a time (up to 50 per page). Optional `filename` matches part of the file name; `group_id` limits results to one Group; `attached` to media attached, or not attached, to a post | No |
| `tsmlt-get-media-details` | Returns one attachment's title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, image sizes, attached post, and upload date | No |
| `tsmlt-get-exif-fields` | Returns one image's camera make and model, lens make and model, software, date taken and date digitized, ISO, aperture, shutter speed, focal length (mm), the exposure program, exposure mode, metering mode, white balance and flash codes defined by the EXIF standard, and the image description — read from the JPEG file, or from values saved in the EXIF editor for any image type. A field without a value is `null`. Dedicated endpoint only | No |
| `tsmlt-get-media-usage` | Reports where up to 50 attachments are used — featured images, content and excerpts, page builders, galleries and custom fields, and site-wide (logo, site icon, header or background image, menus, widgets, term and site options) — from the records of the Used Where scan. Says whether each one is used, not used, or unknown. Dedicated endpoint only | No |
| `tsmlt-start-usage-scan` | Starts the Used Where scan in the background, or reports the scan already running (a stalled one is resumed). A new scan replaces the previous usage results while it runs. Dedicated endpoint only | Yes (usage scan results only) |
| `tsmlt-get-usage-scan-status` | Returns the Used Where scan state, progress, and whether it is stalled or failed. Never starts or resumes a scan. Dedicated endpoint only | No |
| `tsmlt-update-media-metadata` | Changes only the fields you send: title, alt text, caption, description. An empty string clears a field. Dedicated endpoint only | Yes |
| `tsmlt-list-media-groups` | Lists media Groups with their ID, name, parent Group, and number of media items; filter by name or ID. Dedicated endpoint only | No |
| `tsmlt-set-media-groups` | Adds, removes, or replaces the Groups of one attachment. Dedicated endpoint only | Yes (Group assignments only) |
| `tsmlt-create-media-group` | Creates one Group, at the top level or under an existing Group. Dedicated endpoint only | Yes (adds a Group) |
| `tsmlt-update-media-group` | Renames one Group; its ID, slug, parent and media stay the same. Dedicated endpoint only | Yes (Group name) |

### Pro (5)

| Tool | What it does | Changes data |
|---|---|---|
| `tsmlt-get-exif-caption` | **Pro.** Builds a caption from one image's camera make and model and exposure (aperture, shutter speed, ISO) and returns it without saving. Dedicated endpoint only | No |
| `tsmlt-update-exif-fields` | **Pro.** Sets or clears EXIF fields of one image: camera, lens, exposure, dates, artist, copyright, description. Edits JPEG files; other image types keep the values in the plugin's database only. Dedicated endpoint only | Yes (EXIF data, in the JPEG file) |
| `tsmlt-export-media-csv-bulk` | **Pro.** Returns one page of media (up to 100 rows) as CSV text, in attachment ID order, with the `tsmlt-search-media` filters and a cursor for the next page. Dedicated endpoint only | No |
| `tsmlt-preview-media-csv-import` | **Pro.** Checks CSV text (up to 256 KB and 50 rows) and shows, row by row, what it would change on existing media; returns a plan to apply. Dedicated endpoint only | No (stores a short-lived plan) |
| `tsmlt-apply-media-csv-import` | **Pro.** Applies a previewed plan once, with `confirm: true`; rows changed since the preview are skipped. Dedicated endpoint only | Yes (title, alt text, caption, description, attached post, menu order, Groups) |

The five Pro tools are offered only when Media Library Tools Pro is active with a valid licence.

**What is never returned**: server file paths, raw attachment metadata, raw EXIF data, GPS location (or whether an image has one), copyright, artist, camera serial numbers, or custom fields. `tsmlt-get-exif-fields` returns only the fields listed above — never time zone offsets, altitude, location, copyright or artist — and `tsmlt-get-exif-caption` returns only the caption text.

**What is never changed**: the file's name or URL, or any attachment other than the one a tool is given. A usage scan (`tsmlt-start-usage-scan`) never changes an existing attached post ("Uploaded to") and sets one only for an image that has none; the only tool that changes an attached post on request is the Pro CSV import, through its `post_parent` column, after a preview and an explicit confirmation. `tsmlt-set-media-groups` changes only the attachment's Group assignments. The image file itself is changed only by the Pro tool `tsmlt-update-exif-fields`, which edits EXIF data inside JPEG files.

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

## Pro: EXIF tools

These tools need **Media Library Tools Pro** with an active licence. Without one they are not listed, and a call is refused. Both are offered only on the dedicated endpoint.

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

## Pro: CSV export and import

These tools need **Media Library Tools Pro** with an active licence and are offered only on the dedicated endpoint. CSV travels as text inside the tool call and its result: no file is uploaded, downloaded or written.

### Export — `tsmlt-export-media-csv-bulk`

| Input | Type | Meaning |
|---|---|---|
| `limit` | integer 1–100, default 50 | Rows per page |
| `cursor` | string | `next_cursor` from the previous page; it only works with the same filters |
| `filters` | object | `search`, `filename`, `mime_type`, `missing_alt`, `group_id`, `attached` — the same as `tsmlt-search-media` |
| `columns` | list | Any of `ID`, `slug`, `url`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order`, `groups`, in the order wanted. Default: all |

Output: `csv` (a header row, then one row per attachment, UTF-8), `columns`, `rows`, `skipped` (matches you may not read; they are left out), `total` (all matches, or `null` with a `filename` filter) and `next_cursor` (`null` on the last page). Rows follow attachment IDs, so pages neither repeat nor skip rows. `post_parent` is 0 when the attached post is not a valid, readable post; `groups` lists Group names, comma-separated.

Text cells that begin with `=`, `+`, `-`, `@`, a tab or a carriage return — also after apostrophes, as in `'=x` — get one more leading apostrophe so a spreadsheet does not run them as formulas. The number columns `ID`, `post_parent` and `menu_order` are not changed. Custom fields, file paths and the attachment metadata are never exported.

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

## Two Endpoints — Use the Dedicated One

**Recommended setup: connect your assistant to the dedicated Media Library Tools endpoint.**

Once the MCP Adapter is active, your site has two MCP endpoints:

| Endpoint | URL | Exposes |
|---|---|---|
| **Media Library Tools (recommended)** | `https://your-site.com/wp-json/media-library-tools/mcp` | Only the Media Library Tools tools above. Administrators only (`manage_options`) |
| MCP Adapter default server | `https://your-site.com/wp-json/mcp/mcp-adapter-default-server` | Every public ability from **every** plugin on the site, including abilities that change or delete data |

### Which endpoint should I use?

Pick one:
- **Only Media Library Tools:** use `media-library-tools/mcp`. It's simpler and narrower.
- **One connection for everything** (media plus other plugins' and WordPress core abilities, such as WooCommerce): use `mcp-adapter-default-server` by itself. It already includes the Media Library Tools abilities.

**Avoid configuring both:** the media tools would appear twice, and the assistant may switch between them unpredictably.

The default server exposes **abilities** registered through the WordPress Abilities API, not every REST API route.

### Why the dedicated endpoint is safer

The MCP Adapter's default server is a gateway: its *discover* and *execute* tools reach every ability any plugin registers as public — not only Media Library Tools. Other plugins' abilities can be write-capable: on a WooCommerce site, for example, they include abilities that create, update, or delete products and change order status. An assistant connected to the default server with an administrator password can use all of them. Any logged-in user can also list those abilities and their input schemas there, although each ability still checks its own permissions before it runs.

The dedicated endpoint limits exposure to the Media Library Tools abilities:
- It lists only those tools. Calls to any other tool — including the MCP Adapter's own discover/execute gateway tools — are refused with "Tool not found".
- It requires an administrator account (`manage_options`) before a session can even start, so other roles cannot list the tools.
- Each tool still runs every check described under [Access Control](#access-control).

**Important**: The dedicated endpoint does not change or disable the default server. Media Library Tools' read-only search and details tools also remain available there, alongside other plugins' abilities. If nothing on your site needs the default server, a developer can disable it with the MCP Adapter's `mcp_adapter_create_default_server` filter — check with any other integration you use first.

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

#### Claude Code

Run this in your own terminal (not inside a chat):

```bash
read -rs WP_APP_PW   # paste the Application Password; nothing is shown
claude mcp add --transport http media-library-tools \
  https://your-site.com/wp-json/media-library-tools/mcp \
  --header "Authorization: Basic $(printf '%s:%s' 'YOUR_USERNAME' "$WP_APP_PW" | base64 | tr -d '\n')"
unset WP_APP_PW
```

Restart Claude Code, then run `claude mcp list` — the server should show **Connected**.

#### Claude Desktop and other clients with a JSON configuration file

These clients connect through the `@automattic/mcp-wordpress-remote` helper, documented by the MCP Adapter. It needs **Node.js** on your computer.

1. Open the client's configuration file. In Claude Desktop: **Settings → Developer → Edit Config**, which opens:
   - macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
   - Windows: `%APPDATA%\Claude\claude_desktop_config.json`
2. Add the server. If the file already has an `mcpServers` section, add only the `media-library-tools` entry inside it:

```json
{
  "mcpServers": {
    "media-library-tools": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://your-site.com/wp-json/media-library-tools/mcp",
        "WP_API_USERNAME": "YOUR_USERNAME",
        "WP_API_PASSWORD": "your application password"
      }
    }
  }
}
```

3. Put in your Application Password, save the file, then fully quit and reopen Claude Desktop.
4. In a new chat, open the tools menu: the Media Library Tools tools should be listed.

The **Media → MCP Docs** page shows this configuration with your site URL and username already filled in.

### 4. Try it

Ask your assistant something like:
- "Find images in the media library that have no alt text."
- "Show me the details of attachment 1234."
- "Which camera and settings were used for attachment 1234?"
- "Write alt text for attachment 1234 and save it."

## Using the MCP Adapter Default Server (optional)

The read-only search and details tools can also be reached through the MCP Adapter's default server (`tsmlt-update-media-metadata`, `tsmlt-get-exif-fields`, `tsmlt-get-media-usage`, `tsmlt-start-usage-scan`, `tsmlt-get-usage-scan-status`, `tsmlt-list-media-groups`, `tsmlt-set-media-groups`, `tsmlt-create-media-group`, `tsmlt-update-media-group` and the Pro tools `tsmlt-get-exif-caption` and `tsmlt-update-exif-fields` are offered only on the dedicated endpoint):

```
https://your-site.com/wp-json/mcp/mcp-adapter-default-server
```

Prefer the dedicated endpoint — see [Two Endpoints](#two-endpoints--use-the-dedicated-one). Use the default server only if a client needs it.

### How it works

The default server does not list the Media Library Tools tools directly. It offers three **gateway tools**:

| Gateway tool | What it does |
|---|---|
| `mcp-adapter-discover-abilities` | Lists the public abilities on the site, from every plugin |
| `mcp-adapter-get-ability-info` | Shows one ability's description and input fields |
| `mcp-adapter-execute-ability` | Runs an ability by name with the given parameters |

On the default server the abilities are named with a slash — `tsmlt/search-media`, `tsmlt/get-media-details` — and are run through `mcp-adapter-execute-ability`:

```json
{
  "ability_name": "tsmlt/search-media",
  "parameters": { "mime_type": "image", "missing_alt": true }
}
```

The dashed tool names (`tsmlt-search-media`) only exist on the dedicated endpoint; the default server reports them as not found.

### Connect

Use the same Application Password as above.

Claude Code:

```bash
read -rs WP_APP_PW   # paste the Application Password; nothing is shown
claude mcp add --transport http mcp-adapter-default \
  https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  --header "Authorization: Basic $(printf '%s:%s' 'YOUR_USERNAME' "$WP_APP_PW" | base64 | tr -d '\n')"
unset WP_APP_PW
```

Claude Desktop and other JSON clients — add this entry to the same configuration file:

```json
{
  "mcpServers": {
    "mcp-adapter-default": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "YOUR_USERNAME",
        "WP_API_PASSWORD": "your application password"
      }
    }
  }
}
```

### Try it

The assistant usually discovers the ability first, then runs it. You can also name it directly:
- "Use execute-ability to run tsmlt/search-media for images with no alt text."
- "Run tsmlt/get-media-details for attachment 1234."

### Differences to keep in mind

| | Dedicated endpoint | Default server |
|---|---|---|
| Tools the client sees | The Media Library Tools tools | Three gateway tools that can run any public ability, from every plugin |
| Who can connect | Administrators only | Any logged-in user can list abilities; running a Media Library Tools ability still requires an administrator |

## Access Control

On the dedicated endpoint:

| Who | Result |
|---|---|
| Administrators | Can use all the tools |
| Editors, authors, and other roles | Refused before any tool is listed (HTTP 403) |
| Unauthenticated requests (no or invalid Application Password) | Refused (HTTP 401) |

Every tool call also re-checks `manage_options` — inside the tool itself, so code that calls a tool's callback directly is refused too — and the permission to read (or, for updates, edit) the specific attachment, on either endpoint. `tsmlt-set-media-groups` also requires permission to assign Groups, `tsmlt-create-media-group` permission to create them, and `tsmlt-update-media-group` permission to edit that Group (by default, administrators and editors have all of these; the tools still require an administrator). `tsmlt-get-media-usage` lists a post only if you can read it. `tsmlt-start-usage-scan` and `tsmlt-get-usage-scan-status` need only `manage_options`. The Pro tools also check the Pro licence on every call. Tools only work with attachments in WordPress's normal `inherit` status; trashed and private attachments are reported as not found.

## Important Notes

### Review before saving
`tsmlt-update-media-metadata` overwrites the current value and cannot be undone. `tsmlt-set-media-groups` can remove Groups from an attachment; its `groups_before` output lists what was there, so the change can be reversed with `replace`. The Pro tool `tsmlt-update-exif-fields` rewrites EXIF data in the image file, and the previous values are not returned. These tools are marked as destructive so clients can ask before running them. `tsmlt-update-media-group` returns `previous_name` so a rename can be reversed. `tsmlt-create-media-group` adds a Group that can only be removed under **Media → Groups**. `tsmlt-start-usage-scan` replaces the previous usage results, so it is also marked destructive — keep your client's tool-approval prompts turned on.

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
| "Tool not found" | The tool belongs to another plugin, or is an MCP Adapter gateway tool | Expected — the dedicated endpoint only offers the tools above |
| Schema warnings in MCP Inspector about `get-media-details` | Some output fields can be `null`, written as a type list (valid JSON Schema 2020-12, which MCP uses by default) | Informational; no action needed |

## Project status, decisions and roadmap

This section is the maintained record of what the MCP integration does, the decisions behind it, and what is planned. Update it when a feature is implemented and verified; a planned item stays planned until code and tests show otherwise. Before starting a roadmap item, agree its scope, dependencies, security and acceptance criteria here.

### Implemented abilities

Free (registered in `AbilitiesInit::get_abilities()`): `tsmlt/search-media`, `tsmlt/get-media-details`, `tsmlt/update-media-metadata`, `tsmlt/get-exif-fields`, `tsmlt/list-media-groups`, `tsmlt/set-media-groups`, `tsmlt/create-media-group`, `tsmlt/update-media-group`, `tsmlt/get-media-usage`, `tsmlt/start-usage-scan`, `tsmlt/get-usage-scan-status` (11). Pro (registered by `ProAbilitiesInit` in the Pro plugin, offered only with an active licence): `tsmlt/get-exif-caption`, `tsmlt/update-exif-fields`, `tsmlt/export-media-csv-bulk`, `tsmlt/preview-media-csv-import`, `tsmlt/apply-media-csv-import` (5). Descriptions: [Available Tools](#available-tools).

Removed: `tsmlt/preview-trash-unused-media` and `tsmlt/trash-unused-media` (moving unused media to the trash through MCP was judged too risky). They must not be reintroduced; no MCP tool trashes or deletes media.

### Architecture decisions

- MCP is the channel between an AI client and WordPress; it does not supply an AI model. Clients such as Claude Desktop or Claude Code use their own model to reason and call the tools.
- MCP tools do not depend on the plugin's AI provider settings or on OpenAI, Claude or Gemini API keys (no ability uses them today). A future feature may use server-side AI only with a stated reason.
- Prefer client-side AI workflows on top of secure, deterministic WordPress operations (for example, the client writes alt text and saves it with `tsmlt-update-media-metadata`).
- Free and Pro stay separate: Pro-only code lives in the Pro plugin, which adds its tools through the `tsmlt_mcp_server_abilities` filter.
- Every tool requires `manage_options`, checked by the permission callback and again at the start of the tool's own `execute()` (direct calls are refused too), plus the per-attachment or per-Group capability it needs; input schemas are closed; output is allowlisted. Tools never return secrets, absolute server paths or raw internal metadata.
- The MCP Adapter's default gateway is opt-in: `Ability::is_mcp_public()` defaults to false. Only the read-only `tsmlt/search-media` and `tsmlt/get-media-details` opt in; write tools, including `tsmlt/update-media-metadata`, are on the dedicated endpoint only.
- A failed write never leaves a silent partial change: `tsmlt/update-media-metadata` restores the fields it saved when the alt text cannot be saved, or reports the fields that remain changed (`tsmlt_partial_update`).
- Usage scans never clear or replace an attachment's existing parent ("Uploaded to").
- CSV import/export through MCP is Pro-only: three tools on the dedicated endpoint, and no Free CSV tool. Import updates existing media only, through a preview and a single-use, user-bound plan applied with explicit confirmation. See [CSV import and export through MCP (Pro)](#csv-import-and-export-through-mcp-pro).

### Usage classification (shared rules)

The Used Where page (Used / Unused / Unknown tabs) and `tsmlt-get-media-usage` use the same rules (`Modules\UsedWhere\UsageClassifier` with `UsageScanStatus`):

- **Used** — at least one usage is recorded, whatever the scan state. Recorded usages stay listed even when the scan's coverage is incomplete.
- **Unused** — no usage is recorded, the last scan completed with verified coverage, and the attachment was uploaded before that scan started.
- **Unknown** — anything else without a recorded usage: no scan, a scan that is running, stalled, cancelled, failed or unfinished, a scan without a coverage record, a coverage gap (`woocommerce_inactive`, `woocommerce_types_not_scanned`), or an upload after the scan started. An empty usage record alone is never proof of non-use.

The Unknown tab explains each image with these reasons, the evidence found in the database (for example, attached to a product or set as a product category image), the scan details, what information is missing, and a next step; reasons are described in [Used Where](09-used-where.md#unknown-tab). Unknown images have no bulk Trash or Delete action. Through MCP the same images have `used: null`.

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
- **Development site** (read-only, 2026-10-28): usage results for real product, gallery, variation and category images were checked against WooCommerce data. No fresh scan has been run there, so its last scan still has no coverage record.

### CSV import and export through MCP (Pro)

**Status: implemented** (2026-11-13) in the Pro plugin and verified on a disposable site (see Verification evidence). How to use the tools: [Pro: CSV export and import](#pro-csv-export-and-import).

#### Free/Pro boundary

- **Free** has no CSV tool. Single items are covered by `tsmlt-get-media-details`, `tsmlt-update-media-metadata` and `tsmlt-set-media-groups`. The Free media table's **Export CSV** bulk action (selected rows of the current page, built in the browser) is unchanged, as is the Pro gating of the dedicated Export and Import pages.
- **Pro** owns bulk CSV export and CSV import through MCP: `ExportMediaCsvBulk`, `PreviewMediaCsvImport` and `ApplyMediaCsvImport`, registered by `ProAbilitiesInit` like the EXIF tools.

#### Abilities

All three are Pro-only, offered only on the dedicated endpoint (`/wp-json/media-library-tools/mcp`) and never on the MCP Adapter's default gateway (`meta.mcp.public` false, `show_in_rest` false).

| Ability | Purpose | Input | Output |
|---|---|---|---|
| `tsmlt/export-media-csv-bulk` | Read-only. One page of media as CSV text. | `limit` (1–100, default 50), `cursor`, `filters` (the `tsmlt/search-media` filters), `columns` (from the allowlist) | `csv`, `columns`, `rows`, `skipped`, `total` (null with a `filename` filter), `next_cursor` |
| `tsmlt/preview-media-csv-import` | Changes no media. Validates a CSV and shows per-row changes; stores a plan when some row is valid. | `csv` (at most 256 KB and 50 data rows), `options`: `match` (`id` or `id_or_slug`), `create_groups` (default false) | `plan_id`, `expires_at`, per-row `row`, `attachment_id`, `status`, `fields`, `changes`, `errors`; `summary` |
| `tsmlt/apply-media-csv-import` | Write. Applies a previewed plan once. | `plan_id`, `confirm: true` | Per-row `row`, `attachment_id`, `status` (`updated`, `unchanged`, `conflict`, `failed`), `applied_fields`, `errors`; `summary` |

#### CSV columns and data exposure

- Fixed column allowlists in code:
  - Export: `ID`, `slug`, `url`, `title`, `alt_text`, `caption`, `description`, `post_parent`, `menu_order`, `groups` (comma-separated Group names).
  - Import: `ID` and `slug` identify the attachment (the slug is never written); `title`, `alt_text`, `caption`, `description`, `post_parent` (a valid post, or 0 to detach), `menu_order` and `groups` can be written. `url`, `rename_to`, `custom_meta:*`, `post_parents_id` and unknown columns refuse the CSV with a message naming them.
- No custom fields are exported or imported through MCP. Output never contains absolute server paths, raw attachment metadata or custom fields: export values come from `MediaPresenter` plus the slug, menu order and Group names.
- CSV is passed as text in the request and response; no ability accepts a file path or a URL, and none writes a temporary file.
- Formula injection (`CsvCellCodec`, Pro): a text cell is protected when it starts with `=`, `+`, `-`, `@`, a tab or a carriage return, optionally after apostrophes; protection adds one apostrophe. The MCP preview removes exactly one apostrophe from a cell matching that protected pattern and changes nothing else, so every value the export writes is read back as it was (`=SUM(1)` → `'=SUM(1)` → `=SUM(1)`; `'=x` → `''=x` → `'=x`; `'Twas` is never changed). The number columns `ID`, `post_parent` and `menu_order` are not protected. The CSV columns and the tools' inputs and outputs are unchanged. The admin CSV export does not protect cells; changing it is a separate follow-up.
- Export pages follow attachment IDs. The cursor holds the last ID, signed (HMAC with the site's salt) and bound to the filters; a page examines at most 1000 matches. Attachments the user may not read are left out and counted in `skipped`.

#### Import: preview, then apply

- Update only: no create-from-URL and no `rename_to`; renaming files stays a separate Pro feature. Groups that do not exist are created only when the preview was made with `create_groups: true`.
- The preview (`ImportPreviewService`) only reads. It parses RFC 4180 CSV (BOM and CRLF accepted, UTF-8 required), checks the header, matches rows (a second row for the same attachment is refused), checks `edit_post` (`unauthorized` otherwise), validates values by the admin importer's rules (`ImportRowService`), and reports per-row before/after values and errors. It undoes the export's formula protection (`CsvCellCodec`) and keeps caption line breaks: captions use `sanitize_textarea_field()` with CRLF and lone CR turned into LF, and a stored caption with CRLF counts as unchanged against the same text with LF. Other fields keep their single-line sanitizing. Apply writes the validated value stored in the plan.
- When at least one row is valid, the operations are stored as a plan (`ImportPlanStore`): a transient named by a random 128-bit ID, for about 15 minutes, bound to the user, holding the validated operations, each target's values at preview time and a hash of the CSV — never the CSV or file paths. A missing, malformed, expired, used or other user's plan is answered the same way.
- Apply requires `confirm: true`, checks the licence and `manage_options` before touching the plan (a refused request does not use it up), then claims the plan with `add_option()`, an atomic insert, so a repeated or concurrent request cannot apply it again. Each row is then checked again (`ImportApplyService`): the attachment exists and is editable, Group permissions (`assign_terms`, and `edit_terms` plus the plan's `create_groups` for new Groups), and every changed field still holds its preview value — otherwise the row is a `conflict` and is not written. A Group created meanwhile is reused rather than created twice.
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
| 2 | Security and permission audit | Implemented | Audit (2026-10-28) found no critical or high issues. Fixed, covered by tests and verified on a disposable local site: scans no longer clear or replace attachments' parents (M1); `manage_options` re-checked inside every Free tool's `execute()` (L1); partial metadata updates restored or reported (L2); default gateway exposure is opt-in and `update-media-metadata` moved to the dedicated endpoint only (L3). The M1 follow-up found on the disposable site (an attached post set from a rendered-page match during the rescan after a save) is fixed and covered by automated tests (2026-10-31), not re-run on a site. Not tested on an existing site. |
| 3 | Bulk media rename | Planned | Safe file renaming with validation, a preview of old and new names, conflict handling and reference updates. Depends on usage data to show what a rename affects. |
| 4 | AI-assisted metadata generation | Planned | The client's own AI writes titles, alt text, captions and descriptions, saved through existing tools; no WordPress AI provider setup required. Decide what, if anything, needs a new tool. |
| 5 | Duplicate media management | Planned | Read-only detection and review first (from the duplicate scanner, without its stale-row cleanup on read); any merge or removal needs its own safety design. |
| 6 | Media library audit | Planned | Diagnostics such as missing alt text, missing files or metadata, and other library checks, read-only. |
| 7 | EXIF tools expansion | Planned | Assess which further EXIF operations are needed beyond the current read and Pro edit tools. |
| 8 | CSV import/export | Implemented | Pro-only `tsmlt/export-media-csv-bulk`, `tsmlt/preview-media-csv-import` and `tsmlt/apply-media-csv-import` on the dedicated endpoint; no Free CSV tool. All seven steps done; covered by the Pro suite and verified on a disposable site (2026-11-13); see [CSV import and export through MCP (Pro)](#csv-import-and-export-through-mcp-pro). Round trips fixed in Pro (2026-11-13; remaining exceptions under Known limitations). Open: neutralising formulas in the admin CSV export. Not tested on an existing site. |
| 9 | Release readiness | Planned | Documentation, compatibility (WordPress, MCP Adapter, Pro licence states), packaging, permissions and test coverage checked before release. |

