<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Core_JetEngine — reads JetEngine's registered post types, taxonomies,
 * meta boxes, relations and Query Builder items, and maps them into the exact
 * RK Core "rk-core-model" schema so they import natively.
 *
 * Data access is defensive: it prefers the jet_engine() API and falls back to
 * JetEngine's options, and tolerates the several shapes JetEngine has shipped.
 */
class RK_Core_JetEngine {

	/* ---------------- detection + data access ---------------- */

	public static function is_active() {
		return function_exists( 'jet_engine' ) || class_exists( 'Jet_Engine' );
	}

	private static function engine() {
		return function_exists( 'jet_engine' ) ? jet_engine() : null;
	}

	/** Pull items from a jet_engine() module (->$prop->get_items()) or an option fallback. */
	private static function items( $prop, $option_key ) {
		$e = self::engine();
		if ( $e && isset( $e->$prop ) && is_object( $e->$prop ) && method_exists( $e->$prop, 'get_items' ) ) {
			$items = $e->$prop->get_items();
			if ( is_array( $items ) ) { return array_map( array( __CLASS__, 'to_array' ), $items ); }
		}
		$opt = get_option( $option_key, array() );
		return is_array( $opt ) ? array_map( array( __CLASS__, 'to_array' ), $opt ) : array();
	}

	private static function to_array( $x ) { return is_object( $x ) ? get_object_vars( $x ) : (array) $x; }

	private static function cpt_items()      { return self::items( 'cpt', 'jet_cpt' ); }
	private static function tax_items()      { return self::items( 'taxonomies', 'jet_taxonomies' ); }
	private static function mb_items()       { return self::items( 'meta_boxes', 'jet_meta_boxes' ); }

	private static function rel_items() {
		$e = self::engine();
		if ( $e && isset( $e->relations ) && is_object( $e->relations ) ) {
			foreach ( array( 'get_active_relations', 'get_items' ) as $m ) {
				if ( method_exists( $e->relations, $m ) ) {
					$r = $e->relations->$m();
					if ( is_array( $r ) ) { return array_map( array( __CLASS__, 'to_array' ), $r ); }
				}
			}
		}
		$opt = get_option( 'jet_relations', array() );
		return is_array( $opt ) ? array_map( array( __CLASS__, 'to_array' ), $opt ) : array();
	}

	private static function query_items() {
		$e = self::engine();
		if ( $e && isset( $e->query_builder ) && is_object( $e->query_builder ) ) {
			foreach ( array( 'get_queries', 'get_items' ) as $m ) {
				if ( method_exists( $e->query_builder, $m ) ) {
					$q = $e->query_builder->$m();
					if ( is_array( $q ) ) { return array_map( array( __CLASS__, 'to_array' ), $q ); }
				}
			}
		}
		return array();
	}

	/* ---------------- public: list + build ---------------- */

	/** Post types registered via JetEngine, for the export picker. */
	public static function list_post_types() {
		$out = array();
		foreach ( self::cpt_items() as $it ) {
			$slug = self::slug_of( $it );
			if ( '' === $slug ) { continue; }
			$out[] = array( 'slug' => $slug, 'label' => self::label_of( $it, $slug ) );
		}
		return $out;
	}

	/** Build the 5 rk-core-model arrays for the given JetEngine CPT slugs. */
	public static function build( array $slugs ) {
		$slugs   = array_values( array_filter( array_map( 'sanitize_key', $slugs ) ) );
		$cpts    = array();
		$taxes   = array();
		$groups  = array();
		$rels    = array();
		$queries = array();

		// Post types + their inline meta fields.
		foreach ( self::cpt_items() as $it ) {
			$slug = self::slug_of( $it );
			if ( '' === $slug || ! in_array( $slug, $slugs, true ) ) { continue; }
			$cpts[] = self::map_cpt( $it, $slug );
			$mf = self::fields_for( 'post_type', $slug );
			if ( ! $mf ) { $mf = self::meta_fields( $it ); }
			if ( $mf ) { $groups[] = self::map_group( 'grp_' . $slug, self::label_of( $it, $slug ) . ' fields', array( $slug ), $mf ); }
		}

		// Taxonomies attached to any selected CPT.
		foreach ( self::tax_items() as $it ) {
			$tslug = self::slug_of( $it );
			if ( '' === $tslug ) { continue; }
			$objs = self::tax_objects( $it );
			if ( ! array_intersect( $objs, $slugs ) ) { continue; }
			$taxes[] = self::map_tax( $it, $tslug, array_values( array_intersect( $objs, $slugs ) ) );
			$tmf = self::fields_for( 'taxonomy', $tslug );
			if ( ! $tmf ) { $tmf = self::meta_fields( $it ); }
			if ( $tmf ) { $groups[] = self::map_group( 'grp_tax_' . $tslug, self::label_of( $it, $tslug ) . ' term fields', array( $tslug ), $tmf ); }
		}

		// Standalone meta boxes allowed on a selected CPT (only when the fields
		// API is unavailable; otherwise those fields are already included above).
		foreach ( ( self::has_fields_api() ? array() : self::mb_items() ) as $it ) {
			$args    = isset( $it['args'] ) ? (array) $it['args'] : array();
			$allowed = array();
			foreach ( array( 'allowed_post_type', 'object_type', 'post_type' ) as $k ) {
				if ( ! empty( $args[ $k ] ) ) { $allowed = array_map( 'sanitize_key', (array) $args[ $k ] ); break; }
			}
			if ( ! array_intersect( $allowed, $slugs ) ) { continue; }
			$mf = self::meta_fields( $it );
			if ( ! $mf ) { continue; }
			$id    = 'grp_mb_' . sanitize_key( isset( $args['slug'] ) ? $args['slug'] : ( isset( $args['name'] ) ? $args['name'] : uniqid() ) );
			$title = isset( $args['name'] ) ? $args['name'] : 'Fields';
			$groups[] = self::map_group( $id, $title, array_values( array_intersect( $allowed, $slugs ) ), $mf );
		}

		// Relations touching a selected CPT.
		foreach ( self::rel_items() as $it ) {
			$args = isset( $it['args'] ) ? (array) $it['args'] : $it;
			$from = self::object_slug( isset( $args['parent_object'] ) ? $args['parent_object'] : ( isset( $args['from'] ) ? $args['from'] : '' ) );
			$to   = self::object_slug( isset( $args['child_object'] ) ? $args['child_object'] : ( isset( $args['to'] ) ? $args['to'] : '' ) );
			if ( ! in_array( $from, $slugs, true ) && ! in_array( $to, $slugs, true ) ) { continue; }
			$rels[] = array(
				'id'          => 'rel_' . ( $from ? $from : 'x' ) . '_' . ( $to ? $to : 'y' ),
				'name'        => isset( $args['name'] ) && $args['name'] ? (string) $args['name'] : ( $from . ' to ' . $to ),
				'from_object' => $from ? $from : 'post',
				'to_object'   => $to ? $to : 'post',
				'rel_type'    => self::rel_type( isset( $args['type'] ) ? $args['type'] : 'many_to_many' ),
			);
		}

		// Query Builder items whose post type is a selected CPT.
		foreach ( self::query_items() as $it ) {
			$qtype = isset( $it['query_type'] ) ? (string) $it['query_type'] : 'posts';
			$q     = isset( $it['query'] ) ? (array) $it['query'] : array();
			$pt    = '';
			if ( isset( $q['post_type'] ) ) { $pt = is_array( $q['post_type'] ) ? (string) reset( $q['post_type'] ) : (string) $q['post_type']; }
			if ( 'posts' === $qtype && $pt && ! in_array( sanitize_key( $pt ), $slugs, true ) ) { continue; }
			if ( 'posts' === $qtype && ! $pt ) { continue; }
			$queries[] = array(
				'id'        => 'qry_' . sanitize_key( isset( $it['id'] ) ? $it['id'] : ( isset( $it['name'] ) ? $it['name'] : uniqid() ) ),
				'name'      => isset( $it['name'] ) ? (string) $it['name'] : 'Query',
				'source'    => in_array( $qtype, array( 'posts', 'terms', 'users' ), true ) ? $qtype : 'posts',
				'post_type' => $pt ? sanitize_key( $pt ) : 'post',
				'number'    => isset( $q['posts_per_page'] ) ? (int) $q['posts_per_page'] : 10,
				'orderby'   => isset( $q['orderby'] ) ? sanitize_key( is_array( $q['orderby'] ) ? 'date' : $q['orderby'] ) : 'date',
				'order'     => ( isset( $q['order'] ) && 'ASC' === strtoupper( (string) $q['order'] ) ) ? 'ASC' : 'DESC',
			);
		}

		return array(
			'post_types' => $cpts,
			'taxonomies' => $taxes,
			'meta_boxes' => $groups,
			'relations'  => $rels,
			'queries'    => $queries,
		);
	}

	/* ---------------- mappers ---------------- */

	private static function map_cpt( $it, $slug ) {
		$labels = isset( $it['labels'] ) ? (array) $it['labels'] : array();
		$args   = isset( $it['args'] ) ? (array) $it['args'] : array();
		$singular = isset( $labels['singular_name'] ) && $labels['singular_name'] ? $labels['singular_name'] : ucwords( str_replace( array( '_', '-' ), ' ', $slug ) );
		$plural   = isset( $labels['name'] ) && $labels['name'] ? $labels['name'] : $singular . 's';

		$valid = array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'page-attributes', 'comments' );
		$supports = array();
		$req = isset( $args['supports'] ) && is_array( $args['supports'] ) ? $args['supports'] : array( 'title', 'editor', 'thumbnail' );
		foreach ( $req as $k => $v ) {
			// JetEngine may store supports as list ['title',...] or map ['title'=>true].
			$name = is_int( $k ) ? $v : ( $v ? $k : null );
			if ( $name && in_array( $name, $valid, true ) ) { $supports[] = $name; }
		}
		if ( ! $supports ) { $supports = array( 'title' ); }

		$public = ! isset( $args['public'] ) ? true : (bool) $args['public'];
		return array(
			'slug'                => $slug,
			'singular'            => sanitize_text_field( $singular ),
			'plural'              => sanitize_text_field( $plural ),
			'public'              => $public,
			'has_archive'         => ! empty( $args['has_archive'] ),
			'show_in_rest'        => ! isset( $args['show_in_rest'] ) ? true : (bool) $args['show_in_rest'],
			'hierarchical'        => ! empty( $args['hierarchical'] ),
			'show_ui'             => ! isset( $args['show_ui'] ) ? true : (bool) $args['show_ui'],
			'show_in_menu'        => ! isset( $args['show_in_menu'] ) ? true : (bool) $args['show_in_menu'],
			'show_in_nav_menus'   => ! isset( $args['show_in_nav_menus'] ) ? $public : (bool) $args['show_in_nav_menus'],
			'publicly_queryable'  => ! isset( $args['publicly_queryable'] ) ? $public : (bool) $args['publicly_queryable'],
			'exclude_from_search' => ! isset( $args['exclude_from_search'] ) ? ! $public : (bool) $args['exclude_from_search'],
			'supports'            => array_values( array_unique( $supports ) ),
			'menu_icon'           => ( isset( $args['menu_icon'] ) && $args['menu_icon'] ) ? sanitize_text_field( $args['menu_icon'] ) : 'dashicons-admin-post',
		);
	}

	private static function map_tax( $it, $slug, $objects ) {
		$labels = isset( $it['labels'] ) ? (array) $it['labels'] : array();
		$args   = isset( $it['args'] ) ? (array) $it['args'] : array();
		$singular = isset( $labels['singular_name'] ) && $labels['singular_name'] ? $labels['singular_name'] : ucwords( str_replace( array( '_', '-' ), ' ', $slug ) );
		$plural   = isset( $labels['name'] ) && $labels['name'] ? $labels['name'] : $singular . 's';
		return array(
			'slug'         => $slug,
			'singular'     => sanitize_text_field( $singular ),
			'plural'       => sanitize_text_field( $plural ),
			'object_types' => array_values( array_map( 'sanitize_key', $objects ) ),
			'hierarchical' => ! isset( $args['hierarchical'] ) ? true : (bool) $args['hierarchical'],
			'public'       => ! isset( $args['public'] ) ? true : (bool) $args['public'],
			'show_in_rest' => ! isset( $args['show_in_rest'] ) ? true : (bool) $args['show_in_rest'],
		);
	}

	private static function map_group( $id, $title, $post_types, $meta_fields ) {
		$fields = array();
		foreach ( (array) $meta_fields as $f ) {
			$f = self::to_array( $f );
			$mf = self::map_field( $f );
			if ( $mf ) { $fields[] = $mf; }
		}
		return array(
			'id'         => sanitize_key( $id ),
			'title'      => sanitize_text_field( $title ),
			'post_types' => array_values( array_map( 'sanitize_key', (array) $post_types ) ),
			'fields'     => $fields,
		);
	}

	private static function map_field( $f ) {
		$key = sanitize_key( isset( $f['name'] ) ? $f['name'] : ( isset( $f['key'] ) ? $f['key'] : '' ) );
		if ( '' === $key ) { return null; }
		$type  = self::map_field_type( isset( $f['type'] ) ? (string) $f['type'] : 'text' );
		$label = isset( $f['title'] ) ? $f['title'] : ( isset( $f['label'] ) ? $f['label'] : ucwords( str_replace( '_', ' ', $key ) ) );
		$field = array(
			'key'      => $key,
			'label'    => sanitize_text_field( $label ),
			'type'     => $type,
			'required' => ! empty( $f['is_required'] ) || ! empty( $f['required'] ),
		);
		if ( in_array( $type, array( 'select', 'radio', 'checklist' ), true ) ) {
			$opts = array();
			foreach ( array( 'options', 'choices', 'switcher_options', 'buttons_options' ) as $ok ) {
				if ( ! empty( $f[ $ok ] ) ) { $opts = $f[ $ok ]; break; }
			}
			$ch = self::map_choices( $opts );
			if ( '' !== $ch ) { $field['choices'] = $ch; }
		}
		if ( 'repeater' === $type ) {
			$sub = array();
			foreach ( array( 'repeater-fields', 'repeater_fields', 'subfields' ) as $rk ) {
				if ( ! empty( $f[ $rk ] ) ) { $sub = $f[ $rk ]; break; }
			}
			if ( is_string( $sub ) ) { $d = json_decode( $sub, true ); $sub = is_array( $d ) ? $d : array(); }
			$field['subfields'] = self::map_subfields( $sub );
		}
		return $field;
	}

	private static function map_subfields( $subs ) {
		$out = array();
		foreach ( (array) $subs as $sf ) {
			$sf = self::to_array( $sf );
			$k  = sanitize_key( isset( $sf['name'] ) ? $sf['name'] : ( isset( $sf['key'] ) ? $sf['key'] : '' ) );
			if ( '' === $k ) { continue; }
			$out[] = array(
				'key'   => $k,
				'label' => sanitize_text_field( isset( $sf['title'] ) ? $sf['title'] : ( isset( $sf['label'] ) ? $sf['label'] : $k ) ),
				'type'  => self::map_field_type( isset( $sf['type'] ) ? (string) $sf['type'] : 'text' ),
			);
		}
		return $out;
	}

	/** JetEngine field type → RK Core field type. */
	private static function map_field_type( $t ) {
		$t = strtolower( trim( $t ) );
		$map = array(
			'text' => 'text', 'textarea' => 'textarea', 'wysiwyg' => 'wysiwyg', 'html' => 'text',
			'number' => 'number', 'date' => 'date', 'time' => 'time', 'datetime-local' => 'date', 'datetime' => 'date',
			'select' => 'select', 'radio' => 'radio', 'checkbox' => 'checklist',
			'switcher' => 'switcher', 'media' => 'image', 'gallery' => 'gallery',
			'colorpicker' => 'color', 'iconpicker' => 'icon', 'posts' => 'relation',
			'repeater' => 'repeater', 'accept' => 'switcher', 'range' => 'number',
			'text-email' => 'email', 'email' => 'email', 'url' => 'url',
		);
		return isset( $map[ $t ] ) ? $map[ $t ] : 'text';
	}

	/**
	 * Normalize JetEngine option lists into RK Core's "value:Label\nvalue2:Label2".
	 * Handles: assoc map, list of {key,value}, and plain list.
	 */
	private static function map_choices( $options ) {
		$lines = array();
		if ( is_string( $options ) ) { return trim( $options ); } // already a string
		if ( ! is_array( $options ) ) { return ''; }

		$is_list = array_keys( $options ) === range( 0, count( $options ) - 1 );
		if ( $is_list ) {
			foreach ( $options as $o ) {
				$o = is_object( $o ) ? get_object_vars( $o ) : $o;
				if ( is_array( $o ) ) {
					$k = isset( $o['key'] ) ? (string) $o['key'] : ( isset( $o['value'] ) ? (string) $o['value'] : '' );
					$v = isset( $o['value'] ) ? (string) $o['value'] : $k;
					if ( '' !== $k ) { $lines[] = $k . ':' . $v; }
				} else {
					$sv = (string) $o;
					if ( '' !== $sv ) { $lines[] = $sv . ':' . $sv; }
				}
			}
		} else {
			foreach ( $options as $k => $v ) {
				$v = is_object( $v ) ? get_object_vars( $v ) : $v;
				$k = (string) $k;
				$v = is_array( $v ) ? ( isset( $v['value'] ) ? (string) $v['value'] : $k ) : (string) $v;
				if ( '' !== $k ) { $lines[] = $k . ':' . $v; }
			}
		}
		return implode( "\n", $lines );
	}

	/* ---------------- small helpers ---------------- */

	private static function slug_of( $it ) {
		if ( isset( $it['slug'] ) && $it['slug'] ) { return sanitize_key( $it['slug'] ); }
		if ( isset( $it['args']['slug'] ) && $it['args']['slug'] ) { return sanitize_key( $it['args']['slug'] ); }
		return '';
	}

	private static function label_of( $it, $slug ) {
		if ( isset( $it['labels']['name'] ) && $it['labels']['name'] ) { return (string) $it['labels']['name']; }
		if ( isset( $it['args']['name'] ) && $it['args']['name'] ) { return (string) $it['args']['name']; }
		return ucwords( str_replace( array( '_', '-' ), ' ', $slug ) );
	}

	/** Post types a taxonomy is bound to. */
	private static function tax_objects( $it ) {
		$args = isset( $it['args'] ) ? (array) $it['args'] : array();
		foreach ( array( 'object_type', 'object_types', 'post_type' ) as $k ) {
			if ( ! empty( $args[ $k ] ) ) { return array_map( 'sanitize_key', (array) $args[ $k ] ); }
		}
		if ( ! empty( $it['object_type'] ) ) { return array_map( 'sanitize_key', (array) $it['object_type'] ); }
		return array();
	}

	/** "posts::property" → "property"; "users" → "user"; "terms::category" → "category". */
	private static function object_slug( $raw ) {
		$raw = (string) $raw;
		if ( false !== strpos( $raw, '::' ) ) { $raw = substr( $raw, strpos( $raw, '::' ) + 2 ); }
		$raw = sanitize_key( $raw );
		if ( 'users' === $raw ) { return 'user'; }
		return $raw;
	}

	private static function rel_type( $t ) {
		$t = str_replace( '-', '_', strtolower( (string) $t ) );
		if ( 'many_to_one' === $t ) { $t = 'one_to_many'; }
		return in_array( $t, array( 'one_to_one', 'one_to_many', 'many_to_many' ), true ) ? $t : 'many_to_many';
	}

	/** True when the authoritative JetEngine fields API is available. */
	private static function has_fields_api() {
		$e = self::engine();
		return $e && isset( $e->meta_boxes ) && is_object( $e->meta_boxes ) && method_exists( $e->meta_boxes, 'get_fields_for_context' );
	}

	/**
	 * Authoritative field list for a context via JetEngine's own API
	 * (jet_engine()->meta_boxes->get_fields_for_context). This returns the
	 * FULL field definitions — including nested 'repeater-fields' and 'options'
	 * — which the raw CPT item's 'meta_fields' does not always carry.
	 * $context: 'post_type' | 'taxonomy' | 'user'.
	 */
	public static function fields_for( $context, $slug ) {
		$e = self::engine();
		if ( self::has_fields_api() ) {
			$f = $e->meta_boxes->get_fields_for_context( $context, $slug );
			if ( is_array( $f ) ) { return array_map( array( __CLASS__, 'to_array' ), $f ); }
		}
		return array();
	}

	private static function meta_fields( $it ) {
		$mf = isset( $it['meta_fields'] ) ? $it['meta_fields'] : array();
		if ( is_string( $mf ) ) { $d = json_decode( $mf, true ); $mf = is_array( $d ) ? $d : array(); }
		return is_array( $mf ) ? $mf : array();
	}

	/* =====================================================================
	   JetEngine Listing Templates / Components (post_type = jet-engine)
	   ===================================================================== */

	/** Listing source (JetEngine) → RK label. */
	private static function listing_source_map( $src ) {
		$src = strtolower( (string) $src );
		$map = array(
			'posts' => 'posts', 'terms' => 'terms', 'users' => 'users',
			'repeater' => 'repeater', 'jet_engine_repeater' => 'repeater',
			'relations' => 'relation', 'jet_engine_relations' => 'relation', 'relation' => 'relation',
			'query_builder' => 'query', 'jet_query' => 'query', 'query' => 'query',
			'options_page' => 'options',
		);
		return isset( $map[ $src ] ) ? $map[ $src ] : ( $src ? $src : 'posts' );
	}

	/** Read a listing post's source settings (Elementor or Blocks). */
	private static function listing_settings( $post_id ) {
		$s = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! is_array( $s ) || empty( $s['listing_source'] ) ) {
			$b = get_post_meta( $post_id, '_jet_engine_listing', true );
			if ( is_array( $b ) ) { $s = array_merge( is_array( $s ) ? $s : array(), $b ); }
		}
		return is_array( $s ) ? $s : array();
	}

	/** The raw layout data: Elementor JSON string, or block/HTML post content. */
	private static function listing_content( $post ) {
		$data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_array( $data ) ) { $data = wp_json_encode( $data ); }
		if ( is_string( $data ) && '' !== trim( $data ) && '[]' !== trim( $data ) ) {
			return array( 'listing_type' => 'elementor', 'content' => $data );
		}
		return array( 'listing_type' => ( $post->post_content ? 'blocks' : 'elementor' ), 'content' => (string) $post->post_content );
	}

	/** Build a { meta_key => rk_type } map for a post type from its JetEngine fields. */
	private static function meta_map_for( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type ) { return array(); }
		$map = array();
		$fields = self::fields_for( 'post_type', $post_type );
		foreach ( $fields as $mf ) {
			$mf = self::to_array( $mf );
			$name = sanitize_key( isset( $mf['name'] ) ? $mf['name'] : '' );
			if ( '' === $name ) { continue; }
			$map[ $name ] = self::map_field_type( isset( $mf['type'] ) ? (string) $mf['type'] : 'text' );
		}
		return $map;
	}

	/** Raw diagnostic dump for a post type — for debugging field mapping. */
	public static function debug_dump() {
		$out = array( 'jetengine_active' => self::is_active(), 'has_fields_api' => self::has_fields_api(), 'post_types' => array() );
		foreach ( self::cpt_items() as $it ) {
			$slug = self::slug_of( $it );
			if ( '' === $slug ) { continue; }
			$out['post_types'][ $slug ] = array(
				'fields_for_context' => self::fields_for( 'post_type', $slug ),
				'raw_item_meta_fields' => self::meta_fields( $it ),
			);
		}
		return $out;
	}

	/** Post type = 'jet-engine' listing templates for the picker. */
	public static function list_listings() {
		if ( ! post_type_exists( 'jet-engine' ) ) { return array(); }
		$posts = get_posts( array(
			'post_type'      => 'jet-engine',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$out = array();
		foreach ( $posts as $p ) {
			$set = self::listing_settings( $p->ID );
			$out[] = array(
				'id'     => (int) $p->ID,
				'title'  => $p->post_title,
				'source' => self::listing_source_map( isset( $set['listing_source'] ) ? $set['listing_source'] : 'posts' ),
			);
		}
		return $out;
	}

	/** Export selected listing templates into the jet_engine_listings schema. */
	public static function export_listings( array $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'jet-engine' !== $post->post_type ) { continue; }
			$set   = self::listing_settings( $id );
			$src   = self::listing_source_map( isset( $set['listing_source'] ) ? $set['listing_source'] : 'posts' );
			$layout = self::listing_content( $post );
			$pt    = '';
			foreach ( array( 'listing_post_type', 'post_type', 'listing_source_post_type' ) as $k ) {
				if ( ! empty( $set[ $k ] ) ) { $pt = sanitize_key( is_array( $set[ $k ] ) ? reset( $set[ $k ] ) : $set[ $k ] ); break; }
			}
			$entry = array(
				'title'        => $post->post_title,
				'source'       => $src,
				'listing_type' => $layout['listing_type'],
				'content'      => $layout['content'],
			);
			if ( $pt ) { $entry['listing_post_type'] = $pt; $entry['meta_map'] = self::meta_map_for( $pt ); }
			if ( 'repeater' === $src ) {
				$entry['repeater_field']  = sanitize_key( isset( $set['repeater_field'] ) ? $set['repeater_field'] : '' );
				$entry['repeater_source'] = sanitize_text_field( isset( $set['repeater_source'] ) ? $set['repeater_source'] : '' );
			}
			if ( 'query'    === $src ) { $entry['query_id'] = sanitize_text_field( isset( $set['query_id'] ) ? $set['query_id'] : '' ); }
			if ( 'relation' === $src ) { $entry['relation'] = sanitize_text_field( isset( $set['relation'] ) ? $set['relation'] : ( isset( $set['listing_relation'] ) ? $set['listing_relation'] : '' ) ); }
			$out[] = $entry;
		}
		return $out;
	}

	/** Delete a JetEngine listing template. */
	public static function delete_listing( $id ) {
		$post = get_post( (int) $id );
		if ( $post && 'jet-engine' === $post->post_type ) { wp_delete_post( (int) $id, true ); return true; }
		return false;
	}

	/**
	 * Four structural blueprint listings (copy-paste examples). `content` is a
	 * compact token layout — each item names the widget and the dynamic source
	 * it renders — so it is small, human-readable, and provisions as a starting
	 * point that the user finishes in the editor.
	 */
	public static function blueprints() {
		$tok = function ( $arr ) { return wp_json_encode( $arr ); };
		return array(
			array(
				'title'        => 'Blueprint 1 — Meta Fields',
				'source'       => 'posts',
				'listing_type' => 'tokens',
				'content'      => $tok( array(
					array( 'el' => 'image',   'dynamic' => 'post_thumbnail' ),
					array( 'el' => 'heading', 'dynamic' => 'post_title' ),
					array( 'el' => 'text',    'dynamic' => 'meta:subtitle' ),
					array( 'el' => 'text',    'dynamic' => 'meta:summary' ),
				) ),
				'meta_map'     => array( 'subtitle' => 'text', 'summary' => 'textarea' ),
			),
			array(
				'title'          => 'Blueprint 2 — Repeater Listing',
				'source'         => 'repeater',
				'listing_type'   => 'tokens',
				'repeater_field' => 'ingredients',
				'content'        => $tok( array(
					array( 'el' => 'repeater', 'field' => 'ingredients', 'items' => array(
						array( 'el' => 'text', 'dynamic' => 'repeater:ingredient_name' ),
						array( 'el' => 'text', 'dynamic' => 'repeater:ingredient_quantity' ),
					) ),
				) ),
				'meta_map'       => array( 'ingredient_name' => 'text', 'ingredient_quantity' => 'text' ),
			),
			array(
				'title'        => 'Blueprint 3 — Relationship Listing',
				'source'       => 'relation',
				'listing_type' => 'tokens',
				'relation'     => 'recipe_to_chef',
				'content'      => $tok( array(
					array( 'el' => 'image',   'dynamic' => 'related:post_thumbnail' ),
					array( 'el' => 'heading', 'dynamic' => 'related:post_title' ),
					array( 'el' => 'text',    'dynamic' => 'related:meta:role' ),
				) ),
				'meta_map'     => array( 'role' => 'text' ),
			),
			array(
				'title'        => 'Blueprint 4 — Query Listing',
				'source'       => 'query',
				'listing_type' => 'tokens',
				'query_id'     => 'featured_recipes',
				'content'      => $tok( array(
					array( 'el' => 'image',   'dynamic' => 'post_thumbnail' ),
					array( 'el' => 'heading', 'dynamic' => 'post_title' ),
					array( 'el' => 'button',  'dynamic' => 'permalink', 'label' => 'View' ),
				) ),
				'meta_map'     => array(),
			),
		);
	}

	/** Provision jet_engine_listings entries as jet-engine posts. Returns count. */
	public static function import_listings( $listings ) {
		if ( ! is_array( $listings ) ) { return 0; }
		$n = 0;
		foreach ( $listings as $l ) {
			if ( ! is_array( $l ) || empty( $l['title'] ) ) { continue; }
			$type    = isset( $l['listing_type'] ) ? $l['listing_type'] : 'elementor';
			$content = isset( $l['content'] ) ? $l['content'] : '';

			$post_id = wp_insert_post( array(
				'post_type'    => 'jet-engine',
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( $l['title'] ),
				'post_content' => ( 'blocks' === $type ) ? wp_kses_post( $content ) : '',
			), true );
			if ( is_wp_error( $post_id ) || ! $post_id ) { continue; }

			// Source settings for JetEngine to read.
			$settings = array( 'listing_source' => isset( $l['source'] ) ? $l['source'] : 'posts' );
			if ( ! empty( $l['listing_post_type'] ) ) { $settings['listing_post_type'] = sanitize_key( $l['listing_post_type'] ); }
			if ( ! empty( $l['repeater_field'] ) )    { $settings['repeater_field'] = sanitize_key( $l['repeater_field'] ); }
			if ( ! empty( $l['query_id'] ) )          { $settings['query_id'] = sanitize_text_field( $l['query_id'] ); }
			if ( ! empty( $l['relation'] ) )          { $settings['relation'] = sanitize_text_field( $l['relation'] ); }
			update_post_meta( $post_id, '_elementor_page_settings', $settings );
			update_post_meta( $post_id, '_elementor_template_type', 'jet-listing-items' );
			update_post_meta( $post_id, '_jet_engine_listing', $settings );

			if ( 'elementor' === $type && '' !== trim( (string) $content ) ) {
				update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $post_id, '_elementor_data', wp_slash( $content ) );
			} elseif ( 'tokens' === $type ) {
				// Blueprint token layout — stored for reference; user finishes in the editor.
				update_post_meta( $post_id, '_rk_listing_blueprint', $content );
			}
			$n++;
		}
		return $n;
	}

}
