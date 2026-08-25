# RK Migrate — The Elementor Migration Kit

**Author:** Rakib Hasan · [rakibhasaan.com](https://rakibhasaan.com)
**Version:** 3.5.0

RK Migrate turns Elementor site migration into a round trip. **Export** a whole
site to a portable bundle, then **import/update** it on any other site — pages,
posts, custom post types, header/footer, menus, SEO meta, and global
colors/fonts — all driven by a single `manifest.json`. Built for freelancers and
agencies shipping many Elementor sites.

> v3.0.0 is a major release: the one-way importer (v2) is now a full
> export + import engine with find & replace, media re-link, rollback, a visual
> manifest builder, site-to-site push/pull, webhooks, encryption,
> and WP-CLI.

---

## What's new in 3.0.0

**Phase 1 — Core round-trip**
- **Full Site Export** — scan every Elementor page/post/CPT/template + menus and download a ready-to-import `.zip` with an auto-generated `manifest.json`.
- **Selective Export** — checkbox list; export only what you need.
- **Find & Replace on import** — token swaps (phone, domain, brand, hex) applied to every JSON before write. One template → infinite client variations.
- **AJAX import with live progress** — one step per request, so large sites never hit a PHP timeout. Real-time log + progress bar.
- **Import history** — persistent audit log (DB table) of every run.

**Phase 2 — Pro workflows**
- **Media export & re-link** — bundle referenced images on export; on import, sideload them into the new media library and rewrite every URL.
- **Blog posts & CPT import** — any post type, with featured image + taxonomy terms.
- **Global colors & fonts sync** — migrate the Elementor kit design system.
- **One-click rollback** — auto-snapshot affected pages before a run; restore any page (or all) from the history.
- **Manifest Builder UI** — edit the manifest visually and save it as a library bundle, no hand-editing JSON.
- **Staging → Production push** — URL-rewrite helper that fixes internal links, image src, and hrefs.
- **WooCommerce templates** — export/import Woo Elementor templates.
- **Bundle Library** — store master templates inside the install; activate one without re-uploading.

**Site Doctor (v3.2.0)** — agency clean-up tools that scan every page's Elementor data:
- **Color & Font Reclaim** — deep scan finds colors set manually anywhere (widget text, container/section backgrounds, overlays, gradients, borders, box-shadows, repeater items) plus manual fonts. Bind top-level controls to a Global Color/Typography token, or replace a color value everywhere (handles nested colors that can't be bound).
- **Corners & styles** — inventory every border-radius in use and normalise them all to one value for consistency.
- **Section → Container converter** — best-effort conversion of legacy Section/Column layouts to flex Containers (widths, gaps, padding, backgrounds), with per-page preview and rollback.
- **SEO & content audit** — buttons/links inventory, empty/external links, live broken-link (HTTP) check, missing/duplicate H1, skipped heading levels, and images missing alt text — each with an edit link.
- Every fix takes a rollback snapshot first.

**Phase 3 — Agency**
- **Remote push / pull** — pair two sites with a token; push a bundle straight into another site or pull a site down as a bundle over the REST API.
- **Shared component sync** — push a header/footer/section update to many client sites at once.
- **Role-based access** — separate capabilities for import / export / rollback / view-log.
- **Webhook notifications** — POST to Slack/Discord/any URL on import success or failure.
- **Bundle encryption** — AES-256 password-locked `.epenc` bundles for selling kits.

**Phase 4 — Ecosystem**
- **WP-CLI** — `wp rk-migrate export|import|list-library|rollback` for CI/CD.
- **Cloud bundle storage** *(scaffold)* — fully-wired REST client; point it at an endpoint (Settings → Cloud) or a custom backend via the `rk_migrate_cloud_request` filter.
- **Template marketplace** *(scaffold)* — in-admin catalog with one-click install; serve your own catalog via the `rk_migrate_marketplace_endpoint` filter.
- **AI content swap** *(scaffold)* — rewrites placeholder copy for the target client using your own OpenAI-compatible key (Settings → AI). No traffic flows through RK Migrate.

---

## Install

1. **Plugins → Add New → Upload Plugin**, upload `rk-migrate.zip`, **Activate**.
2. Open **RK Migrate** in the admin sidebar — a tabbed screen:
   *Import · Export · Library · History & Rollback · Manifest Builder · Remote · Marketplace · Settings.*
3. Set your **tier** in Settings (self-hosted build exposes all tiers).

**Requirements:** Elementor active; Elementor **Pro** for header/footer
auto-assign. Yoast **or** Rank Math for SEO meta. `ZipArchive` + `OpenSSL` PHP
extensions for export/encryption.

---

## Typical workflows

**Clone a site to a new client**
1. On the source site: **Export** tab → select all → Export Bundle.
2. On the new site: **Import** tab → upload the bundle → add Find & Replace
   rules (old phone → new phone, staging URL → live URL) → tick *media re-link*
   → **Run Import** and watch the live log.

**Agency master template → many clients**
1. **Export** your master once, **Library** → store it.
2. For each client: Library → Activate → Import with Find & Replace.
   Or **Remote** → push straight into the client's site over the API.

**CI/CD**
```bash
wp rk-migrate export --output=site.zip --media
wp rk-migrate import site.zip --from=https://staging.x --to=https://live.x --dry-run
```

---

## The manifest

`manifest.json` is the single source of truth. v3 keeps full backward
compatibility with v2 manifests and adds:

```json
{
  "project": "Acme Co",
  "global_kit": "global-kit.json",
  "replace": [
    { "find": "(555) 123-4567", "replace": "(770) 555-0000" },
    { "find": "https://staging.acme.com", "replace": "https://acme.com" }
  ],
  "pages": [
    { "file": "home.json", "slug": "home", "title": "Acme Co — Home",
      "is_front_page": true, "seo_title": "Acme | Widgets", "focus_kw": "widgets",
      "featured_image": "https://.../hero.jpg",
      "taxonomies": { "category": ["News"] } }
  ],
  "theme_parts": [ { "file": "header.json", "part": "header", "condition": "include/general" } ],
  "fragments":   [ { "file": "hero.json", "title": "Acme Hero" } ],
  "menus":       [ { "name": "Primary", "location": "primary", "items": [ { "slug": "home", "label": "Home" } ] } ]
}
```

New fields: `global_kit` (Elementor colors/fonts file), `replace` (find/replace
rules), and per-page `featured_image`, `taxonomies`, `meta`, `excerpt`,
`content`. Everything from v2 still works unchanged.

---

## Template-kit import (v3.5.0)

RK Migrate auto-detects third-party Elementor **template kits** in two formats:
**marketplace Template Kits** (Envato / Creativemox — `manifest.json` with a
`templates[]` list) and Elementor's **native Website Kit** export (Tools →
Import/Export Kit — `content/` + `templates/` documents). Just upload the kit zip
on the Import tab (or store it in the Library) — RK Migrate maps pages, header/footer, global styles, and theme-builder
templates into its own bundle automatically, then imports as usual. Pro kits
still require Elementor Pro to render.

## Remote Site Doctor API (v3.3.0)

Beyond push/pull, the Remote API now exposes Site Doctor so a central hub
(e.g. the SiteHub Slate plugin) can audit and fix many sites at once. All are
token-gated under `…/wp-json/rk-migrate/v1/`:
`GET doctor/scan` (summary), `POST doctor/replace-color`, `POST doctor/reclaim-color`,
`POST doctor/set-radius`, `POST doctor/convert`. `GET ping` also reports `doctor: true`.

## Remote API

Enable in **Settings → Remote API**, generate a token, and share it with the
paired site. Endpoints (Bearer auth) under `…/wp-json/rk-migrate/v1/`:
`ping`, `receive` (push a bundle in), `pull` (export this site out).

---

## Security & idempotency

- Pages match by **slug**, templates/fragments by **title** — re-running updates
  in place, never duplicates. Menus are rebuilt cleanly each run.
- JSON is only read from the active bundle directory (no path traversal).
- Remote API is **off by default** and token-gated with `hash_equals`.
- Upload/library/export/snapshot dirs ship with `Options -Indexes`.
- Always back up before the first real run, and tick **snapshot** so you can roll back.

---

## File map

```
rk-migrate.php                         boot + activation + WP-CLI registration
includes/
  class-rk-migrate-settings.php        options, tier gating, roles, webhooks
  class-rk-migrate-history.php         audit log table + rollback snapshots
  class-rk-migrate-replace.php         find/replace + URL-rewrite engine
  class-rk-migrate-media.php           media URL collection + sideload/re-link
  class-rk-migrate-importer.php        import engine (sync + step-based for AJAX)
  class-rk-migrate-exporter.php        full/selective export + manifest generation
  class-rk-migrate-ajax.php            chunked import, export, rollback endpoints
  class-rk-migrate-library.php         bundle library + AES bundle encryption
  class-rk-migrate-remote.php          REST push/pull + shared component sync
  class-rk-migrate-ai.php              AI content swap (pluggable provider)
  class-rk-migrate-cloud.php           cloud storage client (filterable)
  class-rk-migrate-marketplace.php     template catalog + install
  class-rk-migrate-manifest-builder.php  visual manifest editor backend
  class-rk-migrate-cli.php             WP-CLI commands
  class-rk-migrate-admin.php           tabbed admin UI + handlers + JS
```

---

## Going live checklist (per site)

1. Run the import (dry run → real, with snapshot on).
2. Confirm header/footer + menu render; spot-check pages in Elementor.
3. If you didn't use media re-link, replace placeholder images.
4. Remove site-wide noindex (Settings → Reading) and set canonical/home URL.
5. Add 301 redirects for any renamed/removed URLs.
6. Submit the XML sitemap in Google Search Console.
