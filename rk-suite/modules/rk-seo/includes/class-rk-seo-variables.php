<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Variables — the %%variable%% replacement engine (Yoast/Rank Math style).
 *
 * Resolves tokens like %%title%% %%sep%% %%sitename%% in title/description
 * templates. Context-aware: pass an explicit context (post/term/user) when
 * computing off-query (e.g. building indexables on save), or let it read the
 * current query on the front end.
 *
 * @package RK_SEO
 */
class Variables {

	/**
	 * Replace every %%token%% in a string.
	 *
	 * @param string $tpl     Template containing %%tokens%%.
	 * @param array  $context Optional { post: WP_Post, term: WP_Term, user: WP_User }.
	 * @return string
	 */
	public static function replace( $tpl, $context = array() ) {
		$tpl = (string) $tpl;
		if ( '' === $tpl || false === strpos( $tpl, '%%' ) ) { return $tpl; }

		$out = preg_replace_callback( '/%%([a-z0-9_]+)%%/i', function ( $m ) use ( $context ) {
			return self::value( strtolower( $m[1] ), $context );
		}, $tpl );

		// Tidy leftover separators/spaces from empty variables.
		$sep = self::sep();
		$out = preg_replace( '/\s{2,}/', ' ', (string) $out );
		$q   = preg_quote( $sep, '/' );
		$out = preg_replace( '/^(?:\s*' . $q . '\s*)+/', '', $out );      // leading sep
		$out = preg_replace( '/(?:\s*' . $q . '\s*)+$/', '', $out );      // trailing sep
		$out = preg_replace( '/(?:\s*' . $q . '\s*){2,}/', ' ' . $sep . ' ', $out ); // doubled sep
		return trim( $out );
	}

	private static function sep() {
		return Helpers::separator();
	}

	/** Resolve one token to its value. */
	private static function value( $key, $context ) {
		$post = isset( $context['post'] ) ? $context['post'] : null;
		$term = isset( $context['term'] ) ? $context['term'] : null;
		$user = isset( $context['user'] ) ? $context['user'] : null;

		// Fall back to the current query when no explicit context is given.
		if ( ! $post && ! $term && ! $user ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Post ) { $post = $obj; }
			elseif ( $obj instanceof \WP_Term ) { $term = $obj; }
			elseif ( $obj instanceof \WP_User ) { $user = $obj; }
		}

		switch ( $key ) {
			case 'sitename':
				return Helpers::site_name();
			case 'sitedesc':
				return get_bloginfo( 'description' );
			case 'sep':
				return self::sep();
			case 'currentyear':
				return gmdate( 'Y' );
			case 'currentmonth':
				return gmdate( 'F' );
			case 'currentday':
				return gmdate( 'j' );
			case 'currentdate':
				return gmdate( get_option( 'date_format' ) );
			case 'searchphrase':
				return get_search_query();
			case 'title':
				if ( $post ) { return get_the_title( $post ); }
				if ( $term ) { return $term->name; }
				if ( $user ) { return $user->display_name; }
				return '';
			case 'excerpt':
			case 'excerpt_only':
				if ( ! $post ) { return ''; }
				if ( ! empty( $post->post_excerpt ) ) { return Helpers::truncate( $post->post_excerpt, 160 ); }
				return ( 'excerpt' === $key ) ? Helpers::truncate( $post->post_content, 160 ) : '';
			case 'date':
				return $post ? get_the_date( '', $post ) : '';
			case 'modified':
				return $post ? get_the_modified_date( '', $post ) : '';
			case 'id':
				return $post ? (string) $post->ID : '';
			case 'name':
			case 'author':
				if ( $user ) { return $user->display_name; }
				if ( $post ) { return get_the_author_meta( 'display_name', $post->post_author ); }
				return '';
			case 'category':
			case 'primary_category':
				return $post ? self::primary_category( $post ) : ( $term ? $term->name : '' );
			case 'category_title':
			case 'term_title':
				return $term ? $term->name : '';
			case 'term_description':
				return $term ? Helpers::truncate( term_description( $term ), 160 ) : '';
			case 'tag':
				return $post ? self::first_term_name( $post, 'post_tag' ) : '';
			case 'pt_single':
				return self::pt_label( $post, false );
			case 'pt_plural':
				return self::pt_label( $post, true );
			case 'page':
				$p = self::paged();
				return $p > 1 ? (string) $p : '';
			case 'pagenumber':
				return (string) self::paged();
			case 'pagetotal':
				global $wp_query;
				return (string) max( 1, (int) ( isset( $wp_query->max_num_pages ) ? $wp_query->max_num_pages : 1 ) );
		}

		// Unknown token: let integrations resolve it, else drop it.
		return (string) apply_filters( 'rk_seo_variable', '', $key, $context );
	}

	private static function paged() {
		$p = (int) get_query_var( 'paged' );
		if ( ! $p ) { $p = (int) get_query_var( 'page' ); }
		return $p ? $p : 1;
	}

	private static function primary_category( $post ) {
		$primary = (int) get_post_meta( $post->ID, '_rk_seo_primary_category', true );
		if ( $primary ) { $t = get_term( $primary ); if ( $t && ! is_wp_error( $t ) ) { return $t->name; } }
		return self::first_term_name( $post, 'category' );
	}

	private static function first_term_name( $post, $tax ) {
		$terms = get_the_terms( $post, $tax );
		if ( $terms && ! is_wp_error( $terms ) ) { $t = reset( $terms ); return $t->name; }
		return '';
	}

	private static function pt_label( $post, $plural ) {
		if ( ! $post ) { return ''; }
		$obj = get_post_type_object( get_post_type( $post ) );
		if ( ! $obj ) { return ''; }
		return $plural ? $obj->labels->name : $obj->labels->singular_name;
	}

	/** Tokens shown in the admin help, label => token. */
	public static function help_list() {
		return array(
			'Title'            => '%%title%%',
			'Site name'        => '%%sitename%%',
			'Separator'        => '%%sep%%',
			'Tagline'          => '%%sitedesc%%',
			'Excerpt'          => '%%excerpt%%',
			'Primary category' => '%%category%%',
			'Post type (sing.)'=> '%%pt_single%%',
			'Post type (plur.)'=> '%%pt_plural%%',
			'Author'           => '%%name%%',
			'Date'             => '%%date%%',
			'Page number'      => '%%page%%',
			'Search phrase'    => '%%searchphrase%%',
			'Current year'     => '%%currentyear%%',
		);
	}
}
