<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Sitemap — a fully dynamic XML sitemap served from memory (no files written).
 *   /sitemap.xml            → index of all sub-sitemaps
 *   /sitemap-{posttype}.xml → URLs of that post type (+ image:image children)
 *   /sitemap-tax-{tax}.xml  → term archives of that taxonomy
 *   /sitemap-news.xml       → Google News: posts from the last 48 hours
 * Output is transient-cached and invalidated on save_post. Core's own sitemap
 * is disabled to avoid duplicates.
 */
class Sitemap {

	const QV    = 'rk_seo_sitemap';
	const CACHE = 'rk_seo_sitemap_';
	const TTL   = 6 * HOUR_IN_SECONDS;
	const PER   = 2000; // max URLs per sitemap page before it paginates to -2, -3, …

	public function hooks() {
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_filter( 'robots_txt', array( $this, 'robots' ), 10, 2 );
		add_filter( 'redirect_canonical', array( $this, 'no_canonical' ), 10, 2 );
		add_action( 'save_post', array( $this, 'flush_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
	}

	public function rewrite() {
		add_rewrite_rule( '^sitemap\.xml/?$', 'index.php?' . self::QV . '=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z0-9_-]+)\.xml/?$', 'index.php?' . self::QV . '=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) { $vars[] = self::QV; return $vars; }

	/** Never canonical-redirect a sitemap request (prevents trailing-slash 404s). */
	public function no_canonical( $redirect_url, $requested_url ) {
		$qv = get_query_var( self::QV );
		if ( '' !== $qv && null !== $qv ) { return false; }
		if ( isset( $requested_url ) && preg_match( '#/sitemap(-[a-z0-9_-]+)?\.xml/?($|\?)#i', (string) $requested_url ) ) { return false; }
		return $redirect_url;
	}

	public function robots( $output, $public ) {
		if ( '1' === (string) $public || 1 === $public ) { $output .= "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n"; }
		return $output;
	}

	public function flush_cache() {
		foreach ( $this->post_types() as $pt ) {
			$pages = max( 1, (int) ceil( $this->count_posts( $pt ) / self::PER ) );
			for ( $n = 1; $n <= $pages; $n++ ) { delete_transient( self::CACHE . $pt . ( $n > 1 ? '-' . $n : '' ) ); }
		}
		foreach ( $this->taxonomies() as $tx ) {
			$pages = max( 1, (int) ceil( $this->count_terms( $tx ) / self::PER ) );
			for ( $n = 1; $n <= $pages; $n++ ) { delete_transient( self::CACHE . 'tax-' . $tx . ( $n > 1 ? '-' . $n : '' ) ); }
		}
		delete_transient( self::CACHE . 'index' );
		delete_transient( self::CACHE . 'news' );
	}

	private function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	private function taxonomies() {
		$out = get_taxonomies( array( 'public' => true ), 'names' );
		unset( $out['post_format'] );
		return array_values( $out );
	}

	public function maybe_render() {
		$key = get_query_var( self::QV );
		if ( '' === $key || null === $key ) { return; }

		$xml = $this->build( $key );
		if ( null === $xml ) { status_header( 404 ); return; }

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		echo $xml; // phpcs:ignore — already XML-escaped
		exit;
	}

	private function build( $key ) {
		$cached = get_transient( self::CACHE . $key );
		if ( false !== $cached ) { return $cached; }

		if ( 'index' === $key )      { $xml = $this->index(); }
		elseif ( 'news' === $key )   { $xml = $this->news(); }
		else {
			// Resolve the base key + page number. Exact (unpaginated) keys win
			// first so hyphenated names ending in a number (e.g. "covid-19")
			// are not mistaken for a page number.
			$base = $key; $page = 1;
			$exact_pt  = in_array( $key, $this->post_types(), true );
			$exact_tax = ( 0 === strpos( $key, 'tax-' ) ) && taxonomy_exists( substr( $key, 4 ) );
			if ( ! $exact_pt && ! $exact_tax && preg_match( '/^(.*)-([0-9]+)$/', $key, $m ) ) {
				$base = $m[1]; $page = max( 1, (int) $m[2] );
			}
			if ( 0 === strpos( $base, 'tax-' ) )                       { $xml = $this->taxonomy( substr( $base, 4 ), $page ); }
			elseif ( in_array( $base, $this->post_types(), true ) )    { $xml = $this->post_type( $base, $page ); }
			else { return null; }
		}

		if ( null !== $xml ) { set_transient( self::CACHE . $key, $xml, self::TTL ); }
		return $xml;
	}

	private function head( $extra = '' ) {
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $extra;
	}

	private function index() {
		$maps = array();
		foreach ( $this->post_types() as $pt ) {
			$total = $this->count_posts( $pt );
			if ( ! $total ) { continue; }
			$pages = (int) ceil( $total / self::PER );
			for ( $n = 1; $n <= $pages; $n++ ) {
				$slug = $pt . ( $n > 1 ? '-' . $n : '' );
				$maps[] = home_url( '/sitemap-' . $slug . '.xml' );
			}
		}
		foreach ( $this->taxonomies() as $tx ) {
			$total = $this->count_terms( $tx );
			if ( ! $total ) { continue; }
			$pages = (int) ceil( $total / self::PER );
			for ( $n = 1; $n <= $pages; $n++ ) {
				$slug = 'tax-' . $tx . ( $n > 1 ? '-' . $n : '' );
				$maps[] = home_url( '/sitemap-' . $slug . '.xml' );
			}
		}
		if ( $this->has_recent_news() ) { $maps[] = home_url( '/sitemap-news.xml' ); }

		$out = $this->head( '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" );
		foreach ( $maps as $u ) { $out .= "  <sitemap><loc>" . esc_url( $u ) . "</loc></sitemap>\n"; }
		$out .= '</sitemapindex>';
		return $out;
	}

	private function post_type( $pt, $page = 1 ) {
		$page   = max( 1, (int) $page );
		$total  = $this->count_posts( $pt );
		$offset = ( $page - 1 ) * self::PER;
		if ( 0 === $total || $offset >= $total ) { return null; } // page out of range → 404
		$q = new \WP_Query( array(
			'post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => self::PER, 'offset' => $offset,
			'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true,
			'ignore_sticky_posts' => true, 'update_post_term_cache' => false,
			// exclude pages flagged noindex (per-post SEO) from the sitemap
			'meta_query' => array( 'relation' => 'OR',
				array( 'key' => '_rk_seo_noindex', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_rk_seo_noindex', 'value' => '1', 'compare' => '!=' ),
			),
		) );
		$open = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
		$out  = $this->head( $open );
		foreach ( $q->posts as $p ) {
			$out .= "  <url>\n    <loc>" . esc_url( get_permalink( $p ) ) . "</loc>\n";
			$out .= "    <lastmod>" . esc_html( get_post_modified_time( 'c', true, $p ) ) . "</lastmod>\n";
			foreach ( $this->post_images( $p ) as $img ) {
				$out .= "    <image:image><image:loc>" . esc_url( $img ) . "</image:loc></image:image>\n";
			}
			$out .= "  </url>\n";
		}
		$out .= '</urlset>';
		return $out;
	}

	private function taxonomy( $tx, $page = 1 ) {
		if ( ! taxonomy_exists( $tx ) ) { return null; }
		$page   = max( 1, (int) $page );
		$offset = ( $page - 1 ) * self::PER;
		$terms  = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => true, 'number' => self::PER, 'offset' => $offset ) );
		if ( is_wp_error( $terms ) || ! $terms ) { return null; } // out of range → 404
		$out = $this->head( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" );
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( is_wp_error( $link ) ) { continue; }
			$out .= "  <url>\n    <loc>" . esc_url( $link ) . "</loc>\n  </url>\n";
		}
		$out .= '</urlset>';
		return $out;
	}

	private function news() {
		$q = new \WP_Query( array(
			'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1000,
			'date_query' => array( array( 'after' => '48 hours ago' ) ),
			'no_found_rows' => true, 'ignore_sticky_posts' => true,
		) );
		if ( ! $q->have_posts() ) { return null; }
		$name = Helpers::site_name();
		$lang = substr( get_bloginfo( 'language' ), 0, 2 );
		$out  = $this->head( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n" );
		foreach ( $q->posts as $p ) {
			$out .= "  <url>\n    <loc>" . esc_url( get_permalink( $p ) ) . "</loc>\n";
			$out .= "    <news:news>\n";
			$out .= "      <news:publication><news:name>" . esc_html( $name ) . "</news:name><news:language>" . esc_html( $lang ) . "</news:language></news:publication>\n";
			$out .= "      <news:publication_date>" . esc_html( get_post_time( 'c', true, $p ) ) . "</news:publication_date>\n";
			$out .= "      <news:title>" . esc_html( get_the_title( $p ) ) . "</news:title>\n";
			$out .= "    </news:news>\n  </url>\n";
		}
		$out .= '</urlset>';
		return $out;
	}

	/* ---- helpers ---- */

	private function count_posts( $pt ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p"
			. " LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_rk_seo_noindex'"
			. " WHERE p.post_type = %s AND p.post_status = 'publish'"
			. " AND ( m.meta_value IS NULL OR m.meta_value <> '1' )",
			$pt
		);
		return (int) $wpdb->get_var( $sql );
	}

	private function count_terms( $tx ) {
		$n = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => true, 'fields' => 'count' ) );
		return is_wp_error( $n ) ? 0 : (int) $n;
	}

	private function has_recent_news() {
		$q = new \WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1,
			'date_query' => array( array( 'after' => '48 hours ago' ) ), 'no_found_rows' => true, 'fields' => 'ids' ) );
		return $q->have_posts();
	}

	private function post_images( $p ) {
		$imgs = array();
		if ( has_post_thumbnail( $p ) ) { $u = get_the_post_thumbnail_url( $p, 'full' ); if ( $u ) { $imgs[] = $u; } }
		// inline images from content (cheap regex, capped)
		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $p->post_content, $m ) ) {
			foreach ( array_slice( $m[1], 0, 8 ) as $u ) { $imgs[] = $u; }
		}
		return array_values( array_unique( $imgs ) );
	}
}
