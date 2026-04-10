# Media Library Tools - Documentation

Welcome to the complete documentation for Media Library Tools, the ultimate WordPress media management plugin.

## 📚 Documentation Structure

### For Users

**Getting Started**
- [Getting Started Guide](GETTING_STARTED.md) — Installation, first steps, initial configuration
- Best for: New users, first-time setup, basic configuration

**Usage & Features**
- [Complete User Guide](USER_GUIDE.md) — Detailed walkthroughs of every feature
- Best for: Understanding how to use each feature, step-by-step instructions
- Includes: Media Table, Renaming, Duplicates, Rubbish Files, Image Usage Tracking, AI Generator, Settings, CSV Export, Thumbnails, and more

**Questions & Help**
- [Frequently Asked Questions](FAQ.md) — Common questions and troubleshooting
- Best for: Quick answers, troubleshooting issues, understanding concepts
- Includes: Installation, Features, Compatibility, Troubleshooting, Best Practices

### For Developers

For developer documentation, see:
- [Main CLAUDE.md](../CLAUDE.md) — Architecture, code patterns, build commands
- [GitHub Repository](https://github.com/wptinysolutions/media-library-tools) — Source code, issues, contributions

## 🚀 Quick Start Paths

**I'm new to Media Library Tools**
1. Read: [Getting Started Guide](GETTING_STARTED.md)
2. Watch: Setup video (if available)
3. Explore: [First Steps](GETTING_STARTED.md#first-steps) section

**I want to understand a specific feature**
1. Open: [Complete User Guide](USER_GUIDE.md)
2. Find: Your feature in the Table of Contents
3. Follow: Step-by-step instructions

**I have a problem**
1. Check: [FAQ Troubleshooting](FAQ.md#troubleshooting) section
2. Search: FAQ for your issue
3. Ask: [Support Forum](https://wordpress.org/support/plugin/media-library-tools/)

**I want best practices**
1. Read: [Getting Started Tips](GETTING_STARTED.md#tips-for-best-results)
2. Check: [FAQ Tips](FAQ.md#tips--best-practices)
3. Ask: Community forum for your use case

## 📖 Feature Documentation

### Core Features

| Feature | Getting Started | User Guide | FAQ |
|---------|-----------------|-----------|-----|
| Media Table | ✅ | ✅ [Media Table](USER_GUIDE.md#media-table) | ✅ [Q&A](FAQ.md#media-table) |
| File Rename | ✅ | ✅ [Media Rename](USER_GUIDE.md#media-rename) | ✅ [Q&A](FAQ.md#media-file-renamer) |
| Duplicate Finder | ✅ | ✅ [Duplicates](USER_GUIDE.md#duplicate-finder) | ✅ [Q&A](FAQ.md#duplicate-image-finder) |
| Rubbish Cleaner | ✅ | ✅ [Rubbish Files](USER_GUIDE.md#rubbish-file-cleaner) | ✅ [Q&A](FAQ.md#rubbish-file-finder) |
| Image Usage Tracker | ✅ | ✅ [Used Where](USER_GUIDE.md#image-usage-tracker) | ✅ [Q&A](FAQ.md#image-usage-tracker-used-where) |
| AI Generator | ✅ | ✅ [AI Content](USER_GUIDE.md#ai-content-generator) | ✅ [Q&A](FAQ.md#ai-content-generator) |
| CSV Export | ✅ | ✅ [CSV Export](USER_GUIDE.md#csv-export) | ✅ [Q&A](FAQ.md#csv-exportimport) |
| Thumbnails | ✅ | ✅ [Regenerate](USER_GUIDE.md#regenerate-thumbnails) | ✅ [Q&A](FAQ.md#regenerate-thumbnails) |
| Image Sizes | ✅ | ✅ [Settings](USER_GUIDE.md#settings--configuration) | ✅ [Q&A](FAQ.md#image-sizes) |
| SVG Support | ✅ | ✅ [SVG](USER_GUIDE.md#svg-upload-support) | ✅ [Q&A](FAQ.md#svg-support) |
| Download Button | ✅ | ✅ [Shortcode](USER_GUIDE.md#media-download-shortcode) | ✅ [Q&A](FAQ.md#media-download-shortcode) |

## 💡 Common Workflows

### Organize Your Media Library
1. [Getting Started](GETTING_STARTED.md#second-steps) — Configure Alt Text Settings
2. [User Guide](USER_GUIDE.md#media-table) — Use Media Table to bulk edit
3. [User Guide](USER_GUIDE.md#media-categories) — Create and assign categories

### Rename Files for SEO
1. [Getting Started](GETTING_STARTED.md#start-with-the-media-table) — Overview
2. [User Guide](USER_GUIDE.md#media-rename) — Single rename or bulk rename
3. [FAQ](FAQ.md#media-file-renamer) — Best practices and troubleshooting

### Clean Up Duplicates
1. [User Guide](USER_GUIDE.md#duplicate-finder) — Scan and review
2. [FAQ](FAQ.md#duplicate-image-finder) — Understand what's happening
3. Merge (Pro feature)

### Find Orphaned Files
1. [User Guide](USER_GUIDE.md#rubbish-file-cleaner) — Scan for rubbish files
2. [FAQ](FAQ.md#rubbish-file-finder-media-library-cleaner) — What are rubbish files?
3. Delete or ignore safely

### Generate AI Metadata
1. [Getting Started](GETTING_STARTED.md#configure-ai-settings) — Setup AI provider
2. [User Guide](USER_GUIDE.md#ai-content-generator) — Use AI suggestions
3. [FAQ](FAQ.md#ai-content-generator) — Providers, models, troubleshooting

### Export for Backup/Analysis
1. [User Guide](USER_GUIDE.md#csv-export) — Select columns and export
2. Use spreadsheet app to analyze
3. [FAQ](FAQ.md#csv-export--import) — Format and usage

### Track Image Usage
1. [User Guide](USER_GUIDE.md#image-usage-tracker) — Scan for usage
2. Find unused images
3. Delete safely

## 🎯 Use Cases

### For Blog Authors
- Keep images organized with categories
- Use AI to generate alt text quickly
- Track which images are actually used
- Find and remove unused images

### For E-commerce Stores
- Rename product images for SEO
- Bulk edit product metadata
- Find duplicate images to save storage
- Export inventory for analysis

### For Agencies
- Batch rename client projects with prefix/suffix
- Organize media by category
- Audit image usage across the site
- CSV export for client reports

### For Developers
- Extend with custom hooks/filters
- Integrate with CI/CD pipelines via API
- Use CSV export/import for automation
- Monitor media library health

## 📚 Key Concepts

### Alt Text (Accessibility & SEO)
- Max 125 characters
- Describe what's in the image
- Key words at start
- No image filenames or special characters
- [Learn more →](USER_GUIDE.md#inline-editing-single-item)

### File Naming (SEO)
- Use descriptive, keyword-relevant names
- Hyphens between words
- Lowercase only
- Under 50 characters preferred
- [Learn more →](USER_GUIDE.md#naming-best-practices)

### Duplicate Files
- Found using MD5 hash fingerprinting
- Exact byte-for-byte comparison
- Shows wasted disk space
- [Learn more →](USER_GUIDE.md#duplicate-finder)

### Rubbish Files
- Exist on disk but not in media library
- Common sources: deleted plugins, failed uploads
- Can be marked as ignored
- [Learn more →](USER_GUIDE.md#rubbish-file-cleaner)

### Image Usage
- Tracks where images are actually used
- Finds unused images safe to delete
- Passive frontend tracking optional
- [Learn more →](USER_GUIDE.md#image-usage-tracker)

## 🔗 Resources

### Official Links
- 🌐 [Website](https://www.wptinysolutions.com)
- 📖 [Documentation Portal](https://docs.wptinysolutions.com/media-library-tools/)
- 💬 [Support Forum](https://wordpress.org/support/plugin/media-library-tools/)
- 🐛 [GitHub Repository](https://github.com/wptinysolutions/media-library-tools)
- 🎥 [Video Tutorials](https://www.youtube.com/channel/wptinysolutions)

### External Resources
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress.org Plugin Directory](https://wordpress.org/plugins/media-library-tools/)
- [WCAG 2.1 Alt Text Standards](https://www.w3.org/WAI/WCAG21/quickref/#text-alternatives)
- [Google Image SEO Guide](https://developers.google.com/search/docs/beginner/images)

## ❓ Still Need Help?

### Before Asking for Support
1. Check this documentation ✅
2. Search the [FAQ](FAQ.md) ✅
3. Check your browser console for errors (F12)
4. Check WordPress debug log (wp-config.php)
5. Try disabling other plugins to check conflicts

### Where to Get Help
- **Quick answers:** [FAQ](FAQ.md)
- **How-to guidance:** [User Guide](USER_GUIDE.md)
- **Setup help:** [Getting Started](GETTING_STARTED.md)
- **Community support:** [WordPress.org Forum](https://wordpress.org/support/plugin/media-library-tools/)
- **Bug reports:** [GitHub Issues](https://github.com/wptinysolutions/media-library-tools/issues)

## 📝 Documentation License

This documentation is part of Media Library Tools and is licensed under [GPLv3](http://www.gnu.org/licenses/gpl-3.0.html).

---

**Latest Update:** April 2026 | **Version:** 2.2.0

Have a suggestion for improving this documentation? [Open an issue on GitHub →](https://github.com/wptinysolutions/media-library-tools/issues)
