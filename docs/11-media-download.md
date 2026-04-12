# Media Download (Shortcode)

The Media Download feature provides shortcodes to add download buttons for individual media files on your WordPress site. This allows visitors to download specific images or files directly from posts and pages.

## Overview

Media Download helps you:
- Add download buttons to media files
- Let visitors download specific images by ID or URL
- Customize button text and styling
- Include download links in posts, pages, or widgets

**Note**: This is for **adding download buttons on your site**—not for downloading media from the admin panel.

**Location**: Media Library Tools → Media Download

## Feature Breakdown

### 1. Download Button Shortcodes

Two shortcode types available:

#### Shortcode 1: Download by ID
- **What it does**: Creates download button for media by WordPress attachment ID
- **Use case**: Link to images in your media library
- **Shortcode**: `[tsmlt_download_button id='12345' text='Download' /]`
- **Parameters**:
  - `id`: WordPress attachment ID (required)
  - `text`: Button text (optional, default: "Download")
  - `class`: Custom CSS class (optional)

#### Shortcode 2: Download by URL
- **What it does**: Creates download button for any file URL
- **Use case**: Download files from external URLs
- **Shortcode**: `[tsmlt_download_button url='https://example.com/file.jpg' text='Download' /]`
- **Parameters**:
  - `url`: Full URL to file (required)
  - `text`: Button text (optional, default: "Download")
  - `class`: Custom CSS class (optional)

### 2. Button Customization

**Text Parameter**
- Set custom button text
- Example: "Download Now", "Get Image", "Save File"
- Default: "Download"

**Class Parameter**
- Add custom CSS classes for styling
- Example: `class='custom-btn large'`
- Apply your own CSS styling

---

## Step-by-Step Usage

### Add Download Button by Media ID

1. Go to **Media Library Tools → Media Download**
2. Find the **"Download By Id"** shortcode card
3. Copy the shortcode:
   ```
   [tsmlt_download_button id='11393' text='Download Now' class='my-custom-btn' /]
   ```
4. Edit your post or page
5. Paste the shortcode in the content
6. Replace `11393` with your media ID
7. Change `text` to your desired button text
8. Publish the post
9. Button appears and visitors can download

**Example**:
```
Here's our latest product brochure:
[tsmlt_download_button id='5432' text='Download Brochure' /]
```

### Add Download Button by URL

1. Go to **Media Library Tools → Media Download**
2. Find the **"Download By URL"** shortcode card
3. Copy the shortcode:
   ```
   [tsmlt_download_button url='https://example.com/image.jpg' text='Download' /]
   ```
4. Edit post or page
5. Paste shortcode in content
6. Replace URL with your file URL
7. Change text as needed
8. Publish
9. Download button ready for visitors

**Example**:
```
[tsmlt_download_button url='https://example.com/wp-content/uploads/2026/04/product.pdf' text='Get PDF' /]
```

### Copy Shortcode to Clipboard

1. Go to **Media Download**
2. Find the shortcode you want
3. Click **copy icon** (top right of code block)
4. Shortcode copied to clipboard
5. Paste where needed

### Get PHP Code Version

If you need to use PHP instead of shortcode:

1. Go to **Media Download**
2. Find the card with your shortcode
3. Copy the **PHP Code** section
4. Use in your theme template

**Example PHP**:
```php
<?php
  echo shortcode_exists('tsmlt_download_button')
    ? do_shortcode("[tsmlt_download_button id='11393' text='Download Now' /]")
    : '' ;
?>
```

---

## Finding Media ID

Need to find your media ID?

1. Go to **Media Library Tools → Media Table**
2. Find the file you want
3. Look for the **ID** column (or hover over image)
4. Copy the ID number
5. Use in shortcode: `id='12345'`

Alternative:
1. Go to **WordPress Media Library**
2. Click image
3. URL shows: `/post.php?post=12345`
4. ID is `12345`

---

## Customization Options

### Change Button Text

```
[tsmlt_download_button id='5432' text='Click to Download' /]
[tsmlt_download_button id='5432' text='Save File' /]
[tsmlt_download_button id='5432' text='Get Now' /]
```

### Add Custom Styling

```
[tsmlt_download_button id='5432' class='primary-btn' /]
[tsmlt_download_button id='5432' class='btn-large btn-red' /]
```

Then add CSS to your theme:
```css
.primary-btn {
    background-color: #007cba;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
}

.btn-large {
    font-size: 18px;
    padding: 15px 30px;
}

.btn-red {
    background-color: #dc3545;
}
```

---

## Common Use Cases

### Product Download

```
[tsmlt_download_button id='5432' text='Download Product Guide' /]
```

### Lead Magnet

```
Get our free guide:
[tsmlt_download_button id='7890' text='Download Free Guide (PDF)' /]
```

### Portfolio File

```
See my work:
[tsmlt_download_button url='https://example.com/portfolio.pdf' text='View Full Portfolio' /]
```

### Resource Library

```
Available Resources:
- [tsmlt_download_button id='111' text='Template 1' /]
- [tsmlt_download_button id='222' text='Template 2' /]
- [tsmlt_download_button id='333' text='Template 3' /]
```

---

## Important Notes

### ID vs URL

**Use ID when**:
- File is in WordPress media library
- You want WordPress to track the download
- File is attached to the post

**Use URL when**:
- File is on external server
- File is outside WordPress
- It's a direct file link

### File Formats Supported

Can download any file type:
- Images: JPG, PNG, GIF, SVG, WebP
- Documents: PDF, DOCX, XLSX, TXT
- Archives: ZIP, RAR, 7Z
- Media: MP3, MP4, WebM
- Custom files (if accessible)

### Security Notes

- Downloads respect file permissions
- External URLs download as-is
- No additional security checks
- Server must have access to file

### Button Styling

- Shortcode generates `<a>` tag
- Default styling: plain link
- Custom class: Apply your CSS
- Styled by theme CSS if available

---

## Troubleshooting

**Q: Button doesn't appear**
- A: Check:
  - Shortcode syntax correct (id or url required)
  - Media ID exists (check Media Table)
  - URL is accessible
  - Shortcode plugin active

**Q: Download doesn't work**
- A: Check:
  - File still exists (not deleted)
  - File permissions allow download
  - URL is correct and accessible
  - Hosting allows downloads

**Q: Button text not changing**
- A: Include `text=` parameter:
  ```
  [tsmlt_download_button id='5432' text='Your Text' /]
  ```

**Q: Styling not working**
- A: Check CSS syntax, class name matches, CSS loaded on page

**Q: External URL showing 404**
- A: Verify URL works in browser before using in shortcode


## Best Practices

1. **Test Links**: Verify downloads work after publishing
2. **Clear Text**: Use descriptive button text ("Download PDF", not just "Download")
3. **Accessible URLs**: Keep files in accessible locations
4. **File Management**: Don't delete media that shortcodes reference
5. **Mobile Friendly**: Test button appearance on mobile devices
6. **Alternative Text**: Provide context around buttons ("Right-click to save")

---

**Next**: Learn about other features in [Image Sizes](10-image-sizes.md)
