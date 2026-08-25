<?php
/**
 * RK Library — RK Suite module. A branded, categorized template library inside
 * the Elementor editor: upload design bundles on the backend, then pick and
 * insert them from a custom modal on the front like Elementor's own library.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists( 'RK_Library' ) ) { return; }

define( 'RK_LIBRARY_VERSION', '1.0.2' );
define( 'RK_LIBRARY_FILE', __FILE__ );
define( 'RK_LIBRARY_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_LIBRARY_URL', plugin_dir_url( __FILE__ ) );

require_once RK_LIBRARY_DIR . 'includes/class-rk-library-store.php';
require_once RK_LIBRARY_DIR . 'includes/class-rk-library-porter.php';
require_once RK_LIBRARY_DIR . 'includes/class-rk-library-source.php';
require_once RK_LIBRARY_DIR . 'includes/class-rk-library-editor.php';
require_once RK_LIBRARY_DIR . 'includes/class-rk-library-admin.php';
require_once RK_LIBRARY_DIR . 'includes/class-rk-library.php';

function rk_library_boot() { RK_Library::instance(); }
function rk_library_activate() { RK_Library::activate(); }
