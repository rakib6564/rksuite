<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Meta — auto title, description, robots, and social (Open Graph / Twitter /
 * LinkedIn) tags. All values are derived from the current context; nothing to
 * configure. Runs only when Helpers::should_run() passes.
 */
class Meta {

	public function hooks() {
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
		add_action( 'wp_head', array( $this, 'head' ), 1 );
		remove_action( 'wp_head', 'rel_canonical' ); // we emit our own canonical
		// Let WordPress core keep the <title> tag support.
		add_theme_support( 'title-tag' );
	}

	/* ---------------- title ---------------- */

	public function title( $title ) {
		if ( ! Helpers::should_run() ) { return $title; }
		if ( is_singular() ) {
			$id = get_queried_object_id();
			if ( class_exists( '\RK\SEO\Indexables' ) ) {
				$ix = Indexables::for_post( $id );
				if ( is_array( $ix ) && '' !== (string) $ix['title'] ) { return Helpers::clean( $ix['title'] ); }
			}
			$ov = Metabox::get( $id, Metabox::T_TITLE );
			if ( '' !== $ov ) { return Helpers::clean( Variables::replace( $ov ) ); }
		}
		// Search Appearance template (with %%variables%%) as the default.
		if ( class_exists( '\RK\SEO\Search_Appearance' ) ) {
			$tpl = Search_Appearance::title_template();
			if ( '' !== $tpl ) {
				$out = Helpers::clean( Variables::replace( $tpl ) );
				if ( '' !== $out ) { return $out; }
			}
		}
		$t = $this->raw_title();
		$name = Helpers::site_name();
		$sep  = Helpers::separator();
		if ( is_front_page() ) {
			$tagline = get_bloginfo( 'description' );
			return $tagline ? "$name $sep $tagline" : $name;
		}
		return $t ? "$t $sep $name" : $name;
	}

	private function raw_title() {
		if ( is_singular() )        { return get_the_title( get_queried_object_id() ); }
		if ( is_category() || is_tag() || is_tax() ) { return single_term_title( '', false ); }
		if ( is_post_type_archive() ) { return post_type_archive_title( '', false ); }
		if ( is_author() )          { $a = get_queried_object(); return $a ? $a->display_name : ''; }
		if ( is_search() )          { return sprintf( 'Search results for “%s”', get_search_query() ); }
		if ( is_year() )            { return get_the_date( 'Y' ); }
		if ( is_month() )           { return get_the_date( 'F Y' ); }
		if ( is_day() )             { return get_the_date(); }
		if ( is_404() )             { return 'Page not found'; }
		return wp_get_document_title();
	}

	/* ---------------- description ---------------- */

	public function description() {
		if ( is_singular() ) {
			$id = get_queried_object_id();
			if ( class_exists( '\RK\SEO\Indexables' ) ) {
				$ix = Indexables::for_post( $id );
				if ( is_array( $ix ) && '' !== (string) $ix['description'] ) { return Helpers::truncate( $ix['description'], 160 ); }
			}
			$ov = Metabox::get( $id, Metabox::T_DESC );
			if ( '' !== $ov ) { return Helpers::truncate( Variables::replace( $ov ), 160 ); }
			$post = get_queried_object();
			if ( $post && ! empty( $post->post_excerpt ) ) { return Helpers::truncate( $post->post_excerpt, 160 ); }
			if ( $post ) { return Helpers::truncate( $post->post_content, 160 ); }
		}
		if ( class_exists( '\RK\SEO\Search_Appearance' ) && ! is_singular() ) {
			$tpl = Search_Appearance::desc_template();
			if ( '' !== $tpl ) {
				$out = Helpers::truncate( Variables::replace( $tpl ), 160 );
				if ( '' !== $out ) { return $out; }
			}
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$d = term_description();
			if ( $d ) { return Helpers::truncate( $d, 160 ); }
			$o = get_queried_object();
			return $o ? Helpers::truncate( $o->name . ' — ' . Helpers::site_name(), 160 ) : '';
		}
		if ( is_front_page() ) {
			$d = get_bloginfo( 'description' );
			return $d ? Helpers::clean( $d ) : '';
		}
		if ( is_author() ) { $a = get_queried_object(); $b = $a ? get_the_author_meta( 'description', $a->ID ) : ''; return $b ? Helpers::truncate( $b, 160 ) : ''; }
		return '';
	}

	/* ---------------- robots ---------------- */

	public function robots() {
		$noindex = false;
		if ( is_404() || is_search() ) { $noindex = true; }
		if ( is_paged() && ( is_archive() || is_home() ) ) { /* keep index,follow on paged */ }
		if ( ( is_archive() || is_home() ) && ! have_posts() && ! is_paged() ) { $noindex = true; } // empty archive
		if ( is_singular() ) {
			$id = get_queried_object_id();
			if ( class_exists( '\RK\SEO\Indexables' ) && ( $ix = Indexables::for_post( $id ) ) && is_array( $ix ) ) {
				if ( ! empty( $ix['is_noindex'] ) ) { $noindex = true; }
			} elseif ( '1' === Metabox::get( $id, Metabox::T_NOIDX ) ) { $noindex = true; }
		}
		if ( class_exists( '\RK\SEO\Search_Appearance' ) && Search_Appearance::noindex() ) { $noindex = true; }
		if ( '0' === get_option( 'blog_public' ) ) { $noindex = true; }
		$val = $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
		return apply_filters( 'rk_seo_robots', $val );
	}

	/* ---------------- head output ---------------- */

	public function head() {
		if ( ! Helpers::should_run() ) { return; }
		$desc  = Helpers::clean( $this->description() );
		$url   = Helpers::current_url();
		if ( is_singular() ) {
			$id = get_queried_object_id();
			$cov = Metabox::get( $id, Metabox::T_CANON );
			if ( '' !== $cov ) { $url = $cov; }
			elseif ( class_exists( '\RK\SEO\Indexables' ) && ( $ix = Indexables::for_post( $id ) ) && is_array( $ix ) && '' !== (string) $ix['canonical'] ) { $url = $ix['canonical']; }
		}
		$title = $this->title( '' );
		$image = $this->image();

		echo "\n<!-- RK SEO -->\n";
		echo '<meta name="robots" content="' . esc_attr( $this->robots() ) . '">' . "\n";
		if ( $desc ) { echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n"; }
		if ( $url )  { echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n"; }

		/* Open Graph (Facebook / LinkedIn) */
		// Blog posts are "article"; pages and everything else are "website".
		// Filterable so article-like CPTs can opt in.
		$og_type = is_singular( array( 'post' ) ) ? 'article' : 'website';
		$og_type = apply_filters( 'rk_seo_og_type', $og_type, get_queried_object() );
		echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( Helpers::site_name() ) . '">' . "\n";
		if ( $title ) { echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n"; }
		if ( $desc )  { echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
		if ( $url )   { echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n"; }
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $title ) . '">' . "\n";
		}
		if ( 'article' === $og_type ) {
			$p = get_queried_object();
			if ( $p ) {
				echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $p ) ) . '">' . "\n";
				echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $p ) ) . '">' . "\n";
			}
		}

		/* Twitter Card */
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		if ( $title ) { echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n"; }
		if ( $desc )  { echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
		if ( $image ) { echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n"; }
		echo "<!-- /RK SEO -->\n";
	}

	/** Best social image for the current context. */
	public function image() {
		if ( is_singular() ) {
			$ov = Metabox::get( get_queried_object_id(), Metabox::T_OGIMG );
			if ( '' !== $ov ) { return $ov; }
		}
		if ( is_singular() && has_post_thumbnail() ) {
			$u = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
			if ( $u ) { return $u; }
		}
		return Helpers::default_image();
	}
}
