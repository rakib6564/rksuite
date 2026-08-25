<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugin — RK SEO module singleton. Wires every component and owns activation
 * (table install + sitemap rewrite flush).
 */
class Plugin {

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		( new Meta() )->hooks();
		( new Images() )->hooks();
		( new Schema() )->hooks();
		( new Breadcrumbs() )->hooks();
		( new Sitemap() )->hooks();
		( new Llms() )->hooks();
		( new Redirects() )->hooks();
		( new Tools() )->hooks();
		( new Crawl() )->hooks();
		( new Indexables() )->hooks();
		( new Analysis() )->hooks();
		( new Integrations() )->hooks();
		if ( is_admin() ) { ( new Admin() )->hooks(); ( new Metabox() )->hooks(); }

		// Make sure sitemap rewrite rules exist even if activation didn't run
		// (e.g. enabling the module from the suite without a fresh activate).
		add_action( 'init', array( $this, 'ensure_rewrite' ), 20 );
		add_action( 'init', array( 'RK\\SEO\\Redirects', 'install' ) );
		add_action( 'init', array( $this, 'ensure_indexables' ), 21 );
	}

	public function ensure_rewrite() {
		if ( get_option( 'rk_seo_rewrite_ready' ) === RK_SEO_VERSION ) { return; }
		( new Sitemap() )->rewrite();
		( new Llms() )->rewrite();
		flush_rewrite_rules( false );
		update_option( 'rk_seo_rewrite_ready', RK_SEO_VERSION );
	}

	public function ensure_indexables() {
		if ( get_option( 'rk_seo_ix_ready' ) === RK_SEO_VERSION ) { return; }
		Indexables::install();
		update_option( 'rk_seo_ix_ready', RK_SEO_VERSION );
	}

	public static function activate() {
		Redirects::install();
		Indexables::install();
		( new Sitemap() )->rewrite();
		( new Llms() )->rewrite();
		flush_rewrite_rules( false );
		update_option( 'rk_seo_rewrite_ready', RK_SEO_VERSION );
	}
}
