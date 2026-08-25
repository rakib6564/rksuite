<?php
/**
 * RK_Elements_Hub_Bridge — registers RK Elements with RK Hub (soft dependency).
 * Declares a soft dependency on RK Core (dynamic widgets need it) but never
 * requires it to activate.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Elements_Hub_Bridge {

	const SLUG = 'rk-elements';
	const NAME = 'RK Elements';

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
			'version' => defined( 'RK_ELEMENTS_VERSION' ) ? RK_ELEMENTS_VERSION : '',
			'file'    => defined( 'RK_ELEMENTS_FILE' ) ? RK_ELEMENTS_FILE : __FILE__,
			'tier'    => 'pro',
			'depends' => array( 'rk-core' ),
		) );
	}
}
