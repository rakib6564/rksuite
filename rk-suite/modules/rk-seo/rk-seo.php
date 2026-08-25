<?php
/**
 * RK SEO — RK Suite module. Zero-config, auto-pilot SEO: meta tags, JSON-LD
 * schema, dynamic XML sitemaps, breadcrumbs, smart redirects & 404 monitoring.
 * Loaded on demand by RK Suite when enabled.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists( '\RK\SEO\Plugin' ) ) { return; }

define( 'RK_SEO_VERSION', '1.6.0' );
define( 'RK_SEO_FILE', __FILE__ );
define( 'RK_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once RK_SEO_DIR . 'includes/class-rk-seo-helpers.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-variables.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-search-appearance.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-crawl.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-indexables.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-meta.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-images.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-schema.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-breadcrumbs.php';
require_once RK_SEO_DIR . 'includes/rk-seo-functions.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-sitemap.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-llms.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-redirects.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-analysis.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-integrations.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-metabox.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-importer.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-tools.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo-admin.php';
require_once RK_SEO_DIR . 'includes/class-rk-seo.php';

function rk_seo_boot() { \RK\SEO\Plugin::instance(); }
function rk_seo_activate() { \RK\SEO\Plugin::activate(); }
