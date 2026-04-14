# CSV Export (Bulk Exporter)

The CSV Export tool is a dedicated menu for bulk exporting your entire media library to CSV format. This is different from the quick export in Media Table—it provides advanced column selection and export history.

**Location**: Media Library Tools → CSV Export (sidebar menu)
**Availability**: Pro feature only

## Overview

The Bulk CSV Exporter allows you to:
- Export all media at once with a single click
- Choose which columns/fields to include
- Select from all available metadata fields
- View and re-download previous exports
- Manage export history

**Ideal for**: Complete library backups, data analysis, migration to other systems

## Feature Breakdown

### 1. Export Interface

The export page has a clean interface with:

| Component | Purpose |
|-----------|---------|
| **Run Exporter Button** | Fetch all media and prepare for download |
| **Column Selection Modal** | Choose which fields to include |
| **Progress Bar** | Shows fetch progress (0-100%) |
| **Download Button** | Download prepared CSV file |
| **Export History** | List of previous exports with re-download |

### 2. Column Selection

When exporting, choose from available columns:

| Column | Contains |
|--------|----------|
| **ID** | WordPress attachment ID |
| **Slug** | URL-friendly filename slug |
| **URL** | Full URL to attachment |
| **Title** | Attachment title/filename |
| **Caption** | Image caption text |
| **Description** | Detailed description |
| **Alt Text** | Alternative text for accessibility |
| **Custom Meta** | Additional fields (ACF, etc.) |

**Typical selections**:
- Backup: ID, Title, Alt Text, Caption, Description
- Analysis: ID, Title, URL, Slug
- Detailed: All columns (full data dump)

### 3. Export Process

**Step 1: Start Export**
1. Click **"Run Exporter"** button
2. Column selection modal appears
3. Choose which columns to include

**Step 2: Fetch Media**
1. System fetches all media from library
2. Progress bar shows: "Fetching media... 45%"
3. All media collected into export batch

**Step 3: Download**
1. When complete, "Download CSV" button appears
2. Click to download the CSV file
3. File saved to Downloads folder

**Step 4: Export History**
1. Export listed in history below
2. Can re-download from history
3. Can delete from history

---

## Step-by-Step Usage

### Export All Media With Specific Columns

1. Go to **Media Library Tools → CSV Export**
2. Click **"Run Exporter"** button
3. **Column Selection Modal** appears with options:
    - ☑ ID
    - ☑ Slug
    - ☑ URL
    - ☑ Title
    - ☑ Caption
    - ☑ Description
    - ☑ Alt Text
    - ☐ Custom Meta (additional fields)
4. **Check/uncheck** columns you want
5. Click **"Apply Selection"** or similar confirm button
6. Export begins
7. **Progress bar** shows status:
   ```
   Fetching media…
   [████████░░░░░░░░░░] 45%
   ```
8. When done: "Export Complete"
9. Shows: "500 media files are ready to download"
10. Click **"Download CSV"** button
11. File downloads: `export-media-file-example.com.csv`

### Re-download Previous Export

1. Go to **CSV Export** page
2. Scroll to **Export History** section
3. Find the export you want in the list:
   ```
   export-media-file-example.com.csv
   500 rows · April 11, 2026 2:30 PM
   [Download] [Delete]
   ```
4. Click **Download** button
5. File re-downloads (if still in session storage)

### Clear Export History

1. Go to **CSV Export**
2. In Export History section, click **"Clear all"** button (top right)
3. Confirmation: "Clear all export history?"
4. Click confirm
5. All history cleared (files still on disk, just history removed)

### Create a Complete Library Backup

1. Go to **CSV Export**
2. Click **"Run Exporter"**
3. **Select ALL columns**:
    - ☑ ID
    - ☑ Slug
    - ☑ URL
    - ☑ Title
    - ☑ Caption
    - ☑ Description
    - ☑ Alt Text
    - ☑ Custom Meta
4. Click confirm
5. Wait for export to complete
6. Click **"Download CSV"**
7. Save file with date label: "media-backup-2026-04-11.csv"
8. Keep in safe location (cloud, external drive, email)

---

## Column Options Explained

### Standard Columns

**ID**
- WordPress attachment/post ID
- Use for: Identifying specific attachments, matching across exports
- Format: Integer (e.g., 12345)

**Slug**
- URL-friendly filename
- Use for: Understanding filename structure
- Format: Text with hyphens (e.g., "product-photo-2024")

**URL**
- Full URL to the attachment
- Use for: Finding where files are hosted
- Format: Full URL (e.g., "https://example.com/wp-content/uploads/2024/04/photo.jpg")

**Title**
- Attachment title (usually filename)
- Use for: Identifying images
- Format: Text

**Caption**
- Image caption
- Use for: Restore captions, analyze descriptions
- Format: Text (can be multi-line)

**Description**
- Detailed description
- Use for: Full metadata backup
- Format: Text (can be lengthy)

**Alt Text**
- Alternative text for accessibility
- Use for: Analyze SEO metadata, restore alt text
- Format: Text

**Custom Meta**
- Additional fields from plugins (ACF, CMB2, etc.)
- Use for: Backup all custom data
- Format: JSON or serialized (depending on field)

### Which Columns to Select?

**Minimal (2 columns)**
```
ID, Title
```
- Smallest file size
- Identify images only

**Standard (5 columns)** — Recommended
```
ID, Title, Alt Text, Caption, Description
```
- Complete metadata
- Good file size
- Essential for most use cases

**Complete (8 columns)**
```
ID, Slug, URL, Title, Caption, Description, Alt Text, Custom Meta
```
- Everything included
- Largest file size
- For complete data dump

---

## Important Notes

### Session Storage Limitation

- Re-download available **only during session**
- If you close browser, re-download may not work
- Solution: Download immediately after export
- Recommendation: Keep multiple dated backups

### File Size

Large exports (5000+ items):
- Export process may take 2-5 minutes
- CSV file size can be 10-50+ MB
- May take time to download
- Consider breaking into date ranges if too large

### All Media vs Current Filter

**Important**: CSV Export exports **ALL media in library**, not filtered
- All images, all documents, all attachments
- All dates, all statuses
- No filtering available (unlike Media Table export)

If you need filtered export: Use [Media Table](02-media-table.md) quick export instead

### Custom Meta Fields

Custom meta from plugins:
- ACF (Advanced Custom Fields)
- CMB2 (Custom Meta Box)
- WooCommerce product data
- Other plugin custom fields

Including custom meta increases file size but captures all data.

---

## Common Workflows

### Complete System Backup

Before major WordPress update:

1. Go to **CSV Export**
2. Click **"Run Exporter"**
3. Select **all columns**
4. Download CSV
5. Save with label: "pre-update-backup-2026-04-11.csv"
6. Store in:
    - Cloud storage (Google Drive, Dropbox)
    - Email (to yourself)
    - External hard drive
    - Multiple locations

### Data Migration to New Site

**Source site**:
1. Export **ID, Title, URL, Caption, Description, Alt Text**
2. Save file
3. Rename files (keep original names)
4. Upload to new site's `/uploads/` folder

**Destination site**:
1. Upload media files to server
2. Use [CSV Import](06-csv-import.md) to restore metadata
3. All images and data restored

### Analysis - Find Large Library Trends

1. Export **ID, URL, Title** (minimal)
2. Open in Excel/Sheets
3. Count total rows (library size)
4. Analyze filename patterns
5. Identify organization issues
6. Plan cleanup strategy

### Documentation - Archive Campaign Media

1. Export specific campaign dates worth:
    - **ID, Title, Description, URL**
2. Create PDF report from CSV
3. Archive with campaign docs
4. Reference for future similar campaigns

---

## Pro Features (This is Pro-Only)

⭐ **CSV Export is a Pro exclusive feature**

Free version has:
- Quick export in Media Table (selected items only)
- Export only current page/selection
- Limited to displayed items

Pro version adds:
- ✅ Dedicated CSV Export menu
- ✅ Export entire library at once
- ✅ Column selection interface
- ✅ Export history
- ✅ Re-download capability

[Upgrade to Pro](https://example.com/pro) to use bulk CSV Export

---

## Troubleshooting

**Q: Export button doesn't work**
- A: Pro feature requires active Pro license. Check that Pro plugin is activated.

**Q: Export takes very long**
- A: Large libraries (5000+ items) take longer. 2-5 minutes normal. Leave page open.

**Q: Can't re-download export**
- A: Session storage cleared (browser closed/cache cleared). Download immediately after export next time. Or re-run exporter.

**Q: File is huge (100+ MB)**
- A: Included all columns for large library. Select fewer columns next time. Or export in sections.

**Q: Exported file is empty or truncated**
- A: Browser download interrupted. Try again. Use different browser if persists. Or use [Media Table](02-media-table.md) quick export instead.

**Q: Custom meta not showing in CSV**
- A: Custom meta columns only appear if they exist. Some fields may not be included. Check column selection.

**Q: Can't open CSV in Excel**
- A: File large or Excel memory issue. Try:
    - Open in Google Sheets instead
    - Use larger machine
    - Open only first 1000 rows
    - Use text editor to view

---

## Difference: CSV Export vs Media Table Export

### CSV Export (This Feature) — Pro

| Feature | CSV Export |
|---------|-----------|
| **Access** | Dedicated menu (Pro) |
| **Scope** | Entire library always |
| **Column Selection** | Full interface with all options |
| **History** | Saved with re-download |
| **Batch Size** | All at once |
| **Typical Use** | Complete backups, migration |

### Media Table Export — Free

| Feature | Media Table |
|---------|-------------|
| **Access** | Quick button on Media Table |
| **Scope** | Current page or selected items |
| **Column Selection** | Limited to visible columns |
| **History** | Not tracked |
| **Batch Size** | Current page or selection |
| **Typical Use** | Quick spot export, selected items |

**Recommendation**: Use CSV Export for complete backups, Media Table for quick selections.

---

## Best Practices

1. **Regular Backups**: Monthly or before major changes
2. **Multiple Copies**: Store in different locations
3. **Label Files**: Include date in filename (media-backup-2026-04-11.csv)
4. **Select Appropriate Columns**: Don't export unnecessary data
5. **Test Restore**: Verify export can be re-imported if needed
6. **Document Contents**: Note what's included in each export
7. **Archive Old Exports**: Keep historical backups for reference

---

**Next**: Learn about [CSV Import](06-csv-import.md) to restore data from backups
