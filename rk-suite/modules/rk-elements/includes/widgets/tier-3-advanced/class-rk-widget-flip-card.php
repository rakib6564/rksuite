<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Flip_Card extends RK_Widget_Base {
	public function get_name()     { return 'rk-flip-card'; }
	public function get_title()    { return 'RK Flip Card'; }
	public function get_icon()     { return 'eicon-flip-box'; }
	public function get_keywords() { return array( 'flip', 'card', 'flip box', '3d', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'cf', array( 'label' => 'Front', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'f_icon', array( 'label' => 'Icon', 'type' => \Elementor\Controls_Manager::ICONS ) );
		$this->add_control( 'f_title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Front title' ) );
		$this->add_control( 'f_text', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Hover to flip.' ) );
		$this->add_control( 'f_img', array( 'label' => 'Background image', 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->end_controls_section();

		$this->start_controls_section( 'cb', array( 'label' => 'Back', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'b_title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Back title' ) );
		$this->add_control( 'b_text', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'More detail on the back.' ) );
		$this->add_control( 'b_btn', array( 'label' => 'Button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Learn more' ) );
		$this->add_control( 'b_link', array( 'label' => 'Link', 'type' => \Elementor\Controls_Manager::URL ) );
		$this->end_controls_section();

		$this->start_controls_section( 'cs', array( 'label' => 'Settings', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'dir', array( 'label' => 'Flip direction', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Left', 'right' => 'Right', 'up' => 'Up', 'down' => 'Down' ) ) );
		$this->add_control( 'trigger', array( 'label' => 'Trigger', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'hover', 'options' => array( 'hover' => 'Hover', 'click' => 'Click' ) ) );
		$this->add_responsive_control( 'height', array( 'label' => 'Height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 200, 'max' => 600 ) ), 'default' => array( 'size' => 320 ), 'selectors' => array( '{{WRAPPER}} .rk-flip' => 'height:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'st', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'f_bg', array( 'label' => 'Front background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-flip-front' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'b_bg', array( 'label' => 'Back background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#101828', 'selectors' => array( '{{WRAPPER}} .rk-flip-back' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'radius', array( 'label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'default' => array( 'size' => 16 ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .rk-flip-face' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'flipbox', 'Card faces', '.rk-flip-face' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$bg = ! empty( $s['f_img']['url'] ) ? ' style="background-image:linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.35)),url(' . esc_url( $s['f_img']['url'] ) . ');background-size:cover;background-position:center;"' : '';
		echo '<div class="rk-flip rk-flip--' . esc_attr( $s['dir'] ) . ' rk-flip--' . esc_attr( $s['trigger'] ) . '" data-rk-flip>';
		echo '<div class="rk-flip-inner">';
		echo '<div class="rk-flip-face rk-flip-front"' . $bg . '>';
		if ( ! empty( $s['f_icon']['value'] ) ) { echo '<span class="rk-flip-ico">'; \Elementor\Icons_Manager::render_icon( $s['f_icon'], array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '<h3>' . esc_html( $s['f_title'] ) . '</h3><p>' . esc_html( $s['f_text'] ) . '</p></div>';
		echo '<div class="rk-flip-face rk-flip-back"><h3>' . esc_html( $s['b_title'] ) . '</h3><p>' . esc_html( $s['b_text'] ) . '</p>';
		if ( ! empty( $s['b_btn'] ) ) {
			$href = ! empty( $s['b_link']['url'] ) ? $s['b_link']['url'] : '#';
			echo '<a class="rk-flip-btn" href="' . esc_url( $href ) . '">' . esc_html( $s['b_btn'] ) . '</a>';
		}
		echo '</div></div></div>';
	}
}
