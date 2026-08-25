<?php
/**
 * RK_API_Admin — settings screen: shows the API base URL + key for the MCP.
 *
 * @package RK_API
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_API_Admin {

	const SLUG = 'rk-api';
	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_rk_api_rotate', array( $this, 'rotate' ) );
	}

	public function menu() {
		add_menu_page( 'RK API', 'RK API', 'manage_options', self::SLUG, array( $this, 'screen' ), 'dashicons-rest-api', 64 );
	}

	public function rotate() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_api_rotate' );
		RK_API::rotate_key();
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'rk_msg' => 'rotated' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function screen() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$key  = RK_API::api_key();
		$base = rest_url( RK_API::NS );
		echo '<div class="wrap rk-api-wrap rk-has-rail">';
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); }
		echo '<main class="rk-main">';
		if ( isset( $_GET['rk_msg'] ) && 'rotated' === $_GET['rk_msg'] ) { echo '<div class="notice notice-success is-dismissible"><p>API key rotated.</p></div>'; }
		echo '<div class="rk-page-head"><h3>RK API — remote management</h3></div>';
		echo '<p class="rk-muted" style="max-width:680px;">Point the RK Suite MCP server (or any tool) at this API to create, duplicate, copy, edit and SEO-manage pages. Authenticate with a <strong>WordPress Application Password</strong> (Users → Profile) or the <strong>API key</strong> below.</p>';

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th>API base URL</th><td><code>' . esc_html( $base ) . '</code></td></tr>';
		echo '<tr><th>MCP endpoint (connect your client here)</th><td><code>' . esc_html( $base . '/mcp' ) . '</code></td></tr>';
		$masked = strlen( $key ) > 10 ? substr( $key, 0, 3 ) . str_repeat( '•', 12 ) . substr( $key, -4 ) : $key;
		echo '<tr><th>API key</th><td><code id="rk-api-key" data-full="' . esc_attr( $key ) . '" style="display:inline-block;max-width:520px;word-break:break-all;vertical-align:middle;">' . esc_html( $masked ) . '</code> ';
		echo '<button class="button" type="button" id="rk-api-key-reveal" aria-label="Reveal API key">Reveal</button> ';
		echo '<button class="button" type="button" aria-label="Copy API key" onclick="navigator.clipboard.writeText(document.getElementById(\'rk-api-key\').getAttribute(\'data-full\'))">Copy</button></td></tr>';
		echo '<script>(function(){var b=document.getElementById("rk-api-key-reveal"),c=document.getElementById("rk-api-key");if(!b||!c)return;var shown=false,full=c.getAttribute("data-full"),mask=c.textContent;b.addEventListener("click",function(){shown=!shown;c.textContent=shown?full:mask;b.textContent=shown?"Hide":"Reveal";});})();</script>';
		echo '<tr><th>Send as</th><td><code>X-RK-API-Key: &lt;key&gt;</code> header, or <code>?api_key=&lt;key&gt;</code></td></tr>';
		echo '</table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Rotate the key? Any tool using the old key must be updated.\');">';
		echo '<input type="hidden" name="action" value="rk_api_rotate" />';
		wp_nonce_field( 'rk_api_rotate' );
		echo '<p><button class="button">Rotate key</button></p></form>';

		echo '<h4>Available endpoints (namespace <code>rk/v1</code>)</h4>';
		echo '<pre class="rk-muted" style="background:#f6f7fb;padding:12px;border-radius:8px;max-width:680px;overflow:auto;">';
		echo esc_html( "GET  /ping\nGET  /pages            POST /pages\nGET  /pages/{id}       POST /pages/{id}      DELETE /pages/{id}\nPOST /pages/{id}/duplicate     POST /pages/copy-from\nPOST /pages/{id}/replace       POST /pages/{id}/widget\nGET/POST /pages/{id}/seo\nGET  /templates   GET /media   POST /media/sideload   GET /menus\nGET  /modules     POST /modules/toggle\nGET  /site/health GET /site/scan\nGET/POST /seo/search-appearance\nGET/POST /seo/redirects   DELETE /seo/redirects/{id}\nPOST /flush-css" );
		echo '</pre>';
		echo '</main></div>';
	}
}
