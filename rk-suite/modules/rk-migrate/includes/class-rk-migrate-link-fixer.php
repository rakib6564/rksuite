<?php
/**
 * RK_Migrate_Link_Fixer — the engine behind the Site Doctor "Links" tab.
 *
 * Locates an Elementor element by id and rewrites its link URL / text directly
 * in the page's Elementor data (permanent), records the change in a lightweight
 * audit table (wp_rk_link_fixes), and offers a the_content safety filter for
 * links that theme/classic rendering (not Elementor) outputs.
 *
 * Exposes a secure REST surface (single + bulk) plus the legacy admin-ajax path.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Link_Fixer {

	const DB      = 'rk_link_fixes';
	const REST_NS = 'rk-migrate/v1';
	const DB_VER  = '1';

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_filter( 'the_content', array( __CLASS__, 'the_content_replacer' ), 20 );
	}

	/* ---------------- table ---------------- */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::DB;
	}

	public static function install_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			page_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			element_id VARCHAR(60) NOT NULL DEFAULT '',
			original_link TEXT NULL,
			new_link TEXT NULL,
			original_text TEXT NULL,
			new_text TEXT NULL,
			applied_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY page_id (page_id),
			KEY page_el (page_id, element_id)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function maybe_install() {
		if ( get_option( 'rk_migrate_linkfix_db' ) !== self::DB_VER ) {
			self::install_table();
			update_option( 'rk_migrate_linkfix_db', self::DB_VER );
		}
	}

	private static function record_fix( $pid, $eid, $olink, $nlink, $otext, $ntext ) {
		global $wpdb;
		if ( get_option( 'rk_migrate_linkfix_db' ) !== self::DB_VER ) { self::maybe_install(); }
		$wpdb->insert( self::table(), array(
			'page_id'       => (int) $pid,
			'element_id'    => substr( (string) $eid, 0, 60 ),
			'original_link' => (string) $olink,
			'new_link'      => (string) $nlink,
			'original_text' => (string) $otext,
			'new_text'      => (string) $ntext,
			'applied_at'    => current_time( 'mysql', true ),
		), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	public static function fixes_for( $pid ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE page_id=%d ORDER BY id DESC", (int) $pid ) );
	}

	/* ---------------- core update (Elementor data) ---------------- */

	/**
	 * Update a single element's link (and optionally text) in a page's Elementor
	 * data. Returns true on success or WP_Error. Records the change.
	 */
	public static function update_element( $pid, $eid, $url, $set_text, $text ) {
		$pid = (int) $pid;
		$eid = (string) $eid;
		if ( ! $pid || '' === $eid ) { return new WP_Error( 'bad', 'Missing element.' ); }

		$raw  = get_post_meta( $pid, '_elementor_data', true );
		$data = $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) ) { return new WP_Error( 'nodata', 'No Elementor data on this page.' ); }

		$orig  = array( 'link' => '', 'text' => '' );
		$found = false;
		RK_Migrate_Scanner::walk( $data, function ( &$el ) use ( $eid, $url, $set_text, $text, &$found, &$orig ) {
			if ( ! isset( $el['id'] ) || $el['id'] !== $eid ) { return; }
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { $el['settings'] = array(); }
			$st =& $el['settings'];
			// capture originals
			if ( isset( $st['url']['url'] ) ) { $orig['link'] = $st['url']['url']; }
			elseif ( isset( $st['link']['url'] ) ) { $orig['link'] = $st['link']['url']; }
			elseif ( isset( $st['link'] ) && is_string( $st['link'] ) ) { $orig['link'] = $st['link']; }
			if ( isset( $st['title_text'] ) ) { $orig['text'] = $st['title_text']; }
			elseif ( isset( $st['text'] ) ) { $orig['text'] = $st['text']; }
			// write new URL
			if ( isset( $st['url'] ) && is_array( $st['url'] ) ) { $st['url']['url'] = $url; }
			elseif ( isset( $st['link'] ) && is_array( $st['link'] ) ) { $st['link']['url'] = $url; }
			elseif ( isset( $st['link'] ) && is_string( $st['link'] ) ) { $st['link'] = $url; }
			else { $st['link'] = array( 'url' => $url ); }
			// write new text
			if ( $set_text ) {
				if ( isset( $st['title_text'] ) ) { $st['title_text'] = $text; }
				else { $st['text'] = $text; }
			}
			unset( $st );
			$found = true;
		} );

		if ( ! $found ) { return new WP_Error( 'notfound', 'Element not found — re-scan and try again.' ); }
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		RK_Migrate_Scanner::clear_cache();
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		}
		self::record_fix( $pid, $eid, $orig['link'], $url, $orig['text'], $set_text ? $text : $orig['text'] );
		return true;
	}

	/* ---------------- the_content safety replacer ---------------- */

	public static function the_content_replacer( $content ) {
		if ( is_admin() || ! is_singular() || '' === trim( (string) $content ) ) { return $content; }
		$pid = get_the_ID();
		if ( ! $pid ) { return $content; }
		$fixes = self::fixes_for( $pid );
		if ( empty( $fixes ) ) { return $content; }
		// Apply the newest fix per element only.
		$seen = array();
		foreach ( $fixes as $fx ) {
			if ( isset( $seen[ $fx->element_id ] ) ) { continue; }
			$seen[ $fx->element_id ] = true;
			if ( $fx->original_link && $fx->new_link && $fx->original_link !== $fx->new_link ) {
				$content = str_replace( 'href="' . $fx->original_link . '"', 'href="' . esc_url( $fx->new_link ) . '"', $content );
			}
			if ( $fx->original_text && $fx->new_text && $fx->original_text !== $fx->new_text ) {
				$content = str_replace( '>' . $fx->original_text . '<', '>' . esc_html( $fx->new_text ) . '<', $content );
			}
		}
		return $content;
	}

	/* ---------------- REST ---------------- */

	public function routes() {
		register_rest_route( self::REST_NS, '/update-link', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_update_link' ),
			'permission_callback' => array( __CLASS__, 'perm' ),
		) );
		register_rest_route( self::REST_NS, '/update-links', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_update_links' ),
			'permission_callback' => array( __CLASS__, 'perm' ),
		) );
	}

	public static function perm() { return current_user_can( 'manage_options' ); }

	public function rest_update_link( $req ) {
		$pid  = (int) $req->get_param( 'pid' );
		$eid  = sanitize_text_field( (string) $req->get_param( 'eid' ) );
		$url  = esc_url_raw( (string) $req->get_param( 'url' ) );
		$has  = null !== $req->get_param( 'text' );
		$text = sanitize_text_field( (string) $req->get_param( 'text' ) );
		$res  = self::update_element( $pid, $eid, $url, $has, $text );
		if ( is_wp_error( $res ) ) { return new WP_REST_Response( array( 'ok' => false, 'message' => $res->get_error_message() ), 400 ); }
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function rest_update_links( $req ) {
		$items = $req->get_param( 'items' );
		if ( ! is_array( $items ) ) { return new WP_REST_Response( array( 'ok' => false, 'message' => 'No items.' ), 400 ); }
		$done = 0; $errors = array();
		foreach ( $items as $it ) {
			$pid  = isset( $it['pid'] ) ? (int) $it['pid'] : 0;
			$eid  = isset( $it['eid'] ) ? sanitize_text_field( (string) $it['eid'] ) : '';
			$url  = isset( $it['url'] ) ? esc_url_raw( (string) $it['url'] ) : '';
			$has  = array_key_exists( 'text', $it );
			$text = $has ? sanitize_text_field( (string) $it['text'] ) : '';
			$res  = self::update_element( $pid, $eid, $url, $has, $text );
			if ( is_wp_error( $res ) ) { $errors[] = $eid . ': ' . $res->get_error_message(); } else { $done++; }
		}
		return new WP_REST_Response( array( 'ok' => true, 'saved' => $done, 'errors' => $errors ), 200 );
	}
}
