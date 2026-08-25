<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Pricing extends RK_Widget_Base {

	public function get_name() { return 'rk-pricing'; }
	public function get_title() { return 'RK Pricing Table'; }
	public function get_icon() { return 'eicon-price-table'; }
	public function get_keywords() { return array( 'pricing', 'plan', 'table', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Plan', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'plan', array( 'label' => 'Plan name', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Pro' ) );
		$this->add_control( 'currency', array( 'label' => 'Currency', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '$' ) );
		$this->add_control( 'price', array( 'label' => 'Price', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '29' ) );
		$this->add_control( 'period', array( 'label' => 'Period', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '/mo' ) );
		$this->add_control( 'featured', array( 'label' => 'Featured', 'type' => \Elementor\Controls_Manager::SWITCHER ) );

		$rep = new \Elementor\Repeater();
		$rep->add_control( 'feature', array( 'label' => 'Feature', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Feature line' ) );
		$rep->add_control( 'enabled', array( 'label' => 'Included', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'features', array(
			'label'       => 'Features',
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'default'     => array(
				array( 'feature' => 'Everything in Free', 'enabled' => 'yes' ),
				array( 'feature' => 'Priority support', 'enabled' => 'yes' ),
				array( 'feature' => 'Team seats', 'enabled' => 'yes' ),
			),
			'title_field' => '{{{ feature }}}',
		) );
		$this->add_control( 'btn_text', array( 'label' => 'Button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Choose plan' ) );
		$this->add_control( 'btn_link', array( 'label' => 'Button link', 'type' => \Elementor\Controls_Manager::URL, 'default' => array( 'url' => '#' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent', array( 'label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#009687', 'selectors' => array(
			'{{WRAPPER}} .rk-pricing.is-featured' => 'border-color:{{VALUE}};',
			'{{WRAPPER}} .rk-pricing-price' => 'color:{{VALUE}};',
			'{{WRAPPER}} .rk-pricing-btn' => 'background:{{VALUE}};',
		) ) );
		$this->end_controls_section();
		$this->add_box_style( 'prbox', 'Plan box', '.rk-pricing-plan' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$cls = 'rk-pricing' . ( 'yes' === $s['featured'] ? ' is-featured' : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		if ( 'yes' === $s['featured'] ) { echo '<div class="rk-pricing-badge">Most popular</div>'; }
		echo '<div class="rk-pricing-plan">' . esc_html( $s['plan'] ) . '</div>';
		echo '<div class="rk-pricing-price"><span class="rk-pricing-cur">' . esc_html( $s['currency'] ) . '</span>' . esc_html( $s['price'] ) . '<span class="rk-pricing-period">' . esc_html( $s['period'] ) . '</span></div>';
		echo '<ul class="rk-pricing-features">';
		if ( ! empty( $s['features'] ) ) {
			foreach ( $s['features'] as $f ) {
				$on = 'yes' === $f['enabled'];
				echo '<li class="' . ( $on ? 'on' : 'off' ) . '"><span class="rk-tick">' . ( $on ? '✓' : '✕' ) . '</span> ' . esc_html( $f['feature'] ) . '</li>';
			}
		}
		echo '</ul>';
		$url = isset( $s['btn_link']['url'] ) && $s['btn_link']['url'] ? $s['btn_link']['url'] : '#';
		$target = ! empty( $s['btn_link']['is_external'] ) ? ' target="_blank" rel="noopener"' : '';
		echo '<a class="rk-pricing-btn" href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $s['btn_text'] ) . '</a>';
		echo '</div>';
	}
}
