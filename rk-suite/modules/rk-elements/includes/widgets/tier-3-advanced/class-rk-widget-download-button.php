<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Download_Button extends RK_Widget_Base {
	public function get_name()     { return 'rk-download-button'; }
	public function get_title()    { return 'RK Download Button'; }
	public function get_icon()     { return 'eicon-download-button'; }
	public function get_keywords() { return array( 'download', 'button', 'file', 'rk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'c', array( 'label' => 'Download', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'text', array( 'label' => 'Button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Download' ) );
		$this->add_control( 'file', array( 'label' => 'File', 'type' => \Elementor\Controls_Manager::MEDIA, 'media_types' => array( 'application', 'image', 'video', 'audio' ), 'description' => 'Pick a media file, or set a URL below.' ) );
		$this->add_control( 'file_url', array( 'label' => 'or File URL', 'type' => \Elementor\Controls_Manager::URL, 'placeholder' => 'https://…/file.pdf', 'show_external' => false ) );
		$this->add_control( 'filename', array( 'label' => 'Save-as filename', 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => 'brochure.pdf' ) );
		$this->add_control( 'icon', array( 'label' => 'Icon', 'type' => \Elementor\Controls_Manager::ICONS, 'default' => array( 'value' => 'eicon-download', 'library' => 'eicons' ) ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array( 'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ), 'center' => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ), 'flex-end' => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ) ),
			'default' => 'flex-start', 'selectors' => array( '{{WRAPPER}} .rk-dl' => 'justify-content:{{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 's', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typo', 'selector' => '{{WRAPPER}} .rk-dl-btn' ) );
		$this->add_control( 'c_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-dl-btn' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'c_text', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-dl-btn' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_bg_h', array( 'label' => 'Hover background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0b7d70', 'selectors' => array( '{{WRAPPER}} .rk-dl-btn:hover' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'radius', array( 'label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'default' => array( 'size' => 10 ), 'selectors' => array( '{{WRAPPER}} .rk-dl-btn' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'pad', array( 'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'default' => array( 'top' => 12, 'right' => 24, 'bottom' => 12, 'left' => 24, 'unit' => 'px', 'isLinked' => false ), 'selectors' => array( '{{WRAPPER}} .rk-dl-btn' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'dlbox', 'Button box', '.rk-dl-btn' );
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();
		$url = ! empty( $s['file']['url'] ) ? $s['file']['url'] : ( ! empty( $s['file_url']['url'] ) ? $s['file_url']['url'] : '' );
		if ( ! $url ) { if ( current_user_can( 'edit_posts' ) ) { echo '<div class="rk-dl"><em>Pick a file or set a URL.</em></div>'; } return; }
		$dl = ! empty( $s['filename'] ) ? ' download="' . esc_attr( $s['filename'] ) . '"' : ' download';
		echo '<div class="rk-dl"><a class="rk-dl-btn" href="' . esc_url( $url ) . '"' . $dl . ' rel="noopener">';
		if ( ! empty( $s['icon']['value'] ) ) { echo '<span class="rk-dl-ico">'; \Elementor\Icons_Manager::render_icon( $s['icon'], array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '<span>' . esc_html( $s['text'] ) . '</span></a></div>';
	}
}
