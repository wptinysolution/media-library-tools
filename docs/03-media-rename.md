# Media Rename

The Media Rename tool allows you to rename media files in bulk or individually. It supports multiple renaming strategies to help organize your media library efficiently.

## Overview

Media Rename helps you:
- Rename individual files
- Rename multiple files at once
- Apply prefixes and suffixes to filenames
- Use intelligent rename strategies based on post titles, metadata, etc.
- Maintain file organization and SEO-friendly naming

**Location**: Media Library Tools → Media Rename

## Feature Breakdown

### 1. File Rename Prefix & Suffix (PRO FEATURE)

Add consistent prefixes and suffixes to all renamed files.

#### Rename Prefix
- **What it does**: Adds text at the **beginning** of the filename
- **Example**:
  - Original: "sunset.jpg"
  - Prefix: "blog-"
  - Result: "blog-sunset.jpg"
- **Use case**: Organize by category (e.g., "product-", "event-", "blog-")
- **Note**: Applied automatically when using auto-rename settings
- **Available**: Pro only

#### Rename Suffix
- **What it does**: Adds text at the **end** of the filename (before extension)
- **Example**:
  - Original: "photo.jpg"
  - Suffix: "-2024"
  - Result: "photo-2024.jpg"
- **Use case**: Add version numbers, dates, or categories
- **Available**: Pro only

---

### 2. Rename Based on Attached Posts (PRO FEATURE)

Automatically rename images based on the post they're attached to.

#### Setting: Auto Rename by Post Title

- **What it does**: Rename uploaded images to match the title of the post they're attached to
- **Trigger**: When editing a post and uploading a new image
- **Example**:
  - Post title: "How to Bake Brownies"
  - Upload image
  - Image renamed to: "how-to-bake-brownies.jpg"
- **Format**: Slugified (lowercase, spaces to hyphens, special characters removed)
- **Use case**: Ensure images have SEO-friendly names matching post content
- **Available**: Pro only

---

### 3. Others Media Auto Rename (PRO FEATURE)

Automatically rename media uploaded outside of post editing context.

#### Setting: Custom Auto Rename

- **What it does**: Automatically rename any media upload with a custom filename pattern
- **Requires**: A custom filename pattern (field appears when enabled)
- **Example**:
  - Pattern: "media"
  - Upload image
  - Image renamed to: "media.jpg", "media-2.jpg" (with counter if duplicate)
- **Use case**: Keep all uploads consistent regardless of original filename
- **Available**: Pro only

---

### 4. Rename Table Interface

The Rename page displays all media files in a table with editing capabilities.

#### Columns

| Column | Purpose |
|--------|---------|
| **Thumbnail** | Image preview |
| **Current Name** | Original/current filename |
| **New Name** | Editable field for new filename |
| **Rename Button** | Apply the rename |
| **Status** | Shows success/error on rename |

#### Inline Editing

1. Click in the "New Name" field
2. Edit the filename (extension updates automatically)
3. Click the **Rename** button
4. File is renamed immediately
5. Success/error status displays

---

## Step-by-Step Usage

### Rename a Single Image File

1. Go to **Media Library Tools → Media Rename**
2. Find the image in the table (use search to filter)
3. Click in the **New Name** field for that image
4. Delete old name and type new one (without extension)
5. Click the **Rename** button next to it
6. Success message appears—file is renamed
7. On disk and in WordPress database updated

**Example Rename**:
- Current: "IMG_12345.jpg"
- Type: "blog-post-featured-image"
- Result: "blog-post-featured-image.jpg"

### Enable Auto Rename by Post Title (Pro)

1. Go to **Media Library Tools → Settings**
2. Find **Renamer Settings** section
3. Check **"Auto Rename by post title"**
4. Click **Save Settings**
5. From now on:
   - Go to edit a post
   - Upload a new image to that post
   - Image automatically renamed to post title slug

### Apply Prefix to All Renames (Pro)

1. Go to **Media Library Tools → Settings**
2. In **Renamer Settings**, find **"File Rename Prefix"**
3. Enter your prefix (e.g., "blog-", "product-", "event-")
4. Click **Save Settings**
5. All future renames automatically include this prefix
6. The prefix is added at the beginning of the filename

**Example**:
- Prefix: "product-"
- Rename "shoes.jpg" to "running-shoes"
- Result: "product-running-shoes.jpg"

### Apply Suffix to New Uploads (Pro)

1. Go to **Media Library Tools → Settings**
2. In **Renamer Settings**, find **"File Rename Suffix"**
3. Enter your suffix (e.g., "-2024", "-v2", "-final")
4. Click **Save Settings**
5. All future renames include this suffix
6. Suffix is added before the file extension

**Example**:
- Suffix: "-2024"
- Rename "photo.jpg" to "team-photo"
- Result: "team-photo-2024.jpg"

### Rename Multiple Files in Bulk

1. Go to **Media Rename**
2. **Option A**: Use the **Bulk Rename Modal** if available
   - Select multiple images
   - Choose rename pattern
   - Apply to all
3. **Option B**: Rename individually
   - Edit "New Name" field for first image
   - Click Rename
   - Repeat for others

---

## Bulk Rename Strategies

### Strategy: Prefix All Product Images

1. Go to **Settings → Renamer Settings**
2. Set **Rename Prefix**: "product-"
3. Go to **Media Rename** page
4. For each product image:
   - New Name: "blue-shirt"
   - Auto becomes: "product-blue-shirt.jpg"

### Strategy: Date-Based Organization

1. Set **Rename Suffix**: "-2024-04"
2. Rename images as normal
3. All files end with "-2024-04" before extension
4. Easy to sort and identify by date

### Strategy: Post Title Matching

1. Check **"Auto Rename by post title"** in Settings
2. When editing a blog post, upload images
3. Images automatically named after post
4. No manual renaming needed for post images

---

## Settings Explanation

| Setting | Default | Effect | Type |
|---------|---------|--------|------|
| Rename Prefix | (empty) | Text prepended to all renames | Pro |
| Rename Suffix | (empty) | Text appended before extension | Pro |
| Auto Rename by Post Title | Off | Auto-rename uploads attached to posts | Pro |
| Custom Auto Rename | Off | Auto-rename all uploads with pattern | Pro |

---

## Important Notes

### File Extension Handling
- Extension is **always preserved** automatically
- "jpg", "png", "gif" etc. always correct
- Don't type extension in the New Name field
- Extensions are case-preserved from original

### Filename Rules
- Special characters are removed automatically (for web safety)
- Spaces converted to hyphens
- Lowercase preferred (plugin enforces this)
- Maximum length varies by filesystem

### Auto-Rename Behavior
- **Post-based rename**: Triggers when uploading to post
- **Custom auto-rename**: Triggers on any upload in WordPress
- **Prefix/Suffix**: Applied to all manual renames + auto-renames
- **Order**: [Prefix] + [Name] + [Suffix] + [.extension]

### URL Updates
When you rename a file:
- Database references updated automatically
- Posts using the image still work (via WordPress attachment system)
- Direct file URL changes
- CDN/cache may need purge

---

## Advanced Features (Pro)

### Auto Rename Strategies

Pro version supports multiple renaming sources:

1. **Post Title**: Renames to attached post's title
2. **Product SKU**: If using WooCommerce (Pro)
3. **Alt Text**: Uses image's alt text (Pro)
4. **Custom Pattern**: Regex-based custom patterns (Pro)

### Bulk Merge with Duplicates

When combined with the [Duplicates](duplicates.md) feature, Pro can:
- Rename and merge duplicate files
- Update all references automatically
- Keep the better-named file

---

## Troubleshooting

**Q: Rename fails with an error**
- A: Check that the new filename is valid (no special characters). Try again.

**Q: Images renamed but frontend still shows old filename**
- A: WordPress stores images by database ID, not filename. Frontend should update after page refresh. If not, clear caches.

**Q: Auto rename by post title isn't working**
- A: Verify it's enabled in Settings. Image must be uploaded while editing the post (attached to the post).

**Q: Prefix/Suffix not appearing**
- A: Check Settings → Renamer Settings. Verify the prefix/suffix is saved. Apply to new renames, not existing files.

**Q: Special characters in filename getting removed**
- A: Deliberate for safety. WordPress only allows standard characters in filenames. You can use hyphens and underscores.

---

## Common Use Cases

### Case 1: SEO-Friendly Organization
```
Prefix: "blog-"
Suffix: "-featured"
Result: blog-article-title-featured.jpg
```

### Case 2: Product Management
```
Use Post Title auto-rename for product uploads
Result: product-names automatically match product pages
```

### Case 3: Date-Based Backup
```
Suffix: "-2024-04-11"
Result: image-2024-04-11.jpg (easy to find by date)
```

---

**Next**: Learn about [Regenerate Thumbnails](regenerate-thumbnails.md) for image optimization
