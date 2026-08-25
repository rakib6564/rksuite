<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Images — automatic image ALT text. Every image that ships without a
 * meaningful alt gets one on the fly, derived from (in order) the attachment's
 * saved alt, its title, its caption, a humanised filename, then the post
 * title. Covers Elementor image widgets & featured images (via the attachment
 * attributes filter) and raw <img> in content (via output filters). New
 * uploads also get their alt back-filled. Zero configuration.
 */
class Images {

	private $first_img = false; // first in-page <img> gets LCP priority

	/** Per-request memo: attachment id => resolved alt, and processed-HTML hashes. */
	private static $alt_cache = array();
	private static $seen_html = array();

	public function hooks() {
		// Elementor image widgets, featured images, galleries, core image block.
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attachment_attrs' ), 20, 3 );
		// Raw <img> inside rendered content.
		add_filter( 'the_content', array( $this, 'filter_html' ), 999 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_html' ), 999 );
		add_filter( 'widget_text_content', array( $this, 'filter_html' ), 999 );
		add_filter( 'elementor/frontend/the_content', array( $this, 'filter_html' ), 999 );
		// Back-fill saved alt on new uploads so audits and future renders agree.
		add_action( 'add_attachment', array( $this, 'backfill_upload' ) );
	}

	/* ---- attachment-based images (Elementor, featured, gallery, block) ---- */

	public function attachment_attrs( $attr, $attachment, $size ) {
		if ( ! empty( $attr['alt'] ) && '' !== trim( $attr['alt'] ) ) { return $attr; }
		$attr['alt'] = $this->alt_for_attachment( $attachment );
		return $attr;
	}

	private function alt_for_attachment( $attachment ) {
		$id = is_object( $attachment ) ? (int) $attachment->ID : (int) $attachment;
		if ( isset( self::$alt_cache[ $id ] ) ) { return self::$alt_cache[ $id ]; }
		$val = $this->compute_alt_for_attachment( $id );
		self::$alt_cache[ $id ] = $val;
		return $val;
	}

	private function compute_alt_for_attachment( $id ) {
		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( $alt ) { return $alt; }
		$post = get_post( $id );
		if ( $post ) {
			if ( ! empty( $post->post_title ) && ! preg_match( '/^\d{4}[-_]?\d{2}[-_]?\d{2}/', $post->post_title ) ) {
				$t = $this->humanise( $post->post_title );
				if ( '' !== $t ) { return $t; }
			}
			if ( ! empty( $post->post_excerpt ) ) { return Helpers::clean( $post->post_excerpt ); }
			$file = get_attached_file( $id );
			if ( $file ) { $fn = $this->humanise( pathinfo( $file, PATHINFO_FILENAME ) ); if ( '' !== $fn ) { return $fn; } }
			if ( $post->post_parent ) { return get_the_title( $post->post_parent ); }
		}
		return Helpers::site_name();
	}

	/* ---- raw <img> in HTML ---- */

	public function filter_html( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, '<img' ) ) { return $html; }
		// Elementor applies both the_content and elementor/frontend/the_content to
		// the same markup — process each distinct blob once per request.
		$key = md5( $html );
		if ( isset( self::$seen_html[ $key ] ) ) { return self::$seen_html[ $key ]; }
		$context = ( is_singular() && ! is_front_page() ) ? get_the_title( get_queried_object_id() ) : Helpers::site_name();
		$out = preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $context ) {
			return $this->fix_img_tag( $m[0], $context );
		}, $html );
		// Cache both directions so the second pass over the already-fixed output is also a no-op.
		self::$seen_html[ $key ]         = $out;
		self::$seen_html[ md5( $out ) ]  = $out;
		return $out;
	}

	private function fix_img_tag( $tag, $context ) {
		$src = '';
		if ( preg_match( '/\bsrc\s*=\s*("|\')(.*?)\1/i', $tag, $s ) ) { $src = $s[2]; }

		/* ---- alt ---- */
		$has_alt = ( preg_match( '/\balt\s*=\s*("|\')(.*?)\1/is', $tag, $a ) && '' !== trim( $a[2] ) );
		if ( ! $has_alt ) {
			$alt = $src ? $this->alt_from_src( $src ) : '';
			if ( '' === $alt ) { $alt = $context; }
			if ( '' !== $alt ) {
				$alt = esc_attr( $alt );
				if ( preg_match( '/\balt\s*=\s*("|\')\s*\1/i', $tag ) ) {
					$tag = preg_replace( '/\balt\s*=\s*("|\')\s*\1/i', 'alt="' . $alt . '"', $tag, 1 );
				} else {
					$tag = preg_replace( '/<img\b/i', '<img alt="' . $alt . '"', $tag, 1 );
				}
			}
		}

		/* ---- performance attributes ---- */
		$add = array();

		// width/height from the WordPress "-WxH" size suffix (no DB lookup).
		if ( ! preg_match( '/\bwidth\s*=/i', $tag ) && ! preg_match( '/\bheight\s*=/i', $tag )
			&& preg_match( '/-(\d{2,4})x(\d{2,4})\.(?:jpe?g|png|gif|webp|avif)/i', (string) $src, $wh ) ) {
			$add[] = 'width="' . (int) $wh[1] . '"';
			$add[] = 'height="' . (int) $wh[2] . '"';
		}

		// async decoding for every image.
		if ( ! preg_match( '/\bdecoding\s*=/i', $tag ) ) { $add[] = 'decoding="async"'; }

		// The first image on the page is the likely LCP: load it eagerly with high
		// fetch priority; everything after it lazy-loads. Never touch data-URIs.
		$is_data = ( 0 === stripos( (string) $src, 'data:' ) );
		if ( ! $is_data && ! $this->first_img ) {
			$this->first_img = true;
			$tag = preg_replace( '/\s+loading\s*=\s*("|\')[^"\']*\1/i', '', $tag ); // drop any lazy
			if ( ! preg_match( '/\bfetchpriority\s*=/i', $tag ) ) { $add[] = 'fetchpriority="high"'; }
			$add[] = 'loading="eager"';
		} elseif ( ! $is_data && ! preg_match( '/\bloading\s*=/i', $tag ) ) {
			$add[] = 'loading="lazy"';
		}

		if ( $add ) { $tag = preg_replace( '/<img\b/i', '<img ' . implode( ' ', $add ) . ' ', $tag, 1 ); }
		return $tag;
	}

	private function alt_from_src( $src ) {
		$src = preg_replace( '/\?.*$/', '', (string) $src );
		$name = pathinfo( $src, PATHINFO_FILENAME );
		$name = preg_replace( '/-\d{2,4}x\d{2,4}$/', '', $name ); // strip -1024x768 size suffix
		$name = preg_replace( '/-(scaled|rotated|e\d+)$/', '', $name );
		return $this->humanise( $name );
	}

	/* ---- new-upload back-fill ---- */

	public function backfill_upload( $post_id ) {
		if ( ! wp_attachment_is_image( $post_id ) ) { return; }
		$existing = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
		if ( $existing ) { return; }
		$alt = $this->alt_for_attachment( $post_id );
		if ( '' !== $alt ) { update_post_meta( $post_id, '_wp_attachment_image_alt', wp_slash( $alt ) ); }
	}

	/* ---- utils ---- */

	private function humanise( $name ) {
		$name = (string) $name;
		if ( '' === $name ) { return ''; }
		$name = preg_replace( '/[-_]+/', ' ', $name );
		$name = preg_replace( '/\s+/', ' ', $name );
		$name = trim( $name );
		if ( '' === $name || is_numeric( $name ) ) { return ''; }
		return ucwords( $name );
	}
}
