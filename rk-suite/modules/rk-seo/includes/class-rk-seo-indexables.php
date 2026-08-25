<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Indexables — an indexed cache of each post's computed SEO fields (title,
 * description, robots, canonical). Filled on save and lazily on first view,
 * read back as a single indexed row on the front end instead of recomputing
 * from templates + meta on every request. Self-healing: clearing the table
 * (or changing Search Appearance) simply causes rows to rebuild on next view.
 *
 * This is the scale/perf foundation the audit called for; it also unlocks
 * future features (internal-linking, cornerstone) that need pre-computed data.
 *
 * @package RK_SEO
 */
class Indexables {

	const OPTION_ON = 'rk_seo_indexables_on';

	/** Per-request memo so one page load hits the table once, not once per meta tag. */
	private static $mem = array();

	public static function table() { global $wpdb; return $wpdb->prefix . 'rk_seo_indexables'; }

	public static function enabled() {
		return '0' !== (string) get_option( self::OPTION_ON, '1' );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL DEFAULT 'post',
			sub_type VARCHAR(50) NOT NULL DEFAULT '',
			permalink TEXT NULL,
			title TEXT NULL,
			description TEXT NULL,
			is_noindex TINYINT(1) NOT NULL DEFAULT 0,
			canonical TEXT NULL,
			og_image TEXT NULL,
			primary_term BIGINT UNSIGNED NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY object (object_type, object_id),
			KEY sub_type (sub_type)
		) {$charset};";
		dbDelta( $sql );
	}

	public function hooks() {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20 );
		add_action( 'deleted_post', array( __CLASS__, 'on_delete_post' ) );
		add_action( 'rk_seo_appearance_saved', array( __CLASS__, 'clear' ) );
	}

	public static function on_save_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) { self::on_delete_post( $post_id ); return; }
		if ( ! in_array( $post->post_type, array_keys( Search_Appearance::post_types() ), true ) ) { return; }
		self::store_post( $post );
	}

	public static function on_delete_post( $post_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'object_type' => 'post', 'object_id' => (int) $post_id ) );
	}

	/** Compute a post's SEO fields context-free (override → template → default). */
	public static function build_post( $post ) {
		$pt   = get_post_type( $post );
		$conf = Search_Appearance::pt( $pt );
		$ctx  = array( 'post' => $post );

		$title = Metabox::get( $post->ID, Metabox::T_TITLE );
		$title = ( '' !== $title ) ? $title : $conf['title'];
		$title = Helpers::clean( Variables::replace( $title, $ctx ) );

		$desc = Metabox::get( $post->ID, Metabox::T_DESC );
		if ( '' === $desc ) { $desc = isset( $conf['desc'] ) ? $conf['desc'] : '%%excerpt%%'; }
		$desc = Helpers::truncate( Variables::replace( $desc, $ctx ), 160 );

		$noindex = ( '1' === (string) Metabox::get( $post->ID, Metabox::T_NOIDX ) ) || ! empty( $conf['noindex'] ) || ( '0' === get_option( 'blog_public' ) );

		$canon = Metabox::get( $post->ID, Metabox::T_CANON );
		if ( '' === $canon ) { $canon = get_permalink( $post ); }

		$og = Metabox::get( $post->ID, Metabox::T_OGIMG );
		if ( '' === $og && has_post_thumbnail( $post ) ) { $og = get_the_post_thumbnail_url( $post, 'full' ); }

		$primary = (int) get_post_meta( $post->ID, '_rk_seo_primary_category', true );

		return array(
			'object_id'    => (int) $post->ID,
			'object_type'  => 'post',
			'sub_type'     => $pt,
			'permalink'    => (string) get_permalink( $post ),
			'title'        => $title,
			'description'  => $desc,
			'is_noindex'   => $noindex ? 1 : 0,
			'canonical'    => (string) $canon,
			'og_image'     => (string) $og,
			'primary_term' => $primary,
			'updated_at'   => current_time( 'mysql' ),
		);
	}

	public static function store_post( $post ) {
		global $wpdb;
		$row = self::build_post( $post );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::table() . " WHERE object_type='post' AND object_id=%d", $row['object_id'] ) );
		if ( $existing ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $existing ) );
		} else {
			$wpdb->insert( self::table(), $row );
		}
		self::$mem[ (int) $row['object_id'] ] = $row;
		return $row;
	}

	/** Read-through: return the cached row for a post, building it if missing. */
	public static function for_post( $post_id ) {
		if ( ! self::enabled() ) { return null; }
		$post_id = (int) $post_id;
		if ( array_key_exists( $post_id, self::$mem ) ) { return self::$mem[ $post_id ]; } // 1 read per request.
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE object_type='post' AND object_id=%d", $post_id ), ARRAY_A );
		if ( ! $row ) {
			$post = get_post( $post_id );
			$row  = ( $post && 'publish' === $post->post_status ) ? self::store_post( $post ) : null; // lazy fill
		}
		self::$mem[ $post_id ] = $row;
		return $row;
	}

	/** Wipe the cache (rows rebuild lazily on next view / save). */
	public static function clear() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE " . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL -- no user input; table name is internal.
	}

	public static function count_rows() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );
	}
}
