# CSV Export

The CSV Export feature lets you download your media library metadata as a spreadsheet file for backup, analysis, or bulk editing in Excel, Google Sheets, or other spreadsheet applications.

## Overview

Export CSV allows you to:
- Back up media metadata
- Edit metadata in bulk using spreadsheets
- Analyze your media library
- Migrate data to other systems
- Share media information with team members

**Location**: Media Library Tools → Media Table → "Export CSV" button

## Feature Breakdown

### Export Options

When you click Export CSV, you can choose which columns to include:

| Column | Contains |
|--------|----------|
| **Filename** | Original/current filename with extension |
| **Alt Text** | Accessibility/SEO alt text |
| **Caption** | Image caption |
| **Description** | Detailed description text |
| **Date** | Upload date |
| **ID** | WordPress attachment ID |
| **MIME Type** | File type (image/jpeg, image/png, etc.) |
| **Dimensions** | Image width x height in pixels |
| **File Size** | File size in bytes/MB |

Select only the columns you need to keep file size manageable.

### CSV Format

The exported file is:
- **Format**: Comma-separated values (.csv)
- **Encoding**: UTF-8
- **Header Row**: Column names on first row
- **Standard**: Compatible with Excel, Google Sheets, Numbers, etc.
- **Structure**:
  ```
  Filename,Alt Text,Caption,Description
  sunset.jpg,Beautiful sunset,"Beach at dusk","Golden hour photo session"
  ocean-wave.jpg,Ocean wave,"Wave photography","Capturing wave motion"
  ```

### File Size Considerations

- Small library (< 500 images): Typically < 1 MB
- Medium library (500-2000): 1-10 MB
- Large library (2000+): 10-100 MB+

Large exports may take time to generate. Patience required for very large libraries.

---

## Step-by-Step Usage

### Export All Media Metadata

1. Go to **Media Library Tools → Media Table**
2. Click the **"Export CSV"** button (top toolbar)
3. A dialog appears asking which columns to include
4. **Select desired columns** (check boxes):
   - For basic backup: Filename, Alt Text, Caption, Description
   - For detailed info: Include ID, Date, Dimensions, File Size
5. Click **"Download CSV"** button
6. File downloads as "media-export-[date].csv"
7. Save to your computer

### Export Specific Images Only

1. In **Media Table**, select specific images (checkboxes)
2. Click **"Export CSV"** button
3. Choose columns
4. Click **"Download CSV"**
5. Only selected images are exported
6. Useful for exporting subset (e.g., products, blog images)

### Open CSV in Excel

1. After downloading, open **Microsoft Excel**
2. Go to **File → Open**
3. Select the CSV file
4. A dialog appears asking about import settings
5. Confirm:
   - **Delimiter**: Comma (should auto-detect)
   - **Encoding**: UTF-8
6. Click **OK**
7. Spreadsheet displays with columns

### Open CSV in Google Sheets

1. Go to **Google Drive** (drive.google.com)
2. Click **New → File upload** or **Google Sheets → From file**
3. Select downloaded CSV file
4. Upload completes
5. File opens in Google Sheets
6. Edit directly in browser

### Open CSV in Apple Numbers

1. Find downloaded CSV file
2. Double-click to open
3. Numbers opens automatically
4. Edit as needed
5. Save back as CSV or Numbers format

---

## Common Workflows

### Backup Your Media Library

1. Go to **Media Table**
2. Click **"Export CSV"**
3. Select: Filename, Alt Text, Caption, Description, ID, Date
4. Click **"Download CSV"**
5. Save file with date (e.g., "media-backup-2024-04-11.csv")
6. Store backup copies in multiple locations

**Frequency**: Monthly or after major uploads

### Bulk Update Alt Text

1. **Export CSV** → Select Filename, Alt Text
2. Open in Excel/Sheets
3. Use formulas to generate alt text:
   - `=CONCATENATE("Product - ", A2)`
   - Creates: "Product - sunset.jpg"
4. Paste new alt text into Alt Text column
5. Save as CSV
6. [Import back](csv-import.md) to update WordPress

### Analyze Image Usage

1. Export with: Filename, Date, File Size, Dimensions
2. Open in Excel/Sheets
3. Sort by Date to find old/new images
4. Sort by File Size to find large files
5. Filter by Dimensions to find low-res images
6. Identify images for optimization/deletion

### Share Media Information

1. Export desired columns
2. Save as CSV
3. Send to team members (read-only)
4. They can open in their spreadsheet app
5. Useful for: Designer references, content team info

### Migrate to Another WordPress Site

1. **Source site**: Export CSV with all columns
2. **Rename files** on new server (if needed)
3. **Upload files** to new WordPress media folder
4. **Destination site**: [Import CSV](csv-import.md)
5. Data restored on new site

---

## Important Notes

### What's Included

✓ File metadata (name, alt text, caption, description)
✓ File info (ID, type, size, dimensions)
✓ Upload date
✗ File actual data (the physical file)
✗ File location/path

**Note**: CSV contains metadata only, not the files themselves.

### What's NOT Included

- Actual image files
- Post associations (which post image is used in)
- Video data
- Large text blocks (descriptions truncated if very long)
- Custom metadata from other plugins

To migrate full data, also copy physical files to new server.

### Character Encoding

- **Default**: UTF-8 (handles special characters)
- **Compatibility**: Works with all modern spreadsheet apps
- **Legacy**: If opening in very old Excel, may need re-encode

### File Size Limits

- Most email services: < 25 MB max attachment
- Most servers: < 100 MB export time limit
- Solution for large exports: Export in batches or request increase

---

## Spreadsheet Tips

### Clean CSV Format

When editing in spreadsheet:

1. **Don't modify the first row** (headers)
2. **Keep filename column exact** (for re-import matching)
3. **Use simple text only** (no formatting)
4. **Save as CSV format** before re-importing

### Useful Formulas (Excel/Sheets)

**Generate text with formula**:
```
=CONCATENATE("Product: ", A2)
=A2 & " - " & B2
=UPPER(A2)  // Convert to uppercase
=PROPER(A2) // Title Case
```

**Find duplicates**:
```
=COUNTIF($A$2:$A$1000,A2)
// Shows "2" if filename appears twice
```

**Conditional formatting**:
- Highlight cells by criteria
- Find empty cells
- Identify duplicates

### Export Best Practices

1. **Include ID column** for re-import matching
2. **Make backup copies** before editing
3. **Don't delete rows** (removes images on import)
4. **Keep filename consistent** (key identifier)
5. **Save often** while editing

---

## Troubleshooting

**Q: CSV file won't open in Excel**
- A: Try right-click → "Open with" → select Excel. Or drag file into open Excel window.

**Q: Special characters showing as ?,  Ã, etc.**
- A: Encoding issue. Excel: File → Info → Inspect File → Check encoding is UTF-8. Re-save as UTF-8 CSV.

**Q: File is huge and takes forever to load**
- A: Normal for 5000+ images. Export in batches (select 500 at a time). Or use CSV import tool instead of spreadsheet.

**Q: Commas in text are breaking the CSV format**
- A: Excel automatically handles comma-quoted fields. If manually editing, wrap fields with commas in quotes: `"Field with, comma"`

**Q: CSV shows strange characters instead of text**
- A: Likely encoding issue. Save file as "CSV UTF-8" not just "CSV" in your spreadsheet app.

---

## Pro Features

Pro version adds:
- Automatic scheduled exports
- Export to cloud storage (Google Drive, Dropbox)
- Export filtered data (by date, size, type)
- Exclude certain columns automatically
- Email export reports

---

**Next**: Learn about [CSV Import](csv-import.md) to update media from spreadsheets
