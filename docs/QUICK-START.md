# Quick Start Guide

Get started with Media Library Tools in minutes.

## Installation

1. Download Media Library Tools from WordPress.org
2. Upload to `/wp-content/plugins/`
3. Activate in WordPress admin
4. Go to Media Library Tools menu in sidebar

## First Time Setup (5 minutes)

### Step 1: Configure Basic Settings

1. Click **Settings** (top menu)
2. Configure what matters most to you:
   - **SVG Support**: Enable if you use vector graphics
   - **Alt Text**: Choose how alt text is auto-assigned
   - **Captions/Descriptions**: Enable if you want auto-population
3. Click **Save Settings**

### Step 2: Explore the Media Table

1. Click **Media Table** in sidebar
2. You'll see all your media files with additional columns
3. Try:
   - **Search**: Find an image by name
   - **Bulk Edit**: Select 2-3 images and edit together
   - **Export CSV**: Export your media metadata

### Step 3: Find Unused Images (Optional)

1. Click **Used Where** in sidebar
2. Click **Scan Media Usage** button
3. Wait for scan (5-30 minutes depending on size)
4. See **Unused** tab—these images take up storage but aren't used
5. Optional: Delete unused images to free space

## Common Tasks

### Task 1: Rename an Image
1. Go to **Media Rename**
2. Find the image
3. Edit the "New Name" field
4. Click **Rename**
5. Done! File renamed and WordPress updated

### Task 2: Edit Multiple Images at Once
1. Go to **Media Table**
2. Select images (checkboxes)
3. Click **Bulk Edit**
4. Choose: Replace Alt Text, Replace Caption, Replace Description
5. Enter text
6. Click **Apply**
7. All selected images updated instantly

### Task 3: Backup Your Media
1. Go to **Media Table**
2. Click **Export CSV**
3. Select all columns
4. Download starts
5. Keep this file safe as backup

### Task 4: Find Duplicate Images
1. Go to **Duplicates**
2. Click **Scan for Duplicates**
3. Wait for scan (varies by library size)
4. See duplicate groups
5. Delete duplicates to free space

### Task 5: Regenerate Thumbnails
When switching themes or images look broken:

1. Go to **Regenerate Thumbnails**
2. Click **Regenerate Thumbnails** button
3. Progress bar shows status
4. When done, images display correctly

## Feature at a Glance

| Task | Feature | Time | Free/Pro |
|------|---------|------|----------|
| Edit metadata | Media Table | 2 min | Free |
| Bulk rename files | Media Rename | 5 min | Free |
| Auto-rename uploads | Rename Settings | 1 min | Pro |
| Find unused images | Used Where | 30 min | Free |
| Backup media | CSV Export | 2 min | Free |
| Find duplicates | Duplicates | 30 min | Free |
| Remove duplicates | Duplicates Merge | 5 min | Pro |
| Fix broken images | Regenerate | 20 min | Free |
| Clean up space | Rubbish Files | 30 min | Free |
| Manage image sizes | Image Sizes | 5 min | Free |


## FAQ

**Q: Is all data stored on my server?**
- A: Yes, completely. No data sent to external services except AI (if enabled).

**Q: Can I undo changes?**
- A: For most operations yes (with backups). Always export CSV first as safety backup.

**Q: Does this slow down my website?**
- A: No. Operations run in admin only, not frontend.

**Q: Can I use this on large sites?**
- A: Yes. May take longer for scans (1-2 hours for very large libraries), but handles any size.

**Q: Is there a PRO version?**
- A: Yes. Pro adds automation and advanced features. Free version covers 90% of needs.

## Pro vs Free Features

### Free (Included)
- Metadata editing
- Bulk operations
- CSV export/import
- Find rubbish files
- Detect duplicates
- Track image usage
- Regenerate thumbnails
- Manage image sizes
- Media download

### Pro Features
- Auto-rename uploads (by post title, custom pattern)
- Auto alt text (generated from content)
- AI content generation (ChatGPT, Gemini, Claude)
- Delete/ignore rubbish files
- Merge duplicates with reference updates
- Trash management
- Scheduled scans
- Advanced reporting

[Upgrade to Pro](https://example.com/pro) for advanced automation.

## Keyboard Shortcuts (if configured)

Common shortcut possibilities:
- `Ctrl+E`: Export CSV
- `Ctrl+S`: Save settings
- (Customizable via settings)

Check Settings page for available shortcuts.

## Need Help?

1. **Documentation**: Read specific feature guide (linked above)
2. **Troubleshooting**: Check "Troubleshooting" section in each guide
3. **Support**: Visit plugin support page
4. **FAQs**: Check feature-specific FAQ sections

---

**Ready?** Start with [Media Settings](01-media-settings.md) or [Media Table](02-media-table.md)!
