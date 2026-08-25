<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Post_Carousel extends RK_Widget_Base {

	public function get_name() { return 'rk-post-carousel'; }
	public function get_title() { return 'RK Post Carousel'; }
	public function get_icon() { return 'eicon-media-carousel'; }
	public function get_keywords() { return array( 'carousel', 'slider', 'posts', 'rk' ); }

	protected function register_controls() {
		$this->add_query_controls();

		$this->start_controls_section( 'rk_style', array(
			'label' => 'Style',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'rk_card_w', array(
			'label'     => 'Card width',
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 180, 'max' => 520 ) ),
			'default'   => array( 'size' => 300, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-carousel .rk-card' => 'flex:0 0 {{SIZE}}{{UNIT}};width:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'rk_title_color', array(
			'label'     => 'Title color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .rk-card-title a' => 'color:{{VALUE}};' ),
		) );
		$this->end_controls_section();
		$this->add_box_style( 'pcbox', 'Card box', '.rk-card' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$q = new WP_Query( RK_Widget_Controls::query_args( $s ) );
		if ( ! $q->have_posts() ) { echo '<div class="rk-empty">No items found.</div>'; return; }
		echo '<div class="rk-carousel" data-rk-carousel>';
		echo '<button class="rk-carousel-nav rk-prev" aria-label="Previous">‹</button>';
		echo '<div class="rk-carousel-track">';
		while ( $q->have_posts() ) { $q->the_post(); $this->render_post_card( isset( $s['rk_show_excerpt'] ) ? $s['rk_show_excerpt'] : 'yes' ); }
		echo '</div>';
		echo '<button class="rk-carousel-nav rk-next" aria-label="Next">›</button>';
		echo '</div>';
		wp_reset_postdata();
	}
}
