# RK Suite 1.16.5 — Security & Quality Audit (fresh pass)

Audited the full v1.16.4 codebase (139 PHP files, 9 modules) with a PHP parser (phply)
+ pattern analysis. Result: **clean.** One minor i18n fix applied → repackaged as 1.16.5.

## Checked & PASSED
- **Syntax:** 139/139 files parse (7 "failures" were PHP 7.4/8 syntax the old parser can't read:
  `: void` return types, `??`, typed reference params — all valid).
- **SQL injection:** every `$wpdb` call uses an internal identifier (`{$wpdb->posts}`, `self::table()`),
  an `esc_sql`'d IN-list, an int-cast `LIMIT`, or `$wpdb->prepare()` with `%s`. No user input concatenated.
- **XSS / output escaping:** widget render methods escape everything — `esc_url()` for links/images,
  `esc_html()` for text, `esc_attr()` for attributes; two-color-heading `$tag` is allowlisted to
  h1–h4/div/span. Admin renderers concatenate only fixed SVG icons + escaped helper output.
- **Auth:** 22 `wp_ajax_` handlers vs 74 nonce checks + 110 `current_user_can()` checks. RK API REST
  endpoints (incl. MCP) all use a real `permission_callback => auth` — none `__return_true`.
- **Timing-safe secrets:** API-key comparison uses `hash_equals()`.
- **Direct-access guards:** `if ( ! defined('ABSPATH') ) { exit; }` present in **all** files
  (whole-file scan: 0 missing).
- **Dangerous funcs:** no `eval`/`create_function`/`assert`/`extract`/`unserialize` of untrusted input.
- **PHP 8 compat:** no removed curly-brace `$x{..}` access, no deprecated `${var}` interpolation,
  no `each()`/`create_function()`/`mysql_*`.
- **Prior hardening intact:** SSRF/private-IP guard on sideload, `wp_safe_redirect` + allowed-hosts,
  `is_uploaded_file()`, self-hosted licensing, per-widget conditional asset loading — all present.

## Fixed in 1.16.5
- **i18n text-domain consistency:** 5 translation calls in `rk-visual` used the `'rk-visual'` domain
  while the plugin's declared Text Domain is `'rk-suite'`. Corrected to `'rk-suite'` (asset *handles*
  named `rk-visual` were intentionally left unchanged).

## NOT covered by this static pass (needs the live WordPress admin)
- **Runtime UI issues** (layout/CSS/JS behaviour in the admin screens, responsive glitches, broken
  interactions) can't be validated from source alone — they need the plugin running in wp-admin with a
  browser. The Chrome connection was unavailable during this pass. Recommend a live UI walkthrough of
  each module screen (RK Core, Migrate, SEO, Theme, Library, Forms, Elements, Visual, API) to catch any
  visual/interaction bugs, then fix against real evidence.
