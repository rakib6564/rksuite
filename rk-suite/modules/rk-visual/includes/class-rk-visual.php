<?php
/**
 * RK_Visual — module bootstrap: wires the editor endpoints, the settings screen,
 * the front-end asset loading and the admin-bar "Edit visually" toggle.
 *
 * @package RK_Visual
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Visual {

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		RK_Visual_Editor::init();
		if ( is_admin() && class_exists( 'RK_Visual_Admin' ) ) { RK_Visual_Admin::instance(); }
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 100 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
	}

	/** True when the on-page editor should be available on the current view. */
	public function active_context() {
		if ( is_admin() || ! is_singular() ) { return false; }
		if ( ! RK_Visual_Settings::get( 'enabled_front' ) ) { return false; }
		if ( ! RK_Visual_Settings::user_can_edit() ) { return false; }
		$pid = get_queried_object_id();
		if ( ! $pid ) { return false; }
		// Only on Elementor-built content.
		return 'builder' === get_post_meta( $pid, '_elementor_edit_mode', true );
	}

	public function enqueue() {
		if ( ! $this->active_context() ) { return; }
		$pid = get_queried_object_id();

		$features = array(
			'rich'         => (bool) RK_Visual_Settings::get( 'rich' ),
			'html_regions' => (bool) RK_Visual_Settings::get( 'html_regions' ),
			'history'      => (bool) RK_Visual_Settings::get( 'history' ),
			'media'        => (bool) RK_Visual_Settings::get( 'media' ),
		);

		// wp.media only needed if image editing is on.
		if ( $features['media'] && function_exists( 'wp_enqueue_media' ) ) { wp_enqueue_media(); }

		$css = RK_VISUAL_DIR . 'assets/editor.css';
		$js  = RK_VISUAL_DIR . 'assets/editor.js';
		wp_enqueue_style( 'rk-visual', RK_VISUAL_URL . 'assets/editor.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_VISUAL_VERSION );
		wp_enqueue_script( 'rk-visual', RK_VISUAL_URL . 'assets/editor.js', array(), file_exists( $js ) ? filemtime( $js ) : RK_VISUAL_VERSION, true );

		wp_localize_script( 'rk-visual', 'RKVisual', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( RK_Visual_Editor::NONCE ),
			'postId'   => $pid,
			'targets'  => RK_Visual_Editor::targets(),
			'features' => $features,
			'i18n'     => array(
				'save' => __( 'Save', 'rk-suite' ),
				'cancel' => __( 'Cancel', 'rk-suite' ),
				'saved' => __( 'Saved', 'rk-suite' ),
				'undo' => __( 'Undo', 'rk-suite' ),
			),
		) );
	}

	/** Admin-bar toggle to switch editing mode on/off. */
	public function admin_bar( $bar ) {
		if ( ! $this->active_context() || ! is_object( $bar ) ) { return; }
		$bar->add_node( array(
			'id'    => 'rk-visual-toggle',
			'title' => '✎ ' . __( 'Edit visually', 'rk-suite' ),
			'href'  => '#',
			'meta'  => array( 'class' => 'rk-visual-abbtn' ),
		) );
	}
}
