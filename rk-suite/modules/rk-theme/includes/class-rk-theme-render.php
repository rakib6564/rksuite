<?php
/**
 * RK_Theme_Render — standalone display of the matched header/footer, without
 * relying on Elementor Pro's Theme Builder. On Hello Elementor (and themes
 * that respect the same filter) it suppresses the theme's own header/footer;
 * everywhere it injects the RK templates at wp_body_open (header) and
 * wp_footer (footer). All Elementor calls are guarded.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Render {

	private static $header_id = 0;
	private static $footer_id = 0;
	private static $behavior  = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'resolve' ), 5 );
	}

	/** Decide once per request which templates apply, then wire the hooks. */
	public static function resolve() {
		if ( is_admin() ) { return; }
		if ( ! class_exists( 'RK_Theme_Store' ) ) { return; }
		// Never wrap a template's own single/preview view (Elementor editor
		// preview, or direct visit) with another header/footer.
		if ( is_singular( RK_Theme_Store::CPT ) ) { return; }
		if ( isset( $_GET['elementor-preview'] ) ) { return; }

		self::$header_id = RK_Theme_Conditions::resolve( 'header' );
		self::$footer_id = RK_Theme_Conditions::resolve( 'footer' );

		if ( ! self::$header_id && ! self::$footer_id ) { return; }

		// Hello Elementor (and compatible themes) expose this filter to turn
		// off their built-in header + footer. Suppress only what we replace.
		if ( self::$header_id || self::$footer_id ) {
			add_filter( 'hello_elementor_header_footer', '__return_false' );
		}

		if ( self::$header_id ) {
			add_action( 'wp_body_open', array( __CLASS__, 'render_header' ), 1 );
			$b = RK_Theme_Store::behavior_of( self::$header_id );
			if ( ! empty( $b['sticky'] ) || ! empty( $b['transparent'] ) ) {
				self::$behavior = $b;
				// Enqueue here (template_redirect → before wp_head) so there is no FOUC.
				wp_enqueue_style( 'rk-theme-header', RK_THEME_URL . 'assets/header.css', array(), RK_THEME_VERSION );
				wp_enqueue_script( 'rk-theme-header', RK_THEME_URL . 'assets/header.js', array(), RK_THEME_VERSION, true );
			}
		}
		if ( self::$footer_id ) { add_action( 'wp_footer', array( __CLASS__, 'render_footer' ), 5 ); }
	}

	public static function render_header() {
		$b = self::$behavior;
		$class = 'rk-theme-header rk-theme-part';
		$attr  = '';
		$style = '';
		if ( $b ) {
			if ( ! empty( $b['sticky'] ) )      { $class .= ' rk-sticky'; }
			if ( ! empty( $b['transparent'] ) ) { $class .= ' rk-transparent'; }
			if ( ! empty( $b['shadow'] ) )      { $class .= ' rk-has-shadow'; }
			$attr  = ' data-rk-behavior data-offset="' . (int) $b['offset'] . '" data-shrink="' . ( ! empty( $b['shrink'] ) ? '1' : '0' ) . '"';
			$style = ' style="--rk-stuck-bg:' . esc_attr( $b['stuck_bg'] ) . ';--rk-logo-shrink:' . (int) $b['logo_shrink'] . 'px;"';
		}
		echo '<div class="' . esc_attr( $class ) . '" data-rk-template="' . (int) self::$header_id . '"' . $attr . $style . '>';
		self::render( self::$header_id );
		echo '</div>';
	}

	public static function render_footer() {
		echo '<div class="rk-theme-footer rk-theme-part" data-rk-template="' . (int) self::$footer_id . '">';
		self::render( self::$footer_id );
		echo '</div>';
	}

	/** Output a template's Elementor content (with CSS), guarded + fallback. */
	private static function render( $id ) {
		$id = (int) $id;
		if ( ! $id ) { return; }
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, true );
				return;
			} catch ( \Throwable $e ) {
				rk_suite_log( '[RK Theme] render failed for #' . $id . ': ' . $e->getMessage() );
			}
		}
		// Fallback: raw content through the_content (no Elementor active).
		$post = get_post( $id );
		if ( $post ) { echo apply_filters( 'the_content', $post->post_content ); }
	}
}
