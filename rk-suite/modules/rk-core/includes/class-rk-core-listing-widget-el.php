<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\Elementor\Widget_Base' ) ) { return; }

class RK_Core_Listing_Widget_El extends \Elementor\Widget_Base {

	public function get_name()      { return 'rk_listing'; }
	public function get_title()     { return 'RK Listing'; }
	public function get_icon()      { return 'eicon-posts-grid'; }
	public function get_categories(){ return array( 'rk-suite' ); }
	public function get_keywords()  { return array( 'listing', 'grid', 'loop', 'rk', 'posts' ); }

	protected function register_controls() {
		$this->start_controls_section( 'sec', array( 'label' => 'Listing' ) );

		$opts = array( '' => '— Select a listing —' );
		if ( class_exists( 'RK_Core_Listings' ) ) {
			foreach ( RK_Core_Listings::all() as $l ) { $opts[ $l->ID ] = $l->post_title; }
		}
		$this->add_control( 'listing_id', array(
			'label'   => 'Listing template',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $opts,
			'default' => '',
		) );
		$this->add_control( 'count', array(
			'label'   => 'Items to show',
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'min'     => 1, 'max' => 60,
			'description' => 'Leave blank to use the listing default.',
		) );
		$this->add_responsive_control( 'columns', array(
			'label'   => 'Columns',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( '' => 'Default', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ),
			'default' => '',
			'selectors' => array( '{{WRAPPER}} .rk-listing-grid' => 'grid-template-columns:repeat({{VALUE}},1fr);' ),
		) );
		$this->add_control( 'gap', array(
			'label'   => 'Gap (px)',
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default' => array( 'size' => 24 ),
			'selectors' => array( '{{WRAPPER}} .rk-listing-grid' => 'gap:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'hint', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => 'Build listing templates under <strong>RK &rsaquo; RK Core &rsaquo; Listings</strong>.',
			'content_classes' => 'elementor-descriptor',
		) );
		$this->end_controls_section();
	}

	protected function rk_render() {
		$s   = $this->get_settings_for_display();
		$lid = isset( $s['listing_id'] ) ? (int) $s['listing_id'] : 0;
		if ( ! $lid || ! class_exists( 'RK_Core_Listings' ) ) { echo '<div class="rk-listing-empty">Select a listing template.</div>'; return; }
		$listing = RK_Core_Listings::get( $lid );
		if ( ! $listing ) { echo '<div class="rk-listing-empty">Listing not found.</div>'; return; }

		$set   = RK_Core_Listings::settings_of( $lid );
		$count = ! empty( $s['count'] ) ? (int) $s['count'] : 0;
		$items = RK_Core_Listings::query_items( $lid, $count );
		if ( empty( $items ) ) { echo '<div class="rk-listing-empty">No items to show.</div>'; return; }

		$cols = ! empty( $s['columns'] ) ? (int) $s['columns'] : (int) $set['columns'];
		$rf = ( 'repeater' === $set['source'] ) ? $set['repeater'] : '';
		echo '<div class="rk-listing-grid" style="display:grid;grid-template-columns:repeat(' . esc_attr( $cols ) . ',1fr);gap:24px;">';
		foreach ( $items as $item ) {
			echo '<div class="rk-listing-item">' . RK_Core_Listings::render_item( $lid, $item, $rf ) . '</div>';
		}
		echo '</div>';
	}

	protected function render() {
		try { $this->rk_render(); }
		catch ( \Throwable $e ) { echo '<div class="rk-listing-empty">Listing could not render.</div>'; }
	}
}
