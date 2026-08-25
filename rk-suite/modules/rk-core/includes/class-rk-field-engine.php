<?php
/**
 * RK_Field_Engine — custom field groups attached to post types.
 *
 * Group definitions live in a single JSON option (portable / exportable). Each
 * field is stored on the post under its OWN meta key so values stay queryable
 * with meta_query and exposed to the REST API via register_post_meta — a
 * deliberate improvement over an opaque single serialized blob.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Field_Engine {

	const OPTION = 'rk_core_field_groups';
	const NONCE  = 'rk_core_fields_save';

	/* ---------------- group storage ---------------- */

	public static function all_groups() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	public static function get_group( $id ) {
		$id = sanitize_key( $id );
		foreach ( self::all_groups() as $g ) {
			if ( isset( $g['id'] ) && $g['id'] === $id ) { return $g; }
		}
		return null;
	}

	public static function save_group( $input ) {
		$g = self::sanitize_group( $input );
		if ( is_wp_error( $g ) ) { return $g; }
		$all = self::all_groups();
		$found = false;
		foreach ( $all as $i => $existing ) {
			if ( $existing['id'] === $g['id'] ) { $all[ $i ] = $g; $found = true; break; }
		}
		if ( ! $found ) { $all[] = $g; }
		update_option( self::OPTION, array_values( $all ) );
		return $g['id'];
	}

	public static function delete_group( $id ) {
		$id = sanitize_key( $id );
		$all = array();
		foreach ( self::all_groups() as $g ) {
			if ( $g['id'] !== $id ) { $all[] = $g; }
		}
		update_option( self::OPTION, array_values( $all ) );
	}

	public static function sanitize_group( $in ) {
		$id = isset( $in['id'] ) && '' !== $in['id'] ? sanitize_key( $in['id'] ) : sanitize_key( uniqid( 'grp_' ) );
		$title = isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : 'Field Group';

		$post_types = array();
		if ( isset( $in['post_types'] ) && is_array( $in['post_types'] ) ) {
			foreach ( $in['post_types'] as $pt ) { $post_types[] = sanitize_key( $pt ); }
		}
		$post_types = array_values( array_unique( array_filter( $post_types ) ) );
		if ( empty( $post_types ) ) { return new WP_Error( 'pt', 'Select at least one post type for the group.' ); }

		$fields = array();
		$raw_fields = isset( $in['fields'] ) && is_array( $in['fields'] ) ? $in['fields'] : array();
		$seen = array();
		foreach ( $raw_fields as $f ) {
			$key = isset( $f['key'] ) ? sanitize_key( $f['key'] ) : '';
			if ( '' === $key || isset( $seen[ $key ] ) ) { continue; }
			$type = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text';
			if ( ! RK_Core_Field_Types::exists( $type ) ) { $type = 'text'; }
			$field = array(
				'key'          => $key,
				'label'        => isset( $f['label'] ) ? sanitize_text_field( $f['label'] ) : $key,
				'type'         => $type,
				'instructions' => isset( $f['instructions'] ) ? sanitize_text_field( $f['instructions'] ) : '',
				'choices'      => isset( $f['choices'] ) ? wp_kses_post( $f['choices'] ) : '',
				'post_type'    => isset( $f['post_type'] ) ? sanitize_key( $f['post_type'] ) : '',
			);
			if ( isset( $f['condition_text'] ) && '' !== trim( (string) $f['condition_text'] ) ) {
				$cond = self::parse_condition( $f['condition_text'] );
				if ( $cond ) { $field['condition'] = $cond; }
			}
			if ( 'repeater' === $type ) {
				$subsource = array();
				if ( isset( $f['subfields'] ) && is_array( $f['subfields'] ) ) {
					$subsource = $f['subfields'];
				} elseif ( isset( $f['subfields_text'] ) && '' !== trim( (string) $f['subfields_text'] ) ) {
					foreach ( preg_split( '/\r\n|\r|\n/', $f['subfields_text'] ) as $line ) {
						$line = trim( $line );
						if ( '' === $line ) { continue; }
						$parts = explode( ':', $line );
						$subsource[] = array(
							'key'   => isset( $parts[0] ) ? $parts[0] : '',
							'label' => isset( $parts[1] ) ? $parts[1] : ( isset( $parts[0] ) ? $parts[0] : '' ),
							'type'  => isset( $parts[2] ) ? $parts[2] : 'text',
						);
					}
				}
				$subs = array();
				foreach ( $subsource as $sf ) {
					$sk = isset( $sf['key'] ) ? sanitize_key( $sf['key'] ) : '';
					if ( '' === $sk ) { continue; }
					$st = isset( $sf['type'] ) ? sanitize_key( $sf['type'] ) : 'text';
					if ( ! in_array( $st, array( 'text', 'textarea', 'number' ), true ) ) { $st = 'text'; }
					$subs[] = array( 'key' => $sk, 'label' => isset( $sf['label'] ) ? sanitize_text_field( $sf['label'] ) : $sk, 'type' => $st );
				}
				$field['subfields'] = $subs;
			}
			$seen[ $key ] = true;
			$fields[] = $field;
		}

		return array( 'id' => $id, 'title' => $title, 'post_types' => $post_types, 'fields' => $fields );
	}

	/* ---------------- hooks ---------------- */

	/** Parse "otherkey == value" (or != / =) into a condition array. */
	private static function parse_condition( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) { return null; }
		if ( preg_match( '/^([a-z0-9_\-]+)\s*(==|!=|=)\s*(.+)$/i', $text, $m ) ) {
			$op = ( '!=' === $m[2] ) ? '!=' : '==';
			return array( 'field' => sanitize_key( $m[1] ), 'op' => $op, 'value' => sanitize_text_field( trim( $m[3] ) ) );
		}
		return null;
	}

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function add_boxes() {
		foreach ( self::all_groups() as $group ) {
			foreach ( $group['post_types'] as $pt ) {
				add_meta_box(
					'rk_group_' . $group['id'],
					$group['title'],
					array( $this, 'render_box' ),
					$pt,
					'normal',
					'default',
					array( 'group' => $group )
				);
			}
		}
	}

	public function render_box( $post, $box ) {
		$group = isset( $box['args']['group'] ) ? $box['args']['group'] : null;
		if ( ! $group ) { return; }
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<div class="rk-fields">';
		foreach ( $group['fields'] as $field ) {
			$value = get_post_meta( $post->ID, $field['key'], true );
			$cattr = ' data-rk-field="' . esc_attr( $field['key'] ) . '"';
			if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
				$c = $field['condition'];
				$cattr .= ' data-rk-cond-field="' . esc_attr( $c['field'] ) . '" data-rk-cond-op="' . esc_attr( $c['op'] ) . '" data-rk-cond-value="' . esc_attr( $c['value'] ) . '"';
			}
			echo '<div class="rk-field rk-field-' . esc_attr( $field['type'] ) . '"' . $cattr . '>';
			if ( 'checkbox' !== $field['type'] ) {
				echo '<label class="rk-field-label"><strong>' . esc_html( $field['label'] ) . '</strong> <code>' . esc_html( $field['key'] ) . '</code></label>';
			}
			if ( ! empty( $field['instructions'] ) ) {
				echo '<p class="description">' . esc_html( $field['instructions'] ) . '</p>';
			}
			echo '<div class="rk-field-control">';
			RK_Core_Field_Types::render( $field, $value );
			echo '</div></div>';
		}
		echo '</div>';
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) { return; }
		if ( ! wp_verify_nonce( $_POST[ self::NONCE . '_nonce' ], self::NONCE ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$submitted = isset( $_POST['rk_fields'] ) && is_array( $_POST['rk_fields'] ) ? wp_unslash( $_POST['rk_fields'] ) : array();

		foreach ( self::all_groups() as $group ) {
			if ( ! in_array( $post->post_type, $group['post_types'], true ) ) { continue; }
			foreach ( $group['fields'] as $field ) {
				$key = $field['key'];
				$raw = isset( $submitted[ $key ] ) ? $submitted[ $key ] : '';
				$clean = RK_Core_Field_Types::sanitize( $field, $raw );
				if ( '' === $clean || array() === $clean ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $clean );
				}
			}
		}
	}

	/** Expose every field to the REST API under its post type. */
	public function register_meta() {
		$auth = function () { return current_user_can( 'edit_posts' ); };
		foreach ( self::all_groups() as $group ) {
			foreach ( $group['post_types'] as $pt ) {
				foreach ( $group['fields'] as $field ) {
					$type = $field['type'];

					// Every field is stored as ONE post-meta row (a scalar, or a
					// single serialized array for gallery/relation), so single is
					// always true. Arrays need an explicit REST schema.
					$args = array(
						'single'        => true,
						'auth_callback' => $auth,
					);

					if ( in_array( $type, array( 'gallery', 'relation' ), true ) ) {
						$args['type']         = 'array';
						$args['show_in_rest'] = array(
							'schema' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'integer' ),
							),
						);
					} elseif ( 'checklist' === $type ) {
						$args['type']         = 'array';
						$args['show_in_rest'] = array(
							'schema' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
						);
					} elseif ( 'repeater' === $type ) {
						// Repeater is an array of arbitrary objects; a strict REST
						// schema is impractical, so it is stored but not auto-exposed.
						$args['type']         = 'array';
						$args['show_in_rest'] = false;
					} else {
						$args['type']         = self::rest_scalar_type( $type );
						$args['show_in_rest'] = true;
					}

					register_post_meta( $pt, $field['key'], $args );
				}
			}
		}
	}

	private static function rest_scalar_type( $type ) {
		if ( 'number' === $type ) { return 'number'; }
		if ( in_array( $type, array( 'image' ), true ) ) { return 'integer'; }
		if ( 'checkbox' === $type ) { return 'string'; }
		return 'string';
	}

	public function assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) { return; }
		wp_enqueue_media();
		$css = RK_CORE_DIR . 'assets/admin.css';
		$js  = RK_CORE_DIR . 'assets/fields.js';
		wp_enqueue_style( 'rk-core-admin', RK_CORE_URL . 'assets/admin.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_CORE_VERSION );
		wp_enqueue_script( 'rk-core-fields', RK_CORE_URL . 'assets/fields.js', array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : RK_CORE_VERSION, true );
	}
}
