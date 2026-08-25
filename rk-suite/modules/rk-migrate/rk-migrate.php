<?php
/**
 * RK Migrate — RK Suite module. Loaded on demand by RK Suite when
 * enabled; not a standalone plugin (no plugin header, no self-registration).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RK_MIGRATE_VERSION', '3.5.4' );
define( 'RK_MIGRATE_FILE', __FILE__ );
define( 'RK_MIGRATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_MIGRATE_URL', plugin_dir_url( __FILE__ ) );
define( 'RK_MIGRATE_BUNDLED_DATA', RK_MIGRATE_DIR . 'data/' );
define( 'RK_MIGRATE_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/rk-migrate/' );
define( 'RK_MIGRATE_LIBRARY_DIR', RK_MIGRATE_UPLOAD_DIR . 'library/' );
define( 'RK_MIGRATE_EXPORT_DIR', RK_MIGRATE_UPLOAD_DIR . 'exports/' );
define( 'RK_MIGRATE_SNAPSHOT_DIR', RK_MIGRATE_UPLOAD_DIR . 'snapshots/' );

// ---- core engine + services ----
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-settings.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-history.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-replace.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-media.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-localize.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-scanner.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-doctor.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-kit.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-importer.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-exporter.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-library.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-ai.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-remote.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-fleet.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-cloud.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-marketplace.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-ajax.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-manifest-builder.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-admin.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-elementor-kit.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-link-fixer.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-live-editor.php';
require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-hub-bridge.php';

function rk_migrate_boot() {
	RK_Migrate_Settings::instance();
	RK_Migrate_History::instance();
	RK_Migrate_Remote::instance();
	RK_Migrate_Fleet::init();
	RK_Migrate_Cloud::instance();
	RK_Migrate_Marketplace::instance();
	RK_Migrate_Ajax::instance();
	new RK_Migrate_Admin();
	RK_Migrate_Elementor_Kit::instance()->hooks();
	RK_Migrate_Link_Fixer::instance()->hooks();
	RK_Migrate_Live_Editor::instance()->hooks();
	RK_Migrate_Hub_Bridge::init();
}
// Keep the Site Doctor scan cache fresh — invalidate when content changes.
function rk_migrate_flush_scan( $post_id = 0 ) {
	if ( $post_id && function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) { return; }
	if ( class_exists( 'RK_Migrate_Scanner' ) ) { RK_Migrate_Scanner::clear_cache(); }
}
add_action( 'save_post', 'rk_migrate_flush_scan' );
add_action( 'deleted_post', 'rk_migrate_flush_scan' );
add_action( 'elementor/document/after_save', function () { rk_migrate_flush_scan(); } );

// ---- WP-CLI ----
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-cli.php';
	WP_CLI::add_command( 'rk-migrate', 'RK_Migrate_CLI' );
}

function rk_migrate_activate() {
	foreach ( array( RK_MIGRATE_UPLOAD_DIR, RK_MIGRATE_LIBRARY_DIR, RK_MIGRATE_EXPORT_DIR, RK_MIGRATE_SNAPSHOT_DIR ) as $dir ) {
		if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }
		$ht = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $ht ) ) { @file_put_contents( $ht, "Options -Indexes\n" ); }
	}
	require_once RK_MIGRATE_DIR . 'includes/class-rk-migrate-history.php';
	RK_Migrate_History::install_table();
	RK_Migrate_Link_Fixer::install_table();
	update_option( 'rk_migrate_linkfix_db', RK_Migrate_Link_Fixer::DB_VER );
	RK_Migrate_Settings::seed_defaults();
}
