<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Testimonial extends RK_Widget_Base {

	public function get_name() { return 'rk-testimonial'; }
	public function get_title() { return 'RK Testimonial'; }
	public function get_icon() { return 'eicon-testimonial'; }
	public function get_keywords() { return array( 'testimonial', 'quote', 'review', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Testimonial', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'quote', array( 'label' => 'Quote', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Working with this team was an absolute pleasure — highly recommended.' ) );
		$this->add_control( 'image', array( 'label' => 'Photo', 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->add_control( 'name', array( 'label' => 'Name', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Jane Cooper' ) );
		$this->add_control( 'role', array( 'label' => 'Role', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'CEO, Acme Inc.' ) );
		$this->add_control( 'rating', array( 'label' => 'Stars', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '5', 'options' => array( '0' => 'None', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent', array( 'label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#009687', 'selectors' => array( '{{WRAPPER}} .rk-testimonial' => 'border-top-color:{{VALUE}};', '{{WRAPPER}} .rk-stars' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'tsbox', 'Card box', '.rk-testimonial' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$img = isset( $s['image']['url'] ) && $s['image']['url'] ? $s['image']['url'] : '';
		$stars = (int) $s['rating'];
		echo '<figure class="rk-testimonial">';
		if ( $stars > 0 ) {
			echo '<div class="rk-stars">';
			for ( $i = 0; $i < 5; $i++ ) { echo '<span class="rk-star' . ( $i < $stars ? ' on' : '' ) . '">★</span>'; }
			echo '</div>';
		}
		echo '<blockquote class="rk-testimonial-quote">' . esc_html( $s['quote'] ) . '</blockquote>';
		echo '<figcaption class="rk-testimonial-cap">';
		if ( $img ) { echo '<img class="rk-testimonial-img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $s['name'] ) . '" />'; }
		echo '<span class="rk-testimonial-meta"><strong class="rk-testimonial-name">' . esc_html( $s['name'] ) . '</strong><span class="rk-testimonial-role">' . esc_html( $s['role'] ) . '</span></span>';
		echo '</figcaption></figure>';
	}
}
