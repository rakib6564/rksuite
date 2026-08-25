<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Accordion extends RK_Widget_Base {

	public function get_name() { return 'rk-accordion'; }
	public function get_title() { return 'RK Accordion'; }
	public function get_icon() { return 'eicon-accordion'; }
	public function get_keywords() { return array( 'accordion', 'faq', 'toggle', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Items', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );

		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Question goes here' ) );
		$rep->add_control( 'content', array( 'label' => 'Content', 'type' => \Elementor\Controls_Manager::WYSIWYG, 'default' => 'Answer content goes here.' ) );
		$this->add_control( 'items', array(
			'label'       => 'Accordion items',
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'default'     => array(
				array( 'title' => 'What is included?', 'content' => 'Everything you need to get started.' ),
				array( 'title' => 'Can I cancel anytime?', 'content' => 'Yes, cancel whenever you like.' ),
			),
			'title_field' => '{{{ title }}}',
		) );
		$this->add_control( 'first_open', array( 'label' => 'Open first item', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent', array( 'label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#009687', 'selectors' => array( '{{WRAPPER}} .rk-acc-item.is-open .rk-acc-head' => 'color:{{VALUE}};', '{{WRAPPER}} .rk-acc-icon' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'acbox', 'Item box', '.rk-acc-item' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		if ( empty( $s['items'] ) ) { return; }
		echo '<div class="rk-accordion" data-rk-accordion>';
		$i = 0;
		foreach ( $s['items'] as $item ) {
			$open = ( 0 === $i && 'yes' === $s['first_open'] ) ? ' is-open' : '';
			echo '<div class="rk-acc-item' . $open . '">';
			echo '<button class="rk-acc-head" type="button">' . esc_html( $item['title'] ) . '<span class="rk-acc-icon">+</span></button>';
			echo '<div class="rk-acc-panel"><div class="rk-acc-inner">' . wp_kses_post( $item['content'] ) . '</div></div>';
			echo '</div>';
			$i++;
		}
		echo '</div>';
	}
}
