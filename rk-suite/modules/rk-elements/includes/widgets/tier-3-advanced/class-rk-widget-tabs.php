<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Tabs extends RK_Widget_Base {
	public function get_name()     { return 'rk-tabs'; }
	public function get_title()    { return 'RK Tabs'; }
	public function get_icon()     { return 'eicon-tabs'; }
	public function get_keywords() { return array( 'tabs', 'accordion', 'toggle', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Tabs', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'orient', array( 'label' => 'Orientation', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'top', 'options' => array( 'top' => 'Top', 'left' => 'Left' ), 'prefix_class' => 'rk-tabs--' ) );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', array( 'label' => 'Tab title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Tab' ) );
		$rep->add_control( 'content', array( 'label' => 'Content', 'type' => \Elementor\Controls_Manager::WYSIWYG, 'default' => 'Tab content goes here.' ) );
		$this->add_control( 'tabs', array( 'label' => 'Tabs', 'type' => \Elementor\Controls_Manager::REPEATER, 'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => array( array( 'title' => 'Tab One', 'content' => 'First tab content.' ), array( 'title' => 'Tab Two', 'content' => 'Second tab content.' ) ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 's', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'c_tab', array( 'label' => 'Tab text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-tab-btn' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_active', array( 'label' => 'Active color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-tab-btn.is-active' => 'color:{{VALUE}};border-color:{{VALUE}};', '{{WRAPPER}} .rk-tab-btn.is-active' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_ink', array( 'label' => 'Active underline', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-tab-btn.is-active::after' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'tabbox', 'Panel box', '.rk-tab-panels' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		if ( empty( $s['tabs'] ) ) { return; }
		$uid = 'rktabs-' . $this->get_id();
		echo '<div class="rk-tabs" id="' . esc_attr( $uid ) . '" data-rk-tabs>';
		echo '<div class="rk-tab-nav" role="tablist">';
		$i = 0;
		foreach ( $s['tabs'] as $t ) { $on = ( 0 === $i ); echo '<button class="rk-tab-btn' . ( $on ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( $on ? 'true' : 'false' ) . '" tabindex="' . ( $on ? '0' : '-1' ) . '" data-i="' . $i . '">' . esc_html( $t['title'] ) . '</button>'; $i++; }
		echo '</div><div class="rk-tab-panels">';
		$i = 0;
		foreach ( $s['tabs'] as $t ) { $on = ( 0 === $i ); echo '<div class="rk-tab-panel' . ( $on ? ' is-active' : '' ) . '" role="tabpanel" aria-hidden="' . ( $on ? 'false' : 'true' ) . '" data-i="' . $i . '">' . wp_kses_post( $t['content'] ) . '</div>'; $i++; }
		echo '</div></div>';
	}
}
