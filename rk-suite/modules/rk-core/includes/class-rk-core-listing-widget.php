<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Core_Listing_Widget — registers the "RK Listing" Elementor widget which
 * loops a listing's source and renders the listing template for each item.
 */
class RK_Core_Listing_Widget {

	public static function register( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) ) { return; }
		require_once RK_CORE_DIR . 'includes/class-rk-core-listing-widget-el.php';
		try { $widgets_manager->register( new \RK_Core_Listing_Widget_El() ); } catch ( \Throwable $e ) {}
	}

	public static function category( $mgr ) {
		if ( is_object( $mgr ) && method_exists( $mgr, 'add_category' ) ) {
			$mgr->add_category( 'rk-suite', array( 'title' => 'RK Suite', 'icon' => 'eicon-posts-grid' ) );
		}
	}
}
