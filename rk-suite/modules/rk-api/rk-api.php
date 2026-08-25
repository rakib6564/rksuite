<?php
/**
 * RK API — RK Suite module. A REST management API (namespace rk/v1) so external
 * tools (e.g. the RK Suite MCP server) can create, duplicate, copy, edit and
 * SEO-manage Elementor pages, plus media, menus, modules, redirects and site
 * health. Auth: a WordPress Application Password (authenticates as a real user,
 * capability-checked) OR an RK API key generated on the settings screen.
 *
 * @package RK_API
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'RK_API' ) ) { return; }

define( 'RK_API_VERSION', '1.0.0' );
define( 'RK_API_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_API_URL', plugin_dir_url( __FILE__ ) );

require_once RK_API_DIR . 'includes/class-rk-api.php';
require_once RK_API_DIR . 'includes/class-rk-api-mcp.php';
require_once RK_API_DIR . 'includes/class-rk-api-admin.php';

function rk_api_boot() {
	RK_API::instance();
	( new RK_API_MCP() )->hooks();
	if ( is_admin() ) { RK_API_Admin::instance(); }
}
function rk_api_activate() { RK_API::ensure_key(); }
