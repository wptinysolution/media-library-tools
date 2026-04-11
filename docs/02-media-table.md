# Media Table

The Media Table is the central hub for viewing and managing all your WordPress media files. It extends the native WordPress media library with powerful search, filtering, and bulk editing capabilities.

## Overview

The Media Table displays all uploaded media files with additional columns and tools for efficient management. You can search, filter, bulk edit, and organize your media library directly from this interface.

**Location**: Media Library Tools → Media Table (default view)

## Feature Breakdown

### Main Table View

The Media Table displays all your media with these columns:

| Column | Description |
|--------|-------------|
| **Thumbnail** | Preview image/icon for the file |
| **File Name** | The actual filename with extension |
| **Alt Text** | Alternative text for accessibility |
| **Caption** | Image caption text |
| **Description** | Detailed description |
| **Date** | Upload date |
| **Actions** | Quick edit, download, delete buttons |

### Search & Filter

**Search Bar** (top right)
- Search by filename, alt text, caption, or description
- Real-time results as you type
- Case-insensitive matching

**File Type Filter** (if applicable)
- Filter by image type (JPEG, PNG, SVG, etc.)
- View only images, or all file types

**Per-Page Display**
- Choose how many items per page: 10, 25, 50, 100
- Pagination controls at bottom

---

## Step-by-Step Usage

### Search for a Specific Image

1. Click on the **Media Table** menu item
2. In the search field (top right), type the image filename or description
3. Results update in real-time
4. Click on an image row to view details or edit

**Tip**: Search works across filename, alt text, caption, and description fields.

### Edit a Single Media File

1. Locate the media file in the table
2. Click the image row or the **Edit** icon
3. A modal opens with editable fields:
   - **Title**: Filename
   - **Alt Text**: Accessibility text
   - **Caption**: Short description
   - **Description**: Detailed text
4. Make changes
5. Click **Save** button
6. Changes are saved immediately

### Bulk Edit Multiple Files

1. Select multiple images using checkboxes (top left of each row)
2. Selected count appears in the header
3. Toolbar appears with **Bulk Edit** button
4. Click **Bulk Edit**
5. **Bulk Edit Modal** opens with options:
   - **Add to Alt Text**: Append text to existing alt text
   - **Replace Alt Text**: Replace entire alt text
   - **Add to Caption**: Append to caption
   - **Replace Caption**: Replace entire caption
   - **Add to Description**: Append to description
   - **Replace Description**: Replace entire description
6. Select operation and enter text
7. Click **Apply** to save

**Example**:
- Select 10 product images
- Choose "Add to Alt Text"
- Enter "Product"
- Result: "existing alt text Product" for each image

### Download as CSV

1. Click **Export CSV** button (top toolbar)
2. Select which columns to include:
   - Filename
   - Alt text
   - Caption
   - Description
   - Other metadata
3. Click **Download**
4. CSV file downloads with your media metadata
5. Edit in Excel/Sheets and [re-import](csv-import.md)

### Delete Files

1. Select one or more images
2. Click **Delete** button in toolbar
3. Confirmation modal appears
4. Click **Confirm** to permanently delete
5. Files are removed from disk and database

---

## Settings Explanation

### Table Columns

The table is pre-configured with essential columns. If you need to customize which columns display:

**Note**: Column customization is handled through WordPress media library settings and filters. Core columns (Thumbnail, Filename, Actions) always show.

### Per-Page Options

Available options: **10, 25, 50, 100**

- Fewer items = faster page load, more pagination clicks
- More items = slower load, fewer clicks to see all
- Default: 10

**Recommendation**: Use 25-50 for most libraries (good balance)

### Search Scope

The search function searches across:
- Filename
- Alt text
- Caption
- Description
- File metadata

Note: Media ID is not searchable via text search.

---

## Controls & Options

### Top Toolbar

| Control | Function |
|---------|----------|
| **Checkbox (Select All)** | Select/deselect all items on current page |
| **Search Bar** | Find images by text (filename, metadata) |
| **Per Page Dropdown** | Choose items per page (10, 25, 50, 100) |
| **Export CSV** | Download media data as spreadsheet |
| **Filter Icon** | Filter by file type (if multiple types) |

### Per-Row Actions

| Action | Description |
|--------|-------------|
| **Edit** | Open edit modal for this file |
| **Download** | Download the file to your computer |
| **Delete** | Remove file and all references |
| **Checkbox** | Select for bulk operations |

### Bulk Operations (when items selected)

| Operation | Purpose |
|-----------|---------|
| **Bulk Edit** | Edit metadata for all selected items at once |
| **Delete Selected** | Delete all selected files |
| **Export Selection** | Export only selected files as CSV |

---

## Common Workflows

### Update Alt Text for Site Relaunch

1. Go to **Media Table**
2. Search for images to update (or select all on page)
3. Select relevant images
4. Click **Bulk Edit**
5. Choose "Replace Alt Text"
6. Enter new alt text format
7. Click **Apply**
8. Repeat for other pages if needed

### Backup Media Metadata

1. Click **Export CSV**
2. Select all columns
3. Click **Download**
4. Save the CSV file to your computer
5. Keep as backup before making bulk changes

### Find Images with Missing Alt Text

1. In **Media Table**, search for blank alt text
2. Select all results
3. **Bulk Edit** → Choose "Add to Alt Text"
4. Enter your default alt text
5. Click **Apply**

### Delete Unused Images After Backup

1. **Export CSV** first (backup)
2. Identify images to delete
3. Select them
4. Click **Delete Selected**
5. Confirm deletion

---

## Important Notes

### Bulk Edit Limitations

- **Add to**: Appends text after existing content
  - Existing: "Blue Sky" + Add "Photography" = "Blue Sky Photography"
- **Replace**: Replaces entire field
  - Existing: "Blue Sky" + Replace with "Nature" = "Nature"

### File Deletion

- **Permanent**: Deleted files cannot be recovered
- **Careful**: Ensure image isn't used in posts before deleting
- **Alternative**: Consider using [Used Where](used-where.md) to check image usage first

### CSV Export Format

- Comma-separated values (standard)
- Compatible with Excel, Google Sheets, Numbers
- Include a header row with column names
- Text fields with commas are quoted

---

## Pro Features

**Pro version adds**:
- Advanced rename strategies (rename by post title, SKU, alt text)
- Auto rename on upload
- AI content generation buttons directly in media table
- Integration with [Duplicate Merger](duplicates.md)
- Advanced filtering options
- More bulk edit operations

Upgrade to Media Library Tools Pro for these advanced features.

---

## Troubleshooting

**Q: Search isn't finding an image I know exists**
- A: Search is exact-match by default. Try searching by different keywords or check the image is uploaded correctly.

**Q: Bulk edit applied to wrong files**
- A: Verify selected images before clicking Apply. Only selected (checked) items are affected.

**Q: Export CSV seems empty**
- A: Some browsers default to opening CSV instead of downloading. Try right-click → "Save Link As" on the export button.

**Q: Can't delete an image**
- A: The image might be in use. Check [Used Where](used-where.md) to see where it's used, then remove references before deleting.

---

**Next**: Learn about [Media Rename](media-rename.md) for advanced file naming
