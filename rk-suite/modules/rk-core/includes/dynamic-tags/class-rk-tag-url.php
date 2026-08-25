<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) { return; }

class RK_Tag_URL extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() { return 'rk-field-url'; }
	public function get_title() { return 'RK Field (URL)'; }
	public function get_group() { return 'rk-core'; }
	public function get_categories() { return array( 'url' ); }

	protected function register_controls() {
		$this->add_control( 'rk_key', array(
			'label'   => 'URL field',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => RK_Core_Dynamic_Tags::field_options( array( 'url', 'email', 'oembed' ) ),
		) );
	}

	public function render() {
		$key = RK_Core_Dynamic_Tags::real_key( $this->get_settings( 'rk_key' ) );
		if ( ! $key ) { return; }
		echo esc_url( (string) get_post_meta( get_the_ID(), $key, true ) );
	}
}
