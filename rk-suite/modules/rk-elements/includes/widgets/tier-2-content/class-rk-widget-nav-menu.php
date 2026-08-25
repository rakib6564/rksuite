<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

/**
 * RK Nav Menu — a professional navigation widget driven by a WordPress menu
 * (Appearance → Menus). Dropdown or full-width mega submenus, hover or click
 * open, four pointer styles, deep per-element styling, a mobile drawer, and
 * SEO-friendly semantic markup (nav > ul > li > a, aria-current). No Pro.
 * Menu CSS/JS load only on pages that use this widget (get_*_depends).
 */
class RK_Widget_Nav_Menu extends RK_Widget_Base {

	public function get_name()           { return 'rk-nav-menu'; }
	public function get_title()          { return 'RK Nav Menu'; }
	public function get_icon()           { return 'eicon-nav-menu'; }
	public function get_keywords()       { return array( 'menu', 'nav', 'navigation', 'wordpress menu', 'dropdown', 'mega', 'rk' ); }
	public function get_style_depends()  { return array( 'rk-menu' ); }
	public function get_script_depends() { return array( 'rk-menu' ); }

	protected function register_controls() {

		/* ===================== CONTENT: Layout ===================== */
		$this->start_controls_section( 'sec_menu', array( 'label' => 'Layout', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'menu', array(
			'label' => 'Menu', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => RK_Widget_Controls::nav_menu_choices(), 'default' => key( RK_Widget_Controls::nav_menu_choices() ),
			'description' => 'Manage items in Appearance → Menus.',
		) );
		$this->add_control( 'layout', array(
			'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'horizontal',
			'options' => array( 'horizontal' => 'Horizontal', 'vertical' => 'Vertical' ), 'prefix_class' => 'rk-nav--',
		) );
		$this->add_responsive_control( 'align', array(
			'label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ),
				'space-between' => array( 'title' => 'Justify', 'icon' => 'eicon-h-align-stretch' ),
			),
			'default' => 'flex-start', 'selectors' => array( '{{WRAPPER}} .rk-nav-list' => 'justify-content:{{VALUE}};' ),
		) );
		$this->add_control( 'submenu_style', array(
			'label' => 'Submenu style', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'dropdown',
			'options' => array( 'dropdown' => 'Dropdown', 'mega' => 'Mega (full width)' ), 'prefix_class' => 'rk-nav-sub--',
		) );
		$this->add_control( 'open_on', array(
			'label' => 'Open submenu on', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'hover',
			'options' => array( 'hover' => 'Hover', 'click' => 'Click' ),
		) );
		$this->add_control( 'pointer', array(
			'label' => 'Pointer', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'underline',
			'options' => array(
				'none' => 'None', 'underline' => 'Underline', 'overline' => 'Overline',
				'highlight' => 'Highlight', 'framed' => 'Framed', 'background' => 'Background', 'text' => 'Text color',
			),
			'prefix_class' => 'rk-nav-pt--',
		) );
		$this->add_control( 'animation', array(
			'label' => 'Animation', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'slide',
			'options' => array( 'slide' => 'Slide', 'fade' => 'Fade', 'grow' => 'Grow' ),
			'prefix_class' => 'rk-nav-anim--',
			'condition' => array( 'pointer' => array( 'underline', 'overline' ) ),
		) );
		$this->add_control( 'indicator', array(
			'label' => 'Submenu indicator', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes',
		) );
		$this->add_control( 'indicator_icon', array(
			'label' => 'Indicator icon', 'type' => \Elementor\Controls_Manager::ICONS,
			'description' => 'Leave empty for the default caret.', 'condition' => array( 'indicator' => 'yes' ),
		) );
		$this->end_controls_section();

		/* ===================== CONTENT: Mobile ===================== */
		$this->start_controls_section( 'sec_mobile', array( 'label' => 'Mobile Dropdown', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'breakpoint', array(
			'label' => 'Mobile menu below (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 1024, 'min' => 0, 'max' => 1600,
		) );
		$this->add_control( 'btn_fullwidth', array( 'label' => 'Full width', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'prefix_class' => 'rk-nav-fw--', 'description' => 'Stretch the mobile dropdown to full width.' ) );
		$this->add_control( 'mobile_text_align', array(
			'label' => 'Text align', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'left',
			'options' => array( 'left' => 'Aside (left)', 'center' => 'Center', 'right' => 'Right' ), 'prefix_class' => 'rk-nav-mtext--',
		) );
		$this->add_control( 'toggle_label', array( 'label' => 'Toggle button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Menu' ) );
		$this->add_control( 'toggle_icon', array(
			'label' => 'Toggle icon', 'type' => \Elementor\Controls_Manager::ICONS,
			'default' => array( 'value' => 'eicon-menu-bar', 'library' => 'eicons' ),
		) );
		$this->add_control( 'toggle_close_icon', array(
			'label' => 'Toggle close icon', 'type' => \Elementor\Controls_Manager::ICONS, 'description' => 'Optional — swaps in when open.',
		) );
		$this->add_responsive_control( 'toggle_align', array(
			'label' => 'Toggle align', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ),
			),
			'default' => 'flex-start', 'prefix_class' => 'rk-nav-talign--',
		) );
		$this->end_controls_section();

		/* ===================== STYLE: Main menu ===================== */
		$this->start_controls_section( 'st_main', array( 'label' => 'Main Menu', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'menu_typo', 'selector' => '{{WRAPPER}} .rk-nav-list > li > a' ) );
		$this->start_controls_tabs( 'main_states' );
		$this->start_controls_tab( 'main_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'c_link', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#2a2230', 'selectors' => array( '{{WRAPPER}} .rk-nav-list > li > a' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'main_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'c_hover', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-nav-list > li > a:hover' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'main_active', array( 'label' => 'Active' ) );
		$this->add_control( 'c_active', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-nav-list > .current-menu-item > a' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control( 'divider', array( 'label' => 'Divider', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'prefix_class' => 'rk-nav-div--', 'separator' => 'before' ) );
		$this->add_control( 'c_divider', array( 'label' => 'Divider color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#e2e5ea', 'condition' => array( 'divider' => 'yes' ), 'selectors' => array( '{{WRAPPER}} .rk-nav' => '--rk-nav-mdiv:{{VALUE}};' ) ) );
		$this->add_control( 'pointer_weight', array(
			'label' => 'Pointer width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 1, 'max' => 8 ) ), 'default' => array( 'size' => 2, 'unit' => 'px' ),
			'condition' => array( 'pointer' => array( 'underline', 'overline', 'framed' ) ), 'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .rk-nav-list > li > a::after' => 'height:{{SIZE}}{{UNIT}};', '{{WRAPPER}}.rk-nav-pt--framed .rk-nav-list > li > a' => 'border-width:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'c_pointer', array( 'label' => 'Pointer color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}}' => '--rk-nav-pointer:{{VALUE}};' ), 'condition' => array( 'pointer!' => array( 'none', 'text' ) ) ) );
		$this->add_responsive_control( 'item_pad', array(
			'label' => 'Item padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .rk-nav-list > li > a' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'item_gap', array(
			'label' => 'Space between', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'default' => array( 'size' => 26, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-nav-list' => 'gap:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'item_radius', array(
			'label' => 'Item radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .rk-nav-list > li > a' => 'border-radius:{{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ===================== STYLE: Dropdown ===================== */
		$this->start_controls_section( 'st_drop', array( 'label' => 'Dropdown', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'sub_typo', 'selector' => '{{WRAPPER}} .sub-menu a, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list a' ) );
		$this->start_controls_tabs( 'sub_states' );
		$this->start_controls_tab( 'sub_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'c_sub_text', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#2a2230', 'selectors' => array( '{{WRAPPER}} .sub-menu a, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list > li > a' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_sub_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .sub-menu, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'sub_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'c_sub_htext', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .sub-menu a:hover, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list a:hover' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'c_sub_hbg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f2f7f6', 'selectors' => array( '{{WRAPPER}} .sub-menu a:hover, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list a:hover' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'sub_active', array( 'label' => 'Active' ) );
		$this->add_control( 'c_sub_active', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .sub-menu .current-menu-item > a, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list .current-menu-item > a' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'sub_border', 'selector' => '{{WRAPPER}} .sub-menu, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list', 'separator' => 'before' ) );
		$this->add_responsive_control( 'sub_radius', array(
			'label' => 'Border radius', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'default' => array( 'top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'px', 'isLinked' => true ),
			'selectors' => array( '{{WRAPPER}} .sub-menu, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list' => 'border-radius:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'sub_shadow', 'selector' => '{{WRAPPER}} .sub-menu, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list' ) );
		$this->add_responsive_control( 'sub_pad', array(
			'label' => 'Item padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ),
			'default' => array( 'top' => 12, 'right' => 14, 'bottom' => 12, 'left' => 14, 'unit' => 'px', 'isLinked' => false ),
			'selectors' => array( '{{WRAPPER}} .sub-menu a, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-list a' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'c_sub_divider', array( 'label' => 'Item divider', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f0f2f5', 'selectors' => array( '{{WRAPPER}} .sub-menu li + li, {{WRAPPER}} .rk-nav.is-mobile .rk-nav-item + .rk-nav-item' => 'border-top:1px solid {{VALUE}};' ) ) );
		$this->add_control( 'sub_width', array(
			'label' => 'Min width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 140, 'max' => 400 ) ), 'default' => array( 'size' => 210, 'unit' => 'px' ),
			'condition' => array( 'submenu_style' => 'dropdown' ), 'selectors' => array( '{{WRAPPER}} .sub-menu' => 'min-width:{{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ===================== STYLE: Toggle button ===================== */
		$this->start_controls_section( 'st_toggle', array( 'label' => 'Toggle Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->start_controls_tabs( 'toggle_states' );
		$this->start_controls_tab( 'toggle_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'c_btn_text', array( 'label' => 'Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
			'selectors' => array(
				'{{WRAPPER}} .rk-nav-toggle' => 'color:{{VALUE}};',
				'{{WRAPPER}} .rk-nav-burger, {{WRAPPER}} .rk-nav-burger::before, {{WRAPPER}} .rk-nav-burger::after' => 'background:{{VALUE}};',
				'{{WRAPPER}} .rk-nav-ti, {{WRAPPER}} .rk-nav-ti i' => 'color:{{VALUE}};',
				'{{WRAPPER}} .rk-nav-ti svg' => 'fill:{{VALUE}};',
			) ) );
		$this->add_control( 'c_btn_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#0e9384', 'selectors' => array( '{{WRAPPER}} .rk-nav-toggle' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'toggle_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'c_btn_text_h', array( 'label' => 'Color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array(
			'{{WRAPPER}} .rk-nav-toggle:hover' => 'color:{{VALUE}};',
			'{{WRAPPER}} .rk-nav-toggle:hover .rk-nav-ti, {{WRAPPER}} .rk-nav-toggle:hover .rk-nav-ti i' => 'color:{{VALUE}};',
			'{{WRAPPER}} .rk-nav-toggle:hover .rk-nav-ti svg' => 'fill:{{VALUE}};',
		) ) );
		$this->add_control( 'c_btn_bg_h', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-nav-toggle:hover' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control( 'toggle_icon_size', array(
			'label' => 'Icon size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 60 ) ), 'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .rk-nav-ti' => 'font-size:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'btn_border', 'selector' => '{{WRAPPER}} .rk-nav-toggle' ) );
		$this->add_control( 'btn_radius', array(
			'label' => 'Border radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 10, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rk-nav-toggle' => 'border-radius:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'btn_pad', array(
			'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .rk-nav-toggle' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	protected function rk_render() {
		$s    = $this->get_settings_for_display();
		$tree = RK_Widget_Controls::nav_menu_tree( isset( $s['menu'] ) ? (int) $s['menu'] : 0 );
		$bp   = isset( $s['breakpoint'] ) ? (int) $s['breakpoint'] : 1024;
		$tgl  = isset( $s['toggle_label'] ) ? $s['toggle_label'] : 'Menu';
		$open = ( isset( $s['open_on'] ) && 'click' === $s['open_on'] ) ? 'click' : 'hover';
		$ind  = ( ! isset( $s['indicator'] ) || 'yes' === $s['indicator'] );

		if ( ! $tree ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<div class="rk-nav rk-nav-empty">Pick a menu in the widget settings, or build one in <strong>Appearance → Menus</strong>.</div>';
			}
			return;
		}

		$toggle_icon = $this->icon_html( isset( $s['toggle_icon'] ) ? $s['toggle_icon'] : array() );
		$close_icon  = $this->icon_html( isset( $s['toggle_close_icon'] ) ? $s['toggle_close_icon'] : array() );
		if ( $toggle_icon ) {
			$burger = '<span class="rk-nav-ti rk-ti-open">' . $toggle_icon . '</span>';
			if ( $close_icon ) { $burger .= '<span class="rk-nav-ti rk-ti-close">' . $close_icon . '</span>'; }
		} else {
			$burger = '<span class="rk-nav-burger"></span>';
		}

		$caret = '';
		if ( $ind ) {
			$ci = $this->icon_html( isset( $s['indicator_icon'] ) ? $s['indicator_icon'] : array() );
			$caret = $ci ? '<span class="rk-nav-caret has-icon" aria-hidden="true">' . $ci . '</span>' : '<span class="rk-nav-caret" aria-hidden="true"></span>';
		}

		echo '<nav class="rk-nav" data-rk-nav data-bp="' . esc_attr( $bp ) . '" data-trigger="' . esc_attr( $open ) . '" aria-label="Primary">';
		echo '<button class="rk-nav-toggle" type="button" aria-expanded="false">' . $burger . '<span class="rk-nav-toggle-txt">' . esc_html( $tgl ) . '</span></button>';
		echo '<ul class="rk-nav-list">';
		foreach ( $tree as $node ) { $this->render_node( $node, $ind, $caret ); }
		echo '</ul></nav>';
	}

	/** Render an Elementor icon control to HTML, or '' when none chosen. */
	private function icon_html( $icon ) {
		if ( empty( $icon['value'] ) || ! class_exists( '\Elementor\Icons_Manager' ) ) { return ''; }
		ob_start();
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return trim( (string) ob_get_clean() );
	}

	private function render_node( $node, $ind, $caret = '' ) {
		$it       = $node['item'];
		$children = $node['children'];
		$url      = ! empty( $it->url ) ? esc_url( $it->url ) : '#';
		$title    = esc_html( $it->title );
		$classes  = array_filter( (array) ( isset( $it->classes ) ? $it->classes : array() ) );
		$is_cur   = ( ! empty( $it->object_id ) && (int) $it->object_id === (int) get_queried_object_id() );
		$li_class = 'rk-nav-item';
		if ( $children ) { $li_class .= ' has-sub'; }
		if ( $is_cur )   { $li_class .= ' current-menu-item'; }
		if ( $classes )  { $li_class .= ' ' . esc_attr( implode( ' ', $classes ) ); }

		echo '<li class="' . $li_class . '">';
		$target = ! empty( $it->target ) ? ' target="' . esc_attr( $it->target ) . '" rel="noopener"' : '';
		$aria   = $is_cur ? ' aria-current="page"' : '';
		echo '<a href="' . $url . '"' . $target . $aria . '><span class="rk-nav-txt">' . $title . '</span>';
		if ( $children && $ind ) { echo $caret; }
		echo '</a>';
		if ( $children ) {
			echo '<button class="rk-sub-toggle" type="button" aria-label="Toggle submenu"></button>';
			echo '<ul class="sub-menu">';
			foreach ( $children as $c ) { $this->render_node( $c, $ind, $caret ); }
			echo '</ul>';
		}
		echo '</li>';
	}
}
