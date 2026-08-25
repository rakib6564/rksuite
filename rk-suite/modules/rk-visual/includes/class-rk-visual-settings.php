<?php
/**
 * RK_Visual_Settings — persisted options for RK Visual Edit.
 *
 * @package RK_Visual
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Visual_Settings {

	const OPTION = 'rk_visual_settings';

	/** Capability choices exposed in the settings UI: value => label. */
	public static function cap_choices() {
		return array(
			'manage_options'         => 'Administrators only',
			'edit_pages'             => 'Editors and up',
			'edit_published_posts'   => 'Authors and up',
		);
	}

	/** Default settings. */
	public static function defaults() {
		return array(
			'enabled_front' => 1,             // master front-end switch (module can be on but feature paused).
			'cap'           => 'edit_pages',  // minimum capability to use the editor.
			'rich'          => 1,             // rich-text toolbar (B/I/U/link).
			'html_regions'  => 1,             // editable [data-rk-edit] regions inside HTML widgets.
			'history'       => 1,             // per-edit undo + history log.
			'media'         => 1,             // image swap + link URL editing.
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) { $saved = array(); }
		return array_merge( self::defaults(), $saved );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( $values ) {
		$d   = self::defaults();
		$out = array();
		$out['enabled_front'] = empty( $values['enabled_front'] ) ? 0 : 1;
		$out['rich']          = empty( $values['rich'] ) ? 0 : 1;
		$out['html_regions']  = empty( $values['html_regions'] ) ? 0 : 1;
		$out['history']       = empty( $values['history'] ) ? 0 : 1;
		$out['media']         = empty( $values['media'] ) ? 0 : 1;
		$cap = isset( $values['cap'] ) ? (string) $values['cap'] : $d['cap'];
		$out['cap'] = array_key_exists( $cap, self::cap_choices() ) ? $cap : $d['cap'];
		update_option( self::OPTION, $out );
		return $out;
	}

	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION, false ) ) {
			update_option( self::OPTION, self::defaults() );
		}
	}

	/** The capability a user needs to use the front-end editor. */
	public static function required_cap() {
		$cap = self::get( 'cap' );
		return $cap ? $cap : 'edit_pages';
	}

	/** Whether the current user may use the editor right now. */
	public static function user_can_edit() {
		return is_user_logged_in() && current_user_can( self::required_cap() );
	}
}
