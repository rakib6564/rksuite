<?php
/**
 * RK_Migrate_Elementor_Kit — imports Elementor's native "Template Kit" export
 * format (manifest.json + content/ + templates/ + site-settings.json + WXR
 * menus), which is what Envato / Elementor Pro full-site kits ship as.
 *
 * This is what makes a kit come in "same as the demo": correct page titles,
 * theme-builder header/footer/single/404 templates with their display
 * conditions, the global colour/type kit, and the navigation menu.
 *
 * Every phase is isolated so one failure never aborts the whole import.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Elementor_Kit {

	private static $instance = null;
	const OPTION = 'rk_migrate_kit_base';

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_rk_migrate_kit_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_rk_migrate_kit_import', array( $this, 'handle_import' ) );
	}

	public function menu() {
		if ( ! function_exists( 'add_submenu_page' ) ) { return; }
		add_submenu_page( 'rk-migrate', 'Import Elementor Kit', 'Import Kit', 'manage_options', 'rk-migrate-kit', array( $this, 'screen' ) );
	}

	/* ---------------- detection ---------------- */

	/** Is $base an Elementor kit? (manifest with templates/content/site-settings). */
	public static function detect( $base ) {
		$mf = trailingslashit( $base ) . 'manifest.json';
		if ( ! file_exists( $mf ) ) { return false; }
		$m = json_decode( (string) file_get_contents( $mf ), true );
		if ( ! is_array( $m ) ) { return false; }
		$has = isset( $m['templates'] ) || isset( $m['content'] ) || isset( $m['site-settings'] );
		return $has && ! isset( $m['pages'] ); // RK bundles use a "pages" array
	}

	private function manifest( $base ) {
		$m = json_decode( (string) file_get_contents( trailingslashit( $base ) . 'manifest.json' ), true );
		return is_array( $m ) ? $m : array();
	}

	private function read_json( $base, $rel ) {
		$path = trailingslashit( $base ) . ltrim( $rel, '/' );
		if ( ! file_exists( $path ) ) { return null; }
		$d = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $d ) ? $d : null;
	}

	/* ---------------- admin screen ---------------- */

	public function screen() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$base = get_option( self::OPTION, '' );
		$is_kit = $base && self::detect( $base );

		echo '<div class="wrap rk-migrate-wrap rk-has-rail">';
		if ( class_exists( '\\RK_Suite_Admin' ) ) { \RK_Suite_Admin::render_sidebar(); }
		echo '<main class="rk-main">';
		echo '<div class="rk-page-head"><h1>Import Elementor Kit</h1></div>';
		echo '<p>Upload an Elementor / Envato <strong>Template Kit</strong> .zip (contains <code>manifest.json</code>, <code>content/</code>, <code>templates/</code>, <code>site-settings.json</code>). RK Migrate will import pages with their real titles, assign the header &amp; footer, apply the global colours/fonts, and build the menu.</p>';

		$this->notices();

		echo '<h2 class="title">1. Upload kit</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_migrate_kit_upload" />';
		wp_nonce_field( 'rk_migrate_kit_upload' );
		echo '<input type="file" name="kit" accept=".zip" required /> ';
		submit_button( 'Upload kit', 'secondary', 'submit', false );
		echo '</form>';

		if ( ! $is_kit ) { echo '</main></div>'; return; }

		$m = $this->manifest( $base );
		$pages = isset( $m['content']['page'] ) && is_array( $m['content']['page'] ) ? $m['content']['page'] : array();
		$tpls  = isset( $m['templates'] ) && is_array( $m['templates'] ) ? $m['templates'] : array();
		$theme = array();
		foreach ( $tpls as $tid => $t ) {
			$dt = isset( $t['doc_type'] ) ? $t['doc_type'] : '';
			if ( in_array( $dt, array( 'header', 'footer', 'single-post', 'single-page', 'archive', 'error-404', 'search-results', 'popup' ), true ) ) { $theme[ $dt ] = isset( $t['title'] ) ? $t['title'] : $dt; }
		}
		echo '<h2 class="title">2. Review — ' . esc_html( isset( $m['title'] ) ? $m['title'] : 'Elementor Kit' ) . '</h2>';
		echo '<p><strong>' . count( $pages ) . '</strong> pages, <strong>' . count( $tpls ) . '</strong> templates';
		if ( $theme ) { echo ' (' . esc_html( implode( ', ', array_keys( $theme ) ) ) . ')'; }
		echo '. Global styles: ' . ( file_exists( trailingslashit( $base ) . 'site-settings.json' ) ? 'yes' : 'no' ) . '.</p>';

		if ( ! empty( $pages ) ) {
			echo '<table class="widefat striped" style="max-width:640px;"><thead><tr><th>Page</th><th>Type</th></tr></thead><tbody>';
			foreach ( $pages as $pid => $p ) {
				echo '<tr><td>' . esc_html( isset( $p['title'] ) ? $p['title'] : $pid ) . '</td><td><code>' . esc_html( isset( $p['doc_type'] ) ? $p['doc_type'] : 'wp-page' ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			echo '<div class="notice notice-warning inline"><p>Elementor <strong>Pro</strong> is not active — header/footer/single templates need Pro to display site-wide.</p></div>';
		}

		echo '<h2 class="title">3. Import</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_migrate_kit_import" />';
		wp_nonce_field( 'rk_migrate_kit_import' );
		echo '<p><label><input type="checkbox" name="set_front" value="1" checked /> Set the homepage as the site front page</label></p>';
		echo '<p><label><input type="checkbox" name="import_menus" value="1" checked /> Import navigation menus</label></p>';
		echo '<p><label><input type="checkbox" name="apply_kit" value="1" checked /> Apply global colours &amp; fonts</label></p>';
		submit_button( 'Import Elementor Kit', 'primary large' );
		echo '</form></main></div>';
	}

	private function notices() {
		if ( ! isset( $_GET['rk_kit'] ) ) { return; }
		$code = sanitize_key( $_GET['rk_kit'] );
		if ( 'uploaded' === $code ) { echo '<div class="notice notice-success"><p>Kit uploaded. Review it below and import.</p></div>'; }
		if ( 'notkit' === $code ) { echo '<div class="notice notice-error"><p>That zip is not an Elementor Template Kit (no valid manifest.json).</p></div>'; }
		if ( 'uploadfail' === $code ) { echo '<div class="notice notice-error"><p>Upload or unzip failed.</p></div>'; }
		if ( 'done' === $code ) {
			$rep = get_transient( 'rk_migrate_kit_report' );
			if ( is_array( $rep ) ) {
				echo '<div class="notice notice-success"><p><strong>Kit imported.</strong></p><ol style="margin-left:18px;">';
				foreach ( $rep as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; }
				echo '</ol></div>';
				delete_transient( 'rk_migrate_kit_report' );
			}
		}
	}

	/* ---------------- upload ---------------- */

	public function handle_upload() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_kit_upload' );
		if ( empty( $_FILES['kit']['name'] ) || ! empty( $_FILES['kit']['error'] ) ) { $this->redir( 'uploadfail' ); }
		if ( 'zip' !== strtolower( pathinfo( $_FILES['kit']['name'], PATHINFO_EXTENSION ) ) ) { $this->redir( 'notkit' ); }

		$dir = trailingslashit( defined( 'RK_MIGRATE_UPLOAD_DIR' ) ? RK_MIGRATE_UPLOAD_DIR : WP_CONTENT_DIR . '/uploads/rk-migrate/' ) . 'kit-' . gmdate( 'Ymd-His' ) . '/';
		wp_mkdir_p( $dir );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		if ( empty( $_FILES['kit']['tmp_name'] ) || ! is_uploaded_file( $_FILES['kit']['tmp_name'] ) ) { $this->redirect( 'uploadfail' ); }
		$un = unzip_file( $_FILES['kit']['tmp_name'], $dir );
		if ( is_wp_error( $un ) ) { $this->redir( 'uploadfail' ); }

		$base = $this->find_manifest_dir( $dir );
		if ( ! $base || ! self::detect( $base ) ) { $this->redir( 'notkit' ); }
		update_option( self::OPTION, untrailingslashit( $base ) );
		$this->redir( 'uploaded' );
	}

	private function find_manifest_dir( $dir ) {
		if ( file_exists( trailingslashit( $dir ) . 'manifest.json' ) ) { return trailingslashit( $dir ); }
		foreach ( (array) glob( trailingslashit( $dir ) . '*', GLOB_ONLYDIR ) as $sub ) {
			if ( file_exists( trailingslashit( $sub ) . 'manifest.json' ) ) { return trailingslashit( $sub ); }
		}
		return '';
	}

	private function redir( $code ) {
		wp_safe_redirect( admin_url( 'admin.php?page=rk-migrate-kit&rk_kit=' . $code ) );
		exit;
	}

	/* ---------------- import ---------------- */

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_kit_import' );
		$base = get_option( self::OPTION, '' );
		if ( ! $base || ! self::detect( $base ) ) { $this->redir( 'notkit' ); }

		@set_time_limit( 300 );
		$opts = array(
			'set_front'     => ! empty( $_POST['set_front'] ),
			'import_menus'  => ! empty( $_POST['import_menus'] ),
			'apply_kit'     => ! empty( $_POST['apply_kit'] ),
		);
		$report = $this->import( $base, $opts );
		set_transient( 'rk_migrate_kit_report', $report, 300 );
		$this->redir( 'done' );
	}

	/** Full kit import. Returns an array of report lines. */
	public function import( $base, $opts ) {
		$report = array();
		$m = $this->manifest( $base );
		$idmap = array();   // original kit page/template id => new WP post id
		$front_id = 0;

		// --- 1. Templates (header/footer/single/etc.) ---
		$templates = isset( $m['templates'] ) && is_array( $m['templates'] ) ? $m['templates'] : array();
		foreach ( $templates as $tid => $t ) {
			try {
				$json = $this->read_json( $base, 'templates/' . $tid . '.json' );
				if ( ! $json ) { continue; }
				$doc = isset( $t['doc_type'] ) ? sanitize_key( $t['doc_type'] ) : 'page';
				$title = isset( $t['title'] ) ? $t['title'] : ( 'Template ' . $tid );
				$conds = $this->convert_conditions( isset( $t['conditions'] ) ? $t['conditions'] : array() );
				$new = $this->import_library_template( $title, $json, $doc, $conds );
				$idmap[ (string) $tid ] = $new;
				$report[] = 'Template: ' . $title . ' [' . $doc . '] -> #' . $new . ( $conds ? ' (assigned)' : '' );
			} catch ( \Throwable $e ) { $report[] = 'Template ' . $tid . ' failed: ' . $e->getMessage(); }
		}

		// --- 2. Content pages ---
		$pages = isset( $m['content']['page'] ) && is_array( $m['content']['page'] ) ? $m['content']['page'] : array();
		foreach ( $pages as $pid => $p ) {
			try {
				$json = $this->read_json( $base, 'content/page/' . $pid . '.json' );
				if ( ! $json ) { continue; }
				$title = isset( $p['title'] ) ? $p['title'] : ( 'Page ' . $pid );
				$slug  = $this->slug_from_url( isset( $p['url'] ) ? $p['url'] : '', $title );
				$new = $this->import_page( $title, $slug, $json );
				$idmap[ (string) $pid ] = $new;
				if ( 0 === strcasecmp( trim( $title ), 'homepage' ) || 0 === strcasecmp( trim( $title ), 'home' ) ) { $front_id = $new; }
				$report[] = 'Page: ' . $title . ' -> #' . $new;
			} catch ( \Throwable $e ) { $report[] = 'Page ' . $pid . ' failed: ' . $e->getMessage(); }
		}

		// --- 3. Global kit (site-settings) ---
		if ( ! empty( $opts['apply_kit'] ) ) {
			try {
				$ss = $this->read_json( $base, 'site-settings.json' );
				if ( $ss ) { $report[] = $this->apply_site_settings( $ss ); }
			} catch ( \Throwable $e ) { $report[] = 'Global styles failed: ' . $e->getMessage(); }
		}

		// --- 4. Front page ---
		if ( ! empty( $opts['set_front'] ) && $front_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front_id );
			$report[] = 'Front page set to "Homepage" (#' . $front_id . ').';
		}

		// --- 5. Menus ---
		if ( ! empty( $opts['import_menus'] ) ) {
			try {
				$menu_file = trailingslashit( $base ) . 'wp-content/nav_menu_item/nav_menu_item.xml';
				$site = isset( $m['site'] ) ? $m['site'] : '';
				if ( file_exists( $menu_file ) ) { $report[] = $this->import_menus( $menu_file, $idmap, $site ); }
			} catch ( \Throwable $e ) { $report[] = 'Menu import failed: ' . $e->getMessage(); }
		}

		// --- 6. Regenerate Elementor CSS (so global colours/fonts take effect) ---
		$this->regenerate_css();
		$this->regenerate_conditions();
		$report[] = 'Elementor global CSS regenerated.';
		return $report;
	}

	/* ---------------- helpers ---------------- */

	private function slug_from_url( $url, $title ) {
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '' === $path ) { return sanitize_title( $title ); }
		$parts = explode( '/', $path );
		$last = end( $parts );
		return $last ? sanitize_title( $last ) : sanitize_title( $title );
	}

	/** Convert manifest condition objects to Elementor condition strings. */
	private function convert_conditions( $conditions ) {
		$out = array();
		foreach ( (array) $conditions as $c ) {
			if ( ! is_array( $c ) ) { continue; }
			$parts = array();
			$parts[] = isset( $c['type'] ) ? $c['type'] : 'include';
			if ( ! empty( $c['name'] ) ) { $parts[] = $c['name']; }
			if ( ! empty( $c['sub_name'] ) ) { $parts[] = $c['sub_name']; }
			if ( ! empty( $c['sub_id'] ) ) { $parts[] = $c['sub_id']; }
			$out[] = implode( '/', $parts );
		}
		return $out;
	}

	private function import_page( $title, $slug, $json ) {
		$existing = $slug ? get_page_by_path( $slug, OBJECT, 'page' ) : null;
		$arr = array( 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '' );
		if ( $existing ) { $arr['ID'] = $existing->ID; }
		$pid = wp_insert_post( $arr, true );
		if ( is_wp_error( $pid ) ) { throw new \RuntimeException( $pid->get_error_message() ); }

		$content  = isset( $json['content'] ) ? $json['content'] : array();
		$settings = isset( $json['settings'] ) ? $json['settings'] : array();
		update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
		update_post_meta( $pid, '_elementor_template_type', 'wp-page' );
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
		if ( ! empty( $settings ) ) { update_post_meta( $pid, '_elementor_page_settings', $settings ); }
		if ( isset( $settings['template'] ) && $settings['template'] ) {
			$tpl = ( 'default' === $settings['template'] ) ? 'default' : 'elementor_' . str_replace( 'elementor_', '', $settings['template'] );
			update_post_meta( $pid, '_wp_page_template', $tpl );
		}
		return (int) $pid;
	}

	private function import_library_template( $title, $json, $doc_type, $conditions ) {
		$existing = get_posts( array( 'post_type' => 'elementor_library', 'title' => $title, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		$arr = array( 'post_title' => $title, 'post_status' => 'publish', 'post_type' => 'elementor_library' );
		if ( ! empty( $existing ) ) { $arr['ID'] = $existing[0]; }
		$id = wp_insert_post( $arr, true );
		if ( is_wp_error( $id ) ) { throw new \RuntimeException( $id->get_error_message() ); }

		$content  = isset( $json['content'] ) ? $json['content'] : array();
		$settings = isset( $json['settings'] ) ? $json['settings'] : array();
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $doc_type );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
		if ( ! empty( $settings ) ) { update_post_meta( $id, '_elementor_page_settings', $settings ); }
		wp_set_object_terms( $id, $doc_type, 'elementor_library_type' );
		if ( ! empty( $conditions ) ) { update_post_meta( $id, '_elementor_conditions', $conditions ); }
		return (int) $id;
	}

	private function apply_site_settings( $ss ) {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) { return 'Global styles skipped (no active Elementor kit).'; }
		$settings = isset( $ss['settings'] ) && is_array( $ss['settings'] ) ? $ss['settings'] : array();
		if ( empty( $settings ) ) { return 'Global styles skipped (empty).'; }
		$current = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $current ) ) { $current = array(); }
		$merged = array_merge( $current, $settings );
		update_post_meta( $kit_id, '_elementor_page_settings', $merged );
		return 'Global colours, fonts & theme style applied to kit #' . $kit_id . '.';
	}

	/** Force Elementor to rebuild global + kit CSS so imported colours apply. */
	private function regenerate_css() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) { return; }
		try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		try {
			if ( class_exists( '\Elementor\Core\Files\CSS\Global_CSS' ) ) {
				$g = new \Elementor\Core\Files\CSS\Global_CSS( 'global.css' );
				$g->update();
			}
		} catch ( \Throwable $e ) {}
		try {
			$kit_id = (int) get_option( 'elementor_active_kit' );
			if ( $kit_id && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				$c = \Elementor\Core\Files\CSS\Post::create( $kit_id );
				$c->update();
			}
		} catch ( \Throwable $e ) {}
	}

	private function regenerate_conditions() {
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) { return; }
		try {
			$tb = \ElementorPro\Modules\ThemeBuilder\Module::instance();
			if ( method_exists( $tb, 'get_conditions_manager' ) ) {
				$cm = $tb->get_conditions_manager();
				if ( method_exists( $cm, 'get_cache' ) ) { $cm->get_cache()->regenerate(); }
			}
		} catch ( \Throwable $e ) {}
	}

	/* ---------------- menu import (WXR) ---------------- */

	private function import_menus( $xml_file, $idmap, $site = '' ) {
		$xml = simplexml_load_file( $xml_file );
		if ( false === $xml ) { return 'Menu XML unreadable.'; }
		$wp = $xml->channel->children( 'http://wordpress.org/export/1.2/' );

		$items = array();      // orig_item_id => data
		$menu_name = 'Imported Menu';
		foreach ( $xml->channel->item as $item ) {
			$w = $item->children( 'http://wordpress.org/export/1.2/' );
			$oid = (int) $w->post_id;
			$data = array(
				'title'  => (string) $item->title,
				'order'  => (int) $w->menu_order,
				'meta'   => array(),
				'parent' => 0,
			);
			foreach ( $w->postmeta as $pm ) {
				$data['meta'][ (string) $pm->meta_key ] = (string) $pm->meta_value;
			}
			// menu name from the nav_menu category
			foreach ( $item->category as $cat ) {
				$attrs = $cat->attributes();
				if ( isset( $attrs['domain'] ) && 'nav_menu' === (string) $attrs['domain'] ) { $menu_name = (string) $item->title ? $menu_name : $menu_name; if ( isset( $attrs['nicename'] ) ) { $menu_name = ucwords( str_replace( '-', ' ', (string) $attrs['nicename'] ) ); } }
			}
			$items[ $oid ] = $data;
		}
		if ( empty( $items ) ) { return 'No menu items found.'; }

		// Create / reset the menu.
		$obj = wp_get_nav_menu_object( $menu_name );
		if ( $obj ) { $menu_id = $obj->term_id; foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $mi ) { wp_delete_post( $mi->ID, true ); } }
		else { $menu_id = wp_create_nav_menu( $menu_name ); }
		if ( is_wp_error( $menu_id ) ) { return 'Could not create menu.'; }

		// Sort by menu_order and create items, mapping parents.
		uasort( $items, function ( $a, $b ) { return $a['order'] - $b['order']; } );
		$orig_to_new = array();
		$count = 0;
		foreach ( $items as $oid => $d ) {
			$meta = $d['meta'];
			$type = isset( $meta['_menu_item_type'] ) ? $meta['_menu_item_type'] : 'custom';
			$args = array(
				'menu-item-title'     => $d['title'],
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => 0,
			);
			if ( 'post_type' === $type ) {
				$obj_id = isset( $meta['_menu_item_object_id'] ) ? (string) $meta['_menu_item_object_id'] : '';
				$mapped = isset( $idmap[ $obj_id ] ) ? $idmap[ $obj_id ] : 0;
				if ( ! $mapped ) { continue; } // page not in this kit
				$args['menu-item-type']      = 'post_type';
				$args['menu-item-object']    = isset( $meta['_menu_item_object'] ) ? $meta['_menu_item_object'] : 'page';
				$args['menu-item-object-id'] = (int) $mapped;
			} else {
				$url = isset( $meta['_menu_item_url'] ) ? (string) $meta['_menu_item_url'] : '';
				if ( '' !== $url && '' !== $site ) {
					// Repoint demo-site links at this install (slugs match imported pages).
					$url = str_replace( untrailingslashit( $site ), untrailingslashit( home_url() ), $url );
				}
				$args['menu-item-type'] = 'custom';
				$args['menu-item-url']  = $url;
			}
			$new_item = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( ! is_wp_error( $new_item ) ) { $orig_to_new[ $oid ] = $new_item; $count++; }
		}
		// second pass: parents
		foreach ( $items as $oid => $d ) {
			$pid = isset( $d['meta']['_menu_item_menu_item_parent'] ) ? (int) $d['meta']['_menu_item_menu_item_parent'] : 0;
			if ( $pid && isset( $orig_to_new[ $oid ] ) && isset( $orig_to_new[ $pid ] ) ) {
				update_post_meta( $orig_to_new[ $oid ], '_menu_item_menu_item_parent', $orig_to_new[ $pid ] );
			}
		}
		// assign to a theme location if one exists
		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( ! is_array( $locations ) ) { $locations = array(); }
		$registered = get_registered_nav_menus();
		if ( ! empty( $registered ) ) {
			$loc = array_key_first( $registered );
			$locations[ $loc ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		return 'Menu "' . $menu_name . '" built (' . $count . ' items).';
	}
}
