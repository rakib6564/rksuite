<?php
/**
 * RK_Migrate_Exporter — scan a live Elementor site and produce a portable bundle
 * (manifest.json + per-item JSON files) zipped for download or library storage.
 *
 * Supports full or selective export, media bundling, global colors/fonts, and
 * WooCommerce Elementor templates.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Exporter {

	private $report  = array();
	private $zip     = null;   // open ZipArchive during build
	private $tmpdir  = '';     // temp working dir for streamed item JSON
	private $tmpfiles = array();
	private $zip_path = '';

	/** Inventory the site for the selective-export UI. */
	public static function inventory() {
		$out = array( 'pages' => array(), 'posts' => array(), 'cpts' => array(), 'templates' => array() );

		// Only Elementor-built posts are exportable — filter in SQL (meta_key) so we
		// never load the whole posts table, and drop the per-row is_elementor() query.
		$builder = array( 'meta_key' => '_elementor_edit_mode', 'meta_value' => 'builder' );

		$pages = get_posts( array_merge( $builder, array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ) ) );
		foreach ( $pages as $p ) { $out['pages'][] = self::row( $p ); }

		$posts = get_posts( array_merge( $builder, array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) ) );
		foreach ( $posts as $p ) { $out['posts'][] = self::row( $p ); }

		$cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		foreach ( $cpts as $cpt ) {
			$items = get_posts( array_merge( $builder, array( 'post_type' => $cpt, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) ) );
			foreach ( $items as $p ) { $r = self::row( $p ); $r['cpt'] = $cpt; $out['cpts'][] = $r; }
		}

		$tpls = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => -1 ) );
		foreach ( $tpls as $t ) {
			$type = wp_get_object_terms( $t->ID, 'elementor_library_type', array( 'fields' => 'slugs' ) );
			$out['templates'][] = array( 'id' => $t->ID, 'title' => $t->post_title, 'type' => $type ? $type[0] : 'section' );
		}
		return $out;
	}

	private static function row( $p ) {
		return array( 'id' => $p->ID, 'title' => $p->post_title, 'slug' => $p->post_name, 'type' => $p->post_type, 'status' => $p->post_status );
	}

	private static function is_elementor( $pid ) {
		return 'builder' === get_post_meta( $pid, '_elementor_edit_mode', true );
	}

	/**
	 * Build a bundle.
	 * @param array $args {
	 *   page_ids, post_ids, cpt_ids, template_ids : arrays of IDs to include
	 *   include_menus (bool), include_global_kit (bool), include_media (bool)
	 *   project (string)
	 * }
	 * @return string|WP_Error path to the written .zip
	 */
	public function build( $args ) {
		$args = wp_parse_args( $args, array(
			'project' => get_bloginfo( 'name' ), 'page_ids' => array(), 'post_ids' => array(),
			'cpt_ids' => array(), 'template_ids' => array(), 'include_menus' => true,
			'include_global_kit' => true, 'include_media' => false,
		) );

		$opened = $this->open_zip( $args['project'] );
		if ( is_wp_error( $opened ) ) { return $opened; }

		$manifest = array(
			'project'     => $args['project'],
			'exported_at' => gmdate( 'c' ),
			'source_url'  => home_url(),
			'options'     => array( 'blogname' => get_bloginfo( 'name' ), 'blogdescription' => get_bloginfo( 'description' ) ),
			'pages'       => array(),
			'theme_parts' => array(),
			'fragments'   => array(),
			'menus'       => array(),
		);
		$front_id = (int) get_option( 'page_on_front' );
		$media_urls = array();

		// pages + posts + cpts -> manifest.pages[]
		foreach ( array( 'page_ids' => 'page', 'post_ids' => 'post' ) as $key => $deftype ) {
			foreach ( (array) $args[ $key ] as $pid ) {
				$entry = $this->export_content_post( $pid, $front_id, $media_urls );
				if ( $entry ) { $manifest['pages'][] = $entry; }
			}
		}
		foreach ( (array) $args['cpt_ids'] as $pid ) {
			$entry = $this->export_content_post( $pid, $front_id, $media_urls );
			if ( $entry ) { $manifest['pages'][] = $entry; }
		}

		// templates -> theme_parts (header/footer) or fragments
		foreach ( (array) $args['template_ids'] as $tid ) {
			$this->export_template( $tid, $manifest, $media_urls );
		}

		// global kit
		if ( $args['include_global_kit'] ) {
			$kitfile = $this->export_global_kit();
			if ( $kitfile ) { $manifest['global_kit'] = $kitfile; }
		}

		// menus
		if ( $args['include_menus'] ) {
			$manifest['menus'] = $this->export_menus();
		}

		$this->add_entry( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		// media bundling
		$media_dir_files = array();
		if ( $args['include_media'] ) {
			$media_dir_files = $this->collect_media_files( array_unique( $media_urls ) );
		}

		return $this->finalize_zip( $media_dir_files );
	}

	private function export_content_post( $pid, $front_id, &$media_urls ) {
		$pid = (int) $pid;
		$post = get_post( $pid );
		if ( ! $post ) { return null; }
		$data = get_post_meta( $pid, '_elementor_data', true );
		$content = $data ? json_decode( $data, true ) : array();
		if ( ! is_array( $content ) ) { $content = array(); }
		$page_settings = get_post_meta( $pid, '_elementor_page_settings', true );

		$json = array(
			'content'       => $content,
			'page_settings' => is_array( $page_settings ) ? $page_settings : array(),
			'version'       => get_post_meta( $pid, '_elementor_version', true ),
			'type'          => 'page',
		);
		$file = sanitize_title( $post->post_type . '-' . $post->post_name ) . '.json';
		$this->add_entry( $file, wp_json_encode( $json, JSON_UNESCAPED_SLASHES ) );
		$media_urls = array_merge( $media_urls, RK_Migrate_Media::collect_urls( $content ) );

		$entry = array(
			'file'      => $file,
			'slug'      => $post->post_name,
			'title'     => $post->post_title,
			'post_type' => $post->post_type,
			'status'    => $post->post_status,
		);
		if ( $pid === $front_id ) { $entry['is_front_page'] = true; }

		// WP post body + excerpt (importer restores these alongside Elementor data)
		if ( '' !== (string) $post->post_content ) { $entry['content'] = $post->post_content; }
		if ( '' !== (string) $post->post_excerpt ) { $entry['excerpt'] = $post->post_excerpt; }

		// SEO meta (read whichever plugin wrote it)
		$st = get_post_meta( $pid, '_yoast_wpseo_title', true ) ?: get_post_meta( $pid, 'rank_math_title', true );
		$sd = get_post_meta( $pid, '_yoast_wpseo_metadesc', true ) ?: get_post_meta( $pid, 'rank_math_description', true );
		$sk = get_post_meta( $pid, '_yoast_wpseo_focuskw', true ) ?: get_post_meta( $pid, 'rank_math_focus_keyword', true );
		if ( $st ) { $entry['seo_title'] = $st; }
		if ( $sd ) { $entry['seo_desc'] = $sd; }
		if ( $sk ) { $entry['focus_kw'] = $sk; }

		// CPT extras
		$thumb = get_post_thumbnail_id( $pid );
		if ( $thumb ) {
			$url = wp_get_attachment_url( $thumb );
			if ( $url ) { $entry['featured_image'] = $url; $media_urls[] = $url; }
		}
		if ( ! in_array( $post->post_type, array( 'page', 'post' ), true ) || 'post' === $post->post_type ) {
			$taxes = get_object_taxonomies( $post->post_type );
			$tax_out = array();
			foreach ( $taxes as $tax ) {
				$terms = wp_get_object_terms( $pid, $tax, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && $terms ) { $tax_out[ $tax ] = $terms; }
			}
			if ( $tax_out ) { $entry['taxonomies'] = $tax_out; }
		}
		return $entry;
	}

	private function export_template( $tid, &$manifest, &$media_urls ) {
		$tid = (int) $tid;
		$t = get_post( $tid );
		if ( ! $t ) { return; }
		$data = get_post_meta( $tid, '_elementor_data', true );
		$content = $data ? json_decode( $data, true ) : array();
		if ( ! is_array( $content ) ) { $content = array(); }
		$types = wp_get_object_terms( $tid, 'elementor_library_type', array( 'fields' => 'slugs' ) );
		$type = $types ? $types[0] : 'section';
		$json = array( 'content' => $content, 'type' => $type, 'version' => get_post_meta( $tid, '_elementor_version', true ) );
		$file = sanitize_title( 'tpl-' . $type . '-' . $t->post_name . '-' . $tid ) . '.json';
		$this->add_entry( $file, wp_json_encode( $json, JSON_UNESCAPED_SLASHES ) );
		$media_urls = array_merge( $media_urls, RK_Migrate_Media::collect_urls( $content ) );

		if ( in_array( $type, array( 'header', 'footer' ), true ) ) {
			$cond = get_post_meta( $tid, '_elementor_conditions', true );
			$manifest['theme_parts'][] = array(
				'file' => $file, 'part' => $type, 'title' => $t->post_title,
				'condition' => ( is_array( $cond ) && $cond ) ? $cond[0] : 'include/general',
			);
		} else {
			// woo & section templates ride along as fragments, tagged with their real type
			$manifest['fragments'][] = array( 'file' => $file, 'title' => $t->post_title, 'template_type' => $type );
		}
	}

	private function export_global_kit() {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) { return ''; }
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) { return ''; }
		$keep = array();
		foreach ( array( 'system_colors', 'custom_colors', 'system_typography', 'custom_typography' ) as $k ) {
			if ( isset( $settings[ $k ] ) ) { $keep[ $k ] = $settings[ $k ]; }
		}
		if ( ! $keep ) { return ''; }
		$file = 'global-kit.json';
		$this->add_entry( $file, wp_json_encode( array( 'settings' => $keep ), JSON_UNESCAPED_SLASHES ) );
		return $file;
	}

	private function export_menus() {
		$out = array();
		$locations = array_flip( (array) get_theme_mod( 'nav_menu_locations', array() ) );
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! $items ) { continue; }
			$by_id = array(); $tree = array();
			foreach ( $items as $it ) {
				$node = array( 'label' => $it->title, '_id' => $it->ID, '_parent' => $it->menu_item_parent, 'children' => array() );
				if ( 'custom' === $it->type ) { $node['url'] = $it->url; }
				else {
					$obj = get_post( $it->object_id );
					$node['slug'] = $obj ? $obj->post_name : '';
				}
				$by_id[ $it->ID ] = $node;
			}
			foreach ( $by_id as $id => &$node ) {
				$pid = $node['_parent'];
				if ( $pid && isset( $by_id[ $pid ] ) ) { $by_id[ $pid ]['children'][] = &$node; }
				else { $tree[] = &$node; }
			}
			unset( $node );
			$clean = $this->strip_menu_internal( $tree );
			$out[] = array(
				'name'     => $menu->name,
				'location' => isset( $locations[ $menu->term_id ] ) ? $locations[ $menu->term_id ] : '',
				'items'    => $clean,
			);
		}
		return $out;
	}

	private function strip_menu_internal( $nodes ) {
		$out = array();
		foreach ( $nodes as $n ) {
			$clean = array( 'label' => $n['label'] );
			if ( isset( $n['url'] ) ) { $clean['url'] = $n['url']; }
			if ( isset( $n['slug'] ) && $n['slug'] ) { $clean['slug'] = $n['slug']; }
			if ( ! empty( $n['children'] ) ) { $clean['children'] = $this->strip_menu_internal( $n['children'] ); }
			$out[] = $clean;
		}
		return $out;
	}

	private function collect_media_files( array $urls ) {
		$urls = RK_Migrate_Media::local_only( $urls );
		$base = wp_get_upload_dir();
		$map = array();
		foreach ( $urls as $u ) {
			$rel = ltrim( str_replace( $base['baseurl'], '', $u ), '/' );
			$path = trailingslashit( $base['basedir'] ) . $rel;
			if ( file_exists( $path ) ) { $map[ 'media/' . $rel ] = $path; }
		}
		return $map;
	}

	/** Open the export zip + a temp working dir up-front, so items stream to disk. */
	private function open_zip( $project ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'nozip', 'PHP ZipArchive is not available on this server.' ); }
		if ( ! file_exists( RK_MIGRATE_EXPORT_DIR ) ) { wp_mkdir_p( RK_MIGRATE_EXPORT_DIR ); }
		$name = sanitize_title( $project ) . '-' . gmdate( 'Ymd-His' ) . '.zip';
		$this->zip_path = trailingslashit( RK_MIGRATE_EXPORT_DIR ) . $name;
		$this->tmpdir   = trailingslashit( RK_MIGRATE_EXPORT_DIR ) . 'tmp-' . wp_generate_password( 8, false, false );
		wp_mkdir_p( $this->tmpdir );
		$this->zip = new ZipArchive();
		if ( true !== $this->zip->open( $this->zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zipopen', 'Could not create the export zip.' );
		}
		return $this->zip_path;
	}

	/**
	 * Stream one entry: write its JSON to a temp file and addFile() it (libzip
	 * reads the file at close, so only ONE item's JSON is held in memory at a
	 * time — peak memory is O(largest page), not O(all pages)).
	 */
	private function add_entry( $name, $contents ) {
		if ( ! $this->zip ) { return; }
		$tmp = trailingslashit( $this->tmpdir ) . md5( $name ) . '.tmp';
		if ( false !== file_put_contents( $tmp, $contents ) ) {
			$this->zip->addFile( $tmp, $name );
			$this->tmpfiles[] = $tmp;
		} else {
			$this->zip->addFromString( $name, $contents ); // fallback if temp write fails.
		}
		unset( $contents );
	}

	/** Close the zip (flushes streamed temp files), then clean up. */
	private function finalize_zip( $media_files ) {
		foreach ( (array) $media_files as $zip_path => $disk_path ) { $this->zip->addFile( $disk_path, $zip_path ); }
		$this->zip->close();
		foreach ( $this->tmpfiles as $t ) { @unlink( $t ); }
		if ( $this->tmpdir && is_dir( $this->tmpdir ) ) { @rmdir( $this->tmpdir ); }
		return $this->zip_path;
	}
}
