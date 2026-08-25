<?php
/**
 * RK_Migrate_Fleet — extended remote control endpoints for a hub (SiteHub).
 * Registered on the same namespaces + Bearer auth as RK_Migrate_Remote:
 * module management, health/maintenance, backups & rollback, and SEO audit.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Fleet {

	const MAINT_OPTION = 'rk_migrate_maintenance';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_maintenance' ), 0 );
	}

	public static function routes() {
		if ( ! class_exists( 'RK_Migrate_Remote' ) ) { return; }
		$auth = array( RK_Migrate_Remote::instance(), 'auth' );
		$get  = function ( $ns, $path, $cb ) use ( $auth ) { register_rest_route( $ns, $path, array( 'methods' => 'GET',  'callback' => $cb, 'permission_callback' => $auth ) ); };
		$post = function ( $ns, $path, $cb ) use ( $auth ) { register_rest_route( $ns, $path, array( 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => $auth ) ); };

		foreach ( RK_Migrate_Remote::namespaces() as $ns ) {
			// Module management
			$get(  $ns, '/modules',          array( __CLASS__, 'modules_list' ) );
			$post( $ns, '/modules/toggle',   array( __CLASS__, 'modules_toggle' ) );
			// Health & maintenance
			$get(  $ns, '/system',           array( __CLASS__, 'system_report' ) );
			$post( $ns, '/cache/clear',      array( __CLASS__, 'cache_clear' ) );
			$post( $ns, '/maintenance',      array( __CLASS__, 'maintenance' ) );
			$post( $ns, '/flush-permalinks', array( __CLASS__, 'flush_permalinks' ) );
			// Backups & rollback
			$get(  $ns, '/snapshots',        array( __CLASS__, 'snapshots_list' ) );
			$post( $ns, '/rollback',         array( __CLASS__, 'rollback' ) );
			$post( $ns, '/snapshot',         array( __CLASS__, 'snapshot_create' ) );
			// SEO fleet audit
			$get(  $ns, '/seo/health',       array( __CLASS__, 'seo_health' ) );
		}
	}

	/* ---------------- Module management ---------------- */

	public static function modules_list() {
		$out = array();
		if ( class_exists( 'RK_Suite_Modules' ) ) {
			$errors = class_exists( 'RK_Suite' ) ? RK_Suite::errors() : array();
			foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
				$out[] = array(
					'slug'    => $slug,
					'name'    => isset( $def['name'] ) ? $def['name'] : $slug,
					'tier'    => isset( $def['tier'] ) ? $def['tier'] : 'free',
					'enabled' => RK_Suite_Modules::is_enabled( $slug ),
					'error'   => isset( $errors[ $slug ] ) ? $errors[ $slug ] : '',
				);
			}
		}
		return rest_ensure_response( array( 'ok' => true, 'modules' => $out ) );
	}

	public static function modules_toggle( $request ) {
		if ( ! class_exists( 'RK_Suite_Modules' ) ) { return new WP_Error( 'nosuite', 'RK Suite module manager unavailable.', array( 'status' => 400 ) ); }
		$slug   = sanitize_key( (string) $request->get_param( 'slug' ) );
		$enable = (bool) $request->get_param( 'enable' );
		if ( ! RK_Suite_Modules::get( $slug ) ) { return new WP_Error( 'badslug', 'Unknown module.', array( 'status' => 404 ) ); }
		if ( $enable ) {
			if ( class_exists( 'RK_Suite' ) && method_exists( 'RK_Suite', 'activate_module' ) ) { RK_Suite::activate_module( $slug ); }
			RK_Suite_Modules::set_enabled( $slug, true );
		} else {
			RK_Suite_Modules::set_enabled( $slug, false );
		}
		return rest_ensure_response( array( 'ok' => true, 'slug' => $slug, 'enabled' => RK_Suite_Modules::is_enabled( $slug ) ) );
	}

	/* ---------------- Health & maintenance ---------------- */

	public static function system_report() {
		if ( ! function_exists( 'get_plugin_updates' ) ) { require_once ABSPATH . 'wp-admin/includes/update.php'; }
		$plugin_updates = function_exists( 'get_plugin_updates' ) ? count( (array) get_plugin_updates() ) : 0;
		$core = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
		$core_update = ( is_array( $core ) && ! empty( $core[0]->response ) && 'upgrade' === $core[0]->response );
		global $wp_version;
		return rest_ensure_response( array(
			'ok'              => true,
			'site'            => home_url(),
			'name'            => get_bloginfo( 'name' ),
			'rk_suite'        => defined( 'RK_SUITE_VERSION' ) ? RK_SUITE_VERSION : '',
			'wp_version'      => $wp_version,
			'php_version'     => PHP_VERSION,
			'theme'           => wp_get_theme()->get( 'Name' ),
			'multisite'       => is_multisite(),
			'elementor'       => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : ( class_exists( '\Elementor\Plugin' ) ? 'active' : '' ),
			'elementor_pro'   => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '',
			'plugin_updates'  => $plugin_updates,
			'core_update'     => $core_update,
			'memory_limit'    => ini_get( 'memory_limit' ),
			'maintenance'     => (bool) get_option( self::MAINT_OPTION, 0 ),
			'https'           => is_ssl(),
		) );
	}

	public static function cache_clear() {
		$done = array();
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); $done[] = 'elementor'; } catch ( \Throwable $e ) {}
		}
		if ( function_exists( 'wp_cache_flush' ) ) { wp_cache_flush(); $done[] = 'object-cache'; }
		return rest_ensure_response( array( 'ok' => true, 'cleared' => $done ) );
	}

	public static function maintenance( $request ) {
		$on = (bool) $request->get_param( 'on' );
		update_option( self::MAINT_OPTION, $on ? 1 : 0 );
		return rest_ensure_response( array( 'ok' => true, 'maintenance' => $on ) );
	}

	/** Soft maintenance: 503 for anonymous visitors; admins/editors pass. */
	public static function maybe_maintenance() {
		if ( is_admin() || ! get_option( self::MAINT_OPTION, 0 ) ) { return; }
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) { return; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
		status_header( 503 );
		nocache_headers();
		header( 'Retry-After: 3600' );
		wp_die(
			esc_html__( 'This site is undergoing scheduled maintenance. Please check back shortly.', 'default' ),
			esc_html( get_bloginfo( 'name' ) . ' — Maintenance' ),
			array( 'response' => 503 )
		);
	}

	public static function flush_permalinks() {
		flush_rewrite_rules( true );
		return rest_ensure_response( array( 'ok' => true, 'flushed' => true ) );
	}

	/* ---------------- Backups & rollback ---------------- */

	public static function snapshots_list() {
		$h = class_exists( 'RK_Migrate_History' ) ? RK_Migrate_History::instance() : null;
		$list = ( $h && method_exists( $h, 'list_snapshots' ) ) ? $h->list_snapshots() : array();
		return rest_ensure_response( array( 'ok' => true, 'snapshots' => $list ) );
	}

	public static function rollback( $request ) {
		if ( ! class_exists( 'RK_Migrate_History' ) ) { return new WP_Error( 'nohistory', 'History unavailable.', array( 'status' => 400 ) ); }
		$token = sanitize_text_field( (string) $request->get_param( 'snapshot' ) );
		if ( '' === $token ) { return new WP_Error( 'notoken', 'snapshot token required.', array( 'status' => 400 ) ); }
		$lines = RK_Migrate_History::instance()->restore( $token );
		return rest_ensure_response( array( 'ok' => true, 'log' => (array) $lines ) );
	}

	public static function snapshot_create( $request ) {
		if ( ! class_exists( 'RK_Migrate_History' ) || ! class_exists( 'RK_Migrate_Exporter' ) ) {
			return new WP_Error( 'nohistory', 'Snapshot unavailable.', array( 'status' => 400 ) );
		}
		$inv = RK_Migrate_Exporter::inventory();
		$ids = array_merge(
			wp_list_pluck( $inv['pages'], 'id' ),
			wp_list_pluck( $inv['posts'], 'id' ),
			wp_list_pluck( $inv['templates'], 'id' )
		);
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		$label = sanitize_text_field( (string) ( $request->get_param( 'label' ) ? $request->get_param( 'label' ) : 'Hub snapshot' ) );
		$token = RK_Migrate_History::instance()->snapshot( $ids, $label );
		return rest_ensure_response( array( 'ok' => true, 'snapshot' => $token, 'count' => count( $ids ) ) );
	}

	/* ---------------- SEO fleet audit ---------------- */

	public static function seo_health() {
		global $wpdb;
		$seo_active = class_exists( 'RK_Suite_Modules' ) && RK_Suite_Modules::is_enabled( 'rk-seo' );

		$pt = array( 'post', 'page' );
		$in = "'" . implode( "','", array_map( 'esc_sql', $pt ) ) . "'";
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ($in)" );
		$no_title = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_status='publish' AND p.post_type IN ($in) AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_rk_seo_title' AND m.meta_value<>'')" );
		$no_desc  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_status='publish' AND p.post_type IN ($in) AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_rk_seo_desc' AND m.meta_value<>'')" );
		$noindex  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_rk_seo_noindex' AND meta_value='1'" );

		$nf_table = $wpdb->prefix . 'rk_seo_notfound';
		$has_nf   = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nf_table ) ) === $nf_table );
		$notfound = $has_nf ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$nf_table}" ) : 0;

		return rest_ensure_response( array(
			'ok'              => true,
			'seo_active'      => $seo_active,
			'published'       => $total,
			'missing_title'   => $no_title,
			'missing_desc'    => $no_desc,
			'noindex'         => $noindex,
			'notfound_logged' => $notfound,
			'sitemap'         => $seo_active ? home_url( '/sitemap.xml' ) : '',
		) );
	}
}
