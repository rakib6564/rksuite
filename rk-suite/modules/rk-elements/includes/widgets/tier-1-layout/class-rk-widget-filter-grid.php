<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Filter_Grid extends RK_Widget_Base {

	public function get_name() { return 'rk-filter-grid'; }
	public function get_title() { return 'RK Filterable Grid'; }
	public function get_icon() { return 'eicon-filter'; }
	public function get_keywords() { return array( 'filter', 'portfolio', 'grid', 'isotope', 'rk' ); }

	protected function register_controls() {
		$this->add_query_controls();

		$this->start_controls_section( 'rk_filter', array(
			'label' => 'Filter',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'rk_filter_tax', array(
			'label'       => 'Filter by taxonomy',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => 'category',
			'description' => 'Taxonomy slug used to build the filter buttons (e.g. category, or a custom taxonomy).',
		) );
		$this->end_controls_section();
		/* ===== STYLE: Filter bar ===== */
		$this->start_controls_section( 'st_filterbar', array( 'label' => 'Filter Bar', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'fb_typo', 'selector' => '{{WRAPPER}} .rk-filter-btn' ) );
		$this->add_responsive_control( 'fb_align', array(
			'label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ),
			),
			'default' => 'center', 'selectors' => array( '{{WRAPPER}} .rk-filterbar' => 'justify-content:{{VALUE}};' ),
		) );
		$this->add_responsive_control( 'fb_gap', array(
			'label' => 'Spacing', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 10, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-filterbar' => 'gap:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'fb_pad', array(
			'label' => 'Button padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .rk-filter-btn' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'fb_radius', array(
			'label' => 'Button radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 30, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-filter-btn' => 'border-radius:{{SIZE}}{{UNIT}};' ),
		) );
		$this->start_controls_tabs( 'fb_states' );
		$this->start_controls_tab( 'fb_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'fb_c', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-filter-btn' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'fb_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-filter-btn' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'fb_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'fb_ch', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-filter-btn:hover' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'fb_bgh', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-filter-btn:hover' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'fb_active', array( 'label' => 'Active' ) );
		$this->add_control( 'fb_ca', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-filter-btn.is-active' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'fb_bga', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-filter-btn.is-active' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_responsive_control( 'fg_gap', array(
			'label' => 'Grid gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .rk-grid' => 'gap:{{SIZE}}{{UNIT}};' ), 'separator' => 'before',
		) );
		$this->end_controls_section();

		$this->add_box_style( 'fgbox', 'Card box', '.rk-card' );
	}

	protected function rk_render() {
		$s   = $this->get_settings_for_display();
		$tax = isset( $s['rk_filter_tax'] ) && $s['rk_filter_tax'] ? sanitize_key( $s['rk_filter_tax'] ) : 'category';
		$q   = new WP_Query( RK_Widget_Controls::query_args( $s ) );
		if ( ! $q->have_posts() ) { echo '<div class="rk-empty">No items found.</div>'; return; }

		// collect terms present
		$terms = array();
		$rows = array();
		while ( $q->have_posts() ) {
			$q->the_post();
			$slugs = array();
			if ( taxonomy_exists( $tax ) ) {
				$obj_terms = get_the_terms( get_the_ID(), $tax );
				if ( $obj_terms && ! is_wp_error( $obj_terms ) ) {
					foreach ( $obj_terms as $t ) { $slugs[] = $t->slug; $terms[ $t->slug ] = $t->name; }
				}
			}
			ob_start();
			$this->render_post_card( isset( $s['rk_show_excerpt'] ) ? $s['rk_show_excerpt'] : 'yes' );
			$rows[] = array( 'html' => ob_get_clean(), 'slugs' => $slugs );
		}
		wp_reset_postdata();

		echo '<div class="rk-filterwrap" data-rk-filter>';
		if ( ! empty( $terms ) ) {
			echo '<div class="rk-filterbar"><button class="rk-filter-btn is-active" data-filter="*">All</button>';
			foreach ( $terms as $slug => $name ) {
				echo '<button class="rk-filter-btn" data-filter="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</button>';
			}
			echo '</div>';
		}
		echo '<div class="rk-grid rk-filter-grid">';
		foreach ( $rows as $row ) {
			echo '<div class="rk-filter-item" data-terms="' . esc_attr( implode( ' ', $row['slugs'] ) ) . '">' . $row['html'] . '</div>';
		}
		echo '</div></div>';
	}
}
