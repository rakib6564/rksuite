<?php
/**
 * RK_Migrate_Library — store multiple bundles inside the install (agency master
 * templates) and pick one to make active without re-uploading. Also provides
 * password-based bundle encryption/decryption (basic DRM for sold kits).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Library {

	/** List stored bundles: each has slug (folder), project, pages count, time. */
	public static function all() {
		$out = array();
		if ( ! file_exists( RK_MIGRATE_LIBRARY_DIR ) ) { return $out; }
		foreach ( glob( trailingslashit( RK_MIGRATE_LIBRARY_DIR ) . '*', GLOB_ONLYDIR ) as $dir ) {
			$base = self::manifest_dir( $dir );
			if ( ! $base ) { continue; }
			$m = json_decode( file_get_contents( $base . 'manifest.json' ), true );
			$out[] = array(
				'slug'    => basename( $dir ),
				'dir'     => untrailingslashit( $base ),
				'project' => isset( $m['project'] ) ? $m['project'] : basename( $dir ),
				'pages'   => isset( $m['pages'] ) ? count( $m['pages'] ) : 0,
				'time'    => isset( $m['exported_at'] ) ? $m['exported_at'] : '',
			);
		}
		usort( $out, function ( $a, $b ) { return strcmp( $b['slug'], $a['slug'] ); } );
		return $out;
	}

	/** Store a .zip (or already-extracted dir) into the library. Returns slug. */
	public static function store_zip( $zip_path, $label = '' ) {
		if ( ! file_exists( RK_MIGRATE_LIBRARY_DIR ) ) { wp_mkdir_p( RK_MIGRATE_LIBRARY_DIR ); }
		$slug = sanitize_title( $label ?: pathinfo( $zip_path, PATHINFO_FILENAME ) ) . '-' . wp_generate_password( 5, false, false );
		$dir  = trailingslashit( RK_MIGRATE_LIBRARY_DIR ) . $slug . '/';
		wp_mkdir_p( $dir );
		if ( ! function_exists( 'unzip_file' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
		WP_Filesystem();
		$res = unzip_file( $zip_path, $dir );
		if ( is_wp_error( $res ) ) { return $res; }
		$mdir = self::manifest_dir( $dir );
		if ( ! $mdir ) { return new WP_Error( 'nomanifest', 'No manifest.json inside bundle.' ); }
		RK_Migrate_Kit::maybe_convert( $mdir ); // auto-convert third-party template kits
		return $slug;
	}

	public static function path( $slug ) {
		$dir = trailingslashit( RK_MIGRATE_LIBRARY_DIR ) . basename( $slug ) . '/';
		return self::manifest_dir( $dir );
	}

	public static function delete( $slug ) {
		$dir = trailingslashit( RK_MIGRATE_LIBRARY_DIR ) . basename( $slug ) . '/';
		if ( file_exists( $dir ) ) { self::rrmdir( $dir ); return true; }
		return false;
	}

	private static function manifest_dir( $dir ) {
		if ( file_exists( trailingslashit( $dir ) . 'manifest.json' ) ) { return trailingslashit( $dir ); }
		foreach ( glob( trailingslashit( $dir ) . '*', GLOB_ONLYDIR ) as $sub ) {
			if ( file_exists( trailingslashit( $sub ) . 'manifest.json' ) ) { return trailingslashit( $sub ); }
		}
		return '';
	}

	private static function rrmdir( $dir ) {
		foreach ( glob( trailingslashit( $dir ) . '*' ) as $f ) {
			is_dir( $f ) ? self::rrmdir( $f ) : @unlink( $f );
		}
		@rmdir( $dir );
	}

	/* ---------------- bundle encryption (AES-256-CBC) ---------------- */

	public static function encrypt_file( $path, $password ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) { return new WP_Error( 'noopenssl', 'OpenSSL not available.' ); }
		$data = file_get_contents( $path );
		if ( false === $data ) { return new WP_Error( 'read', 'Cannot read file.' ); }
		$salt = openssl_random_pseudo_bytes( 16 );
		$iv   = openssl_random_pseudo_bytes( 16 );
		$key  = hash_pbkdf2( 'sha256', $password, $salt, 100000, 32, true );
		$ct   = openssl_encrypt( $data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		$blob = 'RK_MIGRATEENC1' . $salt . $iv . $ct; // 14-byte magic + 16 salt + 16 iv + ct
		$out  = $path . '.epenc';
		file_put_contents( $out, $blob );
		return $out;
	}

	public static function decrypt_file( $path, $password ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) { return new WP_Error( 'noopenssl', 'OpenSSL not available.' ); }
		$blob = file_get_contents( $path );
		$magic = 'RK_MIGRATEENC1';
		$ml    = strlen( $magic ); // 14
		if ( substr( (string) $blob, 0, $ml ) !== $magic ) { return new WP_Error( 'badfmt', 'Not an RK Migrate encrypted bundle.' ); }
		$salt = substr( $blob, $ml, 16 );
		$iv   = substr( $blob, $ml + 16, 16 );
		$ct   = substr( $blob, $ml + 32 );
		$key  = hash_pbkdf2( 'sha256', $password, $salt, 100000, 32, true );
		$data = openssl_decrypt( $ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $data ) { return new WP_Error( 'badpw', 'Wrong password or corrupt bundle.' ); }
		$out = preg_replace( '/\.epenc$/', '', $path );
		if ( $out === $path ) { $out = $path . '.zip'; }
		file_put_contents( $out, $data );
		return $out;
	}
}
