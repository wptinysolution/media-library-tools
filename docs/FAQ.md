# Frequently Asked Questions - Media Library Tools

## Installation & Setup

### Q: Do I need to install anything else?
**A:** No. Media Library Tools is a complete, self-contained WordPress plugin. Just install and activate it. No external tools or services are required (except optional AI providers if you choose to use AI).

### Q: Will this slow down my WordPress site?
**A:** No. The plugin only loads on the Media Library Tools pages in the admin panel. Zero code runs on your frontend (except optional opt-in features like frontend image tracking). All scanning operations use AJAX batching to avoid timeouts.

### Q: What WordPress version do I need?
**A:** WordPress 5.5 or higher. We test up to WordPress 7.0.

### Q: Can I use this with multisite?
**A:** Not officially. The plugin is designed for single-site installations. Multisite support is not tested or documented.

---

## Features & Functionality

### Media Table

**Q: How do I sort the media table?**
**A:** Click any column header to sort ascending. Click again to reverse (descending). You can sort by filename, date, title, alt text, caption, or description.

**Q: Can I search for specific media files?**
**A:** Yes. Use the search box to find files by filename, title, or alt text. Results update as you type.

**Q: What's the difference between "Edit Mode" and "Bulk Edit"?**
**A:** Edit Mode edits one file at a time inline. Bulk Edit opens a modal to edit multiple files at once with the same values.

**Q: Can I assign media to categories?**
**A:** Yes. Create categories in **Media > Media Categories**, then assign them via bulk edit or inline edit in the Media Table.

**Q: How do I copy a file URL?**
**A:** Click the copy icon next to the filename in any row. The full URL copies to your clipboard instantly.

---

### File Renaming

**Q: Does renaming break my images on the frontend?**
**A:** No. When you rename a file, the plugin automatically updates:
- Database media record
- All `<img>` tags in post content
- Featured images
- Elementor page builder data
- Custom post meta fields

Your site continues to work normally.

**Q: What makes a good filename for SEO?**
**A:** Use descriptive, keyword-relevant names:
- ✅ `blue-ceramic-mug-product-photo`
- ✅ `office-desk-workspace`
- ❌ `img-123` (too vague)
- ❌ `unnamed` (no keywords)

Keep it under 50 characters, use hyphens, and lowercase letters.

**Q: Can I rename multiple files at once?**
**A:** Yes. Select files in the Media Rename table, click **Bulk Rename**, choose your rename strategy, and confirm.

**Q: What if I make a mistake renaming a file?**
**A:** Rename it again to the correct name. The plugin will update all references again automatically.

**Q: How do I rename files based on post title? (Pro)**
**A:** In Pro version, select files and choose **Rename Based on Attached Post Title** from the Bulk Rename modal.

**Q: How do I rename WooCommerce product images by SKU? (Pro)**
**A:** In Pro version, select files and choose **Rename Based on Product SKU** from the Bulk Rename modal.

---

### Duplicate Detection

**Q: How does the plugin find duplicates?**
**A:** It uses MD5 hash fingerprinting. Every file is hashed and compared. Exact byte-for-byte duplicates are grouped together, regardless of filename.

**Q: How long does a duplicate scan take?**
**A:** Depends on your library size. The plugin scans in batches to avoid timeouts. Large libraries (10,000+ files) may take several minutes.

**Q: Can I see where duplicate images are used?**
**A:** Yes. Expand any duplicate group to see every copy and where it's used (posts, pages, featured images, Elementor, etc.).

**Q: What does "Total Wasted" mean?**
**A:** It's the total disk space used by all duplicates in that group. If you have 3 copies of a 500KB image, "Total Wasted" shows 1MB (the space of 2 extra copies you could delete).

**Q: Is merging duplicates reversible? (Pro)**
**A:** No. Merged (deleted) files are permanently removed. Back up before merging duplicates.

**Q: What happens when I merge duplicates? (Pro)**
**A:** The plugin keeps one copy and deletes the rest. All references automatically update to point to the kept copy. Your site continues to work without broken images.

---

### Rubbish Files (Orphaned File Cleaner)

**Q: What is a rubbish file?**
**A:** A file that physically exists in your `/uploads/` directory but isn't registered in the WordPress media library database. They waste disk space without serving any purpose.

**Q: Where do rubbish files come from?**
**A:** Common sources:
- Deleted plugins (some leave behind data files)
- Failed uploads
- Manual FTP operations
- Old theme files
- Abandoned plugin features
- Image size variations WordPress no longer generates

**Q: Is it safe to delete rubbish files?**
**A:** Most are safe. However, some themes/plugins store intentional data files in `/uploads/`. Use the **Ignore** feature to mark files you want to keep, then delete the rest.

**Q: How do I ignore a file?**
**A:** Click the **Ignore** button on the rubbish file. It moves to "Ignored" status and won't appear in future scans.

**Q: Can I delete rubbish files automatically? (Pro)**
**A:** Yes. In the scan modal, enable **Instant delete during scan** before starting. Files are deleted as they're identified. ⚠️ This action is irreversible.

---

### Image Usage Tracker (Used Where)

**Q: What types of usage does the scanner detect?**
**A:** It finds images in:
- Post content (`<img>` tags and URLs)
- Featured images (`_thumbnail_id`)
- Elementor page builder widget data
- Custom post meta fields

**Q: How do I find unused images?**
**A:** Run a scan, then click the **Unused** tab. Images not found in any post appear here. Delete with caution after manual verification.

**Q: Can I delete unused images in bulk?**
**A:** Yes. Select images on the **Unused** tab and click **Delete N Images**. A confirmation modal appears with warnings. Once confirmed, deletion is permanent.

**Q: What about images in sidebars or widgets?**
**A:** Those typically aren't detected automatically. Enable **Frontend Tracking** in settings to capture usage from frontend rendering.

**Q: How accurate is the scan?**
**A:** Very accurate for post content, featured images, and Elementor. Some edge cases (custom plugins, unusual meta storage) might be missed. Always verify manually before deleting.

---

### AI Content Generator

**Q: Do I have to use AI?**
**A:** Completely optional. All AI features are opt-in. Ignore the AI buttons if you don't want to use it.

**Q: Does the plugin send my images to third parties?**
**A:** Free version: Only text metadata (filename, alt text, post title, site name) is sent to the AI provider.
Pro version: If you enable "Image Vision", the image is base64-encoded and sent to the AI provider for visual analysis.

In both cases, communication is encrypted via HTTPS. No data is stored by this plugin.

**Q: Which AI providers are supported?**
**A:** ChatGPT (OpenAI), Google Gemini, and Anthropic Claude.

**Q: Do I need to pay for AI generation?**
**A:** The plugin doesn't charge. You need an API key from your provider:
- OpenAI (ChatGPT): `platform.openai.com`
- Google (Gemini): `ai.google.dev`
- Anthropic (Claude): `console.anthropic.com`

Most offer generous free tiers that cover typical media library use.

**Q: How many AI suggestions do I get?**
**A:** Free version: 1 suggestion per field. Pro version: up to 10 suggestions per field.

**Q: What does the AI actually generate?**
**A:** Title, alt text, caption, description, and filename — all SEO-optimized and following best practices.

**Q: Can I switch AI providers?**
**A:** Yes, anytime. Just update the provider and API key in Settings.

**Q: What if the AI suggestion is wrong?**
**A:** You can always edit it manually or regenerate new suggestions. No suggestion is applied until you click to accept it.

---

### CSV Export/Import

**Q: How do I export my media library?**
**A:** Go to **Media Tools > CSV Export**, select which columns to include, and click Export.

**Q: What columns can I export?**
**A:** ID, slug, URL, title, alt text, caption, description, and custom meta fields.

**Q: Can I import CSV data? (Pro)**
**A:** Yes, the Pro version includes CSV Import. Upload a CSV file to bulk-update media or create new attachments.

**Q: What columns does CSV Import support? (Pro)**
**A:** ID, slug, url, rename_to, title, caption, description, alt_text, and custom_meta:_key fields.

**Q: Can I rename files during import? (Pro)**
**A:** Yes. Include a `rename_to` column in your CSV. The plugin renames files during import.

**Q: Is CSV import reversible? (Pro)**
**A:** No. It directly updates your media library. Always back up before importing.

---

### Image Sizes & Thumbnails

**Q: How do I disable unnecessary image sizes?**
**A:** Go to **Settings > Image Size Settings**, uncheck sizes you don't need, and save.

**Q: Will disabling sizes delete existing thumbnails?**
**A:** No. Disabling stops WordPress from generating those sizes for new uploads. To clean up old thumbnails, go to **Regenerate Thumbs** and let it delete orphaned sizes.

**Q: How do I regenerate all thumbnails?**
**A:** Go to **Regenerate Thumbs** and click **Start Regenerating**. The plugin processes 10 images at a time with a progress bar.

**Q: Can I stop the regeneration process?**
**A:** Yes. Click **Stop** to pause. You can resume anytime or move on to cleanup.

**Q: How do I register custom image sizes? (Pro)**
**A:** In Pro version, go to **Settings > Image Size Settings > Register Custom Image Sizes**. Click **Add New Size**, enter dimensions, and save.

---

### Settings

**Q: Where are plugin settings?**
**A:** Go to **Media > Media Tools > Settings**. All options are organized by feature.

**Q: Can I export my settings?**
**A:** Settings are automatically saved to the WordPress options table. They're included in regular WordPress backups.

**Q: Do I have to configure everything?**
**A:** No. Everything is optional. The plugin works with zero configuration. Configure only what you need.

**Q: What happens if I reset the plugin?**
**A:** Deactivate and delete the plugin. All settings are removed. Data (renamed files, etc.) is permanent on your site. Re-install anytime.

---

## Compatibility & Integration

### WordPress & Themes

**Q: Does this plugin work with my theme?**
**A:** Yes. Media Library Tools works with any WordPress theme. It only manages your media library — it doesn't depend on theme features.

**Q: Is it compatible with Elementor?**
**A:** Yes. File renames, duplicate merges, and usage tracking all correctly handle Elementor page builder data. When you rename/merge, the plugin updates Elementor's serialized widget data.

**Q: Is it compatible with WooCommerce?**
**A:** Yes. The "Bulk Rename by Product SKU" feature (Pro) is specifically designed for WooCommerce stores. The plugin handles product images correctly in all features.

**Q: What about other page builders?**
**A:** Divi, Beaver Builder, etc. are supported for basic usage. Elementor has special support for updating builder data during rename/merge.

---

### Security & Permissions

**Q: Who can access Media Library Tools?**
**A:** Users with `manage_options` capability (Administrators by default) or `upload_files` capability (depending on the action).

**Q: Is the plugin secure?**
**A:** Yes. Every AJAX action includes:
- WordPress nonce verification
- User capability checks
- POST-only enforcement
- Proper escaping and sanitization

**Q: Can regular users access it?**
**A:** Only administrators and users with appropriate capabilities. The plugin respects WordPress permissions.

**Q: Does the plugin add SQL injection vulnerabilities?**
**A:** No. It uses a query builder with parameter binding and automatic escaping. No raw SQL queries.

---

## Troubleshooting

### General Issues

**Q: I see a blank page or error in Media Tools.**
**A:**
1. Check **Debug Log**: Enable `WP_DEBUG` in `wp-config.php` and check `/wp-content/debug.log`
2. Check browser console: F12 → Console tab for JavaScript errors
3. Try deactivating other plugins to check for conflicts
4. Verify PHP version is 7.4+

**Q: The plugin says "No compatible version of the plugin".**
**A:** Make sure you have the free plugin installed and activated before using the Pro version.

---

### File Operations

**Q: Files aren't renaming.**
**A:**
1. Check file permissions on `/wp-content/uploads/` (should be 755)
2. Verify PHP process has write access
3. Check WordPress debug log for errors
4. Try renaming a single file to test
5. Check for symlink/soft link issues

**Q: Media table is very slow to load.**
**A:**
1. If you have 50,000+ files, this is normal
2. Try filtering/searching to reduce the number of files shown
3. Check server resources (memory, CPU)
4. Check for slow database queries in debug log

**Q: Scan takes too long.**
**A:**
1. This is expected for large libraries
2. Scans run in background via AJAX
3. You can close the page and scan continues
4. Check back later for results

---

### AI Issues

**Q: "Invalid API Key" error.**
**A:**
1. Copy/paste your API key again (verify no extra spaces)
2. Check that the key is for the correct provider
3. Log into your provider account to verify the key is active
4. Some providers need additional setup (e.g., billing info)

**Q: "API Limit Exceeded" error.**
**A:**
1. You've hit your provider's usage limit
2. Wait a few moments and try again
3. Check your provider's dashboard for usage/limits
4. Some free tiers have low limits — upgrade if needed

**Q: AI suggestions won't show.**
**A:**
1. Verify API key is valid (see above)
2. Check network tab in browser console to see actual API response
3. Try a different AI model
4. Check browser console for JavaScript errors
5. Disable browser extensions that might interfere (especially script blockers)

---

### Deleting & Cleanup

**Q: I accidentally deleted something. Can I undo?**
**A:**
- Trashed files: Yes, restore from trash immediately
- Permanently deleted files: No, they're gone. Restore from your backup.
- Renamed files: The original rename is permanent. Re-rename to correct name.

**Q: I'm worried about deleting files. What's the safest approach?**
**A:**
1. Back up your entire site first
2. Use **Ignore** to exclude any files you're unsure about
3. Delete a small batch first to verify nothing breaks
4. Keep a CSV export for reference
5. Test on staging before production

---

## Getting Help

**Q: Where can I get support?**
**A:**
- 📖 [Official Documentation](https://docs.wptinysolutions.com/media-library-tools/)
- 💬 [WordPress.org Support Forum](https://wordpress.org/support/plugin/media-library-tools/)
- 🐛 [GitHub Issues](https://github.com/wptinysolutions/media-library-tools/issues)
- 📧 Pro version users: Support email and priority support

**Q: How do I report a bug?**
**A:**
1. Search existing issues on [GitHub](https://github.com/wptinysolutions/media-library-tools/issues)
2. Include: WordPress version, PHP version, plugin version, exact steps to reproduce
3. Screenshot or error log (from debug.log)
4. Tell us what you expected vs. what happened

**Q: Can I request a feature?**
**A:**
Yes! Open an issue on GitHub labeled "Feature Request" or discuss in the support forum.

**Q: Do you offer refunds for the Pro version?**
**A:** Check your purchase email for the refund policy. Freemius (our payment processor) handles all billing and refunds.

---

## Pro Version Questions

**Q: What extra features do I get with Pro?**
**A:**
- CSV Import (bulk update/create attachments)
- Duplicate Merge (delete dupes, update references)
- Bulk Rename by Post Title, SKU, or Alt Text
- Auto-Rename on Upload
- Prefix & Suffix for all renames
- Auto Alt Text from Post Title
- Auto Alt Text on Frontend (accessibility)
- Custom Image Sizes
- 10 AI suggestions per field + Image Vision
- Rubbish file deletion/restore/ignore actions
- Priority support

**Q: Can I try Pro before buying?**
**A:** The free version is fully functional. Install and try it. Pro features are shown with a "Pro" label and lock icon. You can see exactly what's included.

**Q: How much is Pro?**
**A:** Visit [wptinysolutions.com](https://www.wptinysolutions.com) for current pricing. Usually a one-time license fee.

**Q: Do I need Pro?**
**A:** Free version covers: rename, bulk edit, AI generation, duplicate finding, rubbish cleanup, CSV export, image tracking. Pro adds: merging, CSV import, auto-rename, advanced rename strategies.

---

## Tips & Best Practices

**Q: What's the best way to organize my media library?**
**A:**
1. Use descriptive filenames (before uploading or rename after)
2. Fill in alt text for every image (for accessibility & SEO)
3. Use media categories to group related images
4. Periodically run rubbish scans and delete orphans
5. Keep CSV exports as backups

**Q: How often should I run scans?**
**A:**
- Rubbish scan: Once per month (or when you delete plugins/themes)
- Duplicate scan: Once per month (or when you upload bulk images)
- Usage scan: Once per quarter (or when content changes significantly)

**Q: Should I disable all image sizes?**
**A:** No. Only disable sizes your theme isn't using. Your theme needs specific sizes to display properly. Check your theme documentation for required sizes.

**Q: What size should my filenames be?**
**A:** Keep under 50 characters. WordPress supports longer names, but shorter names are:
- Easier to read
- Better for URLs
- Less likely to cause issues

---

Have more questions? Post in the [support forum](https://wordpress.org/support/plugin/media-library-tools/) or check the [full documentation](https://docs.wptinysolutions.com/media-library-tools/).
