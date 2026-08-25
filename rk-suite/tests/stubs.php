<?php
/**
 * Minimal WordPress function stubs for the RK Suite unit tests.
 *
 * @package RK_Suite\Tests
 */

// In-memory options store used by the option stubs.
$GLOBALS['rk_test_options'] = array();

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return ( -1 === $component ) ? wp_parse_url_all( $url ) : parse_url( $url, $component );
	}
	function wp_parse_url_all( $url ) { return parse_url( $url ); }
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['rk_test_options'] ) ? $GLOBALS['rk_test_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['rk_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) { return 'rk-test-static-salt-' . $scheme; }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
