# Regenerate Thumbnails

The Regenerate Thumbnails tool rebuilds all image sizes for your media files. This is essential when switching themes, adding new image sizes, or fixing broken thumbnails.

## Overview

When you upload images to WordPress, the system creates multiple versions (thumbnails) at different sizes for different uses. Regenerate Thumbnails rebuilds these versions from the original file, ensuring compatibility with your current theme and image size settings.

**Location**: Media Library Tools → Regenerate Thumbnails

## Why Regenerate Thumbnails?

### Common Scenarios

1. **Theme Changed**: New theme requires different image sizes
2. **New Image Sizes Added**: Custom sizes defined but old images don't have them
3. **Broken Thumbnails**: Images display incorrectly or don't appear
4. **Server Migration**: Images moved to new server, thumbnails lost
5. **Image Size Settings Changed**: WordPress regenerates to match new dimensions

---

## Feature Breakdown

### 1. Registered Image Sizes

WordPress and your active theme define multiple image sizes:

| Size | Use |
|------|-----|
| **Thumbnail** (150x150) | Admin library, galleries |
| **Medium** (300x300) | Blog post content |
| **Large** (1024x1024) | Full-width content |
| **Full** | Original uploaded size |
| **Custom Sizes** | Theme-specific sizes |

When you regenerate, the tool creates these versions from the original file.

### 2. Batch Processing

Regenerating all images at once could hang the browser. The tool processes in batches:

- **Batch Size**: Configurable number of images per request
- **Progress Bar**: Visual indication of completion
- **Real-time Updates**: Shows processed count and total
- **Resume Capable**: Can stop and resume later

### 3. Storage Impact

**Before Regenerating**:
- Check available disk space
- Each image generates versions for all registered sizes
- A single uploaded image can create 5-20 smaller versions

**Example**:
- Original image: 3 MB
- Thumbnail, medium, large, etc.: +2-5 MB total
- Total per image: 5-8 MB

---

## Step-by-Step Usage

### Check Available Image Sizes

1. Go to **Media Library Tools → Regenerate Thumbnails**
2. Look for **"Registered Image Sizes"** section
3. View list of all image sizes (dimensions in pixels)
4. Note any custom sizes your theme requires

### Start Regeneration

1. Go to **Regenerate Thumbnails** page
2. Review the list of image sizes (informational)
3. Click the **"Regenerate Thumbnails"** button
4. A progress bar appears showing:
   - Number of images processed
   - Total images to process
   - Percentage complete
5. **Do not close the page** while regenerating

### Monitor Progress

During regeneration:
- Progress bar updates in real-time
- Shows "Processing: 45/200 images (22%)"
- Speed varies based on:
  - Number of images
  - Server processing power
  - Image sizes and dimensions

### Pause/Resume Regeneration

- **Pause**: Close the page (process stops)
- **Resume**: Return to the page and click **"Regenerate Thumbnails"** again
  - Continues from where it left off

### Completion

- Progress bar reaches 100%
- Message appears: "Regeneration complete!"
- All image sizes now regenerated
- Can safely close page

---

## Settings Explanation

### Batch Size (Advanced Setting)

Typical setting not exposed in UI, but affects performance:

- **Larger batches** (50+): Faster total time, higher server load
- **Smaller batches** (5-10): Slower total time, lighter server load
- **Default**: 20 images per request

*Usually automatic—adjust only if experiencing timeout errors*

### Image Size Categories

**WordPress Core Sizes**:
- Thumbnail, Medium, Large, Full (always present)

**Theme Sizes**:
- Added by your active theme (e.g., "hero-image", "card-thumbnail")
- Changes when you switch themes

**Plugin Sizes**:
- Added by plugins (Media Library Tools, image optimization, etc.)

When you regenerate, **all registered sizes** are rebuilt for **all images**.

---

## Important Notes

### What Gets Regenerated

✓ All existing images get new versions for all registered sizes
✓ Original files are not modified
✓ Metadata (alt text, captions) preserved
✗ Does NOT rename files
✗ Does NOT delete old versions (safe)

### Safety Considerations

- **Non-destructive**: Original files always safe
- **Resumable**: Can pause/resume without issues
- **Reversible**: Old thumbnails kept (safe to run multiple times)
- **Backups**: Consider backup before regenerating very large libraries (500+ images)

### Storage & Performance

**Disk Space**: Ensure ~2x storage space for all images
**Time**: ~1 minute per 100 images (varies by server)
**Server Load**: Moderate impact during regeneration

**Best Practice**:
- Regenerate during off-peak hours
- Compress images first if storage is limited
- Run once per theme change, not repeatedly

### File Size Concerns

If images are very large:
1. Consider [Image Compression](https://example.com) first
2. Remove unnecessary image sizes from theme
3. Use Progressive JPEGs to reduce file size
4. Consider lazy loading for performance

---

## Common Workflows

### After Installing New Theme

1. Activate new theme
2. Go to **Media Library Tools → Regenerate Thumbnails**
3. Review registered image sizes
4. Click **Regenerate Thumbnails**
5. Wait for completion
6. Check theme displays correctly

### After Adding Custom Image Sizes

If you added new image sizes via code/plugin:

1. Ensure plugin/theme is activated
2. Go to **Regenerate Thumbnails**
3. New sizes should appear in the list
4. Click **Regenerate**
5. Existing images now have new sizes

### Troubleshooting Broken Images

1. Go to **Media Library Tools → Regenerate Thumbnails**
2. Click **Regenerate Thumbnails**
3. Wait for completion
4. Check frontend—images should display correctly
5. If still broken, clear caches (browser, server)

---

## Troubleshooting

**Q: Regenerate button is disabled**
- A: May be already running. Wait a moment and refresh the page.

**Q: Progress bar stuck at same percentage**
- A: Large images take time. Leave page open and wait. Or close/refresh to check actual progress.

**Q: Gets timeout error partway through**
- A: Server timeout on large libraries. Process resumes where it stopped—just click Regenerate again.

**Q: New image size not appearing after regenerate**
- A: Verify size is properly registered. Check with theme developer that size registration is correct. Regenerate again.

**Q: File size increased significantly**
- A: Normal—you now have versions of all images at all sizes. Images take more disk space now but display correctly everywhere.

**Q: Images still look bad after regenerate**
- A: May be CDN caching old versions. Purge CDN cache. Or images were already low quality—regenerate only creates versions, doesn't improve quality.

---

## Pro Features

Pro version adds:
- Scheduled automatic regeneration
- Regenerate on theme update
- Better progress tracking with ETA
- Batch regeneration (regenerate specific image sizes)

---

**Next**: Learn about [Image Sizes](image-sizes.md) to manage registered sizes
