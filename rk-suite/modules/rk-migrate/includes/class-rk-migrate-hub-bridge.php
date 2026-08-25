<?php
/**
 * RK_Migrate_Hub_Bridge — registers RK Migrate with RK Hub.
 *
 * Implements the shared RK Hub Bridge contract (see docs/hub-bridge-spec.md).
 * RK Hub is a *soft* dependency: if the RK_Hub class is not present, RK Migrate
 * runs fully standalone. When RK Hub is active, this bridge:
 *   1. registers the product in the Hub catalog / update system, and
 *   2. lets the Hub's single license key drive this plugin's feature tier.
 *
 * No RK plugin ever requires another to activate — they detect each other via
 * class_exists() and unlock combined functionality when present together.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Hub_Bridge {

	const SLUG = 'rk-migrate';
	const NAME = 'RK Migrate';

	/** Hook registration late on plugins_loaded so RK Hub (if any) is loaded. */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Register with RK Hub when available. Safe no-op otherwise.
	 */
	public static function register() {
		if ( ! class_exists( 'RK_Hub' ) || ! is_callable( array( 'RK_Hub', 'register_product' ) ) ) {
			return; // standalone mode — nothing to do.
		}

		RK_Hub::register_product( array(
			'slug'    => self::SLUG,
			'name'    => self::NAME,
			'version' => defined( 'RK_MIGRATE_VERSION' ) ? RK_MIGRATE_VERSION : '',
			'file'    => defined( 'RK_MIGRATE_FILE' ) ? RK_MIGRATE_FILE : __FILE__,
			'tier'    => 'free',        // catalog tier; Pro/Agency features gate internally
			'depends' => array(),       // RK Migrate has no hard RK dependencies
		) );

		self::maybe_sync_tier();
	}

	/**
	 * If RK Hub exposes the account's license tier, mirror it into RK Migrate's
	 * settings so a single Hub key unlocks the matching feature set here.
	 * Guarded by method_exists so it stays compatible with any Hub API shape.
	 */
	private static function maybe_sync_tier() {
		if ( ! is_callable( array( 'RK_Hub', 'get_tier' ) ) ) {
			return;
		}
		if ( ! class_exists( 'RK_Migrate_Settings' ) ) {
			return;
		}
		$tier  = RK_Hub::get_tier();
		$valid = array( 'free', 'pro', 'agency' );
		if ( is_string( $tier ) && in_array( $tier, $valid, true ) ) {
			$settings = RK_Migrate_Settings::instance();
			if ( $settings->get( 'tier' ) !== $tier ) {
				$settings->set( 'tier', $tier );
			}
		}
	}
}
