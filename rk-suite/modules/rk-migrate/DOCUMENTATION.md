# RK Migrate — User Documentation

**The Elementor Migration Kit** · v3.2.0 · by Rakib Hasan ([rakibhasaan.com](https://rakibhasaan.com))

This is the end-user guide. For the developer/architecture overview see `README.md`.
The same content is available inside the plugin under the **Help & Docs** tab.

---

## 1. Install & activate

1. WordPress admin → **Plugins → Add New → Upload Plugin**.
2. Choose `rk-migrate-3.0.0.zip`, **Install**, then **Activate**.
3. A **RK Migrate** item appears in the sidebar. Open it — you'll see a tabbed screen:
   *Import · Export · Library · History & Rollback · Manifest Builder · Remote · Marketplace · Settings · Help & Docs.*

**Requirements:** Elementor active (Elementor Pro for header/footer auto-assign);
Yoast *or* Rank Math for SEO meta; PHP `ZipArchive` + `OpenSSL` for export/encryption.

---

## 2. The core idea

RK Migrate moves an Elementor site as a portable `.zip` **bundle**
(a `manifest.json` plus one JSON file per page/template). You **export** a site
into a bundle, then **import** that bundle onto another site. Everything else
builds on those two actions.

---

## 3. Quick start — clone a site

1. **Export** (on the source site): leave everything selected → **Export Bundle** → download the zip.
2. **Import** (on the new site): upload the zip. RK Migrate shows a plan of what will be **created** vs **updated**.
3. Add **Find & Replace** rules (old phone → new phone, old brand → new brand) and the **Staging → Production** URL boxes.
4. Tick **media re-link** (pull images into the new site) and **snapshot** (so you can undo).
5. Click **Run Import** and watch the live progress log.

> Always run a **Dry run** first — it reports what would happen and changes nothing.

---

## 4. Tab-by-tab

**Import** — Upload or select a bundle, preview the plan, apply find/replace and
URL rewrite, take a rollback snapshot, and run a live, resumable import (one step
per request, so large sites never time out).

**Export** — Scan the site and download a bundle. Export everything or select
specific pages, posts, custom post types, and templates. Optionally bundle media
files and encrypt the bundle with a password.

**Library** — Store master templates inside the install and activate one without
re-uploading. Great for agencies reusing a base build.

**History & Rollback** — Audit log of every run, plus pre-import snapshots you
can restore page-by-page or all at once.

**Manifest Builder** — Edit a bundle's page list visually (file, slug, title,
type, front page, SEO title) and save it as a new Library bundle.

**Remote** — Pair two sites with a token to push a bundle straight into another
site, pull a site down as a bundle, or sync a shared header/footer to many sites.

**Marketplace** — Browse and one-click install full-site starter bundles into
your Library.

**Settings** — License tier, role-based access, webhooks, remote token, AI key,
and cloud storage.

---

## 5. Find & Replace

Rules are applied to the raw JSON of every page *before* it's written, so swaps
reach every widget and setting. Use it for phone numbers, brand names, email
addresses, color hex values, and especially staging → production URLs. Tick
**Regex** for pattern rules. The dedicated **Staging → Production** boxes are a
shortcut that rewrites internal links and image URLs in one step.

---

## 6. Rollback

Before any real import, tick **Take rollback snapshot**. RK Migrate saves the
Elementor data of every page it's about to touch. If anything looks wrong, go to
**History & Rollback** and restore — one page or all of them.

---

## 7. WP-CLI

```bash
wp rk-migrate export --output=site.zip --media
wp rk-migrate import site.zip --from=https://staging.x --to=https://live.x --dry-run
wp rk-migrate list-library
wp rk-migrate rollback <snapshot-token>
```

---

## 8. FAQ

**Will re-running create duplicates?** No — pages match by slug, templates by
title. Re-running updates in place. Menus are rebuilt cleanly each run.

**Are images migrated?** Only if you tick *media re-link* on import (or bundle
media on export). Otherwise image URLs are kept as-is.

**How do I undo an import?** Keep *snapshot* on, then restore from History & Rollback.

**Is the Remote API safe?** It's off by default and token-gated. Enable it only
while pairing sites and keep the token secret.

---

## 9. Going-live checklist

1. Dry run → real run (snapshot on).
2. Confirm header/footer + menu render; spot-check pages in Elementor.
3. Replace placeholder images if you didn't use media re-link.
4. Remove site-wide noindex (Settings → Reading); set canonical/home URL.
5. Add 301 redirects for renamed/removed URLs.
6. Submit the XML sitemap in Google Search Console.

---

Need more? Open the **Help & Docs** tab inside RK Migrate, or visit
[rakibhasaan.com](https://rakibhasaan.com).
