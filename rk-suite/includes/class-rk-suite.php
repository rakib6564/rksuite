<?php
/**
 * RK_Suite — bundle bootstrap. Loads only the enabled modules, each inside a
 * try/catch so one module's runtime error can't take down the others.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Suite {

	private static $instance = null;
	private static $errors = array();

	/** @var RK_Suite_License */
	public $license;
	/** @var RK_Suite_Admin */
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();   // assigned BEFORE init() so re-entrant
			self::$instance->init();        // instance() calls never recurse.
		}
		return self::$instance;
	}

	private function __construct() {}

	private function init() {
		$this->license = new RK_Suite_License();
		$this->boot_modules();
		$this->admin = new RK_Suite_Admin( $this->license );
		$this->admin->hooks();
		$this->license->hooks();
	}

	private function boot_modules() {
		foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
			if ( ! RK_Suite_Modules::is_enabled( $slug ) ) { continue; }
			self::load_module( $slug, $def );
		}
	}

	/** Require a module's files and run its boot callable, isolated. */
	public static function load_module( $slug, $def ) {
		$main = RK_SUITE_DIR . 'modules/' . $def['dir'] . '/' . $def['main'];
		if ( ! file_exists( $main ) ) { self::$errors[ $slug ] = 'Module files are missing.'; return false; }
		// Guard against a duplicate: if the module's classes are already defined
		// (e.g. the separately-installed standalone plugin is still active),
		// skip loading to avoid a fatal "Cannot redeclare class" error.
		if ( ! empty( $def['class'] ) && class_exists( $def['class'] ) ) {
			self::$errors[ $slug ] = 'Also provided by a separately-installed "' . $slug . '" plugin. Deactivate that standalone plugin and keep only RK Suite.';
			return false;
		}
		try {
			require_once $main;
			if ( ! empty( $def['boot'] ) && is_callable( $def['boot'] ) ) { call_user_func( $def['boot'] ); }
			return true;
		} catch ( \Throwable $e ) {
			self::$errors[ $slug ] = $e->getMessage();
			rk_suite_log( '[RK Suite] module "' . $slug . '" failed to boot: ' . $e->getMessage() );
			return false;
		}
	}

	/** Enable-time: load the module files and run its activate routine. */
	public static function activate_module( $slug ) {
		$def = RK_Suite_Modules::get( $slug );
		if ( ! $def ) { return; }
		$main = RK_SUITE_DIR . 'modules/' . $def['dir'] . '/' . $def['main'];
		if ( ! file_exists( $main ) ) { return; }
		if ( ! empty( $def['class'] ) && class_exists( $def['class'] ) ) { return; }
		try {
			require_once $main;
			if ( ! empty( $def['activate'] ) && is_callable( $def['activate'] ) ) { call_user_func( $def['activate'] ); }
		} catch ( \Throwable $e ) {
			rk_suite_log( '[RK Suite] activating "' . $slug . '" failed: ' . $e->getMessage() );
		}
	}

	public static function errors() { return self::$errors; }

	public function get_tier() { return $this->license ? $this->license->tier() : 'free'; }

	/** Plugin activation: seed defaults + activate default-enabled modules. */
	public static function activate() {
		if ( null === get_option( RK_Suite_Modules::OPTION, null ) ) {
			update_option( RK_Suite_Modules::OPTION, RK_Suite_Modules::default_map() );
		}
		foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
			if ( RK_Suite_Modules::is_enabled( $slug ) ) { self::activate_module( $slug ); }
		}
		flush_rewrite_rules();
	}
}
