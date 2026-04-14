# Rubbish Files

The Rubbish Files tool finds and manages orphaned or "unlisted" media files — images that exist on your server but aren't registered in WordPress as attachments.

## Overview

Over time, WordPress media folders accumulate files that:
- Were deleted from the media library but files remained on server
- Were manually uploaded outside WordPress
- Are leftover from theme/plugin trials
- Are corrupted or abandoned

Rubbish Files helps you identify and clean them up safely.

**Location**: Media Library Tools → Rubbish Files

## Why Use Rubbish Files?

### Benefits

- **Reclaim Storage**: Remove unused files and free up disk space
- **Security**: Delete potentially malicious files left behind
- **Cleanliness**: Keep media folder organized and clean
- **Performance**: Reduce disk I/O and server load
- **Organization**: Identify stray files and understand what's where

### Storage Impact Example

- Average site might have 100-500 MB of rubbish files
- Can be 10-50% of total media folder size
- Cleanup can save significant storage costs

---

## Feature Breakdown

### 1. Scan for Rubbish Files

The scanner finds files in your `/uploads/` folder that:
- Exist on disk but aren't in WordPress database
- Aren't recognized as attachments
- Might be auto-generated copies, temporary files, or orphans

**Scan Types**:
- **Full Scan**: Scans all subdirectories in uploads
- **Directory-Specific**: Scan only a specific folder
- **Background Scan**: Runs automatically on schedule (Pro)

### 2. File Detection Methods

The scanner identifies files by:
- **File path**: Comparing disk files to database entries
- **File type**: Filtering by extension (jpg, png, pdf, etc.)
- **File size**: Identifying suspiciously large or tiny files
- **Date**: Finding old files not accessed recently

### 3. Rubbish File List

Results show:

| Column | Description |
|--------|-------------|
| **File Name** | Actual filename |
| **File Type** | Extension (jpg, png, pdf, etc.) |
| **File Size** | Size in MB/KB |
| **Path** | Location in uploads folder |
| **Status** | Pending, Ignored, Deleted, Restored |
| **Actions** | Delete, Ignore, Restore, Show |

---

## Step-by-Step Usage

### Scan for Rubbish Files

1. Go to **Media Library Tools → Rubbish Files**
2. Click **"Scan for Rubbish Files"** button
3. Scan begins (may take 1-30 minutes for large sites)
4. **Progress bar** shows:
   - Directories scanned
   - Files processed
   - Rubbish found so far
5. **Do not close page** while scanning (or it will resume later)
6. Scan completes with total rubbish count
7. Results display in list below

### Review Rubbish Files

1. After scan completes, browse the rubbish list
2. Look at **File Name** and **Path** columns
3. Identify recognizable files to delete
4. Use **File Type** filter to show only certain types:
   - Images: jpg, png, gif, webp
   - Documents: pdf, doc, docx
   - Archives: zip, rar, 7z
5. Sort by **File Size** to find largest files

### Delete Individual Rubbish Files

1. Find the rubbish file in the list
2. Click the **"Delete"** button (trash icon)
3. Confirmation dialog appears: "Delete this file permanently?"
4. Click **"Confirm Delete"**
5. File deleted from server
6. Row marked as "Deleted" and can be removed from list
7. Storage immediately freed

**Example**:
```
File: old-theme-backup.zip (45 MB)
Click Delete → Confirm → File removed
```

### Ignore a File

If a file looks safe but you want to keep it:

1. Click the **"Ignore"** button
2. File is marked as "Ignored"
3. Future scans won't flag it as rubbish
4. File stays on server

**Use case**: Keep temporary backup files you created manually

### Restore Deleted Files

If a file was mistakenly deleted:

1. Look for recently deleted files
2. If still in trash (immediate deletion): Click **"Restore"** button
3. File restored to original location
4. Status changes to "Restored"

**Note**: Only works immediately after deletion. If server trash is emptied, restoration may not be possible.

### View File Directory

To understand where a file is located:

1. Click **"Show in Directory"** button
2. Directory browser opens
3. Shows folder structure and file location
4. Helps identify what file is and why it exists

---

## Scan & Filter Options

### File Type Filter

After scan, filter results by file type:

- **All Files**: Show everything found
- **Images**: jpg, png, gif, webp, svg
- **Documents**: pdf, doc, docx, xls, txt
- **Archives**: zip, rar, 7z, tar, gz
- **Custom**: Filter by specific extension

### File Size Filter

Filter by size to find largest files:

- **Show all**: All sizes
- **Larger than 1 MB**: Large files worth deleting
- **Larger than 10 MB**: Very large files (videos, backups)
- **Smaller than 100 KB**: Tiny files

### Scan Scope

#### Full Directory Scan
- Scans entire /wp-content/uploads/ folder
- Takes longer (30+ minutes on large sites)
- Finds all rubbish across all subdirectories

#### Specific Directory Scan
- Select a subfolder to scan
- Useful if you know rubbish is in specific area
- Faster than full scan
- Example: Scan only 2024/04/ folder

---

## Common Workflows

### Clean Up After Theme Change

1. **Scan** for rubbish files
2. Filter by **Images**
3. Sort by **Date**
4. Identify old theme images (thumbnails, backups)
5. **Delete** old theme files
6. Keep images you recognize

### Remove Temporary Uploads

If you have test uploads or temporary files:

1. **Scan**
2. Filter by **File Name** or **Type** containing "temp", "test", "backup"
3. Review paths to confirm they're temporary
4. **Delete** confirmed temporary files

### Free Up Storage Space

1. **Scan** full directory
2. Sort by **File Size** (largest first)
3. Identify largest files and understand them
4. **Delete** confirmed rubbish files
5. Check freed space: WordPress Settings → Disk Usage

---

## Important Notes

### Safety Considerations

- **Backups**: Always backup before bulk deletion
- **Hidden Files**: Won't find truly hidden files (starting with .)
- **Database Links**: Doesn't affect actual attachment records
- **Reversible**: Deleted files can sometimes be restored (immediately after)

### What's Considered Rubbish

**Typical rubbish files**:
- Backup files (*.backup, *.bak)
- Temporary files (*.tmp, *.temp)
- Old theme thumbnails
- Plugin cache files
- Leftover from deleted plugins

**Usually NOT rubbish**:
- Files referenced in database
- Theme images (.htaccess, index.php)
- .webp versions of images (auto-generated)
- Thumbnails of registered attachments

### Scan Scheduling

**Free Version**:
- Manual scanning only
- Run when you have time

**Pro Version**:
- Automatic scheduled scans
- Weekly/monthly cleanup
- Auto-delete confirmed rubbish

### Large Files Handling

Files over certain sizes may be:
- Slow to scan
- Slow to delete
- Might need manual server cleanup

For extremely large files (100+ MB), consider:
- Use **File Manager** (cPanel/Plesk) for faster deletion
- Contact hosting provider for bulk cleanup

---

## Troubleshooting

**Q: Scan is taking very long**
- A: Normal for large sites (1000s of files). Leave page open—it will complete. Close and resume later if needed.

**Q: File I deleted still appears**
- A: May be cached. Refresh page and rescan. Or file in another location.

**Q: Error: "Permission denied" on delete**
- A: Server permissions issue. File permissions prevent deletion. Contact hosting provider or check folder permissions (755).

**Q: Scan finds nothing but I know I have rubbish**
- A: Files might actually be registered in database. Use [Used Where](used-where.md) to check if images are in use. If unused but registered, use Media Table to delete them instead.

**Q: Deleted file breaks website**
- A: File was probably in use (not actually rubbish). Restore immediately if possible, or re-upload the file.

---

## Pro Features

**Pro version adds**:
- Automatic scheduled scans
- Auto-delete rubbish files
- Advanced file detection (detects more file types)
- Restored file management
- File recovery/undelete within 30 days
- Detailed scan reports

**Features available in Pro**:
- Ignore specific file patterns
- Whitelist folders from scanning
- Notifications on rubbish found
- Bulk ignore with patterns (*.backup, *.cache)

---

## Best Practices

1. **Scan Regularly**: Run monthly for small sites, weekly for large sites
2. **Review Before Deleting**: Understand files before deletion
3. **Backup First**: Always have backups before bulk deletion
4. **Delete Conservatively**: Delete only files you're certain about
5. **Check Used Where**: Use [Used Where](used-where.md) to verify images aren't used
6. **Monitor Storage**: Track freed storage space over time

---

**Next**: Learn about [Duplicates](duplicates.md) to find and merge duplicate files
