<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Counter extends RK_Widget_Base {

	public function get_name() { return 'rk-counter'; }
	public function get_title() { return 'RK Counter'; }
	public function get_icon() { return 'eicon-counter'; }
	public function get_keywords() { return array( 'counter', 'number', 'stat', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Counter', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'start', array( 'label' => 'Start', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0 ) );
		$this->add_control( 'end', array( 'label' => 'End', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 250 ) );
		$this->add_control( 'duration', array( 'label' => 'Duration (ms)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 1600 ) );
		$this->add_control( 'prefix', array( 'label' => 'Prefix', 'type' => \Elementor\Controls_Manager::TEXT ) );
		$this->add_control( 'suffix', array( 'label' => 'Suffix', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '+' ) );
		$this->add_control( 'title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Happy clients' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'num_color', array( 'label' => 'Number color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-counter-num' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'title_color', array( 'label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-counter-title' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'cnbox', 'Counter box', '.rk-counter' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		echo '<div class="rk-counter" data-rk-counter data-start="' . esc_attr( (int) $s['start'] ) . '" data-end="' . esc_attr( (int) $s['end'] ) . '" data-duration="' . esc_attr( (int) $s['duration'] ) . '">';
		echo '<div class="rk-counter-num"><span class="rk-counter-prefix">' . esc_html( $s['prefix'] ) . '</span><span class="rk-counter-value">' . esc_html( (int) $s['start'] ) . '</span><span class="rk-counter-suffix">' . esc_html( $s['suffix'] ) . '</span></div>';
		if ( $s['title'] ) { echo '<div class="rk-counter-title">' . esc_html( $s['title'] ) . '</div>'; }
		echo '</div>';
	}
}
