# Image Sizes

The Image Sizes tool manages WordPress image size settings. You can view registered image dimensions, disable unnecessary sizes to reduce storage, and understand how images are used at different sizes.

## Overview

WordPress creates multiple sizes of each image:
- **Thumbnail**: Small preview (150×150)
- **Medium**: Medium display (300×300)
- **Large**: Large display (1024×1024)
- **Full**: Original uploaded size
- **Custom**: Theme and plugin-specific sizes

Managing these sizes helps:
- **Reduce Storage**: Disable unused sizes
- **Improve Performance**: Fewer image versions = faster loading
- **Optimize CDN**: Fewer files to cache
- **Organize**: Understand what sizes exist and why

**Location**: Media Library Tools → Image Sizes

## Why Manage Image Sizes?

### Benefits

- **Storage Efficiency**: Disable sizes you don't use
- **Performance**: Fewer image files = faster CDN
- **Bandwidth**: Fewer files transmitted
- **Organization**: Understand theme/plugin requirements
- **Flexibility**: Add custom sizes for special needs

### Example Optimization

Site with 1000 images:
- 5 unused image sizes
- ~2 MB per size per image
- 1000 images × 5 sizes × 2 MB = 10 GB wasted
- Disable unused sizes: Free 10 GB

---

## Feature Breakdown

### 1. Registered Image Sizes

View all image sizes WordPress knows about:

| Size Name | Dimensions | Source | Used? |
|-----------|-----------|--------|-------|
| Thumbnail | 150×150 | WordPress | Yes |
| Medium | 300×300 | WordPress | Yes |
| Large | 1024×1024 | WordPress | Yes |
| hero-banner | 1200×600 | Theme | Yes |
| card-image | 300×300 | Plugin | No |

**Columns**:
- **Name**: Official size name
- **Width × Height**: Pixel dimensions
- **Source**: WordPress core, Theme, Plugin
- **Hard Crop**: Whether image is cropped or stretched

### 2. Disable Unused Sizes

You can disable image sizes that:
- Theme doesn't use
- Plugin no longer active
- Custom sizes you created but abandoned
- Old sizes from previous theme

**Effect of Disabling**:
- ✓ New uploads don't create this size
- ✓ Existing images can have this size deleted via [Regenerate](regenerate-thumbnails.md)
- ✓ Storage saved on future uploads
- ✗ Existing files not immediately deleted (must regenerate)

### 3. Hard Crop vs Soft Crop

**Hard Crop** (On):
- Image cropped to exact dimensions
- Aspect ratio may change
- No letterboxing
- Example: Crop 16:9 image to 1:1 square

**Soft Crop** (Off):
- Image scaled to fit dimensions
- Aspect ratio preserved
- May have padding
- Example: Fit image within 300×300 box

---

## Step-by-Step Usage

### View All Image Sizes

1. Go to **Media Library Tools → Image Sizes**
2. Table displays all registered sizes:
   ```
   Thumbnail (150 × 150) - Core - Enabled
   Medium (300 × 300) - Core - Enabled
   Large (1024 × 1024) - Core - Enabled
   hero-image (1200 × 600) - Theme (My Theme) - Enabled
   card-image (300 × 300) - Plugin (Plugin Name) - Disabled
   ```
3. Review which are enabled/disabled
4. Note which are used by your theme

### Register Custom Image Size

If your theme requires a custom size:

1. Check if already registered
2. If not, theme must register it via code
3. Or use:
   - **Settings** (some themes allow)
   - **Advanced Custom Fields** (ACF plugin)
   - **WordPress code customization**
4. Once registered, it appears in Image Sizes
5. Can then be managed here

**Note**: Registration happens in theme/plugin code, not in this UI.

### Disable Unused Image Size

1. Find image size you don't need
2. Click **"Disable"** toggle/button
3. Confirmation: "Disable [Size Name]?"
   - "Future uploads won't create this size"
   - "Existing images still have this size"
4. Click **"Confirm"**
5. Size disabled
6. Existing images still have the file (use Regenerate to delete)

**Example**:
```
Disable "featured-image-large" (1600x900)
- New uploads won't create this size
- 50 existing images still have it
- Run Regenerate to delete from existing
```

### Re-enable Disabled Size

If you disabled a size but now need it:

1. Find disabled size in list
2. Click **"Enable"** button
3. Size re-enabled
4. New uploads create this size again
5. Old uploads still lack this size (run Regenerate to add)

### Check Size Dependencies

To understand what uses a size:

1. Find size in list
2. Check **Source** column
   - **Core**: Built-in, generally always needed
   - **Theme**: Current theme needs it
   - **Plugin**: Active plugin needs it
3. Theme/Plugin name shown in brackets
4. Careful disabling plugin/theme sizes (might break display)

---

## Common Workflows

### Optimize for Mobile-First Theme

1. View **Image Sizes**
2. Identify sizes NOT used on mobile:
   - Large desktop-only sizes
   - Unused editorial sizes
3. Disable those sizes
4. Keep mobile and medium sizes
5. Run [Regenerate](regenerate-thumbnails.md) to delete old files
6. Storage reduced by ~20-30%

### Clean Up After Theme Change

1. Disable theme A's custom sizes
2. Enable theme B's custom sizes
3. Run [Regenerate Thumbnails](regenerate-thumbnails.md)
4. Old theme files deleted
5. New theme files created

### Understand Theme Requirements

1. Go to **Image Sizes**
2. Look at sizes from current theme
3. Note all custom sizes
4. Understand what each is used for
5. Don't disable theme-specific sizes (breaks display)
6. Only disable truly unused sizes

### Reduce Storage for Large Library

1. View **Image Sizes**
2. Identify largest (highest resolution) sizes
3. Check if actually needed
4. Disable unused large sizes
5. Run **Regenerate Thumbnails**
6. Track storage freed

---

## Settings Explanation

### Core WordPress Sizes

These are always present and generally needed:

| Size | Typical Use | Safe to Disable? |
|------|------------|-----------------|
| Thumbnail (150×150) | Admin library | No |
| Medium (300×300) | Content body | Maybe |
| Large (1024×1024) | Featured images | Maybe |
| Full | Original file | No (preserves original) |

**Recommendation**: Keep Core sizes unless you're certain they're unused.

### Theme-Specific Sizes

Common theme sizes:

| Size | Typical Dimensions | Purpose |
|------|------------------|---------|
| hero-image | 1200×600+ | Full-width banner |
| featured-image | 800×450 | Post featured image |
| thumbnail-image | 200×200 | Small grid previews |
| mobile-image | 400×300 | Mobile breakpoint |

**Recommendation**: Keep theme sizes active (disabling breaks design).

### Hard Crop Settings

Most sizes have crop preference:

- **Enabled**: Crop to exact dimensions (no padding)
- **Disabled**: Scale to fit (may have padding)

**Usually set by**: Theme or core WordPress
**You can**: View but usually can't change here

---

## Important Notes

### When to Disable Sizes

✓ Disabled plugin's custom sizes (plugin inactive)
✓ Very old theme sizes (no longer used)
✓ Custom sizes you created but abandoned
✓ Duplicate functionality (two sizes same dimensions)

✗ Core WordPress sizes (Thumbnail, Medium, Large, Full)
✗ Active theme sizes (breaks display)
✗ Sizes you're unsure about

### Disabling vs Deleting

**Disable**:
- Stops creating for new uploads
- Doesn't delete existing files
- Can be re-enabled anytime
- Reversible

**Delete (via Regenerate)**:
- Permanently removes files
- Requires regeneration
- Not easily reversible
- Permanent

### Storage Impact

Disabling a size:
- **Immediate**: No effect (existing files remain)
- **Ongoing**: New uploads won't create this size (saves space)
- **Total Impact**: Depends on frequency of uploads

**Example**:
- Disable size that's 2 MB per image
- Upload 100 images/month
- Saves 200 MB/month = 2.4 GB/year

### Performance Impact

Disabled sizes:
- ✓ Faster regeneration (fewer sizes to create)
- ✓ Faster uploads (fewer processing)
- ✓ Faster server (fewer files written)
- ✗ No frontend impact (already created)

---

## Troubleshooting

**Q: Can't disable a size**
- A: May be required by theme/plugin. Contact theme developer if unsure. Disabling core sizes is prevented.

**Q: Disabled a size but images still have it**
- A: Existing images keep disabled size. Run [Regenerate Thumbnails](regenerate-thumbnails.md) to remove it from old images.

**Q: Size appeared but I don't want it**
- A: Plugin or theme added it. Disable here to stop creating new versions. Run Regenerate to remove from existing images.

**Q: Changed theme but old sizes still showing**
- A: Old theme's sizes still registered. Disable them and run Regenerate to remove from disk.

**Q: How do I add a custom size?**
- A: Use theme customization, ACF plugin, or code. Once registered by theme/plugin, appears here to manage.

---

## Pro Features

**Upgrade to Pro for**:
- Custom size registration UI (create sizes without code)
- Advanced size management
- Size-specific optimization rules
- Auto-disable unused sizes
- Scheduled size cleanup

---

## Best Practices

1. **Understand Before Disabling**: Know why a size exists
2. **Test After Disabling**: Check theme still displays correctly
3. **Disable Conservatively**: Only disable sizes you're certain aren't used
4. **Regenerate After Changes**: Delete old sizes to free storage
5. **Monitor Storage**: Track impact of disabled sizes
6. **Document Changes**: Note why you disabled each size

---

**Next**: Learn about [Media Download](media-download.md) for bulk media operations
