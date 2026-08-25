<?php
/**
 * RK_Theme_Porter — export / import header & footer templates as fully
 * self-contained JSON bundles: the Elementor widget tree, page settings,
 * template type and display conditions travel together, so a template can be
 * moved between sites and shows up ready to display.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Porter {

	const FORMAT = 'rk-theme-template';

	/** Build a portable bundle for one or more template IDs. */
	public static function export( array $ids ) {
		$templates = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$p  = get_post( $id );
			if ( ! $p || RK_Theme_Store::CPT !== $p->post_type ) { continue; }
			$raw  = get_post_meta( $id, '_elementor_data', true );
			$data = $raw ? json_decode( $raw, true ) : array();
			$settings = get_post_meta( $id, '_elementor_page_settings', true );
			$templates[] = array(
				'title'          => $p->post_title,
				'type'           => RK_Theme_Store::type_of( $id ),
				'conditions'     => RK_Theme_Store::conditions_of( $id ),
				'elementor_data' => is_array( $data ) ? $data : array(),
				'page_settings'  => is_array( $settings ) ? $settings : array(),
			);
		}
		return array(
			'format'    => self::FORMAT,
			'version'   => defined( 'RK_THEME_VERSION' ) ? RK_THEME_VERSION : '1',
			'generated' => gmdate( 'c' ),
			'templates' => $templates,
		);
	}

	public static function to_json( $bundle ) {
		return wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	public static function download( $bundle, $filename ) {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo self::to_json( $bundle );
		exit;
	}

	/** Import a decoded bundle; returns a report array. */
	public static function import( $b ) {
		if ( ! is_array( $b ) || empty( $b['templates'] ) || ! is_array( $b['templates'] ) ) {
			return array( 'Nothing to import.' );
		}
		$made = 0;
		foreach ( $b['templates'] as $t ) {
			if ( ! is_array( $t ) ) { continue; }
			$type = isset( $t['type'] ) ? $t['type'] : 'header';
			$id   = RK_Theme_Store::create( isset( $t['title'] ) ? $t['title'] : '', $type );
			if ( ! $id ) { continue; }
			if ( ! empty( $t['conditions'] ) && is_array( $t['conditions'] ) ) {
				RK_Theme_Store::set_conditions( $id, $t['conditions'] );
			}
			$data = ( isset( $t['elementor_data'] ) && is_array( $t['elementor_data'] ) ) ? $t['elementor_data'] : array();
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $id, '_elementor_template_type', 'wp-post' );
			update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
			if ( ! empty( $t['page_settings'] ) && is_array( $t['page_settings'] ) ) {
				update_post_meta( $id, '_elementor_page_settings', $t['page_settings'] );
			}
			self::regen_css( $id );
			$made++;
		}
		return array( $made . ' template(s) imported' );
	}

	private static function regen_css( $id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) { return; }
		try { if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) { \Elementor\Core\Files\CSS\Post::create( $id )->update(); } } catch ( \Throwable $e ) {}
	}
}
