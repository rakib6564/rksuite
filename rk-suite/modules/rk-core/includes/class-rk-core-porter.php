<?php
/**
 * RK_Core_Porter — export / import the whole content model as one JSON bundle
 * (post types + attached taxonomies + meta boxes + relations + queries), plus
 * pre-built starter models and an example bundle for AI-assisted generation.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core_Porter {

	const FORMAT = 'rk-core-model';

	/* ---------------- collect / export ---------------- */

	/** Full model bundle (everything). */
	public static function collect_all() {
		return self::bundle(
			RK_CPT_Builder::all(),
			RK_Taxonomy_Builder::all(),
			RK_Field_Engine::all_groups(),
			RK_Relations::all(),
			RK_Query_Builder::all()
		);
	}

	/** Bundle limited to given CPT slugs + everything attached to them. */
	public static function collect_for( array $slugs ) {
		$slugs = array_map( 'sanitize_key', $slugs );
		$cpts  = array();
		foreach ( RK_CPT_Builder::all() as $c ) { if ( in_array( $c['slug'], $slugs, true ) ) { $cpts[] = $c; } }

		$taxes = array();
		foreach ( RK_Taxonomy_Builder::all() as $t ) {
			$obj = isset( $t['object_types'] ) ? (array) $t['object_types'] : array();
			if ( array_intersect( $obj, $slugs ) ) { $taxes[] = $t; }
		}
		$groups = array();
		foreach ( RK_Field_Engine::all_groups() as $g ) {
			if ( array_intersect( (array) $g['post_types'], $slugs ) ) { $groups[] = $g; }
		}
		$rels = array();
		foreach ( RK_Relations::all() as $r ) {
			if ( in_array( $r['from_object'], $slugs, true ) || in_array( $r['to_object'], $slugs, true ) ) { $rels[] = $r; }
		}
		$queries = array();
		foreach ( RK_Query_Builder::all() as $q ) {
			if ( isset( $q['post_type'] ) && in_array( $q['post_type'], $slugs, true ) ) { $queries[] = $q; }
		}
		return self::bundle( $cpts, $taxes, $groups, $rels, $queries );
	}

	private static function bundle( $cpts, $taxes, $groups, $rels, $queries ) {
		return array(
			'format'     => self::FORMAT,
			'version'    => defined( 'RK_CORE_VERSION' ) ? RK_CORE_VERSION : '1',
			'generated'  => gmdate( 'c' ),
			'post_types' => array_values( $cpts ),
			'taxonomies' => array_values( $taxes ),
			'meta_boxes' => array_values( $groups ),
			'relations'  => array_values( $rels ),
			'queries'    => array_values( $queries ),
		);
	}

	public static function to_json( $bundle ) {
		return wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/** Stream a bundle as a download and exit. */
	public static function download( $bundle, $filename ) {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo self::to_json( $bundle );
		exit;
	}

	/** Append another set of rk-core arrays into a bundle, de-duped by slug/id. */
	public static function merge_bundle( $bundle, $add ) {
		$key_by = array( 'post_types' => 'slug', 'taxonomies' => 'slug', 'meta_boxes' => 'id', 'relations' => 'id', 'queries' => 'id' );
		foreach ( $key_by as $section => $k ) {
			$existing = isset( $bundle[ $section ] ) && is_array( $bundle[ $section ] ) ? $bundle[ $section ] : array();
			$seen = array();
			foreach ( $existing as $it ) { if ( isset( $it[ $k ] ) ) { $seen[ (string) $it[ $k ] ] = true; } }
			foreach ( (array) ( isset( $add[ $section ] ) ? $add[ $section ] : array() ) as $it ) {
				$id = isset( $it[ $k ] ) ? (string) $it[ $k ] : '';
				if ( '' !== $id && isset( $seen[ $id ] ) ) { continue; }
				$existing[] = $it;
				if ( '' !== $id ) { $seen[ $id ] = true; }
			}
			$bundle[ $section ] = array_values( $existing );
		}
		return $bundle;
	}

	/* ---------------- content portability (posts_data) ---------------- */

	/** Meta keys we never export (internal / non-portable). */
	private static function skip_meta_keys() {
		return array(
			'_edit_lock', '_edit_last', '_thumbnail_id', '_wp_page_template',
			'_wp_old_slug', '_wp_old_date', '_pingme', '_encloseme',
			'_elementor_data', '_elementor_edit_mode', '_elementor_version',
			'_elementor_template_type', '_elementor_page_settings', '_elementor_controls_usage',
			'_elementor_css', '_elementor_page_assets', '_elementor_pro_version',
		);
	}

	/** Meta keys on a post type that hold attachment IDs (image / gallery / media). */
	private static function media_keys_for( $pt ) {
		$pt   = sanitize_key( $pt );
		$keys = array();
		if ( '' === $pt ) { return $keys; }
		if ( class_exists( 'RK_Field_Engine' ) ) {
			foreach ( RK_Field_Engine::all_groups() as $g ) {
				$gpt = isset( $g['post_types'] ) ? (array) $g['post_types'] : array();
				if ( ! in_array( $pt, $gpt, true ) ) { continue; }
				foreach ( (array) ( isset( $g['fields'] ) ? $g['fields'] : array() ) as $fd ) {
					$t = isset( $fd['type'] ) ? $fd['type'] : '';
					if ( in_array( $t, array( 'image', 'gallery', 'media' ), true ) && ! empty( $fd['key'] ) ) { $keys[] = $fd['key']; }
				}
			}
		}
		if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() ) {
			foreach ( RK_Core_JetEngine::fields_for( 'post_type', $pt ) as $fd ) {
				$fd = (array) $fd;
				$t  = strtolower( isset( $fd['type'] ) ? (string) $fd['type'] : '' );
				if ( in_array( $t, array( 'media', 'gallery' ), true ) && ! empty( $fd['name'] ) ) { $keys[] = sanitize_key( $fd['name'] ); }
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/** Collect numeric attachment-id candidates from a meta value (scalar / array / CSV). */
	private static function ids_in_value( $value, &$ids ) {
		if ( is_array( $value ) ) { foreach ( $value as $v ) { self::ids_in_value( $v, $ids ); } return; }
		$sv = trim( (string) $value );
		if ( '' === $sv ) { return; }
		if ( ctype_digit( $sv ) ) { $ids[ (int) $sv ] = true; return; }
		if ( preg_match( '/^\d+(\s*,\s*\d+)+$/', $sv ) ) {
			foreach ( preg_split( '/\s*,\s*/', $sv ) as $x ) { if ( ctype_digit( $x ) ) { $ids[ (int) $x ] = true; } }
		}
	}

	/** Rewrite old attachment ids to new ones inside a meta value (scalar / array / CSV). */
	private static function remap_ids( $value, $map ) {
		if ( is_array( $value ) ) { $out = array(); foreach ( $value as $k => $v ) { $out[ $k ] = self::remap_ids( $v, $map ); } return $out; }
		$sv = trim( (string) $value );
		if ( ctype_digit( $sv ) ) { return isset( $map[ (int) $sv ] ) ? (string) $map[ (int) $sv ] : $value; }
		if ( preg_match( '/^\d+(\s*,\s*\d+)+$/', $sv ) ) {
			$parts = preg_split( '/\s*,\s*/', $sv );
			$r = array();
			foreach ( $parts as $pp ) { $r[] = ( ctype_digit( $pp ) && isset( $map[ (int) $pp ] ) ) ? (string) $map[ (int) $pp ] : $pp; }
			return implode( ',', $r );
		}
		return $value;
	}

	/** Collect published posts (content + media + meta) for the given post types. */
	public static function collect_posts_data( array $slugs ) {
		$slugs = array_values( array_filter( array_map( 'sanitize_key', $slugs ) ) );
		$out = array();
		foreach ( $slugs as $pt ) {
			// Bounded so a huge post type can't exhaust memory building posts_data
			// in one pass. Raise via the filter on a healthy server if needed.
			$cap = (int) apply_filters( 'rk_core_export_posts_max', 2000, $pt );
			$q = new WP_Query( array(
				'post_type'              => $pt,
				'post_status'            => 'publish',
				'posts_per_page'         => $cap > 0 ? $cap : -1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'orderby'                => 'menu_order date',
				'order'                  => 'ASC',
				'suppress_filters'       => true,
			) );
			$posts = array();
			foreach ( $q->posts as $p ) { $posts[] = self::export_one_post( $p, $pt ); }
			if ( $posts ) { $out[] = array( 'post_type' => $pt, 'posts' => $posts ); }
		}
		wp_reset_postdata();
		return $out;
	}

	private static function export_one_post( $p, $pt = '' ) {
		$skip = self::skip_meta_keys();
		$meta = array();
		foreach ( get_post_meta( $p->ID ) as $k => $vals ) {
			if ( in_array( $k, $skip, true ) || 0 === strpos( $k, '_edit' ) || 0 === strpos( $k, '_elementor' ) ) { continue; }
			if ( count( $vals ) > 1 ) {
				$meta[ $k ] = array_map( 'maybe_unserialize', $vals );
			} else {
				$meta[ $k ] = maybe_unserialize( $vals[0] );
			}
		}
		$entry = array(
			'title'      => $p->post_title,
			'slug'       => $p->post_name,
			'content'    => $p->post_content,
			'excerpt'    => $p->post_excerpt,
			'date'       => $p->post_date,
			'menu_order' => (int) $p->menu_order,
			'meta'       => $meta,
		);
		$thumb = get_post_thumbnail_id( $p->ID );
		if ( $thumb ) {
			$url = wp_get_attachment_url( $thumb );
			if ( $url ) { $entry['featured_image_url'] = $url; }
		}
		$atts = get_attached_media( '', $p->ID );
		if ( $atts ) {
			$urls = array();
			foreach ( $atts as $a ) { $u = wp_get_attachment_url( $a->ID ); if ( $u ) { $urls[] = $u; } }
			if ( $urls ) { $entry['media'] = array_values( array_unique( $urls ) ); }
		}

		// Attachment-ID meta fields (image / gallery): record id => URL so the
		// destination can sideload and remap the IDs.
		$media_map = array();
		$used_keys = array();
		foreach ( self::media_keys_for( $pt ) as $mk ) {
			if ( ! isset( $meta[ $mk ] ) ) { continue; }
			$ids = array();
			self::ids_in_value( $meta[ $mk ], $ids );
			$hit = false;
			foreach ( array_keys( $ids ) as $aid ) {
				if ( 'attachment' === get_post_type( $aid ) ) {
					$u = wp_get_attachment_url( $aid );
					if ( $u ) { $media_map[ (string) $aid ] = $u; $hit = true; }
				}
			}
			if ( $hit ) { $used_keys[] = $mk; }
		}
		if ( $media_map ) { $entry['media_map'] = $media_map; $entry['media_meta_keys'] = array_values( array_unique( $used_keys ) ); }

		return $entry;
	}

	/** Import posts_data: insert/update posts, remap meta, sideload media. */
	public static function import_posts_data( $groups ) {
		if ( ! is_array( $groups ) ) { return 0; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$count = 0;
		foreach ( $groups as $g ) {
			if ( ! is_array( $g ) || empty( $g['post_type'] ) || empty( $g['posts'] ) || ! is_array( $g['posts'] ) ) { continue; }
			$pt = sanitize_key( $g['post_type'] );
			foreach ( $g['posts'] as $pd ) {
				if ( ! is_array( $pd ) ) { continue; }
				$slug = isset( $pd['slug'] ) ? sanitize_title( $pd['slug'] ) : '';

				$postarr = array(
					'post_type'    => $pt,
					'post_status'  => 'publish',
					'post_title'   => isset( $pd['title'] ) ? sanitize_text_field( $pd['title'] ) : '',
					'post_name'    => $slug,
					'post_content' => isset( $pd['content'] ) ? (string) $pd['content'] : '',
					'post_excerpt' => isset( $pd['excerpt'] ) ? (string) $pd['excerpt'] : '',
					'menu_order'   => isset( $pd['menu_order'] ) ? (int) $pd['menu_order'] : 0,
				);
				if ( ! empty( $pd['date'] ) ) { $postarr['post_date'] = sanitize_text_field( $pd['date'] ); }

				$existing = $slug ? get_page_by_path( $slug, OBJECT, $pt ) : null;
				if ( $existing ) { $postarr['ID'] = (int) $existing->ID; }
				$pid = $existing ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
				if ( is_wp_error( $pid ) || ! $pid ) { continue; }

				// Sideload attachment-id media referenced by meta and build an id remap.
				$id_remap = array();
				if ( ! empty( $pd['media_map'] ) && is_array( $pd['media_map'] ) ) {
					foreach ( $pd['media_map'] as $old => $url ) {
						$new = self::sideload_media( $url, $pid );
						if ( $new ) { $id_remap[ (int) $old ] = (int) $new; }
					}
				}
				$media_keys = isset( $pd['media_meta_keys'] ) ? array_map( 'strval', (array) $pd['media_meta_keys'] ) : array();

				// Meta (arrays / serialized supported; attachment ids remapped).
				if ( ! empty( $pd['meta'] ) && is_array( $pd['meta'] ) ) {
					foreach ( $pd['meta'] as $mk => $mv ) {
						$mk = (string) $mk;
						if ( '' === $mk || 0 === strpos( $mk, '_edit' ) || 0 === strpos( $mk, '_elementor' ) ) { continue; }
						if ( $id_remap && in_array( $mk, $media_keys, true ) ) { $mv = self::remap_ids( $mv, $id_remap ); }
						update_post_meta( $pid, $mk, wp_slash( $mv ) );
					}
				}

				// Featured image via sideload.
				if ( ! empty( $pd['featured_image_url'] ) ) {
					$att = self::sideload_media( $pd['featured_image_url'], $pid );
					if ( $att ) { set_post_thumbnail( $pid, $att ); }
				}
				$count++;
			}
		}
		return $count;
	}

	/** Download a remote URL into the media library, attached to $post_id. */
	/** Block sideloading from private / loopback / link-local hosts (SSRF guard). */
	private static function url_host_allowed( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) { return false; }
		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) { return false; }
		}
		return (bool) apply_filters( 'rk_core_sideload_host_allowed', true, $host, $url );
	}

	private static function sideload_media( $url, $post_id ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) { return 0; }
		if ( ! self::url_host_allowed( $url ) ) { return 0; } // SSRF guard (parity with RK Migrate Media).
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) { return 0; }
		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name ) { $name = 'image-' . time() . '.jpg'; }
		$file = array( 'name' => $name, 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, (int) $post_id );
		if ( is_wp_error( $id ) ) { @unlink( $tmp ); return 0; }
		return (int) $id;
	}

	/* ---------------- import ---------------- */

	/** Import a decoded bundle array. Returns a report (array of lines). */
	public static function import( $b ) {
		if ( ! is_array( $b ) ) { return array( 'Invalid file.' ); }
		$report = array();

		if ( ! empty( $b['post_types'] ) && is_array( $b['post_types'] ) ) {
			$b['post_types'] = array_map( array( __CLASS__, 'default_cpt_visibility' ), $b['post_types'] );
			$n = self::merge_by_slug( RK_CPT_Builder::OPTION, $b['post_types'], array( 'RK_CPT_Builder', 'sanitize_def' ) );
			$report[] = $n . ' post type(s)';
		}
		if ( ! empty( $b['taxonomies'] ) && is_array( $b['taxonomies'] ) ) {
			$n = self::merge_by_slug( RK_Taxonomy_Builder::OPTION, $b['taxonomies'], array( 'RK_Taxonomy_Builder', 'sanitize_def' ) );
			$report[] = $n . ' taxonomy(ies)';
		}
		if ( ! empty( $b['meta_boxes'] ) && is_array( $b['meta_boxes'] ) ) {
			$n = self::merge_by_id( RK_Field_Engine::OPTION, $b['meta_boxes'], array( 'RK_Field_Engine', 'sanitize_group' ) );
			$report[] = $n . ' meta box(es)';
		}
		if ( ! empty( $b['relations'] ) && is_array( $b['relations'] ) ) {
			$b['relations'] = array_map( array( __CLASS__, 'alias_relation' ), $b['relations'] );
			$n = self::merge_by_id( RK_Relations::OPTION, $b['relations'], array( 'RK_Relations', 'sanitize' ) );
			$report[] = $n . ' relation(s)';
		}
		if ( ! empty( $b['queries'] ) && is_array( $b['queries'] ) ) {
			$b['queries'] = array_map( array( __CLASS__, 'alias_query' ), $b['queries'] );
			$n = self::merge_by_id( RK_Query_Builder::OPTION, $b['queries'], array( 'RK_Query_Builder', 'sanitize' ) );
			$report[] = $n . ' query(ies)';
		}
		if ( ! empty( $b['posts_data'] ) && is_array( $b['posts_data'] ) ) {
			$n = self::import_posts_data( $b['posts_data'] );
			$report[] = $n . ' post(s) with content';
		}
		if ( ! empty( $b['jet_engine_listings'] ) && is_array( $b['jet_engine_listings'] ) ) {
			if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() ) {
				$n = RK_Core_JetEngine::import_listings( $b['jet_engine_listings'] );
				$report[] = $n . ' JetEngine listing(s)';
			} else {
				$report[] = count( $b['jet_engine_listings'] ) . ' listing(s) skipped — JetEngine not active';
			}
		}
		flush_rewrite_rules();
		return $report ? $report : array( 'Nothing to import.' );
	}

	/**
	 * Fill admin-visibility keys when an imported model omits them, so a CPT
	 * imported from a bare model (or another builder) still shows in wp-admin.
	 * Explicit values in the model are respected.
	 */
	private static function default_cpt_visibility( $c ) {
		if ( ! is_array( $c ) ) { return $c; }
		$public   = ! empty( $c['public'] );
		$defaults = array(
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => $public,
			'publicly_queryable'  => $public,
			'exclude_from_search' => ! $public,
		);
		foreach ( $defaults as $k => $v ) { if ( ! array_key_exists( $k, $c ) ) { $c[ $k ] = $v; } }
		return $c;
	}

	private static function merge_by_slug( $option, $items, $sanitizer = null ) {
		$cur = get_option( $option, array() );
		if ( ! is_array( $cur ) ) { $cur = array(); }
		$index = array();
		foreach ( $cur as $i => $it ) { if ( isset( $it['slug'] ) ) { $index[ $it['slug'] ] = $i; } }
		$added = 0;
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) { continue; }
			if ( $sanitizer ) { $it = call_user_func( $sanitizer, $it ); if ( is_wp_error( $it ) || empty( $it['slug'] ) ) { continue; } }
			elseif ( empty( $it['slug'] ) ) { continue; }
			$it['slug'] = sanitize_key( $it['slug'] );
			if ( isset( $index[ $it['slug'] ] ) ) { $cur[ $index[ $it['slug'] ] ] = $it; }
			else { $cur[] = $it; $index[ $it['slug'] ] = count( $cur ) - 1; }
			$added++;
		}
		update_option( $option, array_values( $cur ) );
		return $added;
	}

	private static function merge_by_id( $option, $items, $sanitizer = null ) {
		$cur = get_option( $option, array() );
		if ( ! is_array( $cur ) ) { $cur = array(); }
		$index = array();
		foreach ( $cur as $i => $it ) { if ( isset( $it['id'] ) ) { $index[ $it['id'] ] = $i; } }
		$added = 0;
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) { continue; }
			if ( $sanitizer ) { $it = call_user_func( $sanitizer, $it ); if ( is_wp_error( $it ) ) { continue; } }
			if ( empty( $it['id'] ) ) { $it['id'] = sanitize_key( uniqid( 'imp_' ) ); }
			else { $it['id'] = sanitize_key( $it['id'] ); }
			if ( isset( $index[ $it['id'] ] ) ) { $cur[ $index[ $it['id'] ] ] = $it; }
			else { $cur[] = $it; $index[ $it['id'] ] = count( $cur ) - 1; }
			$added++;
		}
		update_option( $option, array_values( $cur ) );
		return $added;
	}

	/** Back-compat: older generated models used different key names. */
	private static function alias_relation( $r ) {
		if ( is_array( $r ) && ! isset( $r['rel_type'] ) && isset( $r['type'] ) ) { $r['rel_type'] = $r['type']; }
		return $r;
	}
	private static function alias_query( $q ) {
		if ( is_array( $q ) && ! isset( $q['number'] ) && isset( $q['posts_per_page'] ) ) { $q['number'] = $q['posts_per_page']; }
		return $q;
	}

	/* ---------------- starters ---------------- */

	public static function starters() {
		return array(
			'services' => array( 'name' => 'Services', 'icon' => 'dashicons-hammer', 'desc' => 'Service post type + categories + details meta box (price, duration, icon, featured).' ),
			'event'    => array( 'name' => 'Events',   'icon' => 'dashicons-calendar-alt', 'desc' => 'Event post type + types taxonomy + details (dates, location, ticket URL, price).' ),
			'news'     => array( 'name' => 'News',     'icon' => 'dashicons-megaphone', 'desc' => 'News post type + categories + details (subtitle, source, external URL).' ),
			'class'    => array( 'name' => 'Classes',  'icon' => 'dashicons-welcome-learn-more', 'desc' => 'Class post type + level taxonomy + details (schedule, instructor, capacity, price).' ),
		);
	}

	public static function install_starter( $id ) {
		$id = sanitize_key( $id );
		if ( ! isset( self::starters()[ $id ] ) ) { return array( 'Unknown starter.' ); }
		$file = RK_CORE_DIR . 'data/starters/' . $id . '.json';
		if ( ! file_exists( $file ) ) { return array( 'Starter file missing.' ); }
		$b = json_decode( (string) file_get_contents( $file ), true );
		return self::import( $b );
	}

	/** A small example bundle users can attach to an AI as a format reference. */
	public static function example_bundle() {
		$file = RK_CORE_DIR . 'data/starters/services.json';
		if ( file_exists( $file ) ) {
			$b = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $b ) ) { return $b; }
		}
		return self::bundle( array(), array(), array(), array(), array() );
	}
}
