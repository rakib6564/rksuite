<?php
/**
 * RK_Migrate_Remote — site-to-site push/pull over the REST API and shared
 * component sync. Two installs are paired with a shared bearer token; Site A
 * can push a bundle straight into Site B (or pull B's site as a bundle).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Remote {

	const NS = 'rk-migrate/v1';
	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Namespaces the site listens on. `portkit/v1` is a compatibility alias so a
	 * PortKit-era control plane (e.g. SiteHub) can drive RK Migrate unchanged.
	 * Filterable via `rk_migrate_rest_namespaces`.
	 */
	public static function namespaces() {
		return apply_filters( 'rk_migrate_rest_namespaces', array( self::NS, 'portkit/v1' ) );
	}

	public function routes() {
		foreach ( self::namespaces() as $ns ) { $this->register_on( $ns ); }
	}

	/** Register the full route set on one namespace. */
	private function register_on( $ns ) {
		$auth = array( $this, 'auth' );
		register_rest_route( $ns, '/ping', array(
			'methods'  => 'GET', 'callback' => array( $this, 'ping' ), 'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/receive', array(
			'methods'  => 'POST', 'callback' => array( $this, 'receive' ), 'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/pull', array(
			'methods'  => 'POST', 'callback' => array( $this, 'pull' ), 'permission_callback' => $auth,
		) );
		// ---- Site Doctor over REST (for a central hub like SiteHub) ----
		register_rest_route( $ns, '/doctor/scan', array(
			'methods'  => 'GET', 'callback' => array( $this, 'doctor_scan' ), 'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/doctor/reclaim-color', array(
			'methods'  => 'POST', 'callback' => array( $this, 'doctor_reclaim_color' ), 'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/doctor/replace-color', array(
			'methods'  => 'POST', 'callback' => array( $this, 'doctor_replace_color' ), 'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/doctor/set-radius', array(
			'methods'  => 'POST', 'callback' => array( $this, 'doctor_set_radius' ), 'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/doctor/convert', array(
			'methods'  => 'POST', 'callback' => array( $this, 'doctor_convert' ), 'permission_callback' => $auth,
		) );
	}

	/* ---------------- Site Doctor REST callbacks ---------------- */

	/** Compact, hub-friendly scan summary. */
	public function doctor_scan( $request ) {
		if ( $request->get_param( 'fresh' ) ) { RK_Migrate_Scanner::clear_cache(); }
		$scan = RK_Migrate_Scanner::scan();
		$colors = array();
		$i = 0;
		foreach ( $scan['colors'] as $norm => $c ) {
			$colors[] = array(
				'value'    => $norm,
				'count'    => (int) $c['count'],
				'bindable' => isset( $c['bindable'] ) ? (int) $c['bindable'] : 0,
				'css'      => isset( $c['css'] ) ? (int) $c['css'] : 0,
				'pages'    => count( $c['pages'] ),
			);
			if ( ++$i >= 25 ) { break; }
		}
		return rest_ensure_response( array(
			'ok'            => true,
			'site'          => home_url(),
			'name'          => get_bloginfo( 'name' ),
			'version'       => RK_MIGRATE_VERSION,
			'scanned_at'    => $scan['time'],
			'posts'         => (int) $scan['posts'],
			'totals'        => $scan['totals'],
			'legacy_pages'  => (int) $scan['sections']['pages_legacy'],
			'radius_values' => count( $scan['radius'] ),
			'heading_issues'=> count( $scan['headings'] ),
			'colors'        => $colors,
		) );
	}

	public function doctor_reclaim_color( $request ) {
		$hex = (string) $request->get_param( 'hex' );
		$gid = (string) $request->get_param( 'global' );
		if ( '' === $hex || '' === $gid ) { return new WP_Error( 'badreq', 'hex and global required.', array( 'status' => 400 ) ); }
		return rest_ensure_response( RK_Migrate_Doctor::reclaim_color( $hex, $gid ) );
	}

	public function doctor_replace_color( $request ) {
		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );
		if ( '' === $from || '' === $to ) { return new WP_Error( 'badreq', 'from and to required.', array( 'status' => 400 ) ); }
		return rest_ensure_response( RK_Migrate_Doctor::replace_color_value( $from, $to ) );
	}

	public function doctor_set_radius( $request ) {
		$px = (int) $request->get_param( 'px' );
		return rest_ensure_response( RK_Migrate_Doctor::set_all_radius( $px ) );
	}

	public function doctor_convert( $request ) {
		$pid = (int) $request->get_param( 'post_id' );
		$dry = (bool) $request->get_param( 'dry' );
		if ( ! $pid ) { return new WP_Error( 'badreq', 'post_id required.', array( 'status' => 400 ) ); }
		return rest_ensure_response( RK_Migrate_Doctor::convert_post( $pid, $dry ) );
	}

	/** Bearer-token auth against the configured remote token. */
	public function auth( $request ) {
		$s = RK_Migrate_Settings::instance();
		if ( ! $s->get( 'remote_enabled' ) ) { return new WP_Error( 'disabled', 'Remote API disabled.', array( 'status' => 403 ) ); }
		$token = $s->get( 'remote_token' );
		$given = self::presented_token( $request );
		if ( ! $token || ! $given || ! hash_equals( (string) $token, (string) $given ) ) {
			return new WP_Error( 'badtoken', 'Invalid or missing connection token.', array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Read the presented token from every place it can survive a proxy/host:
	 * ?token= param, X-RK-Token header, or Authorization: Bearer (pulled from
	 * WP, then $_SERVER fallbacks for hosts that strip it). This makes auth work
	 * even when the Authorization header is stripped by LiteSpeed/FastCGI or a
	 * security plugin intercepts Bearer auth.
	 */
	public static function presented_token( $request ) {
		$t = $request->get_param( 'token' );
		if ( $t ) { return trim( (string) $t ); }
		$x = $request->get_header( 'x_rk_token' );
		if ( ! $x ) { $x = $request->get_header( 'x-rk-token' ); }
		if ( $x ) { return trim( (string) $x ); }
		$auth = $request->get_header( 'authorization' );
		if ( ! $auth && ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) { $auth = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ); }
		if ( ! $auth && ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) { $auth = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); }
		if ( ! $auth && function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $k => $v ) { if ( 0 === strcasecmp( $k, 'authorization' ) ) { $auth = $v; break; } }
		}
		if ( $auth && stripos( $auth, 'bearer ' ) === 0 ) { return trim( substr( $auth, 7 ) ); }
		return '';
	}

	public function ping() {
		return rest_ensure_response( array(
			'ok' => true, 'site' => home_url(), 'plugin' => RK_Migrate_Settings::instance()->brand_name(),
			'version' => RK_MIGRATE_VERSION, 'elementor' => class_exists( '\Elementor\Plugin' ), 'doctor' => true,
		) );
	}

	/** Receive a pushed bundle (base64 zip) and import it. */
	public function receive( $request ) {
		$b64  = $request->get_param( 'bundle' );
		$opts = (array) $request->get_param( 'opts' );
		if ( ! $b64 ) { return new WP_Error( 'nobundle', 'No bundle provided.', array( 'status' => 400 ) ); }
		// Payload size guard (filterable; default 64 MB decoded).
		$max = (int) apply_filters( 'rk_migrate_max_push_bytes', 64 * 1024 * 1024 );
		if ( strlen( $b64 ) > (int) ( $max * 1.4 ) ) {
			return new WP_Error( 'toolarge', 'Bundle exceeds the size limit.', array( 'status' => 413 ) );
		}
		$zip = base64_decode( $b64, true );
		if ( false === $zip ) { return new WP_Error( 'badb64', 'Corrupt payload.', array( 'status' => 400 ) ); }
		if ( strlen( $zip ) > $max ) {
			return new WP_Error( 'toolarge', 'Bundle exceeds the size limit.', array( 'status' => 413 ) );
		}

		if ( ! file_exists( RK_MIGRATE_UPLOAD_DIR ) ) { wp_mkdir_p( RK_MIGRATE_UPLOAD_DIR ); }
		$tmp = trailingslashit( RK_MIGRATE_UPLOAD_DIR ) . 'push-' . wp_generate_password( 8, false, false ) . '.zip';
		file_put_contents( $tmp, $zip );
		$slug = RK_Migrate_Library::store_zip( $tmp, 'pushed' );
		@unlink( $tmp );
		if ( is_wp_error( $slug ) ) { return $slug; }

		$base = RK_Migrate_Library::path( $slug );
		$importer = new RK_Migrate_Importer( $base );
		$report = $importer->run( wp_parse_args( $opts, array(
			'set_front' => false, 'assign_parts' => true, 'build_menus' => true, 'media_relink' => true,
		) ) );
		$c = $importer->counts();
		RK_Migrate_History::instance()->record( array(
			'type' => 'remote-receive', 'bundle' => $slug, 'created' => $c['created'],
			'updated' => $c['updated'], 'errors' => $c['errors'], 'report' => $report,
		) );
		return rest_ensure_response( array( 'ok' => true, 'counts' => $c, 'report' => $report ) );
	}

	/** Export this site and return a base64 bundle (for the caller to import). */
	public function pull( $request ) {
		if ( ! RK_Migrate_Settings::instance()->get( 'remote_allow_pull' ) ) {
			return new WP_Error( 'nopull', 'Pull disabled on this site.', array( 'status' => 403 ) );
		}
		$inv = RK_Migrate_Exporter::inventory();
		$exporter = new RK_Migrate_Exporter();
		$path = $exporter->build( array(
			'project'  => get_bloginfo( 'name' ),
			'page_ids' => wp_list_pluck( $inv['pages'], 'id' ),
			'post_ids' => wp_list_pluck( $inv['posts'], 'id' ),
			'cpt_ids'  => wp_list_pluck( $inv['cpts'], 'id' ),
			'template_ids' => wp_list_pluck( $inv['templates'], 'id' ),
			'include_menus' => true, 'include_global_kit' => true,
			'include_media' => (bool) $request->get_param( 'include_media' ),
		) );
		if ( is_wp_error( $path ) ) { return $path; }
		$b64 = base64_encode( file_get_contents( $path ) );
		return rest_ensure_response( array( 'ok' => true, 'file' => basename( $path ), 'bundle' => $b64 ) );
	}

	/* ---------------- outbound (this site is Site A) ---------------- */

	/** Push a local bundle zip to a remote RK Migrate site. */
	public static function push_to( $remote_url, $token, $zip_path, $opts = array() ) {
		$resp = wp_remote_post( trailingslashit( $remote_url ) . 'wp-json/' . self::NS . '/receive', array(
			'timeout' => 120,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'bundle' => base64_encode( file_get_contents( $zip_path ) ), 'opts' => $opts ) ),
		) );
		if ( is_wp_error( $resp ) ) { return $resp; }
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( wp_remote_retrieve_response_code( $resp ) >= 300 ) {
			return new WP_Error( 'remote', isset( $body['message'] ) ? $body['message'] : 'Remote error.' );
		}
		return $body;
	}

	/** Pull a remote site down as a bundle zip; returns local path. */
	public static function pull_from( $remote_url, $token, $include_media = false ) {
		$resp = wp_remote_post( trailingslashit( $remote_url ) . 'wp-json/' . self::NS . '/pull', array(
			'timeout' => 180,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'include_media' => $include_media ) ),
		) );
		if ( is_wp_error( $resp ) ) { return $resp; }
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['bundle'] ) ) { return new WP_Error( 'remote', isset( $body['message'] ) ? $body['message'] : 'Remote error.' ); }
		if ( ! file_exists( RK_MIGRATE_EXPORT_DIR ) ) { wp_mkdir_p( RK_MIGRATE_EXPORT_DIR ); }
		$path = trailingslashit( RK_MIGRATE_EXPORT_DIR ) . ( isset( $body['file'] ) ? sanitize_file_name( $body['file'] ) : 'pulled-' . gmdate( 'Ymd-His' ) . '.zip' );
		file_put_contents( $path, base64_decode( $body['bundle'] ) );
		return $path;
	}

	/**
	 * Shared component sync: push a single template (header/footer/section) by id
	 * to a list of paired sites. Builds a one-template bundle and pushes it.
	 */
	public static function sync_component( $template_id, array $targets ) {
		$exporter = new RK_Migrate_Exporter();
		$path = $exporter->build( array(
			'project' => 'Shared Component', 'template_ids' => array( (int) $template_id ),
			'include_menus' => false, 'include_global_kit' => false,
		) );
		if ( is_wp_error( $path ) ) { return $path; }
		$results = array();
		foreach ( $targets as $t ) {
			$results[ $t['url'] ] = self::push_to( $t['url'], $t['token'], $path, array( 'assign_parts' => true, 'build_menus' => false ) );
		}
		return $results;
	}
}
