<?php
/**
 * RK Core — RK Suite module. Loaded on demand by RK Suite when
 * enabled; not a standalone plugin (no plugin header, no self-registration).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RK_CORE_VERSION', '1.3.0' );
define( 'RK_CORE_FILE', __FILE__ );
define( 'RK_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once RK_CORE_DIR . 'includes/class-rk-core-field-types.php';
require_once RK_CORE_DIR . 'includes/class-rk-cpt-builder.php';
require_once RK_CORE_DIR . 'includes/class-rk-taxonomy-builder.php';
require_once RK_CORE_DIR . 'includes/class-rk-field-engine.php';
require_once RK_CORE_DIR . 'includes/class-rk-query-builder.php';
require_once RK_CORE_DIR . 'includes/class-rk-relations.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-dynamic-tags.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-porter.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-jetengine.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-listings.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-listing-widget.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-admin.php';
require_once RK_CORE_DIR . 'includes/class-rk-core-hub-bridge.php';
require_once RK_CORE_DIR . 'includes/class-rk-core.php';

function rk_core_boot() {
	RK_Core::instance();
	RK_Core_Hub_Bridge::init();
}
