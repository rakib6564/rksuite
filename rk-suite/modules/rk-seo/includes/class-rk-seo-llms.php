<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Llms — serves a dynamic /llms.txt: a Markdown map of the site for AI agents
 * and LLM crawlers (site name as H1, description, key pages, recent posts, and
 * a link to the sitemap). Generated from memory, transient-cached, no file
 * written. Follows the llms.txt convention (at least one H1 + Markdown links).
 */
class Llms {

	const QV    = 'rk_seo_llms';
	const CACHE = 'rk_seo_llms_txt';
	const TTL   = 12 * HOUR_IN_SECONDS;

	public function hooks() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_action( 'save_post', array( $this, 'flush' ) );
	}

	public function rewrite() { add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QV . '=1', 'top' ); }
	public function query_vars( $v ) { $v[] = self::QV; return $v; }
	public function flush() { delete_transient( self::CACHE ); }

	public function maybe_render() {
		if ( ! get_query_var( self::QV ) ) { return; }
		$txt = get_transient( self::CACHE );
		if ( false === $txt ) { $txt = $this->build(); set_transient( self::CACHE, $txt, self::TTL ); }
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex', true );
		echo $txt; // plain text
		exit;
	}

	private function build() {
		$name = Helpers::site_name();
		$tag  = get_bloginfo( 'description' );
		$out  = '# ' . $name . "\n\n";
		if ( $tag ) { $out .= '> ' . Helpers::clean( $tag ) . "\n\n"; }
		$out .= 'This is the llms.txt for ' . $name . ' (' . home_url( '/' ) . '), a concise map to help AI assistants and crawlers understand the site.' . "\n\n";

		// Key pages
		$pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 40, 'post_status' => 'publish' ) );
		if ( $pages ) {
			$out .= "## Pages\n\n";
			foreach ( $pages as $p ) {
				$excerpt = $p->post_excerpt ? ': ' . Helpers::truncate( $p->post_excerpt, 100 ) : '';
				$out .= '- [' . $this->md( get_the_title( $p ) ) . '](' . get_permalink( $p ) . ')' . $this->md( $excerpt ) . "\n";
			}
			$out .= "\n";
		}

		// Recent posts
		$posts = get_posts( array( 'posts_per_page' => 20, 'post_status' => 'publish', 'no_found_rows' => true ) );
		if ( $posts ) {
			$out .= "## Recent posts\n\n";
			foreach ( $posts as $p ) {
				$out .= '- [' . $this->md( get_the_title( $p ) ) . '](' . get_permalink( $p ) . ")\n";
			}
			$out .= "\n";
		}

		// Public custom post types (archives)
		foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $pt ) {
			if ( ! $pt->has_archive ) { continue; }
			$link = get_post_type_archive_link( $pt->name );
			if ( $link ) { $out .= '- [' . $this->md( $pt->labels->name ) . ' (archive)](' . $link . ")\n"; }
		}

		$out .= "\n## More\n\n";
		$out .= '- Sitemap: ' . home_url( '/sitemap.xml' ) . "\n";
		$out .= '- Website: ' . home_url( '/' ) . "\n";
		return $out;
	}

	private function md( $t ) { return str_replace( array( '[', ']' ), array( '(', ')' ), (string) $t ); }
}
