<?php
/**
 * Plugin Name: RK Suite — Elementor Toolkit
 * Description: The complete RK toolkit in one plugin. Enable only the modules you need — RK Core (CPT/Fields engine), RK Migrate (Elementor site migration), RK Elements (widget library) — from the Modules screen. Everything ships together and updates together; disabled modules never load.
 * Version: 1.16.6
 * Author: Rakib Hasan
 * Author URI: https://rakibhasaan.com
 * Requires PHP: 7.0
 * License: GPL-2.0-or-later
 * Text Domain: rk-suite
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RK_SUITE_VERSION', '1.16.6' );
define( 'RK_SUITE_FILE', __FILE__ );
define( 'RK_SUITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_SUITE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Gated debug logger. Writes to the PHP error log only when WP_DEBUG is on, so
 * production sites stay quiet while developers still get RK diagnostics.
 *
 * @param string $msg Message (already prefixed by caller, e.g. "[RK Elements] …").
 * @return void
 */
if ( ! function_exists( 'rk_suite_log' ) ) {
	function rk_suite_log( $msg ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			error_log( $msg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG.
		}
	}
}

// Hub compat shim MUST load before any module so module bridges see RK_Hub.
require_once RK_SUITE_DIR . 'includes/class-rk-hub-compat.php';
require_once RK_SUITE_DIR . 'includes/class-rk-ui-icons.php';
require_once RK_SUITE_DIR . 'includes/class-rk-suite-modules.php';
require_once RK_SUITE_DIR . 'includes/class-rk-suite-license.php';
require_once RK_SUITE_DIR . 'includes/class-rk-suite-admin.php';
require_once RK_SUITE_DIR . 'includes/class-rk-suite.php';

add_action( 'plugins_loaded', array( 'RK_Suite', 'instance' ), 5 );
register_activation_hook( __FILE__, array( 'RK_Suite', 'activate' ) );
