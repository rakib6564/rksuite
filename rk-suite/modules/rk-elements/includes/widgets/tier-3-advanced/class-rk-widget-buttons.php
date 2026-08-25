<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Buttons extends RK_Widget_Base {
	public function get_name()     { return 'rk-buttons'; }
	public function get_title()    { return 'RK Buttons'; }
	public function get_icon()     { return 'eicon-button'; }
	public function get_keywords() { return array( 'button', 'cta', 'gradient', 'glass', 'shine', '3d', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Button', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'preset', array( 'label' => 'Style preset', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fill',
			'options' => array( 'fill' => 'Solid fill', 'outline' => 'Outline', 'gradient' => 'Gradient', 'glass' => 'Glass', '3d' => '3D press', 'shine' => 'Shine sweep', 'sweep' => 'Fill sweep', 'pill' => 'Pill glow', 'arrow' => 'Arrow slide' ),
			'prefix_class' => 'rk-btn--' ) );
		$this->add_control( 'text', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Click me' ) );
		$this->add_control( 'link', array( 'label' => 'Link', 'type' => \Elementor\Controls_Manager::URL, 'default' => array( 'url' => '#' ) ) );
		$this->add_control( 'icon', array( 'label' => 'Icon', 'type' => \Elementor\Controls_Manager::ICONS ) );
		$this->add_control( 'size', array( 'label' => 'Size', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'md', 'options' => array( 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large' ), 'prefix_class' => 'rk-btn-size--' ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array( 'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ), 'center' => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ), 'flex-end' => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ) ),
			'default' => 'flex-start', 'selectors' => array( '{{WRAPPER}} .rk-btnwrap' => 'display:flex;justify-content:{{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 's', array( 'label' => 'Colors', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'c1', array( 'label' => 'Primary', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-btn' => '--rk-btn-1:{{VALUE}};' ) ) );
		$this->add_control( 'c2', array( 'label' => 'Secondary (gradient/glow)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#17b3a3', 'selectors' => array( '{{WRAPPER}} .rk-btn' => '--rk-btn-2:{{VALUE}};' ) ) );
		$this->add_control( 'ct', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-btn' => '--rk-btn-t:{{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typo', 'selector' => '{{WRAPPER}} .rk-btn' ) );
		$this->end_controls_section();
		$this->add_box_style( 'btnbox', 'Button box', '.rk-btn' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$href = ! empty( $s['link']['url'] ) ? $s['link']['url'] : '#';
		$tgt = ! empty( $s['link']['is_external'] ) ? ' target="_blank" rel="noopener"' : '';
		echo '<div class="rk-btnwrap"><a class="rk-btn" href="' . esc_url( $href ) . '"' . $tgt . '>';
		if ( ! empty( $s['icon']['value'] ) ) { echo '<span class="rk-btn-ico">'; \Elementor\Icons_Manager::render_icon( $s['icon'], array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '<span class="rk-btn-txt">' . esc_html( $s['text'] ) . '</span>';
		echo '<span class="rk-btn-arrow" aria-hidden="true">→</span>';
		echo '</a></div>';
	}
}
