<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) { return; }

class RK_Tag_Image extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() { return 'rk-field-image'; }
	public function get_title() { return 'RK Field (Image)'; }
	public function get_group() { return 'rk-core'; }
	public function get_categories() { return array( 'image' ); }

	protected function register_controls() {
		$this->add_control( 'rk_key', array(
			'label'   => 'Image field',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => RK_Core_Dynamic_Tags::field_options( array( 'image' ) ),
		) );
	}

	public function get_value( array $options = array() ) {
		$key = RK_Core_Dynamic_Tags::real_key( $this->get_settings( 'rk_key' ) );
		$rv  = RK_Core_Dynamic_Tags::row_value( $key );
		$id  = ( null !== $rv ) ? (int) ( is_array( $rv ) ? reset( $rv ) : $rv ) : ( $key ? (int) get_post_meta( get_the_ID(), $key, true ) : 0 );
		return array( 'id' => $id, 'url' => $id ? wp_get_attachment_image_url( $id, 'full' ) : '' );
	}
}
