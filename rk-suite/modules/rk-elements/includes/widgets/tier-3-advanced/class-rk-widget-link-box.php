<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Link_Box extends RK_Widget_Base {
	public function get_name()     { return 'rk-link-box'; }
	public function get_title()    { return 'RK Link Box'; }
	public function get_icon()     { return 'eicon-single-page'; }
	public function get_keywords() { return array( 'link', 'box', 'container', 'card', 'clickable', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'image', array( 'label' => 'Image', 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->add_control( 'title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Clickable card' ) );
		$this->add_control( 'text', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'The whole box is a link.' ) );
		$this->add_control( 'link', array( 'label' => 'Box link', 'type' => \Elementor\Controls_Manager::URL, 'placeholder' => 'https://…', 'default' => array( 'url' => '#' ) ) );
		$this->add_control( 'hover', array( 'label' => 'Hover effect', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'lift', 'options' => array( 'lift' => 'Lift', 'zoom' => 'Image zoom', 'none' => 'None' ), 'prefix_class' => 'rk-lb--' ) );
		$this->end_controls_section();

		$this->start_controls_section( 's', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-lb' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'radius', array( 'label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'default' => array( 'size' => 14 ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .rk-lb' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'c_title', array( 'label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#101828', 'selectors' => array( '{{WRAPPER}} .rk-lb-title' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_text', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#667085', 'selectors' => array( '{{WRAPPER}} .rk-lb-text' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'lbbox', 'Card box', '.rk-lb' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$href = ! empty( $s['link']['url'] ) ? $s['link']['url'] : '#';
		$tgt = ! empty( $s['link']['is_external'] ) ? ' target="_blank" rel="noopener"' : '';
		echo '<a class="rk-lb" href="' . esc_url( $href ) . '"' . $tgt . '>';
		if ( ! empty( $s['image']['url'] ) ) { echo '<span class="rk-lb-media"><img src="' . esc_url( $s['image']['url'] ) . '" alt="' . esc_attr( $s['title'] ) . '"></span>'; }
		echo '<span class="rk-lb-body"><span class="rk-lb-title">' . esc_html( $s['title'] ) . '</span>';
		if ( ! empty( $s['text'] ) ) { echo '<span class="rk-lb-text">' . esc_html( $s['text'] ) . '</span>'; }
		echo '</span></a>';
	}
}
