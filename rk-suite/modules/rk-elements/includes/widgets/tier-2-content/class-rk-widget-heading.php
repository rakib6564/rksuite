<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Heading extends RK_Widget_Base {

	public function get_name() { return 'rk-heading'; }
	public function get_title() { return 'RK Heading'; }
	public function get_icon() { return 'eicon-t-letter'; }
	public function get_keywords() { return array( 'heading', 'title', 'gradient', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Heading', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'title', array(
			'label'   => 'Text',
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => 'A headline that stands out',
		) );
		$this->add_control( 'tag', array(
			'label'   => 'HTML tag',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'h2',
			'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ),
		) );
		$this->add_responsive_control( 'align', array(
			'label'     => 'Alignment',
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'default'   => 'left',
			'selectors' => array( '{{WRAPPER}} .rk-heading' => 'text-align:{{VALUE}};' ),
		) );
		$this->add_control( 'gradient', array( 'label' => 'Gradient text', 'type' => \Elementor\Controls_Manager::SWITCHER ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control_typography();
		$this->add_control( 'color', array(
			'label'     => 'Color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .rk-heading:not(.is-gradient)' => 'color:{{VALUE}};' ),
		) );
		$this->add_control( 'g1', array(
			'label'     => 'Gradient start',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#009687',
			'condition' => array( 'gradient' => 'yes' ),
		) );
		$this->add_control( 'g2', array(
			'label'     => 'Gradient end',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#19b9a6',
			'condition' => array( 'gradient' => 'yes' ),
		) );
		$this->end_controls_section();
		$this->add_box_style( 'hdbox', 'Heading box', '.rk-heading' );
	}

	private function add_group_control_typography() {
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'typo', 'selector' => '{{WRAPPER}} .rk-heading' )
		);
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$tag = in_array( $s['tag'], array( 'h1', 'h2', 'h3', 'h4', 'div' ), true ) ? $s['tag'] : 'h2';
		$cls = 'rk-heading' . ( 'yes' === $s['gradient'] ? ' is-gradient' : '' );
		$style = '';
		if ( 'yes' === $s['gradient'] ) {
			$g1 = ! empty( $s['g1'] ) ? $s['g1'] : '#009687';
			$g2 = ! empty( $s['g2'] ) ? $s['g2'] : '#19b9a6';
			$style = ' style="background-image:linear-gradient(90deg,' . esc_attr( $g1 ) . ',' . esc_attr( $g2 ) . ');"';
		}
		echo '<' . $tag . ' class="' . esc_attr( $cls ) . '"' . $style . '>' . esc_html( $s['title'] ) . '</' . $tag . '>';
	}
}
