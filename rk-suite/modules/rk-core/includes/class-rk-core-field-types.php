<?php
/**
 * RK_Core_Field_Types — the built-in field type registry.
 *
 * Each type provides a render callback (prints the admin control) and a sanitize
 * callback (cleans the submitted value for storage). Third parties can add or
 * override types via the `rk_core_field_types` filter.
 *
 * Input naming convention: every control is named rk_fields[{key}] (repeaters
 * use rk_fields[{key}][{row}][{subkey}]), so the engine can read them all from
 * a single $_POST['rk_fields'] array and store each field under its own,
 * queryable meta key.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core_Field_Types {

	/** type => [ label, render(callable), sanitize(callable), simple(bool) ]. */
	public static function registry() {
		$types = array(
			'text'     => array( 'label' => 'Text',      'render' => array( __CLASS__, 'render_text' ),     'sanitize' => array( __CLASS__, 'san_text' ) ),
			'textarea' => array( 'label' => 'Textarea',  'render' => array( __CLASS__, 'render_textarea' ), 'sanitize' => array( __CLASS__, 'san_textarea' ) ),
			'wysiwyg'  => array( 'label' => 'WYSIWYG',    'render' => array( __CLASS__, 'render_wysiwyg' ),  'sanitize' => array( __CLASS__, 'san_wysiwyg' ) ),
			'number'   => array( 'label' => 'Number',    'render' => array( __CLASS__, 'render_number' ),   'sanitize' => array( __CLASS__, 'san_number' ) ),
			'date'     => array( 'label' => 'Date',      'render' => array( __CLASS__, 'render_date' ),     'sanitize' => array( __CLASS__, 'san_text' ) ),
			'select'   => array( 'label' => 'Select',    'render' => array( __CLASS__, 'render_select' ),   'sanitize' => array( __CLASS__, 'san_text' ) ),
			'checkbox' => array( 'label' => 'Checkbox',  'render' => array( __CLASS__, 'render_checkbox' ), 'sanitize' => array( __CLASS__, 'san_checkbox' ) ),
			'image'    => array( 'label' => 'Image',     'render' => array( __CLASS__, 'render_image' ),    'sanitize' => array( __CLASS__, 'san_id' ) ),
			'gallery'  => array( 'label' => 'Gallery',   'render' => array( __CLASS__, 'render_gallery' ),  'sanitize' => array( __CLASS__, 'san_ids' ) ),
			'relation' => array( 'label' => 'Relation',  'render' => array( __CLASS__, 'render_relation' ), 'sanitize' => array( __CLASS__, 'san_ids' ) ),
			'radio'    => array( 'label' => 'Radio',     'render' => array( __CLASS__, 'render_radio' ),    'sanitize' => array( __CLASS__, 'san_text' ) ),
			'checklist'=> array( 'label' => 'Checklist', 'render' => array( __CLASS__, 'render_checklist' ),'sanitize' => array( __CLASS__, 'san_list' ) ),
			'color'    => array( 'label' => 'Colour',    'render' => array( __CLASS__, 'render_color' ),    'sanitize' => array( __CLASS__, 'san_color' ) ),
			'url'      => array( 'label' => 'URL',       'render' => array( __CLASS__, 'render_url' ),      'sanitize' => array( __CLASS__, 'san_url' ) ),
			'email'    => array( 'label' => 'Email',     'render' => array( __CLASS__, 'render_email' ),    'sanitize' => array( __CLASS__, 'san_email' ) ),
			'switcher' => array( 'label' => 'True / False', 'render' => array( __CLASS__, 'render_switcher' ), 'sanitize' => array( __CLASS__, 'san_checkbox' ) ),
			'time'     => array( 'label' => 'Time',      'render' => array( __CLASS__, 'render_time' ),     'sanitize' => array( __CLASS__, 'san_text' ) ),
			'oembed'   => array( 'label' => 'oEmbed',    'render' => array( __CLASS__, 'render_oembed' ),   'sanitize' => array( __CLASS__, 'san_url' ) ),
			'icon'     => array( 'label' => 'Icon',      'render' => array( __CLASS__, 'render_icon' ),     'sanitize' => array( __CLASS__, 'san_text' ) ),
			'repeater' => array( 'label' => 'Repeater',  'render' => array( __CLASS__, 'render_repeater' ), 'sanitize' => array( __CLASS__, 'san_repeater' ) ),
		);
		return apply_filters( 'rk_core_field_types', $types );
	}

	public static function exists( $type ) {
		$r = self::registry();
		return isset( $r[ $type ] );
	}

	public static function render( $field, $value ) {
		$r = self::registry();
		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		if ( ! isset( $r[ $type ] ) ) { $type = 'text'; }
		$name = 'rk_fields[' . $field['key'] . ']';
		call_user_func( $r[ $type ]['render'], $name, $value, $field );
	}

	public static function sanitize( $field, $raw ) {
		$r = self::registry();
		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		if ( ! isset( $r[ $type ] ) ) { $type = 'text'; }
		return call_user_func( $r[ $type ]['sanitize'], $raw, $field );
	}

	/* ---------------- render callbacks ---------------- */

	public static function render_text( $name, $value, $field ) {
		echo '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '" />';
	}

	public static function render_textarea( $name, $value, $field ) {
		echo '<textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( is_scalar( $value ) ? $value : '' ) . '</textarea>';
	}

	public static function render_wysiwyg( $name, $value, $field ) {
		$id = 'rkw_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
		wp_editor( is_scalar( $value ) ? $value : '', $id, array(
			'textarea_name' => $name,
			'media_buttons' => true,
			'textarea_rows' => 8,
		) );
	}

	public static function render_number( $name, $value, $field ) {
		echo '<input type="number" step="any" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '" />';
	}

	public static function render_date( $name, $value, $field ) {
		echo '<input type="date" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '" />';
	}

	public static function render_select( $name, $value, $field ) {
		$choices = self::parse_choices( $field );
		echo '<select name="' . esc_attr( $name ) . '">';
		echo '<option value="">— select —</option>';
		foreach ( $choices as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( $value, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public static function render_checkbox( $name, $value, $field ) {
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', $value, false ) . ' /> ' . esc_html( isset( $field['label'] ) ? $field['label'] : 'Yes' ) . '</label>';
	}

	public static function render_image( $name, $value, $field ) {
		$id = absint( $value );
		$src = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
		echo '<div class="rk-field-image">';
		echo '<input type="hidden" class="rk-image-id" name="' . esc_attr( $name ) . '" value="' . esc_attr( $id ) . '" />';
		echo '<div class="rk-image-preview">' . ( $src ? '<img src="' . esc_url( $src ) . '" style="max-width:120px;height:auto;" />' : '' ) . '</div>';
		echo '<button type="button" class="button rk-image-pick">Choose image</button> ';
		echo '<button type="button" class="button rk-image-clear">Remove</button>';
		echo '</div>';
	}

	public static function render_gallery( $name, $value, $field ) {
		$ids = self::to_ids( $value );
		echo '<div class="rk-field-gallery">';
		echo '<input type="hidden" class="rk-gallery-ids" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '" />';
		echo '<div class="rk-gallery-preview">';
		foreach ( $ids as $gid ) {
			$src = wp_get_attachment_image_url( $gid, 'thumbnail' );
			if ( $src ) { echo '<img src="' . esc_url( $src ) . '" style="max-width:70px;height:auto;margin:2px;" />'; }
		}
		echo '</div>';
		echo '<button type="button" class="button rk-gallery-pick">Choose images</button> ';
		echo '<button type="button" class="button rk-gallery-clear">Clear</button>';
		echo '</div>';
	}

	public static function render_relation( $name, $value, $field ) {
		$ids = self::to_ids( $value );
		$ptype = isset( $field['post_type'] ) && $field['post_type'] ? sanitize_key( $field['post_type'] ) : 'post';
		$posts = get_posts( array( 'post_type' => $ptype, 'numberposts' => 200, 'post_status' => array( 'publish', 'draft', 'private' ), 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<select multiple size="6" class="rk-relation" name="' . esc_attr( $name ) . '[]" style="min-width:280px;">';
		foreach ( $posts as $p ) {
			$sel = in_array( $p->ID, $ids, true ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $p->ID ) . '"' . $sel . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">Target post type: <code>' . esc_html( $ptype ) . '</code>. Hold Ctrl/Cmd to select several.</p>';
	}

	public static function render_repeater( $name, $value, $field ) {
		$subfields = isset( $field['subfields'] ) && is_array( $field['subfields'] ) ? $field['subfields'] : array();
		$rows = is_array( $value ) ? $value : array();
		echo '<div class="rk-repeater" data-name="' . esc_attr( $name ) . '">';
		echo '<div class="rk-repeater-rows">';
		$i = 0;
		foreach ( $rows as $row ) {
			self::repeater_row( $name, $i, $subfields, is_array( $row ) ? $row : array() );
			$i++;
		}
		echo '</div>';
		// hidden template for JS cloning
		echo '<script type="text/html" class="rk-repeater-tpl">';
		self::repeater_row( $name, '__i__', $subfields, array() );
		echo '</script>';
		echo '<p><button type="button" class="button rk-repeater-add">+ Add row</button></p>';
		echo '</div>';
	}

	private static function repeater_row( $base, $i, $subfields, $rowvals ) {
		echo '<div class="rk-repeater-row">';
		foreach ( $subfields as $sf ) {
			if ( empty( $sf['key'] ) ) { continue; }
			$k = sanitize_key( $sf['key'] );
			$label = isset( $sf['label'] ) ? $sf['label'] : $k;
			$type = isset( $sf['type'] ) ? $sf['type'] : 'text';
			$val = isset( $rowvals[ $k ] ) ? $rowvals[ $k ] : '';
			$fname = $base . '[' . $i . '][' . $k . ']';
			echo '<div class="rk-repeater-cell"><label>' . esc_html( $label ) . '</label> ';
			if ( 'textarea' === $type ) {
				echo '<textarea rows="2" name="' . esc_attr( $fname ) . '">' . esc_textarea( is_scalar( $val ) ? $val : '' ) . '</textarea>';
			} elseif ( 'number' === $type ) {
				echo '<input type="number" step="any" name="' . esc_attr( $fname ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '" />';
			} else {
				echo '<input type="text" name="' . esc_attr( $fname ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '" />';
			}
			echo '</div>';
		}
		echo '<button type="button" class="button-link rk-repeater-remove" style="color:#b42318;">Remove row</button>';
		echo '</div>';
	}

	/* ---------------- sanitize callbacks ---------------- */

	public static function san_text( $raw, $field ) { return sanitize_text_field( is_scalar( $raw ) ? $raw : '' ); }
	public static function san_textarea( $raw, $field ) { return sanitize_textarea_field( is_scalar( $raw ) ? $raw : '' ); }
	public static function san_wysiwyg( $raw, $field ) { return wp_kses_post( is_scalar( $raw ) ? $raw : '' ); }
	public static function san_number( $raw, $field ) { return ( is_scalar( $raw ) && is_numeric( $raw ) ) ? $raw + 0 : ''; }
	public static function san_checkbox( $raw, $field ) { return ! empty( $raw ) ? '1' : ''; }
	public static function san_id( $raw, $field ) { return absint( $raw ); }

	public static function san_ids( $raw, $field ) {
		$ids = self::to_ids( $raw );
		return $ids;
	}

	public static function san_repeater( $raw, $field ) {
		if ( ! is_array( $raw ) ) { return array(); }
		$subfields = isset( $field['subfields'] ) && is_array( $field['subfields'] ) ? $field['subfields'] : array();
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$clean = array();
			$has = false;
			foreach ( $subfields as $sf ) {
				if ( empty( $sf['key'] ) ) { continue; }
				$k = sanitize_key( $sf['key'] );
				$type = isset( $sf['type'] ) ? $sf['type'] : 'text';
				$v = isset( $row[ $k ] ) ? $row[ $k ] : '';
				if ( 'textarea' === $type ) { $clean[ $k ] = sanitize_textarea_field( $v ); }
				elseif ( 'number' === $type ) { $clean[ $k ] = is_numeric( $v ) ? $v + 0 : ''; }
				else { $clean[ $k ] = sanitize_text_field( $v ); }
				if ( '' !== $clean[ $k ] && array() !== $clean[ $k ] ) { $has = true; }
			}
			if ( $has ) { $out[] = $clean; }
		}
		return $out;
	}

	/* ---------------- extra field types (v1.1) ---------------- */

	public static function render_radio( $name, $value, $field ) {
		$choices = self::parse_choices( $field );
		echo '<div class="rk-radios">';
		foreach ( $choices as $val => $label ) {
			echo '<label class="rk-radio"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" ' . checked( $value, $val, false ) . ' /> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
	}

	public static function render_checklist( $name, $value, $field ) {
		$choices = self::parse_choices( $field );
		$vals = is_array( $value ) ? $value : ( '' !== $value ? array( $value ) : array() );
		echo '<div class="rk-checklist">';
		foreach ( $choices as $val => $label ) {
			echo '<label class="rk-check"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $val ) . '" ' . checked( in_array( (string) $val, array_map( 'strval', $vals ), true ), true, false ) . ' /> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
	}

	public static function render_color( $name, $value, $field ) {
		$v = is_string( $value ) && '' !== $value ? $value : '';
		echo '<span class="rk-colorwrap">';
		echo '<input type="color" class="rk-colorpick" value="' . esc_attr( $v ? $v : '#009687' ) . '" />';
		echo '<input type="text" class="rk-colortext" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '" placeholder="#009687" style="width:110px;" />';
		echo '</span>';
	}

	public static function render_url( $name, $value, $field ) {
		echo '<input type="url" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '" placeholder="https://" />';
	}

	public static function render_email( $name, $value, $field ) {
		echo '<input type="email" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '" placeholder="name@example.com" />';
	}

	public static function render_switcher( $name, $value, $field ) {
		$on = ( '1' === (string) $value );
		echo '<label class="rk-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $on, true, false ) . ' /><span class="rk-switch-track"><span class="rk-switch-thumb"></span></span><span class="rk-switch-label">' . esc_html( isset( $field['label'] ) ? $field['label'] : '' ) . '</span></label>';
	}

	public static function render_time( $name, $value, $field ) {
		echo '<input type="time" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '" />';
	}

	public static function render_oembed( $name, $value, $field ) {
		$v = is_scalar( $value ) ? (string) $value : '';
		echo '<input type="url" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '" placeholder="https://youtube.com/watch?v=..." />';
		if ( $v ) {
			$html = wp_oembed_get( $v );
			if ( $html ) { echo '<div class="rk-oembed-preview">' . $html . '</div>'; }
		}
	}

	public static function render_icon( $name, $value, $field ) {
		$v = is_scalar( $value ) ? (string) $value : '';
		echo '<span class="rk-iconwrap">';
		echo '<span class="rk-icon-preview dashicons ' . esc_attr( $v ? $v : 'dashicons-star-filled' ) . '"></span>';
		echo '<input type="text" class="rk-iconinput regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '" placeholder="dashicons-star-filled" list="rk-icon-list" />';
		echo '</span>';
	}

	public static function san_list( $raw, $field ) {
		if ( ! is_array( $raw ) ) { return array(); }
		$out = array();
		foreach ( $raw as $v ) { $v = sanitize_text_field( is_scalar( $v ) ? $v : '' ); if ( '' !== $v ) { $out[] = $v; } }
		return $out;
	}

	public static function san_color( $raw, $field ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $raw ) ? $raw : '';
	}

	public static function san_url( $raw, $field ) {
		return esc_url_raw( is_scalar( $raw ) ? (string) $raw : '' );
	}

	public static function san_email( $raw, $field ) {
		$e = sanitize_email( is_scalar( $raw ) ? (string) $raw : '' );
		return is_email( $e ) ? $e : '';
	}

	/* ---------------- helpers ---------------- */

	public static function parse_choices( $field ) {
		$out = array();
		$raw = isset( $field['choices'] ) ? $field['choices'] : '';
		if ( is_array( $raw ) ) { return $raw; }
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) { continue; }
			if ( false !== strpos( $line, ':' ) ) {
				$parts = explode( ':', $line, 2 );
				$out[ trim( $parts[0] ) ] = trim( $parts[1] );
			} else {
				$out[ $line ] = $line;
			}
		}
		return $out;
	}

	public static function to_ids( $value ) {
		if ( is_array( $value ) ) { $arr = $value; }
		elseif ( is_string( $value ) && '' !== $value ) { $arr = explode( ',', $value ); }
		else { $arr = array(); }
		$out = array();
		foreach ( $arr as $v ) { $v = absint( $v ); if ( $v ) { $out[] = $v; } }
		return $out;
	}
}
