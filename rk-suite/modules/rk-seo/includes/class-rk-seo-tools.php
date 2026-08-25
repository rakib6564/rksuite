<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tools — small utility layer:
 *  • Header/Footer code insertion (Analytics, Pixel, verification tags).
 *  • RSS scraping protection: appends a canonical back-link to feed items.
 * Stored in a single option; output is guarded and printed raw by design.
 */
class Tools {

	const OPTION = 'rk_seo_tools';

	public static function get() {
		$o = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $o ) ? $o : array(), array(
			'head_code' => '', 'footer_code' => '', 'rss_protect' => 1,
		) );
	}

	public function hooks() {
		add_action( 'wp_head', array( $this, 'head_code' ), 99 );
		add_action( 'wp_footer', array( $this, 'footer_code' ), 99 );
		add_filter( 'the_content_feed', array( $this, 'rss_footer' ) );
		add_filter( 'the_excerpt_rss', array( $this, 'rss_footer' ) );
	}

	/*
	 * head_code / footer_code are printed RAW by design — this is the site-wide
	 * "insert headers & footers" feature (analytics, pixels, verification tags,
	 * custom <script>/<meta>). It is intentionally NOT run through wp_kses_post():
	 * doing so would strip exactly the <script>/<meta> the feature exists to add.
	 * The input is manage_options-gated and nonce-checked on save, so only a
	 * trusted admin can set it. Do not "sanitize" these two outputs.
	 */
	public function head_code()  { $o = self::get(); if ( ! empty( $o['head_code'] ) ) { echo "\n" . $o['head_code'] . "\n"; } }   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw by design (see note above).
	public function footer_code(){ $o = self::get(); if ( ! empty( $o['footer_code'] ) ) { echo "\n" . $o['footer_code'] . "\n"; } } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw by design (see note above).

	public function rss_footer( $content ) {
		$o = self::get();
		if ( empty( $o['rss_protect'] ) || ! is_feed() ) { return $content; }
		$link  = esc_url( get_permalink() );
		$title = esc_html( get_the_title() );
		$site  = esc_html( Helpers::site_name() );
		$content .= '<p>The post <a href="' . $link . '">' . $title . '</a> first appeared on <a href="' . esc_url( home_url( '/' ) ) . '">' . $site . '</a>.</p>';
		return $content;
	}
}
