<?php
/**
 * RK_Migrate_Manifest_Builder — server side for the visual manifest editor.
 * Saves a GUI-built manifest into a library bundle so it can be run without
 * hand-editing JSON. (The editor UI itself is rendered by RK_Migrate_Admin.)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Manifest_Builder {

	/**
	 * Persist a manifest (decoded array) into a library bundle directory.
	 * If $source_slug is given, page JSON files are copied from that bundle so
	 * the new manifest references real files.
	 */
	public static function save( array $manifest, $label = 'built', $source_slug = '' ) {
		if ( ! file_exists( RK_MIGRATE_LIBRARY_DIR ) ) { wp_mkdir_p( RK_MIGRATE_LIBRARY_DIR ); }
		$slug = sanitize_title( $label ) . '-' . wp_generate_password( 5, false, false );
		$dir  = trailingslashit( RK_MIGRATE_LIBRARY_DIR ) . $slug . '/';
		wp_mkdir_p( $dir );

		if ( $source_slug ) {
			$src = RK_Migrate_Library::path( $source_slug );
			if ( $src ) {
				foreach ( glob( trailingslashit( $src ) . '*.json' ) as $f ) {
					if ( basename( $f ) === 'manifest.json' ) { continue; }
					copy( $f, $dir . basename( $f ) );
				}
			}
		}
		file_put_contents( $dir . 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		return $slug;
	}

	/** Validate a manifest, returning an array of human-readable warnings. */
	public static function validate( array $manifest, $base_dir = '' ) {
		$warn = array();
		if ( empty( $manifest['pages'] ) ) { $warn[] = 'No pages defined.'; }
		$fronts = 0;
		foreach ( (array) ( $manifest['pages'] ?? array() ) as $i => $p ) {
			if ( empty( $p['slug'] ) ) { $warn[] = "Page #{$i} has no slug."; }
			if ( empty( $p['file'] ) ) { $warn[] = "Page #{$i} has no file."; }
			elseif ( $base_dir && ! file_exists( trailingslashit( $base_dir ) . basename( $p['file'] ) ) ) {
				$warn[] = "Page #{$i} file '{$p['file']}' not found in bundle.";
			}
			if ( ! empty( $p['is_front_page'] ) ) { $fronts++; }
		}
		if ( $fronts > 1 ) { $warn[] = 'More than one page is marked as the front page.'; }
		return $warn;
	}
}
