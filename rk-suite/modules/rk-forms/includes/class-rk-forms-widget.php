<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Forms_Widget — Elementor "RK Form" widget. Pick a published form and drop
 * it into any layout. Registered only when Elementor is active.
 */
class RK_Forms_Widget {

	public static function category( $mgr ) {
		if ( is_object( $mgr ) && method_exists( $mgr, 'add_category' ) ) {
			$mgr->add_category( 'rk-suite', array( 'title' => 'RK Suite', 'icon' => 'eicon-form-horizontal' ) );
		}
	}

	public static function register( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) ) { return; }
		require_once RK_FORMS_DIR . 'includes/class-rk-forms-widget-el.php';
		try { $widgets_manager->register( new \RK_Forms_Widget_El() ); } catch ( \Throwable $e ) {}
	}
}
