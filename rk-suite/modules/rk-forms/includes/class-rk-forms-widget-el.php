<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\Elementor\Widget_Base' ) ) { return; }

class RK_Forms_Widget_El extends \Elementor\Widget_Base {

	public function get_name()      { return 'rk_form'; }
	public function get_title()     { return 'RK Form'; }
	public function get_icon()      { return 'eicon-form-horizontal'; }
	public function get_categories(){ return array( 'rk-suite' ); }
	public function get_keywords()  { return array( 'form', 'contact', 'rk', 'submission' ); }
	public function get_style_depends()  { return array( 'rk-forms' ); }
	public function get_script_depends() { return array( 'rk-forms' ); }

	protected function register_controls() {
		$this->start_controls_section( 'sec', array( 'label' => 'Form' ) );

		$opts = array( '' => '— Select a form —' );
		foreach ( RK_Forms_DB::all_forms() as $f ) {
			$opts[ $f->id ] = $f->title . ( 'published' === $f->status ? '' : ' (' . $f->status . ')' );
		}
		$this->add_control( 'form_id', array(
			'label'   => 'Form',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $opts,
			'default' => '',
		) );
		$this->add_control( 'hide_title', array(
			'label'        => 'Hide form title',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
		) );
		$this->add_control( 'empty_hint', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => 'No forms yet? Create one under <strong>RK &rsaquo; Forms</strong>.',
			'content_classes' => 'elementor-descriptor',
		) );
		$this->end_controls_section();

		// Basic style hooks (accent + spacing) — full style controls in a later phase.
		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent', array(
			'label'     => 'Accent color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .rk-form-submit' => 'background:{{VALUE}};border-color:{{VALUE}}' ),
		) );
		$this->end_controls_section();
	}

	protected function rk_render() {
		$s   = $this->get_settings_for_display();
		$fid = isset( $s['form_id'] ) ? (int) $s['form_id'] : 0;
		if ( ! $fid ) { echo '<div class="rk-form-notice">Select a form in the widget settings.</div>'; return; }
		$form = RK_Forms_DB::get_form( $fid );
		if ( ! $form ) { echo '<div class="rk-form-notice">Form not found.</div>'; return; }
		if ( class_exists( 'RK_Forms' ) ) { RK_Forms::instance()->enqueue_now(); }
		echo RK_Forms_Public::render_form( $form, array( 'hide_title' => ( isset( $s['hide_title'] ) && 'yes' === $s['hide_title'] ) ) );
	}

	protected function render() {
		try { $this->rk_render(); }
		catch ( \Throwable $e ) { echo '<div class="rk-form-notice">Form could not render.</div>'; }
	}
}
