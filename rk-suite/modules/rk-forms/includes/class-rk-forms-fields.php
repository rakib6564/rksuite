<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Forms_Fields — the field model. DSL parse/serialize, JSON normalize,
 * conditional-visibility evaluation, submission validation, and frontend
 * rendering. Ported from the source app's FormsAPI (WP-native, escaped).
 *
 * Phase 1 field types: text, email, tel, url, number, date, time, textarea,
 * select, radio, checkbox (single), checkboxes (multi), heading, hidden.
 */
class RK_Forms_Fields {

	/** Field types accepted in Phase 1. */
	const SUPPORTED_TYPES = array(
		'text', 'email', 'tel', 'url', 'number', 'date', 'time', 'textarea',
		'select', 'radio', 'checkbox', 'checkboxes', 'heading', 'hidden',
	);
	/** Display-only types that never collect a value. */
	const DISPLAY_TYPES   = array( 'heading' );
	const CONDITION_OPS   = array( 'eq', 'ne', 'filled', 'empty', 'gt', 'lt' );

	/* ---------------- DSL <-> field array ---------------- */

	/** Parse the textarea DSL "type|name|label|required|placeholder|options". */
	public static function parse_dsl( $text ) {
		$out = array(); $errors = array(); $used = array(); $ln = 0;
		foreach ( preg_split( '/\r\n|\n|\r/', (string) $text ) as $raw ) {
			$ln++;
			$line = trim( $raw );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) { continue; }

			$parts = array_map( 'trim', explode( '|', $line ) );
			$type  = strtolower( isset( $parts[0] ) ? $parts[0] : '' );
			$name  = isset( $parts[1] ) ? $parts[1] : '';
			$label = isset( $parts[2] ) ? $parts[2] : '';
			$req   = 'required' === strtolower( isset( $parts[3] ) ? $parts[3] : '' );
			$ph    = isset( $parts[4] ) ? $parts[4] : '';
			$opts_raw = isset( $parts[5] ) ? $parts[5] : '';

			if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) { $errors[] = "Line {$ln}: unsupported field type '{$type}'."; continue; }
			if ( ! preg_match( '/^[a-z][a-z0-9_]{0,49}$/i', $name ) ) { $errors[] = "Line {$ln}: name must start with a letter, letters/digits/underscores only."; continue; }
			$name = strtolower( $name );
			if ( isset( $used[ $name ] ) ) { $errors[] = "Line {$ln}: duplicate field name '{$name}'."; continue; }
			$used[ $name ] = true;

			$field = array(
				'type'     => $type,
				'name'     => $name,
				'label'    => '' !== $label ? $label : ucwords( str_replace( '_', ' ', $name ) ),
				'required' => $req,
			);
			if ( '' !== $ph ) { $field['placeholder'] = $ph; }

			if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
				$o = '' !== $opts_raw ? array_values( array_filter( array_map( 'trim', explode( ',', $opts_raw ) ), function ( $s ) { return '' !== $s; } ) ) : array();
				if ( empty( $o ) ) { $errors[] = "Line {$ln}: {$type} field '{$name}' needs a comma-separated options list."; continue; }
				$field['options'] = $o;
			}
			$out[] = $field;
		}
		return array( 'fields' => $out, 'errors' => $errors );
	}

	/** Serialize a field array back into the DSL text. */
	public static function to_dsl( $fields ) {
		$lines = array();
		foreach ( (array) $fields as $f ) {
			$parts = array(
				isset( $f['type'] ) ? $f['type'] : 'text',
				isset( $f['name'] ) ? $f['name'] : '',
				isset( $f['label'] ) ? $f['label'] : '',
				! empty( $f['required'] ) ? 'required' : '',
				isset( $f['placeholder'] ) ? $f['placeholder'] : '',
				isset( $f['options'] ) ? implode( ',', array_map( function ( $o ) { return is_array( $o ) ? (string) ( isset( $o['value'] ) ? $o['value'] : '' ) : (string) $o; }, (array) $f['options'] ) ) : '',
			);
			while ( count( $parts ) > 3 && '' === end( $parts ) ) { array_pop( $parts ); }
			$lines[] = implode( '|', $parts );
		}
		return implode( "\n", $lines );
	}

	/** Normalize a decoded fields array (from the visual builder JSON). */
	public static function normalize( $arr ) {
		$out = array(); $used = array();
		if ( ! is_array( $arr ) ) { return array(); }
		foreach ( $arr as $f ) {
			if ( ! is_array( $f ) ) { continue; }
			$type = strtolower( (string) ( isset( $f['type'] ) ? $f['type'] : '' ) );
			if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) { continue; }
			$name = strtolower( trim( (string) ( isset( $f['name'] ) ? $f['name'] : '' ) ) );
			if ( ! preg_match( '/^[a-z][a-z0-9_]{0,49}$/', $name ) || isset( $used[ $name ] ) ) {
				$name = self::unique_name( (string) ( isset( $f['label'] ) ? $f['label'] : $type ), $used );
			}
			$used[ $name ] = true;

			$field = array(
				'type'     => $type,
				'name'     => $name,
				'label'    => (string) ( isset( $f['label'] ) ? $f['label'] : ucwords( str_replace( '_', ' ', $name ) ) ),
				'required' => ! empty( $f['required'] ),
			);
			$ph = trim( (string) ( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ) );
			if ( '' !== $ph ) { $field['placeholder'] = $ph; }

			if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
				$opts = array();
				foreach ( $f['options'] as $o ) {
					$s = is_array( $o ) ? trim( (string) ( isset( $o['value'] ) ? $o['value'] : ( isset( $o['label'] ) ? $o['label'] : '' ) ) ) : trim( (string) $o );
					if ( '' !== $s ) { $opts[] = $s; }
				}
				if ( $opts ) { $field['options'] = $opts; }
			}

			if ( ! empty( $f['show_if'] ) && is_array( $f['show_if'] ) && ! empty( $f['show_if']['field'] ) ) {
				$op = (string) ( isset( $f['show_if']['op'] ) ? $f['show_if']['op'] : 'eq' );
				if ( in_array( $op, self::CONDITION_OPS, true ) ) {
					$field['show_if'] = array(
						'field' => strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $f['show_if']['field'] ) ),
						'op'    => $op,
						'value' => (string) ( isset( $f['show_if']['value'] ) ? $f['show_if']['value'] : '' ),
					);
				}
			}
			$out[] = $field;
		}
		return $out;
	}

	private static function unique_name( $seed, &$used ) {
		$base = preg_replace( '/[^a-z0-9_]/', '', strtolower( str_replace( array( ' ', '-' ), '_', $seed ) ) );
		if ( '' === $base || ! preg_match( '/^[a-z]/', $base ) ) { $base = 'field_' . $base; }
		$name = $base; $n = 2;
		while ( isset( $used[ $name ] ) ) { $name = $base . '_' . $n; $n++; }
		return $name;
	}

	/** Decode a form row's fields_json into a field array. */
	public static function decode( $json ) {
		$arr = json_decode( (string) $json, true );
		return is_array( $arr ) ? $arr : array();
	}

	/* ---------------- conditional visibility ---------------- */

	public static function condition_met( $field, $input ) {
		$cond = isset( $field['show_if'] ) ? $field['show_if'] : null;
		if ( ! is_array( $cond ) || empty( $cond['field'] ) ) { return true; }
		$other = isset( $input[ $cond['field'] ] ) ? $input[ $cond['field'] ] : null;
		$cur   = is_array( $other ) ? '' : trim( (string) ( null === $other ? '' : $other ) );
		$val   = trim( (string) ( isset( $cond['value'] ) ? $cond['value'] : '' ) );
		switch ( isset( $cond['op'] ) ? $cond['op'] : 'eq' ) {
			case 'eq':     return $cur === $val;
			case 'ne':     return $cur !== $val;
			case 'filled': return '' !== $cur;
			case 'empty':  return '' === $cur;
			case 'gt':     return is_numeric( $cur ) && is_numeric( $val ) && (float) $cur > (float) $val;
			case 'lt':     return is_numeric( $cur ) && is_numeric( $val ) && (float) $cur < (float) $val;
		}
		return true;
	}

	/* ---------------- validation ---------------- */

	/** Validate + sanitize a submission. Returns array('data'=>..., 'errors'=>...). */
	public static function validate( $fields, $input ) {
		$errors = array(); $data = array();
		foreach ( (array) $fields as $f ) {
			$name = isset( $f['name'] ) ? $f['name'] : '';
			if ( '' === $name ) { continue; }
			$type = isset( $f['type'] ) ? $f['type'] : 'text';
			if ( in_array( $type, self::DISPLAY_TYPES, true ) ) { continue; }
			if ( ! self::condition_met( $f, $input ) ) { continue; }
			$required = ! empty( $f['required'] );
			$label    = isset( $f['label'] ) ? $f['label'] : $name;
			$raw      = isset( $input[ $name ] ) ? $input[ $name ] : null;

			if ( 'checkbox' === $type ) {
				$checked = ! empty( $raw );
				if ( $required && ! $checked ) { $errors[ $name ] = $label . ' is required.'; }
				$data[ $name ] = $checked ? 1 : 0;
				continue;
			}
			if ( 'checkboxes' === $type ) {
				$opts = array_map( function ( $o ) { return is_array( $o ) ? (string) ( isset( $o['value'] ) ? $o['value'] : '' ) : (string) $o; }, (array) ( isset( $f['options'] ) ? $f['options'] : array() ) );
				$chosen = array();
				foreach ( (array) $raw as $r ) { $r = is_string( $r ) ? trim( $r ) : ''; if ( '' !== $r && in_array( $r, $opts, true ) ) { $chosen[] = $r; } }
				$chosen = array_values( array_unique( $chosen ) );
				if ( $required && ! $chosen ) { $errors[ $name ] = $label . ' is required.'; }
				$data[ $name ] = $chosen;
				continue;
			}

			$value = is_string( $raw ) ? trim( $raw ) : '';
			if ( '' === $value ) {
				if ( $required ) { $errors[ $name ] = $label . ' is required.'; }
				$data[ $name ] = '';
				continue;
			}

			switch ( $type ) {
				case 'email':
					if ( ! is_email( $value ) ) { $errors[ $name ] = $label . ' must be a valid email address.'; }
					$data[ $name ] = sanitize_email( $value );
					break;
				case 'url':
					$data[ $name ] = esc_url_raw( $value );
					if ( '' === $data[ $name ] ) { $errors[ $name ] = $label . ' must be a valid URL.'; }
					break;
				case 'number':
					if ( ! is_numeric( $value ) ) { $errors[ $name ] = $label . ' must be a number.'; }
					$data[ $name ] = is_numeric( $value ) ? $value + 0 : '';
					break;
				case 'select':
				case 'radio':
					$opts = array_map( function ( $o ) { return is_array( $o ) ? (string) ( isset( $o['value'] ) ? $o['value'] : '' ) : (string) $o; }, (array) ( isset( $f['options'] ) ? $f['options'] : array() ) );
					if ( ! in_array( $value, $opts, true ) ) { $errors[ $name ] = $label . ' has an invalid selection.'; }
					$data[ $name ] = sanitize_text_field( $value );
					break;
				case 'textarea':
					$data[ $name ] = sanitize_textarea_field( $value );
					break;
				case 'tel':
					$data[ $name ] = sanitize_text_field( $value );
					break;
				case 'date':
				case 'time':
					$data[ $name ] = sanitize_text_field( $value );
					break;
				case 'hidden':
				default:
					$data[ $name ] = sanitize_text_field( $value );
			}
		}
		return array( 'data' => $data, 'errors' => $errors );
	}

	/* ---------------- frontend rendering ---------------- */

	/** Render one field's HTML (escaped). $id_prefix scopes element ids. */
	public static function render_field( $f, $id_prefix ) {
		$type  = isset( $f['type'] ) ? $f['type'] : 'text';
		$name  = isset( $f['name'] ) ? $f['name'] : '';
		$label = isset( $f['label'] ) ? $f['label'] : '';
		$req   = ! empty( $f['required'] );
		$ph    = isset( $f['placeholder'] ) ? $f['placeholder'] : '';
		$fid   = $id_prefix . '-' . $name;
		$reqmark = $req ? ' <span class="rk-form-req">*</span>' : '';
		$reqattr = $req ? ' required' : '';

		$data_cond = '';
		if ( ! empty( $f['show_if']['field'] ) ) {
			$data_cond = ' data-rk-show-field="' . esc_attr( $f['show_if']['field'] ) . '"'
				. ' data-rk-show-op="' . esc_attr( isset( $f['show_if']['op'] ) ? $f['show_if']['op'] : 'eq' ) . '"'
				. ' data-rk-show-val="' . esc_attr( isset( $f['show_if']['value'] ) ? $f['show_if']['value'] : '' ) . '"';
		}

		if ( 'heading' === $type ) {
			$out  = '<div class="rk-form-heading"' . $data_cond . '><h3>' . esc_html( $label ) . '</h3>';
			if ( '' !== $ph ) { $out .= '<p>' . esc_html( $ph ) . '</p>'; }
			return $out . '</div>';
		}
		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $ph ) . '" />';
		}

		$out = '<div class="rk-form-row rk-form-row--' . esc_attr( $type ) . '"' . $data_cond . ' data-rk-name="' . esc_attr( $name ) . '">';

		if ( 'checkbox' === $type ) {
			$out .= '<label class="rk-form-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . $reqattr . ' /> <span>' . esc_html( $label ) . $reqmark . '</span></label>';
			return $out . '</div>';
		}

		$out .= '<label class="rk-form-label" for="' . esc_attr( $fid ) . '">' . esc_html( $label ) . $reqmark . '</label>';

		switch ( $type ) {
			case 'textarea':
				$out .= '<textarea id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" rows="4" placeholder="' . esc_attr( $ph ) . '"' . $reqattr . '></textarea>';
				break;
			case 'select':
				$out .= '<select id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '"' . $reqattr . '>';
				$out .= '<option value="">' . esc_html( '' !== $ph ? $ph : '— Select —' ) . '</option>';
				foreach ( (array) ( isset( $f['options'] ) ? $f['options'] : array() ) as $o ) {
					$v = is_array( $o ) ? ( isset( $o['value'] ) ? $o['value'] : '' ) : $o;
					$out .= '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
				}
				$out .= '</select>';
				break;
			case 'radio':
			case 'checkboxes':
				$input_type = ( 'radio' === $type ) ? 'radio' : 'checkbox';
				$fname = ( 'checkboxes' === $type ) ? $name . '[]' : $name;
				$out .= '<div class="rk-form-choices">';
				foreach ( (array) ( isset( $f['options'] ) ? $f['options'] : array() ) as $i => $o ) {
					$v   = is_array( $o ) ? ( isset( $o['value'] ) ? $o['value'] : '' ) : $o;
					$oid = $fid . '-' . $i;
					$out .= '<label class="rk-form-choice" for="' . esc_attr( $oid ) . '"><input type="' . $input_type . '" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $fname ) . '" value="' . esc_attr( $v ) . '"' . ( ( 'radio' === $type && $req ) ? ' required' : '' ) . ' /> <span>' . esc_html( $v ) . '</span></label>';
				}
				$out .= '</div>';
				break;
			default: // text, email, tel, url, number, date, time
				$out .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $ph ) . '"' . $reqattr . ' />';
		}
		return $out . '</div>';
	}
}
