<?php
/**
 * RK_Library_Editor — registers the Elementor source and mounts the custom
 * in-editor library (button + branded modal). Also serves item content over
 * AJAX for the modal's direct-insert fallback. All Elementor-facing hooks are
 * harmless when Elementor is absent.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Library_Editor {

	public function hooks() {
		add_action( 'elementor/template-library/register_sources', array( $this, 'register_source' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'editor_assets' ) );
		add_action( 'wp_ajax_rk_library_items', array( $this, 'ajax_items' ) );
		add_action( 'wp_ajax_rk_library_get', array( $this, 'ajax_get' ) );
	}

	public function register_source( $manager ) {
		if ( ! class_exists( 'RK_Library_Source' ) ) { return; }
		try { $manager->register_source( 'RK_Library_Source' ); } catch ( \Throwable $e ) { rk_suite_log( '[RK Library] source register failed: ' . $e->getMessage() ); }
	}

	public function editor_assets() {
		$css = RK_LIBRARY_DIR . 'assets/editor.css';
		$js  = RK_LIBRARY_DIR . 'assets/editor.js';
		wp_enqueue_style( 'rk-library-editor', RK_LIBRARY_URL . 'assets/editor.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_LIBRARY_VERSION );
		wp_enqueue_script( 'rk-library-editor', RK_LIBRARY_URL . 'assets/editor.js', array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : RK_LIBRARY_VERSION, true );
		wp_localize_script( 'rk-library-editor', 'RKLIB', array(
			'ajax'       => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'rk_library' ),
			'categories' => RK_Library_Store::categories(),
			'items'      => $this->items_payload(),
		) );
	}

	private function items_payload() {
		$out = array();
		foreach ( RK_Library_Store::all() as $p ) {
			$out[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'cat'   => RK_Library_Store::cat_of( $p->ID ),
				'type'  => RK_Library_Store::type_of( $p->ID ),
				'thumb' => RK_Library_Store::thumb_of( $p->ID ),
			);
		}
		return $out;
	}

	public function ajax_items() {
		check_ajax_referer( 'rk_library', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error(); }
		wp_send_json_success( array( 'items' => $this->items_payload(), 'categories' => RK_Library_Store::categories() ) );
	}

	/** Return an item's content (fresh ids) for the direct-insert fallback. */
	public function ajax_get() {
		check_ajax_referer( 'rk_library', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error(); }
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$content = RK_Library_Store::regenerate_ids( RK_Library_Store::content_of( $id ) );
		if ( ! $content ) { wp_send_json_error( array( 'message' => 'Empty template.' ) ); }
		wp_send_json_success( array(
			'content'       => $content,
			'type'          => RK_Library_Store::type_of( $id ),
			'page_settings' => RK_Library_Store::page_settings_of( $id ),
		) );
	}
}
