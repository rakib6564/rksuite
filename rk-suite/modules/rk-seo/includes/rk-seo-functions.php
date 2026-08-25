<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'rk_breadcrumbs' ) ) {
	/** Echo the RK SEO breadcrumb trail in a theme template. */
	function rk_breadcrumbs( $args = array() ) {
		if ( class_exists( '\RK\SEO\Breadcrumbs' ) ) { echo \RK\SEO\Breadcrumbs::render( $args ); }
	}
}
if ( ! function_exists( 'rk_get_breadcrumbs' ) ) {
	/** Return the RK SEO breadcrumb HTML. */
	function rk_get_breadcrumbs( $args = array() ) {
		return class_exists( '\RK\SEO\Breadcrumbs' ) ? \RK\SEO\Breadcrumbs::render( $args ) : '';
	}
}
