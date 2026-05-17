---
title: "How to Find Unused Images in WordPress and Clean Up Your Media Library (2026 Guide)"
slug: find-unused-images-wordpress-media-library
meta_description: "Find unused images in WordPress fast. Learn how to detect used vs unused media, safely delete them, and reclaim disk space with Media Library Tools."
focus_keyword: "find unused images in WordPress"
keywords:
  - find unused images in WordPress
  - delete unused images WordPress
  - WordPress media cleanup
  - used where images plugin
  - WordPress unused media finder
  - clean up WordPress media library
category: WordPress Tutorials
tags:
  - Media Library
  - WordPress Optimization
  - Site Speed
  - WordPress Plugins
author: Tiny Solutions
date: 2026-05-17
featured_image: /assets/blog/find-unused-images-wordpress.jpg
schema_type: BlogPosting
---

# How to Find Unused Images in WordPress and Clean Up Your Media Library (2026 Guide)

Every WordPress site eventually ends up with the same hidden problem: a media library packed with images nobody is using. Old hero shots from a redesign, duplicate product photos, screenshots uploaded "just in case" — they all sit on your server, bloating backups, slowing migrations, and quietly costing you storage money.

In this guide, you'll learn **how to find unused images in WordPress**, separate them from the ones that are actually in use, and safely clean them up using the free [Media Library Tools](https://wordpress.org/plugins/media-library-tools/) plugin.

---

## Why Unused Images Are a Real Problem

Most WordPress sites carry **30%–60% of media files that are never displayed anywhere**. This matters because:

- **Backups take longer** — every unused image is still copied, compressed, and stored.
- **Hosting bills creep up** — storage and bandwidth aren't free.
- **Migrations get painful** — moving a 40 GB site is far worse than moving a 12 GB one.
- **Site search and admin lists slow down** — large `wp_posts` and `wp_postmeta` tables impact dashboard performance.
- **CDN costs grow** — orphaned files still get synced to your CDN origin.

The catch? WordPress doesn't tell you which images are used and which aren't. There's no built-in "Unused" filter. That's exactly the gap **Media Library Tools** fills with its **Used Where** feature.

---

## What "Used" vs "Unused" Actually Means

Before deleting anything, you need a clear definition. An image is considered **used** if it is referenced in at least one of these places:

- **Featured image** of a post, page, or custom post type
- **Inside post content** (Gutenberg blocks, classic editor HTML, shortcodes)
- **Page builder data** (Elementor, Beaver Builder, WPBakery serialized data)
- **Custom meta fields** (ACF, CMB2, Meta Box, builder JSON blobs)
- **WordPress options** (theme settings, logo, favicon, widgets, customizer)
- **WooCommerce product galleries** and gallery shortcodes
- **Frontend-rendered images** detected at runtime (catches dynamic templates)

Anything that doesn't appear in any of those locations is **unused** — a safe deletion candidate.

Media Library Tools scans all of these layers and stores results in a dedicated table (`tsmlt_image_usage`) so you can review, filter, and act on them without re-scanning every time.

---

## Step 1: Install Media Library Tools (Free)

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **"Media Library Tools"**.
3. Click **Install Now**, then **Activate**.
4. You'll see a new **Media Library Tools** menu appear in the sidebar.

The plugin is free, lightweight, and works on PHP 7.4+ — no account, no API key, no email gate.

---

## Step 2: Run the Used Where Scan

Open **Media Library Tools → Used Where**. You'll see a single big button: **Start Scan**.

Click it. The plugin will:

1. Loop through every attachment in your media library in batches (non-blocking, so your dashboard stays responsive).
2. Check each one against posts, pages, custom post types, post meta, options, and serialized builder data.
3. Optionally track **frontend usage** — actual `<img>` tags rendered on real page loads (enable this in **Settings → Track Frontend Usage** for the most accurate results).

Large libraries (10,000+ images) may take several minutes. You can leave the tab open or come back later — progress is persisted.

> **Tip:** Enable "Track Frontend Usage" for at least a week before deleting anything. Some images only appear via dynamic templates, AJAX, or conditional logic, and frontend tracking is what catches them.

---

## Step 3: Review the Three Tabs

After scanning, the Used Where page splits results into three tabs:

### 🟢 Used
Every image that was found in at least one location. Each row shows **where** it's used — post title, post type, and usage type (featured, content, meta, option, frontend). Click through to verify before assuming anything.

### 🔴 Unused
The interesting one. These attachments couldn't be found anywhere. Sort by file size to find the biggest wins first — a single unused 4 MB hero image is worth more than fifty 20 KB icons.

### 🗑️ Trash
A safety net. Images you move to trash are switched to `post_status='trash'` (WordPress native trash) and **hidden from the frontend** via three protective hooks:

- `wp_get_attachment_url` returns empty for trashed images
- `wp_get_attachment_image_src` returns false
- `wp_get_attachment_image` returns empty HTML

This means if you accidentally trash something that *is* used, it just disappears from view — it doesn't break with a broken-image icon. You can restore it from the Trash tab with a single click.

---

## Step 4: Safely Delete Unused Images

The recommended workflow is **trash first, delete later**:

1. From the **Unused** tab, select images you're confident about.
2. Click **Move to Trash**. They're hidden from the frontend immediately.
3. **Wait 7–30 days.** Browse your site. Check key landing pages. If anything looks broken, restore from Trash.
4. Once you're sure, open the **Trash** tab and click **Delete Permanently**. This calls `wp_delete_attachment($id, true)` — files are removed from the server and the database.

This two-stage process is the single biggest difference between "cleaning up" and "breaking your site at 2 a.m."

---

## Step 5: Keep It Clean Going Forward

A one-time cleanup is good. A clean library by default is better. Combine Used Where with these companion features in the same plugin:

- **Duplicate Detection** — MD5-hash scan finds byte-identical duplicate uploads. Pro merges them automatically and updates references everywhere.
- **Rubbish File Finder** — scans the `/wp-content/uploads/` folder for orphaned files (files on disk but not in the media library — common after failed imports or theme switches).
- **EXIF Data Tools** — strip GPS and camera metadata on upload, see camera/date columns in the media list, and scan your library for files still leaking location data.
- **Bulk Rename** — give all images SEO-friendly filenames based on post title, alt text, or SKU (Pro).

Together they turn the media library from a swamp into something you actually trust.

---

## Common Questions

### Will deleting unused images break my site?
Not if you follow the trash-first workflow. The frontend suppression hooks mean trashed images go invisible instead of broken, giving you a no-risk review window.

### Does the scan slow down my site?
No. Scanning runs in 50-item AJAX batches with a 100 ms delay between requests. It uses `wp_doing_ajax()` and runs in the background — your visitors never see it.

### What about images used inside Elementor / Divi / WPBakery?
Covered. The scanner recursively walks serialized builder data and JSON blobs in post meta, and the heuristic meta scanner specifically handles ACF galleries, WooCommerce comma-separated ID lists, and nested arrays.

### Does it work with WordPress Multisite?
Yes — scan each site individually from its own dashboard. Each site's results are stored separately.

### How is this different from other "media cleaner" plugins?
Three things: (1) it scans **WP options** (theme settings, widgets) which most plugins miss; (2) it can track **frontend usage** at runtime so it catches dynamic templates; (3) trashed images are **suppressed on the frontend**, so mistakes never result in broken-image icons.

---

## Bottom Line

If your WordPress site has been online for more than a year, you almost certainly have hundreds — maybe thousands — of unused images burning storage and slowing backups. You don't have to live with it, and you don't need a developer to fix it.

Install [Media Library Tools](https://wordpress.org/plugins/media-library-tools/), run a Used Where scan, trash the unused images, and reclaim the space. Most users free up between **20% and 50% of their uploads folder** on the first pass.

Your future self — the one running a backup, paying a hosting bill, or migrating to a new host — will thank you.

---

**Ready to clean up?** [Download Media Library Tools free from WordPress.org](https://wordpress.org/plugins/media-library-tools/) and run your first scan in under five minutes.
