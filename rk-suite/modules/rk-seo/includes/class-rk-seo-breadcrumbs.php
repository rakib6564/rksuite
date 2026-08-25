<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Breadcrumbs — a lightweight, schema-ready trail built from the current
 * context. Reusable in three ways: Breadcrumbs::trail() (array, used by Schema),
 * Breadcrumbs::render() (HTML), the [rk_breadcrumbs] shortcode, and the global
 * rk_breadcrumbs() template function.
 */
class Breadcrumbs {

	public function hooks() {
		add_shortcode( 'rk_breadcrumbs', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets() {
		$css = RK_SEO_DIR . 'assets/breadcrumbs.css';
		wp_register_style( 'rk-seo-breadcrumbs', RK_SEO_URL . 'assets/breadcrumbs.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_SEO_VERSION );
	}

	/** Ordered trail: [ ['name'=>, 'url'=>], ... ]. Home first, current last. */
	public static function trail() {
		$items = array();
		$home  = array( 'name' => apply_filters( 'rk_seo_breadcrumb_home', __( 'Home' ) ), 'url' => home_url( '/' ) );
		$items[] = $home;

		if ( is_front_page() ) { return array( $home ); }

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( is_page() && $post->post_parent ) {
				$parents = array();
				$pid = $post->post_parent;
				while ( $pid ) { $p = get_post( $pid ); if ( ! $p ) { break; } $parents[] = array( 'name' => get_the_title( $p ), 'url' => get_permalink( $p ) ); $pid = $p->post_parent; }
				$items = array_merge( $items, array_reverse( $parents ) );
			} elseif ( is_singular( 'post' ) ) {
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$primary = $cats[0];
					$anc = array_reverse( get_ancestors( $primary->term_id, 'category' ) );
					foreach ( $anc as $tid ) { $t = get_term( $tid, 'category' ); if ( $t && ! is_wp_error( $t ) ) { $items[] = array( 'name' => $t->name, 'url' => get_term_link( $t ) ); } }
					$items[] = array( 'name' => $primary->name, 'url' => get_term_link( $primary ) );
				}
			} else {
				$pt = get_post_type_object( get_post_type( $post ) );
				if ( $pt && $pt->has_archive ) { $items[] = array( 'name' => $pt->labels->name, 'url' => get_post_type_archive_link( $pt->name ) ); }
			}
			$items[] = array( 'name' => get_the_title( $post ), 'url' => '' );
			return $items;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->taxonomy ) ) {
				$anc = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
				foreach ( $anc as $tid ) { $t = get_term( $tid, $term->taxonomy ); if ( $t && ! is_wp_error( $t ) ) { $items[] = array( 'name' => $t->name, 'url' => get_term_link( $t ) ); } }
				$items[] = array( 'name' => $term->name, 'url' => '' );
			}
			return $items;
		}

		if ( is_post_type_archive() ) { $items[] = array( 'name' => post_type_archive_title( '', false ), 'url' => '' ); return $items; }
		if ( is_author() )  { $a = get_queried_object(); $items[] = array( 'name' => $a ? $a->display_name : '', 'url' => '' ); return $items; }
		if ( is_search() )  { $items[] = array( 'name' => sprintf( __( 'Search: %s' ), get_search_query() ), 'url' => '' ); return $items; }
		if ( is_404() )     { $items[] = array( 'name' => __( 'Page not found' ), 'url' => '' ); return $items; }
		if ( is_year() )    { $items[] = array( 'name' => get_the_date( 'Y' ), 'url' => '' ); return $items; }
		if ( is_month() )   { $items[] = array( 'name' => get_the_date( 'F Y' ), 'url' => '' ); return $items; }
		if ( is_day() )     { $items[] = array( 'name' => get_the_date(), 'url' => '' ); return $items; }

		return $items;
	}

	/** HTML output (microdata-friendly; JSON-LD is emitted separately). */
	public static function render( $args = array() ) {
		$items = self::trail();
		if ( count( $items ) < 2 ) { return ''; }
		wp_enqueue_style( 'rk-seo-breadcrumbs' );
		$sep = isset( $args['separator'] ) ? $args['separator'] : apply_filters( 'rk_seo_breadcrumb_sep', '/' );

		$out  = '<nav class="rk-breadcrumbs" aria-label="Breadcrumb"><ol>';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $it ) {
			$out .= '<li class="rk-bc-item">';
			if ( $i < $last && ! empty( $it['url'] ) ) {
				$out .= '<a href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['name'] ) . '</a>';
			} else {
				$out .= '<span aria-current="page">' . esc_html( $it['name'] ) . '</span>';
			}
			if ( $i < $last ) { $out .= '<span class="rk-bc-sep" aria-hidden="true">' . esc_html( $sep ) . '</span>'; }
			$out .= '</li>';
		}
		$out .= '</ol></nav>';
		return $out;
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'separator' => '/' ), (array) $atts, 'rk_breadcrumbs' );
		return self::render( $atts );
	}
}
