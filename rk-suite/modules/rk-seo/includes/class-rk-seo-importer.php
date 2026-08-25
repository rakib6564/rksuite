<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Importer — migrate per-post SEO data from Yoast SEO or Rank Math into RK SEO
 * override meta, so switching plugins loses nothing. Non-destructive: it never
 * overwrites an RK value you already set, and it leaves the source meta intact.
 */
class Importer {

	/** Source key maps: rk_key => source_meta_key. */
	private static function map( $source ) {
		if ( 'rankmath' === $source ) {
			return array(
				Metabox::T_TITLE => 'rank_math_title',
				Metabox::T_DESC  => 'rank_math_description',
				Metabox::T_CANON => 'rank_math_canonical_url',
				Metabox::T_OGIMG => 'rank_math_facebook_image',
			);
		}
		// Yoast (default)
		return array(
			Metabox::T_TITLE => '_yoast_wpseo_title',
			Metabox::T_DESC  => '_yoast_wpseo_metadesc',
			Metabox::T_CANON => '_yoast_wpseo_canonical',
			Metabox::T_OGIMG => '_yoast_wpseo_opengraph-image',
		);
	}

	/** Detect which sources have data present. */
	public static function available() {
		global $wpdb;
		$out = array();
		$yoast = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc')" );
		$rm    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('rank_math_title','rank_math_description')" );
		if ( $yoast || defined( 'WPSEO_VERSION' ) ) { $out['yoast'] = 'Yoast SEO' . ( $yoast ? " ($yoast fields)" : '' ); }
		if ( $rm || defined( 'RANK_MATH_VERSION' ) ) { $out['rankmath'] = 'Rank Math' . ( $rm ? " ($rm fields)" : '' ); }
		return $out;
	}

	/** Replace SEO template tokens (Yoast %%..%% / Rank Math %..%) per post. */
	private static function detokenize( $text, $post ) {
		if ( '' === $text || false === strpos( $text, '%' ) ) { return $text; }
		$repl = array(
			'%%title%%'    => get_the_title( $post ),
			'%title%'      => get_the_title( $post ),
			'%%sitename%%' => Helpers::site_name(),
			'%sitename%'   => Helpers::site_name(),
			'%%page%%'     => '',
			'%page%'       => '',
			'%%sep%%'      => Helpers::separator(),
			'%sep%'        => Helpers::separator(),
			'%%excerpt%%'  => Helpers::truncate( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 160 ),
		);
		$text = strtr( $text, $repl );
		// Drop any remaining unknown %%tokens%% / %tokens%.
		$text = preg_replace( '/%%?[a-z0-9_\-]+%%?/i', '', $text );
		return Helpers::clean( $text );
	}

	private static function is_noindex( $source, $post_id ) {
		if ( 'rankmath' === $source ) {
			$r = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( is_array( $r ) ) { return in_array( 'noindex', $r, true ); }
			if ( is_string( $r ) ) { return false !== strpos( $r, 'noindex' ); }
			return false;
		}
		return '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
	}

	const BATCH = 200; // posts processed per request when importing.

	/** The source meta keys this import reads (already esc_sql-safe SQL list). */
	private static function key_sql( $source ) {
		$map    = self::map( $source );
		$keys   = array_values( $map );
		$keys[] = ( 'rankmath' === $source ) ? 'rank_math_robots' : '_yoast_wpseo_meta-robots-noindex';
		return "'" . implode( "','", array_map( 'esc_sql', $keys ) ) . "'";
	}

	/** Total distinct posts that have any of the source's meta keys. */
	public static function total( $source ) {
		global $wpdb;
		$in = self::key_sql( $source );
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL -- $in is an esc_sql'd literal list.
	}

	/**
	 * Run one batch of the import. Returns a report array for this slice.
	 * Deterministic ordering (post_id ASC) makes the offset paging safe to
	 * resume across requests.
	 *
	 * @param string $source yoast|rankmath.
	 * @param int    $offset Row offset to start at.
	 * @param int    $batch  Rows to process this call.
	 */
	public static function run( $source, $offset = 0, $batch = self::BATCH ) {
		global $wpdb;
		$map    = self::map( $source );
		$in     = self::key_sql( $source );
		$offset = max( 0, (int) $offset );
		$batch  = max( 1, (int) $batch );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($in) ORDER BY post_id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL -- $in is an esc_sql'd literal list.
			$batch, $offset
		) );
		$posts = 0; $fields = 0;
		foreach ( $ids as $pid ) {
			$pid  = (int) $pid;
			$post = get_post( $pid );
			if ( ! $post ) { continue; }
			$touched = false;
			foreach ( $map as $rk_key => $src_key ) {
				if ( '' !== Metabox::get( $pid, $rk_key ) ) { continue; } // never overwrite existing RK value
				$val = get_post_meta( $pid, $src_key, true );
				if ( is_array( $val ) ) { $val = ''; }
				$val = (string) $val;
				if ( in_array( $rk_key, array( Metabox::T_TITLE, Metabox::T_DESC ), true ) ) { $val = self::detokenize( $val, $post ); }
				if ( '' === trim( $val ) ) { continue; }
				update_post_meta( $pid, $rk_key, $val );
				$fields++; $touched = true;
			}
			if ( '' === Metabox::get( $pid, Metabox::T_NOIDX ) && self::is_noindex( $source, $pid ) ) {
				update_post_meta( $pid, Metabox::T_NOIDX, '1' ); $fields++; $touched = true;
			}
			if ( $touched ) { $posts++; }
		}
		return array( 'posts' => $posts, 'fields' => $fields, 'processed' => count( $ids ) );
	}
}
