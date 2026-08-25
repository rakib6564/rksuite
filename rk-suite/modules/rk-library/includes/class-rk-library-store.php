<?php
/**
 * RK_Library_Store — the template model. Each library item is a private CPT
 * post holding the Elementor content (elements array), a type (section /
 * container / page) and a category. Kept separate from Elementor's own library
 * so RK Library can present its own branded, categorized picker.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Library_Store {

	const CPT       = 'rk_lib_item';
	const META_TYPE = '_rk_lib_type';
	const META_CAT  = '_rk_lib_category';
	const META_DATA = '_rk_lib_data';        // JSON: elements content
	const META_PS   = '_rk_lib_page_settings';
	const META_THUMB= '_rk_lib_thumb';       // external thumb URL (optional)

	public static function register() {
		register_post_type( self::CPT, array(
			'label'        => 'RK Library',
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'rewrite'      => false,
			'query_var'    => false,
			'supports'     => array( 'title', 'thumbnail' ),
		) );
	}

	/** Default categories seeded on activation. */
	public static function default_categories() {
		return array( 'Header', 'Footer', 'Hero', 'About', 'Features', 'CTA', 'FAQ', 'Contact', 'Grid', 'Pricing', 'Team', 'Testimonials', 'Blog', 'Gallery' );
	}

	public static function categories() {
		$cats = get_option( 'rk_library_categories', array() );
		if ( ! is_array( $cats ) || ! $cats ) { $cats = self::default_categories(); }
		return array_values( array_unique( $cats ) );
	}

	public static function add_category( $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) { return; }
		$cats = self::categories();
		if ( ! in_array( $name, $cats, true ) ) { $cats[] = $name; update_option( 'rk_library_categories', array_values( $cats ) ); }
	}

	public static function all() {
		$q = new WP_Query( array(
			'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => 500,
			'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
		) );
		return $q->posts;
	}

	public static function get( $id ) { return get_post( (int) $id ); }
	public static function type_of( $id ) { $t = get_post_meta( $id, self::META_TYPE, true ); return $t ? $t : 'container'; }
	public static function cat_of( $id )  { return get_post_meta( $id, self::META_CAT, true ); }
	public static function thumb_of( $id ) {
		$t = get_post_meta( $id, self::META_THUMB, true );
		if ( $t ) { return $t; }
		if ( has_post_thumbnail( $id ) ) { return get_the_post_thumbnail_url( $id, 'medium_large' ); }
		return '';
	}

	/** Decoded content (array of Elementor elements). */
	public static function content_of( $id ) {
		$raw = get_post_meta( $id, self::META_DATA, true );
		$d = $raw ? json_decode( $raw, true ) : array();
		return is_array( $d ) ? $d : array();
	}
	public static function page_settings_of( $id ) {
		$raw = get_post_meta( $id, self::META_PS, true );
		$d = $raw ? json_decode( $raw, true ) : array();
		return is_array( $d ) ? $d : array();
	}

	/** Create/update a library item. Returns post ID. */
	public static function save_item( $args ) {
		$args = wp_parse_args( $args, array(
			'id' => 0, 'title' => 'Untitled', 'type' => 'container', 'category' => '',
			'content' => array(), 'page_settings' => array(), 'thumbnail' => '',
		) );
		$postarr = array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => sanitize_text_field( $args['title'] ) );
		if ( $args['id'] ) { $postarr['ID'] = (int) $args['id']; $id = wp_update_post( $postarr, true ); }
		else { $id = wp_insert_post( $postarr, true ); }
		if ( is_wp_error( $id ) || ! $id ) { return 0; }

		update_post_meta( $id, self::META_TYPE, sanitize_key( $args['type'] ) );
		if ( $args['category'] ) { update_post_meta( $id, self::META_CAT, sanitize_text_field( $args['category'] ) ); self::add_category( $args['category'] ); }
		update_post_meta( $id, self::META_DATA, wp_slash( wp_json_encode( self::regenerate_ids( (array) $args['content'] ) ) ) );
		if ( ! empty( $args['page_settings'] ) ) { update_post_meta( $id, self::META_PS, wp_slash( wp_json_encode( (array) $args['page_settings'] ) ) ); }
		if ( ! empty( $args['thumbnail'] ) ) { update_post_meta( $id, self::META_THUMB, esc_url_raw( $args['thumbnail'] ) ); }
		return (int) $id;
	}

	public static function delete( $id ) { if ( get_post_type( $id ) === self::CPT ) { wp_delete_post( $id, true ); } }

	/** Give every element (recursively) a fresh unique id — prevents clashes. */
	public static function regenerate_ids( $elements ) {
		if ( ! is_array( $elements ) ) { return array(); }
		foreach ( $elements as &$el ) {
			if ( ! is_array( $el ) ) { continue; }
			$el['id'] = dechex( wp_rand( 0x1000000, 0xfffffff ) );
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) { $el['elements'] = self::regenerate_ids( $el['elements'] ); }
		}
		unset( $el );
		return $elements;
	}
}
