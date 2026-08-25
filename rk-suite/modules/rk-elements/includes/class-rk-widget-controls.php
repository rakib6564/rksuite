<?php
/**
 * RK_Widget_Controls — shared control helpers so every RK widget offers a
 * consistent query builder, typography and spacing set. Keeps widgets DRY.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Widget_Controls {

	/** Public post types as an Elementor select options map. */
	public static function post_type_choices() {
		$out = array();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $types as $slug => $obj ) {
			if ( 'attachment' === $slug ) { continue; }
			$out[ $slug ] = $obj->labels->singular_name;
		}
		if ( empty( $out ) ) { $out['post'] = 'Post'; }
		return $out;
	}

	/** Registered WordPress nav menus as an Elementor select options map. */
	public static function nav_menu_choices() {
		$out = array();
		if ( function_exists( 'wp_get_nav_menus' ) ) {
			foreach ( wp_get_nav_menus() as $m ) { $out[ $m->term_id ] = $m->name; }
		}
		if ( empty( $out ) ) { $out[0] = '— Create a menu in Appearance → Menus —'; }
		return $out;
	}

	/**
	 * Build a nested tree of a nav menu's items:
	 * [ { item, children:[ { item, children:[] } ] } ] — top level only nested one deep+.
	 */
	public static function nav_menu_tree( $menu_id ) {
		$menu_id = (int) $menu_id;
		if ( ! $menu_id || ! function_exists( 'wp_get_nav_menu_items' ) ) { return array(); }
		$items = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
		if ( ! $items ) { return array(); }
		$by_parent = array();
		foreach ( $items as $it ) { $by_parent[ (int) $it->menu_item_parent ][] = $it; }
		$build = function ( $parent ) use ( &$build, $by_parent ) {
			$out = array();
			if ( empty( $by_parent[ $parent ] ) ) { return $out; }
			foreach ( $by_parent[ $parent ] as $it ) {
				$out[] = array( 'item' => $it, 'children' => $build( (int) $it->ID ) );
			}
			return $out;
		};
		return $build( 0 );
	}

	/** Taxonomies attached to a post type as options. */
	public static function taxonomy_choices( $post_type = 'post' ) {
		$out = array();
		$taxes = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxes as $slug => $obj ) {
			if ( ! $obj->public ) { continue; }
			$out[ $slug ] = $obj->labels->singular_name;
		}
		return $out;
	}

	/** Build a WP_Query args array from common layout-widget settings. */
	public static function query_args( $settings ) {
		$pt  = isset( $settings['rk_post_type'] ) && $settings['rk_post_type'] ? $settings['rk_post_type'] : 'post';
		$num = isset( $settings['rk_posts_per_page'] ) ? (int) $settings['rk_posts_per_page'] : 6;
		if ( $num <= 0 ) { $num = 6; }
		$orderby = isset( $settings['rk_orderby'] ) ? $settings['rk_orderby'] : 'date';
		$order   = isset( $settings['rk_order'] ) ? $settings['rk_order'] : 'DESC';

		$args = array(
			'post_type'      => $pt,
			'posts_per_page' => $num,
			'orderby'        => $orderby,
			'order'          => $order,
			'post_status'    => 'publish',
			'ignore_sticky_posts' => true,
		);
		if ( ! empty( $settings['rk_term'] ) && ! empty( $settings['rk_taxonomy'] ) ) {
			$args['tax_query'] = array( array(
				'taxonomy' => sanitize_key( $settings['rk_taxonomy'] ),
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', (array) $settings['rk_term'] ),
			) );
		}
		return $args;
	}
}
