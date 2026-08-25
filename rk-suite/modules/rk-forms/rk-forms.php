<?php
/**
 * RK Forms — RK Suite module. Public-facing form builder: visual/DSL builder,
 * conditional logic, submissions inbox, email notifications, CSV export, and an
 * Elementor "RK Form" widget for dropping a form into any layout.
 * Ported from the standalone "Forms" app to WordPress. Loaded on demand.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'RK_Forms' ) ) { return; }

define( 'RK_FORMS_VERSION', '1.0.0' );
define( 'RK_FORMS_FILE', __FILE__ );
define( 'RK_FORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_FORMS_URL', plugin_dir_url( __FILE__ ) );

require_once RK_FORMS_DIR . 'includes/class-rk-forms-db.php';
require_once RK_FORMS_DIR . 'includes/class-rk-forms-fields.php';
require_once RK_FORMS_DIR . 'includes/class-rk-forms-public.php';
require_once RK_FORMS_DIR . 'includes/class-rk-forms-admin.php';
require_once RK_FORMS_DIR . 'includes/class-rk-forms-widget.php';
require_once RK_FORMS_DIR . 'includes/class-rk-forms.php';

function rk_forms_boot()     { RK_Forms::instance(); }
function rk_forms_activate() { RK_Forms_DB::install(); }
