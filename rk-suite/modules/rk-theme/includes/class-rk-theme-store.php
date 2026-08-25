<?php
/**
 * RK_Theme_Store — the header/footer template model. Templates are stored as a
 * private custom post type (rk_template) so Elementor can edit them like any
 * document; type (header|footer) and display conditions live in post meta.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Store {

	const CPT        = 'rk_template';
	const META_TYPE  = '_rk_template_type';
	const META_COND  = '_rk_conditions';
	const META_BEHAVIOR = '_rk_header_behavior';

	/** Register the CPT (private; own admin UI). */
	public static function register() {
		// Publicly queryable (but hidden) so Elementor's editor preview URL
		// resolves to a real page instead of a 404 — same approach Elementor's
		// own library CPT uses. rewrite=false keeps URLs query-string based, so
		// no rewrite-rule flush is needed on update.
		register_post_type( self::CPT, array(
			'label'               => 'RK Templates',
			'public'              => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'hierarchical'        => false,
			'rewrite'             => false,
			'query_var'           => true,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'elementor', 'revisions', 'author' ),
			'show_in_rest'        => true,
		) );
		add_post_type_support( self::CPT, 'elementor' );
	}

	/** All templates, optionally filtered by type. */
	public static function all( $type = '' ) {
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );
		$out = array();
		foreach ( $q->posts as $p ) {
			$t = get_post_meta( $p->ID, self::META_TYPE, true );
			if ( $type && $t !== $type ) { continue; }
			$out[] = $p;
		}
		return $out;
	}

	public static function type_of( $id )       { return get_post_meta( $id, self::META_TYPE, true ); }
	public static function conditions_of( $id )  { $c = get_post_meta( $id, self::META_COND, true ); return is_array( $c ) ? $c : array(); }

	/** Header behavior settings (sticky / shrink / transparent) with defaults. */
	public static function behavior_of( $id ) {
		$b = get_post_meta( $id, self::META_BEHAVIOR, true );
		if ( ! is_array( $b ) ) { $b = array(); }
		return array_merge( array(
			'sticky'       => 0,
			'shrink'       => 0,
			'transparent'  => 0,
			'offset'       => 60,
			'stuck_bg'     => '#ffffff',
			'shadow'       => 1,
			'logo_shrink'  => 40,
		), $b );
	}
	public static function set_behavior( $id, array $b ) {
		update_post_meta( $id, self::META_BEHAVIOR, array(
			'sticky'      => empty( $b['sticky'] ) ? 0 : 1,
			'shrink'      => empty( $b['shrink'] ) ? 0 : 1,
			'transparent' => empty( $b['transparent'] ) ? 0 : 1,
			'offset'      => isset( $b['offset'] ) ? max( 0, (int) $b['offset'] ) : 60,
			'stuck_bg'    => isset( $b['stuck_bg'] ) ? sanitize_text_field( $b['stuck_bg'] ) : '#ffffff',
			'shadow'      => empty( $b['shadow'] ) ? 0 : 1,
			'logo_shrink' => isset( $b['logo_shrink'] ) ? max( 10, (int) $b['logo_shrink'] ) : 40,
		) );
	}

	public static function body_types() { return array( 'single' => 'Single', 'archive' => 'Archive', 'search' => 'Search results', 'error_404' => '404 page' ); }
	public static function all_types() { return array_merge( array( 'header' => 'Header', 'footer' => 'Footer' ), self::body_types() ); }

	/** Create a new template; returns the new post ID (0 on failure). */
	public static function create( $title, $type ) {
		$type = array_key_exists( $type, self::all_types() ) ? $type : 'header';
		$id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $title ? $title : ucfirst( $type ) ),
		), true );
		if ( is_wp_error( $id ) || ! $id ) { return 0; }
		update_post_meta( $id, self::META_TYPE, $type );
		update_post_meta( $id, self::META_COND, array( 'entire_site' ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-post' );
		update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
		return (int) $id;
	}

	public static function set_type( $id, $type ) {
		if ( array_key_exists( $type, self::all_types() ) ) { update_post_meta( $id, self::META_TYPE, $type ); }
	}
	public static function set_conditions( $id, array $conds ) {
		$clean = array();
		foreach ( $conds as $c ) { $c = sanitize_text_field( $c ); if ( $c !== '' ) { $clean[] = $c; } }
		if ( ! $clean ) { $clean = array( 'entire_site' ); }
		update_post_meta( $id, self::META_COND, array_values( array_unique( $clean ) ) );
	}

	public static function delete( $id ) {
		if ( get_post_type( $id ) === self::CPT ) { wp_delete_post( $id, true ); }
	}

	/** Elementor edit URL for a template (falls back to plain editor). */
	public static function edit_url( $id ) {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				$doc = \Elementor\Plugin::$instance->documents->get( $id );
				if ( $doc ) { return $doc->get_edit_url(); }
			} catch ( \Throwable $e ) {}
			return admin_url( 'post.php?post=' . (int) $id . '&action=elementor' );
		}
		return get_edit_post_link( $id, '' );
	}
}
