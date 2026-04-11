# Pro Features Guide

Media Library Tools Pro unlocks advanced automation, AI-powered content generation, and intelligent management tools that save hours of work.

## Overview

Pro adds powerful features that automate repetitive tasks and provide insights beyond the free version. All Pro features are marked with a **PRO** badge in the plugin interface.

**Current Version**: Media Library Tools Pro 2.1.1+

## What You Get With Pro

### 1. Auto Rename Strategies

**Location**: Settings → Renamer Settings

#### Auto Rename by Post Title
- **What it does**: Automatically rename images when uploaded to a post
- **Example**: Upload image to post "10 Baking Tips" → Image becomes "10-baking-tips.jpg"
- **When**: Triggered during post edit upload
- **Benefit**: SEO-friendly filenames, organized library

#### Auto Rename by Custom Pattern (Pro)
- **What it does**: Automatically rename all uploads with custom pattern
- **Patterns Available**:
  - `{filename}`: Original filename
  - `{date}`: Upload date (YYYY-MM-DD)
  - `{title}`: Post title (if attached)
  - `{random}`: Random string
- **Example Pattern**: `product-{date}`
  - Result: `product-2024-04-11.jpg`

#### Rename Prefix & Suffix
- **What it does**: Add text before/after every filename
- **Example**:
  - Prefix: "site-"
  - Suffix: "-2024"
  - Filename: "photo"
  - Result: "site-photo-2024.jpg"

### 2. Auto Alt Text on Frontend

**Location**: Settings → Renamer Settings → "Auto Alt Text on Frontend"

#### Smart Alt Text Injection
- **What it does**: Automatically adds missing alt text to images on your public website
- **Sources** (in priority order):
  1. Post/page title (if enabled)
  2. Image filename (fallback)
  3. Custom default text (if configured)
- **Example**:
  - Post title: "Best Coffee Shops"
  - Image with no alt text displayed in post
  - Frontend auto-adds: `alt="Best Coffee Shops"`
- **SEO Benefit**: Improves accessibility and search visibility

#### Configuration Options
- **Enable/Disable**: Toggle on/off
- **Use Post Title**: When enabled, prefers post title as alt text
- **Fallback Text**: Custom text if post title unavailable
  - Example: "Image" (generic fallback)
  - Better: Descriptive text like "Product Photo"

#### When It Works
- ✓ Images in post content (missing alt)
- ✓ Images without explicit alt attribute
- ✓ Direct img tags in HTML
- ✓ Theme-generated images
- ✗ Images with alt text already (not modified)
- ✗ Images loaded via JavaScript (after page load)

### 3. AI Content Generation

**Location**: Media Table → AI button (on each image)

#### Generate Alt Text
- **How it works**: AI analyzes image and generates descriptive alt text
- **Using**: Image content + post title + site context
- **Example**:
  - Image of: Coffee cup with latte art
  - AI generates: "Cup of freshly brewed latte with artistic foam design"
- **Quality**: Human-readable, SEO-friendly

#### Generate Captions
- **How it works**: AI creates engaging caption from image
- **Example**:
  - Image of: Mountain landscape at sunset
  - AI generates: "Golden hour illuminates the majestic peaks of the Rocky Mountains"

#### Generate Descriptions
- **How it works**: AI writes detailed description for accessibility/SEO
- **Example**:
  - Image of: Team meeting in office
  - AI generates: "Professional team collaborates at conference table during business meeting, emphasizing teamwork and workplace diversity"

#### AI Provider Selection
Choose your preferred AI service:

**ChatGPT (OpenAI)**
- Models: GPT-4o, GPT-4, GPT-3.5, etc.
- Strengths: Most advanced, excellent descriptions
- Cost: Moderate ($0.005-0.03 per image)
- Requires: API key from OpenAI

**Gemini (Google)**
- Models: Gemini 2.0 Flash (default), Gemini 1.5, etc.
- Strengths: Fast, multimodal, good image understanding
- Cost: Low (free tier available)
- Requires: API key from Google AI Studio

**Claude (Anthropic)**
- Models: Claude Opus (best), Sonnet (balanced), Haiku (fast)
- Strengths: High quality, nuanced writing
- Cost: Moderate
- Requires: API key from Anthropic

#### Send Image to AI (Pro Feature)
- **What it does**: Send actual image to AI (not just filename)
- **Benefit**: More accurate content generation
- **Cost**: Uses more API tokens (increases cost)
- **Default**: Disabled (uses only text context)
- **When to Enable**: If image quality descriptions critical
- **Tradeoff**: Better quality vs higher cost

#### AI Settings
- **Provider**: Choose ChatGPT, Gemini, or Claude
- **API Key**: Securely store your API key
- **Model**: Select specific model (more advanced = higher cost)
- **Suggestion Count**: 3-10 alternative suggestions (Pro)
- **Send Image**: Send actual image to AI for analysis

---

## Advanced Features

### 1. Delete & Ignore Rubbish Files (Pro)

**Location**: Rubbish Files → Individual file actions

#### Delete Rubbish
- Direct deletion of rubbish files
- Confirmation dialog
- Immediately frees storage

#### Ignore Rubbish
- Mark files as "safe" (not rubbish)
- Won't appear in future scans
- Useful for intentional server files (backups you want to keep)

### 2. Merge Duplicates with Reference Updates (Pro)

**Location**: Duplicates → "Merge" button on duplicate group

#### What It Does
1. **Detects All References**:
   - Posts/pages using image
   - Featured images
   - Theme settings
   - Meta fields
   - Custom plugin references

2. **Updates All References**:
   - Old image ID → New image ID
   - Featured image assignments
   - Content links
   - Metadata pointers

3. **Deletes Redundant Files**:
   - Removes duplicate image files
   - Frees storage
   - Preserves one copy

#### Example Workflow
```
Group: sunset.jpg (3 copies)
- sunset.jpg (original, 5 MB, used in 3 posts)
- sunset-copy.jpg (duplicate, 5 MB, used in 2 posts)
- sunset-old.jpg (old backup, 5 MB, unused)

Merge → Keep: sunset.jpg (highest quality)
Result:
- sunset.jpg kept
- sunset-copy.jpg deleted
- sunset-old.jpg deleted
- All 5 posts updated to use sunset.jpg
- Storage saved: 10 MB
```

#### Reference Update Details
Updates these automatically:
- Post featured images (`_thumbnail_id` meta)
- Post content links (parses HTML)
- Post/page metadata
- Theme customizer settings (header, footer, etc.)
- Widget data
- Custom meta fields
- Elementor/builder data

---

### 3. Trash Management (Pro)

**Location**: Used Where → Trash tab

#### Move to Trash
- Move unused images to trash without immediate deletion
- Images still in trash tab
- Accessible for restoration

#### Restoration
- Restore from trash back to Unused/Used tabs
- Undo mistaken moves
- 30-day grace period (configurable)

#### Permanent Deletion
- Delete from trash permanently
- No recovery possible
- Frees storage immediately

---

### 4. Scheduled Operations (Pro)

#### Automatic Scans
- **Duplicate Scan**: Automatically scan for duplicates on schedule
- **Rubbish Scan**: Find new rubbish files automatically
- **Usage Scan**: Update image usage tracking
- **Schedule**: Daily, weekly, monthly options

#### Automatic Cleanup
- **Auto-Delete Rubbish**: Automatically delete marked rubbish
- **Auto-Merge Duplicates**: Automatically merge duplicates
- **Retention Policies**: Auto-delete very old unused images

#### Scheduled Backups
- Automatic CSV exports on schedule
- Cloud backup integration (Pro+)
- Backup rotation and retention

---

### 5. Advanced Reporting (Pro)

#### Storage Analysis
- Visual breakdown of storage usage
- Largest files
- Unused storage
- Duplicate storage savings
- Recommendations

#### Usage Reports
- Image popularity (most used)
- Unused image trends
- Reference patterns
- Download reports as PDF

#### Trend Analysis
- Upload trends over time
- Duplicate trends
- Rubbish accumulation
- Storage growth patterns

---

## How to Upgrade

### Upgrade Path
1. Own free version already? Your data transfers automatically
2. Visit [Pro Upgrade Page](https://example.com/pro)
3. Purchase license
4. Receive activation key
5. Enter key in Settings → License
6. Pro features unlock instantly

### License Management
**Location**: Settings → License Management

- **Status**: Shows if license active/expired
- **Key**: Your unique license key
- **Expiration**: When license expires (if applicable)
- **Deactivate**: Deactivate for transfer to another site

### Multiple Sites
- **Single License**: One site per license
- **Multi-Site License**: Available (contact sales)
- **Development License**: Special pricing for dev/staging
- **Agency License**: Multiple sites under one account

---

## Pro Cost Analysis

### Typical Savings
Using Pro features can save significant time and storage:

| Task | Manual Time | Pro Time | Savings |
|------|------------|----------|---------|
| Rename 100 images | 30 min | 1 min | 29 min |
| Generate alt text (10 images) | 15 min | 2 min | 13 min |
| Merge duplicates (50 duplicates) | 1 hour | 5 min | 55 min |
| Cleanup rubbish | 30 min | 5 min | 25 min |
| **Monthly Savings** | **~5 hours** | **~15 min** | **~285 minutes!** |

### Storage Savings
With duplicate detection and merging:
- Average site: 10-20% storage saved ($2-5/month on typical hosting)
- Large site: 20-50% storage saved ($5-20/month)
- Typical ROI: Pays for itself in 2-3 months for large sites

---

## Pro Best Practices

1. **Verify Before Merging**: Always verify duplicates are true duplicates
2. **Backup First**: Export CSV before major Pro operations
3. **Use AI Wisely**: Check AI-generated content before publishing (usually good, sometimes needs editing)
4. **Review References**: After merge, spot-check frontend to verify updates
5. **Monitor Costs**: AI generation costs accumulate—track your API usage
6. **Schedule Offpeak**: Run scheduled scans during off-peak hours
7. **Archive Reports**: Keep monthly reports for compliance

---

## Troubleshooting Pro Features

**Q: Merge says "0 updates" but I expected references**
- A: Image may not have been actively referenced. Merge still removes duplicates, just no references to update.

**Q: AI generated alt text seems generic**
- A: Try enabling "Send Image to AI" for more detailed analysis (costs more tokens). Or provide better context via post title.

**Q: License key not working**
- A: Verify key is correct (case-sensitive). Check license expiration. Ensure site URL matches license.

**Q: Auto-rename not triggering**
- A: Enable in Settings. Image must be uploaded while editing post (attached to post). Not triggered for uploads to Media Library directly.

**Q: Cost is higher than expected (AI)**
- A: Each image generation costs tokens. Reduce "Send Image" option or reduce suggestion count. Monitor API usage.

---

## FAQ

**Q: How long is the Pro license?**
- A: Annual by default. Lifetime licenses available (higher price).

**Q: Can I use Pro on multiple sites?**
- A: Single-site license per site. Multi-site licensing available (email sales).

**Q: Do I lose free features when I upgrade?**
- A: No. Pro is additive—all free features still available.

**Q: Is there a refund policy?**
- A: 30-day money-back guarantee if not satisfied.

**Q: How much does AI cost?**
- A: Depends on AI provider and model. Typically $0.001-0.05 per image. Check provider pricing.

---

## Support

**Pro members get**:
- Priority email support
- Extended documentation
- Direct plugin developer access
- Feature request input

[Contact Pro Support](https://example.com/support)

---

**Ready to Upgrade?** [Get Media Library Tools Pro](https://example.com/pro)
