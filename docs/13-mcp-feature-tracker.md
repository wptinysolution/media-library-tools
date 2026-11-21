# MCP Feature Tracker

The single source of truth for which Media Library Tools features (Free and Pro) are available through MCP, what has been investigated, what was decided, and what comes next. Read it before starting any MCP investigation or recommending the next feature.

How each tool works is described in [MCP Integration](12-mcp-integration.md). Its roadmap records the history of the work; this tracker covers every plugin feature, including those no work has started on.

Last updated: 2026-11-20.

## Statuses

Investigation and MCP status use only these values:

| Status | Meaning |
|---|---|
| Not Investigated | Nobody has checked whether or how the feature could work through MCP. |
| Investigated | Read-only investigation done; findings recorded here; no scope approved yet. |
| Planned | Scope approved by the project owner; implementation not started. |
| In Progress | Implementation started. |
| Partially Implemented | Some operations are available through MCP and verified; others are not (see "Missing operations"). |
| Implemented | Every operation in the approved scope is available through MCP and verified by tests. |
| Deferred | Deliberately not done for now, for a recorded reason; may be reopened when that reason is resolved. |
| Excluded | Deliberately never offered through MCP, for a recorded reason. |

A feature is marked Implemented only with test evidence, never because code exists.

## Summary

| # | Feature | Owner | MCP status | Investigation | Next step |
|---|---|---|---|---|---|
| F1 | Media search, details and metadata editing | Free | Implemented | Investigated | None |
| F2 | Media Groups | Free | Implemented | Investigated | None |
| F3 | Media usage (Used Where) | Free | Implemented | Investigated | Live-site verification (roadmap row 1) |
| F4 | Trash and restore | Free | Implemented | Investigated | None |
| F5 | CSV export | Free / Pro | Implemented | Investigated | None |
| F6 | CSV import | Pro | Implemented | Investigated | None |
| F7 | Media health audit | Free | Implemented | Investigated | None |
| F8 | EXIF (Free: fields, privacy scan) | Free | Implemented | Investigated | None |
| F9 | EXIF (Pro editing tools) | Pro | Partially Implemented | Investigated | None until a deferred item's blocker is resolved |
| F10 | Duplicate media | Free / Pro | Implemented | Investigated | None (duplicate removal not planned) |
| F11 | Rename | Free / Pro | Partially Implemented | Investigated | Pro tool-list test update (Pro strategies not exposed) |
| F12 | Compression | Free (Pro options) | Implemented | Investigated | None (Pro backup restore deferred) |
| F13 | Conversion (WebP/AVIF) | Free (Pro options) | Implemented | Investigated | None (C5 leftover copies: decision open) |
| F14 | Thumbnails (regeneration) | Free | Implemented | Investigated | None |
| F15 | AI metadata generation | Free / Pro | Excluded (server-side); client-side workflow supported | Investigated | None |
| F16 | Rubbish files and empty folders | Free / Pro | Deferred | Investigated | Decide on exposing file paths |
| F17 | Image sizes, settings and automatic behaviour | Free / Pro | Not Investigated | Not Investigated | Probably not applicable; confirm |

## Next feature

**Not chosen yet.** F15 was closed on 2026-11-21: server-side AI is excluded and the client-side workflow is supported, completed by `tsmlt/get-media-image-preview`. Open items: the Pro tool-list test update for the new tools (`tsmlt/get-media-image-preview` and the F11 rename tools), and C5 (F13: copies left by deleted attachments block new conversions with that name). Remaining candidates: F16 Rubbish files (Deferred), F17 (Not Investigated).

## Features

### F1 Media search, details and metadata editing

- **Owner:** Free.
- **Plugin:** media table (`Controllers/Admin/Api.php`; AJAX `tsmlt_get_media`, `tsmlt_update_single_media`, `tsmlt_bulk_submit`).
- **MCP status:** Implemented.
- **Tools:** `tsmlt/search-media`, `tsmlt/get-media-details`, `tsmlt/update-media-metadata`.
- **Missing operations:** none in scope. Bulk editing is done per attachment through `update-media-metadata`.
- **Tests:** `SearchMediaAbilityTest`, `SearchMediaSelectionTest`, `GetMediaDetailsAbilityTest`, `UpdateMediaMetadataAbilityTest`, `ContentMediaDiscoveryTest`, `McpSecurityRemediationTest`.
- **Source:** `app/Abilities/Media/SearchMedia.php`, `GetMediaDetails.php`, `UpdateMediaMetadata.php`, `app/Abilities/MediaPresenter.php`, `app/Abilities/AbilityGuard.php`.
- **Decisions and limits:** only `search-media` and `get-media-details` are MCP-public (runnable through the default gateway). Output only through `MediaPresenter`: no paths, raw metadata or custom fields.

### F2 Media Groups

- **Owner:** Free.
- **MCP status:** Implemented.
- **Tools:** `tsmlt/list-media-groups`, `tsmlt/set-media-groups`, `tsmlt/create-media-group`, `tsmlt/update-media-group` (rename only).
- **Missing operations:** deleting a Group. No decision recorded; not requested.
- **Tests:** `MediaGroupsAbilitiesTest`, `CreateMediaGroupAbilityTest`, `UpdateMediaGroupAbilityTest`.
- **Source:** `app/Abilities/Media/ListMediaGroups.php`, `SetMediaGroups.php`, `CreateMediaGroup.php`, `UpdateMediaGroup.php`.

### F3 Media usage (Used Where)

- **Owner:** Free.
- **Plugin:** Used Where page (`Modules/UsedWhere/*`; AJAX `tsmlt_used_where_*`).
- **MCP status:** Implemented.
- **Tools:** `tsmlt/get-media-usage`, `tsmlt/start-usage-scan`, `tsmlt/get-usage-scan-status`, `tsmlt/list-media-usage`.
- **Missing operations:** none.
- **Tests:** `GetMediaUsageAbilityTest`, `UsageScanAbilitiesTest`, `StartUsageScanLockTest`, `UsageAuditProgressTest`, `ListMediaUsageAbilityTest`.
- **Verification:** tests plus a disposable local site. A fresh scan on a development site is still open (roadmap row 1, needs permission).
- **Decisions and limits:** `tsmlt/start-usage-scan` must not be run against real sites without permission. Usage is "unknown" unless a complete, verified scan covers the item.

### F4 Trash and restore

- **Owner:** Free.
- **Plugin:** media table bulk actions and Used Where page (`Modules/Trash/AdminTrash.php`).
- **MCP status:** Implemented.
- **Tools:** `tsmlt/list-trashed-media`, `tsmlt/preview-trash-media`, `tsmlt/trash-media`, `tsmlt/restore-media`.
- **Missing operations:** permanent deletion and Empty Trash (**Excluded**, D3).
- **Tests:** `TrashMediaAbilityTest`, `ListTrashedAndRestoreMediaAbilityTest`.
- **Source:** `app/Abilities/Media/*Trash*.php`, `RestoreMedia.php`, `app/Modules/Trash/MediaTrash.php`, `TrashClaim.php`.
- **Investigated:** 2026-11-20, confirmed complete. Do not reinvestigate.
- **Known issue outside MCP (not fixed, needs approval):** the media table `delete` bulk action and `tsmlt_used_where_bulk_delete` delete permanently without a per-item `delete_post` check.

### F5 CSV export

- **Owner:** Free (selected IDs), Pro (whole library to files).
- **MCP status:** Implemented.
- **Tools:** Free `tsmlt/export-media-csv`; Pro `tsmlt/export-media-csv-bulk`, `tsmlt/get-media-csv-export`, `tsmlt/continue-media-csv-export`, and the MCP App resources `tsmlt/media-csv-export-view`, `tsmlt/read-media-csv-export-file`.
- **Missing operations:** none.
- **Tests:** Free `ExportMediaCsvAbilityTest`; Pro `ExportMediaCsvBulkAbilityTest`, `ExportMediaCsvBulkMcpExposureTest`, `CsvExport*Test` (6 files).

### F6 CSV import

- **Owner:** Pro (the import page UI lives in Free but is Pro-gated).
- **Plugin:** `Modules/Import/*` (AJAX `tsmlt_import_attachment`).
- **MCP status:** Implemented for updating existing media.
- **Tools:** `tsmlt/preview-media-csv-import`, `tsmlt/apply-media-csv-import`.
- **Missing operations:** `url` (create media from a URL), `rename_to` and `custom_meta:*` columns (**Excluded**, D6).
- **Tests:** `PreviewMediaCsvImportAbilityTest`, `ApplyMediaCsvImportAbilityTest`, and their `McpExposureTest` files.
- **Investigated:** 2026-11-20, confirmed complete. Do not reinvestigate.

### F7 Media health audit

- **Owner:** Free.
- **MCP status:** Implemented (read-only by design).
- **Tools:** `tsmlt/get-media-health`, `tsmlt/audit-media-health`.
- **Missing operations:** files on disk that are not in the library (part of F16, **Deferred**).
- **Tests:** `GetMediaHealthAbilityTest`, `AuditMediaHealthAbilityTest`.

### F8 EXIF (Free)

- **Owner:** Free.
- **Plugin:** EXIF Data page (`Modules/ExifData/*`).
- **MCP status:** Implemented for reading and the privacy scan.
- **Tools:** `tsmlt/get-exif-fields`, `tsmlt/start-exif-privacy-scan`, `tsmlt/get-exif-privacy-scan-status`, `tsmlt/list-exif-privacy-results`.
- **Missing operations:** removing all EXIF from one image (**Deferred**, D5).
- **Tests:** `GetExifFieldsAbilityTest`, `ExifPrivacyScanAbilitiesTest`.
- **Decisions and limits:** GPS, copyright, artist and raw EXIF are never returned.

### F9 EXIF (Pro)

- **Owner:** Pro.
- **MCP status:** Partially Implemented.
- **Tools:** `tsmlt/get-exif-caption`, `tsmlt/update-exif-fields`, `tsmlt/preview-gps-removal`, `tsmlt/remove-gps-metadata`, `tsmlt/preview-bulk-exif-edit`, `tsmlt/apply-bulk-exif-edit`, `tsmlt/list-exif-change-logs`, `tsmlt/preview-exif-undo`, `tsmlt/undo-exif-change`.
- **Missing operations (all Deferred, D5):** EXIF export, EXIF import, EXIF ↔ WordPress sync, full EXIF removal.
- **Tests:** `GetExifCaption*Test` (3 files), `UpdateExifFieldsAbilityTest`, `UpdateExifFieldsEdgeCasesTest`, `ExifOperationsAbilitiesTest`.
- **Note:** full EXIF removal in WordPress Admin became lossless (Free `JpegMetadataStripper`, 2026-11-20). That does not by itself reopen the MCP decision.
- **Operation locks (resolved 2026-11-21; Pro only, no MCP tool definitions changed):**
  - **Gap closed:** Pro's EXIF writes previously checked none of the shared operation locks (recorded as a limitation in F11, F13 and F14).
  - **What is guarded:** `ExifEditorModule::save_exif()`, `ExifStripperModule::strip_gps_from_attachment()` and `ExifLogger::undo_last_change()`. Every caller goes through these: the EXIF editor, bulk EXIF edit (Admin and MCP), sync, import, GPS removal (MCP, bulk, batch and on upload) and undo.
  - **How:** these methods now take Pro's `Modules/ExifData/ExifWriteLock`, which uses only the free plugin's existing locks, the same way Free's `ExifStripper` does.
    - **Refused while held:** a write is refused while the image is held by compression (`AttachmentProcessor::is_locked()`), conversion (`AttachmentConverter::is_locked()`), a rename or a thumbnail regeneration (`RegenerateLock::is_held()`).
    - **Held while writing:** the write holds the EXIF removal lock (`ScanStartLock`, `ExifStripper::LOCK_PREFIX`), which those operations and another EXIF write check. So the protection works both ways.
    - **Re-entry:** allowed within one request, so an undo, which saves through `save_exif()`, takes the lock once.
  - **Refused result:**
    - **Returned:** `success: false` with `code: 'locked'`, and nothing written.
    - **Bulk runs:** they report a refused image on its own and continue with the others.
    - **Undo:** a refused undo keeps its log entry.
  - **Through MCP:** status definitions were intentionally left unchanged, so a refused image shows as `write_failed` (`tsmlt/update-exif-fields`, `tsmlt/apply-bulk-exif-edit`) or `failed` (`tsmlt/remove-gps-metadata`, `tsmlt/undo-exif-change`).
  - **Tests (2026-11-21):**
    - Pro `ExifWriteLockTest` (14): editor, GPS removal and undo each blocked by each of the four locks, with the file and EXIF metadata unchanged and the write working after release; bulk edit continuing past a blocked image; the EXIF lock held during a write, with Free's rename engine reporting the image `locked` meanwhile.
    - Mutation checks: removing each gate, or the re-entry handling, makes the matching tests fail.
    - Full Pro suite: 572 tests, all passing.
  - **Limitations:**
    - **Lock races:** locks are post meta and are not atomic across operations.
    - **Older free plugin:** where it lacks one of these locks, that check is skipped (rename and regeneration are then not detected).
    - **MCP statuses:** there is no distinct `locked` status in the MCP tools (that would need a tool definition change).

### F10 Duplicate media

- **Owner:** detection and scan Free; merge Pro.
- **MCP status:** Implemented. Detection and scan (Free); merge (Pro, 2026-11-21, D21).
- **Tools (implemented):** Free `tsmlt/find-duplicate-media`, `tsmlt/start-duplicate-scan`, `tsmlt/get-duplicate-scan-status`; Pro `tsmlt/preview-duplicate-merge`, `tsmlt/apply-duplicate-merge`.
- **Missing operations:** removing duplicates (**Not planned**, D20).
- **Tests:** Free `FindDuplicateMediaAbilityTest`, `DuplicateScanAbilitiesTest`; Pro `DuplicateMergeAbilitiesTest`, `DuplicateMergeElementorTest`.

#### Pro duplicate merge over MCP (planned under D20; implemented 2026-11-21, D21)

- **Edition:** duplicate merge stays a Pro feature (the Duplicates page's merge, Pro `Modules/Duplicate/DuplicateApi.php`, AJAX `tsmlt_duplicate_merge`). Its MCP integration belongs in the Pro repository. The Pro merge is not moved, copied or reimplemented in Free; Free keeps detection and scan.
- **Not planned:** removing duplicates (deleting duplicate files or attachments without a merge) is not part of the MCP roadmap.
- **Before design:**
  - inspect the existing Pro merge implementation (what it changes, in which order, and how it fails);
  - agree the scope;
  - get explicit approval.
- **To verify before implementation:**
  - **preview:** a read-only preview of what the merge would change, per group;
  - **confirmation:** an explicit confirmation step (the preview-then-confirm pattern of the other changing tools);
  - **references:** media references are preserved (content, featured images, galleries, page builders, custom fields), so nothing that used a merged item breaks;
  - **metadata:** metadata is handled correctly (title, alt text, caption, description, Groups, attached post);
  - **failure:** safe failure and rollback behaviour, so a merge that stops part-way leaves no broken references or lost files.
- **Status:** implemented 2026-11-21 (D21; see below). The checks above were verified before implementation.

#### Implementation (2026-11-21; Pro only)

- **Reused merge logic:** `Modules/Duplicate/DuplicateApi.php` stays the source of the merge operations.
  - **Extracted:** `copy_groups()` (Groups and other attachment taxonomies) and `forget_duplicate()` (duplicate-results row, now through `Fns::DB()`). The admin `merge_duplicates()` uses them, with unchanged behaviour: it still deletes permanently.
  - **Added:** `merge_into_trash( $keep, $duplicate, $url_pairs )`, which runs, in order:
    1. Free `Fns::replace_image_url_tracked()` for each URL pair; any error stops here (`reference_update_failed`);
    2. `Fns::reassign_featured_image()`;
    3. `copy_groups()`;
    4. Free `MediaTrash::trash()`, which never deletes permanently and refuses while trash is off;
    5. `forget_duplicate()`.
  - **Hooks:** it fires the existing `tsmlt_before_duplicate_merge` / `tsmlt_after_duplicate_merge` hooks.
- **Checks:** `Modules/Duplicate/DuplicateMergePlan.php` handles the MCP checks.
  - **Kept image:** readable, editable, `inherit`, a local file, not locked.
  - **Per duplicate:**
    - access: `read_post`, `edit_post`, `delete_post` and `inherit`;
    - a local file whose size and MD5, computed now, equal the kept file's;
    - no compression, conversion, EXIF or `RegenerateLock`, and trash on;
    - verified usage (`MediaTrash::usage()`), with only `featured`, `content` and `excerpt` records;
    - no ID patterns in those posts;
    - not the site logo or icon;
    - no linked size or original the kept image lacks.
  - **URL pairs:** each of the duplicate's URLs (full size, original, each size) maps to the kept image's file of the same size; unmapped URLs are never replaced.
  - **Fingerprint:** the file hashes and sizes, both attachments' metadata and modified times, the usage records, the scan ID and the URL pairs.
- **Abilities:** `app/Abilities/PreviewDuplicateMerge.php` and `ApplyDuplicateMerge.php` use the `ExifAbilitySupport` conventions.
  - **Access:** licence and `manage_options` in the permission callback and in `execute()`. They are dedicated-only (`meta.mcp.public` false) and listed while licensed (`ProAbilitiesInit`).
  - **Plans:** `ImportPlanStore`, kind `duplicate_merge`; single use, user-bound, about 15 minutes, refused for another tool.
  - **Apply:** it takes `RegenerateLock` on the kept image for the whole request and on each duplicate while it is merged (released in `finally`), checks everything again with those locks, requires the preview's fingerprint, and handles each duplicate on its own (`merged`, `skipped`, `failed`).
  - **Output:** no paths or file hashes.
- **Tests (2026-11-21, Pro):**
  - `DuplicateMergeAbilitiesTest` (13) and `DuplicateMergeElementorTest` (1, separate process). They cover registration and dedicated-endpoint use; gateway refusal; licence and role checks; per-attachment access; identical files; preview read-only; plan single use, user, tool and expiry; confirm; revalidation (file, usage, metadata); the four locks; full-size and size URLs; Elementor escaped and plain; featured images; Groups; unsupported references (ID, usage type, site logo, unmapped size, unverified scan); trash and restore; trash off; a failed link update keeping the copy and not stopping the others; lock release; no paths or hashes; and the admin merge unchanged.
  - Mutation checks: removing the identical-file, ID-reference, usage-verification, fingerprint, reference-error or lock check makes the matching test fail.
  - The exact tool-list tests (`GetExifCaptionAbilityTest`, `GetExifCaptionMcpExposureTest`) were updated.
  - Full Pro suite: 586 tests, all passing. PHPCS clean for the new files.
- **Limitations:**
  - **ID-based use refused:** copies used by ID (block editor images, galleries, WooCommerce galleries, custom fields, Elementor by ID) are refused, not rewritten, so many block-editor uses are refused.
  - **Restore:** restoring a merged copy does not move links back.
  - **Kept image's usage:** its usage records are not updated until the next Used Where scan.
  - **Admin merge:** still deletes permanently, and its AJAX access checks (`upload_files` only, no per-attachment check) are a separate, open security task.
  - **Lock races:** locks are not atomic across operations.
  - **No live-site test.**

### F11 Rename

- **Owner:** typed rename Free; rename strategies (post title, SKU, alt text), prefix/suffix and auto-rename on upload Pro.
- **MCP status:** Partially Implemented: typed renames (Free) implemented 2026-11-21; Pro rename strategies are not exposed.
- **Tools:** `tsmlt/preview-media-rename`, `tsmlt/rename-media`, `tsmlt/get-media-rename-status`, `tsmlt/cancel-media-rename`.
- **Missing operations:** Pro rename strategies (post title, SKU, alt text, AI names) and server-side AI rename (**Not planned** for MCP; D18).
- **Tests:** `PreviewMediaRenameAbilityTest`, `RenameMcpAbilitiesTest`, `McpRenameToolsExposureTest`, `MediaRenamerTest`, `RenameElementorTest`.

#### Engine hardening (implemented 2026-11-21, approved; Free only; no MCP tools)

- **Engine:** `Fns::wp_rename_attachment()` is now a wrapper (same signature, returns whether the file was renamed) around `Modules/Rename/MediaRenamer::rename( $id, $name, $user_id )`, which returns a structured result: `status` renamed, unchanged, refused or failed; `code`; `sizes` regenerated, kept or none; `content` with changed post IDs and update errors; `copies`; `backups`. The Free Rename page, Pro bulk AI apply and Pro CSV import all go through it.
- **Checks:** library (`inherit`) attachment, `edit_post` for the user, a local file.
  - **Locks:** the whole rename holds `RegenerateLock` (so compression, conversion and EXIF removal stay out) and refuses images held by compression, conversion or EXIF removal (`locked`).
- **Files:**
  - **What is renamed:** the attached file and, for scaled and rotated uploads, the full-size original. The attached copy keeps its `-scaled` / `-rotated` suffix, and `_wp_attached_file`, metadata `file` and `original_image` are updated.
  - **No overwriting:** files are moved with no-overwrite moves (hard link, then the old name is removed; a checked rename where links are unsupported). A taken name gets a number (`name-1`): never on disk, never refused by `wp_unique_filename()` for the plain name, never used or claimed by any attachment. If the attached file cannot be moved, the original is moved back and nothing changes (`rename_failed`).
  - **Missing original:** a recorded original that is missing refuses the rename (`original_missing`).
- **Sizes:**
  - **How:** rebuilt from the renamed original by `RegenerateThumbnails::rebuild_held()`, the hardened engine run under the caller's lock: temporary files, the on-upload automation off, no core `create` metadata pass.
  - **Old files:** old size files are removed only once replaced and unreferenced; disabled and unregistered size files are kept.
  - **On failure:** the files keep their new names, while the old sizes and their records stay and point at existing files (`sizes: kept`, with a code).
- **Compression:**
  - **Backups:** the attached file's and original's backups move to the new relative paths; backups of replaced sizes are removed.
  - **Record:** the compression record keeps only the full-size entry (rebuilt sizes count as not compressed and can be compressed again), with totals recomputed and `original.file` updated.
- **Conversion:**
  - **Which copies:** only copies that are positively the image's own (`ConversionOwnership`, worked out before renaming) are touched. The full-size copy is renamed; a size copy is renamed when its size was rebuilt with the same dimensions, otherwise removed.
  - **Untouched:** anything else, including a record naming another attachment's file, is left alone.
  - **Record:** the conversion record and its source path are updated.
- **Content:**
  - **Replacement:** old URLs (main, original, each size) are replaced with the existing replacement (post content and excerpts, Elementor data) through `Fns::replace_image_url_tracked()`, which reports changed posts and database errors separately from the file rename.
  - **Elementor:** data is replaced in both escaped and plain forms, and Elementor's CSS and element caches are cleared only for the posts that changed. The site-wide cache deletion was removed.
- **Strategy keywords:** `RenameModule::handle_rename()` never uses an unresolved strategy keyword (`bulkRenameByPostTitle`, `bulkRenameBySKU`, `bulkRenameByAltText`) as a file name; without Pro, or without a title, SKU or alt text, nothing is renamed.
- **Bulk:**
  - **Job:** `Modules/Rename/RenameJob` takes 1–20 items (ID and name), one job at a time with a job ID, in WP-Cron batches of 5 (`tsmlt_rename_tick`).
  - **Checks:** each item is checked when processed for the user who started it.
  - **Progress and cancel:** per-item results as codes, progress, and cancel by job ID.
  - **UI:** not wired to the admin page yet; the page still renames item by item synchronously.
- **Tests:**
  - `MediaRenamerTest` (12) and `RenameElementorTest` (1, separate process), all passing.
  - Mutation checks: removing the content-error count, the keyword refusal or the backup move makes the matching test fail.
  - Full suites (2026-11-21): Free integration 1341 (5 skipped), Free unit 135, Pro 558, all passing.
- **Limitations:**
  - **Preview names:** the preview's `proposed_filename` does not show the kept `-scaled` / `-rotated` suffix (to align when MCP rename is built).
  - **Non-image files:** content URLs are still not updated (unchanged).
  - **Content coverage:** content replacement covers absolute URLs in post content, excerpts and Elementor only (not other URL forms, custom fields or other page builders), and only the database error is detected, not missed references.
  - **Rebuilt sizes:** they are re-encoded and lose earlier compression.
  - **Disabled-size files:** they keep their old names, unreferenced.
  - **Lock races:** locks are post meta and not atomic across operations.
  - **Pro writers (resolved 2026-11-21):** Pro's EXIF editor, GPS removal, bulk EXIF edit and undo now respect the shared operation locks, including `RegenerateLock`, which the rename holds (see F9, "Operation locks"; D19).

#### MCP rename tools (implemented 2026-11-21; Free only)

- **Preview** (`tsmlt/preview-media-rename`): input unchanged.
  - **Names:** they come from `MediaRenamer::plan()`, a read-only public method added to the engine. It runs the same checks and name choice as `rename()`, which now share private helpers (`precheck()`, `held_by_other_operation()`, `name_plan()`); the behaviour of `rename()` is unchanged, as `MediaRenamerTest` confirms. So `proposed_filename` shows the kept `-scaled` / `-rotated` ending and the number a taken name gets, and `proposed_original_filename` shows the original's new name.
  - **New statuses:** `duplicate_id`, `remote_storage`, `original_missing`, `locked`, `name_unavailable`.
  - **New response fields:** `ready_count`, `job_running`, `confirmation_token` (only with a ready item), `expires_at`, `message`.
  - **Annotations:** no longer read-only, because it stores the token.
- **Rename** (`tsmlt/rename-media`): takes `confirmation_token` and `confirm: true`.
  - **Refused while running:** while a bulk rename runs, the call is refused before the token is used.
  - **Checks again:** every item (permissions, status, file, locks, name); items no longer ready are reported with `queued: false`.
  - **One or several:** a one-item token renames in the request through `MediaRenamer::rename()`. Several start `RenameJob` and return its `job_id`.
  - **Results:** codes, new file name, sizes outcome, and changed posts the user may read.
- **Status and cancel:** `tsmlt/get-media-rename-status` is read-only. `tsmlt/cancel-media-rename` cancels only the current running rename job, by ID. Both report through `Modules/Rename/McpRename::job()`, which shows results only for attachments the user may read and counts the rest.
- **Tokens:** `ConfirmationToken`, prefix `tsmlt_rename_token_`; single use, bound to the user, 10 minutes.
- **Exposure:** all four are `meta.mcp.public` false: listed on the dedicated endpoint and the default server, refused by the default gateway.
- **Tests (2026-11-21):**
  - `RenameMcpAbilitiesTest` (15) and `McpRenameToolsExposureTest` (3); `PreviewMediaRenameAbilityTest` was updated for the token.
  - Mutation checks: removing the running-job check, the check at execution, or the status read filter makes the matching test fail.
  - Full suites: Free integration 1359 (5 skipped) and Free unit 135, all passing.
  - Pro: 557 of 558 pass. `GetExifCaptionMcpExposureTest::test_listed_and_callable_on_dedicated_server` lists the exact tool set and needs the three new tools added; that is a Pro test change, not made in this task.
- **Limitations:**
  - **Admin page:** it does not use the bulk job yet.
  - **Snapshot:** a name taken after the preview gets a number at execution, and the result reports it.
  - **Lock races:** locks are post meta and not atomic across operations; the rename holds the regeneration lock while it runs.

### F12 Compression

- **Owner:** Free engine, job, access checks and the four MCP tools. Pro unlocks custom quality, backups, generated sizes, no job limit, restore and compress-on-upload through Free's `CompressionAccess`; no Pro code is involved in the MCP tools.
- **Plugin:** `Modules/Compress/*` (AJAX `tsmlt_compression_*`, WP-Cron job `tsmlt_compression_tick`).
- **MCP status:** Implemented (2026-11-20).
- **Tools:**
  - `tsmlt/get-compression-status` (read-only per-image results);
  - `tsmlt/preview-compression` (1–20 images or the library; per-image status, effective settings, `reversible`, job limit, one-time token);
  - `tsmlt/compress-media` (token + `confirm: true`; rechecks everything; starts the existing asynchronous `CompressionJob` for one image, chosen images or the library);
  - `tsmlt/get-compression-job-status` (read-only job progress, no healing);
  - `tsmlt/cancel-compression-job` (compression jobs only, by `job_id`).
- **Missing operations:**
  - restoring originals from Pro backups (**Deferred**, D11);
  - retrying failed images (not in the approved scope; a new preview with the failed IDs does the same).
- **Tests:**
  - `GetCompressionStatusAbilityTest`;
  - `CompressionMcpAbilitiesTest` (18);
  - `CompressionMcpProOptionsTest` (2, separate process with a Pro licence stand-in);
  - `CompressionCrossLockTest` (6).
  - Full suites on 2026-11-20: Free integration 1248 (5 skipped), Free unit 135, Pro 558, all passing. Not tested on a real site.
- **Source:** `app/Modules/Compress/McpCompression.php` (shared rules, token, read-only job view), `app/Abilities/Media/PreviewCompression.php`, `CompressMedia.php`, `GetCompressionJobStatus.php`, `CancelCompressionJob.php`; reuses `CompressionJob::start()`, `start_library()`, `cancel()`, `get_library_stats()`, `CompressionSettings::resolve_run_settings()`, `AttachmentProcessor`.
- **Decisions and limits:**
  - **Checks:** the checks before a job starts are not atomic. An image that becomes locked in between is recorded by the job as failed, and its file is unchanged.
  - **Library runs:** images are checked when the job takes them.
  - **Permissions:** the job checks edit permission when it starts, not during the background run.
  - **Job sharing:** jobs are site-wide (any administrator can follow or stop them).
  - **Without WP-Cron:** the job progresses only while the Compress Images page is open.
- **Prerequisite fix (done 2026-11-20):**
  - **Refusal:** `AttachmentProcessor::process()`, the one entry point used by single compression, the bulk and library job and compress-on-upload, refuses an image while a conversion (`_tsmlt_conversion_lock`, 10-minute expiry) or an EXIF removal (`tsmlt_exif_strip_lock_{id}` through `ScanStartLock`, `ScanStartLock::SECONDS` expiry) holds it. It returns the error `tsmlt_compression_operation_locked`.
  - **Order:** the check runs after the compression lock is taken and before anything is read or written; that lock is released by the existing `finally`.
  - **Lock reading:** the other locks are only read. `ScanStartLock::is_held()` uses the same expiry rule as `acquire()`; a database error counts as held.
  - **Remaining limits:**
    - the checks are not atomic across the three lock types;
    - conversion itself does not check the compression or EXIF locks (unchanged).

### F13 Conversion (WebP/AVIF)

- **Owner:** Free engine, job and access checks. Pro adds AVIF, custom quality, generated sizes, no job limit and convert-on-upload, all through Free's `CompressionAccess` (Pro's only conversion code is `CompressApi::convert_on_upload()`).
- **Plugin:** `Modules/Compress/Conversion/*`, `ConvertModule`; AJAX `tsmlt_conversion_*` (`convert_single`, `start`, `start_library`, `get_library_status`, `get_attachment`, `get_bulk`, `delete`, `regenerate`, `get_capabilities`, `save_settings`); the shared `CompressionJob` (WP-Cron `tsmlt_compression_tick`).
- **MCP status:** Implemented (2026-11-20).
- **Tools:**
  - `tsmlt/get-conversion-status` (read-only, unchanged);
  - `tsmlt/preview-media-conversion` (1–20 IDs; `formats` webp/avif, `quality`, `generated_sizes`, `include_converted`; per-image `ready`, `already_converted`, `same_format`, `unsupported`, `file_missing`, `name_conflict`, `locked`, `not_found`, `forbidden`; effective settings; one-time token);
  - `tsmlt/convert-media` (token + `confirm: true`; refused while a compression or conversion job runs, keeping the token; rechecks licence, formats, settings and every image; starts `CompressionJob::start_conversion()`);
  - `tsmlt/cancel-conversion-job` (matching running conversion job only).
  - Progress: the existing `tsmlt/get-compression-job-status` (`job_type: conversion`); no second status tool.
  - Code: `app/Modules/Compress/Conversion/McpConversion.php` and the three classes in `app/Abilities/Media/`; tokens through the shared `ConfirmationToken`.
- **Pro options without a licence:** AVIF, custom quality and `generated_sizes: true` are refused (`tsmlt_pro_required`), never replaced by WebP or the preset quality; unsupported formats are refused (`tsmlt_conversion_format_unavailable`). No Pro code was changed.
- **Missing operations:**
  - whole-library conversion, deleting copies, regenerating copies (**Excluded** from MCP, D14/D15; admin page only).
- **Tests:**
  - `GetConversionStatusAbilityTest`;
  - `ConversionMcpAbilitiesTest` (12);
  - `ConversionMcpProOptionsTest` (2, separate process with a Pro licence stand-in);
  - engine: `ConversionEngineHardeningTest` (12).
  - Mutation checks: removing the AVIF licence refusal, the running-job check before the token is used, or the name-conflict check makes the matching tests fail.
  - Full suites (2026-11-20): Free integration 1308 (5 skipped), Free unit 135, all passing.
  - Pro's pinned tool-list test was updated for these tools on 2026-11-21 (`41d5ccb`); Pro 558, all passing then.
  - Not tested on a real site.
- **Investigation:** Investigated 2026-11-20 (read-only audit; nothing changed in the repositories).

#### Verified existing behaviour (from the code)

- **Single image:** `ConvertModule::convert_single()` runs synchronously: `validate_attachment()` (`edit_post`), `ConversionSettings::resolve_run_settings()`, then `AttachmentConverter::convert()`.
- **Selected images:** `start_job()` calls `CompressionJob::start_conversion( $ids )`, a job of type `conversion` in the same state option and tick as compression. It is refused while any compression or conversion job is running, and the Free limit (20, as for compression) is applied.
  - **Eligibility at start:** `validate_attachment()`, with no post-status check, so trashed attachments are accepted.
- **Library:** `start_library_job()` queues every `inherit` JPEG/PNG/WebP without conversion data (or all with `include_converted`), newest first, at most the Free limit. Its queries and `get_library_stats()` use `$wpdb` directly (prepared), against the project's no-raw-SQL rule.
- **Progress, cancel and retry:** the shared `CompressionJob` (`get_progress()`, which can save corrections; `cancel()`; `retry()`; `reset()`). There is no separate conversion job ID scheme.
- **Per image (`convert()`):**
  1. structural check (JPEG/PNG/WebP, file exists);
  2. own lock `_tsmlt_conversion_lock` (post meta, 10-minute expiry), then a refusal while the regeneration lock is held;
  3. per format, a temporary file from `wp_tempnam()` in the image's folder, written by Imagick (EXIF/IPTC stripped, ICC kept) or GD (no metadata), checked (real image, expected MIME, same dimensions), then `rename()` to `{dir}/{name}.{webp|avif}`;
  4. optionally the same for every generated size (Pro);
  5. the result is saved in `_tsmlt_image_conversion_data` (source size and modification time, per-format status, file and size, `last_error`).
- **Source:** the attached file (the scaled copy for large uploads), never modified. A WebP source converted to WebP is skipped (`same_format`).
- **Stale copies:** `ConversionMetadata::is_stale()` compares the source's size and modification time with what was recorded.
- **Delete copies:** `ConversionManager::delete_conversions()` deletes every file recorded in the conversion data (paths contained in uploads; no `../`).
- **Regenerate copies:** `regenerate()` deletes the existing copies first, then converts.
- **Locks between operations:**
  - Compression (`is_busy_elsewhere()`), Free EXIF removal and regeneration refuse an image while the conversion lock is held.
  - Conversion refuses only its own lock and the regeneration lock, **not** the compression lock or the EXIF-strip lock.
- **On upload (Pro):** Pro's `convert_on_upload` (on `wp_generate_attachment_metadata`) runs when licensed and enabled; it is skipped when conversion data exists or the conversion lock is held, and it is off during thumbnail regeneration (D12).
- **Free/Pro boundaries** (`CompressionAccess`, `ConversionSettings::resolve_run_settings()`):
  - **Free:** WebP, preset quality per format, the 20-image job limit.
  - **Pro only:** AVIF (`can_convert_avif()`), custom quality (`can_use_custom_conversion_quality()`), generated sizes (`can_convert_generated_sizes()`), convert on upload, no limit.
  - **Without an active licence:** Pro-only values are dropped silently, not refused: an AVIF request becomes WebP only, or "no formats".
- **Serving:** `ModernImageDelivery` swaps image URLs to the best converted format the browser accepts.

#### Confirmed by a throwaway test (run outside the repository, 2026-11-20)

- **C1, output-name collision (data loss):**
  - **Overwrite:** converting `collide.jpg` wrote `collide.webp` over a separate WebP attachment with that file name in the same folder. Its 100×50 image became the 640×480 converted image.
  - **Delete:** deleting the JPEG's converted copies then deleted that other attachment's main file.
  - **Cause:** the output name is `{name}.{format}` and the final `rename()` replaces whatever exists.
  - **Other cases:** the same happens when two sources share a base name (`photo.jpg` and `photo.png`) and for generated sizes (`photo-150x150.webp`).

#### Other findings (from the code, not exercised)

- **C2, delete trusts the record:** `delete_conversions()` deletes any file the conversion data names, without checking it is not another attachment's file. After C1 this deletes real media.
- **C3, missing lock checks:** conversion does not refuse the compression or EXIF-strip lock. Compression and EXIF removal replace the source atomically, so conversion reads either version; a copy made from the old bytes is then reported stale. There is no data loss (copies carry no EXIF), but it is inconsistent with the other operations.
- **C4, trashed attachments:** they can be queued (`validate_attachment_file()` has no status check).
- **C5, deleted attachments:** no hook removes their converted copies, so they are left on disk.
- **C6, regenerate:** `regenerate()` deletes before converting; a failed conversion leaves no copies.
- **C7, library queries:** they use `$wpdb` directly (project rule).
- **Not reversible as such:** copies are extra files and the source is untouched (apart from C1), so deleting the copies undoes a conversion. With C1, conversion can destroy another attachment's file.

#### Existing MCP coverage

- **`tsmlt/get-conversion-status`:**
  - **Input:** `ids` (1–50).
  - **Per image:** `completed`, `partial`, `skipped`, `failed`, `not_converted`, `unsupported`, `not_found`, `forbidden`; `stale`, `converting`; per-format status, size, saving, quality and time; no paths or error texts.
  - **Read-only:** `get_many_for_display()`, never the library status read. Reusable as is for per-image results after a conversion run; its documentation matches the code.
- **`tsmlt/get-compression-job-status`** (F12) already reports conversion jobs (`job_type: conversion`), read-only.
- **`tsmlt/cancel-compression-job`** refuses conversion jobs.

#### Prerequisites before any MCP execution (decisions needed; none approved)

- **P1 (C1, C2):**
  - **Writing:** never overwrite a file that is not this attachment's own recorded copy. Either refuse (`name_conflict`), or name copies so they cannot collide (for example `photo.jpg.webp`, which needs `ModernImageDelivery` and existing data to follow).
  - **Deleting:** only delete files recorded as this attachment's copies, and never another attachment's attached or size file.
- **P2 (C3):** conversion refuses images under the compression or EXIF-strip lock, as the other operations already refuse the conversion lock.
- **P3 (C4):** conversion jobs accept only `inherit` attachments.

C5–C7 are not needed for the minimum MCP scope below.

#### Engine hardening (implemented 2026-11-20, approved; Free only; no MCP tools)

- **P1, ownership and collisions** (`Modules/Compress/Conversion/ConversionOwnership.php`):
  - **Naming unchanged:** copies are still named `{name}.webp` and `{name}.avif`.
  - **What counts as this attachment's copy (all must hold):**
    - inside uploads;
    - named exactly as its attached or size file converts;
    - recorded in its conversion data for that format;
    - used by no attachment as an attached file, size, full-size original or pre-edit backup;
    - recorded by no other attachment's conversion data;
    - an image of that format.

    The ownership checks use `Fns::DB()` lookups on post meta, no raw SQL.
  - **Before writing:** a target that is anything else is refused with `tsmlt_conversion_name_conflict`, also when no file is there yet but another attachment claims the name.
  - **Making copies:** every output is made into a `wp_tempnam()` file in the same folder and validated as before.
  - **Placing copies:** the target is checked again immediately before it is placed.
    - **Own copy:** replaced by `rename()`.
    - **Free name:** taken with a hard link (`link()`, which fails if the name appeared meanwhile), then the temporary name is removed. Where hard links are unsupported, the name is checked again and the file renamed.
  - **Recording results:**
    - A refused full-size copy fails the format (`reason: tsmlt_conversion_name_conflict`); a refused size fails only that size.
    - If all formats are refused, the error code is `tsmlt_conversion_name_conflict`.
    - A copy that could not be placed keeps its old record (the old file is still there).
  - **Same-format sizes:** a size already in the target format (for example a WebP size of a WebP source) is skipped (`unsupported_source`) instead of being converted over itself.
- **P1, deletion:**
  - `ConversionManager::delete_conversions()` deletes a recorded file only when it is this attachment's own copy. Anything else is kept and counted in the new `kept`.
  - The record is dropped either way.
- **P2, locks:**
  - Conversion takes its own lock, then refuses (`tsmlt_conversion_operation_locked`) an image under the compression lock, the regeneration lock or the Free EXIF-strip lock (`AttachmentConverter::is_busy_elsewhere()`).
  - Compression, Free EXIF removal and regeneration still refuse a converting image.
  - Every check refuses rather than waits; the lock is released in `finally`.
- **P3, library only:** `validate_attachment_file()` accepts only `inherit` attachments. This covers single conversion, job creation (`filter_eligible_for_conversion()`), each image when its batch is processed, deleting and regenerating copies, and Pro's convert on upload (uploads are `inherit`).
- **C6, regenerate copies:**
  - `ConversionManager::regenerate()` uses `AttachmentConverter::regenerate_copies()`: every new copy is made and checked first.
  - If any output (a full-size copy or a size) fails, nothing is replaced, and the old copies and record stay.
  - Otherwise copies are placed. Old copies of the format that were not made again are deleted only when they are this attachment's own.
  - The record is saved after placement.
- **C7, SQL:** `ConvertModule::get_library_stats()` and `start_library_job()` use `Fns::DB()` (the NOT IN subquery through a prepared `raw()` fragment, as in `CompressionJob::start_library()`), with the same criteria, newest-first order and limit.
- **Unchanged:**
  - Free WebP with preset quality;
  - Pro AVIF, custom quality, generated sizes and convert on upload through `CompressionAccess`. Checked with licensed and unlicensed Pro in a throwaway test run under Pro's test setup, not committed.
  - Image delivery, and `tsmlt/get-conversion-status`.
- **Limitations:**
  - **C5 now blocks conversion (new behaviour):** copies left by deleted attachments are orphaned, recorded by no attachment and unrecognised. A new upload whose copy name matches one is refused with `name_conflict` instead of overwriting it, so it gets no copy. Reproduced with Pro's convert on upload in the test site. Fixing C5 (delete owned copies when an attachment is deleted) and/or removing existing orphans needs a decision.
  - **Not one atomic step:** placing several files (full size, then sizes) is not atomic as a whole. A size that fails to be placed keeps its old record; the record always matches the disk.
  - **Ownership and the record:** ownership depends on the conversion record. A copy whose record was lost is no longer recognised, so it is kept and conversion to that name is refused.
  - **Fallback race:** where hard links are unsupported, a name created between the last check and the rename can still be replaced (narrow race; documented).
  - **Lock races:** locks are post meta; operations starting in the same instant are not excluded.
  - **Lookup cost:** the ownership lookups use `LIKE` on post meta, a few queries per output file; acceptable for background jobs, slower on very large sites.
  - **Pro's own JPEG writers (resolved 2026-11-21):** Pro's EXIF editor, GPS removal, bulk EXIF edit and undo now respect the conversion lock and the other shared operation locks (see F9, "Operation locks"; D19).
- **Tests (2026-11-20, local WordPress test suite, PHP 8.5):**
  - `ConversionEngineHardeningTest`: 12 tests, all passing.
  - Mutation checks: removing the collision check, the ownership check on delete, all-or-nothing regeneration, the trash check or the lock check makes the matching tests fail.
  - Related compression, conversion, EXIF and regeneration suites: 242, all passing.
  - Full Free integration 1294 (5 skipped) and unit 135, all passing.
  - Pro 558 with 1 failure: the pinned tool-list test (F14), unrelated to this change.
  - Not tested on a real site.

#### MCP design as implemented (approved 2026-11-20, D15)

Free; Pro options only through `CompressionAccess`, as for compression. Every tool requires `manage_options` in the permission check and in `execute()`, is not runnable through the default gateway, and returns no paths, file names or error texts.

- **`tsmlt/preview-conversion`:**
  - **Input:** `ids` (1–20); `formats` (`webp` and/or `avif`, one shared interface); optional `quality` per format, `generated_sizes`, `include_converted`.
  - **Pro options:** AVIF, custom quality and `generated_sizes: true` are **refused** without Pro (`tsmlt_pro_required`) rather than silently dropped.
  - **Per image:** `ready`, `already_converted`, `same_format` (WebP source with only WebP), `unsupported`, `file_missing`, `name_conflict` (after P1), `locked`, `not_found` (also trashed), `forbidden`.
  - **Also returned:** effective settings, job limit, `job_running`, and a one-time user-bound token (shared `ConfirmationToken`).
- **`tsmlt/convert-media`:** token + `confirm: true`; refused while any compression or conversion job runs (token kept); rechecks settings and images; starts `CompressionJob::start_conversion( $ready )`. One asynchronous job for one or many images; the synchronous `convert_single()` is not used.
- **Job status:** reuse `tsmlt/get-compression-job-status`, which already reports conversion jobs; no new status tool, unless a separately named alias is wanted.
- **`tsmlt/cancel-conversion-job`:** `job_id`; only a running job of type `conversion`; `CompressionJob::cancel()`.
- **Not proposed:** deleting copies and regenerating copies, until P1 makes deletion safe and C6 is addressed; whole-library conversion (below).

#### Whole-library conversion

Not recommended through MCP, consistent with D12 for thumbnails. It queues at most 20 images on Free but the whole library with Pro, its queries break the project's SQL rule (C7), and C1 can destroy files at scale. It stays available on the admin page.

#### Open questions for the project owner

1. ~~P1~~: decided 2026-11-20: refuse colliding names, keep the naming, delete only own copies (D14).
2. ~~P1–P3, C6, C7~~: implemented 2026-11-20 (D14).
6. **C5:** delete an attachment's own copies when it is deleted, and/or clean existing orphans, now that orphans block conversion?
3. **Job status:** reuse `tsmlt/get-compression-job-status`, or add `tsmlt/get-conversion-job-status`?
4. **Pro options:** refuse without Pro (as compression does), rather than drop silently?
5. **Delete and regenerate copies:** in scope later, after P1 and C6?

### F14 Thumbnails

- **Owner:** Free. Pro has no regeneration code (verified: no regeneration class, AJAX action or setting in Pro).
- **Plugin:** `Modules/Regenerate/RegenerateThumbnails.php`; AJAX `tsmlt_regenerate_start`, `_get_progress`, `_cancel`, `_batch`, `_get_status` (`Controllers/Hooks/Ajax.php`); WP-Cron `tsmlt_regenerate_tick` (`CronJobHooks`); UI calls in `src/js/Utils/Data.ts`.
- **MCP status:** Implemented (2026-11-20).
- **Tools:**
  - `tsmlt/get-thumbnail-status` (read-only);
  - `tsmlt/preview-thumbnail-regeneration` (1–20 IDs; per-image status, scaled or rotated original, compressed/converted, sizes made and disabled sizes kept; one-time token);
  - `tsmlt/regenerate-thumbnails` (token + `confirm: true`; rechecks images, originals, locks and sizes; starts the engine's fixed-ID background job);
  - `tsmlt/get-thumbnail-regeneration-status` (read-only job, recent per-image codes for readable images);
  - `tsmlt/cancel-thumbnail-regeneration` (matching running job only).
  - All in `app/Abilities/Media/` and `app/Modules/Regenerate/McpRegenerate.php`. The one-time token code is shared with compression in `app/Abilities/ConfirmationToken.php`.
- **Missing operations:**
  - whole-library regeneration (**Excluded** from MCP, D12; admin page only);
  - Pro GPS removal and Pro EXIF editor checking the regeneration lock (out of scope: no Pro change).
- **Tests:**
  - `GetThumbnailStatusAbilityTest`;
  - `RegenerationMcpAbilitiesTest` (15);
  - engine: `RegenerateThumbnailsEngineTest` (17), `RegenerateUploadContextTest` (1, separate process with a Pro licence stand-in), `RegenerateAjaxTest` (1).
  - Mutation checks: removing the sizes check, the running-job check before the token is used, or the read filter on status results makes the matching test fail.
  - With licensed Pro: a throwaway test run under Pro's test setup (not committed; no Pro change) used the four tools; a Pro custom image size was generated and Pro's compress on upload stayed out.
  - Full suites (2026-11-20): Free integration 1282 (5 skipped), Free unit 135, all passing.
  - Pro's pinned tool-list test was updated for these tools on 2026-11-21 (`41d5ccb`).
  - Not tested on a real site.
- **Investigation:** Investigated 2026-11-20 (read-only audit; nothing changed).

#### Behaviour before the 2026-11-20 engine hardening (audit, from the code)

- **Library only:**
  - There is no single-image or selected-images regeneration.
  - `start()` queues every `inherit` attachment whose MIME type starts with `image/`.
  - The job walks them by offset (`ORDER BY ID`, `LIMIT`/`OFFSET`), 50 per WP-Cron tick (filter `tsmlt_regenerate_tick_batch_size`, 1–200).
  - `tsmlt_regenerate_batch` lets the browser drive the same walk by offset (at most 50).
- **Per image:**
  1. a missing file, or an image the editor cannot open (for example SVG), is recorded as an error and skipped;
  2. `wp_generate_attachment_metadata( $id, get_attached_file( $id ) )` rebuilds every size;
  3. files of sizes in the old metadata that are no longer registered are deleted (`wp_delete_file`);
  4. the new metadata replaces the old one (`wp_update_attachment_metadata`).
- **Job state:**
  - The state is stored in option `tsmlt_regenerate_state`.
  - It records status (`idle`, `running`, `done`, `cancelled`), offset, total and counters, plus recent errors and recent successes holding the file name and the editor's error text.
  - The state has no job ID and no job type.
- **No running check:** `start()` does not check for a running job. It clears the tick and restarts from offset 0, wiping the previous progress. `cancel()` sets `cancelled`, and a tick already running re-checks the status before rescheduling.
- **Permissions:** `manage_options` through the AJAX guard only. There is no per-attachment check, and the cron run has no user.
- **No locks:** regeneration takes no lock and checks none (compression, conversion, EXIF removal). Compression, conversion and EXIF removal do not check for regeneration either.
- **Limits:** no Free/Pro limit and no Pro option. Pro influences regeneration only indirectly:
  - its custom image sizes (`add_image_size` in Pro `ActionHooks`) are registered sizes and so are generated;
  - its upload hooks on `wp_generate_attachment_metadata` also run during regeneration (below).

#### Verified in WordPress core (7.1.4 source), not exercised by a test here

- **Scaled originals:** `wp_create_image_subsizes()` sets `original_image` only when the file is above `big_image_size_threshold`. Regenerating from an attached `-scaled` file (at the threshold) produces metadata without `original_image`: the full-size original stays on disk but is no longer linked (`wp_get_original_image_path()` then returns the scaled file). The same applies to `-rotated` uploads. The sizes are made from the already-scaled JPEG.
- **Disabled sizes:** `wp_get_registered_image_subsizes()` uses `get_intermediate_image_sizes()`, so sizes disabled in Media Library Tools (removed only through `intermediate_image_sizes_advanced`) still count as registered. They are not regenerated, their files are not deleted, and they are dropped from the metadata: the files are left on disk unreferenced.
- **Progressive saves:** core saves the metadata after each size (`_wp_make_subsizes()`), so a failure part-way leaves partial metadata. Size files are written in place with the same names, not through a temporary file.
- **Upload hooks fire:** the `wp_generate_attachment_metadata` filter runs with context `create`, as on upload. Pro's compress-on-upload (skipped once compression data exists), convert-on-upload and GPS-removal-on-upload hooks run during regeneration when their settings are on.

#### Data-integrity and security findings

- **Not reversible:**
  - deleted unregistered-size files are gone;
  - regenerated sizes replace compressed ones (compression data and compression backups of sizes become stale);
  - WebP/AVIF copies of sizes become outdated;
  - a lost `original_image` link is not restored.
- **Concurrent operations:** none is excluded. Compression, conversion or EXIF removal on the same image can interleave with regeneration (last writer wins).
- **Offset walk:** media added or deleted during a run shifts the offsets, so images can be skipped or processed twice.
- **Changed dimensions:** when a registered size's dimensions change, the old files of that size are not deleted (only unregistered sizes are).
- **Output:** stored errors contain file names and raw editor messages; MCP must not return them.

#### Prerequisites identified by the audit (all implemented 2026-11-20, see below)

- **P1:** a per-attachment regeneration entry point and an ID queue, so one image, chosen images and the library can share one asynchronous job.
- **P2:** a running-job check and a job ID in `start()`; a second start must not restart a running job.
- **P3:** lock coordination. Regeneration skips images locked by compression, conversion or EXIF removal, and those operations refuse images being regenerated (needs a regeneration lock).
- **P4:** scaled and rotated uploads. Regenerate from `wp_get_original_image_path()` and keep `original_image`, or refuse those images.
- **P5:** disabled sizes. Decide whether their files are deleted or kept and still recorded.
- **P6:** upload hooks. Decide whether Pro's compress, convert and GPS-removal-on-upload hooks may run during an MCP regeneration.

#### Engine hardening (implemented 2026-11-20, approved; Free only; no MCP tools)

- **P1, ID queue:**
  - `RegenerateThumbnails::start( ?array $ids )` queues the given IDs (job type `selected`, one or more images) or every library image (`library`, admin page only).
  - The queue is fixed at start; media added or deleted meanwhile cannot shift it.
  - WP-Cron works through it, 50 per tick (`run_batch()` takes the batch off the queue and saves it before processing).
  - The page-driven `tsmlt_regenerate_batch` still walks the library by position, through the same per-image pipeline `regenerate_attachment()`.
- **P2, lifecycle:**
  - Each job has an ID (`tsmlt_regen_` + 16 hex), a type, an owner, the queue and counters (`success_count`, `skipped_count`, `failed_count`, `errors_count`, `offset` = processed).
  - `start()` refuses while a job is running (`tsmlt_regenerate_running`). The admin AJAX start then returns the running job's progress, so the page re-attaches instead of restarting.
  - A job is stale (`stale` in progress) when it is running, has no tick scheduled and has made no progress for 15 minutes; a new start may replace it.
  - `cancel( $job_id )` acts only on the matching running job.
  - `run_batch()` re-reads the job before each image and stops on cancel or replacement; counters are written only to the same job.
- **P3, locks both ways:**
  - **New lock:** `RegenerateLock` (post meta `_tsmlt_regenerate_lock`, `time:token`, 10-minute expiry, released only by its holder, in `finally`).
  - **Regeneration side:** it takes its lock, then refuses (`locked`) an image under the compression lock, conversion lock or EXIF-strip lock.
  - **Other side:** compression (`is_busy_elsewhere()`, `tsmlt_compression_operation_locked`), conversion (`tsmlt_conversion_operation_locked`, checked after taking its own lock) and Free EXIF removal refuse an image being regenerated.
  - **No deadlock:** every check refuses rather than waits.
- **P4, originals:**
  - With `original_image` recorded, sizes are made from `wp_get_original_image_path()` (same folder, recorded name, readable), rotated by EXIF as core does. `original_image`, the original and the scaled or rotated copy are kept unchanged.
  - Otherwise the image is refused with `original_missing`, and nothing changes. Sizes are named after the original, as on upload.
- **P5, disabled sizes:**
  - **Disabled sizes:** not generated or recorded; their files are kept.
  - **Sizes no longer registered:** keep their record and file.
  - **Changed dimensions:** when a regenerated size changed dimensions, its old file is deleted, unless any record, the attached file, the original, a disabled size or a pre-edit backup uses that name.
- **P6, not an upload:**
  - **Context:** while an image is rebuilt, `RegenerateThumbnails::is_regenerating()` is true and the Free settings Pro's upload hooks read report the on-upload automation as off: `CompressionSettings::get_settings()['auto_compress_on_upload']`, `ConversionSettings::get_settings()['auto_on_upload']`, `Fns::get_options()['auto_remove_gps_on_upload']`.
  - **Hooks still run:** the `wp_generate_attachment_metadata` filter still runs (context `update`) for other plugins.
  - **Second layer:** Pro's compress and convert hooks are also stopped by the regeneration lock.
  - **No Pro code changed.** Verified against the real Pro hooks with a throwaway test run under Pro's test setup (not committed): a licensed site with compress and convert on upload left a regenerated image uncompressed and unconverted, while a normal upload with the same settings was compressed.
- **Authorization:**
  - The job stores the starting user.
  - Each image is checked when it is processed with `user_can( owner, 'manage_options' )` and `user_can( owner, 'edit_post', id )`; the cron run has no current user.
  - Trashed, missing, deleted, non-image and remote-storage attachments are reported with codes; nothing about them changes.
- **Integrity:**
  - **Temporary files:** the source is copied to a temporary file in the image's folder (`.tsmlt-regen-*`); every size is made from it with the editor's `make_subsize()` and checked (name, dimensions) before any file is replaced.
  - **On failure:** if any size fails, all temporary files are deleted and files and metadata are unchanged (`generation_failed`).
  - **Replacement:** files are replaced one by one with same-folder `rename()`.
  - **Metadata:** written once at the end, with refreshed `filesize`.
  - **Clean-up:** temporary files are removed in `finally`; leftovers older than an hour (from a request that died) are removed by the next rebuild in that folder.
  - **User-facing errors:** fixed messages per code, with no paths or raw editor errors (the admin page still shows file names).
- **Limitations:**
  - **Not one atomic step:** replacing several size files is not atomic as a whole. If a rename fails part-way, the sizes already moved keep their new files, the others keep their old files and records, the metadata matches the disk, and the image reports `replace_failed`.
  - **Lock races:** locks are post meta, and the checks are not atomic across operations (the same as the compression and conversion locks), so operations starting in the same instant are not excluded.
  - **Two drivers:** two drivers reading the job at the same moment can take the same batch; the per-image lock prevents double processing, but counts can be off.
  - **Pro's own JPEG code (resolved 2026-11-21):** Pro's EXIF editor, GPS removal, bulk EXIF edit and undo now respect the regeneration lock and the other shared operation locks (see F9, "Operation locks"; D19).
  - **Dropped records:** sizes now too large for the image, and disabled sizes, are dropped from the records but their files stay.
  - **Not reversible:** replaced thumbnails are not kept, compressed sizes are replaced by uncompressed ones, and WebP/AVIF copies of sizes may become outdated.
  - **Page-driven batch:** still position-based.
- **Tests (2026-11-20, local WordPress test suite, PHP 8.5):**
  - Engine, upload-context and AJAX tests: 19 tests, all passing.
  - Mutation checks: disabling the original-source rule, the lock check or the failure handling makes the matching tests fail.
  - Full Free integration 1267 (5 skipped), Free unit 135, Pro 558: all passing.
  - Not tested on a real site.

#### MCP design as implemented (approved 2026-11-20, D13)

All Free; no Pro-only behaviour. Every tool requires `manage_options` in the permission check and in `execute()`, is not runnable through the default gateway, and returns no paths, file names or error texts.

- **`tsmlt/preview-thumbnail-regeneration`:**
  - **Input:** `ids` (1–20) only; no library option.
  - **Per image:** `ready`, `not_found` (also trashed and unreadable), `forbidden`, `not_image`, `file_missing` (missing or unreadable), `remote_storage`, `original_missing` or `locked`, from the engine's own `RegenerateThumbnails::preflight()`. Also `upload_copy`, `original`, `compressed`, `converted`; globally the size names made and the disabled sizes kept. Not reversible.
  - **Token:** one-time, user-bound, 10 minutes, bound to the ready IDs and a fingerprint of the sizes. Changes nothing but the token.
- **`tsmlt/regenerate-thumbnails`:** token + `confirm: true`.
  - **Running job:** refused (token kept) while a regeneration job runs.
  - **Sizes changed:** refused when the sizes changed since the preview.
  - **Recheck:** each image is checked again; only ready ones are queued through `RegenerateThumbnails::start( $ids )`, never the page-driven batch path. Destructive: replaces size files.
- **`tsmlt/get-thumbnail-regeneration-status`:** read-only; never saves, restarts or advances the job.
  - **Job:** job ID, type, status, `stale`.
  - **Counts:** counts, percent, `tick_scheduled`, times.
  - **Results:** recent per-image results (ID, result, code) for images the user may read; the others are counted in `hidden`.
- **`tsmlt/cancel-thumbnail-regeneration`:** exact running job ID only; done and cancelled jobs are refused; uses `cancel( $job_id )`. Jobs are site-wide, as on the Regenerate page.

#### Open questions for the project owner

1. ~~P1–P6~~: decided and implemented 2026-11-20 (D12).
2. ~~Library scope through MCP~~: not offered (D12).
3. ~~MCP tool scope~~: approved and implemented 2026-11-20 (D13).
4. ~~Pro tool-list test~~: updated 2026-11-21 (`41d5ccb`).

### F15 AI metadata generation

- **Owner:**
  - **Plugin feature:** single image Free (`Controllers/AI/AiApi.php`, AJAX `tsmlt_ai_generate`); bulk Pro (`Modules/BulkAi/*`, AJAX `tsmlt_bulk_ai_*`).
  - **MCP image preview:** Free.
- **MCP status:**
  - **Excluded:** server-side AI generation (D16).
  - **Supported instead:** a client-side workflow.
- **Investigation:** Investigated 2026-11-21 (read-only audit of `AiApi`, Pro `BulkAiJob`/`BulkAiApply` and Pro `FilterHooks::ai_prepare_image()`).
- **Why server-side AI is excluded from MCP:**
  - **Cost:** every call spends the site's provider budget.
  - **Data leaving the site:** site name and tagline, file names, current text, parent post content (any status) and, with Pro image sending, the full original image go to a third party.
  - **Errors:** raw provider error texts can include parts of credentials.
  - **Design:** it duplicates what an MCP client's own model does, and goes against the guide's architecture decision ("prefer client-side AI").
- **Supported workflow:**
  1. `tsmlt/search-media` (`missing_alt: true`, `mime_type: "image"`);
  2. `tsmlt/get-media-details` (current text, attached post title);
  3. `tsmlt/get-media-image-preview` (the image, for the client's vision model);
  4. `tsmlt/update-media-metadata` (save, one image per call).

  Documented in the guide: "AI-written alt text and captions — client-side".
- **Tools:** `tsmlt/get-media-image-preview` (Free, read-only, implemented 2026-11-21).
  - **Code:** `app/Abilities/ImagePreview.php` (service) and `app/Abilities/Media/GetMediaImagePreview.php`.
  - **Contract:** input `id` (one attachment). It returns the MCP Adapter's image result shape (`type: image`, raw bytes, `mimeType`), which the adapter 0.7.0 turns into an `image` content block (`ToolsHandler`, image branch; `ContentBlockHelper::image()`). The output schema is empty, so no `outputSchema` is listed; WordPress skips output validation for an empty schema (`WP_Ability::validate_output()`), confirmed by the tests.
  - **Rules:**
    - `manage_options`, `read_post`, library (`inherit`) JPEG/PNG/WebP only;
    - a local file inside uploads (realpath check; no streams or offloaded files);
    - the smallest sufficient source file;
    - a source of at most 50 MB and 50 megapixels before decoding;
    - turned upright and downscaled to at most 1024 px.
  - **Metadata removal:** after re-encoding, all metadata is removed and the result checked for each format. JPEG uses `JpegMetadataStripper::strip_bytes()` and keeps only colour headers; PNG keeps only image and colour chunks; WebP drops the EXIF/XMP chunks and their flags. WordPress's Imagick editor strips metadata only when it resizes, so this step is required.
  - **Size:** at most 1,000,000 bytes, trying 768 then 512 px. The `tsmlt_mcp_image_preview_max_bytes` filter can only lower it. Over the limit, or on any failure, nothing is returned, never the original.
  - **Temporary files:** in the system temp folder, always removed.
- **Tests:**
  - `GetMediaImagePreviewAbilityTest`: 11 tests, including over the real MCP endpoint.
  - With the stripping removed, the JPEG tests fail: the GD encoder leaves a comment segment.
  - Full suites: Free integration 1328 (5 skipped) and unit 135, all passing.
  - Pro 558 with 1 failure: its pinned tool-list test needs `tsmlt-get-media-image-preview` added (Pro tests not changed; approval needed).
- **Limitations:**
  - **Engine coverage:** verified with GD only. The ImageMagick path uses the same removal and checks, and the removal is tested directly on metadata-laden bytes, but it was not run with ImageMagick.
  - **Clients:** display depends on the MCP client; not tested with a real client.
  - **One image per call:** no bulk preview (by scope).

### F16 Rubbish files and empty folders

- **Owner:** scanning Free (`Modules/Rubbish/RubbishScanner.php`); delete, ignore and restore Pro (`Modules/Rubbish/RubbishApi.php`).
- **MCP status:** Deferred (roadmap row 6): it needs a decision on exposing file paths, which every MCP tool currently withholds.
- **Tools:** none.

### F17 Image sizes, settings and automatic behaviour

- **Owner:** Free (image sizes, settings, SVG upload, download shortcode); Pro (custom sizes, auto-rename on upload, front-end alt injection, AI image vision).
- **MCP status:** Not Investigated. These are configuration or automatic behaviour rather than media operations, so MCP is probably not applicable. That is not a recorded decision; confirm before marking Excluded.

## Decision log

Only decisions made by the project owner are recorded. Proposals awaiting approval are listed under the feature as "Proposed design (not approved)".

| ID | Date | Decision | Applies to |
|---|---|---|---|
| D1 | 2026-10-28 | Every ability requires `manage_options`, checked in the permission callback and again in `execute()`, plus per-item `read_post` / `edit_post`; inaccessible items are reported as not found. No file paths, raw metadata or custom fields in output. | All |
| D2 | 2026-11-18 | Every eligible tool is always listed directly on the MCP Adapter default server (the opt-in setting was removed); the dedicated endpoint stays as the alternative. Only read-only `search-media` and `get-media-details` are runnable through the generic gateway. | All |
| D3 | 2026-11-15, confirmed 2026-11-20 | MCP supports Trash and Restore only (the first unused-media trash tools were removed on 2026-10-28 as too risky and replaced by these safeguarded tools). Permanent deletion and Empty Trash are excluded: irreversible, and they can break content that still references the media. Trashing needs a preview, a one-time token and a verified usage scan. | F4 |
| D4 | 2026-11-18 | Renaming files through MCP is deferred until the existing rename's gaps are handled (size files deleted first, scaled original and backups keep old names, links updated only in some places). Pro rename strategies stay Pro. Superseded by D17 and D18 (2026-11-21). | F11 |
| D5 | 2026-11-18 | EXIF export, import, sync and full removal are not offered through MCP: export includes GPS; import and sync have no preview; full removal is irreversible and blocks undo. | F8, F9 |
| D6 | 2026-11-12 | MCP CSV import updates existing media only: no create-from-URL (server-side download), no file renaming, no custom fields. Preview, then a one-time, user-bound plan with conflict detection. | F6 |
| D7 | 2026-11-18 | Merging or removing duplicates through MCP is not planned; it needs its own safety design. Merge part superseded by D20 (2026-11-21); removal stays not planned. | F10 |
| D8 | 2026-11-20 | Pro 1.7.0 requires Free 2.5.0 (RC: `2.5.0-rc.1`) for full EXIF removal; Pro checks helper classes per feature and falls back or refuses with a notice. | F8, F9 |
| D9 | 2026-11-20 | This tracker is the single source of truth for MCP feature selection. One feature at a time; investigate, agree scope, get explicit approval, implement, verify, update the tracker, then move on. | All |
| D10 | 2026-11-20 | Before MCP compression execution, compression must refuse an image while a conversion or an EXIF removal is running on it (prerequisite fix, done). | F12 |
| D11 | 2026-11-20 | MCP compression scope: preview with a one-time, user-bound token, then the existing asynchronous job only (no synchronous path) for one image, chosen images or the library; Free compression without backups is allowed after an explicit "not reversible" preview and confirmation; Pro options only through existing licence checks; restoring Pro backups through MCP is deferred as a separate feature. | F12 |
| D12 | 2026-11-20 | Thumbnail regeneration engine, before any MCP tool: ID queue with job IDs and no restart of a running job; locks both ways with compression, conversion and EXIF removal; sizes from the full-size original for scaled and rotated uploads (refuse when it is missing); disabled-size files kept; regeneration is not an upload (on-upload automation off, without Pro changes); per-image `edit_post` at processing time; new files made and checked before replacing. Whole-library regeneration stays admin-only, never through MCP. | F14 |
| D13 | 2026-11-20 | MCP thumbnail regeneration: four Free tools; 1–20 images only, never the whole library; preview with a one-time, user-bound token; the hardened engine's fixed-ID background job only; refused while a regeneration job runs, keeping the token; no Pro code changes (Pro GPS removal and EXIF editor lock checks out of scope). | F14 |
| D14 | 2026-11-20 | Conversion engine, before any MCP tool: never overwrite or delete a file that is not positively the attachment's own copy (refuse with `name_conflict`, keep the existing naming); conversion refuses compression, EXIF-strip and regeneration locks; library (`inherit`) attachments only; regenerate copies all or nothing; no raw SQL in library selection. Whole-library conversion, deleting and regenerating copies stay out of MCP. | F13 |
| D15 | 2026-11-20 | MCP conversion: Free `tsmlt/preview-media-conversion`, `tsmlt/convert-media`, `tsmlt/cancel-conversion-job`; 1–20 images; Pro options refused without a licence, never silently downgraded; progress through the existing `tsmlt/get-compression-job-status`; no whole-library conversion, delete or regenerate copies through MCP; no Pro changes. | F13 |
| D16 | 2026-11-21 | Server-side AI generation is not offered through MCP (cost, data leaving the site, raw provider errors, duplicates the client's model). AI-written metadata is supported client-side: `tsmlt/search-media`, `tsmlt/get-media-details`, the new read-only `tsmlt/get-media-image-preview` (one downscaled, metadata-free image per call) and `tsmlt/update-media-metadata`. | F15 |
| D17 | 2026-11-21 | Rename engine (before any MCP rename): rename the attached file and its full-size original with no-overwrite moves, rebuild sizes with the hardened regeneration engine, move compression backups and the image's own converted copies, report content-update failures separately, clear Elementor caches only for changed posts, never use a strategy keyword as a file name; bulk renames as a background job of up to 20. MCP rename tools are still not implemented. | F11 |
| D18 | 2026-11-21 | MCP rename: four Free tools (preview with a one-time token, rename with confirmation, status, cancel); one item renamed in the request, 2–20 as the background `RenameJob`; preview names from the engine (`MediaRenamer::plan()`). Pro rename strategies and server-side AI rename are not exposed through MCP. | F11 |
| D19 | 2026-11-21 | Pro EXIF writes (editor, GPS removal, bulk edit, undo) respect the free plugin's shared operation locks (compression, conversion, `RegenerateLock` for rename and regeneration, the EXIF removal lock) and hold the EXIF lock while writing; no new lock mechanism. MCP tool status definitions stay unchanged (a refused image is `write_failed` or `failed`). | F9, F11, F13, F14 |
| D20 | 2026-11-21 | Duplicate merge is an existing Pro feature; its MCP integration is planned in the Pro repository, never moved or copied to Free. Detection and scan stay implemented. Duplicate removal is not planned. Before design: inspect the Pro merge, then verify preview, explicit confirmation, reference preservation, metadata handling and safe failure/rollback. | F10 |
| D21 | 2026-11-21 | Pro duplicate merge over MCP implemented as `tsmlt/preview-duplicate-merge` and `tsmlt/apply-duplicate-merge` (1–10 copies, synchronous) on `DuplicateApi`'s operations: identical files only, verified usage, ID-based and other unsupported references refused (never rewritten), links moved before the copy is trashed, never deleted; copies' files, WebP/AVIF copies and backups kept. The admin merge is unchanged. | F10 |

## Workflow

1. Read this tracker before any MCP investigation.
2. Do not investigate a feature again when its investigation is complete, unless a reason below applies; record the reason in the reopen log.
3. For a Partially Implemented feature, investigate only its missing operations.
4. Agree the scope and design, and get explicit approval, before writing an implementation prompt.
5. One feature at a time: finish, verify and record it before starting another.
6. Update this tracker at each milestone: investigation completed, scope approved, implementation started, implementation completed, tests completed, deferred or excluded decision made.
7. Mark Implemented only with verified implementation and test evidence.

**Reasons to reopen an investigation:** a reported bug, a material source change, a newly approved requirement, documentation that conflicts with the verified code, or an unresolved test or security finding.

## Reopen log

| Date | Feature | Reason |
|---|---|---|
| (none yet) | | |
