<?php
/**
 * RK_CPT_Builder — build custom post types from the admin.
 *
 * Definitions are stored as a portable JSON array in a single option, so they
 * can be exported/imported by RK Migrate. Post types are registered on `init`
 * from those definitions.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_CPT_Builder {

	const OPTION = 'rk_core_cpts';

	/** Reserved / built-in types we must never let a user redefine. */
	public static function reserved() {
		return array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item',
			'custom_css', 'customize_changeset', 'elementor_library', 'product' );
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

	/** Insert or update a definition. Returns sanitized slug or WP_Error. */
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
		if ( '' === $slug ) { return new WP_Error( 'slug', 'A post type key is required.' ); }
		if ( strlen( $slug ) > 20 ) { return new WP_Error( 'slug', 'Post type key must be 20 characters or fewer.' ); }
		if ( in_array( $slug, self::reserved(), true ) ) { return new WP_Error( 'slug', 'That post type key is reserved.' ); }

		$singular = isset( $in['singular'] ) ? sanitize_text_field( $in['singular'] ) : ucfirst( $slug );
		$plural   = isset( $in['plural'] ) ? sanitize_text_field( $in['plural'] ) : ( $singular . 's' );

		$supports = array();
		$valid_supports = array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'page-attributes', 'comments' );
		$requested = isset( $in['supports'] ) && is_array( $in['supports'] ) ? $in['supports'] : array( 'title', 'editor', 'thumbnail' );
		foreach ( $requested as $s ) { if ( in_array( $s, $valid_supports, true ) ) { $supports[] = $s; } }
		if ( empty( $supports ) ) { $supports = array( 'title' ); }

		$icon = isset( $in['menu_icon'] ) ? sanitize_text_field( $in['menu_icon'] ) : 'dashicons-admin-post';
		$rewrite = isset( $in['rewrite_slug'] ) && '' !== $in['rewrite_slug'] ? sanitize_title( $in['rewrite_slug'] ) : $slug;

		return array(
			'slug'               => $slug,
			'singular'           => $singular,
			'plural'             => $plural,
			'public'             => ! empty( $in['public'] ),
			'exclude_from_search'=> ! empty( $in['exclude_from_search'] ),
			'publicly_queryable' => ! empty( $in['publicly_queryable'] ),
			'show_ui'            => ! empty( $in['show_ui'] ),
			'show_in_menu'       => ! empty( $in['show_in_menu'] ),
			'show_in_nav_menus'  => ! empty( $in['show_in_nav_menus'] ),
			'hierarchical'       => ! empty( $in['hierarchical'] ),
			'has_archive'        => ! empty( $in['has_archive'] ),
			'show_in_rest'       => ! empty( $in['show_in_rest'] ),
			'supports'           => $supports,
			'menu_icon'          => $icon,
			'rewrite_slug'       => $rewrite,
		);
	}

	/** Build register_post_type args from a stored definition. */
	public static function args_from_def( $def ) {
		$singular = isset( $def['singular'] ) ? $def['singular'] : ucfirst( $def['slug'] );
		$plural   = isset( $def['plural'] ) ? $def['plural'] : $singular . 's';
		$labels = array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New ' . $singular,
			'edit_item'          => 'Edit ' . $singular,
			'new_item'           => 'New ' . $singular,
			'view_item'          => 'View ' . $singular,
			'search_items'       => 'Search ' . $plural,
			'not_found'          => 'No ' . strtolower( $plural ) . ' found',
			'all_items'          => 'All ' . $plural,
		);
		$public = ! empty( $def['public'] );
		return array(
			'labels'              => $labels,
			'public'              => $public,
			'exclude_from_search' => isset( $def['exclude_from_search'] ) ? ! empty( $def['exclude_from_search'] ) : ! $public,
			'publicly_queryable'  => isset( $def['publicly_queryable'] ) ? ! empty( $def['publicly_queryable'] ) : $public,
			'show_ui'             => isset( $def['show_ui'] ) ? ! empty( $def['show_ui'] ) : true,
			'show_in_menu'        => isset( $def['show_in_menu'] ) ? ! empty( $def['show_in_menu'] ) : true,
			'show_in_nav_menus'   => isset( $def['show_in_nav_menus'] ) ? ! empty( $def['show_in_nav_menus'] ) : $public,
			'hierarchical'        => ! empty( $def['hierarchical'] ),
			'has_archive'         => ! empty( $def['has_archive'] ),
			'show_in_rest'        => ! empty( $def['show_in_rest'] ),
			'supports'            => isset( $def['supports'] ) ? $def['supports'] : array( 'title', 'editor' ),
			'menu_icon'           => isset( $def['menu_icon'] ) ? $def['menu_icon'] : 'dashicons-admin-post',
			'rewrite'             => array( 'slug' => isset( $def['rewrite_slug'] ) ? $def['rewrite_slug'] : $def['slug'] ),
		);
	}

	public function register_all() { self::register_all_static(); }

	public static function register_all_static() {
		foreach ( self::all() as $def ) {
			if ( empty( $def['slug'] ) ) { continue; }
			if ( post_type_exists( $def['slug'] ) ) { continue; }
			register_post_type( $def['slug'], self::args_from_def( $def ) );
		}
	}
}
