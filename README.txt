=== AI-Powered Media Library Tools - Rename, Clean & CSV Import/Export ===
Contributors: tinysolution, mehediihasan
Tags: csv, export, media, rename, cleaner
Requires at least: 5.5
Tested up to: 6.9
Stable tag: 2.1.1
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Bulk rename media files, edit ALT/title/caption, AI content, CSV export/import, find rubbish files, SVG support.

== Description ==

Media Library Tools is a powerful WordPress plugin that helps you manage, organize, and optimize your media library. Rename files, bulk edit metadata, clean up unused media, generate AI content, and import/export via CSV — all from one intuitive interface.

👉 [Documentation](https://docs.wptinysolutions.com/media-library-tools/) | [Get Pro](https://www.wptinysolutions.com/tiny-products/media-library-tools/) 👈

[youtube https://www.youtube.com/watch?v=L7F33DYnsZU]

== 🏆 Free Features ==

* **Media File Rename** — Rename individual or bulk media files with SEO-friendly filenames
* **Bulk Edit Metadata** — Edit title, ALT text, caption, and description for single or multiple media items
* **AI Content Generator** — Generate title, alt text, caption, description, and filename suggestions using ChatGPT, Gemini, or Claude
* **SVG Upload Support** — Safely upload SVG files with automatic sanitization
* **Find Rubbish Files** — Scan upload directories and identify files not registered in the media library
* **Media Categories** — Organize media items with custom categories and filter by category
* **Disable Image Sizes** — Selectively disable default or custom image sizes to save disk space
* **Auto Alt Text, Caption & Description** — Automatically set default alt text, caption, or description on upload
* **Attached Post Detection** — See where each media file is used across your site
* **Media Download Shortcode** — Create download buttons for any file type with a simple shortcode
* **Sort & Filter** — Filter media by date, category, status, or keyword; sort by any column
* **Number Duplicate Filenames** — Automatically appends a number when a filename already exists
* **Trash Management** — Move images to trash instead of permanent deletion; restore or permanently delete

== 🏆 PRO Features ==

All free features plus:

* **CSV Export/Import** — Transfer media data between sites or bulk-update metadata via CSV
* **Rename via CSV** — Import a CSV with a `rename_to` column to bulk rename files
* **Register Custom Image Sizes** — Add custom image sizes with width, height, and crop settings
* **Bulk Rename by Post Title** — Rename media files based on their attached post title
* **Bulk Rename by Product SKU** — Rename WooCommerce product images using the product SKU
* **Auto Rename on Upload** — Automatically rename files using the attached post title or a custom name
* **Post Title as Metadata** — Set alt text, caption, and description from the attached post title on upload
* **Bulk Delete Rubbish Files** — Delete unregistered files in bulk with one click
* **Instant Deletion During Scan** — Delete rubbish files as they are found during directory scan
* **Bulk Edit by Post Title** — Update media title based on the attached post title
* **AI: Send Image to AI** — Send the actual image to AI for visual analysis and better suggestions
* **AI: Multiple Suggestions** — Get 5–10 AI-generated suggestions to choose from

== 🏆 AI Content Generator ==

Generate SEO-optimized metadata for your media files using AI — directly from the media table.

**Supported AI Providers:**

* **ChatGPT** — GPT-5.1, gpt-5-mini, gpt-4o-mini, gpt-4o, gpt-4.1, and more
* **Google Gemini** — Gemini 2.0 Flash, Flash Lite, 1.5 Pro, 1.5 Flash
* **Anthropic Claude** — Claude Haiku, Sonnet, Opus

**What AI generates:**

* Title (3–8 words, title case)
* Alt text (SEO-friendly, WCAG 2.1 accessible, max 125 characters)
* Caption (1–2 sentence engaging caption)
* Description (2–4 sentence SEO-optimized description)
* Filename (lowercase, hyphenated, max 50 characters)

The AI considers your site name, tagline, existing metadata, and attached post context for accurate results.

== 🏆 CSV Export/Import ==

Transfer, backup, and bulk-manage your media library data with CSV files.

**Export:** Select which columns to include — ID, slug, URL, title, caption, description, alt text, and custom meta fields. Download as a CSV file with a progress indicator.

**Import:** Upload a CSV to update existing media or import new files. Supported columns: `ID`, `slug`, `url`, `rename_to`, `title`, `caption`, `description`, `alt_text`, `custom_meta:_key`.

👉 Video 1: CSV transfer Media between systems. 👈

[youtube https://www.youtube.com/watch?v=CxBf8m3dTpo]

👉 Video 2: CSV Edit Title, Alt, Caption, Description. 👈

[youtube https://www.youtube.com/watch?v=uQ1KQqTLFss]

👉 Bulk Rename With CSV Import 👈

[youtube https://www.youtube.com/watch?v=4o17Q5gRSXs]

== 🏆 Media File Rename ==

Optimizing media file names provides valuable SEO benefits. Descriptive, keyword-rich filenames help search engines better understand your content, leading to higher rankings and increased visibility.

Well-named media files also enhance user experience, improving engagement and reducing bounce rates. Organized filenames make it easier for users to find and share content, driving more organic traffic.

**Important:** Renaming files can affect references across your site. We strongly recommend:

1. Practice on a staging site first
2. Back up your files and database before bulk renaming
3. Try renaming individual files before using bulk rename
4. Clear your cache after renaming if you notice broken references

== 🏆 Find Unused / Rubbish Files ==

Identify files that physically exist in your upload directories but are not registered in the WordPress media library.

* Scan all upload directories with a visual progress indicator
* Filter rubbish files by file extension
* Ignore false positives (mark files as "not deletable")
* View current scanning directory name during scan
* Re-scan directories or clear scan history

Removing unnecessary files improves site performance, load times, and storage efficiency.

== 🏆 Image SVG Support ==

Safely upload SVG files to your WordPress media library with automatic sanitization that removes remote references, XSS vectors, and other security risks. SVGs are lightweight and scalable, improving page load times while providing high-quality visuals on all devices.

* Max SVG file size: 500KB (filterable via `tsmlt_upload_max_svg_file_size`)
* Proper width/height metadata generation for SVG files

== 🏆 Disable Image Sizes ==

Selectively disable unnecessary image sizes generated by WordPress, your theme, or other plugins. Disable default sizes like Thumbnail, Medium, Large, Medium Large (768px), 1536x1536, 2048x2048, and the scaled "Big" size. Save server space and reduce upload processing time.

== 🏆 Register Custom Image Sizes (Pro) ==

Register custom image sizes with specific width, height, and crop settings for your project. Custom sizes are automatically prefixed with `tsmlt_` to avoid conflicts.

== 🏆 Media Library Categories ==

Organize your media items with a custom taxonomy. Bulk-assign categories from the media table, and filter media by category. Categories are displayed as clickable links in the native WordPress media list.

== 🏆 Download Media Shortcode ==

Create download buttons for any file type with a simple shortcode:

`[tsmlt_download_button id='123' text='Download Now' class='my-custom-btn' /]`

Or by URL:

`[tsmlt_download_button url='http://example.com/file.pdf' text='Download Now' class='my-custom-btn' /]`

Supports audio, video, documents, Office files, scripts, archives, images, and e-books. Forces a browser download prompt.

== 🏆 Media Table Columns ==

The plugin adds these columns to the native WordPress media list:

* Alt text (sortable)
* Caption (sortable)
* Description (sortable)
* Media Categories
* "Uploaded To" post info in the media modal

== 🏆 Development Source Code ==
👉 [Official GitHub Repository](https://github.com/wptinysolution/media-library-tools) 👈

== Frequently Asked Questions ==

= How do I enable edit mode for single items? =
Navigate to Media > Media Tools > Media Table and click the "Enable Edit Mode" button to toggle inline editing.

= How do I move images to the trash? =
In Media > Media Tools > Media Table, select the images using the checkboxes, then choose "Move to Trash" from the "Bulk actions" dropdown.

= How do I create and assign media categories? =
Select images in Media > Media Tools > Media Table, choose "Bulk Edit" from the "Bulk actions" dropdown, and assign categories.

= How do I search for images by category? =
Use the category dropdown filter near the Filter button in Media > Media Tools > Media Table.

= How do I use the AI content generator? =
1. Go to Settings and configure your AI provider (ChatGPT, Gemini, or Claude) with an API key.
2. In the Media Table, click the AI button on any media item.
3. Select the generated suggestion you want to apply.

= How do I rename files using a CSV? =
1. Export your media data as CSV.
2. Add a `rename_to` column with the desired new filenames.
3. Import the modified CSV using the Import feature (Pro required).

= What is a "rubbish file"? =
A rubbish file is a file that exists in your WordPress uploads directory but is not registered in the media library database. These files may be leftover from deleted plugins, failed uploads, or manual file operations.

= Is it safe to delete rubbish files? =
Most rubbish files are safe to delete, but some may be used by plugins that store files outside the media library. Review the file list before deleting and use the "Ignore" feature for files you want to keep.

== Screenshots ==

01. Media Table for editing media content
02. Media File Rename table
03. Rubbish Media notice
04. Rubbish Media table for finding unused files
05. Rubbish Media directory list with scan history
06. Media Table in edit mode
07. Media Table in bulk edit mode
08. Plugin settings page
09. AI Content Generator suggestions

== Changelog ==

= 2.1.1 ( Mar 10, 2026 ) =
* Added: AI content generator — generate title, alt text, caption, description, and filename using ChatGPT, Gemini, or Claude directly from the media table
* Updated: Update "codesvault/howdy-qb" Composer Dependencies
* Fixed: Custom Table Query Truncate Function Updated

= 2.1.0 ( Mar 03, 2026 ) =
* Security: Migrated all REST API endpoints to WordPress admin-ajax for improved security compliance
* Improvement: Added clear button to search keyword input field
* Fixed: Bulk action select de-syncs visually after filter reset
* Improvement: Refactored loading states and per-page controls
* Improvement: Updated Export/Import UI styles
* Fixed: Text size classes not applying correctly in rubbish file notice
* Improvement: Refactored checkbox markup and shortened column labels
* Improvement: Overall UI improvements for better user experience
* Fixed: Bulk directory scan now processes files in batches of 50 to prevent timeouts
* Fixed: Directories containing only subdirectories no longer loop infinitely during bulk scan
* Fixed: Files in custom directories incorrectly excluded from rubbish list
* Fixed: Progress bar in bulk delete confirmation modal
* Fixed: Attached Post column sorting in file rename table
* Added: Current scanning directory name shown during bulk scan
* Added: Horizontal scroll shadow indicators in data tables
* Improvement: DataTable first column maintains fixed width
* Improvement: Directory scan list displays trimmed paths
* Fixed: Missing text domain on translatable strings

= 2.0.1 ( Jan 08, 2026 ) =
* Fixed: Search Attached Post issue resolved

= 2.0.0 ( Jan 08, 2026 ) =
* Updated: Display name and branding for clarity and compliance
* Updated: Removed license checks, trial logic, and locked features
* Updated: Removed Freemius code and remote assets; bundled all required files locally
* Updated: Added composer.json and included readable JS/CSS source files
* Updated: Fixed sanitization, validation, escaping, and nonce handling
* Updated: Standardized prefixes across codebase to avoid conflicts
* Updated: General code quality improvements and directory guideline compliance

= 1.7.0 ( Nov 25, 2025 ) =
* Improvement: Rubbish File Bulk Delete made faster
* Improvement: Security improvements

= 1.6.15 ( Nov 16, 2025 ) =
* Fixed: Activation time hooks
* Fixed: Migration issue resolved

= 1.6.14 ( Oct 23, 2025 ) =
* Added: Media File Download Shortcode

= 1.6.13 ( Sep 29, 2025 ) =
* Added: Sort By Attached Post (Parent)
* Fixed: Image Group Name

= 1.6.12 ( Aug 20, 2025 ) =
* Fixed: Broken URL removal
* Added: Proper support URL

= 1.6.11 ( Jul 17, 2025 ) =
* Fixed: Cron error
* Fixed: Load Text Domain

= 1.6.10 ( May 27, 2025 ) =
* Fixed: Content Update issue

= 1.6.9 ( May 07, 2025 ) =
* Added: Export selected columns for CSV
* Added: Export all media items with selected columns

= 1.6.8 ( Apr 21, 2025 ) =
* Added: Updated support URL
* Support: WordPress latest version compatibility

= 1.6.7 ( Mar 09, 2025 ) =
* Fixed: URL fixes and removed unwanted srcset from SVG images

= 1.6.6 ( Mar 01, 2025 ) =
* Fixed: SVG image rename issue

= 1.6.5 ( Feb 22, 2025 ) =
* Added: Attached Post searching filter
* Fixed: Attached Post search optimization

= 1.6.4 ( Feb 21, 2025 ) =
* Fixed: Attached Post search optimization

= 1.6.3 ( Feb 17, 2025 ) =
* Fixed: Attached Post search optimization

= 1.6.2 ( Feb 06, 2025 ) =
* Improvement: Attached Post Detection — detect where media files are used
* Fixed: Media Table loader issue
* Fixed: Search image issue

= 1.6.1 ( Jan 30, 2025 ) =
* Fixed: File rename prefix and suffix

= 1.6.0 ( Jan 29, 2025 ) =
* Fixed: Image broken issue
* Fixed: Elementor page image broken issue

= 1.1.4 ( Aug 27, 2023 ) =
* Added: Parent Post URL
* Added: URL for Title
* Fixed: Media content issue

= 1.0.0 ( Mar 07, 2023 ) =
* Initial release
