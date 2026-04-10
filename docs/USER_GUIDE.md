# Media Library Tools - Complete User Guide

## Table of Contents
1. [Media Table](#media-table)
2. [Media Rename](#media-rename)
3. [Duplicate Finder](#duplicate-finder)
4. [Rubbish File Cleaner](#rubbish-file-cleaner)
5. [Image Usage Tracker](#image-usage-tracker)
6. [AI Content Generator](#ai-content-generator)
7. [Settings & Configuration](#settings--configuration)
8. [CSV Export](#csv-export)
9. [Regenerate Thumbnails](#regenerate-thumbnails)
10. [Other Features](#other-features)

---

## Media Table

The **Media Table** is your central hub for viewing, filtering, and editing all media files in your WordPress library.

### Viewing Your Media Library

1. Go to **Media** → **Media Tools** → **Media Table**
2. You'll see a table with all your media files containing:
   - Thumbnail preview
   - Filename
   - Title
   - Alt Text
   - Caption
   - Description
   - Attached Post
   - Upload Date
   - File Type

### Sorting and Filtering

**Sort by any column:**
- Click the column header to sort ascending/descending
- Click again to reverse the sort order

**Filter results:**
- **Search:** Enter keywords to find files by filename, title, or alt text
- **Date Range:** Select start and end dates
- **Category:** Filter by media category (if enabled)
- **Status:** Show trashed or active files

### Inline Editing (Single Item)

To edit one file's metadata:

1. Find the file in the table
2. Click **Enable Edit Mode** on that row
3. Edit the following fields:
   - **Title** — headline for the image
   - **Alt Text** — accessibility & SEO text (max 125 characters recommended)
   - **Caption** — visible on the frontend
   - **Description** — internal notes
   - **Category** — assign a media category
4. Click **Save** to apply changes

### Bulk Editing (Multiple Items)

To edit many files at once:

1. **Select files** using the checkboxes on the left
2. Click **Bulk Edit** from the **Bulk Actions** dropdown
3. Choose your action:
   - **Edit Metadata** — update title, alt text, caption, description
   - **Assign Category** — set media category for selected files
4. A modal will open
5. Enter the new values (leaving blank skips that field)
6. Click **Apply to N items**
7. Review the confirmation and click **Confirm**

### Copy File URL

Each row has a copy icon next to the filename:
- Click it to copy the full file URL to your clipboard
- Useful for getting direct links without opening the media attachment screen

### Trash and Delete

**Move to Trash:**
1. Select files
2. Choose **Move to Trash** from Bulk Actions
3. Click **Apply**

**Delete Permanently:**
1. Select files from the trash
2. Choose **Delete Permanently**
3. Confirm deletion (this cannot be undone)

### Media Categories

Organize files with custom categories:

1. **Create/manage categories** in **Media** → **Media Categories**
2. **Assign categories** using bulk edit or inline edit
3. **Filter by category** using the category dropdown in Media Table
4. Categories appear as links in the native WordPress media list too

---

## Media Rename

Rename files for better SEO and organization.

### Single File Rename

1. Go to **Media** → **Media Tools** → **Media Rename**
2. Find your file in the table
3. Click into the **Filename** field
4. Type your new name (e.g., `blue-widget-for-store`)
   - Don't include the file extension (e.g., `.jpg`)
   - Use lowercase and hyphens
   - Keep it concise and descriptive
5. Click **Save**

The plugin automatically:
- Updates the filename on disk
- Updates all references in post content
- Updates featured image links
- Updates Elementor page builder data
- Updates custom meta fields

### Bulk Rename (Custom Name)

To rename multiple files to the same name:

1. Select files in the **Media Rename** table
2. Click **Bulk Rename**
3. Choose **Rename with Custom Name**
4. Enter your new name (e.g., `product-image`)
5. Click **Apply**
6. Review and confirm

All selected files will be renamed with auto-incrementing numbers (e.g., `product-image-1`, `product-image-2`).

### View Attached Post

Each row shows which post the file is attached to:
- Click the post link to jump to that post
- Useful when deciding on a name for the file

### AI Filename Suggestions

If you have AI configured:

1. Click the **AI** button on any row
2. Choose a suggestion from the AI response
3. The field auto-populates with the AI suggestion
4. Click **Save** to apply

### Why Rename Files?

- **SEO:** Keyword-rich filenames help search engines understand images
- **Organization:** Descriptive names make your library easier to navigate
- **Accessibility:** Descriptive filenames improve screen reader experience

### Naming Best Practices

✅ Good names:
- `blue-ceramic-mug-product-photo`
- `office-workspace-example`
- `customer-testimonial-sarah-smith`

❌ Poor names:
- `IMG_0042`
- `screenshot-2024-01-15`
- `unnamed`
- `image` (too generic)

---

## Duplicate Finder

Find and remove duplicate images wasting storage space.

### Scan for Duplicates

1. Go to **Media** → **Media Tools** → **Duplicates**
2. Click **Scan for Duplicates**
3. The scanner compares all files using MD5 hash fingerprinting
4. Wait for the scan to complete (shows progress bar)
5. Results display in groups

### Understanding Results

For each duplicate group, you see:

| Column | Meaning |
|--------|---------|
| **File Name** | Original filename |
| **Size** | Individual file size |
| **Count** | Number of duplicate copies |
| **Total Wasted** | Sum of duplicate sizes (potential savings) |
| **Used In** | Number of posts using this group |

### View Duplicate Details

Click on any duplicate group to expand it:

- See each copy of the file
- View file path on disk
- Check where it's used (posts, pages, featured images)
- Click post links to view/edit that post

### Clear Scan Results

After reviewing results:
1. Click **Clear Scan Results**
2. Confirm deletion
3. The scan data is removed (files are not deleted)

### Pro Tip: Keep the Right Copy

Before merging, consider:
1. **Newest:** Keep the most recently uploaded version
2. **Most Used:** Keep the copy with the most references
3. **Quality:** Keep the file with best resolution/quality

---

## Rubbish File Cleaner

Find and remove orphaned files cluttering your uploads directory.

### What is a Rubbish File?

A rubbish file is a file that:
- Physically exists in your `/uploads/` directory
- But is NOT in the WordPress media library database
- Common sources: deleted plugins, failed uploads, old theme files, manual FTP operations

### Scan for Rubbish Files

1. Go to **Media** → **Media Tools** → **Rubbish Files**
2. Click **Find Rubbish Files**
3. A directory scan modal opens
4. Select directories to scan (usually just `/uploads/`)
5. Click **Start Scan**
6. Watch the progress bar as it scans in batches
7. Results appear when complete

### Review Rubbish Files

Results show:

| Info | Purpose |
|------|---------|
| **File Name** | The actual filename |
| **File Type** | Extension (.jpg, .png, etc.) |
| **File Size** | Disk space used |
| **File Path** | Relative path in uploads directory |
| **Status** | Active, Ignored, or Deleted |

### Filter by File Type

Click the **File Type** dropdown to show only certain extensions:
- `.jpg` / `.jpeg`
- `.png`
- `.gif`
- `.pdf`
- Or any custom type found

### Ignore Files

Mark files as safe to exclude from future scans:

1. Click the **Ignore** button on a file
2. It moves to the "Ignored" status
3. It won't appear in future scan results
4. Useful for plugin data files or intentional custom files

### Unignore Files

To reinclude an ignored file:
1. Filter by status "Ignored"
2. Click **Unignore** on the file

### Clear Scan History

Remove all rubbish file scan data:
1. Click **Truncate Scan Results**
2. Confirm (this doesn't delete files, just the scan history)

### Empty Directories Cleanup

After deleting files, empty folders may remain:

1. On the **Rubbish Files** page, scroll to **Empty Directories**
2. View all empty folders with their paths
3. Delete one at a time or **Delete All**
4. Keeps your uploads directory clean

### Safety First

⚠️ **Before deleting:**
- Review the file list carefully
- Use **Ignore** for any files you're unsure about
- Back up your uploads directory
- Consider testing on a staging site first
- Some themes/plugins store files in uploads that aren't in the library

---

## Image Usage Tracker

Track exactly where every image is used across your site.

### Run a Scan

1. Go to **Media** → **Media Tools** → **Used Where**
2. Click **Scan All Posts**
3. The scanner processes all posts in batches
4. Progress bar shows scan status
5. When complete, results display

### What Gets Detected?

The scanner finds images used in:
- ✅ Post content (via `<img>` tags)
- ✅ Featured images (`_thumbnail_id`)
- ✅ Elementor page builder data
- ✅ Custom post meta fields
- ✅ Frontend usage (if tracking enabled in settings)

### View Usage Results

Results tab shows:

| Column | Meaning |
|--------|---------|
| **Thumbnail** | Image preview |
| **Filename** | Original filename |
| **Used In** | Number of posts using this image |
| **Last Detected** | When scan found this usage |

Click on any image to expand and see:
- Every post using the image
- Post type (Post, Page, Custom Post Type)
- Usage type (Content, Featured Image, Elementor, Meta Field)
- Direct link to view each post

### Find Unused Images

1. Click the **Unused** tab
2. Shows all media files NOT found during the scan
3. These are candidates for safe deletion
4. Verify manually before deleting (some images may be in widgets, plugins, etc.)

### Delete Unused Images

1. Go to **Unused** tab
2. Select images to delete (or use **Select All**)
3. Click **Delete N Images**
4. Review the confirmation with disclaimer
5. Click **Confirm Delete**

⚠️ **Warning:** This action is permanent. Make sure you've verified the images are truly unused.

### Bulk Select

- **Select All:** Check the header checkbox to select all visible images
- **Deselect All:** Uncheck to clear selection
- Manual checkboxes for individual selection

### Filter & Search

- **Search box:** Find images by filename or title
- **Page numbers:** Navigate through results
- **Re-scan:** Run scan again after making site changes

### Enable Frontend Tracking (Settings)

For passive tracking without full scans:

1. Go to **Settings** → **Image Usage Settings**
2. Enable **Track Frontend Usage**
3. When visitors browse your site, usage is automatically recorded
4. Shows more accurate real-world usage patterns

---

## AI Content Generator

Generate SEO-optimized metadata using ChatGPT, Gemini, or Claude.

### Setup

1. Go to **Media** → **Media Tools** → **Settings** → **AI Settings**
2. Select your AI provider:
   - **ChatGPT** (OpenAI)
   - **Google Gemini** (Google)
   - **Anthropic Claude** (Anthropic)
3. Enter your API key
4. Select a model
5. Click **Save Settings**

### How It Works

1. Open **Media Table** or **Media Rename**
2. Click the **AI** button on any row
3. The plugin sends file metadata to your AI provider:
   - Filename
   - Existing alt text
   - Attached post title
   - Your site name and tagline
4. AI generates suggestions for:
   - **Title** (3-8 words, title case)
   - **Alt Text** (SEO-friendly, max 125 characters)
   - **Caption** (1-2 sentences)
   - **Description** (2-4 sentences)
   - **Filename** (lowercase, hyphenated)
5. Review suggestions and click to use

### Choosing Suggestions

- Free version: 1 suggestion per field
- Pro version: Up to 10 suggestions per field (choose the best)

### AI Providers & Models

**ChatGPT (OpenAI):**
- GPT-5.1, GPT-5, GPT-4o, GPT-4.1
- [Get API Key](https://platform.openai.com/api-keys)

**Google Gemini:**
- Gemini 2.0 Flash, Gemini 2.0 Flash Lite, Gemini 1.5 Pro, Gemini 1.5 Flash
- [Get API Key](https://ai.google.dev)

**Anthropic Claude:**
- Claude Haiku, Claude Sonnet, Claude Opus
- [Get API Key](https://console.anthropic.com)

### Troubleshooting

**"Invalid API Key"**
- Verify your key is correct in Settings
- Check that the key is for the correct provider
- Ensure the key has appropriate permissions

**"API Limit Exceeded"**
- You've hit your provider's rate limit
- Wait a few moments and try again
- Check your provider's usage dashboard

**"Timeout"**
- Network issue or API provider is slow
- Retry the request
- Try a different model

---

## Settings & Configuration

### Alt Text Settings

Configure automatic alt text for new uploads:

1. Go to **Media** → **Media Tools** → **Settings** → **Alt Text Settings**
2. Choose one option:
   - **Use Image Filename** — uses the uploaded filename as alt text
   - **Custom Text** — use your own default text (e.g., "My Website Product")
3. Click **Save**

Every new upload automatically gets this alt text. You can edit it later.

### Rename Settings

(Pro feature - free version has basic rename only)

**Available in Pro:**
- Auto-rename on upload
- Rename prefix & suffix
- Auto alt text from post title
- Custom rename patterns

### Image Size Settings

Disable unused image sizes to save storage:

1. Go to **Settings** → **Image Size Settings**
2. View all registered image sizes
3. Uncheck sizes you don't need:
   - Default WordPress sizes (Thumbnail, Medium, Large, etc.)
   - Theme-specific sizes
   - Plugin-specific sizes
4. Click **Save**

WordPress won't generate these sizes for new uploads.

**Regenerate old thumbnails:**
- Go to **Regenerate Thumbs**
- Click **Start Regenerating**
- Old unneeded thumbnail files are automatically deleted

### AI Settings

Configure your AI provider:

1. Go to **Settings** → **AI Settings**
2. Provider: Choose ChatGPT, Gemini, or Claude
3. API Key: Paste your API key from the provider
4. Model: Select which model to use
5. Click **Save**

---

## CSV Export

Export your entire media library to spreadsheet format.

### Start Export

1. Go to **Media** → **Media Tools** → **CSV Export**
2. Select which columns to include:
   - ID
   - Slug
   - URL
   - Title
   - Alt Text
   - Caption
   - Description
   - Custom Meta Fields
3. Click **Export**
4. The plugin processes your library in batches
5. CSV file downloads when complete

### Using the Export

Open the CSV in:
- Microsoft Excel
- Google Sheets
- Apple Numbers
- Or any spreadsheet app

### What You Can Do

- 📊 Analyze your media library metadata
- 📋 Audit missing alt text
- 🔗 Find broken URLs
- 📥 Prepare data for bulk import (Pro)
- 📤 Back up your media metadata

---

## Regenerate Thumbnails

Rebuild image sizes after changing theme image size settings.

### When to Regenerate

- After adding a new theme or plugin with new image sizes
- After disabling/enabling image sizes
- After changing image dimensions in Settings
- To clean up orphaned thumbnail files

### Start Regeneration

1. Go to **Media** → **Media Tools** → **Regenerate Thumbs**
2. Click **Start Regenerating**
3. Watch the progress bar
4. Processing batch: 10 images at a time

### What Happens

The plugin:
- ✅ Generates all registered image sizes for each image
- ✅ Automatically detects and deletes orphaned thumbnails
- ✅ Shows real-time progress (images processed, succeeded, failed)
- ✅ Logs any errors for troubleshooting

### Stop & Restart

**To pause:**
- Click **Stop** button
- Progress bar shows "Stopped" state
- Can resume anytime

**To restart:**
- Click **Restart from Beginning**
- Or resume from where you stopped

### Review Errors

If some images fail:
1. Check the error log below the progress bar
2. Each error shows filename and reason
3. Common causes:
   - Unsupported file type
   - Corrupted file
   - Insufficient server memory
   - PHP timeout (increase `memory_limit`)

### Next Steps

After regeneration:
1. Clear your cache (CDN, browser, WordPress)
2. Optionally run **Rubbish Files** to clean orphaned thumbnails
3. Verify images display correctly on frontend

---

## Other Features

### SVG Upload Support

Upload SVG files safely with automatic sanitization:

- Remove XSS vulnerabilities
- Remove remote references
- Generate proper width/height metadata
- Max file size: 500KB (configurable)

Just upload like any other image. The plugin handles sanitization automatically.

### Media Download Shortcode

Add download buttons to your content:

```
[tsmlt_download_button id='123' text='Download Now' class='my-button' /]
```

Or by URL:

```
[tsmlt_download_button url='https://example.com/file.pdf' text='Download PDF' /]
```

Supports: images, PDFs, documents, audio, video, archives.

### Additional Media Columns

Native WordPress media list now includes:

- Alt Text (sortable)
- Caption (sortable)
- Description (sortable)
- Media Categories
- "Uploaded To" post info

---

## Performance & Optimization

### Best Practices

✅ **Do:**
- Run scans during off-peak hours on large sites
- Test on staging before bulk operations
- Back up before major actions
- Use filters to work on subsets of files
- Enable cron to run scans in background

❌ **Don't:**
- Run multiple large scans simultaneously
- Rename thousands of files at once
- Ignore error messages
- Skip backups before bulk deletes
- Disable image sizes in use by your theme

### Caching

The plugin doesn't cache results. To see updated results:
- Refresh the page
- Run the scan again
- Clear browser cache if needed

### Server Resources

Operations use:
- Batch processing (prevents timeouts)
- AJAX requests (doesn't block WordPress)
- Moderate database queries
- No external API calls except AI (optional)

---

## Troubleshooting

### Files Aren't Renaming

1. Check file permissions on `/wp-content/uploads/`
2. Verify PHP has write access
3. Check for error logs in browser console
4. Try renaming a single file first

### Duplicates Not Found

1. Re-run the scan (it's not automatic)
2. Check file permissions
3. Ensure files actually exist on disk
4. Large libraries take time to scan

### AI Suggestions Not Appearing

1. Verify API key is valid
2. Check API provider account (not expired/out of credits)
3. Check browser console for errors
4. Try a different AI model
5. Check your network connection

### Changes Not Saving

1. Verify you have `manage_options` capability
2. Check WordPress file permissions
3. Look for PHP errors in WordPress debug log
4. Disable browser extensions that might interfere
5. Clear browser cache and try again

---

## Support & Resources

- 📖 [Official Documentation](https://docs.wptinysolutions.com/media-library-tools/)
- 🐛 [Report Issues](https://github.com/wptinysolutions/media-library-tools/issues)
- 💬 [Support Forum](https://wordpress.org/support/plugin/media-library-tools/)
- 🎥 [Video Tutorials](https://www.youtube.com/channel/wptinysolutions)
- 💡 [Feature Requests](https://github.com/wptinysolutions/media-library-tools/discussions)
