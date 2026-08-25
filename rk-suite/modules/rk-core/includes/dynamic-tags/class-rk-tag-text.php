<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) { return; }

class RK_Tag_Text extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() { return 'rk-field'; }
	public function get_title() { return 'RK Field'; }
	public function get_group() { return 'rk-core'; }
	public function get_categories() { return array( 'text', 'post_meta' ); }

	protected function register_controls() {
		$this->add_control( 'rk_key', array(
			'label'   => 'Field',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => RK_Core_Dynamic_Tags::field_options( RK_Core_Dynamic_Tags::text_types() ),
		) );
	}

	public function render() {
		$key = $this->get_settings( 'rk_key' );
		if ( ! $key ) { return; }
		echo wp_kses_post( RK_Core_Dynamic_Tags::resolve_value( $key ) );
	}
}
