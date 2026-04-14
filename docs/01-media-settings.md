# Media Settings

The Media Settings section allows you to configure default values and behaviors for media metadata across your entire WordPress site.

## Overview

Media Settings control how metadata (alt text, captions, descriptions) is automatically populated when you upload new media files. These settings ensure consistency and improve SEO without manual editing of each image.

**Location**: Media Library Tools → Settings → Media Settings section

## Feature Breakdown

### 1. Others File Support

Extend media upload capabilities to include additional file types beyond standard images.

**Setting**: Others File Support → SVG

- **Label**: SVG
- **What it does**: Enables upload and management of SVG (Scalable Vector Graphics) files
- **Use case**: When you need to upload vector graphics, logos, or scalable icons
- **Note**: SVG files are sanitized for security before upload

**When to use**:
- Your site needs vector graphics
- You work with designers providing SVG assets
- You want full media library integration for all file types

---

### 2. Auto Alt Text

Configure how alt text is automatically assigned to images on upload.

#### Setting: Use Post Title as Alt Text (PRO FEATURE)

- **Label**: "Default Alt Text Base On Post Title"
- **What it does**: When you upload an image as an attachment to a post, the post's title is automatically used as the alt text
- **Use case**: SEO optimization—images on post pages automatically get descriptive alt text from the post title
- **Requirement**: Image must be uploaded while editing a post
- **Pro Only**: Yes, requires Media Library Tools Pro

**Example**:
- Post title: "How to Bake Chocolate Cake"
- Upload image to this post
- Image alt text automatically becomes: "How to Bake Chocolate Cake"

#### Setting: Default Images Alt Text

Set a fallback alt text strategy for all new uploads.

**Option 1: Image name use as alt text**
- **What it does**: Uses the uploaded file's name (without extension) as alt text
- **Example**: Uploading "sunset-beach.jpg" → alt text becomes "sunset-beach"
- **Available**: Free & Pro

**Option 2: Custom text (with text field)**
- **What it does**: Uses your custom text as alt text for all uploads
- **Field**: Enter your custom alt text
- **Example**: Custom text "Product Photo" → all images get this alt text
- **Available**: Free & Pro

**When to use**:
- Image name option: If your filenames are descriptive and SEO-friendly
- Custom text option: For consistency (e.g., "Gallery Image", "Product Photo")

---

### 3. Caption Settings (PRO FEATURE)

Configure how captions are automatically assigned when uploading images.

#### Setting: Use Post Title as Caption

- **Label**: "Default Caption Text Base On Post Title"
- **What it does**: Uses the post's title as the image caption when uploaded to a post
- **Use case**: Automated caption generation for blog post images
- **Pro Only**: Yes

**Example**:
- Post: "10 Summer Destinations"
- Upload image to this post
- Image caption: "10 Summer Destinations"

#### Setting: Default Caption Text

Choose how captions are generated for all uploads.

**Option 1: Image name use as caption**
- **What it does**: Uses the filename as the caption
- **Example**: "vacation-photo.jpg" → caption becomes "vacation-photo"

**Option 2: Custom text**
- **What it does**: Uses your specified text for all image captions
- **Text field**: Enter your caption text
- **Example**: Custom caption "Featured Image" → all uploads get this caption

---

### 4. Description Settings (PRO FEATURE)

Configure automatic description assignment for media files.

#### Setting: Use Post Title as Description

- **Label**: "Default Description Text Base On Post Title"
- **What it does**: Uses the post's title as the image description when uploaded as a post attachment
- **Use case**: Automatic SEO-friendly descriptions for images
- **Pro Only**: Yes

#### Setting: Default Description Text

Choose how descriptions are generated.

**Option 1: Image name use as description**
- **What it does**: Uses the filename as the description
- **Example**: "client-testimonial.jpg" → description becomes "client-testimonial"

**Option 2: Custom text**
- **What it does**: Uses custom text for all image descriptions
- **Text field**: Enter your description
- **Example**: Custom text "Website Resource" → all uploads get this description

---

## Step-by-Step Usage

### Enable SVG Upload Support

1. Navigate to **Media Library Tools → Settings**
2. Find **Media Settings** section
3. Under "Others File Support", check **SVG**
4. Click **Save Settings** button
5. You can now upload SVG files through the WordPress media library

### Set Image Name as Default Alt Text

1. Go to **Media Library Tools → Settings**
2. In **Auto Alt Text** section, find "Default Images Alt Text"
3. Select **"Image name use as alt text"**
4. Click **Save Settings**
5. From now on, new uploads automatically get alt text from their filename

### Use Custom Alt Text for All Images

1. Go to **Media Library Tools → Settings**
2. In **Auto Alt Text** section, select **"Custom text"**
3. A text field appears—enter your text (e.g., "Product Photo")
4. Click **Save Settings**
5. All future uploads will have this alt text

### Configure Post Title Auto-Captions (Pro)

1. Ensure you have Media Library Tools Pro installed
2. Go to **Media Library Tools → Settings**
3. In **Caption Settings**, check **"Default Caption Text Base On Post Title"**
4. Click **Save Settings**
5. When you upload images while editing a post, the caption is auto-filled with the post title

---

## Settings Explanation

| Setting | Option | Free/Pro | When to Use |
|---------|--------|----------|-------------|
| SVG Support | Enable/Disable | Free | Need to upload vector graphics |
| Alt Text | Image name | Free | Filenames are descriptive |
| Alt Text | Custom text | Free | Need consistent text for all images |
| Alt Text | Post title (Pro) | Pro | Want SEO alt text based on post context |
| Captions | Image name | Free | Filenames work as captions |
| Captions | Custom text | Free | Need consistent captions |
| Captions | Post title (Pro) | Pro | Want captions matching post titles |
| Descriptions | Image name | Free | Filenames work as descriptions |
| Descriptions | Custom text | Free | Need consistent descriptions |
| Descriptions | Post title (Pro) | Pro | Want descriptions from post context |

---

## Important Notes

### Auto-Population Only on Upload
- Settings apply **only to new uploads**
- Existing media files are **not affected**
- Use [Bulk Edit](media-table.md#bulk-edit) to update existing metadata

### Post Title Requirements (Pro)
- Image must be uploaded **while editing a post**
- Images in the Media Library (not attached to a post) won't use post title
- Works with posts, pages, and custom post types

### Custom Text Fallback
If you enable Post Title but upload an image not attached to a post:
- Pro plugin falls back to the custom default text (if configured)
- Then to image filename
- Pro handles this gracefully with the fallback chain

### SVG Security
- SVG files are automatically sanitized
- Malicious scripts in SVG files are removed
- Safe to upload SVGs from external sources

---

## Pro Features

**Upgrade to Pro for**:
- Auto-caption from post titles
- Auto-description from post titles
- Auto alt text based on post context
- Auto rename files based on post title ([Rename Module](media-rename.md))
- AI content generation for alt text, captions, descriptions
- Auto alt text injection on frontend

See [Media Rename](media-rename.md) and [AI Features](#) for more Pro-exclusive automation options.

---

## Troubleshooting

**Q: I enabled "Image name as alt text" but existing images don't have alt text**
- A: Settings only apply to new uploads. Bulk edit existing images to add alt text retroactively.

**Q: Post title option isn't available**
- A: This is a Pro feature. [Upgrade to Pro](https://example.com/pro) to enable it.

**Q: SVG files are blocked from uploading**
- A: Check that "SVG" is enabled in Media Settings. Also verify your WordPress install allows file uploads.

**Q: Custom alt text shows as plain text, not formatted**
- A: Alt text is plain text only—formatting is stripped automatically (security).

---

**Next**: Learn how to manage media files in [Media Table](media-table.md)
