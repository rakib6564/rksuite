<?php
/**
 * RK_Library_Source — registers RK Library items as an Elementor template
 * library source ("rk-library"). The custom editor modal browses items and
 * calls Elementor's own importTemplate against this source, so insertion uses
 * Elementor's battle-tested pipeline (id remap, global styles, etc.).
 * Guarded: does nothing if Elementor's Source_Base is unavailable.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( '\Elementor\TemplateLibrary\Source_Base' ) ) { return; }

class RK_Library_Source extends \Elementor\TemplateLibrary\Source_Base {

	public function get_id()    { return 'rk-library'; }
	public function get_title() { return 'RK Library'; }
	public function register_data() {}

	public function get_items( $args = array() ) {
		$out = array();
		foreach ( RK_Library_Store::all() as $p ) { $out[] = $this->item( $p ); }
		return $out;
	}

	public function get_item( $template_id ) {
		$p = RK_Library_Store::get( $template_id );
		return $p ? $this->item( $p ) : array();
	}

	private function item( $p ) {
		return array(
			'template_id' => $p->ID,
			'title'       => $p->post_title,
			'source'      => $this->get_id(),
			'type'        => RK_Library_Store::type_of( $p->ID ),
			'subtype'     => RK_Library_Store::cat_of( $p->ID ),
			'thumbnail'   => RK_Library_Store::thumb_of( $p->ID ),
			'date'        => strtotime( $p->post_date ),
			'author'      => '',
			'tags'        => array( RK_Library_Store::cat_of( $p->ID ) ),
			'url'         => '',
		);
	}

	/** Content Elementor inserts when a template is chosen. */
	public function get_data( array $args ) {
		$id      = isset( $args['template_id'] ) ? (int) $args['template_id'] : 0;
		$content = RK_Library_Store::content_of( $id );
		// Fresh ids on every insert so repeated inserts never collide.
		$content = RK_Library_Store::regenerate_ids( $content );
		$content = $this->replace_elements_ids( $content );
		$data = array(
			'content'       => $content,
			'page_settings' => RK_Library_Store::page_settings_of( $id ),
			'type'          => RK_Library_Store::type_of( $id ),
		);
		// Let Elementor process/normalise the content for the current site.
		if ( method_exists( $this, 'process_export_import_content' ) ) {
			$data['content'] = $this->process_export_import_content( $data['content'], 'on_import' );
		}
		return $data;
	}

	/* Read-only source — writes are no-ops. */
	public function save_item( $template_data ) { return new \WP_Error( 'rk_readonly', 'RK Library is managed from its admin screen.' ); }
	public function update_item( $new_data ) { return new \WP_Error( 'rk_readonly', 'RK Library is read-only here.' ); }
	public function delete_template( $template_id ) { return false; }
	public function export_template( $template_id ) { return false; }
}
