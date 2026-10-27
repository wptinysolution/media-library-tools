# MCP Integration (AI Assistants)

Media Library Tools lets AI assistants that speak the Model Context Protocol (MCP) search your media library, read media details and camera EXIF fields, find where media is used, update media text fields, and organise media into Groups on your behalf. With Media Library Tools Pro it can also build captions from EXIF data and edit EXIF fields. It has been tested with Claude Code and the official MCP Inspector.

## Overview

The integration lets an AI assistant:
- Search the media library (by text, file name, MIME type, Group, attached or unattached, or attachments missing alt text)
- Read the details of one attachment
- Find where media is used on the site (featured images, content, page builders, custom fields, logo, menus, widgets), from the Used Where scan
- Read an image's camera, lens and exposure EXIF fields (make, model, lens, software, dates taken and digitized, ISO, aperture, shutter speed, focal length, exposure codes, flash, description)
- Update an attachment's title, alt text, caption, and description
- List, create, and rename media Groups, and change which Groups an attachment belongs to
- **Pro:** build a caption from an image's EXIF data, and set or clear EXIF fields of a JPEG image (see [Pro: EXIF tools](#pro-exif-tools))

The Free tools never change EXIF data or image files. Only the Pro tool `tsmlt-update-exif-fields` changes EXIF data, inside JPEG files. No tool can rename, move, upload, or delete files, or delete or move Groups; `tsmlt-create-media-group` adds Groups and `tsmlt-update-media-group` renames them. Connected to the recommended dedicated endpoint, an assistant cannot reach anything outside these tools.

**Requirements**:
- WordPress **6.9 or newer** (the Abilities API is part of WordPress core from 6.9)
- The official **MCP Adapter** plugin from [wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/) — tested with **0.7.0**. The dedicated endpoint uses the adapter's documented `create_server()` API, whose signature is unchanged since 0.3.0, but versions before 0.7.0 have not been tested
- A WordPress **administrator** account (the `manage_options` capability)

Without the MCP Adapter, or on WordPress older than 6.9, nothing is registered and the plugin behaves exactly as before.

## Available Tools

| Tool | What it does | Changes data |
|---|---|---|
| `tsmlt-search-media` | Lists matching attachments, one page at a time (up to 50 per page). Optional `filename` matches part of the file name; `group_id` limits results to one Group; `attached` to media attached, or not attached, to a post | No |
| `tsmlt-get-media-details` | Returns one attachment's title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, image sizes, attached post, and upload date | No |
| `tsmlt-get-exif-fields` | Returns one image's camera make and model, lens make and model, software, date taken and date digitized, ISO, aperture, shutter speed, focal length (mm), the exposure program, exposure mode, metering mode, white balance and flash codes defined by the EXIF standard, and the image description — read from the JPEG file, or from values saved in the EXIF editor for any image type. A field without a value is `null`. Dedicated endpoint only | No |
| `tsmlt-get-media-usage` | Reports where up to 50 attachments are used — featured images, content and excerpts, page builders, galleries and custom fields, and site-wide (logo, site icon, header or background image, menus, widgets, term and site options) — from the records of the Used Where scan. Says whether each one is used, not used, or unknown. Dedicated endpoint only | No |
| `tsmlt-update-media-metadata` | Changes only the fields you send: title, alt text, caption, description. An empty string clears a field | Yes |
| `tsmlt-list-media-groups` | Lists media Groups with their ID, name, parent Group, and number of media items; filter by name or ID. Dedicated endpoint only | No |
| `tsmlt-set-media-groups` | Adds, removes, or replaces the Groups of one attachment. Dedicated endpoint only | Yes (Group assignments only) |
| `tsmlt-create-media-group` | Creates one Group, at the top level or under an existing Group. Dedicated endpoint only | Yes (adds a Group) |
| `tsmlt-update-media-group` | Renames one Group; its ID, slug, parent and media stay the same. Dedicated endpoint only | Yes (Group name) |
| `tsmlt-get-exif-caption` | **Pro.** Builds a caption from one image's camera make and model and exposure (aperture, shutter speed, ISO) and returns it without saving. Dedicated endpoint only | No |
| `tsmlt-update-exif-fields` | **Pro.** Sets or clears EXIF fields of one image: camera, lens, exposure, dates, artist, copyright, description. Edits JPEG files; other image types keep the values in the plugin's database only. Dedicated endpoint only | Yes (EXIF data, in the JPEG file) |

The two Pro tools are offered only when Media Library Tools Pro is active with a valid licence.

**What is never returned**: server file paths, raw attachment metadata, raw EXIF data, GPS location (or whether an image has one), copyright, artist, camera serial numbers, or custom fields. `tsmlt-get-exif-fields` returns only the fields listed above — never time zone offsets, altitude, location, copyright or artist — and `tsmlt-get-exif-caption` returns only the caption text.

**What is never changed**: the file's name or URL, the attached post, or any other attachment. `tsmlt-set-media-groups` changes only the attachment's Group assignments. The image file itself is changed only by the Pro tool `tsmlt-update-exif-fields`, which edits EXIF data inside JPEG files.

## Search by file name

`tsmlt-search-media` accepts `filename`: part of a file name, for example `IMG_2041` or `.png`. It matches the name of the attachment's file, ignoring case — not its title, caption or description (that is what `search` does), and not folder names: `2026` does not match files just because they are in the `2026` upload folder. For large images WordPress scaled down, the file is the `-scaled` one, whose name still contains the original name. `%` and `_` are matched as ordinary characters.

`filename` must be 1–200 characters without `/`, `\`, or control characters; anything else is refused. It combines with every other filter and with paging. Results show the same fields as any search; no server file paths are returned.

## Attached and unattached media

`tsmlt-search-media` accepts `attached`: `true` returns only media attached to a post — WordPress's **Uploaded to** relationship, which may point to a draft, private, or trashed post — and `false` returns only unattached media. Leave it out to search both. It must be a JSON boolean (`true` or `false`); strings such as `"false"` are refused.

Attached is not the same as used. An image can appear in a page's content or a page builder without being attached to that page, and an attached image may not appear anywhere. `attached` does not check where media is used.

## Where media is used — `tsmlt-get-media-usage`

Reads the records of the plugin's **Used Where** scan (**Media Library Tools → Used Where**). It does not scan and changes nothing; run or finish a scan there for complete results. A post saved after a scan is re-checked shortly afterwards, by a WP-Cron event.

| Input | Type | Meaning |
|---|---|---|
| `ids` | list of integers, required (1–50, no repeats) | Attachment IDs |
| `max_usages` | integer, 1–100 (default 50) | Most usages listed per attachment; the counts are always complete |

If any ID is not an attachment you can read (missing, trashed, or private to you), the whole request is refused with an error naming that ID.

Output:
- `scan`: `state` of the last scan (`idle`, `queued`, `running`, `complete`, `cancelled` or `error`), `complete`, `processed`, `total`, and `last_update` (site time).
- `items`, in the order requested, each with:
  - `id`, and `used`: `true` when a usage is recorded; `false` only when none is recorded **and** the last scan completed; `null` when that is unknown. Do not treat `null` as unused.
  - `total_usage` (listed plus hidden), `hidden` (usages in posts you cannot read, or of a post type that is no longer registered, and WooCommerce orders kept in WooCommerce's own order tables — counted, never listed), and `truncated` (more usages than `max_usages`).
  - `usages`: `post_id`, `post_type`, `post_status` and the post's current `title`; `usage_type` (for example `featured`, `content`, `excerpt`, `elementor`, `meta`, `woo_gallery`, `site_logo`, `widget`, `nav_menu`, `option_settings`, or `other`); and `sitewide`. Site-wide usages have `post_id` 0, `post_type` `site_settings`, and no status or title.

Usages in posts that have since been deleted are left out. No permalinks, file paths or excerpts are returned.

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

**Important**: The dedicated endpoint does not change or disable the default server. Media Library Tools' own tools also remain available there, alongside other plugins' abilities. If nothing on your site needs the default server, a developer can disable it with the MCP Adapter's `mcp_adapter_create_default_server` filter — check with any other integration you use first.

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

The search, details and update tools can also be reached through the MCP Adapter's default server (`tsmlt-get-exif-fields`, `tsmlt-get-media-usage`, `tsmlt-list-media-groups`, `tsmlt-set-media-groups`, `tsmlt-create-media-group`, `tsmlt-update-media-group` and the Pro tools `tsmlt-get-exif-caption` and `tsmlt-update-exif-fields` are offered only on the dedicated endpoint):

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

On the default server the abilities are named with a slash — `tsmlt/search-media`, `tsmlt/get-media-details`, `tsmlt/update-media-metadata` — and are run through `mcp-adapter-execute-ability`:

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

Every tool call also re-checks `manage_options` and the permission to read (or, for updates, edit) the specific attachment — on either endpoint. `tsmlt-set-media-groups` also requires permission to assign Groups, `tsmlt-create-media-group` permission to create them, and `tsmlt-update-media-group` permission to edit that Group (by default, administrators and editors have all of these; the tools still require an administrator). `tsmlt-get-media-usage` lists a post only if you can read it. The Pro tools also check the Pro licence on every call. Tools only work with attachments in WordPress's normal `inherit` status; trashed and private attachments are reported as not found.

## Important Notes

### Review before saving
`tsmlt-update-media-metadata` overwrites the current value and cannot be undone. `tsmlt-set-media-groups` can remove Groups from an attachment; its `groups_before` output lists what was there, so the change can be reversed with `replace`. The Pro tool `tsmlt-update-exif-fields` rewrites EXIF data in the image file, and the previous values are not returned. These tools are marked as destructive so clients can ask before running them. `tsmlt-update-media-group` returns `previous_name` so a rename can be reversed. `tsmlt-create-media-group` adds a Group that can only be removed under **Media → Groups** — keep your client's tool-approval prompts turned on.

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
