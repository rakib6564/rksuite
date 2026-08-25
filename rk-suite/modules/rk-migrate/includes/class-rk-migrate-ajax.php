<?php
/**
 * RK_Migrate_Ajax — chunked import with a real-time progress log (one step per
 * request cycle, so large sites never hit PHP timeouts), plus export, rollback
 * and AI-swap AJAX endpoints.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Ajax {

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {
		add_action( 'wp_ajax_rk_migrate_import_start', array( $this, 'import_start' ) );
		add_action( 'wp_ajax_rk_migrate_import_step',  array( $this, 'import_step' ) );
		add_action( 'wp_ajax_rk_migrate_export',       array( $this, 'export' ) );
		add_action( 'wp_ajax_rk_migrate_rollback',     array( $this, 'rollback' ) );
		add_action( 'wp_ajax_rk_migrate_reclaim_color', array( $this, 'reclaim_color' ) );
		add_action( 'wp_ajax_rk_migrate_reclaim_font',  array( $this, 'reclaim_font' ) );
		add_action( 'wp_ajax_rk_migrate_convert',       array( $this, 'convert' ) );
		add_action( 'wp_ajax_rk_migrate_replace_color', array( $this, 'replace_color' ) );
		add_action( 'wp_ajax_rk_migrate_set_radius',    array( $this, 'set_radius' ) );
		add_action( 'wp_ajax_rk_migrate_linkcheck',     array( $this, 'linkcheck' ) );
		add_action( 'wp_ajax_rk_migrate_edit_link',     array( $this, 'edit_link' ) );
	}

	/* ---------------- Site Doctor ---------------- */
	public function reclaim_color() {
		$this->guard( 'import' );
		$hex = isset( $_POST['hex'] ) ? sanitize_text_field( wp_unslash( $_POST['hex'] ) ) : '';
		$gid = isset( $_POST['global'] ) ? sanitize_text_field( wp_unslash( $_POST['global'] ) ) : '';
		if ( ! $hex || ! $gid ) { wp_send_json_error( array( 'message' => 'Pick a color and a global swatch.' ) ); }
		wp_send_json_success( RK_Migrate_Doctor::reclaim_color( $hex, $gid ) );
	}

	public function reclaim_font() {
		$this->guard( 'import' );
		$fam = isset( $_POST['family'] ) ? sanitize_text_field( wp_unslash( $_POST['family'] ) ) : '';
		$gid = isset( $_POST['global'] ) ? sanitize_text_field( wp_unslash( $_POST['global'] ) ) : '';
		if ( ! $fam || ! $gid ) { wp_send_json_error( array( 'message' => 'Pick a font and a global typography token.' ) ); }
		wp_send_json_success( RK_Migrate_Doctor::reclaim_font( $fam, $gid ) );
	}

	public function convert() {
		$this->guard( 'import' );
		$pid = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$dry = ! empty( $_POST['dry'] );
		if ( ! $pid ) { wp_send_json_error( array( 'message' => 'No post selected.' ) ); }
		wp_send_json_success( RK_Migrate_Doctor::convert_post( $pid, $dry ) );
	}

	/** Update a single link (url + text) inside a page's Elementor data. */
	public function edit_link() {
		$this->guard( 'import' );
		$pid  = isset( $_POST['pid'] ) ? (int) $_POST['pid'] : 0;
		$eid  = isset( $_POST['eid'] ) ? sanitize_text_field( wp_unslash( $_POST['eid'] ) ) : '';
		$url  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$text = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
		if ( ! $pid || '' === $eid ) { wp_send_json_error( array( 'message' => 'Missing element.' ) ); }

		$res = RK_Migrate_Link_Fixer::update_element( $pid, $eid, $url, '' !== $text, $text );
		if ( is_wp_error( $res ) ) { wp_send_json_error( array( 'message' => $res->get_error_message() ) ); }
		wp_send_json_success( array( 'message' => 'Link updated.' ) );
	}

	public function replace_color() {
		$this->guard( 'import' );
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! $from || ! $to ) { wp_send_json_error( array( 'message' => 'Provide both colors.' ) ); }
		wp_send_json_success( RK_Migrate_Doctor::replace_color_value( $from, $to ) );
	}

	public function set_radius() {
		$this->guard( 'import' );
		$px = isset( $_POST['px'] ) ? (int) $_POST['px'] : 0;
		wp_send_json_success( RK_Migrate_Doctor::set_all_radius( $px ) );
	}

	public function linkcheck() {
		$this->guard( 'view_log' );
		$urls   = RK_Migrate_Scanner::link_urls();
		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$batch  = 8;
		$slice  = array_slice( $urls, $offset, $batch );
		$results = array();
		foreach ( $slice as $u ) {
			// Verify TLS by default; a cert failure is a genuinely broken link for
			// real visitors. Filterable for intranet/self-signed edge cases.
			$verify = (bool) apply_filters( 'rk_migrate_linkcheck_sslverify', true, $u );
			$args   = array( 'timeout' => 7, 'redirection' => 3, 'sslverify' => $verify );
			$r = wp_remote_head( $u, $args );
			if ( is_wp_error( $r ) ) {
				$r = wp_remote_get( $u, $args );
			}
			$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			$results[] = array( 'url' => $u, 'code' => $code, 'ok' => ( $code >= 200 && $code < 400 ) );
		}
		wp_send_json_success( array( 'results' => $results, 'next' => $offset + $batch, 'total' => count( $urls ), 'done' => ( $offset + $batch ) >= count( $urls ) ) );
	}

	private function guard( $action ) {
		check_ajax_referer( 'rk_migrate_ajax', 'nonce' );
		if ( ! RK_Migrate_Settings::instance()->current_user_can( $action ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to ' . $action . '.' ) );
		}
	}

	private function active_base() {
		$uploaded = get_option( 'rk_migrate_active_bundle', '' );
		if ( $uploaded && file_exists( trailingslashit( $uploaded ) . 'manifest.json' ) ) { return $uploaded; }
		if ( file_exists( RK_MIGRATE_BUNDLED_DATA . 'manifest.json' ) ) { return RK_MIGRATE_BUNDLED_DATA; }
		return '';
	}

	/** Collect run options + replace rules from the POSTed form. */
	private function read_opts() {
		$opts = array(
			'dry'          => ! empty( $_POST['dry_run'] ),
			'set_front'    => ! empty( $_POST['set_front'] ),
			'assign_parts' => ! empty( $_POST['assign_parts'] ),
			'build_menus'  => ! empty( $_POST['build_menus'] ),
			'media_relink' => ! empty( $_POST['media_relink'] ),
		);
		$rules = array();
		if ( ! empty( $_POST['replace'] ) && is_array( $_POST['replace'] ) ) {
			foreach ( $_POST['replace'] as $r ) {
				$find = isset( $r['find'] ) ? wp_unslash( $r['find'] ) : '';
				if ( '' === $find ) { continue; }
				$rules[] = array(
					'find'    => $find,
					'replace' => isset( $r['replace'] ) ? wp_unslash( $r['replace'] ) : '',
					'regex'   => ! empty( $r['regex'] ),
				);
			}
		}
		if ( ! empty( $_POST['from_url'] ) && ! empty( $_POST['to_url'] ) ) {
			$rules[] = array( 'find' => untrailingslashit( esc_url_raw( wp_unslash( $_POST['from_url'] ) ) ), 'replace' => untrailingslashit( esc_url_raw( wp_unslash( $_POST['to_url'] ) ) ) );
		}
		if ( $rules ) { $opts['replace_rules'] = $rules; }

		// Conflict resolution: global default + per-page overrides.
		$opts['conflict_default'] = isset( $_POST['conflict_default'] ) ? sanitize_key( wp_unslash( $_POST['conflict_default'] ) ) : 'overwrite';
		if ( ! empty( $_POST['conflict'] ) && is_array( $_POST['conflict'] ) ) {
			$map = array();
			foreach ( $_POST['conflict'] as $slug => $policy ) {
				$map[ sanitize_title( $slug ) ] = sanitize_key( $policy );
			}
			$opts['conflict_map'] = $map;
		}
		return $opts;
	}

	public function import_start() {
		$this->guard( 'import' );
		$base = $this->active_base();
		if ( ! $base ) { wp_send_json_error( array( 'message' => 'No active project source.' ) ); }

		$importer = new RK_Migrate_Importer( $base );
		$steps = $importer->build_steps();
		$opts  = $this->read_opts();

		// rollback snapshot before a real run
		$snapshot = '';
		if ( empty( $opts['dry'] ) && ! empty( $_POST['snapshot'] ) ) {
			$ids = $importer->affected_post_ids();
			$snapshot = RK_Migrate_History::instance()->snapshot( $ids, 'Pre-import ' . gmdate( 'Y-m-d H:i' ) );
		}

		$token = 'run-' . wp_generate_password( 8, false, false );
		set_transient( 'rk_migrate_run_' . $token, array(
			'base'  => $base,
			'opts'  => $opts,
			'steps' => $steps,
			'state' => array( 'slug_to_id' => array(), 'front_id' => 0 ),
			'counts'=> array( 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'merged' => 0 ),
			'snapshot' => $snapshot,
			'log'   => array(),
		), HOUR_IN_SECONDS );

		wp_send_json_success( array( 'token' => $token, 'total' => count( $steps ), 'snapshot' => $snapshot ) );
	}

	public function import_step() {
		$this->guard( 'import' );
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$index = isset( $_POST['index'] ) ? (int) $_POST['index'] : 0;
		$run = get_transient( 'rk_migrate_run_' . $token );
		if ( ! $run ) { wp_send_json_error( array( 'message' => 'Run session expired. Start again.' ) ); }

		$steps = $run['steps'];
		if ( $index >= count( $steps ) ) { wp_send_json_success( array( 'done' => true, 'log' => array() ) ); }

		$importer = new RK_Migrate_Importer( $run['base'] );
		$result = $importer->run_step( $steps[ $index ], $run['opts'], $run['state'] );
		$run['state'] = $result['state'];
		$c = $importer->counts();
		foreach ( array( 'created', 'updated', 'errors', 'skipped', 'merged' ) as $k ) { if ( isset( $c[ $k ] ) ) { $run['counts'][ $k ] = ( $run['counts'][ $k ] ?? 0 ) + $c[ $k ]; } }
		$run['log'] = array_merge( $run['log'], $result['lines'] );
		set_transient( 'rk_migrate_run_' . $token, $run, HOUR_IN_SECONDS );

		$done = ( $index + 1 ) >= count( $steps );
		if ( $done ) {
			if ( empty( $run['opts']['dry'] ) ) {
				RK_Migrate_History::instance()->record( array(
					'type' => 'import', 'bundle' => basename( $run['base'] ),
					'created' => $run['counts']['created'], 'updated' => $run['counts']['updated'],
					'errors' => $run['counts']['errors'], 'snapshot' => $run['snapshot'], 'report' => $run['log'],
				) );
				$ev = $run['counts']['errors'] ? 'import_fail' : 'import_done';
				RK_Migrate_Settings::instance()->fire_webhook( $ev, array(
					'bundle' => basename( $run['base'] ), 'created' => $run['counts']['created'],
					'updated' => $run['counts']['updated'], 'errors' => $run['counts']['errors'],
				) );
			}
			delete_transient( 'rk_migrate_run_' . $token );
		}

		wp_send_json_success( array(
			'done'    => $done,
			'label'   => isset( $steps[ $index ]['label'] ) ? $steps[ $index ]['label'] : $steps[ $index ]['kind'],
			'lines'   => $result['lines'],
			'counts'  => $run['counts'],
			'index'   => $index,
		) );
	}

	public function export() {
		$this->guard( 'export' );
		$exporter = new RK_Migrate_Exporter();
		$args = array(
			'project'            => isset( $_POST['project'] ) ? sanitize_text_field( wp_unslash( $_POST['project'] ) ) : get_bloginfo( 'name' ),
			'page_ids'           => array_map( 'intval', (array) ( $_POST['page_ids'] ?? array() ) ),
			'post_ids'           => array_map( 'intval', (array) ( $_POST['post_ids'] ?? array() ) ),
			'cpt_ids'            => array_map( 'intval', (array) ( $_POST['cpt_ids'] ?? array() ) ),
			'template_ids'       => array_map( 'intval', (array) ( $_POST['template_ids'] ?? array() ) ),
			'include_menus'      => ! empty( $_POST['include_menus'] ),
			'include_global_kit' => ! empty( $_POST['include_global_kit'] ),
			'include_media'      => ! empty( $_POST['include_media'] ),
		);
		$path = $exporter->build( $args );
		if ( is_wp_error( $path ) ) { wp_send_json_error( array( 'message' => $path->get_error_message() ) ); }

		// optional encryption
		if ( ! empty( $_POST['encrypt_pw'] ) ) {
			$enc = RK_Migrate_Library::encrypt_file( $path, wp_unslash( $_POST['encrypt_pw'] ) );
			if ( ! is_wp_error( $enc ) ) { @unlink( $path ); $path = $enc; }
		}

		RK_Migrate_History::instance()->record( array( 'type' => 'export', 'bundle' => basename( $path ), 'report' => array( 'Exported ' . basename( $path ) ) ) );
		$url = trailingslashit( RK_MIGRATE_EXPORT_DIR );
		$url = str_replace( wp_normalize_path( WP_CONTENT_DIR ), content_url(), wp_normalize_path( $url ) );
		wp_send_json_success( array( 'file' => basename( $path ), 'url' => $url . basename( $path ) ) );
	}

	public function rollback() {
		$this->guard( 'rollback' );
		$token = isset( $_POST['snapshot'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot'] ) ) : '';
		$post  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$lines = RK_Migrate_History::instance()->restore( $token, $post );
		wp_send_json_success( array( 'lines' => $lines ) );
	}
}
