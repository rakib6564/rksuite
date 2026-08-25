<?php
/**
 * RK_Theme — module singleton. Registers the template CPT, wires the
 * standalone renderer, and mounts the admin.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme {

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'init', array( 'RK_Theme_Store', 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
		RK_Theme_Render::init();
		RK_Theme_Body::init();
		RK_Theme_Visibility::init();
		if ( is_admin() ) {
			$admin = new RK_Theme_Admin();
			$admin->hooks();
		}
	}

	/** One-time: ensure existing templates render on Elementor's canvas. */
	public static function maybe_migrate() {
		if ( get_option( 'rk_theme_migrated' ) === RK_THEME_VERSION ) { return; }
		foreach ( RK_Theme_Store::all() as $p ) {
			if ( 'elementor_canvas' !== get_post_meta( $p->ID, '_wp_page_template', true ) ) {
				update_post_meta( $p->ID, '_wp_page_template', 'elementor_canvas' );
			}
		}
		update_option( 'rk_theme_migrated', RK_THEME_VERSION );
	}

	public static function activate() {
		RK_Theme_Store::register();
		flush_rewrite_rules();
	}
}
