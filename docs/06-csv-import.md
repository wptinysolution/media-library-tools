# CSV Import

The CSV Import feature lets you update existing media or create new attachments from a CSV file. It is a **Pro-only** feature.

**Location**: Media Library Tools → Import

---

## Quick Reference: Accepted Column Headers

Headers in your CSV file **must match these names exactly** — they are case-sensitive and must be lowercase / snake_case. Any other column name will be ignored.

| Header | Used for | Notes |
|---|---|---|
| `ID` | Primary match key when updating | WordPress attachment ID (integer) |
| `slug` | Fallback match key when updating; sets `post_name` on create | Used if `ID` is missing or does not match |
| `url` | Source file URL when creating new attachments | Required for "create new" mode; ignored in update mode |
| `rename_to` | New file name (without extension) | Only applied when "Rename" option is checked |
| `title` | Attachment title (`post_title`) | |
| `caption` | Attachment caption (`post_excerpt`) | |
| `description` | Attachment description (`post_content`) | |
| `alt_text` | Image alt text | Stored as `_wp_attachment_image_alt` post meta |
| `post_parents_id` | Parent post ID (`post_parent`) | Optional; integer |
| `custom_meta:<key>` | Any custom post meta value | The portion after `custom_meta:` becomes the meta key |

**Important rules:**

- Headers are case-sensitive. `ID`, not `Id` or `id`. `alt_text`, not `Alt Text`.
- Any **unknown** column (e.g. `Filename`, `Alt Text`, `Photo URL`) is silently ignored — it will not produce an error, but no data from that column is written.
- Empty cells are treated as "no value" and leave the existing field unchanged in update mode.
- The first row of the file must be the header row.

---

## Import Modes

There are two modes, selected with the **"Update existing media"** checkbox on the upload screen.

### 1. Update existing media (checkbox ON)

Updates fields on attachments that already exist in the library.

- **Match order**: Each row is matched first by `ID`. If the `ID` is missing or does not match any attachment, the importer falls back to matching by `slug`.
- **No match → row skipped**. The row is reported as failed; nothing is created.
- **Empty cells leave the existing field unchanged.** Only columns with a value are written.
- **Optional rename**: If you also tick "Rename using the value located in the (rename_to) column", the file is renamed to the value of `rename_to` for each matched row.

### 2. Create new attachments (checkbox OFF)

Downloads each `url` and creates a new attachment.

- **`url` is required** in every row. Rows without a `url` are skipped.
- If `url` contains a comma-separated list, only the **first** URL is used.
- WordPress downloads the file via `download_url()`, sideloads it into the uploads directory, then runs `wp_insert_attachment()` + `wp_generate_attachment_metadata()`.
- `ID` is ignored in this mode (a new ID is generated).
- `title`, `slug`, `caption`, `description`, `alt_text`, `post_parents_id`, and any `custom_meta:*` columns are applied to the new attachment.

---

## CSV File Requirements

- **Format**: `.csv`
- **Encoding**: UTF-8 (a BOM is stripped automatically)
- **Delimiter**: Comma `,`
- **Quoting**: Wrap values containing commas, quotes, or newlines in double quotes `"…"`. Escape inner quotes by doubling them: `""`.
- **First row**: Header row (column names from the table above).
- **Subsequent rows**: One attachment per row. Fully empty rows are skipped.

### Example — update existing media

The first row is the header row; each row below it is one attachment.

| ID | slug | title | caption | description | alt_text |
|---|---|---|---|---|---|
| 123 | sunset | Sunset over the bay | Golden hour, July 2025 | Shot from the pier | Sunset over a calm bay |
| 124 | ocean-waves | Pacific waves | *(empty)* | Long exposure of Pacific waves | Pacific Ocean waves |
| 125 | *(empty)* | Desert dunes | *(empty)* | Utah desert | *(empty)* |

Saved as CSV this becomes:

```csv
ID,slug,title,caption,description,alt_text
123,sunset,Sunset over the bay,"Golden hour, July 2025","Shot from the pier","Sunset over a calm bay"
124,ocean-waves,Pacific waves,,"Long exposure of Pacific waves","Pacific Ocean waves"
125,,Desert dunes,,"Utah desert",
```

What happens row by row:
- **Row 1** — updates attachment ID `123` with all fields.
- **Row 2** — updates ID `124`; the empty `caption` cell leaves the existing caption unchanged.
- **Row 3** — has an empty `ID`, so it falls back to matching by `slug` — but `slug` is also empty, so the row is skipped.

### Example — create new attachments

| url | title | slug | caption | description | alt_text | post_parents_id |
|---|---|---|---|---|---|---|
| https://example.com/img/sunset.jpg | Sunset over the bay | sunset | Golden hour, July 2025 | Shot from the pier | Sunset over a calm bay | 0 |
| https://example.com/img/waves.jpg | Pacific waves | ocean-waves | *(empty)* | Long exposure | Pacific Ocean waves | *(empty)* |

Saved as CSV this becomes:

```csv
url,title,slug,caption,description,alt_text,post_parents_id
https://example.com/img/sunset.jpg,Sunset over the bay,sunset,"Golden hour, July 2025","Shot from the pier","Sunset over a calm bay",0
https://example.com/img/waves.jpg,Pacific waves,ocean-waves,,"Long exposure","Pacific Ocean waves",
```

### Example — with custom meta

Any column whose header starts with `custom_meta:` is written to post meta. The text after `custom_meta:` is used as the meta key.

| ID | title | alt_text | custom_meta:_photographer | custom_meta:_license |
|---|---|---|---|---|
| 123 | Sunset over the bay | Sunset over a calm bay | Jane Doe | CC-BY-4.0 |

Saved as CSV this becomes:

```csv
ID,title,alt_text,custom_meta:_photographer,custom_meta:_license
123,Sunset over the bay,Sunset over a calm bay,Jane Doe,CC-BY-4.0
```

This sets:
- `_photographer` = `Jane Doe`
- `_license` = `CC-BY-4.0`

A handy way to get the exact custom meta column names for your site is to use **CSV Export** first — its columns are the same names the importer accepts.

---

## Step-by-Step Usage

1. Go to **Media Library Tools → Import**.
2. Click **Upload CSV File** and pick your `.csv` file.
3. After the file parses, choose your mode:
   - Leave **"Update existing media"** unchecked to create new attachments from `url`.
   - Check **"Update existing media"** to update by `ID`/`slug`.
   - Optionally check **"Rename using the value located in the (rename_to) column"** to rename matched files.
4. Click **Run the importer**.
5. The importer processes rows one-by-one. Each row reports `uploaded` or `failed`.
6. When finished, a record is saved to **Import History** for re-import.

---

## What Gets Written Where

| CSV column | Update mode writes to… | Create mode writes to… |
|---|---|---|
| `ID` | Match key (no write) | Ignored — new ID generated |
| `slug` | Fallback match key + `posts.post_name` | `post_name` on new attachment |
| `url` | Ignored | Source file to download (required) |
| `title` | `posts.post_title` | `post_title` on new attachment |
| `caption` | `posts.post_excerpt` | `post_excerpt` |
| `description` | `posts.post_content` | `post_content` |
| `alt_text` | `_wp_attachment_image_alt` post meta | `_wp_attachment_image_alt` post meta |
| `post_parents_id` | `posts.post_parent` | `post_parent` |
| `rename_to` | Triggers `wp_rename_attachment()` (if Rename option is on) | Ignored |
| `custom_meta:<key>` | `update_post_meta($id, '<key>', $value)` | `update_post_meta($newId, '<key>', $value)` |

---

## Important Notes

- **Pro required**: If the Pro plugin is not active, the importer returns `failed` for every row.
- **Empty cells are non-destructive**: in update mode, empty cells do not clear existing fields.
- **Unknown columns are ignored**: misspelling a header means that column does nothing — there is no error.
- **`url` parsing**: only the first comma-separated URL is used in create mode.
- **Rename caveat**: `rename_to` is only used when the Rename checkbox is also ticked. It is the file name **without extension**.
- **Match priority**: `ID` always wins over `slug`. If both are present and `ID` is valid, `slug` is not consulted.

---

## Troubleshooting

**Q: My CSV had headers like `Filename` and `Alt Text` and nothing was updated.**
A: Those headers are not recognised. The importer only accepts the exact lowercase / snake_case names in the table above (`alt_text`, not `Alt Text`; there is no `Filename` column — use `slug` or `ID` to match).

**Q: Every row is reported as "failed" in update mode.**
A: Likely no `ID` matched and no `slug` matched. Export the current media table first to get the correct `ID` and `slug` values, then edit that file.

**Q: Every row is reported as "failed" in create mode.**
A: Check that the `url` column exists and each row has a reachable URL. Server-side `download_url()` must be able to fetch the file.

**Q: I see import progress stop or the page errors out on a large file.**
A: Try a smaller batch — the notice on the import screen recommends this. Each row is one AJAX request, so very large files are limited by your server's PHP timeout and the browser session.

**Q: Custom meta is not being written.**
A: The header must start with `custom_meta:` (lowercase, colon, no space). For example `custom_meta:_sku`, not `Custom Meta: _sku`.

**Q: Special characters look corrupted after import.**
A: Save the CSV as **UTF-8**. Excel: *Save As → CSV UTF-8 (Comma delimited)*.

---

## Tip: Generate a Valid Import Template from Export

The safest way to build an import file is to use **CSV Export** first:

1. Go to **Media Library Tools → Export**.
2. Pick the columns you want to edit.
3. Download the CSV.
4. Edit values in your spreadsheet.
5. Re-upload the same file via **Import**.

The exporter writes headers in the exact format the importer expects (`ID`, `slug`, `url`, `title`, `caption`, `description`, `alt_text`, plus any `custom_meta:*` columns you selected), so there is no header-mapping work to do.

---

**Next**: [Rubbish Files](07-rubbish-files.md) — find and clean up orphaned media.
