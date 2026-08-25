<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Team extends RK_Widget_Base {

	public function get_name() { return 'rk-team'; }
	public function get_title() { return 'RK Team Member'; }
	public function get_icon() { return 'eicon-person'; }
	public function get_keywords() { return array( 'team', 'member', 'person', 'staff', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Member', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'image', array( 'label' => 'Photo', 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->add_control( 'name', array( 'label' => 'Name', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Alex Morgan' ) );
		$this->add_control( 'role', array( 'label' => 'Role', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Lead Designer' ) );
		$this->add_control( 'bio', array( 'label' => 'Bio', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Crafting delightful interfaces for a decade.' ) );

		$rep = new \Elementor\Repeater();
		$rep->add_control( 'label', array( 'label' => 'Label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'in' ) );
		$rep->add_control( 'link', array( 'label' => 'URL', 'type' => \Elementor\Controls_Manager::URL, 'default' => array( 'url' => '#' ) ) );
		$this->add_control( 'social', array(
			'label'       => 'Social links',
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'default'     => array( array( 'label' => 'in', 'link' => array( 'url' => '#' ) ) ),
			'title_field' => '{{{ label }}}',
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent', array( 'label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#009687', 'selectors' => array( '{{WRAPPER}} .rk-team-role' => 'color:{{VALUE}};', '{{WRAPPER}} .rk-team-social a:hover' => 'background:{{VALUE}};border-color:{{VALUE}};color:#fff;' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'tmbox', 'Card box', '.rk-team' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$img = isset( $s['image']['url'] ) && $s['image']['url'] ? $s['image']['url'] : '';
		echo '<div class="rk-team">';
		if ( $img ) { echo '<div class="rk-team-photo"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $s['name'] ) . '" /></div>'; }
		echo '<div class="rk-team-body">';
		echo '<h3 class="rk-team-name">' . esc_html( $s['name'] ) . '</h3>';
		echo '<div class="rk-team-role">' . esc_html( $s['role'] ) . '</div>';
		if ( $s['bio'] ) { echo '<p class="rk-team-bio">' . esc_html( $s['bio'] ) . '</p>'; }
		if ( ! empty( $s['social'] ) ) {
			echo '<div class="rk-team-social">';
			foreach ( $s['social'] as $soc ) {
				$url = isset( $soc['link']['url'] ) && $soc['link']['url'] ? $soc['link']['url'] : '#';
				$target = ! empty( $soc['link']['is_external'] ) ? ' target="_blank" rel="noopener"' : '';
				echo '<a href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $soc['label'] ) . '</a>';
			}
			echo '</div>';
		}
		echo '</div></div>';
	}
}
