=== Media Library Tools - AI Rename, Duplicate Finder & Media Cleaner ===
Contributors: tinysolution, mehediihasan
Tags: media library, rename, duplicate finder, media cleaner, alt text
Requires at least: 5.5
Tested up to: 6.9
Stable tag: 2.1.2
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The complete WordPress media library manager. Rename files, bulk edit metadata, find duplicates, clean rubbish files, track image usage, and generate AI content.

== Description ==

**Media Library Tools** is the most complete WordPress media management plugin available. It gives you full control over your media library — rename files for better SEO, bulk edit alt text and metadata, find and remove duplicate images, clean up orphaned files, track where every image is used, and generate AI-powered metadata using ChatGPT, Gemini, or Claude.

Whether you run a blog, WooCommerce store, or content-heavy site, Media Library Tools helps you keep your media library clean, organized, and SEO-optimized.

👉 [Documentation](https://docs.wptinysolutions.com/media-library-tools/) | [Get Pro](https://www.wptinysolutions.com/tiny-products/media-library-tools/) 👈

[youtube https://www.youtube.com/watch?v=L7F33DYnsZU]

---

== Why Media Library Tools? ==

Most WordPress sites accumulate hundreds or thousands of media files over time — poorly named, missing alt text, duplicated across posts, and cluttered with orphaned files that waste disk space. Media Library Tools solves all of this from a single, intuitive admin interface:

* **SEO** — Rename files to keyword-rich slugs, bulk-fill missing alt text, and generate AI-optimized metadata.
* **Performance** — Find and delete duplicate images, rubbish files, and unnecessary image sizes to reduce server storage.
* **Organization** — Categorize media, track image usage across posts and pages, and filter by any column.
* **Automation** — Auto-rename on upload, auto-fill metadata from post titles, and inject missing alt text on the frontend.

---

== Free Features ==

= Media Table =
* View your entire media library in a fast, paginated, and filterable table
* Sort by filename, date, alt text, caption, and description
* Filter by date range, media category, status, or keyword search
* Inline single-item editing — title, alt text, caption, and description
* Bulk edit multiple items at once with a single save action
* Move selected images to trash or restore them; permanently delete when ready
* Copy file URL to clipboard with one click

= AI Content Generator =
* Generate SEO-optimized alt text, title, caption, description, and filename for any media file
* Supports three AI providers: **ChatGPT** (GPT-4o, GPT-5, and more), **Google Gemini** (2.0 Flash, 1.5 Pro), and **Anthropic Claude** (Haiku, Sonnet, Opus)
* AI considers your site name, tagline, existing metadata, and attached post context for accurate, relevant results
* Free plan includes 1 AI suggestion per field

= Media File Renamer =
* Rename individual media files with SEO-friendly slugs directly from the rename table
* Bulk rename selected files using a custom name
* See the attached post for each file to make informed rename decisions
* Inline AI filename suggestions in the rename table
* Prefix and suffix apply automatically during bulk rename (Pro)

= Duplicate Image Finder =
* Scan your entire media library for duplicate files using MD5 hash comparison
* View duplicate groups with file count, individual file size, and total wasted space
* See exactly where each duplicate is used — in posts, pages, featured images, and custom fields
* Batch scanning with a visual progress bar — no timeouts on large libraries

= Rubbish File Finder (Media Library Cleaner) =
* Scan your WordPress uploads directory for orphaned files not registered in the media library
* Identify leftover files from deleted plugins, failed uploads, or manual file operations
* Filter rubbish files by file extension
* Mark files as "ignored" to exclude known safe files from future scan results
* Re-scan specific directories or clear the full scan history
* Automated background scanning via WordPress cron

= Image Usage Tracker (Used Where) =
* Track exactly where every image is used across your WordPress site
* Detect images in post content, featured images, Elementor data, and custom meta fields
* Batch scan the full site or enable passive frontend tracking to capture usage automatically
* Filter media by "Used" vs "Unused" status to find images that can be safely removed
* View a per-image breakdown: how many posts use it, which post types, and direct links

= Auto Metadata on Upload =
* Automatically set alt text, caption, and description when a new image is uploaded
* Choose between using the image filename or a custom default text as the alt text source

= SVG Upload Support =
* Safely upload SVG files with automatic sanitization — removes XSS vectors, remote references, and other security risks
* Proper width and height metadata generation for SVG files
* Maximum SVG file size: 500KB (filterable via `tsmlt_upload_max_svg_file_size`)

= Disable Image Sizes =
* Stop WordPress from generating unnecessary thumbnail sizes and save disk space
* Disable default sizes: Thumbnail, Medium, Large, Medium Large (768px), 1536×1536, 2048×2048, and the "Big" scaled size
* Works with sizes added by your theme or other plugins

= Media Categories =
* Organize your media library with a custom category taxonomy
* Bulk-assign categories from the media table
* Filter media by category using the dropdown filter
* Categories appear as clickable links in the native WordPress media list view

= Media Download Shortcode =
* Add styled download buttons anywhere on your site using a simple shortcode:
  `[tsmlt_download_button id='123' text='Download Now' class='my-btn' /]`
* Also supports direct file URLs:
  `[tsmlt_download_button url='https://example.com/file.pdf' text='Download PDF' /]`
* Forces a browser download prompt — works with images, PDFs, documents, audio, video, archives, and more

= Additional Columns in WordPress Media List =
* Alt text column (sortable)
* Caption column (sortable)
* Description column (sortable)
* Media Categories column
* Uploaded To post info in the media attachment modal

= CSV Export =
* Export your full media library to a CSV file — IDs, slugs, URLs, titles, alt text, captions, descriptions, and custom meta fields
* Select which columns to include before exporting
* Visual progress indicator during export

---

== Pro Features ==

All free features, plus:

= CSV Import =
* Upload a CSV file to bulk-update existing media metadata or create new attachments from external URLs
* Supported columns: `ID`, `slug`, `url`, `rename_to`, `title`, `caption`, `description`, `alt_text`, `custom_meta:_key`
* Batch processing with progress tracking — handles large libraries without timeouts
* Rename files during import using the `rename_to` column

[youtube https://www.youtube.com/watch?v=CxBf8m3dTpo]
[youtube https://www.youtube.com/watch?v=uQ1KQqTLFss]
[youtube https://www.youtube.com/watch?v=4o17Q5gRSXs]

= Duplicate Merge =
* Select which copy of a duplicate group to keep, then merge with one click
* All references to the deleted copies are automatically updated across:
  - Post content and excerpts
  - Featured images
  - Elementor page builder data
  - Custom post meta fields
* Keeps your site fully functional after cleanup — no broken image references

= Rubbish File Pro Actions =
* **Bulk Delete** — delete all selected rubbish files with a single confirmation
* **Single Delete** — delete individual rubbish files with confirmation
* **Restore to Library** — import a rubbish file back into the WordPress media library, generating proper thumbnails and metadata
* **Ignore / Unignore** — mark files as safe to keep and exclude them from future scan results

= Bulk Rename by Post Title =
* Rename all selected media files based on their attached post or page title
* Prefix and suffix are automatically applied

= Bulk Rename by Product SKU =
* Rename WooCommerce product images using the product's SKU
* Ideal for WooCommerce stores managing large product catalogs

= Bulk Rename by Alt Text =
* Rename media files using their existing alt text as the new filename
* Keeps filenames and alt text consistent for stronger SEO alignment

= Auto Rename on Upload =
* Automatically rename uploaded files based on the attached post title
* Or set a custom global rename pattern for all uploads

= Auto Metadata from Post Title =
* Automatically set alt text, caption, and description from the attached post title at upload time
* No manual editing needed for images uploaded directly to a post

= Rename Prefix & Suffix =
* Prepend and append custom text to every renamed filename
* Applies to all bulk rename strategies: by post title, SKU, alt text, and CSV import

= Auto Alt Text on Frontend =
* Automatically inject missing alt text into image tags when WordPress renders frontend pages
* Fallback strategy: post title → filename → custom default text
* Improves accessibility (WCAG 2.1) and SEO without editing every image manually

= Register Custom Image Sizes =
* Define custom image sizes (width, height, crop) that WordPress generates on every upload
* Sizes are prefixed with `tsmlt_` to avoid conflicts
* Reduce storage waste by generating only the sizes your theme actually uses

= Enhanced AI Features =
* Send the actual image to the AI provider for visual analysis — get far more accurate alt text and captions
* Up to 10 AI suggestions per field to choose from (vs. 1 in the free version)

---

== AI Content Generator – Supported Models ==

= ChatGPT =
GPT-5.1, GPT-5 Mini, GPT-4o, GPT-4o Mini, GPT-4.1, and more

= Google Gemini =
Gemini 2.0 Flash, Gemini 2.0 Flash Lite, Gemini 1.5 Pro, Gemini 1.5 Flash

= Anthropic Claude =
Claude Haiku, Claude Sonnet, Claude Opus

**What the AI generates:**

* **Title** — 3 to 8 words, title case
* **Alt Text** — SEO-friendly, WCAG 2.1 accessible, maximum 125 characters
* **Caption** — 1 to 2 sentence engaging description
* **Description** — 2 to 4 sentence SEO-optimized description
* **Filename** — lowercase, hyphenated, maximum 50 characters

---

== Media File Rename — SEO Benefits ==

Descriptive, keyword-rich filenames help search engines understand your images, improving rankings in Google Image Search and general web search. Well-named files also make your media library easier to navigate and audit over time.

**Before renaming, we strongly recommend:**

1. Practice on a staging environment first
2. Back up your database and files before any bulk rename operation
3. Test renaming a single file before running bulk actions
4. Clear your site and CDN cache after renaming to prevent broken references

---

== Frequently Asked Questions ==

= How do I enable inline editing for a single media item? =
Go to **Media > Media Tools > Media Table** and click the **Enable Edit Mode** button. Each row will show editable fields for title, alt text, caption, and description.

= How do I bulk edit multiple media items? =
Select items using the checkboxes in the Media Table, then choose **Bulk Edit** from the Bulk Actions dropdown. Apply changes to all selected items at once.

= How do I move media files to the trash? =
In the Media Table, select one or more items and choose **Move to Trash** from the Bulk Actions dropdown. Trashed items can be restored or permanently deleted.

= How do I create and assign media categories? =
Select items in the Media Table, choose **Bulk Edit**, and assign a category in the modal. You can also filter the table by category using the category dropdown.

= How does the AI content generator work? =
1. Go to **Settings** and enter your API key for ChatGPT, Gemini, or Claude.
2. Open the Media Table, click the AI button on any media item.
3. Review the generated suggestions and apply the one you want.

= How do I bulk rename using a CSV file? =
1. Export your media as a CSV.
2. Add a `rename_to` column with your desired filenames (no extension needed).
3. Import the CSV using the Import feature (Pro required).

= What is a rubbish file? =
A rubbish file physically exists in your WordPress uploads directory but is not registered in the media library database. These are typically leftover files from deleted plugins, failed uploads, theme switches, or manual FTP operations.

= Is it safe to delete rubbish files? =
Most rubbish files are safe to delete. However, some plugins store files in the uploads directory outside of the media library. Always review the rubbish file list before deleting, and use the **Ignore** feature to protect files you want to keep.

= What happens when I merge duplicate images? =
The plugin keeps the copy you select and deletes the rest. All references to the deleted files — in post content, featured images, Elementor data, and custom meta fields — are automatically updated to point to the kept copy. Your site will continue to work normally.

= How does image usage tracking work? =
The Used Where feature scans your posts and pages in batches to detect where each image is referenced — in content, featured images, Elementor data, and custom meta fields. You can also enable frontend tracking to passively capture usage as visitors browse your site.

= Does the plugin support SVG files? =
Yes. SVG files are sanitized on upload to remove XSS vectors and remote references. The maximum allowed SVG size is 500KB (adjustable via the `tsmlt_upload_max_svg_file_size` filter).

= Where is the source code? =
👉 [Official GitHub Repository](https://github.com/wptinysolution/media-library-tools) 👈

---

== Screenshots ==

01. Media Table — view, filter, sort, and inline-edit all media files
02. Media File Rename table with inline editing and AI suggestions
03. Rubbish File notice and scan prompt
04. Rubbish File table showing orphaned files with filter and action buttons
05. Directory list with scan history for targeted rubbish scanning
06. Media Table in single-item edit mode
07. Media Table in bulk edit mode
08. Plugin settings page — AI, rename, alt text, image sizes, and more
09. AI Content Generator showing multiple field suggestions

---

== Changelog ==

= 2.1.2 =
* Added: Duplicate file scanner with batch scanning and progress bar
* Added: Duplicate groups view with file count, size, and usage details
* Added: Pro — Merge duplicates: keep one copy, delete the rest, and update all references automatically
* Fixed: Database tables now auto-created on plugin update (not just on activation)

= 2.1.1 ( Mar 10, 2026 ) =
* Added: AI Content Generator — generate title, alt text, caption, description, and filename using ChatGPT, Gemini, or Claude directly from the media table
* Updated: Composer dependency `codesvault/howdy-qb` updated to latest version
* Fixed: Custom table query truncate function updated

= 2.1.0 ( Mar 03, 2026 ) =
* Security: Migrated all REST API endpoints to WordPress admin-ajax for improved security compliance
* Added: Clear button on search keyword input field
* Added: Current scanning directory name shown live during bulk scan
* Added: Horizontal scroll shadow indicators in data tables
* Fixed: Bulk action select de-syncs visually after filter reset
* Fixed: Bulk directory scan now processes files in batches of 50 to prevent timeouts
* Fixed: Directories containing only subdirectories no longer loop infinitely during scan
* Fixed: Files in custom directories incorrectly excluded from rubbish list
* Fixed: Progress bar in bulk delete confirmation modal
* Fixed: Attached Post column sorting in the file rename table
* Fixed: Missing text domain on translatable strings
* Improvement: DataTable first column maintains fixed width
* Improvement: Directory scan list displays trimmed relative paths
* Improvement: Refactored loading states and per-page controls
* Improvement: Updated Export/Import UI styles
* Improvement: Overall UI improvements for better user experience

= 2.0.1 ( Jan 08, 2026 ) =
* Fixed: Search Attached Post issue resolved

= 2.0.0 ( Jan 08, 2026 ) =
* Updated: Display name and branding for clarity and compliance
* Updated: Removed Freemius code and remote assets; all required files now bundled locally
* Updated: Added composer.json and included readable JS and CSS source files
* Updated: Fixed sanitization, validation, escaping, and nonce handling throughout
* Updated: Standardized prefixes across the codebase to avoid conflicts with other plugins
* Updated: General code quality improvements and WordPress directory guideline compliance

= 1.7.0 ( Nov 25, 2025 ) =
* Improvement: Rubbish file bulk delete made significantly faster
* Improvement: Security hardening across AJAX endpoints

= 1.6.15 ( Nov 16, 2025 ) =
* Fixed: Activation time hooks
* Fixed: Migration issue resolved

= 1.6.14 ( Oct 23, 2025 ) =
* Added: Media File Download Shortcode (`[tsmlt_download_button]`)

= 1.6.13 ( Sep 29, 2025 ) =
* Added: Sort by Attached Post (parent post) in the rename table
* Fixed: Image group name display issue

= 1.6.12 ( Aug 20, 2025 ) =
* Fixed: Broken URL removal
* Added: Proper support URL

= 1.6.11 ( Jul 17, 2025 ) =
* Fixed: Cron scheduling error
* Fixed: Text domain loading issue

= 1.6.10 ( May 27, 2025 ) =
* Fixed: Content update issue on save

= 1.6.9 ( May 07, 2025 ) =
* Added: Export selected columns only for CSV
* Added: Export all media items with selected columns

= 1.6.8 ( Apr 21, 2025 ) =
* Added: Updated support URL
* Compatibility: Tested with latest WordPress version

= 1.6.7 ( Mar 09, 2025 ) =
* Fixed: URL fixes and removed unwanted srcset from SVG images

= 1.6.6 ( Mar 01, 2025 ) =
* Fixed: SVG image rename issue

= 1.6.5 ( Feb 22, 2025 ) =
* Added: Attached Post searching and filtering
* Fixed: Attached Post search query optimization

= 1.6.4 ( Feb 21, 2025 ) =
* Fixed: Attached Post search query optimization

= 1.6.3 ( Feb 17, 2025 ) =
* Fixed: Attached Post search query optimization

= 1.6.2 ( Feb 06, 2025 ) =
* Improvement: Attached Post Detection — detect where media files are used across the site
* Fixed: Media Table loader issue
* Fixed: Search image query issue

= 1.6.1 ( Jan 30, 2025 ) =
* Fixed: File rename prefix and suffix not applying correctly

= 1.6.0 ( Jan 29, 2025 ) =
* Fixed: Image broken after rename issue
* Fixed: Elementor page image broken after rename issue

= 1.1.4 ( Aug 27, 2023 ) =
* Added: Parent post URL display
* Added: URL for title field
* Fixed: Media content update issue

= 1.0.0 ( Mar 07, 2023 ) =
* Initial release
