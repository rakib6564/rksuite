<?php
/**
 * RK_Migrate_Replace — token find & replace + URL rewrite engine.
 *
 * Operates on the raw JSON string of each page before it is written, so swaps
 * reach every widget, setting, and nested element. One template bundle becomes
 * infinite client variations.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Replace {

	/** @var array<string,string> literal find=>replace pairs */
	private $pairs = array();
	/** @var array<string,string> regex pattern=>replacement pairs */
	private $regex = array();

	/**
	 * @param array $rules [ ['find'=>'old','replace'=>'new','regex'=>false], ... ]
	 *                     or a flat map [ 'old' => 'new' ].
	 */
	public function __construct( $rules = array() ) {
		if ( $this->is_assoc( $rules ) ) {
			foreach ( $rules as $find => $replace ) { $this->add( $find, $replace ); }
		} else {
			foreach ( (array) $rules as $r ) {
				if ( ! isset( $r['find'] ) ) { continue; }
				$this->add( $r['find'], isset( $r['replace'] ) ? $r['replace'] : '', ! empty( $r['regex'] ) );
			}
		}
	}

	public function add( $find, $replace, $regex = false ) {
		if ( '' === $find ) { return; }
		if ( $regex ) { $this->regex[ $find ] = $replace; }
		else { $this->pairs[ $find ] = $replace; }
	}

	public function has_rules() {
		return ! empty( $this->pairs ) || ! empty( $this->regex );
	}

	/** Apply to a decoded JSON array, return a new decoded array. */
	public function apply_to_array( $data ) {
		if ( ! $this->has_rules() ) { return $data; }
		$json = wp_json_encode( $data );
		$json = $this->apply_to_string( $json );
		$decoded = json_decode( $json, true );
		return ( null === $decoded ) ? $data : $decoded;
	}

	/** Apply to a raw string (handles JSON-escaped slashes in URLs too). */
	public function apply_to_string( $string ) {
		foreach ( $this->pairs as $find => $replace ) {
			$string = str_replace( $find, $replace, $string );
			// also catch JSON-escaped forward slashes inside encoded URLs
			$ef = str_replace( '/', '\/', $find );
			$er = str_replace( '/', '\/', $replace );
			if ( $ef !== $find ) { $string = str_replace( $ef, $er, $string ); }
		}
		foreach ( $this->regex as $pattern => $replace ) {
			$safe = @preg_replace( $pattern, $replace, $string );
			if ( null !== $safe ) { $string = $safe; }
		}
		return $string;
	}

	/** Convenience factory: build URL-rewrite rules for staging -> production. */
	public static function url_rewrite( $from_url, $to_url ) {
		$from = untrailingslashit( $from_url );
		$to   = untrailingslashit( $to_url );
		$rules = array();
		if ( $from && $to && $from !== $to ) {
			$rules[ $from ] = $to;
			// protocol-relative + host-only variants
			$rules[ preg_replace( '#^https?:#', '', $from ) ] = preg_replace( '#^https?:#', '', $to );
		}
		return new self( $rules );
	}

	private function is_assoc( $arr ) {
		if ( ! is_array( $arr ) || array() === $arr ) { return false; }
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
