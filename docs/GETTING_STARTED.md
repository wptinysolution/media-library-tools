# Getting Started with Media Library Tools

## Installation

### From WordPress Plugin Directory
1. Go to **WordPress Admin** → **Plugins** → **Add New**
2. Search for **Media Library Tools**
3. Click **Install Now** → **Activate Plugin**
4. Go to **Media** → **Media Tools** in your WordPress admin menu

### Manual Installation
1. Download the latest version from [GitHub Releases](https://github.com/wptinysolutions/media-library-tools/releases)
2. Extract the ZIP file
3. Upload the `media-library-tools` folder to `/wp-content/plugins/`
4. Go to **Plugins** in WordPress admin and click **Activate** next to Media Library Tools

## Requirements
- **WordPress:** 5.5 or higher
- **PHP:** 7.4 or higher
- **Database:** MySQL 5.6+ or MariaDB 10.1+

## First Steps

After activating the plugin, you'll see a new **Media Tools** menu in your WordPress admin sidebar. Here's what you should do:

### 1. Configure AI Settings (Optional)
If you want to use the AI content generator:
1. Go to **Media** → **Media Tools** → **Settings** → **AI Settings**
2. Choose your AI provider:
   - **ChatGPT** (OpenAI)
   - **Google Gemini** (Google)
   - **Anthropic Claude** (Anthropic)
3. Enter your API key from the provider
4. Select your preferred model
5. Click **Save Settings**

> **Need an API key?** Each provider offers a free tier that covers typical media library use.

### 2. Configure Default Alt Text (Recommended)
For accessibility and SEO:
1. Go to **Media** → **Media Tools** → **Settings** → **Alt Text Settings**
2. Choose one of these options:
   - Use image filename as alt text (automatic for all new uploads)
   - Use custom default text (e.g., "Your Site Name Product Image")
3. Click **Save Settings**

### 3. Disable Unnecessary Image Sizes (Optional)
To reduce storage usage:
1. Go to **Media** → **Media Tools** → **Settings** → **Image Size Settings**
2. Uncheck image sizes you don't need (e.g., 1536×1536, 2048×2048)
3. Click **Save Settings**
4. Go to **Regenerate Thumbs** to clean up existing thumbnails

### 4. Start with the Media Table
1. Go to **Media** → **Media Tools** → **Media Table**
2. View your entire media library in an organized table
3. Sort by any column (filename, date, alt text, etc.)
4. Search for specific images
5. Click **Enable Edit Mode** on any row to edit metadata

## Common Workflows

### Batch Update Alt Text
1. Open **Media Table**
2. Select multiple images with checkboxes
3. Click **Bulk Edit** → **Apply**
4. Enter new alt text that applies to all selected images
5. Click **Save**

### Rename Files for Better SEO
1. Open **Media Rename**
2. Find the image you want to rename
3. Click into the filename field
4. Type your new SEO-friendly name (e.g., `blue-widget-product`)
5. Click **Save**
6. The plugin automatically updates all references to the file across your site

### Find Duplicate Images
1. Open **Duplicates**
2. Click **Scan for Duplicates**
3. Wait for the scan to complete
4. Review duplicate groups to see wasted space
5. Click each duplicate to see where it's used

### Find Orphaned Files
1. Open **Rubbish Files**
2. Click **Find Rubbish Files**
3. Select directories to scan (usually `/uploads/`)
4. Click **Start** to scan
5. Review the list of orphaned files
6. Use **Ignore** to exclude files you want to keep

### Export Your Media Library
1. Open **CSV Export**
2. Select which columns to include (ID, URL, title, alt text, etc.)
3. Click **Export**
4. Your media library CSV file will download

## Tips for Best Results

✅ **Do This:**
- Test on a staging site before bulk operations on production
- Back up your database before large rename operations
- Review duplicate groups carefully before merging
- Enable **Frontend Tracking** if you want to track image usage automatically
- Use the **Rubbish Files Ignore** feature to exclude theme/plugin files

❌ **Avoid This:**
- Renaming hundreds of files at once without testing first
- Merging duplicates without checking all references
- Deleting files without reviewing them first
- Disabling image sizes in use by your theme

## Support

- 📖 [Full Documentation](https://docs.wptinysolutions.com/media-library-tools/)
- 🐛 [Report Issues](https://github.com/wptinysolutions/media-library-tools/issues)
- 💬 [Support Forum](https://wordpress.org/support/plugin/media-library-tools/)
- 🎥 [Video Tutorials](https://www.youtube.com/channel/wptinysolutions)

## Next Steps

Once you're comfortable with the basics:
1. Read the [User Guide](USER_GUIDE.md) for detailed feature documentation
2. Check out the [FAQ](FAQ.md) for common questions
3. Consider upgrading to **Pro** for advanced features like CSV import and merge duplicates