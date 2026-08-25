<?php
/**
 * RK_Migrate_Localize — pull remotely-hosted images (e.g. template-kit demo
 * images still served from another domain) into this site's media library and
 * rewrite every reference in post content + Elementor data. This removes slow,
 * uncacheable third-party image loads — a common LCP / Speed-Index killer after
 * importing a template kit.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Localize {

	const EXT = 'jpg|jpeg|png|gif|webp|avif';

	private static function home_host() {
		return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/** Post types worth scanning. */
	private static function post_types() {
		$pts = get_post_types( array( 'public' => true ), 'names' );
		$pts = array_merge( array_values( $pts ), array( 'elementor_library', 'rk_template', 'rk_lib_item' ) );
		return array_values( array_unique( array_filter( $pts, 'post_type_exists' ) ) );
	}

	private static function post_ids() {
		$q = new WP_Query( array(
			'post_type' => self::post_types(), 'post_status' => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 1000, 'fields' => 'ids', 'no_found_rows' => true,
		) );
		return $q->posts;
	}

	/** Find external image URLs across all content. Returns [ url => count ]. */
	public static function scan() {
		$home = self::home_host();
		$found = array();
		foreach ( self::post_ids() as $pid ) {
			$blob = get_post_field( 'post_content', $pid );
			$meta = get_post_meta( $pid, '_elementor_data', true );
			if ( is_string( $meta ) ) { $blob .= ' ' . str_replace( '\\/', '/', $meta ); }
			if ( '' === trim( (string) $blob ) ) { continue; }
			if ( preg_match_all( '#https?://[^"\'\\\\ )]+?\.(?:' . self::EXT . ')#i', $blob, $m ) ) {
				foreach ( $m[0] as $url ) {
					$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
					if ( '' === $host || $host === $home ) { continue; }
					$url = html_entity_decode( $url );
					$found[ $url ] = isset( $found[ $url ] ) ? $found[ $url ] + 1 : 1;
				}
			}
		}
		return $found;
	}

	/** Download up to $limit external images and rewrite references. */
	public static function run( $limit = 15 ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$urls = array_keys( self::scan() );
		$urls = array_slice( $urls, 0, max( 1, (int) $limit ) );
		$map = array();
		$downloaded = 0; $failed = 0;

		foreach ( $urls as $url ) {
			$local = self::sideload( $url );
			if ( $local ) { $map[ $url ] = $local; $downloaded++; }
			else { $failed++; }
		}
		$rewritten = $map ? self::rewrite( $map ) : 0;

		return array(
			'downloaded' => $downloaded,
			'failed'     => $failed,
			'rewritten'  => $rewritten,
			'remaining'  => max( 0, count( self::scan() ) ),
		);
	}

	private static function sideload( $url ) {
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) { return ''; }
		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name ) { $name = 'image-' . wp_generate_password( 6, false ) . '.jpg'; }
		$file = array( 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) { if ( file_exists( $tmp ) ) { @unlink( $tmp ); } return ''; }
		return wp_get_attachment_url( $id );
	}

	/** Replace old→new URLs in content + Elementor data everywhere. */
	private static function rewrite( $map ) {
		$count = 0;
		foreach ( self::post_ids() as $pid ) {
			$content = get_post_field( 'post_content', $pid );
			$data    = get_post_meta( $pid, '_elementor_data', true );
			$c2 = $content; $d2 = is_string( $data ) ? $data : '';
			foreach ( $map as $old => $new ) {
				$c2 = str_replace( $old, $new, $c2 );
				if ( '' !== $d2 ) {
					$d2 = str_replace( $old, $new, $d2 );
					$d2 = str_replace( str_replace( '/', '\\/', $old ), str_replace( '/', '\\/', $new ), $d2 );
				}
			}
			$changed = false;
			if ( $c2 !== $content ) { wp_update_post( array( 'ID' => $pid, 'post_content' => $c2 ) ); $changed = true; }
			if ( '' !== $d2 && $d2 !== $data ) { update_post_meta( $pid, '_elementor_data', wp_slash( $d2 ) ); $changed = true; }
			if ( $changed ) { $count++; }
		}
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		}
		return $count;
	}
}
