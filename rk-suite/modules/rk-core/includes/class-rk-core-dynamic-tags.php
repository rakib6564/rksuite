<?php
/**
 * RK_Core_Dynamic_Tags — exposes RK Core custom fields to Elementor's Dynamic
 * Tags picker (the "database" icon in the editor), so any text/image/URL
 * control can pull a field value for the current post. JetEngine-style.
 *
 * Tag subclasses extend Elementor classes, so they are loaded only inside the
 * registration hook (when Elementor is present) — the plugin never fatals.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core_Dynamic_Tags {

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function hooks() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register' ) );          // 3.5+
		add_action( 'elementor/dynamic_tags/register_tags', array( $this, 'register_legacy' ) ); // legacy
	}

	private function load_tags() {
		require_once RK_CORE_DIR . 'includes/dynamic-tags/class-rk-tag-text.php';
		require_once RK_CORE_DIR . 'includes/dynamic-tags/class-rk-tag-image.php';
		require_once RK_CORE_DIR . 'includes/dynamic-tags/class-rk-tag-url.php';
	}

	public function register( $dynamic_tags ) {
		if ( ! is_object( $dynamic_tags ) ) { return; }
		try {
			$this->load_tags();
			if ( method_exists( $dynamic_tags, 'register_group' ) ) {
				$dynamic_tags->register_group( 'rk-core', array( 'title' => 'RK Core' ) );
			}
			foreach ( array( 'RK_Tag_Text', 'RK_Tag_Image', 'RK_Tag_URL' ) as $cls ) {
				if ( ! class_exists( $cls ) ) { continue; }
				if ( method_exists( $dynamic_tags, 'register' ) ) { $dynamic_tags->register( new $cls() ); }
				elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) { $dynamic_tags->register_tag( new $cls() ); }
			}
		} catch ( \Throwable $e ) {
			rk_suite_log( '[RK Core] dynamic tags failed: ' . $e->getMessage() );
		}
	}

	public function register_legacy( $dynamic_tags ) {
		// Older Elementor passes the manager here and expects register_group + register_tag.
		if ( class_exists( 'RK_Tag_Text' ) ) { return; } // already handled by modern hook
		$this->register( $dynamic_tags );
	}

	/**
	 * Field options for a tag control, grouped by post type (Elementor optgroups)
	 * and filtered by field type. Repeater sub-fields are exposed as
	 * "repeater.subfield" tokens so they appear under their post type too.
	 */
	public static function field_options( $types = null ) {
		$out = array( '' => '— Select field —' );
		if ( ! class_exists( 'RK_Field_Engine' ) ) { return $out; }

		// Collect { post_type => { fieldKey => plainLabel } }.
		$by_pt = array();
		foreach ( RK_Field_Engine::all_groups() as $g ) {
			$pts = isset( $g['post_types'] ) && is_array( $g['post_types'] ) ? $g['post_types'] : array();
			if ( ! $pts ) { $pts = array( '_none' ); }
			foreach ( (array) ( isset( $g['fields'] ) ? $g['fields'] : array() ) as $f ) {
				$entries = self::field_entries( $f, $types );
				if ( ! $entries ) { continue; }
				foreach ( $pts as $pt ) {
					if ( ! isset( $by_pt[ $pt ] ) ) { $by_pt[ $pt ] = array(); }
					$by_pt[ $pt ] += $entries;
				}
			}
		}
		if ( ! $by_pt ) { return $out; }

		// Order by CPT registration order, then leftovers. Flat list, prefixed by
		// post type so all of one type's fields sit together (Elementor's SELECT
		// control is flat — no optgroups). Value is post_type|key to stay unique.
		$order = array();
		if ( class_exists( 'RK_CPT_Builder' ) ) {
			foreach ( RK_CPT_Builder::all() as $c ) { if ( ! empty( $c['slug'] ) ) { $order[] = $c['slug']; } }
		}
		foreach ( array_keys( $by_pt ) as $pt ) { if ( ! in_array( $pt, $order, true ) ) { $order[] = $pt; } }

		foreach ( $order as $pt ) {
			if ( empty( $by_pt[ $pt ] ) ) { continue; }
			$ptl = ( '_none' === $pt ) ? 'Other' : self::pt_label( $pt );
			foreach ( $by_pt[ $pt ] as $key => $label ) {
				$out[ $pt . '|' . $key ] = $ptl . ' — ' . $label;
			}
		}
		return $out;
	}

	/** Build the option entries for one field (incl. repeater sub-fields). */
	private static function field_entries( $f, $types ) {
		$out  = array();
		$type = isset( $f['type'] ) ? $f['type'] : 'text';
		$key  = isset( $f['key'] ) ? $f['key'] : '';
		if ( '' === $key ) { return $out; }
		$flabel = isset( $f['label'] ) ? $f['label'] : $key;

		if ( 'repeater' === $type ) {
			foreach ( (array) ( isset( $f['subfields'] ) ? $f['subfields'] : array() ) as $sf ) {
				$st = isset( $sf['type'] ) ? $sf['type'] : 'text';
				$sk = isset( $sf['key'] ) ? $sf['key'] : '';
				if ( '' === $sk ) { continue; }
				if ( $types && ! in_array( $st, (array) $types, true ) ) { continue; }
				$slabel = isset( $sf['label'] ) ? $sf['label'] : $sk;
				$out[ $key . '.' . $sk ] = $flabel . ' → ' . $slabel . ' (' . $st . ')';
			}
			return $out;
		}
		if ( $types && ! in_array( $type, (array) $types, true ) ) { return $out; }
		$out[ $key ] = $flabel . ' (' . $type . ')';
		return $out;
	}

	/** Human label for a post type slug. */
	private static function pt_label( $pt ) {
		if ( class_exists( 'RK_CPT_Builder' ) ) {
			$def = RK_CPT_Builder::get( $pt );
			if ( $def && ! empty( $def['plural'] ) ) { return $def['plural']; }
		}
		$obj = get_post_type_object( $pt );
		if ( $obj && isset( $obj->labels->name ) && $obj->labels->name ) { return $obj->labels->name; }
		return ucwords( str_replace( array( '_', '-' ), ' ', $pt ) );
	}

	/**
	 * Resolve a tag value key to a string. Handles plain field keys and
	 * "repeater.subfield" tokens (joins each row's sub-value).
	 */
	public static function real_key( $val ) {
		$val = (string) $val;
		if ( false !== strpos( $val, '|' ) ) { $val = substr( $val, strpos( $val, '|' ) + 1 ); }
		return $val;
	}

	/** Value of a field/sub-field from the active repeater row, or null. */
	public static function row_value( $key ) {
		if ( ! class_exists( 'RK_Core_Listings' ) ) { return null; }
		$row = RK_Core_Listings::current_row();
		if ( ! is_array( $row ) ) { return null; }
		$key = self::real_key( $key );
		$sub = ( false !== strpos( $key, '.' ) ) ? substr( $key, strpos( $key, '.' ) + 1 ) : $key;
		return array_key_exists( $sub, $row ) ? $row[ $sub ] : null;
	}

	public static function resolve_value( $key, $post_id = 0 ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		$key = self::real_key( $key );
		// Active repeater row wins (JetEngine-style loop context).
		$rv = self::row_value( $key );
		if ( null !== $rv ) { return is_array( $rv ) ? implode( ', ', array_map( 'strval', $rv ) ) : (string) $rv; }
		if ( '' === (string) $key || ! $post_id ) { return ''; }
		if ( false !== strpos( $key, '.' ) ) {
			list( $rk, $sk ) = explode( '.', $key, 2 );
			$rows = get_post_meta( $post_id, $rk, true );
			if ( ! is_array( $rows ) ) { return ''; }
			$vals = array();
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && isset( $row[ $sk ] ) && '' !== (string) $row[ $sk ] ) { $vals[] = (string) $row[ $sk ]; }
			}
			return implode( ', ', $vals );
		}
		$v = get_post_meta( $post_id, $key, true );
		return is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v;
	}

	/** Scalar field types usable as text (repeater sub-fields inherit these). */
	public static function text_types() {
		return array( 'text', 'textarea', 'wysiwyg', 'number', 'date', 'time', 'select', 'radio', 'checkbox', 'checklist', 'switcher', 'color', 'email', 'icon' );
	}
}
