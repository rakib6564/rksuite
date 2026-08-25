<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Redirects — smart, mostly automatic redirection + 404 monitoring.
 *  • Slug change → auto 301 from the old URL to the new one.
 *  • Canonical host → redirect to the site's configured www/non-www host.
 *  • Manual rules (admin) supported.
 *  • 404s logged minimally (path + hit count) for later inspection.
 * Two small tables, created via dbDelta with a version guard.
 */
class Redirects {

	const DB_VER = '1';

	public function hooks() {
		add_action( 'post_updated', array( $this, 'watch_slug' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
		add_action( 'template_redirect', array( $this, 'log_404' ), 999 );
	}

	/* ---- tables ---- */

	public static function tables() {
		global $wpdb;
		return array( 'redirects' => $wpdb->prefix . 'rk_seo_redirects', 'notfound' => $wpdb->prefix . 'rk_seo_404' );
	}

	public static function install() {
		if ( get_option( 'rk_seo_db_ver' ) === self::DB_VER ) { return; }
		global $wpdb;
		$t = self::tables();
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$t['redirects']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(255) NOT NULL,
			target TEXT NOT NULL,
			type SMALLINT NOT NULL DEFAULT 301,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY source (source(191))
		) $charset;" );
		dbDelta( "CREATE TABLE {$t['notfound']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url VARCHAR(255) NOT NULL,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 1,
			referer VARCHAR(255) NULL,
			last_seen DATETIME NULL,
			PRIMARY KEY (id),
			KEY url (url(191))
		) $charset;" );
		update_option( 'rk_seo_db_ver', self::DB_VER );
	}

	/* ---- slug change → 301 ---- */

	public function watch_slug( $post_id, $post_after, $post_before ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
		if ( 'publish' !== $post_after->post_status ) { return; }
		if ( $post_before->post_name === $post_after->post_name || '' === $post_before->post_name ) { return; }
		// Old public permalink came from the pre-update object.
		$old = get_permalink( $post_before );
		$new = get_permalink( $post_after );
		if ( ! $old || ! $new || $old === $new ) { return; }
		$this->add_rule( $this->path( $old ), $new );
	}

	/** Add/replace a redirect rule (source is a path, target a full URL). */
	public function add_rule( $source, $target, $type = 301 ) {
		global $wpdb;
		$t = self::tables();
		$source = $this->path( $source );
		if ( '' === $source || '/' === $source ) { return; }
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['redirects']} WHERE source = %s", $source ) );
		if ( $exists ) { $wpdb->update( $t['redirects'], array( 'target' => $target, 'type' => (int) $type ), array( 'id' => $exists ) ); }
		else { $wpdb->insert( $t['redirects'], array( 'source' => $source, 'target' => $target, 'type' => (int) $type, 'hits' => 0, 'created_at' => current_time( 'mysql' ) ) ); }
	}

	public function delete_rule( $id ) {
		global $wpdb; $t = self::tables();
		$wpdb->delete( $t['redirects'], array( 'id' => (int) $id ) );
	}

	/* ---- request handling ---- */

	public function maybe_redirect() {
		// Canonical host (www ↔ non-www) using the site's own configured host.
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$req_host  = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( $home_host && $req_host && $req_host !== strtolower( $home_host ) && ! wp_doing_ajax() ) {
			$dest = set_url_scheme( ( is_ssl() ? 'https://' : 'http://' ) . $home_host . ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ) );
			wp_safe_redirect( $dest, 301 ); exit;
		}

		$path = $this->current_path();
		if ( '' === $path ) { return; }
		global $wpdb; $t = self::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['redirects']} WHERE source = %s LIMIT 1", $path ) );
		if ( ! $row ) { return; }
		// Guard against self-referential loops (target path === source path).
		$target_path = wp_parse_url( $row->target, PHP_URL_PATH );
		if ( $target_path && untrailingslashit( $target_path ) === untrailingslashit( $path ) ) { return; }
		$wpdb->query( $wpdb->prepare( "UPDATE {$t['redirects']} SET hits = hits + 1 WHERE id = %d", $row->id ) );
		// Off-site redirect targets are a deliberate admin feature, so we allow
		// this row's host through wp_safe_redirect's allow-list for this request
		// only — keeping the safe-redirect host validation everywhere else.
		$target = $row->target;
		$host   = wp_parse_url( $target, PHP_URL_HOST );
		if ( $host ) {
			add_filter( 'allowed_redirect_hosts', function ( $hosts ) use ( $host ) {
				$hosts[] = $host;
				return $hosts;
			} );
		}
		wp_safe_redirect( $target, (int) $row->type );
		exit;
	}

	const NF_CAP = 5000; // hard cap on stored 404 rows to stop unbounded bot-driven growth.

	public function log_404() {
		if ( ! is_404() ) { return; }
		// Only log real page requests: GET, not obvious asset/junk probes. This
		// stops bots/scanners hammering the DB with a write per random URL.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) { return; }
		$path = $this->current_path();
		if ( '' === $path || strlen( $path ) > 255 ) { return; }
		if ( preg_match( '/\.(?:php|aspx?|jsp|env|git|sql|bak|css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|zip|gz|tar)$/i', $path ) ) { return; }
		if ( ! apply_filters( 'rk_seo_log_404', true, $path ) ) { return; }

		// Per-URL write throttle: collapse a burst of hits on the same path into
		// one DB write per 5 minutes (object cache; falls back to transient).
		$lock = 'rk404_' . md5( $path );
		if ( false !== wp_cache_get( $lock, 'rk_seo' ) || false !== get_transient( $lock ) ) { return; }
		wp_cache_set( $lock, 1, 'rk_seo', 300 );
		set_transient( $lock, 1, 300 );

		global $wpdb; $t = self::tables();
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$id  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['notfound']} WHERE url = %s", $path ) );
		if ( $id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['notfound']} SET hits = hits + 1, last_seen = %s, referer = %s WHERE id = %d", current_time( 'mysql' ), $ref, $id ) );
			return;
		}
		// New row: enforce the table cap before inserting (evict least-recent).
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['notfound']}" ); // phpcs:ignore WordPress.DB.PreparedSQL -- internal table name.
		if ( $count >= self::NF_CAP ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['notfound']} ORDER BY last_seen ASC LIMIT %d", max( 1, $count - self::NF_CAP + 1 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- internal table name.
		}
		$wpdb->insert( $t['notfound'], array( 'url' => $path, 'hits' => 1, 'referer' => $ref, 'last_seen' => current_time( 'mysql' ) ) );
	}

	/* ---- utils ---- */

	private function current_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return $this->path( $uri );
	}
	private function path( $url ) {
		$p = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $p ) { return ''; }
		$p = '/' . trim( $p, '/' );
		return $p;
	}
}
