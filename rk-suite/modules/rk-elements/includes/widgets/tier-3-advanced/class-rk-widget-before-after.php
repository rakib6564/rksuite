<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Before_After extends RK_Widget_Base {
	public function get_name()     { return 'rk-before-after'; }
	public function get_title()    { return 'RK Before / After'; }
	public function get_icon()     { return 'eicon-image-before-after'; }
	public function get_keywords() { return array( 'before', 'after', 'compare', 'slider', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Images', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'before', array( 'label' => 'Before image', 'type' => \Elementor\Controls_Manager::MEDIA, 'default' => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ) ) );
		$this->add_control( 'after', array( 'label' => 'After image', 'type' => \Elementor\Controls_Manager::MEDIA, 'default' => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ) ) );
		$this->add_control( 'before_label', array( 'label' => 'Before label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Before' ) );
		$this->add_control( 'after_label', array( 'label' => 'After label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'After' ) );
		$this->add_control( 'orient', array( 'label' => 'Orientation', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h', 'options' => array( 'h' => 'Horizontal', 'v' => 'Vertical' ) ) );
		$this->add_control( 'start', array( 'label' => 'Start position (%)', 'type' => \Elementor\Controls_Manager::SLIDER, 'default' => array( 'size' => 50 ), 'range' => array( '%' => array( 'min' => 0, 'max' => 100 ) ) ) );
		$this->end_controls_section();
		/* ===== STYLE ===== */
		$this->start_controls_section( 'st_ba', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'ba_radius', array(
			'label' => 'Corner radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 10, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-ba' => 'border-radius:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'ba_hd_color', array( 'label' => 'Handle color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
			'selectors' => array( '{{WRAPPER}} .rk-ba-handle' => 'background:{{VALUE}};', '{{WRAPPER}} .rk-ba-handle span' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'ba_arrow', array( 'label' => 'Arrow color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384',
			'selectors' => array( '{{WRAPPER}} .rk-ba-handle span::before' => 'border-right-color:{{VALUE}};', '{{WRAPPER}} .rk-ba-handle span::after' => 'border-left-color:{{VALUE}};' ) ) );
		$this->add_control( 'ba_hd_size', array(
			'label' => 'Handle size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 24, 'max' => 64 ) ), 'default' => array( 'size' => 38, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-ba-handle span' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'ba_line', array(
			'label' => 'Divider width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 1, 'max' => 8 ) ), 'default' => array( 'size' => 2, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-ba[data-orient="h"] .rk-ba-handle' => 'width:{{SIZE}}{{UNIT}};', '{{WRAPPER}} .rk-ba[data-orient="v"] .rk-ba-handle' => 'height:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'ba_lbl_typo', 'label' => 'Label', 'selector' => '{{WRAPPER}} .rk-ba-lbl', 'separator' => 'before' ) );
		$this->add_control( 'ba_lbl_c', array( 'label' => 'Label text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-ba-lbl' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'ba_lbl_bg', array( 'label' => 'Label background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(16,24,40,.7)', 'selectors' => array( '{{WRAPPER}} .rk-ba-lbl' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->add_box_style( 'babox', 'Frame', '.rk-ba' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$b = ! empty( $s['before']['url'] ) ? $s['before']['url'] : '';
		$a = ! empty( $s['after']['url'] ) ? $s['after']['url'] : '';
		if ( ! $b || ! $a ) { return; }
		$pos = isset( $s['start']['size'] ) ? (int) $s['start']['size'] : 50;
		echo '<div class="rk-ba" data-rk-ba data-orient="' . esc_attr( $s['orient'] ) . '" data-start="' . esc_attr( $pos ) . '">';
		echo '<img class="rk-ba-after" src="' . esc_url( $a ) . '" alt="' . esc_attr( $s['after_label'] ) . '">';
		echo '<div class="rk-ba-before-wrap"><img class="rk-ba-before" src="' . esc_url( $b ) . '" alt="' . esc_attr( $s['before_label'] ) . '"></div>';
		if ( $s['before_label'] ) { echo '<span class="rk-ba-lbl rk-ba-lbl-b">' . esc_html( $s['before_label'] ) . '</span>'; }
		if ( $s['after_label'] ) { echo '<span class="rk-ba-lbl rk-ba-lbl-a">' . esc_html( $s['after_label'] ) . '</span>'; }
		$orient = ( 'v' === $s['orient'] ) ? 'vertical' : 'horizontal';
		echo '<div class="rk-ba-handle" tabindex="0" role="slider" aria-label="Comparison slider" aria-orientation="' . esc_attr( $orient ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $pos . '"><span></span></div>';
		echo '</div>';
	}
}
