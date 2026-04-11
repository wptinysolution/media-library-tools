# Duplicates

The Duplicates tool finds duplicate image files in your media library using advanced file hashing. It identifies identical files (whether renamed or in different folders) and helps you remove duplicates or merge them while maintaining references.

## Overview

Duplicate images waste storage and make organization difficult. They often exist because:
- Users upload the same image multiple times
- Images imported from external sources without deduplication
- Manual file uploads created copies
- Editing workflows created versions

The Duplicates tool finds these and safely removes them.

**Location**: Media Library Tools → Duplicates

## Why Remove Duplicates?

### Benefits

- **Storage Savings**: Recover 5-50% of media storage
- **Organization**: Cleaner media library
- **Performance**: Faster media library loading
- **Bandwidth**: Reduce server bandwidth for copies
- **SEO**: Avoid duplicate content issues

### Example Savings

- 1000 images with 20% duplicates = 200 redundant copies
- Average image: 2-5 MB
- Potential savings: 400-1000 MB freed

---

## Feature Breakdown

### 1. Duplicate Detection

The system finds duplicates using:
- **MD5 File Hash**: Unique fingerprint of file content
- **File Comparison**: Binary-level matching
- **Filename Agnostic**: Finds duplicates even if renamed
- **Format Agnostic**: Finds copies regardless of filename extension

**How it works**:
```
Image A (sunset.jpg) → MD5 Hash: abc123
Image B (sunset-copy.jpg) → MD5 Hash: abc123
Image C (photo.jpg) → MD5 Hash: abc123

Result: All three are identical → Grouped as duplicates
```

### 2. Duplicate Groups

Results are organized in groups:

| Column | Description |
|--------|-------------|
| **Group ID** | Unique group number |
| **Count** | Number of duplicates in group |
| **Potential Savings** | Space that could be freed |
| **Filenames** | All files in this group |
| **Actions** | View, Merge, Ignore |

### 3. Merge vs Delete

#### Delete Duplicates
- Remove extra copies
- Keep one original
- URLs still work (WordPress DB preserved)

#### Merge Duplicates (Pro)
- Delete extras
- Update all references to use kept image
- Comprehensive URL replacement in:
  - Post content
  - Featured images
  - Metadata references
  - Theme settings

---

## Step-by-Step Usage

### Scan for Duplicates

1. Go to **Media Library Tools → Duplicates**
2. If no scan yet, click **"Scan for Duplicates"** button
3. Progress bar appears showing:
   - Files scanned
   - Duplicates found so far
   - Estimated time remaining
4. **Leave page open** during scan (or resume later)
5. Scan may take 5-60 minutes depending on:
   - Number of images
   - Server speed
   - Image file sizes
6. Results display when complete

### View Duplicate Groups

After scan, results show groups:

1. Each row = one duplicate group
2. Example:
   ```
   sunset.jpg (5 MB) — 3 duplicates found
   Duplicates:
   - sunset.jpg
   - sunset-copy.jpg
   - sunset-old.jpg
   ```
3. Click group to expand and see all files
4. Sort by **Potential Savings** to prioritize cleanup

### Delete Duplicate Files (Free)

1. Find duplicate group
2. Identify which copy to keep (usually original)
3. Click **"Delete"** on duplicates you want to remove
4. Confirmation: "Delete this file and all versions?"
5. Click **"Confirm Delete"**
6. File deleted, storage freed
7. References in WordPress preserved (by ID)

**Example Workflow**:
```
Group: sunset.jpg (3 duplicates, 15 MB total savings)
Keep: sunset.jpg (original)
Delete: sunset-copy.jpg (delete)
Delete: sunset-old.jpg (delete)
Result: 10 MB freed, 1 kept
```

### Merge Duplicates (Pro Feature)

When you have Pro, merge keeps one and updates all references:

1. Find duplicate group
2. Click **"Merge"** button
3. Choose which image to keep (or auto-select)
4. Click **"Confirm Merge"**
5. Process:
   - Scans all posts, pages, settings
   - Updates all references to use kept image
   - Deletes removed image files
   - Updates featured images
   - Updates metadata references
6. Completion message shows updates made
   - "Updated 45 post references, 12 featured images"

**Pro Merge Features**:
- Automatic reference detection
- Update featured image assignments
- Update post content links
- Update theme settings
- Update meta fields
- Preserve all references
- Undo capability

### Clear Old Scan Results

To start fresh scan:

1. Go to **Duplicates**
2. Click **"Clear Results"** button
3. Confirmation: "Remove all scan results?"
4. Click **"Confirm"**
5. Previous results cleared
6. Can now run new scan

---

## Scan & Analysis

### Scan Status

During or after scan:

| Status | Meaning |
|--------|---------|
| **Scanning...** | Currently processing files |
| **Scanning: 45/1000** | Progress (45 of 1000 files) |
| **Complete** | Scan finished, results ready |
| **No duplicates** | Scan found no matches |

### Duplicate Statistics

View summary:

- **Total Duplicates Found**: Number of duplicate groups
- **Potential Savings**: Total storage that could be freed
- **Duplicate Files**: Individual duplicate files (not grouped)
- **Duplicate Groups**: Number of groups (each group = 2+ duplicates)

**Example**:
```
Duplicates Found: 127 groups
Potential Savings: 1.2 GB
Files to Review: 254 (127 groups × 2 duplicates per group average)
```

### Manual Investigation

For groups you're unsure about:

1. Click group to expand details
2. Review **Filename** and **Path** of duplicates
3. Check **Upload Date** to identify newer/older copies
4. Use **Actions** to view or check usage

If unsure:
- Use [Used Where](used-where.md) to see if image is actively used
- Check website frontend to see which version displays
- Keep higher resolution/quality version

---

## Common Workflows

### Quick Cleanup: Delete Most Duplicates

1. **Scan** for duplicates
2. Sort by **Potential Savings** (largest first)
3. Review top 10 groups
4. For each group:
   - Keep original (usually first listed)
   - **Delete** other copies
5. Repeat for remaining groups
6. Monitor freed storage

### Pro: Comprehensive Merge

1. **Scan** for duplicates
2. Review all groups
3. For each group, click **"Merge"**
4. System automatically:
   - Keeps best version
   - Updates all references
   - Deletes extras
5. Completion shows total storage freed and references updated

### Find Specific Duplicate

1. **Scan**
2. Use search/filter for filename
3. Find related duplicates
4. Review group carefully
5. Decide which to keep
6. Delete or merge others

### Archive Before Cleanup

To safely clean up:

1. **Export CSV** from Media Table
2. Run **Duplicate Scan**
3. Note duplicates to delete
4. **Back up server files** (or export media)
5. Delete duplicates
6. Keep CSV as record

---

## Important Notes

### Duplicate Detection Accuracy

**Highly accurate**:
- Same file content = always found
- Different filenames = still found
- Identical images = always matched

**Possible false positives**:
- Intentionally kept versions (e.g., edited + original)
- Variations intentionally preserved

**Unlikely issues**:
- Only compares file content (not metadata)
- Doesn't false-match different images
- Very reliable for finding true duplicates

### Safety Considerations

- **Non-destructive (Free)**: Deletion only removes duplicates, not originals
- **References Preserved**: WordPress DB updated to stay intact
- **Pro Merge Safe**: Pro handles reference updates automatically
- **Reversible**: Deleted files may be recoverable immediately
- **Backup First**: Always backup before bulk deletion

### What Gets Updated (Pro Merge)

✓ Post/page featured images
✓ Image references in post content
✓ Meta fields and metadata
✓ Theme settings with image URLs
✓ Custom post type references

✗ Direct HTML image tags (in custom code)
✗ External site references
✗ Hard-coded URLs in PHP

### Scan Performance

Large sites (5000+ images):
- Scan time: 30-120 minutes
- Resource usage: Moderate
- Best done during off-peak hours
- Safe to pause/resume

---

## Troubleshooting

**Q: Scan is taking too long**
- A: Normal for large media libraries. Leave page open—scan runs in background. Or close and resume later.

**Q: No duplicates found but I know they exist**
- A: Duplicates must be byte-for-byte identical (including metadata). Edited versions won't match. Check images carefully.

**Q: Merge shows "0 updates" but I expected more**
- A: Image might not be actively used in posts/pages. It's still deleted, just no references to update.

**Q: Error: "Permission denied" on delete**
- A: Server permission issue. Check file permissions (should be 644). Contact hosting provider.

**Q: Deleted image breaks featured images**
- A: Should not happen—WordPress database should preserve references. Check database integrity or clear caches.

---

## Pro Features

**Upgrade to Pro for**:
- **Merge Duplicates**: Merge with automatic reference updates
- **Smart Merge**: Algorithm picks best quality image to keep
- **Reference Tracking**: Detailed report of what was updated
- **Undo Merge**: Restore merged duplicates within 30 days
- **Scheduled Scans**: Automatic duplicate detection
- **Notification**: Alert when new duplicates detected

---

## Best Practices

1. **Scan Monthly**: Identify growing duplicates early
2. **Review Carefully**: Understand duplicates before deleting
3. **Backup First**: Always have backup before bulk operations
4. **Delete Conservative**: Delete only clear duplicates
5. **Use Pro for References**: Use Pro Merge to ensure all references updated
6. **Archive Results**: Keep scan reports for record

---

**Next**: Learn about [Used Where](used-where.md) to track image usage
