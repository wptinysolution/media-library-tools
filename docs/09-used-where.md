# Used Where

The Used Where tool tracks where images are used across your WordPress site. It identifies which images are actively used in posts, pages, settings, and which images are orphaned (unused).

## Overview

Understanding image usage is critical for:
- **Safe Deletion**: Know if image is used before deleting
- **Update Strategy**: Understand impact of changing/removing images
- **Cleanup**: Identify unused images taking up storage
- **SEO**: See which images drive traffic
- **Organization**: Understand image distribution

**Location**: Media Library Tools → Used Where

## Why Track Image Usage?

### Benefits

- **Prevent Breakage**: Don't delete used images
- **Cleanup Confidence**: Safely delete only truly unused images
- **Migration**: Understand references when migrating
- **Performance**: Optimize by understanding image dependencies
- **Compliance**: Track usage for content audits

### Usage Categories

Images can be used in:
- **Posts/Pages**: Content, featured images, galleries
- **Elementor**: Builder elements, templates
- **WooCommerce**: Product images, galleries
- **Theme Settings**: Logo, header, footer images
- **Meta Fields**: ACF, CMB2, custom metadata
- **WordPress Options**: Settings, widget data

---

## Feature Breakdown

### 1. Scan for Usage

The scanner:
- Crawls all posts, pages, custom post types
- Checks featured images
- Parses post content for image references
- Scans theme settings and widget data
- Inspects custom meta fields (ACF, CMB2)
- Checks WordPress options for image URLs
- Detects frontend image usage (passive tracking)

**Scan Types**:
- **Full Scan**: Backend detection of all references
- **Frontend Tracking**: Passive tracking when users visit pages (Pro)

### 2. Three Tabs: Used, Unused, Trash

#### Used Tab
Shows images actively used on the site:
- Featured images
- Images in post content
- Theme settings
- Widget images
- Meta field images

#### Unused Tab
Images in media library but not used:
- Not featured image for any post
- Not in any post content
- Not in settings
- Orphaned attachments

#### Trash Tab
Images moved to trash awaiting deletion:
- Can restore to Unused/Used tabs
- Can permanently delete
- Shows deletion countdown (if applicable)

### 3. Usage Details

For each image, see:

| Info | Meaning |
|------|---------|
| **File Name** | Image filename |
| **Alt Text** | Image alt text |
| **Usage Count** | Number of posts using it |
| **Usage Type** | Where it's used (featured, content, settings, meta) |
| **Posts** | Which posts reference it (expandable) |
| **Last Updated** | When image last appeared in scan |

---

## Step-by-Step Usage

### Scan for Image Usage

1. Go to **Media Library Tools → Used Where**
2. Click **"Scan Media Usage"** button (or "Re-scan" if already scanned)
3. Progress bar appears:
   - Checking featured images
   - Scanning post content
   - Checking theme settings
   - Scanning custom metadata
   - Progress: "Processed 45/250 posts"
4. **Leave page open** while scanning
5. Scan completes and results display

**Scan Time**:
- Small sites (100 images): 2-5 minutes
- Medium sites (500 images): 5-15 minutes
- Large sites (2000+ images): 15-60 minutes

### View Used Images

1. Go to **Used Where**
2. Click the **"Used"** tab
3. Table shows all actively used images:
   ```
   Image Name | Usage Count | Posts Using
   featured.jpg | 5 | "Blog Post", "About", "Team Page"
   logo.png | 3 | Settings, Header, Footer
   product.jpg | 12 | Multiple product pages
   ```
4. Click image row to expand and see details
5. See which posts use each image

### View Unused Images

1. Go to **Used Where**
2. Click the **"Unused"** tab
3. Table shows images not used anywhere:
   - Media library attachments
   - Not featured images
   - Not in content
   - Not in settings
   - Safe to delete
4. Select images to bulk delete or trash
5. Free up storage space

### Move Images to Trash

To safely delete images:

1. Go to **"Unused"** tab
2. Select images you want to remove (checkboxes)
3. Click **"Move to Trash"** button
4. Confirmation modal: "Move X images to trash?"
5. Click **"Confirm Move"**
6. Images moved to Trash tab
7. **Decision period**: Images stay in trash for period (Pro feature)

### Restore from Trash

If you moved an image to trash by mistake:

1. Go to **"Trash"** tab
2. Find the image
3. Click **"Restore"** button
4. Image moved back to Unused tab
5. Can now use again if needed

### Permanently Delete from Trash

To remove images permanently:

1. Go to **"Trash"** tab
2. Find image(s)
3. Select checkbox(es)
4. Click **"Delete Permanently"** button
5. Confirmation: "Permanently delete X images?"
6. Click **"Confirm Delete"**
7. Images deleted from WordPress and disk
8. **Cannot be recovered** (permanent)

### Search Images

To find specific images:

1. In search bar (top right), type:
   - Image filename
   - Alt text
   - Post name
2. Search filters all three tabs
3. Results update in real-time
4. Clear search to see all again

### Filter by Per-Page

Adjust items shown per page:

1. Find **"Per page"** dropdown (top toolbar)
2. Select: 10, 25, 50, or 100
3. Table updates to show selected count
4. Pagination updates accordingly

---

## Common Workflows

### Find All Product Images and Their Usage

1. **Scan** for usage
2. Search for "product" in the Used tab
3. See all product images with counts
4. Identify which product pages use each image
5. Understand distribution

### Identify Orphaned Images

1. Go to **"Unused"** tab (after scan)
2. Review all unused images
3. Spot-check a few to confirm they're truly unused
4. Select all or batch (using Per page filter)
5. Move to Trash for deletion
6. Free up storage

### Prepare for Theme Change

1. **Scan** for usage
2. Review **"Used"** tab to see all active images
3. Note images used in theme settings
4. Export image list (if needed)
5. Plan new theme image requirements
6. After new theme, can clean up old theme images

### Safe Image Deletion

1. Find image you want to delete
2. Check **Used Where** to see if it's used
3. If in **Unused** tab: Safe to delete
4. If in **Used** tab: Decide if safe to remove
5. If removing from Used, update posts first
6. Then move to Trash and delete

### Audit Theme Settings Images

1. **Scan** for usage
2. Look for images in **Settings**, **Logo**, **Header**, **Footer**
3. Verify they're the intended images
4. Replace if incorrect images set
5. Re-scan to confirm new images detected

---

## Frontend Usage Tracking (Pro)

### Passive Visitor Tracking

Pro version includes background tracking:
- Automatically detects images when users visit pages
- Complements backend scan
- Captures images loaded dynamically
- Improves accuracy for builder-created content

**How it works**:
- Frontend tracker runs on page footer
- Detects images in page DOM
- Records usage in background
- No impact on page performance
- Optional to disable (Settings)

### Enable Frontend Tracking

1. Go to **Settings → Renamer Settings**
2. Find **"Frontend Image Usage Tracking"**
3. Check **"Enable passive image usage tracking"**
4. Click **Save Settings**
5. From now on, usage tracked automatically

### When to Use

- **Enable**: For highly dynamic sites (elementor, page builders)
- **Disable**: If concerned about performance (rarely needed)
- **Default**: Enabled in Pro by default

---

## Scan Settings

### Manual Scan

Click button to start on-demand scan:
- Scans current state
- Updates usage for all images
- Shows progress in real-time

### Scheduled Scans (Pro)

Automatic scans run on schedule:
- Daily, weekly, monthly options
- Runs in background
- Updates usage automatically
- No user action required

### Clear Results

To start fresh:

1. Click **"Clear Results"** button
2. Confirmation: "Remove all scan results?"
3. Previous scan data deleted
4. Can now run new scan

---

## Important Notes

### Scan Accuracy

**Very Accurate For**:
- Featured images (WordPress native)
- Post content (post_content field)
- Theme settings (theme_mods option)
- Standard meta fields

**Good Coverage For**:
- Builder content (Elementor, etc.)
- WooCommerce data
- Custom meta fields (ACF, CMB2)

**May Miss**:
- Hard-coded image tags in custom theme code
- External site references
- Images loaded via JavaScript
- Images in PDF attachments

### Usage Status

**Used**: Image appears in at least one post/page/setting
**Unused**: Image in media library but not referenced anywhere
**Trash**: Image moved to trash, awaiting deletion

### Safety Guarantees

- **Safe to Delete (Unused)**: All unused images are truly unused
- **Check before Delete (Used)**: Used images appear in actual posts
- **Non-destructive Scan**: Scan doesn't modify anything

---

## Troubleshooting

**Q: Scan showing 0 results**
- A: Scan in progress or not started. Wait for scan to complete. Check progress bar.

**Q: Image shows as Unused but I know it's used**
- A: May be:
  - Hard-coded in theme (not detected)
  - Loaded via JavaScript
  - External image URL (not WordPress)
  - From previous scan before image was used
  - Re-scan to update results

**Q: Some posts not showing for image usage**
- A: Image might be:
  - In post draft (not published)
  - In scheduled post (not live yet)
  - In private post (scanned but not shown)
  - Re-scan to refresh results

**Q: Scan taking very long**
- A: Normal for large sites (500+ posts). Can take 30-60 minutes. Leave page open—scan runs in background.

**Q: Search not finding image I'm looking for**
- A: Search is exact-match. Try partial filename or alt text. Or re-scan if image recently added.

**Q: Delete keeps failing with error**
- A: Permission issue or image still being referenced. Check:
  - File permissions (644)
  - WordPress database integrity
  - Plugin conflicts (disable plugins temporarily)

---

## Pro Features

**Upgrade to Pro for**:
- **Frontend Tracking**: Automatic passive image detection
- **Trash Management**: Images stay in trash before deletion
- **Detailed Reports**: Export usage reports
- **Scheduled Scans**: Automatic scanning on schedule
- **Quick Actions**: Bulk operations from results
- **Merge with Duplicates**: Delete duplicates while updating references

---

## Best Practices

1. **Scan Before Deleting**: Always check Used Where before deletion
2. **Scan After Major Changes**: Re-scan after theme/plugin changes
3. **Use Unused Tab First**: Delete unused images before deleting used ones
4. **Verify Posts**: For used images, verify they're actually needed
5. **Archive Before Bulk Delete**: Backup before large deletions
6. **Regular Cleanup**: Monthly scans to track accumulation

---

**Next**: Learn about [Image Sizes](image-sizes.md) to manage WordPress image dimensions
