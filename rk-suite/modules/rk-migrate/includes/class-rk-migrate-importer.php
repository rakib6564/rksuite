<?php
/**
 * RK_Migrate_Importer — the reusable import engine.
 *
 * Source-agnostic: handed a base directory containing manifest.json + the
 * referenced JSON files. v3 adds find & replace, media re-link, global
 * colors/fonts, CPT import (featured image + taxonomies), rollback snapshots
 * and step-based execution for AJAX progress.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Importer {

	private $base;
	private $manifest;
	private $report = array();
	private $dry = false;
	/** @var RK_Migrate_Replace|null */
	private $replace = null;
	private $media_relink = false;
	private $conflict_default = 'overwrite';
	private $conflict_map = array();
	private $counts = array( 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'merged' => 0 );

	public function __construct( $base_dir ) {
		$this->base = trailingslashit( $base_dir );
	}

	public function base() { return $this->base; }
	public function counts() { return $this->counts; }

	public function get_manifest() {
		if ( $this->manifest ) { return $this->manifest; }
		$path = $this->base . 'manifest.json';
		if ( ! file_exists( $path ) ) { return null; }
		$data = json_decode( file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) { return null; }
		$this->manifest = $this->normalize_manifest( $data );
		return $this->manifest;
	}

	private function normalize_manifest( $m ) {
		$m['project']     = isset( $m['project'] ) ? $m['project'] : 'Elementor Project';
		$m['pages']       = isset( $m['pages'] ) && is_array( $m['pages'] ) ? $m['pages'] : array();
		$m['theme_parts'] = isset( $m['theme_parts'] ) && is_array( $m['theme_parts'] ) ? $m['theme_parts'] : array();
		$m['fragments']   = isset( $m['fragments'] ) && is_array( $m['fragments'] ) ? $m['fragments'] : array();
		$m['menus']       = isset( $m['menus'] ) && is_array( $m['menus'] ) ? $m['menus'] : array();
		$m['options']     = isset( $m['options'] ) && is_array( $m['options'] ) ? $m['options'] : array();
		$m['global_kit']  = isset( $m['global_kit'] ) ? $m['global_kit'] : '';
		$m['replace']     = isset( $m['replace'] ) && is_array( $m['replace'] ) ? $m['replace'] : array();
		return $m;
	}

	/** Build the dry-run plan table for the admin screen. */
	public function plan() {
		$m = $this->get_manifest();
		if ( ! $m ) { return array(); }
		$rows = array();
		foreach ( $m['pages'] as $p ) {
			$slug = isset( $p['slug'] ) ? $p['slug'] : '';
			$type = isset( $p['post_type'] ) ? $p['post_type'] : 'page';
			$existing = $slug ? get_page_by_path( $slug, OBJECT, $type ) : null;
			$rows[] = array(
				'slug'   => $slug,
				'title'  => isset( $p['title'] ) ? $p['title'] : $slug,
				'type'   => $type,
				'action' => $existing ? 'UPDATE' : 'CREATE',
				'id'     => $existing ? $existing->ID : 0,
			);
		}
		return $rows;
	}

	/** Post IDs that an import would touch (for pre-run snapshots). */
	public function affected_post_ids() {
		$ids = array();
		foreach ( $this->plan() as $r ) { if ( $r['id'] ) { $ids[] = $r['id']; } }
		return $ids;
	}

	/** Configure shared options (replace/media) before running. */
	private function configure( $opts ) {
		$this->dry          = ! empty( $opts['dry'] );
		$this->media_relink = ! empty( $opts['media_relink'] );
		$valid = array( 'overwrite', 'skip', 'new', 'merge' );
		$this->conflict_default = ( isset( $opts['conflict_default'] ) && in_array( $opts['conflict_default'], $valid, true ) ) ? $opts['conflict_default'] : 'overwrite';
		$this->conflict_map = ( isset( $opts['conflict_map'] ) && is_array( $opts['conflict_map'] ) ) ? $opts['conflict_map'] : array();
		if ( isset( $opts['replace'] ) && $opts['replace'] instanceof RK_Migrate_Replace ) {
			$this->replace = $opts['replace'];
		} else {
			$m = $this->get_manifest();
			$rules = $m ? $m['replace'] : array();
			if ( ! empty( $opts['replace_rules'] ) ) { $rules = array_merge( $rules, (array) $opts['replace_rules'] ); }
			if ( ! empty( $rules ) ) { $this->replace = new RK_Migrate_Replace( $rules ); }
		}
	}

	/** Full synchronous run (CLI, small sites). */
	public function run( $opts = array() ) {
		$this->configure( $opts );
		$this->report = array();
		$m = $this->get_manifest();
		if ( ! $m ) { $this->report[] = 'ERROR: manifest.json not found or invalid.'; $this->counts['errors']++; return $this->report; }

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			$this->report[] = 'WARNING: Elementor not active — layouts will not render until it is.';
		}

		if ( $m['global_kit'] && ! $this->dry ) {
			$this->report[] = $this->import_global_kit( $m['global_kit'] );
		}

		$front_id = 0;
		$slug_to_id = array();
		foreach ( $m['pages'] as $p ) {
			$res = $this->import_page( $p );
			if ( $res ) {
				$slug_to_id[ $res['slug'] ] = $res['id'];
				if ( ! empty( $p['is_front_page'] ) ) { $front_id = $res['id']; }
			}
		}

		if ( ! empty( $opts['set_front'] ) && $front_id && ! $this->dry ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front_id );
			$this->report[] = "Front page set to #{$front_id}";
		}

		foreach ( $m['theme_parts'] as $tp ) { $this->report[] = $this->do_theme_part( $tp, $m, $opts ); }
		foreach ( $m['fragments'] as $fr ) { $this->report[] = $this->do_fragment( $fr ); }

		if ( ! empty( $opts['build_menus'] ) && ! empty( $m['menus'] ) && ! $this->dry ) {
			foreach ( $m['menus'] as $menu ) { $this->report[] = 'Menu: ' . $this->build_menu( $menu, $slug_to_id ); }
		}

		if ( ! empty( $m['options'] ) && ! $this->dry ) {
			foreach ( $m['options'] as $k => $v ) {
				if ( in_array( $k, array( 'blogname', 'blogdescription' ), true ) ) {
					update_option( $k, $v );
					$this->report[] = "Option {$k} set.";
				}
			}
		}

		if ( ! $this->dry && class_exists( '\Elementor\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
			$this->report[] = 'Elementor CSS cache cleared.';
		}
		return $this->report;
	}

	/* ---------------- step-based execution (AJAX) ---------------- */

	/** Ordered list of step labels for a progress UI. */
	public function build_steps() {
		$m = $this->get_manifest();
		$steps = array();
		if ( ! $m ) { return $steps; }
		if ( $m['global_kit'] ) { $steps[] = array( 'kind' => 'global_kit' ); }
		foreach ( $m['pages'] as $i => $p ) { $steps[] = array( 'kind' => 'page', 'i' => $i, 'label' => isset( $p['slug'] ) ? $p['slug'] : '' ); }
		foreach ( $m['theme_parts'] as $i => $tp ) { $steps[] = array( 'kind' => 'theme_part', 'i' => $i, 'label' => isset( $tp['part'] ) ? $tp['part'] : 'part' ); }
		foreach ( $m['fragments'] as $i => $fr ) { $steps[] = array( 'kind' => 'fragment', 'i' => $i, 'label' => isset( $fr['title'] ) ? $fr['title'] : 'fragment' ); }
		if ( ! empty( $m['menus'] ) ) { $steps[] = array( 'kind' => 'menus' ); }
		$steps[] = array( 'kind' => 'finalize' );
		return $steps;
	}

	/**
	 * Execute one step. $state carries slug_to_id + front_id between calls.
	 * Returns [ 'lines' => [...], 'state' => $state ].
	 */
	public function run_step( $step, $opts, $state ) {
		$this->configure( $opts );
		$this->report = array();
		$m = $this->get_manifest();
		$state = wp_parse_args( $state, array( 'slug_to_id' => array(), 'front_id' => 0 ) );
		if ( ! is_array( $m ) ) { $this->report[] = 'Manifest missing or unreadable — run aborted.'; return array( 'state' => $state, 'lines' => $this->report ); }

		switch ( $step['kind'] ) {
			case 'global_kit':
				$this->report[] = $this->import_global_kit( $m['global_kit'] );
				break;
			case 'page':
				$p = isset( $m['pages'][ $step['i'] ] ) ? $m['pages'][ $step['i'] ] : null;
				$res = $p ? $this->import_page( $p ) : false;
				if ( $res ) {
					$state['slug_to_id'][ $res['slug'] ] = $res['id'];
					if ( ! empty( $p['is_front_page'] ) ) { $state['front_id'] = $res['id']; }
				}
				break;
			case 'theme_part':
				if ( isset( $m['theme_parts'][ $step['i'] ] ) ) { $this->report[] = $this->do_theme_part( $m['theme_parts'][ $step['i'] ], $m, $opts ); }
				break;
			case 'fragment':
				if ( isset( $m['fragments'][ $step['i'] ] ) ) { $this->report[] = $this->do_fragment( $m['fragments'][ $step['i'] ] ); }
				break;
			case 'menus':
				if ( ! empty( $opts['build_menus'] ) ) {
					foreach ( $m['menus'] as $menu ) { $this->report[] = 'Menu: ' . $this->build_menu( $menu, $state['slug_to_id'] ); }
				}
				break;
			case 'finalize':
				if ( ! empty( $opts['set_front'] ) && $state['front_id'] ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $state['front_id'] );
					$this->report[] = "Front page set to #{$state['front_id']}";
				}
				if ( ! empty( $m['options'] ) ) {
					foreach ( $m['options'] as $k => $v ) {
						if ( in_array( $k, array( 'blogname', 'blogdescription' ), true ) ) { update_option( $k, $v ); }
					}
				}
				if ( class_exists( '\Elementor\Plugin' ) ) { try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {} }
				$this->report[] = 'Finalized (front page, options, cache).';
				break;
		}
		return array( 'lines' => $this->report, 'state' => $state, 'counts' => $this->counts );
	}

	private function do_theme_part( $tp, $m, $opts ) {
		$json = $this->read_json( isset( $tp['file'] ) ? $tp['file'] : '' );
		if ( ! $json ) { return 'SKIP theme part (file unreadable)'; }
		$part  = isset( $tp['part'] ) ? $tp['part'] : 'header';
		$title = isset( $tp['title'] ) ? $tp['title'] : ( $m['project'] . ' ' . $part );
		if ( $this->dry ) { return "[dry] THEME PART {$part} ({$title})"; }
		$cond = empty( $opts['assign_parts'] ) ? null : ( isset( $tp['condition'] ) ? $tp['condition'] : 'include/general' );
		$id = $this->import_library_template( $title, $json, $part, $cond );
		return "THEME PART {$part} -> library #{$id}" . ( empty( $opts['assign_parts'] ) ? ' (assign manually)' : ' (assigned)' );
	}

	private function do_fragment( $fr ) {
		$json = $this->read_json( isset( $fr['file'] ) ? $fr['file'] : '' );
		if ( ! $json ) { return 'SKIP fragment (file unreadable)'; }
		$title = isset( $fr['title'] ) ? $fr['title'] : 'Template';
		if ( $this->dry ) { return "[dry] FRAGMENT {$title}"; }
		$id = $this->import_library_template( $title, $json, 'section', null );
		return "FRAGMENT -> library #{$id} ({$title})";
	}

	/* ---------------- page import ---------------- */
	private function import_page( $p ) {
		$slug = isset( $p['slug'] ) ? sanitize_title( $p['slug'] ) : '';
		if ( ! $slug ) { $this->report[] = 'SKIP page with no slug'; $this->counts['errors']++; return null; }
		$type = isset( $p['post_type'] ) ? $p['post_type'] : 'page';
		$json = $this->read_json( isset( $p['file'] ) ? $p['file'] : '' );
		if ( ! $json ) { $this->report[] = "SKIP {$slug} (file unreadable)"; $this->counts['errors']++; return null; }

		$existing = get_page_by_path( $slug, OBJECT, $type );
		$policy   = $existing
			? ( isset( $this->conflict_map[ $slug ] ) && in_array( $this->conflict_map[ $slug ], array( 'overwrite', 'skip', 'new', 'merge' ), true ) ? $this->conflict_map[ $slug ] : $this->conflict_default )
			: 'create';

		// SKIP: leave the existing page untouched, but still resolve it for menus.
		if ( 'skip' === $policy ) {
			if ( $this->dry ) { $this->report[] = "[dry] SKIP /{$slug}/ (exists)"; }
			else { $this->report[] = "SKIP /{$slug}/ (exists)"; $this->counts['skipped']++; }
			return array( 'slug' => $slug, 'id' => $existing->ID );
		}

		// NEW: keep the existing page, create a fresh one under a unique slug.
		$write_slug = $slug;
		if ( 'new' === $policy ) {
			$n = 2;
			while ( get_page_by_path( $write_slug, OBJECT, $type ) ) { $write_slug = $slug . '-' . $n; $n++; }
			$existing = null;
		}

		$action = $existing ? ( 'merge' === $policy ? 'MERGE' : 'UPDATE' ) : ( 'new' === $policy ? 'CREATE-NEW' : 'CREATE' );
		if ( $this->dry ) { $this->report[] = "[dry] {$action} ({$type}) /{$write_slug}/"; return array( 'slug' => $slug, 'id' => $existing ? $existing->ID : 0 ); }

		$postarr = array(
			'post_title'   => isset( $p['title'] ) ? $p['title'] : $slug,
			'post_name'    => $write_slug,
			'post_status'  => isset( $p['status'] ) ? $p['status'] : 'publish',
			'post_type'    => $type,
			'post_content' => isset( $p['content'] ) ? $p['content'] : '',
		);
		if ( isset( $p['excerpt'] ) ) { $postarr['post_excerpt'] = $p['excerpt']; }
		if ( $existing ) { $postarr['ID'] = $existing->ID; }

		$pid = wp_insert_post( $postarr, true );
		if ( is_wp_error( $pid ) ) { $this->report[] = "ERROR {$slug}: " . $pid->get_error_message(); $this->counts['errors']++; return null; }

		if ( 'merge' === $policy && $existing ) {
			$this->merge_elementor( $pid, $json );
			$this->counts['merged']++;
		} else {
			$this->apply_elementor( $pid, $json );
		}
		$this->apply_seo( $pid, $p );
		$this->apply_cpt_extras( $pid, $p ); // featured image + taxonomies + meta

		if ( 'merge' !== $policy ) { $existing ? $this->counts['updated']++ : $this->counts['created']++; }
		$this->report[] = "{$action} ({$type}) /{$write_slug}/ (post #{$pid})";
		return array( 'slug' => $slug, 'id' => $pid );
	}

	/** MERGE: append the imported top-level elements to the page's existing tree. */
	private function merge_elementor( $pid, $json ) {
		$incoming = isset( $json['content'] ) && is_array( $json['content'] ) ? $json['content'] : array();
		$current  = json_decode( (string) get_post_meta( $pid, '_elementor_data', true ), true );
		if ( ! is_array( $current ) ) { $current = array(); }
		$merged = array_merge( $current, $incoming );
		update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
		update_post_meta( $pid, '_elementor_template_type', 'wp-page' );
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $merged ) ) );
	}

	private function read_json( $file ) {
		if ( ! $file ) { return null; }
		$file = basename( $file );
		$path = $this->base . $file;
		if ( ! file_exists( $path ) ) { return null; }
		$data = json_decode( file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) { return null; }
		if ( $this->replace ) { $data = $this->replace->apply_to_array( $data ); }
		if ( $this->media_relink ) { $data = $this->relink_media( $data ); }
		return $data;
	}

	/** Sideload remote images and rewrite their URLs inside the JSON. */
	private function relink_media( $data ) {
		$urls = RK_Migrate_Media::collect_urls( $data );
		if ( empty( $urls ) ) { return $data; }
		$map = array();
		foreach ( $urls as $u ) {
			$new = RK_Migrate_Media::sideload( $u );
			if ( $new && $new !== $u ) { $map[ $u ] = $new; }
		}
		if ( empty( $map ) ) { return $data; }
		$rep = new RK_Migrate_Replace( $map );
		return $rep->apply_to_array( $data );
	}

	private function apply_elementor( $pid, $json ) {
		$content  = isset( $json['content'] ) ? $json['content'] : array();
		$settings = isset( $json['page_settings'] ) ? $json['page_settings'] : array();
		update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
		update_post_meta( $pid, '_elementor_template_type', 'wp-page' );
		if ( isset( $json['version'] ) ) { update_post_meta( $pid, '_elementor_version', $json['version'] ); }
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
		if ( ! empty( $settings ) ) { update_post_meta( $pid, '_elementor_page_settings', $settings ); }
	}

	private function apply_seo( $pid, $p ) {
		$title = isset( $p['seo_title'] ) ? $p['seo_title'] : '';
		$desc  = isset( $p['seo_desc'] ) ? $p['seo_desc'] : '';
		$kw    = isset( $p['focus_kw'] ) ? $p['focus_kw'] : '';
		if ( $title ) { update_post_meta( $pid, '_yoast_wpseo_title', $title ); update_post_meta( $pid, 'rank_math_title', $title ); }
		if ( $desc )  { update_post_meta( $pid, '_yoast_wpseo_metadesc', $desc ); update_post_meta( $pid, 'rank_math_description', $desc ); }
		if ( $kw )    { update_post_meta( $pid, '_yoast_wpseo_focuskw', $kw ); update_post_meta( $pid, 'rank_math_focus_keyword', $kw ); }
	}

	/** Featured image (URL or id), taxonomy terms, and arbitrary meta for CPTs. */
	private function apply_cpt_extras( $pid, $p ) {
		if ( ! empty( $p['featured_image'] ) ) {
			$url = $p['featured_image'];
			$att = RK_Migrate_Media::sideload( $url );
			if ( $att ) {
				$id = attachment_url_to_postid( $att );
				if ( $id ) { set_post_thumbnail( $pid, $id ); }
			}
		}
		if ( ! empty( $p['taxonomies'] ) && is_array( $p['taxonomies'] ) ) {
			foreach ( $p['taxonomies'] as $tax => $terms ) {
				if ( ! taxonomy_exists( $tax ) ) { continue; }
				wp_set_object_terms( $pid, (array) $terms, $tax, false );
			}
		}
		if ( ! empty( $p['meta'] ) && is_array( $p['meta'] ) ) {
			foreach ( $p['meta'] as $k => $v ) {
				if ( 0 === strpos( $k, '_elementor' ) ) { continue; } // never let manifest meta clobber layout
				update_post_meta( $pid, $k, $v );
			}
		}
	}

	/* ---------------- global colors & fonts (Elementor kit) ---------------- */
	private function import_global_kit( $file ) {
		$json = $this->read_json( $file );
		if ( ! $json ) { return 'SKIP global kit (file unreadable)'; }
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) { return 'SKIP global kit (no active Elementor kit on this site).'; }
		$settings = isset( $json['settings'] ) ? $json['settings'] : $json;
		$current  = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $current ) ) { $current = array(); }
		foreach ( array( 'system_colors', 'custom_colors', 'system_typography', 'custom_typography' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) { $current[ $key ] = $settings[ $key ]; }
		}
		update_post_meta( $kit_id, '_elementor_page_settings', $current );
		if ( class_exists( '\Elementor\Plugin' ) ) { try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {} }
		return 'Global colors & fonts imported into active kit #' . $kit_id;
	}

	/* ---------------- library templates ---------------- */
	private function import_library_template( $title, $json, $type, $condition ) {
		$existing = get_posts( array(
			'post_type'   => 'elementor_library',
			'title'       => $title,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		) );
		$postarr = array( 'post_title' => $title, 'post_status' => 'publish', 'post_type' => 'elementor_library' );
		if ( ! empty( $existing ) ) { $postarr['ID'] = $existing[0]; }
		$id = wp_insert_post( $postarr );
		if ( is_wp_error( $id ) ) { return 0; }

		$content = isset( $json['content'] ) ? $json['content'] : array();
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $type );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
		wp_set_object_terms( $id, $type, 'elementor_library_type' );

		if ( $condition && in_array( $type, array( 'header', 'footer' ), true ) ) {
			update_post_meta( $id, '_elementor_conditions', array( $condition ) );
			if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				try {
					$tb = \ElementorPro\Modules\ThemeBuilder\Module::instance();
					$cm = $tb->get_conditions_manager();
					if ( method_exists( $cm, 'get_cache' ) ) { $cm->get_cache()->regenerate(); }
				} catch ( \Throwable $e ) {}
			}
		}
		return $id;
	}

	/* ---------------- menus ---------------- */
	private function build_menu( $menu, $slug_to_id ) {
		$name = isset( $menu['name'] ) ? $menu['name'] : 'Primary';
		$obj = wp_get_nav_menu_object( $name );
		if ( ! $obj ) {
			$menu_id = wp_create_nav_menu( $name );
		} else {
			$menu_id = $obj->term_id;
			$items = wp_get_nav_menu_items( $menu_id );
			if ( $items ) { foreach ( $items as $it ) { wp_delete_post( $it->ID, true ); } }
		}
		if ( is_wp_error( $menu_id ) ) { return 'error creating "' . $name . '"'; }

		$count = $this->add_menu_items( $menu_id, isset( $menu['items'] ) ? $menu['items'] : array(), 0, $slug_to_id );

		$loc = isset( $menu['location'] ) ? $menu['location'] : null;
		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( ! is_array( $locations ) ) { $locations = array(); }
		$registered = get_registered_nav_menus();
		if ( $loc && isset( $registered[ $loc ] ) ) {
			$locations[ $loc ] = $menu_id;
		} else {
			foreach ( array( 'primary', 'menu-1', 'main', 'top' ) as $cand ) {
				if ( isset( $registered[ $cand ] ) ) { $loc = $cand; break; }
			}
			if ( ! $loc && ! empty( $registered ) ) { $loc = array_key_first( $registered ); }
			if ( $loc ) { $locations[ $loc ] = $menu_id; }
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		return '"' . $name . '" built (' . $count . ' items)' . ( $loc ? ' -> ' . $loc : ' (no location; assign manually)' );
	}

	private function add_menu_items( $menu_id, $items, $parent, $slug_to_id ) {
		$count = 0;
		foreach ( $items as $it ) {
			$label = isset( $it['label'] ) ? $it['label'] : '';
			$args = array( 'menu-item-title' => $label, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent );
			if ( ! empty( $it['url'] ) ) {
				$args['menu-item-type'] = 'custom';
				$args['menu-item-url']  = $it['url'];
			} elseif ( ! empty( $it['slug'] ) && isset( $slug_to_id[ sanitize_title( $it['slug'] ) ] ) ) {
				$args['menu-item-type']      = 'post_type';
				$args['menu-item-object']    = 'page';
				$args['menu-item-object-id'] = $slug_to_id[ sanitize_title( $it['slug'] ) ];
			} else { continue; }
			$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( ! is_wp_error( $item_id ) ) {
				$count++;
				if ( ! empty( $it['children'] ) && is_array( $it['children'] ) ) {
					$count += $this->add_menu_items( $menu_id, $it['children'], $item_id, $slug_to_id );
				}
			}
		}
		return $count;
	}
}
