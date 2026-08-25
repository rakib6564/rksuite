<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Progress extends RK_Widget_Base {

	public function get_name() { return 'rk-progress'; }
	public function get_title() { return 'RK Progress Bar'; }
	public function get_icon() { return 'eicon-skill-bar'; }
	public function get_keywords() { return array( 'progress', 'skill', 'bar', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Progress', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'label', array( 'label' => 'Label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Design' ) );
		$this->add_control( 'percent', array( 'label' => 'Percent', 'type' => \Elementor\Controls_Manager::SLIDER, 'default' => array( 'size' => 80 ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ) ) );
		$this->add_control( 'show_percent', array( 'label' => 'Show %', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'bar_color', array( 'label' => 'Bar color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#009687', 'selectors' => array( '{{WRAPPER}} .rk-progress-fill' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'track_color', array( 'label' => 'Track color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#eef2f3', 'selectors' => array( '{{WRAPPER}} .rk-progress-track' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'pgbox', 'Bar box', '.rk-progress' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$pct = isset( $s['percent']['size'] ) ? (int) $s['percent']['size'] : 0;
		echo '<div class="rk-progress">';
		echo '<div class="rk-progress-head"><span class="rk-progress-label">' . esc_html( $s['label'] ) . '</span>';
		if ( 'yes' === $s['show_percent'] ) { echo '<span class="rk-progress-pct">' . esc_html( $pct ) . '%</span>'; }
		echo '</div>';
		echo '<div class="rk-progress-track"><div class="rk-progress-fill" data-rk-progress data-pct="' . esc_attr( $pct ) . '" style="width:0;"></div></div>';
		echo '</div>';
	}
}
