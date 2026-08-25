<?php
/**
 * RK_Taxonomy_Builder — build custom taxonomies from the admin, stored as JSON,
 * registered on `init` and attached to the chosen post types.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Taxonomy_Builder {

	const OPTION = 'rk_core_taxonomies';

	public static function reserved() {
		return array( 'category', 'post_tag', 'nav_menu', 'link_category', 'post_format' );
	}

	public static function all() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	public static function get( $slug ) {
		$slug = sanitize_key( $slug );
		foreach ( self::all() as $def ) {
			if ( isset( $def['slug'] ) && $def['slug'] === $slug ) { return $def; }
		}
		return null;
	}

	public static function save( $input ) {
		$def = self::sanitize_def( $input );
		if ( is_wp_error( $def ) ) { return $def; }
		$all = self::all();
		$found = false;
		foreach ( $all as $i => $existing ) {
			if ( $existing['slug'] === $def['slug'] ) { $all[ $i ] = $def; $found = true; break; }
		}
		if ( ! $found ) { $all[] = $def; }
		update_option( self::OPTION, array_values( $all ) );
		return $def['slug'];
	}

	public static function delete( $slug ) {
		$slug = sanitize_key( $slug );
		$all = array();
		foreach ( self::all() as $def ) {
			if ( $def['slug'] !== $slug ) { $all[] = $def; }
		}
		update_option( self::OPTION, array_values( $all ) );
	}

	public static function sanitize_def( $in ) {
		$slug = isset( $in['slug'] ) ? sanitize_key( $in['slug'] ) : '';
		if ( '' === $slug ) { return new WP_Error( 'slug', 'A taxonomy key is required.' ); }
		if ( strlen( $slug ) > 32 ) { return new WP_Error( 'slug', 'Taxonomy key must be 32 characters or fewer.' ); }
		if ( in_array( $slug, self::reserved(), true ) ) { return new WP_Error( 'slug', 'That taxonomy key is reserved.' ); }

		$singular = isset( $in['singular'] ) ? sanitize_text_field( $in['singular'] ) : ucfirst( $slug );
		$plural   = isset( $in['plural'] ) ? sanitize_text_field( $in['plural'] ) : ( $singular . 's' );

		$objects = array();
		if ( isset( $in['object_types'] ) && is_array( $in['object_types'] ) ) {
			foreach ( $in['object_types'] as $ot ) { $objects[] = sanitize_key( $ot ); }
		}
		$objects = array_values( array_unique( array_filter( $objects ) ) );

		return array(
			'slug'         => $slug,
			'singular'     => $singular,
			'plural'       => $plural,
			'object_types' => $objects,
			'hierarchical' => ! empty( $in['hierarchical'] ),
			'public'       => ! empty( $in['public'] ),
			'show_in_rest' => ! empty( $in['show_in_rest'] ),
		);
	}

	public static function args_from_def( $def ) {
		$singular = isset( $def['singular'] ) ? $def['singular'] : ucfirst( $def['slug'] );
		$plural   = isset( $def['plural'] ) ? $def['plural'] : $singular . 's';
		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			'all_items'     => 'All ' . $plural,
			'edit_item'     => 'Edit ' . $singular,
			'add_new_item'  => 'Add New ' . $singular,
			'search_items'  => 'Search ' . $plural,
		);
		return array(
			'labels'       => $labels,
			'hierarchical' => ! empty( $def['hierarchical'] ),
			'public'       => ! empty( $def['public'] ),
			'show_in_rest' => ! empty( $def['show_in_rest'] ),
			'show_admin_column' => true,
		);
	}

	public function register_all() { self::register_all_static(); }

	public static function register_all_static() {
		foreach ( self::all() as $def ) {
			if ( empty( $def['slug'] ) ) { continue; }
			$objects = isset( $def['object_types'] ) ? (array) $def['object_types'] : array();
			register_taxonomy( $def['slug'], $objects, self::args_from_def( $def ) );
		}
	}
}
