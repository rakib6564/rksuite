<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Helpers — shared, side-effect-free utilities. Everything here is cheap and
 * derives from the already-loaded main query object (no extra DB hits).
 */
class Helpers {

	/** True when a competing SEO plugin is active — we then stand down. */
	public static function foreign_seo_active() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
	}

	/** Should RK SEO emit head output on this request? */
	public static function should_run() {
		if ( is_admin() || is_feed() || is_embed() ) { return false; }
		if ( self::foreign_seo_active() ) { return false; }
		return true;
	}

	/** Clean a string into a meta-safe, single-line snippet. */
	public static function clean( $text ) {
		$text = wp_strip_all_tags( (string) $text, true );
		$text = preg_replace( '/\[[^\]]*\]/', '', $text ); // strip shortcodes
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/** Trim to ~N chars on a word boundary. */
	public static function truncate( $text, $len = 160 ) {
		$text = self::clean( $text );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $len : strlen( $text ) <= $len ) { return $text; }
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $len ) : substr( $text, 0, $len );
		$sp  = strrpos( $cut, ' ' );
		if ( $sp > 40 ) { $cut = substr( $cut, 0, $sp ); }
		return rtrim( $cut, " ,.;:-" ) . '…';
	}

	public static function site_name() { return get_bloginfo( 'name' ); }
	public static function separator()  {
		$sep = '-';
		if ( class_exists( '\RK\SEO\Search_Appearance' ) ) { $sep = Search_Appearance::sep(); }
		return apply_filters( 'rk_seo_title_sep', $sep );
	}

	/** Best default social image: site custom logo, then site icon. */
	public static function default_image() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) { $u = wp_get_attachment_image_url( $logo_id, 'full' ); if ( $u ) { return $u; } }
		$icon = get_site_icon_url( 512 );
		return $icon ? $icon : '';
	}

	/** Organization vs Person site representation (auto, zero-config). */
	public static function site_entity() {
		// If exactly one author has published, treat the site as a Person brand;
		// otherwise an Organization. Cached for the request.
		static $cache = null;
		if ( null !== $cache ) { return $cache; }
		$type = 'Organization';
		$cache = array(
			'type' => $type,
			'name' => self::site_name(),
			'url'  => home_url( '/' ),
			'logo' => self::default_image(),
		);
		return $cache;
	}

	/** Absolute, canonical URL of the current request. */
	public static function current_url() {
		if ( is_singular() ) { return get_permalink(); }
		if ( is_tax() || is_category() || is_tag() ) { $l = get_term_link( get_queried_object() ); return is_wp_error( $l ) ? home_url( add_query_arg( array() ) ) : $l; }
		if ( is_post_type_archive() ) { return get_post_type_archive_link( get_post_type() ); }
		if ( is_author() ) { return get_author_posts_url( get_queried_object_id() ); }
		if ( is_front_page() || is_home() ) { return home_url( '/' ); }
		return home_url( add_query_arg( array() ) );
	}
}
