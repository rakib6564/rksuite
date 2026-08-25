<?php
/**
 * RK_Theme_Conditions — RK's own display-condition system (independent of
 * Elementor Pro's Theme Builder). Evaluates which header/footer template
 * applies to the current request, using a simple specificity ranking.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Conditions {

	/** Human-readable list of available conditions for the admin UI. */
	public static function catalog() {
		$list = array(
			'entire_site' => 'Entire site',
			'front_page'  => 'Front page',
			'blog'        => 'Blog / posts page',
			'search'      => 'Search results',
			'error_404'   => '404 page',
			'archive'     => 'Any archive',
		);
		foreach ( self::public_types() as $slug => $label ) {
			$list[ 'singular:' . $slug ] = 'Single: ' . $label;
			$list[ 'archive:' . $slug ]  = 'Archive: ' . $label;
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tx ) {
			if ( in_array( $tx->name, array( 'post_format' ), true ) ) { continue; }
			$list[ 'tax:' . $tx->name ] = 'Taxonomy: ' . ( $tx->labels->singular_name ? $tx->labels->singular_name : $tx->name );
		}
		return $list;
	}

	private static function public_types() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, array( 'attachment' ), true ) ) { continue; }
			$out[ $pt->name ] = $pt->labels->singular_name ? $pt->labels->singular_name : $pt->name;
		}
		return $out;
	}

	/** Does a single condition string match the current request? */
	public static function matches( $cond ) {
		$cond = (string) $cond;
		if ( 'entire_site' === $cond ) { return true; }
		if ( 'front_page' === $cond ) { return is_front_page(); }
		if ( 'blog' === $cond )       { return is_home(); }
		if ( 'search' === $cond )     { return is_search(); }
		if ( 'error_404' === $cond )  { return is_404(); }
		if ( 'archive' === $cond )    { return is_archive() || is_home(); }
		if ( 0 === strpos( $cond, 'singular:' ) ) {
			$pt = substr( $cond, 9 );
			return is_singular( $pt );
		}
		if ( 0 === strpos( $cond, 'archive:' ) ) {
			$pt = substr( $cond, 8 );
			return is_post_type_archive( $pt ) || ( is_tax() && self::tax_belongs( $pt ) ) || ( 'post' === $pt && ( is_category() || is_tag() || is_date() || is_author() ) );
		}
		if ( 0 === strpos( $cond, 'tax:' ) ) {
			$tx = substr( $cond, 4 );
			return is_tax( $tx ) || ( 'category' === $tx && is_category() ) || ( 'post_tag' === $tx && is_tag() );
		}
		return false;
	}

	private static function tax_belongs( $pt ) {
		$obj = get_queried_object();
		if ( ! $obj || empty( $obj->taxonomy ) ) { return false; }
		$tax = get_taxonomy( $obj->taxonomy );
		return $tax && in_array( $pt, (array) $tax->object_type, true );
	}

	/** Specificity score — higher wins when multiple templates match. */
	public static function score( $cond ) {
		if ( 'entire_site' === $cond ) { return 1; }
		if ( 'archive' === $cond )     { return 2; }
		if ( in_array( $cond, array( 'front_page', 'blog', 'search', 'error_404' ), true ) ) { return 5; }
		if ( 0 === strpos( $cond, 'tax:' ) )      { return 6; }
		if ( 0 === strpos( $cond, 'archive:' ) )  { return 6; }
		if ( 0 === strpos( $cond, 'singular:' ) ) { return 7; }
		return 0;
	}

	/**
	 * Best-matching template id of a given type for the current request,
	 * or 0 if none applies.
	 */
	public static function resolve( $type ) {
		$best = 0; $best_score = -1;
		foreach ( RK_Theme_Store::all( $type ) as $p ) {
			if ( 'publish' !== $p->post_status ) { continue; }
			foreach ( RK_Theme_Store::conditions_of( $p->ID ) as $cond ) {
				if ( self::matches( $cond ) ) {
					$s = self::score( $cond );
					if ( $s > $best_score ) { $best_score = $s; $best = $p->ID; }
				}
			}
		}
		return $best;
	}
}
