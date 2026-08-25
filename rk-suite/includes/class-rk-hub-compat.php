<?php
/**
 * RK_Hub (compat shim) — inside the RK Suite bundle there is no separate RK Hub
 * connector plugin, so this lightweight class provides the same contract the
 * module hub-bridges expect: register_product() and get_tier(). It lets the
 * modules keep their standalone bridge code unchanged.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'RK_Hub' ) ) {
	class RK_Hub {

		private static $products = array();

		public static function register_product( $args ) {
			if ( empty( $args['slug'] ) ) { return; }
			self::$products[ $args['slug'] ] = $args;
		}

		public static function get_products() { return self::$products; }

		public static function is_registered( $slug ) { return isset( self::$products[ $slug ] ); }

		/**
		 * License tier for module bridges to mirror. Returns the tier ONLY when a
		 * license is actively set — otherwise null, so modules keep their own
		 * defaults instead of being silently downgraded to free before licensing.
		 */
		public static function get_tier() {
			if ( class_exists( 'RK_Suite' ) ) {
				$lic = RK_Suite::instance()->license;
				if ( $lic && $lic->is_active() ) { return $lic->tier(); }
			}
			return null;
		}
	}
}
