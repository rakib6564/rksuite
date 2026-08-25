<?php
/**
 * RK_Core_Hub_Bridge — registers RK Core with RK Hub (soft dependency).
 * Standalone when the Hub is absent.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core_Hub_Bridge {

	const SLUG = 'rk-core';
	const NAME = 'RK Core';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register' ), 20 );
	}

	public static function register() {
		if ( ! class_exists( 'RK_Hub' ) || ! is_callable( array( 'RK_Hub', 'register_product' ) ) ) {
			return;
		}
		RK_Hub::register_product( array(
			'slug'    => self::SLUG,
			'name'    => self::NAME,
			'version' => defined( 'RK_CORE_VERSION' ) ? RK_CORE_VERSION : '',
			'file'    => defined( 'RK_CORE_FILE' ) ? RK_CORE_FILE : __FILE__,
			'tier'    => 'free',
			'depends' => array(),
		) );
	}
}
