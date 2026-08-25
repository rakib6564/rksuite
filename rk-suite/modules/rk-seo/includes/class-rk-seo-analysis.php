<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Analysis — real-time SEO + readability content analysis in the block editor
 * (Yoast/Rank Math style): focus keyword, Google snippet preview, and live
 * traffic-light checks. All scoring runs client-side in the editor; this class
 * only registers the meta and enqueues the sidebar.
 *
 * @package RK_SEO
 */
class Analysis {

	const T_FOCUS = '_rk_seo_focus_kw';

	public function hooks() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/** Expose the SEO meta to the editor (read/write via REST). */
	public function register_meta() {
		$keys = array(
			self::T_FOCUS      => 'string',
			Metabox::T_TITLE   => 'string',
			Metabox::T_DESC    => 'string',
		);
		foreach ( get_post_types( array( 'public' => true, 'show_in_rest' => true ), 'names' ) as $pt ) {
			if ( 'attachment' === $pt ) { continue; }
			foreach ( $keys as $key => $type ) {
				register_post_meta( $pt, $key, array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
				) );
			}
		}
	}

	public function enqueue() {
		// Only for public post types with an Elementor-agnostic block editor.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! $screen->is_block_editor() ) { return; }

		$js  = RK_SEO_DIR . 'assets/editor-analysis.js';
		$css = RK_SEO_DIR . 'assets/editor-analysis.css';
		wp_enqueue_script(
			'rk-seo-analysis',
			RK_SEO_URL . 'assets/editor-analysis.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose' ),
			file_exists( $js ) ? filemtime( $js ) : RK_SEO_VERSION,
			true
		);
		wp_enqueue_style(
			'rk-seo-analysis',
			RK_SEO_URL . 'assets/editor-analysis.css',
			array( 'wp-components' ),
			file_exists( $css ) ? filemtime( $css ) : RK_SEO_VERSION
		);
		wp_localize_script( 'rk-seo-analysis', 'RKSeoAnalysis', array(
			'focusKey' => self::T_FOCUS,
			'titleKey' => Metabox::T_TITLE,
			'descKey'  => Metabox::T_DESC,
			'sep'      => class_exists( '\RK\SEO\Search_Appearance' ) ? Search_Appearance::sep() : '-',
			'siteName' => Helpers::site_name(),
			'home'     => home_url( '/' ),
		) );
	}
}
