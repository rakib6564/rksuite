<?php
/**
 * RK Theme — RK Suite module. Standalone header/footer builder with its own
 * display-condition engine and self-contained import/export. Loaded on demand
 * by RK Suite when enabled.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists( 'RK_Theme' ) ) { return; }

define( 'RK_THEME_VERSION', '1.3.0' );
define( 'RK_THEME_FILE', __FILE__ );
define( 'RK_THEME_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_THEME_URL', plugin_dir_url( __FILE__ ) );

require_once RK_THEME_DIR . 'includes/class-rk-theme-store.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme-conditions.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme-render.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme-body.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme-visibility.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme-porter.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme-admin.php';
require_once RK_THEME_DIR . 'includes/class-rk-theme.php';

function rk_theme_boot() { RK_Theme::instance(); }
function rk_theme_activate() { RK_Theme::activate(); }
