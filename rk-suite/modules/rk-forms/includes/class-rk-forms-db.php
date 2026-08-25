<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Forms_DB — custom tables (definitions + submissions) and their CRUD.
 * Single-site: the source app's tenant_id is dropped.
 */
class RK_Forms_DB {

	const DB_VERSION = '1.0.0';

	public static function forms_table()       { global $wpdb; return $wpdb->prefix . 'rk_forms'; }
	public static function submissions_table() { global $wpdb; return $wpdb->prefix . 'rk_form_submissions'; }

	/** Create/upgrade tables via dbDelta. Idempotent. */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$forms   = self::forms_table();
		$subs    = self::submissions_table();

		dbDelta( "CREATE TABLE {$forms} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(80) NOT NULL DEFAULT '',
			title VARCHAR(200) NOT NULL DEFAULT '',
			description TEXT NULL,
			fields_json LONGTEXT NULL,
			submit_label VARCHAR(80) NOT NULL DEFAULT 'Submit',
			success_message TEXT NULL,
			redirect_url VARCHAR(500) NULL,
			notify_email VARCHAR(200) NULL,
			confirm_submitter TINYINT(1) NOT NULL DEFAULT 0,
			confirm_subject VARCHAR(200) NULL,
			confirm_body TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$subs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ref VARCHAR(32) NOT NULL DEFAULT '',
			data_json LONGTEXT NULL,
			submitter_email VARCHAR(200) NULL,
			ip VARCHAR(60) NULL,
			user_agent VARCHAR(255) NULL,
			read_at DATETIME NULL,
			email_sent TINYINT(1) NOT NULL DEFAULT 0,
			email_error TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY form_id (form_id),
			KEY read_at (read_at),
			KEY created_at (created_at)
		) {$charset};" );

		update_option( 'rk_forms_db_version', self::DB_VERSION );
	}

	/* ---------------- forms CRUD ---------------- */

	public static function all_forms( $status = '' ) {
		global $wpdb; $t = self::forms_table();
		if ( $status ) { return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY updated_at DESC", $status ) ); }
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY updated_at DESC" );
	}

	public static function get_form( $id ) {
		global $wpdb; $t = self::forms_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) );
	}

	public static function get_form_by_slug( $slug ) {
		global $wpdb; $t = self::forms_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", sanitize_title( $slug ) ) );
	}

	/** Insert or update. $data is a sanitized associative array. Returns form id. */
	public static function save_form( $id, $data ) {
		global $wpdb; $t = self::forms_table();
		$now = current_time( 'mysql' );
		$data['updated_at'] = $now;
		if ( $id ) {
			$wpdb->update( $t, $data, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$data['created_at'] = $now;
		$wpdb->insert( $t, $data );
		return (int) $wpdb->insert_id;
	}

	public static function delete_form( $id ) {
		global $wpdb;
		$wpdb->delete( self::forms_table(), array( 'id' => (int) $id ) );
		$wpdb->delete( self::submissions_table(), array( 'form_id' => (int) $id ) );
	}

	/** Unique slug helper. */
	public static function unique_slug( $slug, $ignore_id = 0 ) {
		global $wpdb; $t = self::forms_table();
		$slug = sanitize_title( $slug ) ?: 'form';
		$base = $slug; $n = 2;
		while ( true ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t} WHERE slug = %s", $slug ) );
			if ( ! $row || (int) $row->id === (int) $ignore_id ) { return $slug; }
			$slug = $base . '-' . $n; $n++;
		}
	}

	/* ---------------- submissions ---------------- */

	public static function insert_submission( $row ) {
		global $wpdb;
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::submissions_table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function submissions( $form_id = 0, $unread_only = false, $limit = 500 ) {
		global $wpdb; $t = self::submissions_table();
		$where = array(); $args = array();
		if ( $form_id ) { $where[] = 'form_id = %d'; $args[] = (int) $form_id; }
		if ( $unread_only ) { $where[] = 'read_at IS NULL'; }
		$sql = "SELECT * FROM {$t}";
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
	}

	public static function get_submission( $id ) {
		global $wpdb; $t = self::submissions_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) );
	}

	public static function mark_read( $id ) {
		global $wpdb;
		$wpdb->update( self::submissions_table(), array( 'read_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id, 'read_at' => null ) );
	}

	public static function delete_submission( $id ) {
		global $wpdb;
		$wpdb->delete( self::submissions_table(), array( 'id' => (int) $id ) );
	}

	public static function unread_count( $form_id = 0 ) {
		global $wpdb; $t = self::submissions_table();
		if ( $form_id ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE form_id = %d AND read_at IS NULL", (int) $form_id ) ); }
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE read_at IS NULL" );
	}
}
