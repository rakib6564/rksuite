<?php
/**
 * RK_Migrate_Kit — ingest third-party Elementor template kits and convert them, in
 * place, into a RK Migrate bundle so the normal importer can run them.
 *
 * Two formats are auto-detected:
 *   - "envato"  : marketplace Template Kit (Envato / Creativemox). manifest.json
 *                 with a `templates` LIST, each { name, source, type, metadata }.
 *   - "native"  : Elementor's own Website Kit export (Tools → Import/Export Kit).
 *                 manifest.json with `content`/`templates` as OBJECTS; documents
 *                 live under content/<type>/<id>.json and templates/<id>.json,
 *                 each carrying its own `doc_type`, `title` and `content` array.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Kit {

	/** Returns 'envato' | 'native' | false. */
	public static function detect( $base ) {
		$path = trailingslashit( $base ) . 'manifest.json';
		if ( ! file_exists( $path ) ) { return false; }
		$m = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $m ) ) { return false; }
		if ( isset( $m['pages'] ) ) { return false; } // already a RK Migrate bundle
		if ( isset( $m['templates'] ) && is_array( $m['templates'] ) && self::is_list( $m['templates'] ) ) { return 'envato'; }
		$has_content_obj  = isset( $m['content'] ) && is_array( $m['content'] ) && ! self::is_list( $m['content'] );
		$has_tpl_obj      = isset( $m['templates'] ) && is_array( $m['templates'] ) && ! self::is_list( $m['templates'] );
		if ( $has_content_obj || $has_tpl_obj || isset( $m['site-settings'] ) ) { return 'native'; }
		return false;
	}

	public static function is_kit( $base ) { return (bool) self::detect( $base ); }

	/** Convert in place. Returns ['ok'=>true,'count'=>N,'title'=>..,'format'=>..] or false. */
	public static function maybe_convert( $base ) {
		$type = self::detect( $base );
		if ( ! $type ) { return false; }
		$base = trailingslashit( $base );
		$m = json_decode( (string) file_get_contents( $base . 'manifest.json' ), true );
		$title = isset( $m['title'] ) ? $m['title'] : ( isset( $m['name'] ) ? $m['name'] : 'Imported Template Kit' );

		$pages = array(); $theme_parts = array(); $fragments = array(); $global_kit = '';

		if ( 'envato' === $type ) {
			foreach ( $m['templates'] as $t ) {
				if ( ! is_array( $t ) || empty( $t['source'] ) ) { continue; }
				$name = isset( $t['name'] ) ? $t['name'] : 'Template';
				$tp   = isset( $t['type'] ) ? $t['type'] : 'page';
				$md   = isset( $t['metadata'] ) && is_array( $t['metadata'] ) ? $t['metadata'] : array();
				$tt   = isset( $md['template_type'] ) ? $md['template_type'] : '';
				$abs  = $base . ltrim( str_replace( '\\/', '/', $t['source'] ), '/' );
				if ( ! file_exists( $abs ) ) { continue; }
				$libtype = isset( $md['elementor_library_type'] ) ? $md['elementor_library_type'] : $tp;
				self::route( $abs, $base, $name, $tp, $tt, $libtype, $pages, $theme_parts, $fragments, $global_kit );
			}
		} else {
			// native: scan every JSON document and classify by its own doc_type
			foreach ( self::rscan( $base ) as $abs ) {
				$fnbase = basename( $abs );
				if ( in_array( $fnbase, array( 'manifest.json', 'kit-source-manifest.json' ), true ) ) { continue; }
				$doc = json_decode( (string) file_get_contents( $abs ), true );
				if ( ! is_array( $doc ) ) { continue; }
				// site/global settings file (no content tree, but has settings)
				if ( ! isset( $doc['content'] ) && isset( $doc['settings'] ) && is_array( $doc['settings'] ) ) {
					if ( ! $global_kit ) { file_put_contents( $base . 'global-kit.json', wp_json_encode( array( 'settings' => $doc['settings'] ) ) ); $global_kit = 'global-kit.json'; }
					continue;
				}
				if ( ! isset( $doc['content'] ) || ! is_array( $doc['content'] ) ) { continue; } // not a document
				$dt   = isset( $doc['doc_type'] ) ? $doc['doc_type'] : ( isset( $doc['type'] ) ? $doc['type'] : 'page' );
				$name = isset( $doc['title'] ) ? $doc['title'] : pathinfo( $fnbase, PATHINFO_FILENAME );
				if ( 'kit' === $dt ) {
					if ( ! $global_kit && isset( $doc['settings'] ) ) { file_put_contents( $base . 'global-kit.json', wp_json_encode( array( 'settings' => $doc['settings'] ) ) ); $global_kit = 'global-kit.json'; }
					continue;
				}
				self::route( $abs, $base, $name, self::native_role( $dt ), '', $dt, $pages, $theme_parts, $fragments, $global_kit );
			}
		}

		if ( ! $pages && ! $theme_parts && ! $fragments ) { return false; } // nothing usable

		if ( $pages && ! self::has_front( $pages ) ) { $pages[0]['is_front_page'] = true; }
		usort( $pages, function ( $a, $b ) { return ( ! empty( $b['is_front_page'] ) ) - ( ! empty( $a['is_front_page'] ) ); } );
		$items = array();
		foreach ( $pages as $p ) { $items[] = array( 'slug' => $p['slug'], 'label' => $p['title'] ); }

		$rk_migrate = array(
			'project'     => $title,
			'version'     => isset( $m['kit_version'] ) ? $m['kit_version'] : ( isset( $m['version'] ) ? (string) $m['version'] : '1.0.0' ),
			'source'      => 'template-kit:' . $type,
			'global_kit'  => $global_kit,
			'options'     => array(),
			'theme_parts' => $theme_parts,
			'pages'       => $pages,
			'fragments'   => $fragments,
			'menus'       => $items ? array( array( 'name' => 'Primary', 'location' => 'primary', 'items' => $items ) ) : array(),
		);
		@rename( $base . 'manifest.json', $base . 'kit-source-manifest.json' );
		file_put_contents( $base . 'manifest.json', wp_json_encode( $rk_migrate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		return array( 'ok' => true, 'count' => count( $pages ) + count( $theme_parts ) + count( $fragments ), 'title' => $title, 'format' => $type );
	}

	/** Map a document into the right bucket (shared by both formats). */
	private static function route( $abs, $base, $name, $role, $tt, $libtype, &$pages, &$theme_parts, &$fragments, &$global_kit ) {
		if ( 'global-styles' === $tt || 'global' === $role ) {
			$g  = json_decode( (string) file_get_contents( $abs ), true );
			$ps = ( is_array( $g ) && isset( $g['page_settings'] ) ) ? $g['page_settings'] : ( ( is_array( $g ) && isset( $g['settings'] ) ) ? $g['settings'] : $g );
			if ( ! $global_kit ) { file_put_contents( $base . 'global-kit.json', wp_json_encode( array( 'settings' => $ps ) ) ); $global_kit = 'global-kit.json'; }
			return;
		}
		$fn = self::flatten( $abs, $base, $name );
		if ( ! $fn ) { return; }
		if ( 'header' === $role ) {
			$theme_parts[] = array( 'file' => $fn, 'part' => 'header', 'title' => $name, 'condition' => 'include/general' );
		} elseif ( 'footer' === $role ) {
			$theme_parts[] = array( 'file' => $fn, 'part' => 'footer', 'title' => $name, 'condition' => 'include/general' );
		} elseif ( 'page' === $role ) {
			$slug = self::slug( $name );
			$entry = array( 'file' => $fn, 'slug' => $slug, 'title' => self::clean_title( $name ) );
			if ( in_array( $slug, array( 'home', 'homepage', 'home-page', 'front-page' ), true ) ) { $entry['slug'] = 'home'; $entry['title'] = 'Home'; }
			$pages[] = $entry;
		} else {
			$fragments[] = array( 'file' => $fn, 'title' => $name, 'template_type' => $libtype );
		}
	}

	private static function native_role( $doc_type ) {
		$doc_type = strtolower( (string) $doc_type );
		if ( in_array( $doc_type, array( 'wp-page', 'page' ), true ) ) { return 'page'; }
		if ( 'header' === $doc_type ) { return 'header'; }
		if ( 'footer' === $doc_type ) { return 'footer'; }
		if ( in_array( $doc_type, array( 'kit', 'global', 'global-styles' ), true ) ) { return 'global'; }
		return 'fragment'; // section, container, archive, single, popup, error-404, search-results, post, etc.
	}

	/* ---------------- helpers ---------------- */
	private static function flatten( $abs, $base, $name ) {
		$slug = self::slug( $name ); $fn = $slug . '.json'; $i = 2;
		while ( file_exists( $base . $fn ) && wp_normalize_path( $base . $fn ) !== wp_normalize_path( $abs ) ) { $fn = $slug . '-' . $i . '.json'; $i++; }
		if ( wp_normalize_path( $abs ) !== wp_normalize_path( $base . $fn ) ) { @copy( $abs, $base . $fn ); }
		return file_exists( $base . $fn ) ? $fn : '';
	}
	private static function rscan( $dir ) {
		$out = array();
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) { if ( $f->isFile() && strtolower( $f->getExtension() ) === 'json' ) { $out[] = $f->getPathname(); } }
		return $out;
	}
	private static function clean_title( $name ) {
		$t = trim( preg_replace( '/^.*?\s-\s/', '', (string) $name ) );
		return '' !== $t ? $t : (string) $name;
	}
	private static function slug( $name ) {
		$s = sanitize_title( self::clean_title( $name ) );
		return $s ?: ( sanitize_title( (string) $name ) ?: 'template-' . substr( md5( (string) $name ), 0, 5 ) );
	}
	private static function has_front( $pages ) { foreach ( $pages as $p ) { if ( ! empty( $p['is_front_page'] ) ) { return true; } } return false; }
	private static function is_list( $arr ) {
		if ( ! is_array( $arr ) ) { return false; }
		if ( array() === $arr ) { return true; }
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}
}
