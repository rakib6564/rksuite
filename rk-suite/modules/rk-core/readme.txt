=== RK Core — CPT, Fields & Content Engine ===
Contributors: rakibhasan
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPL-2.0-or-later

Custom post types, taxonomies and field groups — the dynamic-content
foundation of the RK suite.

== Description ==
RK Core lets you build content structures from the WordPress admin:

* Custom post types and taxonomies, defined in the UI and stored as portable
  JSON (exportable via RK Migrate), registered on `init`.
* Custom field groups attached to any post type, with field types: text,
  textarea, WYSIWYG, number, date, select, checkbox, image, gallery, relation
  and repeater. Extend the set with the `rk_core_field_types` filter.
* Each field is stored under its own post-meta key, so values stay queryable
  with meta_query and are exposed to the REST API automatically.

Relations and the visual Query Builder are planned for a later release
(this is the minimal Phase 3 scope). RK Core registers with RK Hub when the
Hub is active, and runs fully standalone otherwise.

== Changelog ==
= 1.0.0 =
* Initial release: CPT builder, taxonomy builder, field engine with 11 field
  types, per-field meta storage and REST exposure.
