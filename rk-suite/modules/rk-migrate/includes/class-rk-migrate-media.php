<?php
/**
 * RK_Migrate_Media — media export collection + import-time sideload & re-link.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Media {

	/** Find every image/file URL referenced inside a decoded Elementor JSON. */
	public static function collect_urls( $data ) {
		$json = wp_json_encode( $data );
		$urls = array();
		if ( preg_match_all( '#https?:\\\\?/\\\\?/[^"\\\\\s]+\.(?:jpe?g|png|gif|svg|webp|avif|mp4|webm|pdf)#i', $json, $m ) ) {
			foreach ( $m[0] as $u ) {
				$u = str_replace( '\/', '/', $u );
				$urls[ $u ] = $u;
			}
		}
		return array_values( $urls );
	}

	/** Filter a URL list down to those hosted on this site's uploads. */
	public static function local_only( array $urls ) {
		$base = wp_get_upload_dir();
		$baseurl = $base['baseurl'];
		return array_values( array_filter( $urls, function ( $u ) use ( $baseurl ) {
			return false !== strpos( $u, $baseurl );
		} ) );
	}

	/**
	 * Sideload a remote URL into this site's media library (idempotent by
	 * source-url meta). Returns the new attachment URL or '' on failure.
	 */
	public static function sideload( $url ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) { return ''; }
		$existing = self::find_by_source( $url );
		if ( $existing ) { return wp_get_attachment_url( $existing ); }

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! self::url_host_allowed( $url ) ) { return ''; }
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) { return ''; }
		$file = array( 'name' => basename( parse_url( $url, PHP_URL_PATH ) ), 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) { @unlink( $tmp ); return ''; }
		update_post_meta( $id, '_rk_migrate_source_url', $url );
		return wp_get_attachment_url( $id );
	}

	/** Block sideloading from private / loopback / link-local hosts (SSRF guard). */
	private static function url_host_allowed( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) { return false; }
		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) { return false; }
		}
		return (bool) apply_filters( 'rk_migrate_sideload_host_allowed', true, $host, $url );
	}

	private static function find_by_source( $url ) {
		$q = get_posts( array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_rk_migrate_source_url',
			'meta_value'  => $url,
		) );
		return $q ? (int) $q[0] : 0;
	}
}
