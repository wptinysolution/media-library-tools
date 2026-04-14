# Media Library Tools - Complete Documentation

Welcome to the official documentation for **Media Library Tools**, the ultimate WordPress media management plugin.

## 📚 Documentation Structure

This documentation is organized by feature and includes detailed guides for both free and Pro versions.

### Getting Started
- **[Quick Start Guide](QUICK-START.md)** — 5-minute setup and common tasks
- **[Home](index.md)** — Overview and navigation

### Core Modules (Free & Pro)

| Module | Description | Location |
|--------|-------------|----------|
| **[Media Settings](01-media-settings.md)** | Configure metadata defaults and behaviors | Settings page |
| **[Media Table](02-media-table.md)** | Main interface for managing all media files | Media Table |
| **[Media Rename](03-media-rename.md)** | Rename files individually or in bulk | Media Rename |
| **[Regenerate Thumbnails](04-regenerate-thumbnails.md)** | Rebuild image sizes for compatibility | Regenerate Thumbnails |
| **[CSV Export (Quick)](05-csv-export.md)** | Export selected media from Media Table | Media Table → Export |
| **[CSV Export (Bulk)](12-csv-export-bulk.md)** | Bulk export entire library (Pro) | CSV Export menu |
| **[CSV Import](06-csv-import.md)** | Import and update metadata from spreadsheets | CSV Import |
| **[Rubbish Files](07-rubbish-files.md)** | Find and clean orphaned media files | Rubbish Files |
| **[Duplicates](08-duplicates.md)** | Detect and manage duplicate images | Duplicates |
| **[Used Where](09-used-where.md)** | Track image usage across your site | Used Where |
| **[Image Sizes](10-image-sizes.md)** | Manage WordPress image dimensions | Image Sizes |
| **[Media Download](11-media-download.md)** | Add download buttons for media files | Media Download |

### Advanced Topics
- **[Pro Features Guide](PRO-FEATURES.md)** — Automation, AI, and advanced tools
- **[Free vs Pro Comparison](#free-vs-pro-features)** — Feature matrix and pricing

---

## 🎯 Quick Navigation

### By Task

**Want to organize images?**
- [Media Rename](03-media-rename.md) — Rename files
- [Image Sizes](10-image-sizes.md) — Manage image versions

**Want to clean up storage?**
- [Used Where](09-used-where.md) — Find unused images
- [Duplicates](08-duplicates.md) — Find and remove duplicates
- [Rubbish Files](07-rubbish-files.md) — Find orphaned files

**Want to edit metadata?**
- [Media Table](02-media-table.md) — Edit individual or bulk
- [Media Settings](01-media-settings.md) — Configure auto-population
- [CSV Import](06-csv-import.md) — Bulk update from spreadsheet

**Want to backup/migrate?**
- [CSV Export](05-csv-export.md) — Export metadata
- [Media Download](11-media-download.md) — Download files
- [CSV Import](06-csv-import.md) — Restore to new site

**Want automation?** (Pro)
- [Pro Features Guide](PRO-FEATURES.md) — AI, auto-rename, scheduled tasks

### By User Level

**Beginner**
1. Read [Quick Start Guide](QUICK-START.md)
2. Explore [Media Table](02-media-table.md)
3. Configure [Media Settings](01-media-settings.md)

**Intermediate**
1. Use [Duplicates](08-duplicates.md) to clean storage
2. Use [Used Where](09-used-where.md) to understand usage
3. Use [CSV Export/Import](05-csv-export.md) for bulk updates
4. Use [Media Rename](03-media-rename.md) for organization

**Advanced**
1. Schedule regular [Rubbish Files](07-rubbish-files.md) scans
2. Optimize with [Image Sizes](10-image-sizes.md)
3. Automate with [Pro Features](PRO-FEATURES.md)
4. Integrate CSV workflows for complex operations

---

## 📊 Free vs Pro Features

### Complete Feature Matrix

| Feature | Free | Pro |
|---------|------|-----|
| **Media Management** | | |
| View all media | ✓ | ✓ |
| Edit metadata | ✓ | ✓ |
| Bulk edit | ✓ | ✓ |
| Search & filter | ✓ | ✓ |
| **File Organization** | | |
| Rename files | ✓ | ✓ |
| Auto-rename by pattern | | ✓ |
| Auto-rename by post title | | ✓ |
| Rename prefix/suffix | | ✓ |
| **Metadata Defaults** | | |
| Alt text from filename | ✓ | ✓ |
| Custom default alt text | ✓ | ✓ |
| Alt text from post title | | ✓ |
| Caption/Description defaults | ✓ | ✓ |
| Caption/Description from post | | ✓ |
| **Advanced Features** | | |
| CSV export (Quick) | ✓ | ✓ |
| CSV export (Bulk/Entire library) | | ✓ |
| CSV import | ✓ | ✓ |
| Media download/backup | ✓ | ✓ |
| **Image Optimization** | | |
| Regenerate thumbnails | ✓ | ✓ |
| Image size management | ✓ | ✓ |
| **Finding Issues** | | |
| Find unused images | ✓ | ✓ |
| Detect duplicates | ✓ | ✓ |
| Find rubbish files | ✓ | ✓ |
| **Cleaning Up** | | |
| Delete rubbish files | | ✓ |
| Ignore rubbish files | | ✓ |
| Merge duplicates | | ✓ |
| Trash management | | ✓ |
| **Automation** | | |
| Auto alt text injection | | ✓ |
| AI content generation | | ✓ |
| Scheduled scans | | ✓ |
| Auto-cleanup | | ✓ |
| **Reporting** | | |
| Storage analysis | | ✓ |
| Usage reports | | ✓ |
| Cloud backups | | ✓ |

---

## 🤔 Frequently Asked Questions

### General Questions

**Q: Is my data stored on external servers?**
- A: No. All data stays on your WordPress server. Only AI features (optional) send data to external APIs (ChatGPT, Gemini, Claude).

**Q: Does this plugin slow down my website?**
- A: No. All operations run in WordPress admin only. Frontend performance unaffected.

**Q: Can I undo changes?**
- A: Most operations are reversible (with backups). Always export CSV before major bulk operations.

**Q: Does this work with my theme/plugins?**
- A: Yes. Compatible with all WordPress themes and plugins. Works with Elementor, WooCommerce, ACF, etc.

### Technical Questions

**Q: What PHP/WordPress versions are required?**
- A: PHP 7.4+ and WordPress 5.5+

**Q: Is there a maximum library size?**
- A: No hard limit. Large libraries (10,000+ images) may have slower scans (1-2 hours) but work fine.

**Q: Can I use this on multisite?**
- A: Yes. Works per site or network-wide (Pro).

**Q: Is my API key secure?**
- A: Yes. API keys encrypted in database and never logged.

### Upgrade Questions

**Q: What happens when I upgrade from Free to Pro?**
- A: All your data transfers automatically. No setup needed.

**Q: Is there a refund guarantee?**
- A: Yes. 30-day money-back guarantee on Pro licenses.

**Q: Can I upgrade/downgrade anytime?**
- A: Yes. Pro features gracefully degrade if license expires.

**Q: What about license renewal?**
- A: Annual licenses auto-renew (optional). One-time purchases available.

---

## 🚀 Getting Started

### Recommended First Steps

1. **Install & Activate**
   - Upload plugin to `/wp-content/plugins/`
   - Activate from WordPress admin

2. **Read Quick Start**
   - [QUICK-START.md](QUICK-START.md) — 5-minute overview

3. **Configure Settings**
   - [Media Settings](01-media-settings.md) — Set metadata defaults

4. **Explore Main Features**
   - [Media Table](02-media-table.md) — Manage all media
   - [Media Rename](03-media-rename.md) — Organize by name

5. **Clean Up (Optional)**
   - [Used Where](09-used-where.md) — Find unused images
   - [Duplicates](08-duplicates.md) — Remove duplicates

6. **Consider Pro** (Optional)
   - [Pro Features Guide](PRO-FEATURES.md) — See advanced options

---

## 📖 Reading Tips

### For Different Uses

**SEO Focused**: Read [Media Settings](01-media-settings.md) → [Auto Alt Text](PRO-FEATURES.md#auto-alt-text-on-frontend)

**Storage Optimization**: Read [Used Where](09-used-where.md) → [Duplicates](08-duplicates.md) → [Rubbish Files](07-rubbish-files.md)

**Team Collaboration**: Read [CSV Export](05-csv-export.md) → [Media Table](02-media-table.md) → [Bulk Edit](02-media-table.md#bulk-edit-multiple-files)

**Automation (Pro)**: Read [Pro Features](PRO-FEATURES.md) → [Media Settings](01-media-settings.md) → [Media Rename](03-media-rename.md)

---

## 🆘 Need Help?

### Self-Help Resources

1. **Read the Docs**: Most answers in feature guides
2. **Check Troubleshooting**: Every guide has Troubleshooting section
3. **Search Keywords**: Use Ctrl+F to find topics

### Getting Support

- **Documentation**: This guide covers all features
- **Plugin Support**: Visit plugin support forum
- **Pro Support**: Priority support for Pro members
- **Email**: Direct support available for Pro members

### Report Issues

Found a bug or have feature request?
- GitHub Issues: [Report Issue](https://github.com/example/repo/issues)
- Support Forum: [Ask Question](https://wordpress.org/support/plugin/)

---

## 📋 Documentation Quality

This documentation includes:
- ✓ Step-by-step guides for every feature
- ✓ Screenshots/examples (referenced in guides)
- ✓ Common workflows and use cases
- ✓ Troubleshooting section in each guide
- ✓ Free vs Pro feature clarity
- ✓ Important notes and safety considerations
- ✓ Best practices and recommendations

---

## 📅 Version & Updates

**Current Version**: 2.1.1+
**Last Updated**: April 2026
**PHP Requirement**: 7.4+
**WordPress Requirement**: 5.5+

### What's New in Recent Versions

- ✨ Enhanced duplicate detection accuracy
- ✨ Improved AI content generation
- ✨ Better image usage tracking
- ✨ Expanded Pro automation features
- ✨ Cloud backup integration (Pro)

[View Full Changelog](https://example.com/changelog)

---

## 🎓 Learning Path

### Path 1: Essential Features (1-2 hours)
1. [Quick Start](QUICK-START.md)
2. [Media Table](02-media-table.md)
3. [Media Settings](01-media-settings.md)

### Path 2: Storage Optimization (3-4 hours)
1. [Used Where](09-used-where.md)
2. [Duplicates](08-duplicates.md)
3. [Rubbish Files](07-rubbish-files.md)
4. [Media Rename](03-media-rename.md)

### Path 3: Pro Deep Dive (2-3 hours)
1. [Pro Features Guide](PRO-FEATURES.md)
2. [Media Settings](01-media-settings.md)
3. [Media Rename](03-media-rename.md)
4. [Duplicates](08-duplicates.md)

---

**Happy managing! 🎉** For any questions, refer to specific feature guides or visit the support forum.

---

**Copyright © 2026 Tiny Solutions** | [Visit Website](https://example.com) | [Contact Support](https://example.com/support)
