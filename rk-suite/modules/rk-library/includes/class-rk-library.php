<?php
/**
 * RK_Library — module singleton. Registers the store CPT, wires the editor
 * integration and admin, and owns activation (seed categories + starter set).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Library {

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'init', array( 'RK_Library_Store', 'register' ) );
		( new RK_Library_Editor() )->hooks();
		if ( is_admin() ) { ( new RK_Library_Admin() )->hooks(); }
	}

	public static function activate() {
		RK_Library_Store::register();
		if ( ! get_option( 'rk_library_categories' ) ) { update_option( 'rk_library_categories', RK_Library_Store::default_categories() ); }
		// Seed a starter set once, so the editor library isn't empty on day one.
		if ( ! get_option( 'rk_library_seeded' ) ) {
			$b = RK_Library_Porter::example_bundle();
			if ( ! empty( $b['items'] ) ) { RK_Library_Porter::import( $b ); }
			update_option( 'rk_library_seeded', 1 );
		}
	}
}
