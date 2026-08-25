<?php
/**
 * RK_Widget_Base — shared base for every RK Elements widget. Puts widgets in the
 * RK Elements category and provides a reusable Query controls section for the
 * layout widgets (listing grid, carousel, filterable grid).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( '\Elementor\Widget_Base' ) ) { return; }

abstract class RK_Widget_Base extends \Elementor\Widget_Base {

	/** Load the shared RK Elements bundle only on pages that use an RK widget. */
	public function get_style_depends()  { return array( 'rk-elements' ); }
	public function get_script_depends() { return array( 'rk-elements' ); }


	public function get_categories() { return array( 'rk-elements' ); }

	/**
	 * Elementor calls render(); we wrap each widget's rk_render() so a runtime
	 * error in one widget can never white-screen the page — it is logged and the
	 * widget renders nothing (with a hint for admins) instead.
	 */
	protected function render() {
		try {
			if ( method_exists( $this, 'rk_render' ) ) { $this->rk_render(); }
		} catch ( \Throwable $e ) {
			if ( function_exists( 'rk_suite_log' ) ) {
				rk_suite_log( '[RK Elements] render error in ' . $this->get_name() . ': ' . $e->getMessage() );
			}
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<!-- RK Elements: "' . esc_html( $this->get_name() ) . '" failed to render: ' . esc_html( $e->getMessage() ) . ' -->';
			}
		}
	}
	public function get_icon() { return 'eicon-flash'; }

	/** Reusable Query section for layout widgets. */
	protected function add_query_controls() {
		$this->start_controls_section( 'rk_query', array(
			'label' => 'Query',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'rk_post_type', array(
			'label'   => 'Source',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'post',
			'options' => RK_Widget_Controls::post_type_choices(),
		) );
		$this->add_control( 'rk_posts_per_page', array(
			'label'   => 'Items',
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 6,
			'min'     => 1,
			'max'     => 48,
		) );
		$this->add_responsive_control( 'rk_columns', array(
			'label'   => 'Columns',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '3',
			'tablet_default' => '2',
			'mobile_default' => '1',
			'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'selectors' => array(
				'{{WRAPPER}} .rk-grid' => 'grid-template-columns:repeat({{VALUE}},1fr);',
			),
		) );
		$this->add_control( 'rk_orderby', array(
			'label'   => 'Order by',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'date',
			'options' => array( 'date' => 'Date', 'title' => 'Title', 'menu_order' => 'Menu order', 'rand' => 'Random' ),
		) );
		$this->add_control( 'rk_order', array(
			'label'   => 'Order',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'DESC',
			'options' => array( 'DESC' => 'Descending', 'ASC' => 'Ascending' ),
		) );
		$this->add_control( 'rk_show_excerpt', array(
			'label'        => 'Show excerpt',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
		) );

		$this->end_controls_section();
	}

	/** Small helper: render a card for a post inside the loop. */
	protected function render_post_card( $show_excerpt ) {
		$thumb = get_the_post_thumbnail( get_the_ID(), 'medium_large', array( 'class' => 'rk-card-img' ) );
		echo '<article class="rk-card">';
		if ( $thumb ) {
			echo '<a class="rk-card-media" href="' . esc_url( get_permalink() ) . '">' . $thumb . '</a>';
		}
		echo '<div class="rk-card-body">';
		echo '<h3 class="rk-card-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
		if ( 'yes' === $show_excerpt ) {
			echo '<p class="rk-card-excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), 22 ) ) . '</p>';
		}
		echo '<a class="rk-card-link" href="' . esc_url( get_permalink() ) . '">Read more →</a>';
		echo '</div></article>';
	}

	/**
	 * Reusable "Box design" style section: background (classic + gradient),
	 * padding, border, radius and box-shadow for a given inner element. Gives
	 * every RK widget deep, consistent per-element customization.
	 */
	protected function add_box_style( $id, $label, $selector ) {
		if ( ! class_exists( '\\Elementor\\Group_Control_Background' ) ) { return; }
		$sel = '{{WRAPPER}} ' . $selector;
		$this->start_controls_section( $id, array( 'label' => $label, 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), array(
			'name' => $id . '_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => $sel,
		) );
		$this->add_responsive_control( $id . '_pad', array(
			'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', '%' ),
			'selectors' => array( $sel => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => $id . '_bd', 'selector' => $sel ) );
		$this->add_responsive_control( $id . '_radius', array(
			'label' => 'Border radius', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ),
			'selectors' => array( $sel => 'border-radius:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => $id . '_shadow', 'selector' => $sel ) );
		$this->add_responsive_control( $id . '_margin', array(
			'label' => 'Margin', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', '%' ),
			'selectors' => array( $sel => 'margin:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}
}
