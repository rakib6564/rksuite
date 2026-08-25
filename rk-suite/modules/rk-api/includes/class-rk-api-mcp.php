<?php
/**
 * RK_API_MCP — native MCP server over HTTP. RK Suite *is* the MCP server: clients
 * POST JSON-RPC 2.0 to /wp-json/rk/v1/mcp (initialize, tools/list, tools/call).
 * Each tool dispatches to the existing rk/v1 routes via rest_do_request() — no
 * duplicated logic. Stateless. Same auth as RK API (App Password or API key).
 *
 * @package RK_API
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_API_MCP {

	const PROTO = '2025-06-18';

	public function hooks() { add_action( 'rest_api_init', array( $this, 'route' ) ); }

	public function route() {
		register_rest_route( RK_API::NS, '/mcp', array(
			array( 'methods' => 'POST', 'callback' => array( $this, 'handle' ), 'permission_callback' => array( RK_API::instance(), 'auth' ) ),
			array( 'methods' => 'GET', 'callback' => array( $this, 'hello' ), 'permission_callback' => array( RK_API::instance(), 'auth' ) ),
		) );
	}

	public function hello() {
		return new \WP_REST_Response( array( 'mcp' => true, 'transport' => 'streamable-http', 'note' => 'POST JSON-RPC here (initialize, tools/list, tools/call).' ), 200 );
	}

	public function handle( \WP_REST_Request $req ) {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) { return new \WP_REST_Response( $this->rpc_error( null, -32700, 'Parse error' ), 200 ); }
		if ( isset( $body[0] ) ) {
			$out = array();
			foreach ( $body as $msg ) { $r = $this->dispatch( $msg ); if ( null !== $r ) { $out[] = $r; } }
			return new \WP_REST_Response( $out, 200 );
		}
		$r = $this->dispatch( $body );
		if ( null === $r ) { return new \WP_REST_Response( null, 202 ); }
		return new \WP_REST_Response( $r, 200 );
	}

	private function dispatch( $msg ) {
		$id     = isset( $msg['id'] ) ? $msg['id'] : null;
		$method = isset( $msg['method'] ) ? (string) $msg['method'] : '';
		$params = isset( $msg['params'] ) && is_array( $msg['params'] ) ? $msg['params'] : array();
		if ( 0 === strpos( $method, 'notifications/' ) ) { return null; }
		switch ( $method ) {
			case 'initialize':
				return $this->rpc_result( $id, array(
					'protocolVersion' => self::PROTO,
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array( 'name' => 'wordpress_rk', 'version' => defined( 'RK_API_VERSION' ) ? RK_API_VERSION : '1.0.0' ),
				) );
			case 'ping':
				return $this->rpc_result( $id, new \stdClass() );
			case 'tools/list':
				return $this->rpc_result( $id, array( 'tools' => array_values( self::catalog() ) ) );
			case 'tools/call':
				return $this->call_tool( $id, $params );
		}
		return $this->rpc_error( $id, -32601, 'Method not found: ' . $method );
	}

	private function call_tool( $id, $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
		$map      = self::routes();
		$handlers = self::handlers();
		if ( ! isset( $map[ $name ], $handlers[ $name ] ) ) { return $this->rpc_error( $id, -32602, 'Unknown tool: ' . $name ); }
		$spec   = $map[ $name ];
		$method = $handlers[ $name ];
		$api    = RK_API::instance();
		if ( ! method_exists( $api, $method ) ) { return $this->rpc_error( $id, -32603, 'Handler missing: ' . $method ); }
		// The /mcp request is already authenticated — call the handler directly
		// (no inner permission re-check). Feed args as both params and JSON body.
		$request = new \WP_REST_Request( $spec['method'], '/' . RK_API::NS . $spec['path'] );
		foreach ( $args as $k => $v ) { $request->set_param( $k, $v ); }
		if ( 'POST' === $spec['method'] ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $args ) );
		}
		$resp = call_user_func( array( $api, $method ), $request );
		if ( is_wp_error( $resp ) ) {
			$data = array( 'code' => $resp->get_error_code(), 'message' => $resp->get_error_message() );
			$is_err = true;
		} else {
			$data = ( $resp instanceof \WP_REST_Response ) ? $resp->get_data() : $resp;
			$is_err = is_array( $data ) && isset( $data['code'], $data['message'] );
		}
		return $this->rpc_result( $id, array(
			'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $data ) ) ),
			'isError' => (bool) $is_err,
		) );
	}

	/** tool name => RK_API handler method. */
	public static function handlers() {
		return array(
			'wp_ping' => 'ping', 'wp_list_pages' => 'list_pages', 'wp_get_page' => 'get_page',
			'wp_create_page' => 'create_page', 'wp_update_page' => 'update_page', 'wp_delete_page' => 'delete_page',
			'wp_duplicate_page' => 'duplicate_page', 'wp_copy_from_template' => 'copy_from',
			'wp_replace_text' => 'replace_text', 'wp_update_widget' => 'update_widget',
			'wp_get_globals' => 'read_globals', 'wp_apply_globals' => 'apply_globals',
			'wp_get_seo' => 'get_seo', 'wp_set_seo' => 'set_seo', 'wp_list_templates' => 'list_templates',
			'wp_list_media' => 'list_media', 'wp_sideload_media' => 'sideload_media', 'wp_list_menus' => 'list_menus',
			'wp_list_modules' => 'list_modules', 'wp_toggle_module' => 'toggle_module',
			'wp_site_health' => 'site_health', 'wp_site_scan' => 'site_scan',
			'wp_get_search_appearance' => 'get_appearance', 'wp_set_search_appearance' => 'set_appearance',
			'wp_list_redirects' => 'list_redirects', 'wp_add_redirect' => 'add_redirect', 'wp_delete_redirect' => 'del_redirect',
			'wp_flush_css' => 'flush_css',
		);
	}

	private function rpc_result( $id, $result ) { return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ); }
	private function rpc_error( $id, $code, $message ) { return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) ); }

	private static function obj( $props, $required = array() ) { return array( 'type' => 'object', 'properties' => (object) $props, 'required' => $required, 'additionalProperties' => true ); }
	private static function s( $d ) { return array( 'type' => 'string', 'description' => $d ); }
	private static function i( $d ) { return array( 'type' => 'integer', 'description' => $d ); }
	private static function b( $d ) { return array( 'type' => 'boolean', 'description' => $d ); }

	public static function catalog() {
		$c = array();
		$add = function ( $name, $desc, $schema ) use ( &$c ) { $c[ $name ] = array( 'name' => $name, 'description' => $desc, 'inputSchema' => $schema ); };
		$add( 'wp_ping', 'Check connectivity + auth; returns site, versions and Elementor status.', self::obj( array() ) );
		$add( 'wp_list_pages', 'List pages/posts (paged, searchable). elementor_only finds Elementor-built pages.', self::obj( array( 'search' => self::s( 'Search term' ), 'post_type' => self::s( "default 'page'" ), 'elementor_only' => self::b( 'Only Elementor-built' ), 'per_page' => self::i( '1-100' ), 'page' => self::i( 'Page number' ) ) ) );
		$add( 'wp_get_page', 'Get one page in full: content, decoded Elementor tree (element ids), page settings, SEO.', self::obj( array( 'id' => self::i( 'Page ID' ) ), array( 'id' ) ) );
		$add( 'wp_create_page', 'Create a page/post. Optionally seed Elementor layout + SEO. Defaults to draft.', self::obj( array( 'title' => self::s( 'Title' ), 'status' => self::s( 'publish|draft|private|pending' ), 'post_type' => self::s( 'Post type' ), 'content' => self::s( 'HTML content' ), 'excerpt' => self::s( 'Excerpt' ), 'elementor_data' => array( 'type' => 'array', 'description' => 'Elementor tree' ), 'seo' => array( 'type' => 'object', 'description' => 'SEO fields' ), 'normalize_globals' => self::b( 'Rebind fonts to the site global preset after seeding layout' ), 'heading_font' => self::s( 'Heading font override for normalize_globals' ), 'body_font' => self::s( 'Body font override for normalize_globals' ) ), array( 'title' ) ) );
		$add( 'wp_update_page', 'Update title/status/content/excerpt, replace Elementor tree, and/or set SEO. Only provided fields change.', self::obj( array( 'id' => self::i( 'Page ID' ), 'title' => self::s( '' ), 'status' => self::s( '' ), 'content' => self::s( '' ), 'excerpt' => self::s( '' ), 'elementor_data' => array( 'type' => 'array' ), 'seo' => array( 'type' => 'object' ) ), array( 'id' ) ) );
		$add( 'wp_delete_page', 'Trash a page (force=true to permanently delete).', self::obj( array( 'id' => self::i( 'Page ID' ), 'force' => self::b( 'Permanent delete' ) ), array( 'id' ) ) );
		$add( 'wp_duplicate_page', 'Duplicate a page including its Elementor layout + settings. Draft by default.', self::obj( array( 'id' => self::i( 'Source page ID' ), 'title' => self::s( 'Copy title' ), 'status' => self::s( '' ) ), array( 'id' ) ) );
		$add( 'wp_copy_from_template', 'Create a NEW page copying the layout from another page OR an Elementor template.', self::obj( array( 'source_id' => self::i( 'Page/template ID' ), 'title' => self::s( 'New title' ), 'status' => self::s( '' ) ), array( 'source_id', 'title' ) ) );
		$add( 'wp_replace_text', 'Find/replace exact text in a page Elementor data. Use dry_run=true first.', self::obj( array( 'id' => self::i( 'Page ID' ), 'find' => self::s( 'Text to find' ), 'replace' => self::s( 'Replacement' ), 'dry_run' => self::b( 'Preview only' ) ), array( 'id', 'find' ) ) );
		$add( 'wp_update_widget', 'Set one widget setting by Elementor element_id (from wp_get_page).', self::obj( array( 'id' => self::i( 'Page ID' ), 'element_id' => self::s( 'Element id' ), 'field' => self::s( 'Setting key' ), 'value' => array( 'description' => 'New value' ) ), array( 'id', 'element_id', 'field', 'value' ) ) );
		$add( 'wp_get_globals', "Read the active Elementor Kit's global fonts and colors (the site's typography/color preset).", self::obj( array() ) );
		$add( 'wp_apply_globals', 'Normalize a page to the site preset: rebind every heading to the heading font and every body/text widget to the body font (sizes/colors preserved). Uses the active kit unless heading_font/body_font are given. Use after mixing sections from different pages.', self::obj( array( 'id' => self::i( 'Page ID' ), 'heading_font' => self::s( 'Override heading font family' ), 'body_font' => self::s( 'Override body font family' ) ), array( 'id' ) ) );
		$add( 'wp_get_seo', 'Read a page SEO overrides.', self::obj( array( 'id' => self::i( 'Page ID' ) ), array( 'id' ) ) );
		$add( 'wp_set_seo', 'Set a page SEO (title, desc, noindex, canonical, og_image, focus_kw).', self::obj( array( 'id' => self::i( 'Page ID' ), 'title' => self::s( '' ), 'desc' => self::s( '' ), 'noindex' => self::b( '' ), 'canonical' => self::s( '' ), 'og_image' => self::s( '' ), 'focus_kw' => self::s( '' ) ), array( 'id' ) ) );
		$add( 'wp_list_templates', 'List Elementor library templates (id, title, type).', self::obj( array() ) );
		$add( 'wp_list_media', 'List image attachments.', self::obj( array( 'search' => self::s( '' ), 'per_page' => self::i( '' ) ) ) );
		$add( 'wp_sideload_media', 'Download an external image into the media library.', self::obj( array( 'url' => self::s( 'Image URL' ), 'alt' => self::s( 'Alt text' ) ), array( 'url' ) ) );
		$add( 'wp_list_menus', 'List nav menus + items.', self::obj( array() ) );
		$add( 'wp_list_modules', 'List RK Suite modules and enabled state.', self::obj( array() ) );
		$add( 'wp_toggle_module', 'Enable/disable an RK module by slug.', self::obj( array( 'slug' => self::s( 'Module slug' ), 'enabled' => self::b( 'On/off' ) ), array( 'slug', 'enabled' ) ) );
		$add( 'wp_site_health', 'WP/PHP versions, Elementor active, page/post counts.', self::obj( array() ) );
		$add( 'wp_site_scan', 'RK Migrate Site Doctor scan summary.', self::obj( array() ) );
		$add( 'wp_get_search_appearance', 'Get RK SEO per-type title/description templates.', self::obj( array() ) );
		$add( 'wp_set_search_appearance', 'Replace RK SEO search-appearance settings object.', self::obj( array( 'settings' => array( 'type' => 'object' ) ) ) );
		$add( 'wp_list_redirects', 'List RK SEO redirects.', self::obj( array() ) );
		$add( 'wp_add_redirect', 'Add a redirect (default 301).', self::obj( array( 'source' => self::s( '' ), 'target' => self::s( '' ), 'type' => self::i( '301|302' ) ), array( 'source', 'target' ) ) );
		$add( 'wp_delete_redirect', 'Delete a redirect by id.', self::obj( array( 'id' => self::i( 'Redirect ID' ) ), array( 'id' ) ) );
		$add( 'wp_flush_css', 'Flush Elementor generated CSS (site or one page_id).', self::obj( array( 'page_id' => self::i( 'Optional page id' ) ) ) );
		return $c;
	}

	public static function routes() {
		return array(
			'wp_ping'                  => array( 'method' => 'GET',    'path' => '/ping' ),
			'wp_list_pages'            => array( 'method' => 'GET',    'path' => '/pages' ),
			'wp_get_page'              => array( 'method' => 'GET',    'path' => '/pages/{id}' ),
			'wp_create_page'           => array( 'method' => 'POST',   'path' => '/pages' ),
			'wp_update_page'           => array( 'method' => 'POST',   'path' => '/pages/{id}' ),
			'wp_delete_page'           => array( 'method' => 'DELETE', 'path' => '/pages/{id}' ),
			'wp_duplicate_page'        => array( 'method' => 'POST',   'path' => '/pages/{id}/duplicate' ),
			'wp_copy_from_template'    => array( 'method' => 'POST',   'path' => '/pages/copy-from' ),
			'wp_replace_text'          => array( 'method' => 'POST',   'path' => '/pages/{id}/replace' ),
			'wp_update_widget'         => array( 'method' => 'POST',   'path' => '/pages/{id}/widget' ),
			'wp_get_globals'           => array( 'method' => 'GET',    'path' => '/globals' ),
			'wp_apply_globals'         => array( 'method' => 'POST',   'path' => '/pages/{id}/apply-globals' ),
			'wp_get_seo'               => array( 'method' => 'GET',    'path' => '/pages/{id}/seo' ),
			'wp_set_seo'               => array( 'method' => 'POST',   'path' => '/pages/{id}/seo' ),
			'wp_list_templates'        => array( 'method' => 'GET',    'path' => '/templates' ),
			'wp_list_media'            => array( 'method' => 'GET',    'path' => '/media' ),
			'wp_sideload_media'        => array( 'method' => 'POST',   'path' => '/media/sideload' ),
			'wp_list_menus'            => array( 'method' => 'GET',    'path' => '/menus' ),
			'wp_list_modules'          => array( 'method' => 'GET',    'path' => '/modules' ),
			'wp_toggle_module'         => array( 'method' => 'POST',   'path' => '/modules/toggle' ),
			'wp_site_health'           => array( 'method' => 'GET',    'path' => '/site/health' ),
			'wp_site_scan'             => array( 'method' => 'GET',    'path' => '/site/scan' ),
			'wp_get_search_appearance' => array( 'method' => 'GET',    'path' => '/seo/search-appearance' ),
			'wp_set_search_appearance' => array( 'method' => 'POST',   'path' => '/seo/search-appearance' ),
			'wp_list_redirects'        => array( 'method' => 'GET',    'path' => '/seo/redirects' ),
			'wp_add_redirect'          => array( 'method' => 'POST',   'path' => '/seo/redirects' ),
			'wp_delete_redirect'       => array( 'method' => 'DELETE', 'path' => '/seo/redirects/{id}' ),
			'wp_flush_css'             => array( 'method' => 'POST',   'path' => '/flush-css' ),
		);
	}
}
