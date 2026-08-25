<?php
/**
 * RK_Migrate_History — persistent import/export audit log + rollback snapshots.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_History {

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rk_migrate_log';
	}

	public static function install_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			run_at DATETIME NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			type VARCHAR(20) NOT NULL DEFAULT 'import',
			bundle VARCHAR(191) NOT NULL DEFAULT '',
			created INT NOT NULL DEFAULT 0,
			updated INT NOT NULL DEFAULT 0,
			errors INT NOT NULL DEFAULT 0,
			snapshot VARCHAR(191) NOT NULL DEFAULT '',
			report LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY run_at (run_at)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Record a run. Returns the inserted row id. */
	public function record( $args ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'type' => 'import', 'bundle' => '', 'created' => 0,
			'updated' => 0, 'errors' => 0, 'snapshot' => '', 'report' => array(),
		) );
		$wpdb->insert( self::table(), array(
			'run_at'   => current_time( 'mysql', true ),
			'user_id'  => get_current_user_id(),
			'type'     => substr( $args['type'], 0, 20 ),
			'bundle'   => substr( $args['bundle'], 0, 191 ),
			'created'  => (int) $args['created'],
			'updated'  => (int) $args['updated'],
			'errors'   => (int) $args['errors'],
			'snapshot' => substr( $args['snapshot'], 0, 191 ),
			'report'   => wp_json_encode( $args['report'] ),
		) );
		return (int) $wpdb->insert_id;
	}

	public function recent( $limit = 50 ) {
		global $wpdb;
		$limit = (int) $limit;
		return $wpdb->get_results( "SELECT * FROM " . self::table() . " ORDER BY id DESC LIMIT {$limit}" );
	}

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id=%d", $id ) );
	}

	/* ---------------- snapshots / rollback ---------------- */

	/**
	 * Snapshot the Elementor data + key meta of a set of post IDs before an import.
	 * Returns a snapshot token (folder name) or '' on failure.
	 */
	public function snapshot( array $post_ids, $label = '' ) {
		if ( empty( $post_ids ) ) { return ''; }
		if ( ! file_exists( RK_MIGRATE_SNAPSHOT_DIR ) ) { wp_mkdir_p( RK_MIGRATE_SNAPSHOT_DIR ); }
		$token = 'snap-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
		$dir   = trailingslashit( RK_MIGRATE_SNAPSHOT_DIR ) . $token . '/';
		wp_mkdir_p( $dir );
		$index = array( 'label' => $label, 'time' => gmdate( 'c' ), 'posts' => array() );
		foreach ( array_unique( $post_ids ) as $pid ) {
			$pid = (int) $pid;
			if ( ! $pid ) { continue; }
			$post = get_post( $pid );
			if ( ! $post ) { continue; }
			$snap = array(
				'ID'             => $pid,
				'post_title'     => $post->post_title,
				'post_status'    => $post->post_status,
				'_elementor_data'          => get_post_meta( $pid, '_elementor_data', true ),
				'_elementor_page_settings' => get_post_meta( $pid, '_elementor_page_settings', true ),
				'_elementor_version'       => get_post_meta( $pid, '_elementor_version', true ),
				'_elementor_template_type' => get_post_meta( $pid, '_elementor_template_type', true ),
			);
			file_put_contents( $dir . $pid . '.json', wp_json_encode( $snap ) );
			$index['posts'][] = $pid;
		}
		file_put_contents( $dir . 'index.json', wp_json_encode( $index ) );
		return $token;
	}

	/** Restore one or all posts from a snapshot token. */
	public function restore( $token, $only_post = 0 ) {
		$token = basename( (string) $token );
		$dir   = trailingslashit( RK_MIGRATE_SNAPSHOT_DIR ) . $token . '/';
		if ( ! file_exists( $dir . 'index.json' ) ) { return array( 'ERROR: snapshot not found.' ); }
		$index = json_decode( file_get_contents( $dir . 'index.json' ), true );
		$out   = array();
		$ok = 0; $fail = 0;
		foreach ( (array) $index['posts'] as $pid ) {
			if ( $only_post && (int) $pid !== (int) $only_post ) { continue; }
			$file = $dir . $pid . '.json';
			if ( ! file_exists( $file ) ) { $out[] = "SKIP #{$pid}: snapshot file missing"; $fail++; continue; }
			$s = json_decode( file_get_contents( $file ), true );
			if ( ! $s ) { $out[] = "SKIP #{$pid}: snapshot unreadable"; $fail++; continue; }
			if ( ! get_post( $pid ) ) { $out[] = "SKIP #{$pid}: post no longer exists"; $fail++; continue; }
			if ( ! empty( $s['_elementor_data'] ) ) {
				update_post_meta( $pid, '_elementor_data', wp_slash( $s['_elementor_data'] ) );
			}
			if ( isset( $s['_elementor_page_settings'] ) ) { update_post_meta( $pid, '_elementor_page_settings', $s['_elementor_page_settings'] ); }
			if ( isset( $s['_elementor_version'] ) )       { update_post_meta( $pid, '_elementor_version', $s['_elementor_version'] ); }
			if ( isset( $s['_elementor_template_type'] ) ) { update_post_meta( $pid, '_elementor_template_type', $s['_elementor_template_type'] ); }
			$res = wp_update_post( array( 'ID' => $pid, 'post_title' => isset( $s['post_title'] ) ? $s['post_title'] : '', 'post_status' => isset( $s['post_status'] ) ? $s['post_status'] : 'publish' ), true );
			if ( is_wp_error( $res ) ) {
				$out[] = "ERROR #{$pid}: " . $res->get_error_message();
				$fail++;
				continue;
			}
			$out[] = "Restored #{$pid} ({$s['post_title']})";
			$ok++;
		}
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		}
		$out[] = $fail
			? "Rollback finished with issues: {$ok} restored, {$fail} failed."
			: "Rollback complete: {$ok} restored.";
		return $out;
	}

	/** Posts captured in a snapshot: [ {pid,title} ]. */
	public function snapshot_posts( $token ) {
		$token = basename( (string) $token );
		$dir   = trailingslashit( RK_MIGRATE_SNAPSHOT_DIR ) . $token . '/';
		if ( ! file_exists( $dir . 'index.json' ) ) { return array(); }
		$index = json_decode( file_get_contents( $dir . 'index.json' ), true );
		$out = array();
		foreach ( (array) ( $index['posts'] ?? array() ) as $pid ) {
			$title = '#' . $pid;
			if ( file_exists( $dir . $pid . '.json' ) ) {
				$s = json_decode( file_get_contents( $dir . $pid . '.json' ), true );
				if ( is_array( $s ) && isset( $s['post_title'] ) ) { $title = $s['post_title']; }
			}
			$out[] = array( 'pid' => (int) $pid, 'title' => $title );
		}
		return $out;
	}

	public function list_snapshots() {
		$out = array();
		if ( ! file_exists( RK_MIGRATE_SNAPSHOT_DIR ) ) { return $out; }
		foreach ( glob( trailingslashit( RK_MIGRATE_SNAPSHOT_DIR ) . '*', GLOB_ONLYDIR ) as $dir ) {
			$idx = $dir . '/index.json';
			if ( ! file_exists( $idx ) ) { continue; }
			$i = json_decode( file_get_contents( $idx ), true );
			$out[] = array(
				'token' => basename( $dir ),
				'label' => isset( $i['label'] ) ? $i['label'] : '',
				'time'  => isset( $i['time'] ) ? $i['time'] : '',
				'count' => isset( $i['posts'] ) ? count( $i['posts'] ) : 0,
			);
		}
		usort( $out, function ( $a, $b ) { return strcmp( $b['token'], $a['token'] ); } );
		return $out;
	}
}
