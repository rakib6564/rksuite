<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Crawl — crawl-optimization / head-cleanup controls (Yoast "Crawl settings" /
 * Rank Math "Remove …"). Each toggle strips a bit of default WordPress output
 * that adds no SEO value and clutters the crawl surface.
 *
 * @package RK_SEO
 */
class Crawl {

	const OPTION = 'rk_seo_crawl';

	/** key => [label, description]. */
	public static function options() {
		return array(
			'generator'   => array( 'Remove WordPress generator tag', 'Hides the <meta name="generator"> version tag.' ),
			'emojis'      => array( 'Remove emoji scripts', 'Removes the wp-emoji inline script/style and DNS-prefetch.' ),
			'oembed'      => array( 'Remove oEmbed discovery', 'Drops the oEmbed <link> discovery tags and the wp-embed script.' ),
			'rsd'         => array( 'Remove RSD / WLW links', 'Removes the Really Simple Discovery and Windows Live Writer manifest links.' ),
			'shortlink'   => array( 'Remove shortlink', 'Removes the <link rel="shortlink"> tag and HTTP header.' ),
			'rest_header' => array( 'Remove REST API Link header', 'Removes the <link rel="https://api.w.org/"> discovery + Link: header.' ),
			'adjacent'    => array( 'Remove adjacent post links', 'Removes rel="prev"/"next" for the previous/next single post.' ),
			'feed_links'  => array( 'Remove global feed links', 'Removes the site/comment RSS feed <link> tags from the head.' ),
			'pingback'    => array( 'Remove X-Pingback header', 'Drops the X-Pingback HTTP header.' ),
		);
	}

	public static function all() {
		$o = get_option( self::OPTION, array() );
		$d = array();
		foreach ( array_keys( self::options() ) as $k ) { $d[ $k ] = 0; } // default: nothing stripped.
		return array_merge( $d, is_array( $o ) ? $o : array() );
	}

	private static function on( $key ) {
		$o = self::all();
		return ! empty( $o[ $key ] );
	}

	public function hooks() {
		add_action( 'init', array( $this, 'apply' ), 5 );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
	}

	public function apply() {
		if ( self::on( 'generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( self::on( 'emojis' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if ( self::on( 'oembed' ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_filter( 'embed_oembed_discover', '__return_false' );
		}
		if ( self::on( 'rsd' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}
		if ( self::on( 'shortlink' ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
		if ( self::on( 'rest_header' ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}
		if ( self::on( 'adjacent' ) ) {
			remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
		}
		if ( self::on( 'feed_links' ) ) {
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	/** Strip selected HTTP headers. */
	public function headers( $headers ) {
		if ( self::on( 'pingback' ) && isset( $headers['X-Pingback'] ) ) { unset( $headers['X-Pingback'] ); }
		return $headers;
	}
}
