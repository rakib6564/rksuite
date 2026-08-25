<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Search_Appearance — per-content-type title/description templates, noindex
 * toggles and the title separator (Yoast "Search Appearance" / Rank Math
 * "Titles & Meta"). Templates use %%variables%% resolved by Variables.
 *
 * @package RK_SEO
 */
class Search_Appearance {

	const OPTION = 'rk_seo_appearance';

	/** Available separators: value => display glyph. */
	public static function separators() {
		return array( '-' => '-', '–' => '–', '—' => '—', '·' => '·', '•' => '•', '|' => '|', '~' => '~', '»' => '»', '<' => '‹', '>' => '›' );
	}

	public static function all() {
		$o = get_option( self::OPTION, array() );
		return is_array( $o ) ? $o : array();
	}

	public static function sep() {
		$o = self::all();
		$s = isset( $o['sep'] ) ? (string) $o['sep'] : '-';
		return array_key_exists( $s, self::separators() ) ? $s : '-';
	}

	/** Public post types we expose (skip attachment). */
	public static function post_types() {
		$t = get_post_types( array( 'public' => true ), 'objects' );
		unset( $t['attachment'] );
		return $t;
	}

	public static function taxonomies() {
		$t = get_taxonomies( array( 'public' => true ), 'objects' );
		unset( $t['post_format'] );
		return $t;
	}

	/** Default template for a post type. */
	private static function pt_default( $pt ) {
		return array(
			'title'   => '%%title%% %%sep%% %%sitename%%',
			'desc'    => '%%excerpt%%',
			'noindex' => 0,
		);
	}
	private static function tax_default( $tx ) {
		return array(
			'title'   => '%%term_title%% %%sep%% %%sitename%%',
			'desc'    => '%%term_description%%',
			'noindex' => 0,
		);
	}
	private static function archive_defaults() {
		return array(
			'author' => array( 'enabled' => 1, 'noindex' => 0, 'title' => '%%name%% %%sep%% %%sitename%%', 'desc' => '' ),
			'date'   => array( 'enabled' => 1, 'noindex' => 1, 'title' => '%%date%% %%sep%% %%sitename%%', 'desc' => '' ),
			'search' => array( 'title' => 'You searched for %%searchphrase%% %%sep%% %%sitename%%' ),
			'404'    => array( 'title' => 'Page not found %%sep%% %%sitename%%' ),
			'home'   => array( 'title' => '%%sitename%% %%sep%% %%sitedesc%%', 'desc' => '%%sitedesc%%' ),
		);
	}

	public static function pt( $pt ) {
		$o = self::all();
		$d = self::pt_default( $pt );
		return isset( $o['pt'][ $pt ] ) && is_array( $o['pt'][ $pt ] ) ? array_merge( $d, $o['pt'][ $pt ] ) : $d;
	}
	public static function tax( $tx ) {
		$o = self::all();
		$d = self::tax_default( $tx );
		return isset( $o['tax'][ $tx ] ) && is_array( $o['tax'][ $tx ] ) ? array_merge( $d, $o['tax'][ $tx ] ) : $d;
	}
	public static function archive( $key ) {
		$o  = self::all();
		$ds = self::archive_defaults();
		$d  = isset( $ds[ $key ] ) ? $ds[ $key ] : array();
		return isset( $o['archive'][ $key ] ) && is_array( $o['archive'][ $key ] ) ? array_merge( $d, $o['archive'][ $key ] ) : $d;
	}

	/* ---------- resolvers used by the Meta engine (front-end, query-aware) ---------- */

	/** Title template string for the current query context, or '' if none. */
	public static function title_template() {
		if ( is_front_page() )        { return self::archive( 'home' )['title']; }
		if ( is_singular() )          { return self::pt( get_post_type( get_queried_object_id() ) )['title']; }
		if ( is_category() || is_tag() || is_tax() ) { $o = get_queried_object(); return $o ? self::tax( $o->taxonomy )['title'] : ''; }
		if ( is_post_type_archive() ) { return self::pt( self::current_archive_pt() )['title']; }
		if ( is_author() )            { return self::archive( 'author' )['title']; }
		if ( is_date() )              { return self::archive( 'date' )['title']; }
		if ( is_search() )            { return self::archive( 'search' )['title']; }
		if ( is_404() )               { return self::archive( '404' )['title']; }
		return '';
	}

	public static function desc_template() {
		if ( is_front_page() )        { return self::archive( 'home' )['desc']; }
		if ( is_singular() )          { $p = self::pt( get_post_type( get_queried_object_id() ) ); return isset( $p['desc'] ) ? $p['desc'] : ''; }
		if ( is_category() || is_tag() || is_tax() ) { $o = get_queried_object(); return $o ? self::tax( $o->taxonomy )['desc'] : ''; }
		if ( is_author() )            { $a = self::archive( 'author' ); return isset( $a['desc'] ) ? $a['desc'] : ''; }
		return '';
	}

	/** Type/archive-level noindex for the current query (per-post override handled in Meta). */
	public static function noindex() {
		if ( is_singular() )          { return ! empty( self::pt( get_post_type( get_queried_object_id() ) )['noindex'] ); }
		if ( is_category() || is_tag() || is_tax() ) { $o = get_queried_object(); return $o ? ! empty( self::tax( $o->taxonomy )['noindex'] ) : false; }
		if ( is_post_type_archive() ) { return ! empty( self::pt( self::current_archive_pt() )['noindex'] ); }
		if ( is_author() )            { $a = self::archive( 'author' ); return empty( $a['enabled'] ) || ! empty( $a['noindex'] ); }
		if ( is_date() )              { $a = self::archive( 'date' ); return empty( $a['enabled'] ) || ! empty( $a['noindex'] ); }
		return false;
	}

	private static function current_archive_pt() {
		$o = get_queried_object();
		return ( $o && isset( $o->name ) ) ? $o->name : 'post';
	}

	public static function update( $values ) {
		update_option( self::OPTION, $values );
	}
}
