<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Steps extends RK_Widget_Base {
	public function get_name()     { return 'rk-steps'; }
	public function get_title()    { return 'RK Steps'; }
	public function get_icon()     { return 'eicon-number-field'; }
	public function get_keywords() { return array( 'steps', 'process', 'timeline', 'how', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Steps', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'layout', array( 'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'horizontal', 'options' => array( 'horizontal' => 'Horizontal', 'vertical' => 'Vertical' ), 'prefix_class' => 'rk-steps--' ) );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'num', array( 'label' => 'Number/label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '1' ) );
		$rep->add_control( 'title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Step title' ) );
		$rep->add_control( 'desc', array( 'label' => 'Description', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Short description of this step.' ) );
		$this->add_control( 'steps', array( 'label' => 'Steps', 'type' => \Elementor\Controls_Manager::REPEATER, 'fields' => $rep->get_controls(), 'title_field' => '{{{ num }}}. {{{ title }}}',
			'default' => array( array( 'num' => '1', 'title' => 'Plan' ), array( 'num' => '2', 'title' => 'Design' ), array( 'num' => '3', 'title' => 'Launch' ) ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 's', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'c_badge', array( 'label' => 'Badge background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-step-num' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'c_badge_t', array( 'label' => 'Badge text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-step-num' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_line', array( 'label' => 'Connector line', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#d7ded9', 'selectors' => array( '{{WRAPPER}} .rk-step::after' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'c_title', array( 'label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-step h4' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'stepbox', 'Step box', '.rk-step' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		if ( empty( $s['steps'] ) ) { return; }
		echo '<div class="rk-steps">';
		foreach ( $s['steps'] as $st ) {
			echo '<div class="rk-step"><div class="rk-step-num">' . esc_html( $st['num'] ) . '</div><div class="rk-step-body"><h4>' . esc_html( $st['title'] ) . '</h4>';
			if ( ! empty( $st['desc'] ) ) { echo '<p>' . esc_html( $st['desc'] ) . '</p>'; }
			echo '</div></div>';
		}
		echo '</div>';
	}
}
