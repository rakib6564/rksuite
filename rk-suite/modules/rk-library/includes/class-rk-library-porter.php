<?php
/**
 * RK_Library_Porter — import / export RK Library bundles. A bundle carries many
 * templates plus their category, so a whole design set moves in one file.
 * Also accepts a single Elementor template export as a one-item import.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Library_Porter {

	const FORMAT = 'rk-library';

	/** Export selected (or all) items as one bundle array. */
	public static function export( $ids = array() ) {
		$items = array();
		$posts = $ids ? array_map( 'get_post', array_map( 'intval', $ids ) ) : RK_Library_Store::all();
		foreach ( $posts as $p ) {
			if ( ! $p || RK_Library_Store::CPT !== $p->post_type ) { continue; }
			$items[] = array(
				'title'         => $p->post_title,
				'category'      => RK_Library_Store::cat_of( $p->ID ),
				'type'          => RK_Library_Store::type_of( $p->ID ),
				'thumbnail'     => RK_Library_Store::thumb_of( $p->ID ),
				'content'       => RK_Library_Store::content_of( $p->ID ),
				'page_settings' => RK_Library_Store::page_settings_of( $p->ID ),
			);
		}
		return array(
			'format'    => self::FORMAT,
			'version'   => defined( 'RK_LIBRARY_VERSION' ) ? RK_LIBRARY_VERSION : '1',
			'generated' => gmdate( 'c' ),
			'items'     => $items,
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

	/** Import a decoded bundle OR a single Elementor export. Returns report. */
	public static function import( $b, $fallback_category = '' ) {
		if ( ! is_array( $b ) ) { return array( 'made' => 0, 'msg' => 'Invalid file.' ); }

		// Single Elementor template export -> wrap as one item.
		if ( isset( $b['content'] ) && ! isset( $b['items'] ) ) {
			$b = array( 'items' => array( array(
				'title'    => isset( $b['title'] ) ? $b['title'] : 'Imported template',
				'category' => $fallback_category,
				'type'     => isset( $b['type'] ) ? $b['type'] : 'section',
				'content'  => $b['content'],
				'page_settings' => isset( $b['page_settings'] ) ? $b['page_settings'] : array(),
			) ) );
		}

		if ( empty( $b['items'] ) || ! is_array( $b['items'] ) ) { return array( 'made' => 0, 'msg' => 'No templates found in file.' ); }
		$made = 0;
		foreach ( $b['items'] as $it ) {
			if ( ! is_array( $it ) || empty( $it['content'] ) ) { continue; }
			$id = RK_Library_Store::save_item( array(
				'title'         => isset( $it['title'] ) ? $it['title'] : 'Template',
				'category'      => ! empty( $it['category'] ) ? $it['category'] : $fallback_category,
				'type'          => isset( $it['type'] ) ? $it['type'] : 'container',
				'content'       => $it['content'],
				'page_settings' => isset( $it['page_settings'] ) ? $it['page_settings'] : array(),
				'thumbnail'     => isset( $it['thumbnail'] ) ? $it['thumbnail'] : '',
			) );
			if ( $id ) { $made++; }
		}
		return array( 'made' => $made, 'msg' => $made . ' template(s) imported.' );
	}

	public static function example_bundle() {
		$file = RK_LIBRARY_DIR . 'data/starter-library.json';
		if ( file_exists( $file ) ) { $b = json_decode( (string) file_get_contents( $file ), true ); if ( is_array( $b ) ) { return $b; } }
		return array( 'format' => self::FORMAT, 'items' => array() );
	}
}
