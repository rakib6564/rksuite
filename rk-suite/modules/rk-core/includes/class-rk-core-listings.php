<?php
/**
 * RK_Core_Listings — native listing templates (like JetEngine Listing Items).
 *
 * Each listing is an Elementor-editable custom post type (rk_listing). Its data
 * source (which post type / query / repeater / relation it loops) lives in post
 * meta. The RK Listing widget queries the source, loops it, and renders this
 * template per item with dynamic RK Core fields resolving to each item.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core_Listings {

	/** Active repeater row during a repeater-source loop (JetEngine-style). */
	private static $current_row = null;
	private static $current_repeater_field = '';
	public static function current_row() { return self::$current_row; }
	public static function current_repeater_field() { return self::$current_repeater_field; }

	const CPT           = 'rk_listing';
	const META_SOURCE   = '_rk_listing_source';    // posts|query|repeater|relation|terms|users
	const META_POSTTYPE = '_rk_listing_post_type';
	const META_REPEATER = '_rk_listing_repeater';  // repeater field key (source=repeater)
	const META_QUERY    = '_rk_listing_query';      // RK Core query id (source=query)
	const META_RELATION = '_rk_listing_relation';   // relation id (source=relation)
	const META_COLUMNS  = '_rk_listing_columns';
	const META_COUNT    = '_rk_listing_count';

	public static function sources() {
		return array(
			'posts'    => 'Posts (a post type)',
			'query'    => 'RK Core query',
			'repeater' => 'Repeater field',
			'relation' => 'Relation',
			'terms'    => 'Terms (taxonomy)',
			'users'    => 'Users',
		);
	}

	/** Register the Elementor-editable CPT (private; own admin UI). */
	public static function register() {
		register_post_type( self::CPT, array(
			'label'               => 'RK Listings',
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

	/* ---------------- read ---------------- */

	public static function all() {
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 300,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );
		return $q->posts;
	}

	public static function get( $id ) {
		$p = get_post( (int) $id );
		return ( $p && self::CPT === $p->post_type ) ? $p : null;
	}

	public static function settings_of( $id ) {
		$id = (int) $id;
		return array(
			'source'    => get_post_meta( $id, self::META_SOURCE, true ) ?: 'posts',
			'post_type' => get_post_meta( $id, self::META_POSTTYPE, true ),
			'repeater'  => get_post_meta( $id, self::META_REPEATER, true ),
			'query'     => get_post_meta( $id, self::META_QUERY, true ),
			'relation'  => get_post_meta( $id, self::META_RELATION, true ),
			'columns'   => (int) ( get_post_meta( $id, self::META_COLUMNS, true ) ?: 3 ),
			'count'     => (int) ( get_post_meta( $id, self::META_COUNT, true ) ?: 6 ),
		);
	}

	public static function save_settings( $id, $s ) {
		$id = (int) $id;
		update_post_meta( $id, self::META_SOURCE,   sanitize_key( isset( $s['source'] ) ? $s['source'] : 'posts' ) );
		update_post_meta( $id, self::META_POSTTYPE, sanitize_key( isset( $s['post_type'] ) ? $s['post_type'] : '' ) );
		update_post_meta( $id, self::META_REPEATER, sanitize_key( isset( $s['repeater'] ) ? $s['repeater'] : '' ) );
		update_post_meta( $id, self::META_QUERY,    sanitize_key( isset( $s['query'] ) ? $s['query'] : '' ) );
		update_post_meta( $id, self::META_RELATION, sanitize_key( isset( $s['relation'] ) ? $s['relation'] : '' ) );
		update_post_meta( $id, self::META_COLUMNS,  max( 1, min( 6, (int) ( isset( $s['columns'] ) ? $s['columns'] : 3 ) ) ) );
		update_post_meta( $id, self::META_COUNT,    max( 1, (int) ( isset( $s['count'] ) ? $s['count'] : 6 ) ) );
	}

	/* ---------------- create / duplicate / delete ---------------- */

	public static function create( $title, $settings ) {
		$id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $title ? $title : 'Listing' ),
		), true );
		if ( is_wp_error( $id ) || ! $id ) { return 0; }
		self::save_settings( $id, $settings );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-post' );
		return (int) $id;
	}

	public static function duplicate( $id ) {
		$src = self::get( $id );
		if ( ! $src ) { return 0; }
		$new = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => $src->post_title . ' (copy)',
			'post_content'=> $src->post_content,
		), true );
		if ( is_wp_error( $new ) || ! $new ) { return 0; }
		foreach ( get_post_meta( $id ) as $k => $vals ) {
			if ( '_edit_lock' === $k || '_edit_last' === $k ) { continue; }
			update_post_meta( $new, $k, maybe_unserialize( $vals[0] ) );
		}
		return (int) $new;
	}

	public static function delete( $id ) {
		$p = self::get( $id );
		if ( $p ) { wp_delete_post( (int) $id, true ); return true; }
		return false;
	}

	public static function edit_url( $id ) {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				$doc = \Elementor\Plugin::$instance->documents->get( (int) $id );
				if ( $doc ) { return $doc->get_edit_url(); }
			} catch ( \Throwable $e ) {}
			return admin_url( 'post.php?post=' . (int) $id . '&action=elementor' );
		}
		return get_edit_post_link( $id, '' );
	}

	/* ---------------- import / export ---------------- */

	public static function export_one( $id ) {
		$p = self::get( $id );
		if ( ! $p ) { return null; }
		$s = self::settings_of( $id );
		return array(
			'format'        => 'rk-listing',
			'title'         => $p->post_title,
			'settings'      => $s,
			'post_content'  => $p->post_content,
			'elementor_data'=> get_post_meta( $id, '_elementor_data', true ),
		);
	}

	public static function import_one( $data ) {
		if ( ! is_array( $data ) || empty( $data['title'] ) ) { return 0; }
		$s  = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$id = self::create( sanitize_text_field( $data['title'] ), $s );
		if ( ! $id ) { return 0; }
		if ( ! empty( $data['post_content'] ) ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => wp_kses_post( $data['post_content'] ) ) );
		}
		if ( ! empty( $data['elementor_data'] ) ) {
			$ed = is_array( $data['elementor_data'] ) ? wp_json_encode( $data['elementor_data'] ) : (string) $data['elementor_data'];
			update_post_meta( $id, '_elementor_data', wp_slash( $ed ) );
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		}
		return $id;
	}

	/* ---------------- rendering (used by the widget) ---------------- */

	/** Resolve the source into an array of WP_Post items to loop. */
	public static function query_items( $id, $override_count = 0 ) {
		$s     = self::settings_of( $id );
		$count = $override_count ? (int) $override_count : $s['count'];

		// Repeater source: loop the ROWS of a repeater field on the current post,
		// not a list of posts (JetEngine-style). Each item is an array (one row).
		if ( 'repeater' === $s['source'] && $s['repeater'] ) {
			$parent = self::current_parent_id();
			if ( ! $parent ) { return array(); }
			$rows = get_post_meta( $parent, $s['repeater'], true );
			if ( is_string( $rows ) ) {
				$u = maybe_unserialize( $rows );
				if ( is_array( $u ) ) { $rows = $u; } else { $d = json_decode( $rows, true ); $rows = is_array( $d ) ? $d : array(); }
			}
			if ( ! is_array( $rows ) ) { return array(); }
			$rows = array_values( $rows );
			if ( $count > 0 ) { $rows = array_slice( $rows, 0, $count ); }
			return $rows;
		}

		if ( 'query' === $s['source'] && $s['query'] && class_exists( 'RK_Query_Builder' ) && method_exists( 'RK_Query_Builder', 'run' ) ) {
			$res = RK_Query_Builder::run( $s['query'] );
			if ( is_array( $res ) ) { return array_slice( $res, 0, $count ); }
		}

		$pt = $s['post_type'] ? $s['post_type'] : 'post';
		$q  = new WP_Query( array(
			'post_type'      => $pt,
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'no_found_rows'  => true,
		) );
		return $q->posts;
	}

	/** The post that owns the repeater rows: the queried single post, else current. */
	private static function current_parent_id() {
		$id = (int) get_queried_object_id();
		if ( $id && 'post' === substr( get_post_type( $id ), 0, 4 ) ) { /* any post type */ }
		if ( ! $id || ! get_post( $id ) ) { $id = (int) get_the_ID(); }
		return $id;
	}

	/** [rk_listing id="12" count="6" columns="3"] */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'id' => 0, 'count' => 0, 'columns' => 0 ), $atts, 'rk_listing' );
		$id = (int) $a['id'];
		if ( ! $id || ! self::get( $id ) ) { return ''; }
		$items = self::query_items( $id, (int) $a['count'] );
		if ( empty( $items ) ) { return ''; }
		$set  = self::settings_of( $id );
		$cols = $a['columns'] ? (int) $a['columns'] : (int) $set['columns'];
		$out  = '<div class="rk-listing-grid" style="display:grid;grid-template-columns:repeat(' . esc_attr( $cols ) . ',1fr);gap:24px;">';
		$rf = ( 'repeater' === $set['source'] ) ? $set['repeater'] : '';
		foreach ( $items as $item ) { $out .= '<div class="rk-listing-item">' . self::render_item( $id, $item, $rf ) . '</div>'; }
		return $out . '</div>';
	}

	/** Render the listing content for one item (dynamic fields resolve to it). */
	public static function render_item( $listing_id, $item, $repeater_field = '' ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) { return ''; }
		$html = '';

		// Repeater row: keep the parent post as context, expose the row so field
		// tokens resolve to this row's sub-values (JetEngine-style).
		if ( is_array( $item ) ) {
			self::$current_row = $item;
			self::$current_repeater_field = $repeater_field;
			try { $html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $listing_id, true ); }
			catch ( \Throwable $e ) { $html = ''; }
			self::$current_row = null;
			self::$current_repeater_field = '';
			return $html;
		}

		// Post item: set up post data so dynamic fields resolve to it.
		global $post;
		$prev = $post;
		$post = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		try { $html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $listing_id, true ); }
		catch ( \Throwable $e ) { $html = ''; }
		$post = $prev; // phpcs:ignore
		wp_reset_postdata();
		return $html;
	}
}
