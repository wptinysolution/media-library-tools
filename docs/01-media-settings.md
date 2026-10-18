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

### 5. EXIF on Upload

Reads EXIF data from new JPEG, TIFF and WebP uploads. Every option is **off by default** and needs the PHP EXIF extension. Existing images are not affected.

#### Setting: Process EXIF on Upload

- **Label**: "Read EXIF data from new JPEG, TIFF and WebP uploads"
- **What it does**: Stores each upload's camera (`_tsmlt_exif_camera`) and GPS location (`_tsmlt_exif_gps_lat`, `_tsmlt_exif_gps_lng`) as attachment meta, used by the media query's camera and has-GPS filters. Turns on the options below.
- **Changes the file**: No

#### Setting: Fill Missing Details from EXIF

- **Label**: "Fill empty title, alt text and caption"
- **What it does**: Fills only empty fields: the title from the camera make and model, the alt text from the file name, and the caption from the EXIF image description. WordPress usually sets a title from the file name, and the Alt Text setting above may already set alt text, so in practice this mostly fills the caption.
- **Changes the file**: No

#### Setting: Use Date Taken

- **Label**: "Set the media upload date to the date the photo was taken"
- **What it does**: Sets the attachment's date to the EXIF date taken (`DateTimeOriginal`, else `DateTime`). The file stays in its current upload folder.
- **Changes the file**: No

#### GPS removal on upload (Pro, no setting yet)

The Pro plugin removes GPS data from new JPEG uploads when the `auto_remove_gps_on_upload` option is enabled, the **Process EXIF on Upload** switch is on and the Pro licence is active. There is no settings field for it yet; the free plugin never saves this option. Removal runs when WordPress adds the attachment, before it creates the scaled, rotated and resized copies, so those copies are made from the GPS-free file.

HEIC uploads are different: WordPress converts them to JPEG only while generating the image sizes, after the attachment is added, and keeps the attachment's MIME type as `image/heic`. The Pro plugin then removes GPS from those JPEG files (the scaled file and the sizes). The HEIC original itself cannot be cleaned (see below).

The same GPS-only removal is used by the Pro bulk and batch GPS modes (the `tsmlt_bulk_remove_exif` AJAX action with `mode: "gps"` and `tsmlt_exif_strip_batch` with `gps_only`). The admin screens do not offer a button for them yet.

What GPS-only removal does:
- **JPEG files only.** Whether an attachment is a JPEG is decided by its attached file, so HEIC uploads WordPress converted to JPEG are included. Other formats are left unchanged and reported as unsupported.
- **Every JPEG file of the attachment**: the attached file, the original WordPress keeps for scaled or rotated images, every generated size, and the pre-edit files WordPress keeps after an image is edited.
- **Files that are not JPEG are never claimed clean.** If the original or another file is a HEIC/HEIF or TIFF that still contains GPS (or cannot be read to check), the JPEG files are cleaned and the stored location is removed, but the operation reports that file as not cleaned and the attachment is not marked GPS-free. WebP and AVIF copies (made by WordPress's image editor or the plugin's converter, which keep no metadata) are skipped: PHP cannot read their EXIF.
- **EXIF**: the GPS directory pointer is removed and the GPS directory and its values are overwritten with zero bytes.
- **XMP**: properties named `<prefix>:GPS…` (for example `exif:GPSLatitude`) are overwritten with spaces; the XMP stays well-formed.
- **Nothing else changes**: the image is not decoded or re-encoded. Only those bytes are overwritten, so the file size, image data, orientation, other EXIF tags (camera, date, exposure), other XMP properties, ICC profiles and IPTC data stay byte-for-byte the same.
- **Verified before it counts**: each edited file is checked for GPS and unchanged image data and dimensions, written to a temporary file, checked again, then moved over the original. Only when every file of the attachment passes is the attachment marked (`_tsmlt_exif_gps_removed`) and its stored location deleted — the EXIF filter index (`_tsmlt_exif_gps_lat`/`_lng`) and the coordinates saved by the EXIF editor. If any file fails, nothing is marked and the stored location is kept; files already cleaned stay clean, and running it again retries the rest.

Known limitations:
- Location text such as IPTC city or country fields is not removed.
- GPS data stored inside camera maker notes is not detected or removed.
- **Extended XMP** (XMP split across several segments) containing GPS is refused: the file is left unchanged.
- An XMP packet is refused (left unchanged) when anything that looks like a `<prefix>:GPS…` name remains after removal — including such text inside an unrelated property value — or when it is not in a form that can be edited safely.
- Malformed or truncated JPEGs, and files that cannot be written, are refused and left unchanged.

**Removing all EXIF** (the EXIF Data page's **Remove EXIF Data** button and the Pro full strip) is different: it re-saves the attached JPEG through GD at quality 90, which removes all EXIF, XMP and ICC data, including the orientation tag, and recompresses the image. It processes only the attached file; the kept original, generated sizes and pre-edit files keep their metadata.

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
| EXIF on Upload | Process EXIF on upload | Free | Record camera and location of new uploads |
| EXIF on Upload | Fill missing details | Free | Captions from the camera's image description |
| EXIF on Upload | Use date taken | Free | Media Library dates should match when photos were taken |

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
