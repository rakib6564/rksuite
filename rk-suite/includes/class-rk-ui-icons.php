<?php
/**
 * RK_UI_Icons — tiny inline-SVG icon set (premium 1.6px stroke, currentColor).
 * Use RK_UI_Icons::get( 'name' ) or ::e( 'name' ). No emoji, no icon fonts.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_UI_Icons {

	public static function map() {
		return array(
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-4.5"/>',
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'download' => '<path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
			'upload'   => '<path d="M12 21V9m0 0 4 4m-4-4-4 4M4 7V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2"/>',
			'trash'    => '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-9 0 1 12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-12"/>',
			'edit'     => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
			'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.3 1a7 7 0 0 0-1.7-1L14.5 3h-5l-.4 2.6a7 7 0 0 0-1.7 1l-2.3-1-2 3.4 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 1.7 1L9.5 21h5l.4-2.6a7 7 0 0 0 1.7-1l2.3 1 2-3.4-2-1.5a7 7 0 0 0 .1-1Z"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
			'image'    => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="1.6"/><path d="m3 17 5-4 4 3 3-2 6 5"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
			'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
			'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
			'sparkle'  => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
		);
	}

	public static function get( $name, $size = 18 ) {
		$m = self::map();
		$p = isset( $m[ $name ] ) ? $m[ $name ] : $m['check'];
		return '<span class="rk-ico" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg></span>';
	}
	public static function e( $name, $size = 18 ) { echo self::get( $name, $size ); }
}
