<?php
/**
 * RK_Migrate_Doctor — applies fixes the scanner surfaces. Every apply takes a
 * rollback snapshot first (restore from History & Rollback).
 *
 *  - reclaim_color(): bind every hardcoded use of a hex to a global color
 *    (via Elementor's __globals__ mechanism) so global changes then propagate.
 *  - reclaim_font():  bind manual font-family uses to a global typography token.
 *  - convert_post():  convert legacy Section/Column trees to flex Containers.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Doctor {

	private static function save( $pid, $els ) {
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $els ) ) );
	}

	private static function flush() {
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		RK_Migrate_Scanner::clear_cache();
	}

	/* ---------------- color reclaim ---------------- */
	public static function reclaim_color( $target_hex, $global_id ) {
		$target = RK_Migrate_Scanner::norm_color( $target_hex );
		$global_id = sanitize_text_field( $global_id );
		$binding = 'globals/colors?id=' . $global_id;
		$affected = array();
		$report   = array();
		$count    = 0;

		// find affected posts first (for snapshot)
		foreach ( RK_Migrate_Scanner::post_ids() as $pid ) {
			$els = RK_Migrate_Scanner::data( $pid );
			if ( ! $els ) { continue; }
			$hit = false;
			RK_Migrate_Scanner::walk( $els, function ( &$el ) use ( $target, &$hit ) {
				$st = isset( $el['settings'] ) ? $el['settings'] : array();
				$g  = isset( $st['__globals__'] ) ? $st['__globals__'] : array();
				foreach ( $st as $k => $v ) {
					if ( '__' === substr( $k, 0, 2 ) ) { continue; }
					if ( RK_Migrate_Scanner::is_color( $v ) && ! isset( $g[ $k ] ) && RK_Migrate_Scanner::norm_color( $v ) === $target ) { $hit = true; }
				}
			} );
			if ( $hit ) { $affected[] = $pid; }
		}
		if ( ! $affected ) { return array( 'lines' => array( 'Nothing to change.' ), 'count' => 0, 'snapshot' => '' ); }

		$snapshot = RK_Migrate_History::instance()->snapshot( $affected, 'Color reclaim ' . $target . ' → ' . $global_id );

		foreach ( $affected as $pid ) {
			$els = RK_Migrate_Scanner::data( $pid );
			$changed = 0;
			RK_Migrate_Scanner::walk( $els, function ( &$el ) use ( $target, $binding, &$changed ) {
				if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { return; }
				$st = &$el['settings'];
				$g  = isset( $st['__globals__'] ) && is_array( $st['__globals__'] ) ? $st['__globals__'] : array();
				foreach ( $st as $k => $v ) {
					if ( '__' === substr( $k, 0, 2 ) ) { continue; }
					if ( RK_Migrate_Scanner::is_color( $v ) && ! isset( $g[ $k ] ) && RK_Migrate_Scanner::norm_color( $v ) === $target ) {
						$g[ $k ] = $binding;
						$changed++;
					}
				}
				if ( $changed ) { $st['__globals__'] = $g; }
			} );
			if ( $changed ) { self::save( $pid, $els ); $count += $changed; $report[] = 'Bound ' . $changed . ' control(s) on “' . get_the_title( $pid ) . '”'; }
		}
		self::flush();
		$report[] = 'Done — ' . $count . ' controls now use the global color.';
		return array( 'lines' => $report, 'count' => $count, 'snapshot' => $snapshot );
	}

	/* ---------------- font reclaim ---------------- */
	public static function reclaim_font( $family, $global_id ) {
		$binding = 'globals/typography?id=' . sanitize_text_field( $global_id );
		$affected = array(); $report = array(); $count = 0;

		foreach ( RK_Migrate_Scanner::post_ids() as $pid ) {
			$els = RK_Migrate_Scanner::data( $pid );
			if ( ! $els ) { continue; }
			$hit = false;
			RK_Migrate_Scanner::walk( $els, function ( &$el ) use ( $family, &$hit ) {
				$st = isset( $el['settings'] ) ? $el['settings'] : array();
				$g  = isset( $st['__globals__'] ) ? $st['__globals__'] : array();
				if ( isset( $st['typography_font_family'] ) && $st['typography_font_family'] === $family && ! isset( $g['typography_typography'] ) ) { $hit = true; }
			} );
			if ( $hit ) { $affected[] = $pid; }
		}
		if ( ! $affected ) { return array( 'lines' => array( 'Nothing to change.' ), 'count' => 0, 'snapshot' => '' ); }

		$snapshot = RK_Migrate_History::instance()->snapshot( $affected, 'Font reclaim ' . $family );
		foreach ( $affected as $pid ) {
			$els = RK_Migrate_Scanner::data( $pid );
			$changed = 0;
			RK_Migrate_Scanner::walk( $els, function ( &$el ) use ( $family, $binding, &$changed ) {
				if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { return; }
				$st = &$el['settings'];
				$g  = isset( $st['__globals__'] ) && is_array( $st['__globals__'] ) ? $st['__globals__'] : array();
				if ( isset( $st['typography_font_family'] ) && $st['typography_font_family'] === $family && ! isset( $g['typography_typography'] ) ) {
					$g['typography_typography'] = $binding;
					$st['__globals__'] = $g;
					$changed++;
				}
			} );
			if ( $changed ) { self::save( $pid, $els ); $count += $changed; }
		}
		self::flush();
		$report[] = 'Bound ' . $count . ' element(s) to the global font.';
		return array( 'lines' => $report, 'count' => $count, 'snapshot' => $snapshot );
	}

	/* ---------------- replace a color value everywhere (incl. nested) ---------------- */
	public static function replace_color_value( $from, $to ) {
		$from_n = RK_Migrate_Scanner::norm_color( $from );
		$to_n   = RK_Migrate_Scanner::norm_color( $to );
		if ( ! RK_Migrate_Scanner::is_color( $to_n ) ) { return array( 'lines' => array( 'Invalid target color.' ), 'count' => 0, 'snapshot' => '' ); }

		$variants = array( $from_n, strtoupper( $from_n ) );
		if ( preg_match( '/^#(.)\1(.)\2(.)\3$/', $from_n, $m ) ) {
			$short = '#' . $m[1] . $m[2] . $m[3];
			$variants[] = $short; $variants[] = strtoupper( $short );
		}
		$variants = array_values( array_unique( $variants ) );

		$affected = array();
		foreach ( RK_Migrate_Scanner::post_ids() as $pid ) {
			$raw = get_post_meta( $pid, '_elementor_data', true );
			$ps  = get_post_meta( $pid, '_elementor_page_settings', true );
			$in_css = is_array( $ps ) && ! empty( $ps['custom_css'] ) && false !== stripos( $ps['custom_css'], $from_n );
			if ( ( $raw && false !== stripos( $raw, $from_n ) ) || $in_css ) { $affected[] = $pid; }
		}
		if ( ! $affected ) { return array( 'lines' => array( 'No occurrences of ' . $from_n . ' found.' ), 'count' => 0, 'snapshot' => '' ); }

		$snapshot = RK_Migrate_History::instance()->snapshot( $affected, 'Color replace ' . $from_n . ' to ' . $to_n );
		$total = 0; $report = array();
		foreach ( $affected as $pid ) {
			$raw = get_post_meta( $pid, '_elementor_data', true );
			$cnt = 0;
			$raw = str_ireplace( $variants, $to_n, $raw, $cnt );
			if ( $cnt ) {
				update_post_meta( $pid, '_elementor_data', wp_slash( $raw ) );
				$total += $cnt;
			}
			$ps = get_post_meta( $pid, '_elementor_page_settings', true );
			if ( is_array( $ps ) && ! empty( $ps['custom_css'] ) ) {
				$c2 = 0;
				$ps['custom_css'] = str_ireplace( $variants, $to_n, $ps['custom_css'], $c2 );
				if ( $c2 ) { update_post_meta( $pid, '_elementor_page_settings', $ps ); $total += $c2; $cnt += $c2; }
			}
			if ( $cnt ) { $report[] = 'Replaced ' . $cnt . ' on ' . get_the_title( $pid ); }
		}
		self::flush();
		$report[] = 'Done - ' . $total . ' occurrence(s) changed to ' . $to_n . '.';
		return array( 'lines' => $report, 'count' => $total, 'snapshot' => $snapshot );
	}

	/* ---------------- normalise corner radius on elements that have one ---------------- */
	public static function set_all_radius( $px ) {
		$px = max( 0, (int) $px );
		$affected = array();
		foreach ( RK_Migrate_Scanner::post_ids() as $pid ) {
			$els = RK_Migrate_Scanner::data( $pid );
			if ( ! $els ) { continue; }
			$hit = false;
			RK_Migrate_Scanner::walk( $els, function ( &$el ) use ( &$hit ) {
				if ( empty( $el['settings'] ) || ! is_array( $el['settings'] ) ) { return; }
				foreach ( $el['settings'] as $k => $v ) { if ( false !== strpos( $k, 'border_radius' ) && is_array( $v ) ) { $hit = true; } }
			} );
			if ( $hit ) { $affected[] = $pid; }
		}
		if ( ! $affected ) { return array( 'lines' => array( 'No elements with a corner radius found.' ), 'count' => 0, 'snapshot' => '' ); }

		$snapshot = RK_Migrate_History::instance()->snapshot( $affected, 'Set corner radius ' . $px . 'px' );
		$total = 0;
		foreach ( $affected as $pid ) {
			$els = RK_Migrate_Scanner::data( $pid );
			$changed = 0;
			RK_Migrate_Scanner::walk( $els, function ( &$el ) use ( $px, &$changed ) {
				if ( empty( $el['settings'] ) || ! is_array( $el['settings'] ) ) { return; }
				foreach ( $el['settings'] as $k => $v ) {
					if ( false !== strpos( $k, 'border_radius' ) && is_array( $v ) ) {
						$el['settings'][ $k ] = array( 'unit' => 'px', 'top' => (string) $px, 'right' => (string) $px, 'bottom' => (string) $px, 'left' => (string) $px, 'isLinked' => true );
						$changed++;
					}
				}
			} );
			if ( $changed ) { self::save( $pid, $els ); $total += $changed; }
		}
		self::flush();
		return array( 'lines' => array( 'Set ' . $total . ' radius control(s) to ' . $px . 'px.' ), 'count' => $total, 'snapshot' => $snapshot );
	}

	/* ---------------- section → container ---------------- */
	public static function convert_post( $pid, $dry = false ) {
		$pid = (int) $pid;
		$els = RK_Migrate_Scanner::data( $pid );
		if ( ! $els ) { return array( 'lines' => array( 'No Elementor data.' ), 'sections' => 0, 'snapshot' => '' ); }

		$counter = array( 'sections' => 0, 'columns' => 0 );
		$converted = self::convert_tree( $els, $counter );

		if ( $dry ) {
			return array( 'lines' => array( $counter['sections'] . ' sections + ' . $counter['columns'] . ' columns would become containers.' ), 'sections' => $counter['sections'], 'snapshot' => '' );
		}
		if ( ! $counter['sections'] && ! $counter['columns'] ) {
			return array( 'lines' => array( 'No legacy sections found — already container-based.' ), 'sections' => 0, 'snapshot' => '' );
		}
		$snapshot = RK_Migrate_History::instance()->snapshot( array( $pid ), 'Container convert: ' . get_the_title( $pid ) );
		self::save( $pid, $converted );
		self::flush();
		return array(
			'lines'    => array( 'Converted ' . $counter['sections'] . ' sections + ' . $counter['columns'] . ' columns on “' . get_the_title( $pid ) . '”.', 'Review it in Elementor; rollback from History if needed.' ),
			'sections' => $counter['sections'],
			'snapshot' => $snapshot,
		);
	}

	private static function convert_tree( $els, &$counter ) {
		$out = array();
		foreach ( (array) $els as $el ) {
			$type = isset( $el['elType'] ) ? $el['elType'] : '';
			if ( 'section' === $type ) { $out[] = self::section_to_container( $el, $counter ); }
			elseif ( 'column' === $type ) { $out[] = self::column_to_container( $el, $counter ); }
			else {
				if ( ! empty( $el['elements'] ) ) { $el['elements'] = self::convert_tree( $el['elements'], $counter ); }
				$out[] = $el;
			}
		}
		return $out;
	}

	private static function section_to_container( $sec, &$counter ) {
		$counter['sections']++;
		$s = isset( $sec['settings'] ) && is_array( $sec['settings'] ) ? $sec['settings'] : array();
		$ns = array();
		$ns['content_width'] = ( isset( $s['layout'] ) && 'full_width' === $s['layout'] ) ? 'full' : 'boxed';
		$ns['flex_direction'] = 'row';
		$ns['flex_wrap'] = 'wrap';
		// Vertical alignment of the columns (legacy "content_position").
		$vmap = array( 'top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end', 'space-between' => 'space-between', 'space-around' => 'space-around', 'space-evenly' => 'space-evenly' );
		if ( isset( $s['content_position'] ) && isset( $vmap[ $s['content_position'] ] ) ) { $ns['flex_align_items'] = $vmap[ $s['content_position'] ]; }
		// NB: legacy column gutters came from column PADDING (copied below), not a
		// flex gap. Adding a px gap on top of %-widths forces wrap/stacking — the
		// classic broken-conversion symptom — so we deliberately don't set flex_gap.
		if ( isset( $s['height'] ) && 'min-height' === $s['height'] && isset( $s['custom_height'] ) ) { $ns['min_height'] = $s['custom_height']; }
		self::copy_style( $s, $ns );
		$children = array();
		foreach ( ( isset( $sec['elements'] ) ? $sec['elements'] : array() ) as $col ) {
			$children[] = self::column_to_container( $col, $counter );
		}
		return array( 'id' => isset( $sec['id'] ) ? $sec['id'] : self::nid(), 'elType' => 'container', 'settings' => $ns, 'elements' => $children, 'isInner' => isset( $sec['isInner'] ) ? $sec['isInner'] : false );
	}

	private static function column_to_container( $col, &$counter ) {
		$counter['columns']++;
		$s = isset( $col['settings'] ) && is_array( $col['settings'] ) ? $col['settings'] : array();
		$ns = array();
		$ns['content_width'] = 'full';
		$ns['flex_direction'] = 'column';
		$w = isset( $s['_inline_size'] ) && $s['_inline_size'] ? $s['_inline_size'] : ( isset( $s['_column_size'] ) ? $s['_column_size'] : null );
		if ( $w ) { $ns['width'] = array( 'unit' => '%', 'size' => floatval( $w ) ); }
		// Preserve responsive widths so tablet/mobile stacking matches the original.
		if ( ! empty( $s['_inline_size_tablet'] ) ) { $ns['width_tablet'] = array( 'unit' => '%', 'size' => floatval( $s['_inline_size_tablet'] ) ); }
		$ns['width_mobile'] = ! empty( $s['_inline_size_mobile'] )
			? array( 'unit' => '%', 'size' => floatval( $s['_inline_size_mobile'] ) )
			: array( 'unit' => '%', 'size' => 100 );
		// Vertical alignment of the column's widgets (column-direction container).
		$jmap = array( 'top' => 'flex-start', 'center' => 'center', 'middle' => 'center', 'bottom' => 'flex-end', 'space-between' => 'space-between', 'space-around' => 'space-around' );
		if ( isset( $s['content_position'] ) && isset( $jmap[ $s['content_position'] ] ) ) { $ns['flex_justify_content'] = $jmap[ $s['content_position'] ]; }
		self::copy_style( $s, $ns );
		$children = array();
		foreach ( ( isset( $col['elements'] ) ? $col['elements'] : array() ) as $child ) {
			$ct = isset( $child['elType'] ) ? $child['elType'] : '';
			if ( 'section' === $ct ) { $children[] = self::section_to_container( $child, $counter ); }
			elseif ( 'column' === $ct ) { $children[] = self::column_to_container( $child, $counter ); }
			else {
				if ( ! empty( $child['elements'] ) ) { $child['elements'] = self::convert_tree( $child['elements'], $counter ); }
				$children[] = $child;
			}
		}
		return array( 'id' => isset( $col['id'] ) ? $col['id'] : self::nid(), 'elType' => 'container', 'settings' => $ns, 'elements' => $children, 'isInner' => true );
	}

	/** Copy style controls that share names between section/column and container. */
	private static function copy_style( $from, &$to ) {
		$prefixes = array( 'background_', 'border_', 'box_shadow_', 'padding', 'margin', '_margin', '_padding', 'css_filters_', 'motion_fx', '_animation', 'animation', 'custom_css', 'z_index', '_element_id', '_css_classes', 'overflow', 'shape_divider_' );
		foreach ( $from as $k => $v ) {
			foreach ( $prefixes as $p ) {
				if ( 0 === strpos( $k, $p ) ) { $to[ $k ] = $v; break; }
			}
		}
		if ( isset( $from['__globals__'] ) ) { $to['__globals__'] = $from['__globals__']; }
		if ( isset( $from['__dynamic__'] ) ) { $to['__dynamic__'] = $from['__dynamic__']; }
	}

	private static function nid() { return substr( md5( uniqid( '', true ) ), 0, 7 ); }
}
