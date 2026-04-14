# CSV Import

The CSV Import feature allows you to update media metadata in bulk by uploading a CSV file. Perfect for batch updates from spreadsheets or migrating data from other systems.

## Overview

CSV Import lets you:
- Update metadata for multiple images at once
- Import data from spreadsheets (Excel, Google Sheets, etc.)
- Migrate data from other WordPress sites
- Create attachments from imported data
- Match images by filename or ID

**Location**: Media Library Tools → CSV Import

## Feature Breakdown

### Import Modes

#### 1. Update Existing Media

- **What it does**: Matches files by filename or ID and updates their metadata
- **Use case**: Bulk updating alt text, captions, descriptions for existing images
- **Safety**: Only updates selected fields
- **Matching**: By filename (exact match) or ID

**Example**:
- You have "sunset.jpg" in WordPress
- CSV row: sunset.jpg, "Beautiful sunset", "Beach at dusk"
- Result: Alt text and caption updated

#### 2. Create New Attachments

- **What it does**: Creates new attachment entries from CSV data
- **Requires**: File already uploaded to media folder on server
- **Use case**: Restoring attachments after server migration, bulk registering images
- **Matching**: By filename in /uploads/ folder

---

### CSV File Requirements

Your CSV file must follow this structure:

**Required**:
- **First row**: Column headers (Filename, Alt Text, Caption, Description, etc.)
- **Data rows**: One per image/attachment

**Column Names** (must match exactly):
- `Filename` — Image filename (required for matching)
- `Alt Text` — Alt text field
- `Caption` — Caption field
- `Description` — Description field
- `ID` — WordPress attachment ID (optional, alternative to Filename)

**Example CSV**:
```
Filename,Alt Text,Caption,Description
sunset.jpg,Beautiful sunset,"Beach at dusk","Golden hour photography"
ocean.jpg,Ocean waves,"Wave motion","Capturing Pacific waves"
desert.jpg,Desert landscape,"Sand dunes","Utah desert scenery"
```

### File Format

- **Type**: CSV (.csv) file only
- **Encoding**: UTF-8 (standard for modern tools)
- **Delimiter**: Comma (,)
- **Text qualifiers**: Double quotes (") for fields with commas
- **Max file size**: 10 MB (check plugin settings for limit)

---

## Step-by-Step Usage

### Prepare Your CSV File

1. **Open spreadsheet** (Excel, Google Sheets, etc.)
2. **Create columns**:
   - Column A: `Filename` (or `ID`)
   - Column B: `Alt Text`
   - Column C: `Caption`
   - Column D: `Description`
   - (Add more columns as needed)
3. **Add data rows**:
   - Row 1: Headers
   - Row 2+: Image data
4. **Save as CSV**:
   - File → Save As
   - Format: CSV (Comma-separated values)
   - Encoding: UTF-8

### Import CSV to Update Metadata

1. Go to **Media Library Tools → CSV Import**
2. Click **"Choose File"** button
3. Select your prepared CSV file
4. Preview appears showing:
   - Number of rows
   - Column mapping
5. Select **"Update Existing Media"** mode
6. Choose matching method:
   - **By Filename**: Match "sunset.jpg" to existing image
   - **By ID**: Match ID numbers
7. Click **"Preview"** to verify matches
8. Verify mapped columns (Filename → Filename column, Alt Text → Alt Text column)
9. Click **"Import"** to start
10. Progress bar shows import status
11. Summary shows: "Updated 45 images, 3 skipped, 0 errors"

### Import to Create New Attachments

1. Go to **CSV Import**
2. Upload CSV file
3. Ensure image files exist in `/wp-content/uploads/` on server
4. Select **"Create New Attachments"** mode
5. Select **"By Filename"** matching
6. Click **"Preview"**
7. Verify filenames match actual files in uploads folder
8. Click **"Import"**
9. New attachment entries created in WordPress media library

### Handle Import Errors

If import shows errors:

1. **Error**: "File not found"
   - Cause: Filename doesn't exist in WordPress/uploads
   - Fix: Upload the file first, then re-import

2. **Error**: "Invalid column mapping"
   - Cause: CSV headers don't match expected names
   - Fix: Rename columns to match (Filename, Alt Text, etc.)

3. **Error**: "Encoding issue - special characters invalid"
   - Cause: File not saved as UTF-8
   - Fix: Save CSV as UTF-8 encoding again

4. **Warning**: "3 rows skipped"
   - Cause: Filenames not found or ID doesn't match
   - Fix: Check filenames match exactly (case-sensitive on Linux servers)

---

## Common Workflows

### Bulk Update Alt Text from Excel

1. **Export CSV** from Media Table
2. Open in **Excel**
3. Add column: "Alt Text"
4. Use formula to generate:
   ```
   =CONCATENATE("Product - ", A2)
   ```
5. Save as CSV
6. **Import**: Update Existing Media → By Filename
7. Alt text updated for all images

### Migrate Images from Old WordPress Site

**Old site**:
1. Export CSV with: Filename, Alt Text, Caption, Description
2. Note file paths

**New site**:
1. Upload image files to `/wp-content/uploads/` folder
2. Keep exact filenames
3. Use CSV Import → Create New Attachments
4. Import CSV from old site
5. Metadata restored on new site

### Restore Attachments After Server Migration

1. You have images in `/uploads/` but WordPress lost attachment data
2. Export CSV from Excel with: Filename, Alt Text, Caption, Description
3. Create rows for each image file:
   ```
   Filename,Alt Text,Caption,Description
   image1.jpg,,,"Old photo"
   image2.jpg,Photo 2,,"Family picture"
   ```
4. Import → Create New Attachments
5. Attachments recreated with metadata restored

### Bulk Edit Descriptions for SEO

1. Export current CSV
2. Open in Google Sheets
3. Edit Description column for all rows
4. Add SEO-friendly descriptions
5. Download as CSV
6. Import → Update Existing Media
7. All descriptions updated

---

## Important Notes

### Matching Logic

**By Filename**:
- Matches exactly: "sunset.jpg" must match "sunset.jpg"
- Case-sensitive on Linux servers
- Extension required: ".jpg" not just "jpg"
- One match per filename

**By ID**:
- Requires `ID` column in CSV
- More reliable than filename matching
- Works even if filename changed
- Must be valid WordPress attachment ID

### What Gets Updated

✓ Alt Text
✓ Caption
✓ Description
✗ Filename (use Media Rename for this)
✗ Upload date
✗ File size/dimensions

To rename files, use [Media Rename](media-rename.md).

### Safety Considerations

- **Non-destructive**: Only updates fields you specify
- **No deletions**: Existing data not removed
- **Reversible**: Export CSV before import as backup
- **Preview**: Always preview before importing

### Large Imports

For very large files (1000+ rows):
- Process may take several minutes
- Don't close browser during import
- Progress bar shows status
- Browser tab shows when complete

---

## Troubleshooting

**Q: Import says "File not found" for images I can see**
- A: Filenames must match exactly. Check:
  - Extension exact (.jpg vs .JPG)
  - Spaces, hyphens match
  - No special characters
  - Case-sensitive on Linux

**Q: Error: "Invalid CSV format"**
- A: Check CSV requirements:
  - Save as UTF-8 encoding
  - First row must be headers
  - Column names: Filename, Alt Text, Caption, Description
  - Commas in text must be quoted

**Q: Some rows imported, others skipped**
- A: Likely filename mismatches for skipped rows. Check:
  - Filename spelling
  - Extension correct
  - File actually uploaded to media
  - No extra spaces

**Q: Special characters show as garbage after import**
- A: Encoding issue. Re-save CSV as UTF-8:
  - Excel: Save As → CSV UTF-8 (not just CSV)
  - Google Sheets: Download → CSV

**Q: Too many errors, want to stop import**
- A: You can't stop mid-import, but you can:
  - Wait for import to complete
  - Use [CSV Export](csv-export.md) to check current state
  - Fix errors and re-import corrected rows

---

## Pro Features

Pro version adds:
- Automatic CSV imports on schedule
- Import from cloud storage (Google Drive, Dropbox)
- Advanced mapping (custom field mapping)
- Bulk create with file upload
- Undo import (restore previous state)
- Import history and logs

---

## CSV Template

Use this template to prepare your CSV file:

```csv
Filename,Alt Text,Caption,Description
image1.jpg,Alt text here,Caption text,Full description
image2.jpg,Another alt,Another caption,Description text
image3.jpg,,,
```

Headers: `Filename`, `Alt Text`, `Caption`, `Description`

---

**Next**: Learn about [Rubbish Files](rubbish-files.md) to clean up orphaned media
