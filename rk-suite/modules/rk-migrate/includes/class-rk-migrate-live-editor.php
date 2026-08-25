<?php
/**
 * RK_Migrate_Live_Editor — lightweight front-end inline editor.
 *
 * For logged-in users who can edit content, it shows a small pencil on hover
 * over heading / text / button widgets. Click → edit the text in place → save.
 * The save writes straight into the correct post's Elementor data (header,
 * footer and template widgets map to their own source post via
 * .elementor[data-elementor-id]). Loads NOTHING for visitors.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Live_Editor {

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	/**
	 * Widget css class => list of editable targets. Each target is either
	 * simple  { field, sel [, html] }  or a repeater item
	 * { field, sub, item_sel, sel } where field is the repeater key and sub is
	 * the per-item text key.
	 */
	public static function targets() {
		return array(
			'elementor-widget-heading' => array(
				array( 'field' => 'title', 'sel' => '.elementor-heading-title' ),
			),
			'elementor-widget-text-editor' => array(
				array( 'field' => 'editor', 'sel' => '.elementor-text-editor, .elementor-widget-container', 'html' => 1 ),
			),
			'elementor-widget-button' => array(
				array( 'field' => 'text', 'sel' => '.elementor-button-text, .elementor-button .elementor-button-content-wrapper, a.elementor-button' ),
			),
			'elementor-widget-rk-heading' => array(
				array( 'field' => 'title', 'sel' => '.rk-heading' ),
			),
			'elementor-widget-rk-buttons' => array(
				array( 'field' => 'text', 'sel' => '.rk-btn-txt' ),
			),
			'elementor-widget-rk-download-button' => array(
				array( 'field' => 'text', 'sel' => '.rk-dl-btn span:last-child' ),
			),
			'elementor-widget-icon-box' => array(
				array( 'field' => 'title_text', 'sel' => '.elementor-icon-box-title' ),
				array( 'field' => 'description_text', 'sel' => '.elementor-icon-box-description', 'html' => 1 ),
			),
			'elementor-widget-image-box' => array(
				array( 'field' => 'title_text', 'sel' => '.elementor-image-box-title' ),
				array( 'field' => 'description_text', 'sel' => '.elementor-image-box-description', 'html' => 1 ),
			),
			'elementor-widget-icon-list' => array(
				array( 'field' => 'icon_list', 'sub' => 'text', 'item_sel' => '.elementor-icon-list-item', 'sel' => '.elementor-icon-list-text' ),
			),
			'elementor-widget-divider' => array(
				array( 'field' => 'text', 'sel' => '.elementor-divider__text' ),
			),
			'elementor-widget-testimonial' => array(
				array( 'field' => 'testimonial_content', 'sel' => '.elementor-testimonial-content', 'html' => 1 ),
				array( 'field' => 'testimonial_name', 'sel' => '.elementor-testimonial-name' ),
				array( 'field' => 'testimonial_job', 'sel' => '.elementor-testimonial-job' ),
			),
			'elementor-widget-call-to-action' => array(
				array( 'field' => 'title', 'sel' => '.elementor-cta__title' ),
				array( 'field' => 'description', 'sel' => '.elementor-cta__description', 'html' => 1 ),
				array( 'field' => 'button', 'sel' => '.elementor-cta__button .elementor-button-text' ),
			),
			'elementor-widget-price-table' => array(
				array( 'field' => 'heading', 'sel' => '.elementor-price-table__heading' ),
				array( 'field' => 'sub_heading', 'sel' => '.elementor-price-table__subheading' ),
				array( 'field' => 'period', 'sel' => '.elementor-price-table__period' ),
				array( 'field' => 'button_text', 'sel' => '.elementor-price-table__button .elementor-button-text' ),
				array( 'field' => 'features_list', 'sub' => 'item_text', 'item_sel' => '.elementor-price-table__feature-item', 'sel' => '.elementor-price-table__feature-item' ),
			),
			'elementor-widget-price-list' => array(
				array( 'field' => 'price_list', 'sub' => 'title', 'item_sel' => '.elementor-price-list-item', 'sel' => '.elementor-price-list__title' ),
				array( 'field' => 'price_list', 'sub' => 'item_description', 'item_sel' => '.elementor-price-list-item', 'sel' => '.elementor-price-list__description' ),
			),
		);
	}

	/** Flat allow-list of every field key that can be written (safety). */
	public static function allowed_fields() {
		$out = array();
		foreach ( self::targets() as $list ) {
			foreach ( $list as $t ) { $out[] = $t['field']; if ( isset( $t['sub'] ) ) { $out[] = $t['sub']; } }
		}
		return array_values( array_unique( $out ) );
	}

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 100 );
		add_action( 'wp_ajax_rk_migrate_live_save', array( $this, 'save' ) );
	}

	public function enqueue() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) { return; }
		// RK Visual Edit is now the dedicated on-page editor module. Whenever it is
		// present in the suite this legacy inline editor stands down entirely, so
		// disabling RK Visual means NO front-end editor appears (expected behaviour).
		if ( class_exists( 'RK_Suite_Modules' ) && RK_Suite_Modules::get( 'rk-visual' ) ) { return; }
		$css = RK_MIGRATE_DIR . 'assets/live-editor.css';
		$js  = RK_MIGRATE_DIR . 'assets/live-editor.js';
		wp_enqueue_style( 'rk-migrate-live', RK_MIGRATE_URL . 'assets/live-editor.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_MIGRATE_VERSION );
		wp_enqueue_script( 'rk-migrate-live', RK_MIGRATE_URL . 'assets/live-editor.js', array(), file_exists( $js ) ? filemtime( $js ) : RK_MIGRATE_VERSION, true );
		wp_localize_script( 'rk-migrate-live', 'RKLE', array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rk_migrate_live' ),
			'targets' => self::targets(),
		) );
	}

	public function save() {
		check_ajax_referer( 'rk_migrate_live', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => 'Not allowed.' ) ); }
		$pid   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$eid   = isset( $_POST['element_id'] ) ? sanitize_text_field( wp_unslash( $_POST['element_id'] ) ) : '';
		$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
		$sub   = isset( $_POST['sub'] ) && '' !== $_POST['sub'] ? sanitize_key( $_POST['sub'] ) : '';
		$index = isset( $_POST['index'] ) && '' !== $_POST['index'] ? (int) $_POST['index'] : -1;
		$value = isset( $_POST['value'] ) ? wp_kses_post( wp_unslash( $_POST['value'] ) ) : '';
		$allowed = self::allowed_fields();
		if ( ! $pid || '' === $eid || '' === $field || ! in_array( $field, $allowed, true ) ) { wp_send_json_error( array( 'message' => 'Bad request.' ) ); }
		if ( $sub && ! in_array( $sub, $allowed, true ) ) { wp_send_json_error( array( 'message' => 'Bad field.' ) ); }
		if ( ! current_user_can( 'edit_post', $pid ) ) { wp_send_json_error( array( 'message' => 'You cannot edit this content.' ) ); }

		$raw  = get_post_meta( $pid, '_elementor_data', true );
		$data = $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) ) { wp_send_json_error( array( 'message' => 'No Elementor data.' ) ); }

		$found = false;
		RK_Migrate_Scanner::walk( $data, function ( &$el ) use ( $eid, $field, $sub, $index, $value, &$found ) {
			if ( ! isset( $el['id'] ) || $el['id'] !== $eid ) { return; }
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { $el['settings'] = array(); }
			if ( '' !== $sub && $index >= 0 ) {
				if ( ! isset( $el['settings'][ $field ] ) || ! is_array( $el['settings'][ $field ] ) ) { return; }
				if ( ! isset( $el['settings'][ $field ][ $index ] ) || ! is_array( $el['settings'][ $field ][ $index ] ) ) { return; }
				$el['settings'][ $field ][ $index ][ $sub ] = $value;
			} else {
				$el['settings'][ $field ] = $value;
			}
			$found = true;
		} );
		if ( ! $found ) { wp_send_json_error( array( 'message' => 'Element not found.' ) ); }

		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		if ( class_exists( 'RK_Migrate_Scanner' ) ) { RK_Migrate_Scanner::clear_cache(); }
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		}
		wp_send_json_success( array( 'message' => 'Saved.' ) );
	}
}
