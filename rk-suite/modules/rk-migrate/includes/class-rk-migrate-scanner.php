<?php
/**
 * RK_Migrate_Scanner — walks every page's _elementor_data and reports issues:
 * hardcoded colors (not bound to the global kit), manual fonts, links
 * (empty/internal/external), heading structure, images missing alt text, and
 * legacy Section/Column usage. Read-only; the fixer (RK_Migrate_Doctor) applies.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Scanner {

	/** All Elementor-built post IDs across pages, posts, public CPTs, templates. */
	/**
	 * Elementor-built post IDs to scan. Bounded by a filterable cap (newest
	 * first) so Site Doctor can never load an unbounded number of large
	 * _elementor_data blobs into memory in one admin request on big sites.
	 */
	public static function post_ids() {
		$ids  = array();
		$types = array_merge( array( 'page', 'post', 'elementor_library' ), array_values( get_post_types( array( 'public' => true, '_builtin' => false ), 'names' ) ) );
		$types = array_unique( $types );
		$cap = (int) apply_filters( 'rk_migrate_scan_max_posts', 400 );
		$q = get_posts( array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'numberposts'    => $cap > 0 ? $cap : -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_key'       => '_elementor_edit_mode',
			'meta_value'     => 'builder',
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		foreach ( $q as $id ) { $ids[] = (int) $id; }
		return $ids;
	}

	/** Total Elementor-built posts (for the "scanned N of M" notice). */
	public static function total_built() {
		global $wpdb;
		// Count only posts the scan actually considers — same types + statuses as
		// post_ids(). Excludes revisions / auto-drafts / internal posts that
		// inherit the builder meta, so the "scanned N of M" note is accurate.
		$types = array_merge( array( 'page', 'post', 'elementor_library' ), array_values( get_post_types( array( 'public' => true, '_builtin' => false ), 'names' ) ) );
		$types = array_unique( $types );
		$in = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 WHERE m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder'
			 AND p.post_type IN ($in) AND p.post_status IN ('publish','draft','private')" // phpcs:ignore WordPress.DB.PreparedSQL -- $in is esc_sql'd literals; no user input.
		);
	}

	public static function data( $pid ) {
		$raw = get_post_meta( $pid, '_elementor_data', true );
		if ( ! $raw ) { return array(); }
		$d = json_decode( $raw, true );
		return is_array( $d ) ? $d : array();
	}

	/** Recursive walk; $cb receives each element by reference (mutation-safe). */
	public static function walk( &$els, callable $cb ) {
		if ( ! is_array( $els ) ) { return; }
		foreach ( $els as &$el ) {
			if ( ! is_array( $el ) ) { continue; }
			$cb( $el );
			if ( ! empty( $el['elements'] ) ) { self::walk( $el['elements'], $cb ); }
		}
		unset( $el );
	}

	public static function is_color( $v ) {
		return is_string( $v ) && ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v ) || preg_match( '/^rgba?\(/i', $v ) );
	}

	public static function norm_color( $v ) {
		$v = strtolower( trim( $v ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $v, $m ) ) {
			$v = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return $v;
	}

	/**
	 * Human-clean a color string for display / as a sensible replace default.
	 * Rounds rgb()/rgba() channels to whole numbers (0-255) and alpha to two
	 * decimals, so values like "rgba(235.18088832736737, …, 0.84)" render as
	 * "rgba(235, 235, 235, 0.84)". Hex values pass through uppercased.
	 *
	 * @param string $v Raw color.
	 * @return string Cleaned color (or the original if not recognised).
	 */
	public static function pretty_color( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^#([0-9a-fA-F]{3,8})$/', $v ) ) {
			return strtoupper( $v );
		}
		if ( preg_match( '/^rgba?\(([^)]*)\)$/i', $v, $m ) ) {
			$parts = array_map( 'trim', explode( ',', $m[1] ) );
			if ( count( $parts ) >= 3 ) {
				$r = max( 0, min( 255, (int) round( (float) $parts[0] ) ) );
				$g = max( 0, min( 255, (int) round( (float) $parts[1] ) ) );
				$b = max( 0, min( 255, (int) round( (float) $parts[2] ) ) );
				if ( isset( $parts[3] ) && '' !== $parts[3] ) {
					$a = round( (float) $parts[3], 2 );
					// Trim trailing zeros: 1.00 -> 1, 0.80 -> 0.8.
					$a = rtrim( rtrim( number_format( $a, 2, '.', '' ), '0' ), '.' );
					return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $a . ')';
				}
				return 'rgb(' . $r . ', ' . $g . ', ' . $b . ')';
			}
		}
		return $v;
	}

	/**
	 * Recursively collect color values from a settings tree.
	 * Top-level scalar controls (not already bound to a global) are "bindable";
	 * colors nested inside groups/repeaters are value-only (replace, not bind).
	 */
	public static function collect_colors( $node, $top, $globals, &$out ) {
		if ( ! is_array( $node ) ) { return; }
		foreach ( $node as $k => $v ) {
			if ( $top && is_string( $k ) && '__' === substr( $k, 0, 2 ) ) { continue; }
			if ( is_string( $v ) ) {
				if ( self::is_color( $v ) ) {
					$bindable = ( $top && is_string( $k ) && ! isset( $globals[ $k ] ) );
					$out[] = array( 'norm' => self::norm_color( $v ), 'value' => $v, 'bindable' => $bindable, 'css' => false );
				} elseif ( is_string( $k ) && false !== strpos( $k, 'custom_css' ) && '' !== $v ) {
					foreach ( self::extract_hexes( $v ) as $hx ) {
						$out[] = array( 'norm' => self::norm_color( $hx ), 'value' => $hx, 'bindable' => false, 'css' => true );
					}
				}
			} elseif ( is_array( $v ) ) {
				self::collect_colors( $v, false, $globals, $out );
			}
		}
	}

	/** Pull hex color tokens out of a CSS string. */
	public static function extract_hexes( $css ) {
		$out = array();
		if ( preg_match_all( '/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', (string) $css, $m ) ) {
			foreach ( $m[0] as $h ) { $out[] = $h; }
		}
		return $out;
	}

	/** Human signature for a dimensions (radius/padding) control value. */
	public static function dim_sig( $v ) {
		if ( ! is_array( $v ) ) { return ''; }
		$unit = isset( $v['unit'] ) ? $v['unit'] : 'px';
		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $s ) {
			if ( isset( $v[ $s ] ) && '' !== $v[ $s ] ) { $sides[] = $v[ $s ]; }
		}
		if ( empty( $sides ) ) { return ''; }
		$uniq = array_unique( $sides );
		if ( 1 === count( $uniq ) ) { return $uniq[0] . $unit; }
		return implode( '/', $sides ) . $unit;
	}

	/** Global kit colors as bind targets: [ {id,title,color} ]. */
	public static function global_colors() {
		$out = array();
		$kit = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit ) { return $out; }
		$ps = get_post_meta( $kit, '_elementor_page_settings', true );
		if ( ! is_array( $ps ) ) { return $out; }
		foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
			if ( empty( $ps[ $bucket ] ) || ! is_array( $ps[ $bucket ] ) ) { continue; }
			foreach ( $ps[ $bucket ] as $c ) {
				if ( empty( $c['_id'] ) ) { continue; }
				$out[] = array( 'id' => $c['_id'], 'title' => isset( $c['title'] ) ? $c['title'] : $c['_id'], 'color' => isset( $c['color'] ) ? $c['color'] : '' );
			}
		}
		return $out;
	}

	public static function global_fonts() {
		$out = array();
		$kit = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit ) { return $out; }
		$ps = get_post_meta( $kit, '_elementor_page_settings', true );
		if ( ! is_array( $ps ) ) { return $out; }
		foreach ( array( 'system_typography', 'custom_typography' ) as $bucket ) {
			if ( empty( $ps[ $bucket ] ) || ! is_array( $ps[ $bucket ] ) ) { continue; }
			foreach ( $ps[ $bucket ] as $t ) {
				if ( empty( $t['_id'] ) ) { continue; }
				$out[] = array( 'id' => $t['_id'], 'title' => isset( $t['title'] ) ? $t['title'] : $t['_id'], 'font' => isset( $t['typography_font_family'] ) ? $t['typography_font_family'] : '' );
			}
		}
		return $out;
	}

	/** Cached full-site scan. */
	public static function scan( $force = false ) {
		$cached = $force ? false : get_transient( 'rk_migrate_scan' );
		if ( $cached ) { return $cached; }
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); } // phpcs:ignore -- bound the worst case.

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$report = array(
			'time'        => current_time( 'mysql' ),
			'posts'       => 0,
			'colors'      => array(),
			'fonts'       => array(),
			'links'       => array(),
			'images'      => array(),
			'headings'    => array(),
			'heading_tree' => array(),
			'seo'         => array(),
			'radius'      => array(),
			'sections'    => array( 'legacy' => 0, 'containers' => 0, 'pages_legacy' => 0 ),
			'totals'      => array( 'hardcoded_colors' => 0, 'links' => 0, 'links_empty' => 0, 'images_no_alt' => 0, 'pages_no_h1' => 0, 'pages_multi_h1' => 0 ),
		);

		$scan_ids = self::post_ids();
		$report['scanned_count'] = count( $scan_ids );
		$report['total_built']  = self::total_built();
		foreach ( $scan_ids as $pid ) {
			$els = self::data( $pid );
			if ( ! $els ) { continue; }
			$report['posts']++;
			$title = get_the_title( $pid );
			$edit  = get_edit_post_link( $pid, '' );
			$h1 = 0; $levels = array(); $has_legacy = false; $page_headings = array(); $page_words = 0;

			self::walk( $els, function ( &$el ) use ( &$report, $pid, $title, $edit, $home_host, &$h1, &$levels, &$has_legacy, &$page_headings, &$page_words ) {
				$type = isset( $el['elType'] ) ? $el['elType'] : '';
				if ( 'section' === $type || 'column' === $type ) { $report['sections']['legacy']++; if ( 'section' === $type ) { $has_legacy = true; } }
				if ( 'container' === $type ) { $report['sections']['containers']++; }

				$st = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
				$globals = isset( $st['__globals__'] ) && is_array( $st['__globals__'] ) ? $st['__globals__'] : array();
				$widget  = isset( $el['widgetType'] ) ? $el['widgetType'] : $type;

				// colors — deep scan (backgrounds, overlays, gradients, borders, box-shadow, repeater items)
				$found = array();
				self::collect_colors( $st, true, $globals, $found );
				foreach ( $found as $hit ) {
					$norm = $hit['norm'];
					if ( ! isset( $report['colors'][ $norm ] ) ) {
						$report['colors'][ $norm ] = array( 'value' => $hit['value'], 'count' => 0, 'bindable' => 0, 'css' => 0, 'pages' => array() );
					}
					$report['colors'][ $norm ]['count']++;
					if ( $hit['bindable'] ) { $report['colors'][ $norm ]['bindable']++; }
					if ( ! empty( $hit['css'] ) ) { $report['colors'][ $norm ]['css']++; }
					$report['colors'][ $norm ]['pages'][ $pid ] = $title;
					$report['totals']['hardcoded_colors']++;
				}

				// border-radius / corners inventory (top-level + responsive variants)
				foreach ( $st as $k => $v ) {
					if ( false === strpos( $k, 'border_radius' ) || ! is_array( $v ) ) { continue; }
					$sig = self::dim_sig( $v );
					if ( '' === $sig ) { continue; }
					if ( ! isset( $report['radius'][ $sig ] ) ) { $report['radius'][ $sig ] = array( 'count' => 0, 'pages' => array() ); }
					$report['radius'][ $sig ]['count']++;
					$report['radius'][ $sig ]['pages'][ $pid ] = $title;
				}

				// fonts
				if ( isset( $st['typography_font_family'] ) && is_string( $st['typography_font_family'] ) && '' !== $st['typography_font_family'] && ! isset( $globals['typography_typography'] ) ) {
					$fam = $st['typography_font_family'];
					if ( ! isset( $report['fonts'][ $fam ] ) ) { $report['fonts'][ $fam ] = array( 'count' => 0, 'pages' => array() ); }
					$report['fonts'][ $fam ]['count']++;
					$report['fonts'][ $fam ]['pages'][ $pid ] = $title;
				}

				// links / buttons
				$url = null;
				if ( isset( $st['link']['url'] ) ) { $url = $st['link']['url']; }
				elseif ( isset( $st['url']['url'] ) ) { $url = $st['url']['url']; }
				elseif ( in_array( $widget, array( 'button', 'call-to-action' ), true ) && isset( $st['link'] ) && is_string( $st['link'] ) ) { $url = $st['link']; }
				if ( null !== $url ) {
					$txt = isset( $st['text'] ) ? $st['text'] : ( isset( $st['title_text'] ) ? $st['title_text'] : '' );
					$kind = 'internal';
					if ( '' === trim( (string) $url ) || '#' === $url ) { $kind = 'empty'; $report['totals']['links_empty']++; }
					else {
						$h = wp_parse_url( $url, PHP_URL_HOST );
						if ( $h && $h !== $home_host ) { $kind = 'external'; }
					}
					if ( count( $report['links'] ) < 400 ) {
						$report['links'][] = array( 'pid' => $pid, 'eid' => isset( $el['id'] ) ? $el['id'] : '', 'title' => $title, 'widget' => $widget, 'url' => (string) $url, 'text' => wp_strip_all_tags( (string) $txt ), 'kind' => $kind, 'edit' => $edit );
					}
					$report['totals']['links']++;
				}

				// headings (static heading widget + dynamic page-title widget)
				$is_heading   = ( 'heading' === $widget );
				$is_pagetitle = ( 'theme-page-title' === $widget );
				if ( $is_heading || $is_pagetitle ) {
					$default_tag = $is_pagetitle ? 'h1' : 'h2';
					$tag = ( isset( $st['header_size'] ) && $st['header_size'] ) ? $st['header_size'] : $default_tag;
					if ( preg_match( '/^h([1-6])$/', $tag, $mm ) ) {
						$lvl = (int) $mm[1];
						if ( 1 === $lvl ) { $h1++; }
						$levels[] = $lvl;
						if ( count( $page_headings ) < 60 ) {
							$raw = $is_pagetitle ? get_the_title( $pid ) : ( isset( $st['title'] ) ? (string) $st['title'] : '' );
							$page_headings[] = array( 'level' => $lvl, 'text' => self::clean_heading_text( $raw ) );
						}
					}
				}

				// approximate word count from text-bearing widget settings
				foreach ( array( 'title', 'editor', 'text', 'description', 'sub_heading', 'title_text', 'description_text' ) as $tk ) {
					if ( isset( $st[ $tk ] ) && is_string( $st[ $tk ] ) && '' !== $st[ $tk ] ) {
						$page_words += str_word_count( wp_strip_all_tags( $st[ $tk ] ) );
					}
				}

				// images missing alt
				if ( 'image' === $widget && ! empty( $st['image']['id'] ) ) {
					$alt = get_post_meta( (int) $st['image']['id'], '_wp_attachment_image_alt', true );
					if ( '' === trim( (string) $alt ) && count( $report['images'] ) < 300 ) {
						$report['images'][] = array( 'pid' => $pid, 'title' => $title, 'img' => isset( $st['image']['url'] ) ? $st['image']['url'] : '', 'edit' => $edit );
						$report['totals']['images_no_alt']++;
					}
				}
			} );

			// page-level custom CSS (lives in page settings, not the data tree)
			$psettings = get_post_meta( $pid, '_elementor_page_settings', true );
			if ( is_array( $psettings ) && ! empty( $psettings['custom_css'] ) ) {
				foreach ( self::extract_hexes( $psettings['custom_css'] ) as $hx ) {
					$norm = self::norm_color( $hx );
					if ( ! isset( $report['colors'][ $norm ] ) ) { $report['colors'][ $norm ] = array( 'value' => $hx, 'count' => 0, 'bindable' => 0, 'css' => 0, 'pages' => array() ); }
					$report['colors'][ $norm ]['count']++;
					$report['colors'][ $norm ]['css']++;
					$report['colors'][ $norm ]['pages'][ $pid ] = $title;
					$report['totals']['hardcoded_colors']++;
				}
			}

			// heading structure per page
			$issues = array();
			if ( 0 === $h1 ) { $issues[] = 'No H1'; $report['totals']['pages_no_h1']++; }
			elseif ( $h1 > 1 ) { $issues[] = 'Multiple H1 (' . $h1 . ')'; $report['totals']['pages_multi_h1']++; }
			$prev = 0; $skip = false;
			foreach ( $levels as $lv ) { if ( $prev && $lv - $prev > 1 ) { $skip = true; } $prev = $lv; }
			if ( $skip ) { $issues[] = 'Heading level skipped'; }
			if ( $issues ) { $report['headings'][] = array( 'pid' => $pid, 'title' => $title, 'h1' => $h1, 'issues' => $issues, 'edit' => $edit ); }
			if ( ! empty( $page_headings ) ) { $report['heading_tree'][] = array( 'pid' => $pid, 'title' => $title, 'edit' => $edit, 'h1' => $h1, 'items' => $page_headings ); }

			// Per-page SEO signals (Yoast / Rank Math, else fallbacks).
			$mt = get_post_meta( $pid, '_yoast_wpseo_title', true );
			if ( '' === (string) $mt ) { $mt = get_post_meta( $pid, 'rank_math_title', true ); }
			$md = get_post_meta( $pid, '_yoast_wpseo_metadesc', true );
			if ( '' === (string) $md ) { $md = get_post_meta( $pid, 'rank_math_description', true ); }
			$can = get_post_meta( $pid, '_yoast_wpseo_canonical', true );
			if ( '' === (string) $can ) { $can = get_post_meta( $pid, 'rank_math_canonical_url', true ); }
			$noindex = ( '1' === (string) get_post_meta( $pid, '_yoast_wpseo_meta-robots-noindex', true ) );
			$rmrob = get_post_meta( $pid, 'rank_math_robots', true );
			if ( is_array( $rmrob ) && in_array( 'noindex', $rmrob, true ) ) { $noindex = true; }
			$og = get_post_meta( $pid, '_yoast_wpseo_opengraph-image', true );
			if ( '' === (string) $og ) { $og = get_post_meta( $pid, 'rank_math_facebook_image', true ); }

			$flags = array();
			if ( '' === trim( (string) $md ) ) { $flags[] = 'No description'; }
			if ( $noindex ) { $flags[] = 'Noindex'; }
			if ( 0 === $h1 ) { $flags[] = 'No H1'; }
			$dlen = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $md ) : strlen( (string) $md );
			if ( $dlen > 0 && $dlen < 80 ) { $flags[] = 'Desc short'; }
			if ( $dlen > 160 ) { $flags[] = 'Desc long'; }
			$eff_title = '' !== (string) $mt ? (string) $mt : $title;
			$tlen = function_exists( 'mb_strlen' ) ? mb_strlen( $eff_title ) : strlen( $eff_title );
			if ( $tlen > 60 ) { $flags[] = 'Title long'; }
			if ( '' === (string) $og ) { $flags[] = 'No OG image'; }

			$report['seo'][] = array(
				'pid' => $pid, 'title' => $title, 'slug' => get_post_field( 'post_name', $pid ), 'edit' => $edit,
				'meta_title' => (string) $mt, 'meta_desc' => (string) $md, 'canonical' => (string) $can,
				'noindex' => $noindex, 'og' => '' !== (string) $og, 'h1' => $h1, 'words' => (int) $page_words, 'flags' => $flags,
			);
			if ( $has_legacy ) { $report['sections']['pages_legacy']++; }
		}

		// finalize: sort colors by count desc
		uasort( $report['colors'], function ( $a, $b ) { return $b['count'] - $a['count']; } );
		uasort( $report['radius'], function ( $a, $b ) { return $b['count'] - $a['count']; } );

		set_transient( 'rk_migrate_scan', $report, 10 * MINUTE_IN_SECONDS );
		return $report;
	}

	/** Clean a heading string: drop icon tags/comments, strip HTML, trim, cap length. */
	public static function clean_heading_text( $raw ) {
		$raw = preg_replace( '/<i[^>]*>.*?<\/i>/is', '', (string) $raw );
		$raw = preg_replace( '/<!--.*?-->/s', '', (string) $raw );
		$txt = trim( wp_strip_all_tags( (string) $raw ) );
		$txt = html_entity_decode( $txt, ENT_QUOTES );
		$txt = preg_replace( '/\s+/', ' ', $txt );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $txt ) > 200 ) { $txt = mb_substr( $txt, 0, 200 ) . '…'; }
		return $txt;
	}

	public static function clear_cache() { delete_transient( 'rk_migrate_scan' ); }

	/** Pages that still use legacy Section/Column structure. */
	public static function legacy_pages() {
		$out = array();
		$scan_ids = self::post_ids();
		$report['scanned_count'] = count( $scan_ids );
		$report['total_built']  = self::total_built();
		foreach ( $scan_ids as $pid ) {
			$els = self::data( $pid );
			if ( ! $els ) { continue; }
			$n = 0;
			self::walk( $els, function ( &$el ) use ( &$n ) { if ( isset( $el['elType'] ) && 'section' === $el['elType'] ) { $n++; } } );
			if ( $n ) { $out[] = array( 'pid' => $pid, 'title' => get_the_title( $pid ), 'sections' => $n, 'edit' => get_edit_post_link( $pid, '' ) ); }
		}
		return $out;
	}

	/**
	 * Inspect a bundle directory and report which widget plugins it needs.
	 * Returns [ 'Label' => count ] e.g. [ 'Elementor Pro' => 12, 'Essential Addons' => 4 ].
	 */
	public static function bundle_dependencies( $base_dir ) {
		$prefix_map = array(
			'eael-'        => 'Essential Addons',
			'premium-'     => 'Premium Addons',
			'pp-'          => 'PowerPack Addons',
			'powerpack-'   => 'PowerPack Addons',
			'jet-'         => 'Crocoblock (JetElements/JetEngine)',
			'happy-'       => 'Happy Addons',
			'sina-'        => 'Sina Extension',
			'unlimited-'   => 'Unlimited Elements',
			'aae-'         => 'Anywhere Elementor',
			'mailchimp-'   => 'Elementor Pro',
		);
		$pro_widgets = array(
			'form', 'posts', 'portfolio', 'slides', 'nav-menu', 'animated-headline', 'price-list',
			'price-table', 'flip-box', 'call-to-action', 'share-buttons', 'blockquote', 'media-carousel',
			'testimonial-carousel', 'reviews', 'table-of-contents', 'countdown', 'lottie', 'code-highlight',
			'video-playlist', 'hotspot', 'search-form', 'loop-grid', 'loop-carousel', 'woocommerce-products',
			'wc-products', 'theme-post-content', 'theme-site-logo', 'theme-page-title',
		);
		$found = array();
		foreach ( glob( trailingslashit( $base_dir ) . '*.json' ) as $f ) {
			if ( basename( $f ) === 'manifest.json' ) { continue; }
			$data = json_decode( (string) file_get_contents( $f ), true );
			if ( ! is_array( $data ) ) { continue; }
			$content = isset( $data['content'] ) ? $data['content'] : $data;
			self::walk( $content, function ( &$el ) use ( &$found, $prefix_map, $pro_widgets ) {
				$w = isset( $el['widgetType'] ) ? $el['widgetType'] : '';
				if ( ! $w ) { return; }
				$label = null;
				foreach ( $prefix_map as $pre => $name ) {
					if ( 0 === strpos( $w, $pre ) ) { $label = $name; break; }
				}
				if ( ! $label && in_array( $w, $pro_widgets, true ) ) { $label = 'Elementor Pro'; }
				if ( ! $label && 0 === strpos( $w, 'woocommerce' ) ) { $label = 'WooCommerce'; }
				if ( $label ) { $found[ $label ] = ( isset( $found[ $label ] ) ? $found[ $label ] : 0 ) + 1; }
			} );
		}
		arsort( $found );
		return $found;
	}

	/** Collect unique non-empty URLs for the link checker. */
	public static function link_urls() {
		$scan = self::scan();
		$urls = array();
		foreach ( $scan['links'] as $l ) {
			$u = $l['url'];
			if ( '' === $u || '#' === $u || 0 === strpos( $u, '#' ) ) { continue; }
			if ( 0 === strpos( $u, 'mailto:' ) || 0 === strpos( $u, 'tel:' ) ) { continue; }
			if ( 0 === strpos( $u, '/' ) ) { $u = home_url( $u ); }
			$urls[ $u ] = true;
		}
		return array_keys( $urls );
	}
}
