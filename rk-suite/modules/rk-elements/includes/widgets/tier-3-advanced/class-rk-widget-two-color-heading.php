<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Two_Color_Heading extends RK_Widget_Base {
	public function get_name()     { return 'rk-two-color-heading'; }
	public function get_title()    { return 'RK Two-Color Heading'; }
	public function get_icon()     { return 'eicon-t-letter'; }
	public function get_keywords() { return array( 'heading', 'title', 'dual', 'two color', 'highlight', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Heading', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'p1', array( 'label' => 'First part', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Build something' ) );
		$this->add_control( 'p2', array( 'label' => 'Highlighted part', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'beautiful' ) );
		$this->add_control( 'p3', array( 'label' => 'Last part', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'today' ) );
		$this->add_control( 'tag', array( 'label' => 'Tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h2', 'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div', 'span' => 'span' ) ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array( 'left' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ) ),
			'default' => 'left', 'selectors' => array( '{{WRAPPER}} .rk-2ch' => 'text-align:{{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 's', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typo', 'selector' => '{{WRAPPER}} .rk-2ch' ) );
		$this->add_control( 'c1', array( 'label' => 'First/last color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#101828', 'selectors' => array( '{{WRAPPER}} .rk-2ch' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'grad', array( 'label' => 'Gradient highlight', 'type' => \Elementor\Controls_Manager::SWITCHER ) );
		$this->add_control( 'c2', array( 'label' => 'Highlight color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'condition' => array( 'grad!' => 'yes' ), 'selectors' => array( '{{WRAPPER}} .rk-2ch .hl' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'g1', array( 'label' => 'Gradient start', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'condition' => array( 'grad' => 'yes' ) ) );
		$this->add_control( 'g2', array( 'label' => 'Gradient end', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#17b3a3', 'condition' => array( 'grad' => 'yes' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'chbox', 'Heading box', '.rk-2ch' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$tag = in_array( $s['tag'], array( 'h1','h2','h3','h4','div','span' ), true ) ? $s['tag'] : 'h2';
		$hl_style = '';
		if ( 'yes' === $s['grad'] ) {
			$hl_style = ' style="background:linear-gradient(90deg,' . esc_attr( $s['g1'] ) . ',' . esc_attr( $s['g2'] ) . ');-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;"';
		}
		echo '<' . $tag . ' class="rk-2ch">';
		if ( '' !== $s['p1'] ) { echo esc_html( $s['p1'] ) . ' '; }
		if ( '' !== $s['p2'] ) { echo '<span class="hl"' . $hl_style . '>' . esc_html( $s['p2'] ) . '</span>'; }
		if ( '' !== $s['p3'] ) { echo ' ' . esc_html( $s['p3'] ); }
		echo '</' . $tag . '>';
	}
}
