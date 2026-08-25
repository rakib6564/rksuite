<?php
/**
 * RK_API — REST management API for external tools (MCP, automation).
 *
 * @package RK_API
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_API {

	const NS         = 'rk/v1';
	const OPT_KEY    = 'rk_api_key';
	const ELEM_DATA  = '_elementor_data';
	const ELEM_MODE  = '_elementor_edit_mode';
	const ELEM_PS    = '_elementor_page_settings';
	const ELEM_VER   = '_elementor_version';

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/** Generate the API key once if missing. */
	public static function ensure_key() {
		if ( ! get_option( self::OPT_KEY ) ) {
			update_option( self::OPT_KEY, 'rk_' . wp_generate_password( 40, false, false ) );
		}
	}
	public static function api_key() { self::ensure_key(); return (string) get_option( self::OPT_KEY ); }
	public static function rotate_key() { update_option( self::OPT_KEY, 'rk_' . wp_generate_password( 40, false, false ) ); return self::api_key(); }

	/* ---------------- auth ---------------- */

	/**
	 * Allow when: (a) the request is authenticated as a user who can edit_pages
	 * (WordPress Application Password / cookie), OR (b) a valid RK API key is
	 * presented via the X-RK-API-Key header or ?api_key= query param.
	 */
	public function auth( \WP_REST_Request $req ) {
		if ( is_user_logged_in() && current_user_can( 'edit_pages' ) ) { return true; }
		$key = $req->get_header( 'x_rk_api_key' );
		if ( ! $key ) { $key = $req->get_param( 'api_key' ); }
		if ( $key && hash_equals( self::api_key(), (string) $key ) ) { return true; }
		return new \WP_Error( 'rk_api_forbidden', 'Authentication required: use a WordPress Application Password or the RK API key.', array( 'status' => 401 ) );
	}

	private function r( $route, $methods, $cb, $args = array() ) {
		register_rest_route( self::NS, $route, array(
			'methods'             => $methods,
			'callback'            => array( $this, $cb ),
			'permission_callback' => array( $this, 'auth' ),
			'args'                => $args,
		) );
	}

	public function routes() {
		$this->r( '/ping', 'GET', 'ping' );
		// pages
		$this->r( '/pages', 'GET', 'list_pages' );
		$this->r( '/pages', 'POST', 'create_page' );
		$this->r( '/pages/(?P<id>\d+)', 'GET', 'get_page' );
		$this->r( '/pages/(?P<id>\d+)', 'POST', 'update_page' );
		$this->r( '/pages/(?P<id>\d+)', 'DELETE', 'delete_page' );
		$this->r( '/pages/(?P<id>\d+)/duplicate', 'POST', 'duplicate_page' );
		$this->r( '/pages/copy-from', 'POST', 'copy_from' );
		$this->r( '/pages/(?P<id>\d+)/replace', 'POST', 'replace_text' );
		$this->r( '/pages/(?P<id>\d+)/widget', 'POST', 'update_widget' );
		$this->r( '/globals', 'GET', 'read_globals' );
		$this->r( '/pages/(?P<id>\d+)/apply-globals', 'POST', 'apply_globals' );
		$this->r( '/pages/(?P<id>\d+)/seo', 'GET', 'get_seo' );
		$this->r( '/pages/(?P<id>\d+)/seo', 'POST', 'set_seo' );
		// templates / media / menus
		$this->r( '/templates', 'GET', 'list_templates' );
		$this->r( '/media', 'GET', 'list_media' );
		$this->r( '/media/sideload', 'POST', 'sideload_media' );
		$this->r( '/menus', 'GET', 'list_menus' );
		// suite-level
		$this->r( '/modules', 'GET', 'list_modules' );
		$this->r( '/modules/toggle', 'POST', 'toggle_module' );
		$this->r( '/site/health', 'GET', 'site_health' );
		$this->r( '/site/scan', 'GET', 'site_scan' );
		$this->r( '/seo/search-appearance', 'GET', 'get_appearance' );
		$this->r( '/seo/search-appearance', 'POST', 'set_appearance' );
		$this->r( '/seo/redirects', 'GET', 'list_redirects' );
		$this->r( '/seo/redirects', 'POST', 'add_redirect' );
		$this->r( '/seo/redirects/(?P<id>\d+)', 'DELETE', 'del_redirect' );
		$this->r( '/flush-css', 'POST', 'flush_css' );
	}

	/* ---------------- helpers ---------------- */

	private function ok( $data ) { return new \WP_REST_Response( $data, 200 ); }
	private function err( $code, $msg, $status = 400 ) { return new \WP_Error( $code, $msg, array( 'status' => $status ) ); }

	private function elementor_data( $pid ) {
		$raw = get_post_meta( $pid, self::ELEM_DATA, true );
		$d   = $raw ? json_decode( $raw, true ) : array();
		return is_array( $d ) ? $d : array();
	}

	/** Flush Elementor's generated CSS so edits show immediately. */
	public static function flush_elementor_css( $pid = 0 ) {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
				return true;
			} catch ( \Throwable $e ) {}
		}
		if ( $pid ) { delete_post_meta( $pid, '_elementor_css' ); }
		delete_option( '_elementor_global_css' );
		return true;
	}

	/** Recursive Elementor tree walker (by reference). */
	public static function walk( &$nodes, $cb ) {
		if ( ! is_array( $nodes ) ) { return; }
		foreach ( $nodes as &$n ) {
			if ( is_array( $n ) ) {
				if ( isset( $n['id'] ) ) { call_user_func_array( $cb, array( &$n ) ); }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) { self::walk( $n['elements'], $cb ); }
			}
		}
		unset( $n );
	}

	private function is_elementor( $pid ) {
		return 'builder' === get_post_meta( $pid, self::ELEM_MODE, true );
	}

	private function page_summary( $p ) {
		return array(
			'id'        => (int) $p->ID,
			'title'     => $p->post_title,
			'slug'      => $p->post_name,
			'status'    => $p->post_status,
			'type'      => $p->post_type,
			'elementor' => $this->is_elementor( $p->ID ),
			'link'      => get_permalink( $p ),
			'edit'      => admin_url( 'post.php?post=' . $p->ID . '&action=elementor' ),
			'modified'  => $p->post_modified_gmt,
		);
	}

	/* ---------------- endpoints: read ---------------- */

	public function ping() {
		return $this->ok( array(
			'ok' => true, 'plugin' => 'RK API', 'version' => RK_API_VERSION,
			'suite' => defined( 'RK_SUITE_VERSION' ) ? RK_SUITE_VERSION : null,
			'elementor' => class_exists( '\Elementor\Plugin' ),
			'site' => home_url( '/' ), 'user' => wp_get_current_user()->user_login,
		) );
	}

	public function list_pages( $req ) {
		$args = array(
			'post_type'      => $req->get_param( 'post_type' ) ? sanitize_key( $req->get_param( 'post_type' ) ) : 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => min( 100, max( 1, (int) ( $req->get_param( 'per_page' ) ?: 25 ) ) ),
			'paged'          => max( 1, (int) ( $req->get_param( 'page' ) ?: 1 ) ),
			's'              => $req->get_param( 'search' ) ? sanitize_text_field( $req->get_param( 'search' ) ) : '',
			'orderby'        => 'modified', 'order' => 'DESC',
		);
		if ( $req->get_param( 'elementor_only' ) ) { $args['meta_key'] = self::ELEM_MODE; $args['meta_value'] = 'builder'; }
		$q = new WP_Query( $args );
		$out = array();
		foreach ( $q->posts as $p ) { $out[] = $this->page_summary( $p ); }
		return $this->ok( array( 'total' => (int) $q->found_posts, 'pages' => $out ) );
	}

	public function get_page( $req ) {
		$pid = (int) $req['id'];
		$p = get_post( $pid );
		if ( ! $p ) { return $this->err( 'not_found', 'Page not found.', 404 ); }
		$data = $this->page_summary( $p );
		$data['content']       = $p->post_content;
		$data['excerpt']       = $p->post_excerpt;
		$data['elementor_data']= $this->elementor_data( $pid );
		$data['page_settings'] = get_post_meta( $pid, self::ELEM_PS, true ) ?: array();
		$data['seo']           = $this->seo_read( $pid );
		return $this->ok( $data );
	}

	public function list_templates() {
		$q = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => 100 ) );
		$out = array();
		foreach ( $q as $t ) {
			$type = wp_get_object_terms( $t->ID, 'elementor_library_type', array( 'fields' => 'slugs' ) );
			$out[] = array( 'id' => $t->ID, 'title' => $t->post_title, 'type' => $type ? $type[0] : 'section' );
		}
		return $this->ok( array( 'templates' => $out ) );
	}

	public function list_media( $req ) {
		$q = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => min( 100, (int) ( $req->get_param( 'per_page' ) ?: 25 ) ), 's' => $req->get_param( 'search' ) ?: '' ) );
		$out = array();
		foreach ( $q as $a ) { $out[] = array( 'id' => $a->ID, 'title' => $a->post_title, 'url' => wp_get_attachment_url( $a->ID ), 'alt' => get_post_meta( $a->ID, '_wp_attachment_image_alt', true ) ); }
		return $this->ok( array( 'media' => $out ) );
	}

	public function list_menus() {
		$out = array();
		foreach ( wp_get_nav_menus() as $m ) {
			$items = wp_get_nav_menu_items( $m->term_id );
			$out[] = array( 'id' => $m->term_id, 'name' => $m->name, 'count' => is_array( $items ) ? count( $items ) : 0,
				'items' => is_array( $items ) ? array_map( function ( $i ) { return array( 'title' => $i->title, 'url' => $i->url ); }, $items ) : array() );
		}
		return $this->ok( array( 'menus' => $out ) );
	}

	/* ---------------- endpoints: page lifecycle ---------------- */

	public function create_page( $req ) {
		$title  = sanitize_text_field( (string) $req->get_param( 'title' ) );
		if ( '' === $title ) { return $this->err( 'bad', 'title is required.' ); }
		$status = in_array( $req->get_param( 'status' ), array( 'publish', 'draft', 'private', 'pending' ), true ) ? $req->get_param( 'status' ) : 'draft';
		$pid = wp_insert_post( array(
			'post_title'   => $title,
			'post_status'  => $status,
			'post_type'    => $req->get_param( 'post_type' ) ? sanitize_key( $req->get_param( 'post_type' ) ) : 'page',
			'post_content' => (string) $req->get_param( 'content' ),
			'post_excerpt' => (string) $req->get_param( 'excerpt' ),
		), true );
		if ( is_wp_error( $pid ) ) { return $pid; }
		$ed = $req->get_param( 'elementor_data' );
		if ( ! empty( $ed ) ) { $this->write_elementor( $pid, $ed, $req->get_param( 'page_settings' ) ); }
		if ( $req->get_param( 'normalize_globals' ) ) { $this->normalize_page_fonts( $pid, $req->get_param( 'heading_font' ), $req->get_param( 'body_font' ) ); }
		if ( $req->get_param( 'seo' ) ) { $this->seo_write( $pid, (array) $req->get_param( 'seo' ) ); }
		return $this->ok( array( 'created' => true, 'page' => $this->page_summary( get_post( $pid ) ) ) );
	}

	public function update_page( $req ) {
		$pid = (int) $req['id'];
		if ( ! get_post( $pid ) ) { return $this->err( 'not_found', 'Page not found.', 404 ); }
		$patch = array( 'ID' => $pid );
		if ( null !== $req->get_param( 'title' ) )   { $patch['post_title']   = sanitize_text_field( $req->get_param( 'title' ) ); }
		if ( null !== $req->get_param( 'status' ) )  { $patch['post_status']  = sanitize_key( $req->get_param( 'status' ) ); }
		if ( null !== $req->get_param( 'content' ) ) { $patch['post_content'] = (string) $req->get_param( 'content' ); }
		if ( null !== $req->get_param( 'excerpt' ) ) { $patch['post_excerpt'] = (string) $req->get_param( 'excerpt' ); }
		if ( count( $patch ) > 1 ) { wp_update_post( $patch ); }
		if ( null !== $req->get_param( 'elementor_data' ) ) { $this->write_elementor( $pid, $req->get_param( 'elementor_data' ), $req->get_param( 'page_settings' ) ); }
		if ( $req->get_param( 'seo' ) ) { $this->seo_write( $pid, (array) $req->get_param( 'seo' ) ); }
		return $this->ok( array( 'updated' => true, 'page' => $this->page_summary( get_post( $pid ) ) ) );
	}

	public function delete_page( $req ) {
		$pid = (int) $req['id'];
		if ( ! get_post( $pid ) ) { return $this->err( 'not_found', 'Page not found.', 404 ); }
		$force = (bool) $req->get_param( 'force' );
		$res = wp_delete_post( $pid, $force );
		return $res ? $this->ok( array( 'deleted' => true, 'forced' => $force ) ) : $this->err( 'fail', 'Delete failed.' );
	}

	public function duplicate_page( $req ) {
		$src = (int) $req['id'];
		return $this->clone_post( $src, sanitize_text_field( (string) $req->get_param( 'title' ) ), $req->get_param( 'status' ) );
	}

	/** Create a new page copying layout from another page OR an Elementor template. */
	public function copy_from( $req ) {
		$src = (int) $req->get_param( 'source_id' );
		if ( ! $src || ! get_post( $src ) ) { return $this->err( 'bad', 'Valid source_id is required.' ); }
		return $this->clone_post( $src, sanitize_text_field( (string) $req->get_param( 'title' ) ), $req->get_param( 'status' ), 'page' );
	}

	private function clone_post( $src, $title, $status, $force_type = '' ) {
		$sp = get_post( $src );
		if ( ! $sp ) { return $this->err( 'not_found', 'Source not found.', 404 ); }
		$status = in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ? $status : 'draft';
		$pid = wp_insert_post( array(
			'post_title'   => $title ? $title : ( $sp->post_title . ' (copy)' ),
			'post_status'  => $status,
			'post_type'    => $force_type ? $force_type : $sp->post_type,
			'post_content' => $sp->post_content,
			'post_excerpt' => $sp->post_excerpt,
		), true );
		if ( is_wp_error( $pid ) ) { return $pid; }
		// copy Elementor data + settings + build flags
		$data = get_post_meta( $src, self::ELEM_DATA, true );
		if ( $data ) {
			update_post_meta( $pid, self::ELEM_DATA, wp_slash( $data ) );
			update_post_meta( $pid, self::ELEM_MODE, 'builder' );
			$ps = get_post_meta( $src, self::ELEM_PS, true ); if ( $ps ) { update_post_meta( $pid, self::ELEM_PS, $ps ); }
			$ver = get_post_meta( $src, self::ELEM_VER, true ); update_post_meta( $pid, self::ELEM_VER, $ver ?: '3.0.0' );
			update_post_meta( $pid, '_wp_page_template', get_post_meta( $src, '_wp_page_template', true ) ?: 'default' );
		}
		self::flush_elementor_css( $pid );
		return $this->ok( array( 'created' => true, 'copied_from' => $src, 'page' => $this->page_summary( get_post( $pid ) ) ) );
	}

	private function write_elementor( $pid, $data, $page_settings = null ) {
		if ( is_string( $data ) ) { $decoded = json_decode( $data, true ); if ( is_array( $decoded ) ) { $data = $decoded; } }
		if ( ! is_array( $data ) ) { return; }
		update_post_meta( $pid, self::ELEM_DATA, wp_slash( wp_json_encode( $data ) ) );
		update_post_meta( $pid, self::ELEM_MODE, 'builder' );
		if ( ! get_post_meta( $pid, self::ELEM_VER, true ) ) { update_post_meta( $pid, self::ELEM_VER, '3.0.0' ); }
		if ( is_array( $page_settings ) ) { update_post_meta( $pid, self::ELEM_PS, $page_settings ); }
		self::flush_elementor_css( $pid );
	}

	/* ---------------- endpoints: content edit ---------------- */

	public function replace_text( $req ) {
		$pid = (int) $req['id'];
		$find = (string) $req->get_param( 'find' );
		$replace = (string) $req->get_param( 'replace' );
		if ( '' === $find ) { return $this->err( 'bad', 'find is required.' ); }
		$raw = get_post_meta( $pid, self::ELEM_DATA, true );
		if ( ! $raw ) { return $this->err( 'no_data', 'Page has no Elementor data.', 404 ); }
		$count = substr_count( $raw, $find );
		if ( $req->get_param( 'dry_run' ) ) { return $this->ok( array( 'dry_run' => true, 'matches' => $count ) ); }
		if ( 0 === $count ) { return $this->ok( array( 'replaced' => 0, 'note' => 'no matches' ) ); }
		$new = str_replace( $find, $replace, $raw );
		update_post_meta( $pid, self::ELEM_DATA, wp_slash( $new ) );
		self::flush_elementor_css( $pid );
		return $this->ok( array( 'replaced' => $count ) );
	}

	public function update_widget( $req ) {
		$pid = (int) $req['id'];
		$eid = sanitize_text_field( (string) $req->get_param( 'element_id' ) );
		$field = sanitize_key( (string) $req->get_param( 'field' ) );
		$value = $req->get_param( 'value' );
		if ( '' === $eid || '' === $field ) { return $this->err( 'bad', 'element_id and field are required.' ); }
		$data = $this->elementor_data( $pid );
		if ( ! $data ) { return $this->err( 'no_data', 'Page has no Elementor data.', 404 ); }
		$found = false;
		self::walk( $data, function ( &$el ) use ( $eid, $field, $value, &$found ) {
			if ( ! isset( $el['id'] ) || $el['id'] !== $eid ) { return; }
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { $el['settings'] = array(); }
			$el['settings'][ $field ] = is_string( $value ) ? wp_kses_post( $value ) : $value;
			$found = true;
		} );
		if ( ! $found ) { return $this->err( 'no_element', 'Element id not found on this page.', 404 ); }
		update_post_meta( $pid, self::ELEM_DATA, wp_slash( wp_json_encode( $data ) ) );
		self::flush_elementor_css( $pid );
		return $this->ok( array( 'updated' => true, 'element_id' => $eid, 'field' => $field ) );
	}

	/* ---------------- endpoints: Elementor global preset ---------------- */

	/** Read the active Elementor Kit's global fonts + colors (the site preset). */
	public static function kit_globals() {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		$out = array( 'kit_id' => $kit_id, 'heading_font' => '', 'body_font' => '', 'colors' => array(), 'has_globals' => false );
		if ( ! $kit_id ) { return $out; }
		$ps = get_post_meta( $kit_id, self::ELEM_PS, true );
		if ( ! is_array( $ps ) ) { return $out; }
		$typo = array_merge(
			isset( $ps['system_typography'] ) && is_array( $ps['system_typography'] ) ? $ps['system_typography'] : array(),
			isset( $ps['custom_typography'] ) && is_array( $ps['custom_typography'] ) ? $ps['custom_typography'] : array()
		);
		foreach ( $typo as $t ) {
			if ( ! isset( $t['_id'] ) || empty( $t['typography_font_family'] ) ) { continue; }
			if ( 'primary' === $t['_id'] ) { $out['heading_font'] = $t['typography_font_family']; }
			elseif ( 'secondary' === $t['_id'] && '' === $out['heading_font'] ) { $out['heading_font'] = $t['typography_font_family']; }
			elseif ( 'text' === $t['_id'] ) { $out['body_font'] = $t['typography_font_family']; }
			elseif ( 'accent' === $t['_id'] && '' === $out['body_font'] ) { $out['body_font'] = $t['typography_font_family']; }
		}
		$cols = array_merge(
			isset( $ps['system_colors'] ) && is_array( $ps['system_colors'] ) ? $ps['system_colors'] : array(),
			isset( $ps['custom_colors'] ) && is_array( $ps['custom_colors'] ) ? $ps['custom_colors'] : array()
		);
		foreach ( $cols as $c ) { if ( isset( $c['_id'], $c['color'] ) ) { $out['colors'][ $c['_id'] ] = $c['color']; } }
		$out['has_globals'] = ( '' !== $out['heading_font'] || '' !== $out['body_font'] || ! empty( $out['colors'] ) );
		return $out;
	}

	public function read_globals() {
		return $this->ok( self::kit_globals() );
	}

	/**
	 * Rebind every heading widget to the heading font and every body widget to the
	 * body font, so a page built from mixed sources matches one typographic preset.
	 * Existing sizes/weights/colors are preserved; only font-family is normalized.
	 */
	public static function apply_font_preset( &$data, $hf, $bf ) {
		$stats = array( 'remapped' => 0, 'injected' => 0 );
		$body_widgets = array( 'text-editor', 'button', 'icon-list', 'icon-box', 'testimonial', 'price-list', 'tabs', 'toggle', 'accordion', 'call-to-action', 'form' );
		self::walk( $data, function ( &$n ) use ( $hf, $bf, &$stats, $body_widgets ) {
			$wt = isset( $n['widgetType'] ) ? $n['widgetType'] : '';
			if ( '' === $wt ) { return; }
			if ( ! isset( $n['settings'] ) || ! is_array( $n['settings'] ) ) { $n['settings'] = array(); }
			$is_head = ( 'heading' === $wt );
			$is_body = in_array( $wt, $body_widgets, true );
			$had_font = false;
			foreach ( array_keys( $n['settings'] ) as $k ) {
				if ( preg_match( '/font_family$/i', $k ) ) {
					$had_font = true;
					$headingish = preg_match( '/title|head/i', $k );
					$val = $headingish ? $hf : ( $is_head ? $hf : $bf );
					if ( '' === $val ) { continue; }
					if ( ! isset( $n['settings'][ $k ] ) || $n['settings'][ $k ] !== $val ) { $n['settings'][ $k ] = $val; $stats['remapped']++; }
					$grp = preg_replace( '/_font_family$/i', '', $k );
					if ( ! isset( $n['settings'][ $grp . '_typography' ] ) || 'custom' !== $n['settings'][ $grp . '_typography' ] ) { $n['settings'][ $grp . '_typography' ] = 'custom'; }
				}
			}
			if ( ! $had_font && $is_head && '' !== $hf ) { $n['settings']['typography_typography'] = 'custom'; $n['settings']['typography_font_family'] = $hf; $stats['injected']++; }
			if ( ! $had_font && 'text-editor' === $wt && '' !== $bf ) { $n['settings']['typography_typography'] = 'custom'; $n['settings']['typography_font_family'] = $bf; $stats['injected']++; }
			// drop global typography bindings that would re-point the font away from the preset
			if ( isset( $n['settings']['__globals__'] ) && is_array( $n['settings']['__globals__'] ) ) {
				foreach ( array_keys( $n['settings']['__globals__'] ) as $gk ) {
					if ( preg_match( '/typograph/i', $gk ) ) { unset( $n['settings']['__globals__'][ $gk ] ); }
				}
			}
		} );
		return $stats;
	}

	/** Resolve preset fonts (explicit args win, else active kit) and apply to a page. */
	private function normalize_page_fonts( $pid, $heading_font = '', $body_font = '' ) {
		$g  = self::kit_globals();
		$hf = $heading_font ? sanitize_text_field( $heading_font ) : $g['heading_font'];
		$bf = $body_font ? sanitize_text_field( $body_font ) : $g['body_font'];
		if ( '' === $hf && '' === $bf ) { return array( 'skipped' => 'no_preset' ); }
		if ( '' === $hf ) { $hf = $bf; }
		if ( '' === $bf ) { $bf = $hf; }
		$data = $this->elementor_data( $pid );
		if ( ! $data ) { return array( 'skipped' => 'no_data' ); }
		$stats = self::apply_font_preset( $data, $hf, $bf );
		update_post_meta( $pid, self::ELEM_DATA, wp_slash( wp_json_encode( $data ) ) );
		self::flush_elementor_css( $pid );
		return array( 'heading_font' => $hf, 'body_font' => $bf, 'stats' => $stats );
	}

	public function apply_globals( $req ) {
		$pid = (int) $req['id'];
		if ( ! get_post( $pid ) ) { return $this->err( 'not_found', 'Page not found.', 404 ); }
		if ( ! $this->elementor_data( $pid ) ) { return $this->err( 'no_data', 'Page has no Elementor data.', 404 ); }
		$res = $this->normalize_page_fonts( $pid, (string) $req->get_param( 'heading_font' ), (string) $req->get_param( 'body_font' ) );
		if ( isset( $res['skipped'] ) ) {
			if ( 'no_preset' === $res['skipped'] ) { return $this->err( 'no_preset', 'No global fonts in the active kit; pass heading_font and body_font.', 400 ); }
			return $this->err( 'no_data', 'Page has no Elementor data.', 404 );
		}
		return $this->ok( array_merge( array( 'applied' => true ), $res ) );
	}

	/* ---------------- endpoints: SEO ---------------- */

	private function seo_read( $pid ) {
		$g = function ( $k ) use ( $pid ) { return (string) get_post_meta( $pid, $k, true ); };
		return array(
			'title'     => $g( '_rk_seo_title' ),
			'desc'      => $g( '_rk_seo_desc' ),
			'noindex'   => '1' === $g( '_rk_seo_noindex' ),
			'canonical' => $g( '_rk_seo_canonical' ),
			'og_image'  => $g( '_rk_seo_og_image' ),
			'focus_kw'  => $g( '_rk_seo_focus_kw' ),
		);
	}
	private function seo_write( $pid, $seo ) {
		$map = array( 'title' => '_rk_seo_title', 'desc' => '_rk_seo_desc', 'canonical' => '_rk_seo_canonical', 'og_image' => '_rk_seo_og_image', 'focus_kw' => '_rk_seo_focus_kw' );
		foreach ( $map as $k => $meta ) {
			if ( array_key_exists( $k, $seo ) ) {
				$v = $seo[ $k ];
				$v = ( 'canonical' === $k || 'og_image' === $k ) ? esc_url_raw( $v ) : sanitize_text_field( $v );
				update_post_meta( $pid, $meta, $v );
			}
		}
		if ( array_key_exists( 'noindex', $seo ) ) { update_post_meta( $pid, '_rk_seo_noindex', $seo['noindex'] ? '1' : '' ); }
		if ( class_exists( '\RK\SEO\Indexables' ) && method_exists( '\RK\SEO\Indexables', 'clear' ) ) { \RK\SEO\Indexables::clear(); }
	}
	public function get_seo( $req ) {
		$pid = (int) $req['id'];
		if ( ! get_post( $pid ) ) { return $this->err( 'not_found', 'Page not found.', 404 ); }
		return $this->ok( $this->seo_read( $pid ) );
	}
	public function set_seo( $req ) {
		$pid = (int) $req['id'];
		if ( ! get_post( $pid ) ) { return $this->err( 'not_found', 'Page not found.', 404 ); }
		$this->seo_write( $pid, (array) $req->get_json_params() ?: $req->get_params() );
		return $this->ok( array( 'updated' => true, 'seo' => $this->seo_read( $pid ) ) );
	}

	/* ---------------- endpoints: media / suite ---------------- */

	public function sideload_media( $req ) {
		$url = esc_url_raw( (string) $req->get_param( 'url' ) );
		if ( ! $url ) { return $this->err( 'bad', 'url is required.' ); }
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) { return $tmp; }
		$file = array( 'name' => basename( wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'image.jpg', 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) { @unlink( $tmp ); return $id; }
		$alt = sanitize_text_field( (string) $req->get_param( 'alt' ) );
		if ( $alt ) { update_post_meta( $id, '_wp_attachment_image_alt', $alt ); }
		return $this->ok( array( 'id' => (int) $id, 'url' => wp_get_attachment_url( $id ) ) );
	}

	public function list_modules() {
		if ( ! class_exists( 'RK_Suite_Modules' ) ) { return $this->err( 'no_suite', 'RK Suite not available.', 500 ); }
		$out = array();
		foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
			$out[] = array( 'slug' => $slug, 'name' => $def['name'], 'enabled' => RK_Suite_Modules::is_enabled( $slug ), 'tier' => $def['tier'] );
		}
		return $this->ok( array( 'modules' => $out ) );
	}
	public function toggle_module( $req ) {
		if ( ! class_exists( 'RK_Suite_Modules' ) ) { return $this->err( 'no_suite', 'RK Suite not available.', 500 ); }
		$slug = sanitize_key( (string) $req->get_param( 'slug' ) );
		if ( ! RK_Suite_Modules::get( $slug ) ) { return $this->err( 'bad', 'Unknown module slug.' ); }
		$on = (bool) $req->get_param( 'enabled' );
		RK_Suite_Modules::set_enabled( $slug, $on );
		return $this->ok( array( 'slug' => $slug, 'enabled' => RK_Suite_Modules::is_enabled( $slug ) ) );
	}

	public function site_health() {
		$out = array( 'site' => home_url( '/' ), 'wp' => get_bloginfo( 'version' ), 'php' => PHP_VERSION,
			'elementor' => class_exists( '\Elementor\Plugin' ), 'blog_public' => get_option( 'blog_public' ),
			'pages' => (int) wp_count_posts( 'page' )->publish, 'posts' => (int) wp_count_posts( 'post' )->publish );
		return $this->ok( $out );
	}
	public function site_scan() {
		if ( ! class_exists( 'RK_Migrate_Scanner' ) ) { return $this->err( 'no_scanner', 'RK Migrate not enabled.', 400 ); }
		$s = RK_Migrate_Scanner::scan();
		return $this->ok( array(
			'time' => $s['time'], 'pages_scanned' => $s['posts'],
			'total_built' => isset( $s['total_built'] ) ? $s['total_built'] : null,
			'hardcoded_colors' => $s['totals']['hardcoded_colors'], 'links' => $s['totals']['links'],
			'images_no_alt' => $s['totals']['images_no_alt'], 'pages_no_h1' => $s['totals']['pages_no_h1'],
		) );
	}

	public function get_appearance() {
		if ( ! class_exists( '\RK\SEO\Search_Appearance' ) ) { return $this->err( 'no_seo', 'RK SEO not enabled.', 400 ); }
		return $this->ok( \RK\SEO\Search_Appearance::all() );
	}
	public function set_appearance( $req ) {
		if ( ! class_exists( '\RK\SEO\Search_Appearance' ) ) { return $this->err( 'no_seo', 'RK SEO not enabled.', 400 ); }
		$vals = $req->get_json_params(); if ( ! is_array( $vals ) ) { return $this->err( 'bad', 'JSON body required.' ); }
		if ( isset( $vals['settings'] ) && is_array( $vals['settings'] ) ) { $vals = $vals['settings']; }
		\RK\SEO\Search_Appearance::update( $vals );
		do_action( 'rk_seo_appearance_saved' );
		return $this->ok( array( 'updated' => true ) );
	}

	public function list_redirects() {
		if ( ! class_exists( '\RK\SEO\Redirects' ) ) { return $this->err( 'no_seo', 'RK SEO not enabled.', 400 ); }
		global $wpdb; $t = \RK\SEO\Redirects::tables();
		$rows = $wpdb->get_results( "SELECT id, source, target, type, hits FROM {$t['redirects']} ORDER BY id DESC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL -- internal table.
		return $this->ok( array( 'redirects' => $rows ) );
	}
	public function add_redirect( $req ) {
		if ( ! class_exists( '\RK\SEO\Redirects' ) ) { return $this->err( 'no_seo', 'RK SEO not enabled.', 400 ); }
		$src = sanitize_text_field( (string) $req->get_param( 'source' ) );
		$dst = esc_url_raw( (string) $req->get_param( 'target' ) );
		$type = (int) ( $req->get_param( 'type' ) ?: 301 );
		if ( ! $src || ! $dst ) { return $this->err( 'bad', 'source and target are required.' ); }
		( new \RK\SEO\Redirects() )->add_rule( $src, $dst, $type );
		return $this->ok( array( 'added' => true ) );
	}
	public function del_redirect( $req ) {
		if ( ! class_exists( '\RK\SEO\Redirects' ) ) { return $this->err( 'no_seo', 'RK SEO not enabled.', 400 ); }
		( new \RK\SEO\Redirects() )->delete_rule( (int) $req['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	public function flush_css( $req ) {
		self::flush_elementor_css( (int) $req->get_param( 'page_id' ) );
		return $this->ok( array( 'flushed' => true ) );
	}
}
