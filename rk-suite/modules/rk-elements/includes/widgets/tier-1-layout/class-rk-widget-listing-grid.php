<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Listing_Grid extends RK_Widget_Base {

	public function get_name() { return 'rk-listing-grid'; }
	public function get_title() { return 'RK Listing Grid'; }
	public function get_icon() { return 'eicon-posts-grid'; }
	public function get_keywords() { return array( 'grid', 'posts', 'listing', 'cpt', 'rk' ); }

	protected function register_controls() {
		$this->add_query_controls();

		if ( class_exists( 'RK_Query_Builder' ) ) {
			$choices = array( '' => '— none (use above) —' );
			foreach ( RK_Query_Builder::choices() as $qid => $qname ) { $choices[ $qid ] = $qname; }
			$this->start_controls_section( 'rk_saved_query', array( 'label' => 'Saved Query', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
			$this->add_control( 'rk_use_query', array(
				'label'       => 'Use a saved query',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $choices,
				'description' => 'When set, overrides the Query tab.',
			) );
			$this->end_controls_section();
		}

		$this->start_controls_section( 'rk_style', array(
			'label' => 'Card style',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'rk_gap', array(
			'label'      => 'Gap',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'default'    => array( 'size' => 24, 'unit' => 'px' ),
			'selectors'  => array( '{{WRAPPER}} .rk-grid' => 'gap:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'rk_title_color', array(
			'label'     => 'Title color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .rk-card-title a' => 'color:{{VALUE}};' ),
		) );
		$this->add_control( 'rk_radius', array(
			'label'      => 'Card radius',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .rk-card' => 'border-radius:{{SIZE}}{{UNIT}};overflow:hidden;' ),
		) );
		$this->end_controls_section();
		$this->add_box_style( 'lgbox', 'Card box', '.rk-card' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$show_excerpt = isset( $s['rk_show_excerpt'] ) ? $s['rk_show_excerpt'] : 'yes';

		if ( ! empty( $s['rk_use_query'] ) && class_exists( 'RK_Query_Builder' ) ) {
			$posts = RK_Query_Builder::results( $s['rk_use_query'] );
			if ( empty( $posts ) ) { echo '<div class="rk-empty">No items found.</div>'; return; }
			echo '<div class="rk-grid rk-listing-grid">';
			global $post;
			foreach ( $posts as $post ) {
				if ( ! is_a( $post, 'WP_Post' ) ) { continue; }
				setup_postdata( $post );
				$this->render_post_card( $show_excerpt );
			}
			wp_reset_postdata();
			echo '</div>';
			return;
		}

		$q = new WP_Query( RK_Widget_Controls::query_args( $s ) );
		if ( ! $q->have_posts() ) { echo '<div class="rk-empty">No items found.</div>'; return; }
		echo '<div class="rk-grid rk-listing-grid">';
		while ( $q->have_posts() ) { $q->the_post(); $this->render_post_card( $show_excerpt ); }
		echo '</div>';
		wp_reset_postdata();
	}
}
