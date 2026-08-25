<?php
/**
 * RK Visual Edit — RK Suite module.
 *
 * Advanced on-page (front-end) visual editing: click text to edit in place with
 * a rich-text toolbar, edit marked regions inside HTML widgets, swap images and
 * edit link URLs, all with per-edit undo/history — writing straight back into
 * the correct post's Elementor data. Access is role-gated and every advanced
 * capability can be toggled from the module's settings.
 *
 * @package RK_Visual
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists( 'RK_Visual' ) ) { return; }

define( 'RK_VISUAL_VERSION', '1.0.0' );
define( 'RK_VISUAL_FILE', __FILE__ );
define( 'RK_VISUAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_VISUAL_URL', plugin_dir_url( __FILE__ ) );

require_once RK_VISUAL_DIR . 'includes/class-rk-visual-settings.php';
require_once RK_VISUAL_DIR . 'includes/class-rk-visual-editor.php';
require_once RK_VISUAL_DIR . 'includes/class-rk-visual-admin.php';
require_once RK_VISUAL_DIR . 'includes/class-rk-visual.php';

function rk_visual_boot() { RK_Visual::instance(); }
function rk_visual_activate() {
	if ( class_exists( 'RK_Visual_Settings' ) ) { RK_Visual_Settings::ensure_defaults(); }
}
