<?php
/**
 * RK Elements — RK Suite module. Loaded on demand by RK Suite when
 * enabled; not a standalone plugin (no plugin header, no self-registration).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RK_ELEMENTS_VERSION', '1.11.2' );
define( 'RK_ELEMENTS_FILE', __FILE__ );
define( 'RK_ELEMENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_ELEMENTS_URL', plugin_dir_url( __FILE__ ) );

require_once RK_ELEMENTS_DIR . 'includes/class-rk-elements.php';
require_once RK_ELEMENTS_DIR . 'includes/class-rk-elements-hub-bridge.php';

function rk_elements_boot() {
	RK_Elements::instance();
	RK_Elements_Hub_Bridge::init();
}
