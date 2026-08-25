<?php
/**
 * RK_Visual_Editor — the server side of RK Visual Edit.
 *
 * Owns the widget→field target map, the AJAX save/undo/history endpoints, and
 * all the writes into the post's Elementor data. Every write is role-gated
 * (per settings) and per-post capability-checked. Four edit kinds are handled:
 * plain/rich text, HTML-widget regions (data-rk-edit), image swaps and link URLs.
 *
 * @package RK_Visual
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Visual_Editor {

	const NONCE       = 'rk_visual';
	const HISTORY_KEY = '_rk_visual_history';
	const HISTORY_MAX = 30;

	/**
	 * Widget css class => editable targets. Each target: { field, sel } for a
	 * top-level control, plus optional 'html'=>1 (save inner HTML), or a repeater
	 * target { field, sub, item_sel, sel }.
	 */
	public static function targets() {
		return array(
			'elementor-widget-heading'            => array( array( 'field' => 'title', 'sel' => '.elementor-heading-title' ) ),
			'elementor-widget-text-editor'        => array( array( 'field' => 'editor', 'sel' => '.elementor-text-editor, .elementor-widget-container', 'html' => 1 ) ),
			'elementor-widget-button'             => array(
				array( 'field' => 'text', 'sel' => '.elementor-button-text, a.elementor-button' ),
				array( 'field' => 'link', 'kind' => 'link', 'sel' => 'a.elementor-button' ),
			),
			'elementor-widget-image'              => array( array( 'field' => 'image', 'kind' => 'image', 'sel' => 'img' ) ),
			'elementor-widget-rk-heading'         => array( array( 'field' => 'title', 'sel' => '.rk-heading' ) ),
			'elementor-widget-rk-buttons'         => array( array( 'field' => 'text', 'sel' => '.rk-btn-txt' ) ),
			'elementor-widget-rk-download-button' => array( array( 'field' => 'text', 'sel' => '.rk-dl-btn span:last-child' ) ),
			'elementor-widget-icon-box'           => array(
				array( 'field' => 'title_text', 'sel' => '.elementor-icon-box-title' ),
				array( 'field' => 'description_text', 'sel' => '.elementor-icon-box-description', 'html' => 1 ),
			),
			'elementor-widget-image-box'          => array(
				array( 'field' => 'title_text', 'sel' => '.elementor-image-box-title' ),
				array( 'field' => 'description_text', 'sel' => '.elementor-image-box-description', 'html' => 1 ),
			),
			'elementor-widget-icon-list'          => array( array( 'field' => 'icon_list', 'sub' => 'text', 'item_sel' => '.elementor-icon-list-item', 'sel' => '.elementor-icon-list-text' ) ),
			'elementor-widget-divider'            => array( array( 'field' => 'text', 'sel' => '.elementor-divider__text' ) ),
			'elementor-widget-testimonial'        => array(
				array( 'field' => 'testimonial_content', 'sel' => '.elementor-testimonial-content', 'html' => 1 ),
				array( 'field' => 'testimonial_name', 'sel' => '.elementor-testimonial-name' ),
				array( 'field' => 'testimonial_job', 'sel' => '.elementor-testimonial-job' ),
			),
			'elementor-widget-call-to-action'     => array(
				array( 'field' => 'title', 'sel' => '.elementor-cta__title' ),
				array( 'field' => 'description', 'sel' => '.elementor-cta__description', 'html' => 1 ),
			),
			'elementor-widget-price-table'        => array(
				array( 'field' => 'heading', 'sel' => '.elementor-price-table__heading' ),
				array( 'field' => 'sub_heading', 'sel' => '.elementor-price-table__subheading' ),
			),
			// HTML widget — only the marked [data-rk-edit] regions are editable.
			'elementor-widget-html'               => array( array( 'field' => 'html', 'kind' => 'html_region', 'sel' => '[data-rk-edit]' ) ),
		);
	}

	/** Flat allow-list of every writable field key. */
	public static function allowed_fields() {
		$out = array();
		foreach ( self::targets() as $list ) {
			foreach ( $list as $t ) {
				$out[] = $t['field'];
				if ( isset( $t['sub'] ) ) { $out[] = $t['sub']; }
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function init() {
		add_action( 'wp_ajax_rk_visual_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_rk_visual_undo', array( __CLASS__, 'ajax_undo' ) );
		add_action( 'wp_ajax_rk_visual_history', array( __CLASS__, 'ajax_history' ) );
	}

	/** Shared guard: nonce + role + per-post cap. Returns post id or exits JSON. */
	private static function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Reload and try again.' ) );
		}
		if ( ! RK_Visual_Settings::user_can_edit() ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to edit on the front end.' ) );
		}
		$pid = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
			wp_send_json_error( array( 'message' => 'You cannot edit this content.' ) );
		}
		return $pid;
	}

	/** Recursive walker over an Elementor data tree, by reference. */
	public static function walk( &$nodes, $cb ) {
		if ( ! is_array( $nodes ) ) { return; }
		foreach ( $nodes as &$node ) {
			if ( is_array( $node ) ) {
				if ( isset( $node['id'] ) ) { call_user_func_array( $cb, array( &$node ) ); }
				if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) { self::walk( $node['elements'], $cb ); }
			}
		}
		unset( $node );
	}

	/** AJAX: save one edit. */
	public static function ajax_save() {
		$pid   = self::guard();
		$eid   = isset( $_POST['element_id'] ) ? sanitize_text_field( wp_unslash( $_POST['element_id'] ) ) : '';
		$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
		$sub   = isset( $_POST['sub'] ) && '' !== $_POST['sub'] ? sanitize_text_field( wp_unslash( $_POST['sub'] ) ) : '';
		$index = isset( $_POST['index'] ) && '' !== $_POST['index'] ? (int) $_POST['index'] : -1;
		$kind  = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : 'text';
		$raw_v = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

		$allowed = self::allowed_fields();
		if ( '' === $eid || '' === $field || ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Bad request.' ) );
		}
		// For repeater text edits the subfield must also be allow-listed (region
		// keys for html_region are intentionally exempt — they are not fields).
		if ( 'text' === $kind && '' !== $sub && ! in_array( sanitize_key( $sub ), $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Bad field.' ) );
		}

		// Feature gating from settings.
		if ( 'html_region' === $kind && ! RK_Visual_Settings::get( 'html_regions' ) ) { wp_send_json_error( array( 'message' => 'HTML-region editing is turned off.' ) ); }
		if ( in_array( $kind, array( 'image', 'link' ), true ) && ! RK_Visual_Settings::get( 'media' ) ) { wp_send_json_error( array( 'message' => 'Image/link editing is turned off.' ) ); }

		$data = self::load_data( $pid );
		if ( null === $data ) { wp_send_json_error( array( 'message' => 'No Elementor data on this post.' ) ); }

		$found = false;
		$old   = null;
		$region_key = ( 'html_region' === $kind ) ? preg_replace( '/[^a-z0-9_\-]/i', '', $sub ) : '';

		self::walk( $data, function ( &$el ) use ( $eid, $field, $sub, $index, $kind, $raw_v, $region_key, &$found, &$old ) {
			if ( ! isset( $el['id'] ) || $el['id'] !== $eid ) { return; }
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { $el['settings'] = array(); }
			$s = &$el['settings'];

			if ( 'html_region' === $kind ) {
				$html      = isset( $s['html'] ) ? (string) $s['html'] : '';
				$old       = $html;
				$s['html'] = self::replace_region( $html, $region_key, wp_kses_post( $raw_v ) );
				$found     = true;
				return;
			}
			if ( 'image' === $kind ) {
				$decoded = json_decode( $raw_v, true );
				$id      = ( is_array( $decoded ) && isset( $decoded['id'] ) ) ? (int) $decoded['id'] : 0;
				$url     = ( is_array( $decoded ) && isset( $decoded['url'] ) ) ? esc_url_raw( $decoded['url'] ) : '';
				if ( ! $url ) { return; }
				$old            = isset( $s['image'] ) ? $s['image'] : null;
				$s['image']     = array( 'id' => $id, 'url' => $url );
				$found          = true;
				return;
			}
			if ( 'link' === $kind ) {
				$url = esc_url_raw( trim( $raw_v ) );
				$old = isset( $s['link'] ) ? $s['link'] : null;
				if ( ! isset( $s['link'] ) || ! is_array( $s['link'] ) ) { $s['link'] = array( 'url' => '', 'is_external' => '', 'nofollow' => '' ); }
				$s['link']['url'] = $url;
				$found            = true;
				return;
			}

			// text / rich text.
			$value = wp_kses_post( $raw_v );
			if ( '' !== $sub && $index >= 0 ) {
				if ( ! isset( $s[ $field ] ) || ! is_array( $s[ $field ] ) || ! isset( $s[ $field ][ $index ] ) || ! is_array( $s[ $field ][ $index ] ) ) { return; }
				$sk        = sanitize_key( $sub );
				$old       = isset( $s[ $field ][ $index ][ $sk ] ) ? $s[ $field ][ $index ][ $sk ] : '';
				$s[ $field ][ $index ][ $sk ] = $value;
			} else {
				$old         = isset( $s[ $field ] ) ? $s[ $field ] : '';
				$s[ $field ] = $value;
			}
			$found = true;
		} );

		if ( ! $found ) { wp_send_json_error( array( 'message' => 'Element not found on this post.' ) ); }

		// History (undo) before persisting the new value.
		if ( RK_Visual_Settings::get( 'history' ) ) {
			self::push_history( $pid, array(
				'eid' => $eid, 'field' => $field, 'sub' => $sub, 'index' => $index,
				'kind' => $kind, 'region' => $region_key, 'old' => $old,
				'ts' => time(), 'user' => get_current_user_id(),
			) );
		}

		self::save_data( $pid, $data );
		wp_send_json_success( array( 'message' => 'Saved.' ) );
	}

	/** AJAX: undo the most recent edit on a post. */
	public static function ajax_undo() {
		$pid = self::guard();
		if ( ! RK_Visual_Settings::get( 'history' ) ) { wp_send_json_error( array( 'message' => 'History is turned off.' ) ); }
		$hist = get_post_meta( $pid, self::HISTORY_KEY, true );
		if ( ! is_array( $hist ) || ! $hist ) { wp_send_json_error( array( 'message' => 'Nothing to undo.' ) ); }
		$entry = array_pop( $hist );

		$data = self::load_data( $pid );
		if ( null === $data ) { wp_send_json_error( array( 'message' => 'No Elementor data.' ) ); }

		$done = false;
		self::walk( $data, function ( &$el ) use ( $entry, &$done ) {
			if ( ! isset( $el['id'] ) || $el['id'] !== $entry['eid'] ) { return; }
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { $el['settings'] = array(); }
			$s = &$el['settings'];
			$f = $entry['field'];
			if ( 'html_region' === $entry['kind'] || 'image' === $entry['kind'] || 'link' === $entry['kind'] ) {
				$s[ $f ] = $entry['old'];
			} elseif ( '' !== $entry['sub'] && $entry['index'] >= 0 ) {
				if ( isset( $s[ $f ][ $entry['index'] ] ) && is_array( $s[ $f ][ $entry['index'] ] ) ) {
					$s[ $f ][ $entry['index'] ][ sanitize_key( $entry['sub'] ) ] = $entry['old'];
				}
			} else {
				$s[ $f ] = $entry['old'];
			}
			$done = true;
		} );

		if ( ! $done ) { wp_send_json_error( array( 'message' => 'Could not locate the element to restore.' ) ); }
		update_post_meta( $pid, self::HISTORY_KEY, $hist );
		self::save_data( $pid, $data );
		wp_send_json_success( array( 'message' => 'Reverted last change.', 'remaining' => count( $hist ) ) );
	}

	/** AJAX: return the recent history for a post (labels + counts). */
	public static function ajax_history() {
		$pid  = self::guard();
		$hist = get_post_meta( $pid, self::HISTORY_KEY, true );
		$out  = array();
		if ( is_array( $hist ) ) {
			foreach ( array_reverse( $hist ) as $h ) {
				$out[] = array(
					'field' => isset( $h['field'] ) ? $h['field'] : '',
					'kind'  => isset( $h['kind'] ) ? $h['kind'] : 'text',
					'when'  => isset( $h['ts'] ) ? human_time_diff( $h['ts'] ) . ' ago' : '',
				);
			}
		}
		wp_send_json_success( array( 'items' => $out ) );
	}

	/** Push a history entry (ring buffer). */
	private static function push_history( $pid, $entry ) {
		$hist = get_post_meta( $pid, self::HISTORY_KEY, true );
		if ( ! is_array( $hist ) ) { $hist = array(); }
		$hist[] = $entry;
		if ( count( $hist ) > self::HISTORY_MAX ) { $hist = array_slice( $hist, -self::HISTORY_MAX ); }
		update_post_meta( $pid, self::HISTORY_KEY, $hist );
	}

	/** Load + decode a post's Elementor data, or null. */
	private static function load_data( $pid ) {
		$raw  = get_post_meta( $pid, '_elementor_data', true );
		$data = $raw ? json_decode( $raw, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/** Persist Elementor data + bust caches. */
	private static function save_data( $pid, $data ) {
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		}
	}

	/**
	 * Replace the inner content of a <… data-rk-edit="KEY"> region inside an HTML
	 * blob, leaving every other byte of the markup untouched. Falls back to the
	 * original html if the region cannot be found or DOM parsing is unavailable.
	 *
	 * @param string $html   The raw HTML-widget code.
	 * @param string $key    The data-rk-edit key to target.
	 * @param string $newval Sanitized replacement HTML.
	 * @return string
	 */
	public static function replace_region( $html, $key, $newval ) {
		if ( '' === $key || ! class_exists( 'DOMDocument' ) ) { return $html; }
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		// Wrap so we can round-trip a fragment, and force UTF-8.
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="rk-cf-root">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
		$xpath = new DOMXPath( $doc );
		$nodes = $xpath->query( '//*[@data-rk-edit="' . $key . '"]' );
		if ( $nodes && $nodes->length ) {
			$node = $nodes->item( 0 );
			while ( $node->firstChild ) { $node->removeChild( $node->firstChild ); }
			$frag = $doc->createDocumentFragment();
			// Import the new value as HTML (best-effort); fall back to text.
			$tmp = new DOMDocument();
			$tmp->loadHTML( '<?xml encoding="utf-8"?><span id="rk-cf-val">' . $newval . '</span>', LIBXML_NOWARNING | LIBXML_NOERROR );
			$val = $tmp->getElementById( 'rk-cf-val' );
			if ( $val ) {
				foreach ( iterator_to_array( $val->childNodes ) as $child ) {
					$frag->appendChild( $doc->importNode( $child, true ) );
				}
			} else {
				$frag->appendChild( $doc->createTextNode( wp_strip_all_tags( $newval ) ) );
			}
			$node->appendChild( $frag );
		}
		$root = $doc->getElementById( 'rk-cf-root' );
		$out  = '';
		if ( $root ) {
			foreach ( $root->childNodes as $child ) { $out .= $doc->saveHTML( $child ); }
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return '' !== $out ? $out : $html;
	}
}
