# Media Download

The Media Download feature allows you to download media files from your WordPress site. It provides tools for bulk media exports, creating downloadable archives, and organizing media for backup or distribution.

## Overview

Media Download helps you:
- **Backup Media**: Safely download your entire media library
- **Bulk Export**: Download multiple files at once
- **Archives**: Create ZIP files for easy sharing
- **Transfer**: Move media to another site
- **Distribution**: Share media with team members

**Location**: Media Library Tools → Media Download

## Why Download Media?

### Benefits

- **Backup**: Independent backup of files (not database)
- **Migration**: Easily move media to new server
- **Archiving**: Keep historical copies
- **Sharing**: Send multiple files to clients
- **Compliance**: Maintain media records for audits

### Common Use Cases

1. **Backup Before Major Updates**: Download media before theme/plugin update
2. **Server Migration**: Get all files for new server
3. **Site Transfer**: Move media to new domain
4. **Content Archive**: Archive old campaign media
5. **Team Collaboration**: Share media pack with designers

---

## Feature Breakdown

### 1. Download Methods

#### Direct File Download
- Download individual or selected files
- Single click per file
- Browser download
- Best for: One or few files

#### Bulk ZIP Archive
- Multiple files in one ZIP
- Compressed for smaller size
- Single download
- Best for: Many files (10+)

#### CSV Export
- Metadata export (separate from files)
- File information in spreadsheet
- Used with [CSV Import](csv-import.md)
- Best for: Documenting/organizing

### 2. Download Organization

Downloaded files can be organized by:

| Organization | Structure |
|--------------|-----------|
| Flat | All files in root (thousands in one folder) |
| By Date | 2024/04/, 2024/03/, etc. |
| By Type | images/, documents/, videos/ |
| By Post | post-123/, post-456/ |
| Custom | As configured |

**Note**: Organization depends on plugin settings and how you download.

### 3. Compression & Size

**ZIP Archive Compression**:
- Reduces file size by 10-30%
- JPEG already compressed (saves less)
- PNG lossless (saves more)
- Large archives: Split into multiple files

**Example**:
- Original media: 2 GB
- ZIP compressed: 1.7 GB (15% reduction)
- Download time: Depends on server speed

---

## Step-by-Step Usage

### Download Individual File

1. Go to **Media Library Tools → Media Table**
2. Find the image you want to download
3. Click the **"Download"** button (down arrow icon)
4. File downloads to your computer's Downloads folder
5. Original filename preserved

**Example**:
```
Click Download on: sunset.jpg
→ Downloads to: ~/Downloads/sunset.jpg
→ Size: Original image size
```

### Download Multiple Files (Bulk)

**Method 1: From Media Table**
1. Go to **Media Table**
2. Select multiple images (checkboxes)
3. Click **"Export CSV"** or **"Download Selection"** if available
4. Choose format:
   - CSV (metadata only)
   - ZIP (files + metadata)
5. Download starts

**Method 2: From Media Download Page**
1. Go to **Media Library Tools → Media Download**
2. Select images to download:
   - By date range
   - By file type
   - By usage status
   - All images
3. Click **"Download as ZIP"**
4. ZIP created with all selected files
5. Single download starts

### Create Backup Archive

To backup all media files:

1. Go to **Media Download**
2. Select **"All Images"** or **"All Media"**
3. Choose **"Create ZIP Archive"**
4. Optionally include:
   - Metadata (CSV file in ZIP)
   - Subfolder organization
   - Compression settings
5. Click **"Create Archive"**
6. Progress bar shows:
   - Files being processed
   - Archive size
   - Compression ratio
7. Download starts when ready

**Archive Contents**:
```
media-backup-2024-04-11.zip
├── images/
│   ├── 2024/
│   │   ├── 04/
│   │   │   ├── sunset.jpg
│   │   │   ├── ocean.jpg
│   │   │   └── ...
│   │   └── 03/
│   │       └── ...
├── media-metadata.csv
└── README.txt
```

### Download by Date Range

To backup only recent media:

1. Go to **Media Download**
2. Select **"By Date Range"**
3. Choose **"From"** and **"To"** dates
4. Click **"Apply Filter"**
5. Select all filtered items
6. Click **"Download as ZIP"**
7. Only files in date range included

**Example**:
```
Date Range: 2024-04-01 to 2024-04-11
Downloads: All media uploaded in April 2024
Excludes: Older images from March/earlier
```

### Download by File Type

To backup specific file types:

1. Go to **Media Download**
2. Select **"By File Type"**
3. Choose type(s):
   - Images (JPEG, PNG, GIF, WebP, SVG)
   - Documents (PDF, DOCX)
   - Video (MP4, WebM)
   - Audio (MP3, WAV)
4. Click **"Download"**
5. Only selected types included

### Download Used/Unused Images

To backup only used or unused media:

1. Run [Used Where](used-where.md) scan first
2. Go to **Media Download**
3. Select **"By Usage"**
4. Choose:
   - Used images only
   - Unused images only
5. Click **"Download as ZIP"**

**Use Cases**:
- Backup active images (before theme change)
- Archive unused (before deletion)
- Clean up verification (download unused to review)

---

## Common Workflows

### Complete Media Backup

Scenario: Prepare for major WordPress update

1. Go to **Media Download**
2. Select **"All Media"**
3. Click **"Create Backup Archive"**
4. Include **"Metadata CSV"**: Yes
5. Choose **"Full Compression"**
6. Download starts
7. Save backup file in multiple locations:
   - External hard drive
   - Cloud storage (Dropbox, Google Drive)
   - Backup service

### Migrate to New Server

Scenario: Moving WordPress to new hosting

1. **Old Server**:
   - Go to **Media Download**
   - Create archive of all media
   - Download to computer
2. **New Server**:
   - Upload media files to `/wp-content/uploads/`
   - Import metadata via [CSV Import](csv-import.md)
3. **Verify**:
   - Check images display
   - Run [Regenerate Thumbnails](regenerate-thumbnails.md)

### Archive Campaign Media

Scenario: Quarterly campaign cleanup

1. Go to **Media Download**
2. **By Date Range**: January-March 2024
3. **By Usage**: Used images only (active campaign)
4. Download as ZIP
5. Save with label: "Q1-2024-Campaign-Media.zip"
6. Archive in cloud storage
7. Delete from WordPress after archiving

### Share Media with Client

Scenario: Designer needs product images

1. Go to **Media Download**
2. **By File Type**: Images
3. **By Usage**: Used images (active product images)
4. Download ZIP
5. Email to designer
6. Designer has full product image set

---

## Important Notes

### Download Limitations

**File Size**:
- Individual files: Limited by server (usually 100+ MB ok)
- ZIP archives: May be very large (1-5 GB)
- Large downloads: May timeout on slow connections

**Browser Considerations**:
- Downloads over 2 GB may fail in browser
- Consider downloading in sections
- Use FTP for very large transfers

### Archive Contents & Organization

**Standard Archive Structure**:
```
images/
├── 2024/
│   ├── 04/
│   │   └── sunset.jpg
│   └── 03/
│       └── photo.jpg
├── 2023/
│   └── 12/
└── media-metadata.csv
```

**Metadata CSV Included**:
- Filename, Alt Text, Caption, Description
- File size, dimensions, upload date
- Can reimport with [CSV Import](csv-import.md)

### Compression Details

**ZIP Compression Effect**:
- JPEG (already compressed): 0-5% reduction
- PNG (uncompressed): 20-40% reduction
- Mixed media: 10-20% average reduction
- Text files: 50%+ reduction

---

## Pro Features

**Upgrade to Pro for**:
- Scheduled automatic backups
- Cloud storage integration (Google Drive, Dropbox, S3)
- Incremental backups (only new files)
- Backup encryption
- Backup size limit enforcement
- Automatic retention policies
- One-click restore from backup

---

## Safety Considerations

### Before Large Downloads

1. **Check Disk Space**: Ensure you have room
2. **Test Connection**: Try downloading small file first
3. **Backup Metadata**: Export CSV separately
4. **Verify Download**: Check file integrity after download

### After Download

1. **Verify Files**: Spot-check downloaded images open
2. **Test Extraction**: Extract ZIP and verify files
3. **Keep Multiple Copies**: Store in multiple locations
4. **Document Backup**: Note what's included, when backed up

### Archive Security

- **Unencrypted Archives**: Files visible to anyone with ZIP
- **Sensitive Media**: Consider additional encryption
- **Cloud Storage**: Use secure storage (encrypted cloud drives)
- **Access Control**: Restrict who can download

---

## Troubleshooting

**Q: Download fails or times out**
- A: Archive may be too large. Try:
  - Download fewer files (smaller archive)
  - Download by date range instead of all
  - Use FTP instead of browser download
  - Contact hosting provider about timeout

**Q: ZIP archive is larger than expected**
- A: Uncompressed metadata or files not actually compressed. Check:
  - Files included in archive
  - Compression actually enabled
  - Files are JPEG (already compressed, less benefit)

**Q: Can't extract downloaded ZIP**
- A: Corrupted download or incomplete. Try:
  - Re-download the archive
  - Use different extraction tool (WinRAR, 7-Zip)
  - Verify file size matches announced size

**Q: Missing files from archive**
- A: Archive generation may have errors. Check:
  - Download log for errors
  - Re-create archive
  - Try excluding file types causing issues

**Q: Download starts but stops mid-way**
- A: Connection interrupted. Try:
  - Download fewer files (smaller archive)
  - Use wired internet (more stable)
  - Use FTP client instead of browser
  - Try during off-peak hours

---

## Best Practices

1. **Regular Backups**: Monthly at minimum
2. **Multiple Copies**: Keep backups in 2+ locations
3. **Test Extraction**: Verify zips extract correctly
4. **Document Backups**: Note dates and contents
5. **Archive Old Media**: Download before bulk deletion
6. **Include Metadata**: Always include CSV in backups

---

**Documentation Complete**: You now have comprehensive coverage of all Media Library Tools features!

For additional help, see [Getting Help](index.md#getting-help)
