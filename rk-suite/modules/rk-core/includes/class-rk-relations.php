<?php
/**
 * RK_Relations — post-to-post / post-to-user relations (JetEngine style).
 *
 * Relation definitions are stored as JSON; the actual links live in a custom
 * table so many-to-many scales. A metabox on the "from" object lets editors
 * link "to" items, and helpers fetch related objects in both directions.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Relations {

	const OPTION  = 'rk_core_relations';
	const DB      = 'rk_relations';
	const NONCE   = 'rk_core_rel_save';

	/* ---------------- table ---------------- */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::DB;
	}

	public static function install_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			rel_id VARCHAR(60) NOT NULL DEFAULT '',
			from_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			to_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY rel_from (rel_id, from_id),
			KEY rel_to (rel_id, to_id)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/* ---------------- definitions ---------------- */

	public static function all() {
		$d = get_option( self::OPTION, array() );
		return is_array( $d ) ? $d : array();
	}

	public static function get( $id ) {
		$id = sanitize_key( $id );
		foreach ( self::all() as $r ) {
			if ( isset( $r['id'] ) && $r['id'] === $id ) { return $r; }
		}
		return null;
	}

	public static function save( $input ) {
		$def = self::sanitize( $input );
		if ( is_wp_error( $def ) ) { return $def; }
		$all = self::all();
		$found = false;
		foreach ( $all as $i => $ex ) {
			if ( $ex['id'] === $def['id'] ) { $all[ $i ] = $def; $found = true; break; }
		}
		if ( ! $found ) { $all[] = $def; }
		update_option( self::OPTION, array_values( $all ) );
		return $def['id'];
	}

	public static function delete( $id ) {
		$id = sanitize_key( $id );
		$all = array();
		foreach ( self::all() as $r ) { if ( $r['id'] !== $id ) { $all[] = $r; } }
		update_option( self::OPTION, array_values( $all ) );
	}

	public static function sanitize( $in ) {
		$id   = isset( $in['id'] ) && '' !== $in['id'] ? sanitize_key( $in['id'] ) : sanitize_key( uniqid( 'rel_' ) );
		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : 'Relation';
		$from = isset( $in['from_object'] ) ? sanitize_key( $in['from_object'] ) : 'post';
		$to   = isset( $in['to_object'] ) ? sanitize_key( $in['to_object'] ) : 'post';
		$type = isset( $in['rel_type'] ) ? sanitize_key( $in['rel_type'] ) : 'many_to_many';
		if ( ! in_array( $type, array( 'one_to_one', 'one_to_many', 'many_to_many' ), true ) ) { $type = 'many_to_many'; }
		return array( 'id' => $id, 'name' => $name, 'from_object' => $from, 'to_object' => $to, 'rel_type' => $type );
	}

	/* ---------------- links ---------------- */

	/** Children linked from a parent for a relation. Returns array of IDs. */
	public static function get_children( $rel_id, $from_id ) {
		global $wpdb;
		$rel_id = sanitize_key( $rel_id );
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT to_id FROM " . self::table() . " WHERE rel_id=%s AND from_id=%d", $rel_id, (int) $from_id ) );
		return array_map( 'intval', (array) $rows );
	}

	/** Parents linked to a child for a relation. Returns array of IDs. */
	public static function get_parents( $rel_id, $to_id ) {
		global $wpdb;
		$rel_id = sanitize_key( $rel_id );
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT from_id FROM " . self::table() . " WHERE rel_id=%s AND to_id=%d", $rel_id, (int) $to_id ) );
		return array_map( 'intval', (array) $rows );
	}

	/** Replace all children of a parent with the given set (respecting type). */
	public static function set_children( $rel_id, $from_id, array $to_ids ) {
		global $wpdb;
		$rel_id  = sanitize_key( $rel_id );
		$from_id = (int) $from_id;
		$def     = self::get( $rel_id );
		$table   = self::table();

		$wpdb->delete( $table, array( 'rel_id' => $rel_id, 'from_id' => $from_id ), array( '%s', '%d' ) );
		$to_ids = array_values( array_unique( array_filter( array_map( 'intval', $to_ids ) ) ) );

		foreach ( $to_ids as $to_id ) {
			// one-to-* : a child can only have one parent — detach it elsewhere first.
			if ( $def && in_array( $def['rel_type'], array( 'one_to_one', 'one_to_many' ), true ) ) {
				$wpdb->delete( $table, array( 'rel_id' => $rel_id, 'to_id' => $to_id ), array( '%s', '%d' ) );
			}
			$wpdb->insert( $table, array( 'rel_id' => $rel_id, 'from_id' => $from_id, 'to_id' => $to_id ), array( '%s', '%d', '%d' ) );
		}
	}

	/* ---------------- object option lists ---------------- */

	/** Items of an object type (post type slug or 'user') as id => label. */
	public static function object_items( $object ) {
		$out = array();
		if ( 'user' === $object ) {
			foreach ( get_users( array( 'number' => 300, 'fields' => array( 'ID', 'display_name' ) ) ) as $u ) {
				$out[ (int) $u->ID ] = $u->display_name;
			}
			return $out;
		}
		$posts = get_posts( array( 'post_type' => $object, 'numberposts' => 300, 'post_status' => array( 'publish', 'draft', 'private' ), 'orderby' => 'title', 'order' => 'ASC' ) );
		foreach ( $posts as $p ) { $out[ (int) $p->ID ] = $p->post_title; }
		return $out;
	}

	/** Object types available to relate: public post types + user. */
	public static function object_choices() {
		$out = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $slug => $obj ) {
			if ( in_array( $slug, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ), true ) ) { continue; }
			$out[ $slug ] = $obj->labels->singular_name . ' (' . $slug . ')';
		}
		$out['user'] = 'User';
		return $out;
	}

	/* ---------------- metabox on the post editor ---------------- */

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post_links' ), 10, 2 );
	}

	public function add_boxes() {
		foreach ( self::all() as $rel ) {
			// Show the linker on the "from" post type (skip user objects here).
			if ( 'user' === $rel['from_object'] ) { continue; }
			add_meta_box(
				'rk_rel_' . $rel['id'],
				'Relation: ' . $rel['name'],
				array( $this, 'render_box' ),
				$rel['from_object'],
				'side',
				'default',
				array( 'rel' => $rel )
			);
		}
	}

	public function render_box( $post, $box ) {
		$rel = isset( $box['args']['rel'] ) ? $box['args']['rel'] : null;
		if ( ! $rel ) { return; }
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$linked = self::get_children( $rel['id'], $post->ID );
		$items  = self::object_items( $rel['to_object'] );
		echo '<p class="description">Link ' . esc_html( $rel['to_object'] ) . ' items to this ' . esc_html( $rel['from_object'] ) . '.</p>';
		echo '<select name="rk_rel[' . esc_attr( $rel['id'] ) . '][]" multiple size="6" style="width:100%;">';
		foreach ( $items as $id => $label ) {
			if ( (int) $id === (int) $post->ID && $rel['from_object'] === $rel['to_object'] ) { continue; }
			echo '<option value="' . esc_attr( $id ) . '" ' . ( in_array( (int) $id, $linked, true ) ? 'selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function save_post_links( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) { return; }
		if ( ! wp_verify_nonce( $_POST[ self::NONCE . '_nonce' ], self::NONCE ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$submitted = isset( $_POST['rk_rel'] ) && is_array( $_POST['rk_rel'] ) ? wp_unslash( $_POST['rk_rel'] ) : array();
		foreach ( self::all() as $rel ) {
			if ( $rel['from_object'] !== $post->post_type ) { continue; }
			$ids = isset( $submitted[ $rel['id'] ] ) ? (array) $submitted[ $rel['id'] ] : array();
			self::set_children( $rel['id'], $post_id, $ids );
		}
	}
}
