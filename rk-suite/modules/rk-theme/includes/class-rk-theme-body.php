<?php
/**
 * RK_Theme_Body — full theme templates (Single / Archive / Search / 404).
 * Serves a matching RK template as the page body via template_include, keeping
 * the theme's (or RK's) header & footer. JetEngine-style Theme Builder.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Body {

	private static $id   = 0;
	private static $type = '';

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
	}

	public static function template_include( $template ) {
		if ( is_admin() || ! class_exists( '\Elementor\Plugin' ) ) { return $template; }
		// Never hijack our own editable CPTs (their Elementor preview needs the real single).
		if ( is_singular( array( RK_Theme_Store::CPT, 'rk_listing' ) ) ) { return $template; }

		$type = self::detect_type();
		if ( ! $type ) { return $template; }
		$id = RK_Theme_Conditions::resolve( $type );
		if ( ! $id ) { return $template; }

		self::$id   = $id;
		self::$type = $type;
		$file = RK_THEME_DIR . 'templates/body.php';
		return file_exists( $file ) ? $file : $template;
	}

	/** Which body-template type the current request needs. */
	public static function detect_type() {
		if ( is_404() )      { return 'error_404'; }
		if ( is_search() )   { return 'search'; }
		if ( is_singular() ) { return 'single'; }
		if ( is_archive() || is_home() || is_post_type_archive() || is_tax() || is_category() || is_tag() || is_author() || is_date() ) { return 'archive'; }
		return '';
	}

	public static function current_type() { return self::$type; }

	public static function render_content() {
		if ( ! self::$id ) { return; }
		try { echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) self::$id, true ); }
		catch ( \Throwable $e ) { rk_suite_log( '[RK Theme] body render failed for #' . self::$id . ': ' . $e->getMessage() ); }
	}
}
