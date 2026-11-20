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
| F10 | Duplicate media | Free / Pro | Partially Implemented | Investigated | None (merge deferred) |
| F11 | Rename | Free / Pro | Partially Implemented | Investigated | None (renaming deferred) |
| F12 | Compression | Free (Pro options) | Implemented | Investigated | None (Pro backup restore deferred) |
| F13 | Conversion (WebP/AVIF) | Free | Partially Implemented | Not Investigated | Investigate the execution operations only |
| F14 | Thumbnails (regeneration) | Free | Partially Implemented | Not Investigated | Investigate regeneration only |
| F15 | AI metadata generation | Free / Pro | Planned | Not Investigated | Decide whether any tool is needed |
| F16 | Rubbish files and empty folders | Free / Pro | Deferred | Investigated | Decide on exposing file paths |
| F17 | Image sizes, settings and automatic behaviour | Free / Pro | Not Investigated | Not Investigated | Probably not applicable; confirm |

## Next feature

**Not chosen yet.** F12 Compression execution was implemented and verified on 2026-11-20. The remaining candidates are F14 Thumbnails (regeneration; the lower risk, since sizes are rebuilt from the original) and F13 Conversion (execution), both Not Investigated. Pick one and investigate only its missing operations before any design.

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

### F10 Duplicate media

- **Owner:** detection Free, merge Pro.
- **MCP status:** Partially Implemented.
- **Tools:** `tsmlt/find-duplicate-media`, `tsmlt/start-duplicate-scan`, `tsmlt/get-duplicate-scan-status`.
- **Missing operations:** merging duplicates (Pro, **Deferred**, D7).
- **Tests:** `FindDuplicateMediaAbilityTest`, `DuplicateScanAbilitiesTest`.

### F11 Rename

- **Owner:** typed rename Free; rename strategies (post title, SKU, alt text), prefix/suffix and auto-rename on upload Pro.
- **MCP status:** Partially Implemented.
- **Tools:** `tsmlt/preview-media-rename`.
- **Missing operations:** renaming files (**Deferred**, D4).
- **Tests:** `PreviewMediaRenameAbilityTest`.

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

- **Owner:** Free.
- **Plugin:** `Modules/Compress/Conversion/*` (AJAX `tsmlt_conversion_*`: convert single, start, start library, delete copies, regenerate, settings).
- **MCP status:** Partially Implemented.
- **Tools:** `tsmlt/get-conversion-status` (read-only).
- **Missing operations:** converting, deleting converted copies, regenerating.
- **Tests:** `GetConversionStatusAbilityTest`.
- **Investigation:** Not Investigated for execution. Investigate only the missing operations.

### F14 Thumbnails

- **Owner:** Free.
- **Plugin:** `Modules/Regenerate/RegenerateThumbnails.php` (AJAX `tsmlt_regenerate_*`).
- **MCP status:** Partially Implemented.
- **Tools:** `tsmlt/get-thumbnail-status` (read-only).
- **Missing operations:** regenerating thumbnails, job progress, cancelling.
- **Tests:** `GetThumbnailStatusAbilityTest`.
- **Investigation:** Not Investigated for regeneration.

### F15 AI metadata generation

- **Owner:** single image Free (`Controllers/AI/AiApi.php`, AJAX `tsmlt_ai_generate`); bulk Pro (`Modules/BulkAi/*`, AJAX `tsmlt_bulk_ai_*`).
- **MCP status:** Planned (roadmap row 4): decide whether any tool is needed, since the client's own AI can write text through `tsmlt/update-media-metadata`.
- **Tools:** none.
- **Investigation:** Not Investigated.

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
| D4 | 2026-11-18 | Renaming files through MCP is deferred until the existing rename's gaps are handled (size files deleted first, scaled original and backups keep old names, links updated only in some places). Pro rename strategies stay Pro. | F11 |
| D5 | 2026-11-18 | EXIF export, import, sync and full removal are not offered through MCP: export includes GPS; import and sync have no preview; full removal is irreversible and blocks undo. | F8, F9 |
| D6 | 2026-11-12 | MCP CSV import updates existing media only: no create-from-URL (server-side download), no file renaming, no custom fields. Preview, then a one-time, user-bound plan with conflict detection. | F6 |
| D7 | 2026-11-18 | Merging or removing duplicates through MCP is not planned; it needs its own safety design. | F10 |
| D8 | 2026-11-20 | Pro 1.7.0 requires Free 2.5.0 (RC: `2.5.0-rc.1`) for full EXIF removal; Pro checks helper classes per feature and falls back or refuses with a notice. | F8, F9 |
| D9 | 2026-11-20 | This tracker is the single source of truth for MCP feature selection. One feature at a time; investigate, agree scope, get explicit approval, implement, verify, update the tracker, then move on. | All |
| D10 | 2026-11-20 | Before MCP compression execution, compression must refuse an image while a conversion or an EXIF removal is running on it (prerequisite fix, done). | F12 |
| D11 | 2026-11-20 | MCP compression scope: preview with a one-time, user-bound token, then the existing asynchronous job only (no synchronous path) for one image, chosen images or the library; Free compression without backups is allowed after an explicit "not reversible" preview and confirmation; Pro options only through existing licence checks; restoring Pro backups through MCP is deferred as a separate feature. | F12 |

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
