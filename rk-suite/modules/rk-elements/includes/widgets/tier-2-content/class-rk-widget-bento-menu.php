<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

/**
 * RK Bento Menu — a fullscreen "bento" overlay menu built from a WordPress
 * menu. Top-level items become tiles; children become italic sub-link chips.
 * Tile background images come from a chosen source (the linked page's featured
 * image, or an image URL in the item's Description). Mark a top item with the
 * CSS class "cta" for the gold tile. Vanilla JS, focus-managed, no jQuery.
 * Assets load only on pages that use this widget (get_*_depends).
 */
class RK_Widget_Bento_Menu extends RK_Widget_Base {

	public function get_name()           { return 'rk-bento-menu'; }
	public function get_title()          { return 'RK Bento Menu'; }
	public function get_icon()           { return 'eicon-menu-bar'; }
	public function get_keywords()       { return array( 'menu', 'mega', 'bento', 'fullscreen', 'overlay', 'nav', 'rk' ); }
	public function get_style_depends()  { return array( 'rk-menu' ); }
	public function get_script_depends() { return array( 'rk-menu' ); }

	protected function register_controls() {

		/* ---- Content ---- */
		$this->start_controls_section( 'sec_menu', array( 'label' => 'Menu', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'menu', array(
			'label'   => 'WordPress menu', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => RK_Widget_Controls::nav_menu_choices(), 'default' => key( RK_Widget_Controls::nav_menu_choices() ),
			'description' => 'Top-level items become tiles; child items become chips. Add CSS class "cta" to a top item for the gold tile.',
		) );
		$this->add_control( 'img_source', array(
			'label' => 'Tile image source', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'featured',
			'options' => array(
				'featured'    => 'Linked page featured image',
				'description' => 'Image URL in item Description',
				'none'        => 'No images (flat tiles)',
			),
		) );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'match_title', array( 'label' => 'Menu item title', 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => 'e.g. About' ) );
		$rep->add_control( 'image', array( 'label' => 'Tile image', 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->add_control( 'tile_images', array(
			'label' => 'Tile images (per item)', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ match_title }}}',
			'description' => 'Type a top-level menu item title exactly, then pick its tile image. These override the source above.',
		) );
		$this->add_control( 'trigger_label', array( 'label' => 'Trigger button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Menu' ) );
		$this->add_control( 'close_label', array( 'label' => 'Close button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Close' ) );
		$this->add_control( 'logo', array( 'label' => 'Overlay logo', 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->add_control( 'show_numbers', array( 'label' => 'Show tile numbers', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'wide_every', array(
			'label' => 'Wide tile every Nth item', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 4, 'min' => 0, 'max' => 10,
			'description' => '0 = all tiles the same (small) size.',
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'sec_foot', array( 'label' => 'Footer contact', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'foot_email',    array( 'label' => 'Email',    'type' => \Elementor\Controls_Manager::TEXT ) );
		$this->add_control( 'foot_phone',    array( 'label' => 'Phone',    'type' => \Elementor\Controls_Manager::TEXT ) );
		$this->add_control( 'foot_location', array( 'label' => 'Location', 'type' => \Elementor\Controls_Manager::TEXT ) );
		$this->end_controls_section();

		/* ---- Style: colors ---- */
		$this->start_controls_section( 'sec_style', array( 'label' => 'Colors', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'c_accent',  array( 'label' => 'Gold accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#bf982a', 'selectors' => array( '{{WRAPPER}} .rkb-root' => '--rkb-accent:{{VALUE}};' ) ) );
		$this->add_control( 'c_accent2', array( 'label' => 'Gold (bright)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#e6c75a', 'selectors' => array( '{{WRAPPER}} .rkb-root' => '--rkb-accent2:{{VALUE}};' ) ) );
		$this->add_control( 'c_bg',      array( 'label' => 'Overlay background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#fffdfb', 'selectors' => array( '{{WRAPPER}} .rkb-root' => '--rkb-bg:{{VALUE}};' ) ) );
		$this->add_control( 'c_card',    array( 'label' => 'Tile (no image)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f4efe8', 'selectors' => array( '{{WRAPPER}} .rkb-root' => '--rkb-card:{{VALUE}};' ) ) );
		$this->add_control( 'c_text',    array( 'label' => 'Text / trigger', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#2a2230', 'selectors' => array( '{{WRAPPER}} .rkb-root' => '--rkb-text:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'trigger_align', array(
			'label' => 'Trigger alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ),
			),
			'default' => 'flex-start',
			'selectors' => array( '{{WRAPPER}} .rkb-root' => 'display:flex;justify-content:{{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ---- Style: typography ---- */
		$this->start_controls_section( 'sec_typo', array( 'label' => 'Typography', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'title_typo', 'label' => 'Tile title', 'selector' => '{{WRAPPER}} .rkb-tbody h3' ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'chip_typo', 'label' => 'Chips', 'selector' => '{{WRAPPER}} .rkb-chip' ) );
		$this->add_responsive_control( 'tile_min', array(
			'label' => 'Tile min height', 'type' => \Elementor\Controls_Manager::SLIDER,
			'range' => array( 'px' => array( 'min' => 120, 'max' => 400 ) ), 'default' => array( 'size' => 210, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rkb-tile' => 'min-height:{{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'tile_radius', array(
			'label' => 'Tile radius', 'type' => \Elementor\Controls_Manager::SLIDER,
			'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 18, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rkb-tile' => 'border-radius:{{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ===================== STYLE: Trigger button ===================== */
		$this->start_controls_section( 'st_trigger', array( 'label' => 'Trigger Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'trig_typo', 'selector' => '{{WRAPPER}} .rkb-trigger' ) );
		$this->start_controls_tabs( 'trig_states' );
		$this->start_controls_tab( 'trig_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'trig_c', array( 'label' => 'Text / bars', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .rkb-trigger' => 'color:{{VALUE}};', '{{WRAPPER}} .rkb-bars, {{WRAPPER}} .rkb-bars::before, {{WRAPPER}} .rkb-bars::after' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'trig_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-trigger' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'trig_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'trig_ch', array( 'label' => 'Text / bars', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .rkb-trigger:hover' => 'color:{{VALUE}};', '{{WRAPPER}} .rkb-trigger:hover .rkb-bars, {{WRAPPER}} .rkb-trigger:hover .rkb-bars::before, {{WRAPPER}} .rkb-trigger:hover .rkb-bars::after' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'trig_bgh', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-trigger:hover' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_responsive_control( 'trig_pad', array( 'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .rkb-trigger' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'trig_border', 'selector' => '{{WRAPPER}} .rkb-trigger' ) );
		$this->add_control( 'trig_radius', array( 'label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .rkb-trigger' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		/* ===================== STYLE: Overlay ===================== */
		$this->start_controls_section( 'st_overlay', array( 'label' => 'Overlay', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'ov_pad', array( 'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'vw' ),
			'selectors' => array( '{{WRAPPER}} .rkb-overlay' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'ov_logo_h', array( 'label' => 'Logo max height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 20, 'max' => 120 ) ), 'default' => array( 'size' => 44, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rkb-logo' => 'max-height:{{SIZE}}{{UNIT}};width:auto;' ) ) );
		$this->add_control( 'ov_close_head', array( 'label' => 'Close button', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->start_controls_tabs( 'close_states' );
		$this->start_controls_tab( 'close_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'close_c', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-close' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'close_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-close' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'close_bd', array( 'label' => 'Border', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-close' => 'border-color:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'close_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'close_ch', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-close:hover' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'close_bgh', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-close:hover' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control( 'close_radius', array( 'label' => 'Close radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .rkb-close' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		/* ===================== STYLE: Tiles ===================== */
		$this->start_controls_section( 'st_tiles', array( 'label' => 'Tiles', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'tile_gap', array( 'label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 16, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rkb-grid' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'tile_pad', array( 'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .rkb-tile' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'tile_title_c', array( 'label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-tile:not(.has-img) .rkb-tbody h3' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'tile_title_img_c', array( 'label' => 'Title color (on image)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rkb-tile.has-img .rkb-tbody h3' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'tile_num_c', array( 'label' => 'Number color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-num' => 'color:{{VALUE}};opacity:1;' ) ) );
		$this->add_control( 'tile_arrow_c', array( 'label' => 'Corner arrow color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-corner .rkb-ar' => 'stroke:{{VALUE}};color:{{VALUE}};opacity:1;' ) ) );
		$this->add_control( 'tile_overlay', array( 'label' => 'Image overlay', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(0,0,0,.55)', 'selectors' => array( '{{WRAPPER}} .rkb-tile.has-img::after' => 'background:linear-gradient(180deg,rgba(0,0,0,0) 30%,{{VALUE}} 100%);' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'tile_border', 'selector' => '{{WRAPPER}} .rkb-tile', 'separator' => 'before' ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'tile_shadow', 'label' => 'Hover shadow', 'selector' => '{{WRAPPER}} .rkb-tile:hover' ) );
		$this->end_controls_section();

		/* ===================== STYLE: Chips ===================== */
		$this->start_controls_section( 'st_chips', array( 'label' => 'Chips', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'chip_gap', array( 'label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'default' => array( 'size' => 8, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rkb-chips' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'chip_pad', array( 'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .rkb-chip' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'chip_radius', array( 'label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'default' => array( 'size' => 20, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .rkb-chip' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->start_controls_tabs( 'chip_states' );
		$this->start_controls_tab( 'chip_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'chip_c', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-chip' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'chip_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-chip' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'chip_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'chip_ch', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-chip:hover' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'chip_bgh', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-chip:hover' => 'background:{{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		/* ===================== STYLE: Gold CTA tile ===================== */
		$this->start_controls_section( 'st_gold', array( 'label' => 'Gold CTA Tile', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'gold_c', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rkb-gold, {{WRAPPER}} .rkb-gold h3' => 'color:{{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'gold_typo', 'label' => 'Typography', 'selector' => '{{WRAPPER}} .rkb-gold h3' ) );
		$this->add_control( 'gold_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'The gold gradient uses the accent colors from the Colors section.', 'content_classes' => 'elementor-descriptor' ) );
		$this->end_controls_section();

		/* ===================== STYLE: Footer ===================== */
		$this->start_controls_section( 'st_foot', array( 'label' => 'Footer', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'foot_typo', 'selector' => '{{WRAPPER}} .rkb-foot' ) );
		$this->add_control( 'foot_c', array( 'label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-foot a, {{WRAPPER}} .rkb-foot span' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'foot_ch', array( 'label' => 'Link hover', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-foot a:hover' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'foot_gap', array( 'label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .rkb-foot' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'foot_divider', array( 'label' => 'Divider color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rkb-foot' => 'border-top-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private function looks_like_image( $url ) {
		return $url && preg_match( '/\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i', $url );
	}

	/** Resolve a tile background image URL for a menu item, per chosen source. */
	private function tile_image( $it, $source ) {
		if ( 'none' === $source ) { return ''; }
		if ( 'description' === $source ) {
			$d = isset( $it->description ) ? trim( $it->description ) : '';
			return $this->looks_like_image( $d ) ? $d : '';
		}
		// featured image of the linked object (page/post/CPT)
		if ( ! empty( $it->object_id ) && 'custom' !== $it->object ) {
			$src = get_the_post_thumbnail_url( (int) $it->object_id, 'large' );
			if ( $src ) { return $src; }
		}
		// fallback: description URL if present
		$d = isset( $it->description ) ? trim( $it->description ) : '';
		return $this->looks_like_image( $d ) ? $d : '';
	}

	protected function rk_render() {
		$s      = $this->get_settings_for_display();
		$tree   = RK_Widget_Controls::nav_menu_tree( isset( $s['menu'] ) ? (int) $s['menu'] : 0 );
		$uid    = 'rkb-' . $this->get_id();
		$wide   = isset( $s['wide_every'] ) ? (int) $s['wide_every'] : 4;
		$source = isset( $s['img_source'] ) ? $s['img_source'] : 'featured';
		$nums   = ( ! isset( $s['show_numbers'] ) || 'yes' === $s['show_numbers'] );

		// Per-item image overrides, keyed by lowercased title.
		$img_map = array();
		if ( ! empty( $s['tile_images'] ) && is_array( $s['tile_images'] ) ) {
			foreach ( $s['tile_images'] as $row ) {
				$t = isset( $row['match_title'] ) ? strtolower( trim( $row['match_title'] ) ) : '';
				if ( '' !== $t && ! empty( $row['image']['url'] ) ) { $img_map[ $t ] = $row['image']['url']; }
			}
		}

		if ( ! $tree ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<div class="rkb-root"><em>Pick a menu in the widget settings, or build one in Appearance → Menus.</em></div>';
			}
			return;
		}

		$spark = '<svg class="rkb-ar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6z"/></svg>';
		$arrow = '<svg class="rkb-ar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg>';

		$tiles = '';
		$i = 0;
		foreach ( $tree as $node ) {
			$i++;
			$it      = $node['item'];
			$title   = esc_html( $it->title );
			$url     = ! empty( $it->url ) ? esc_url( $it->url ) : '#';
			$classes = array_map( 'strtolower', array_filter( (array) ( isset( $it->classes ) ? $it->classes : array() ) ) );
			$is_cta  = ( in_array( 'cta', $classes, true ) || in_array( 'gold', $classes, true ) );
			$num     = str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
			$width   = ( $wide > 0 && 0 === $i % $wide ) ? 'rkb-full' : 'rkb-half';

			if ( $is_cta ) {
				$tiles .= '<a class="rkb-tile rkb-gold ' . $width . '" href="' . $url . '"><span class="rkb-tbody">' . $spark . '<h3>' . $title . '</h3></span></a>';
				continue;
			}

			$key    = strtolower( trim( (string) $it->title ) );
			$img    = isset( $img_map[ $key ] ) ? esc_url( $img_map[ $key ] ) : esc_url( $this->tile_image( $it, $source ) );
			$imgtag = $img ? '<img class="rkb-tbg" src="' . $img . '" alt="" loading="lazy" onload="this.classList.add(\'rkb-loaded\')">' : '';
			$chips  = '';
			if ( ! empty( $node['children'] ) ) {
				$chips .= '<span class="rkb-chips">';
				foreach ( $node['children'] as $c ) {
					$curl = ! empty( $c['item']->url ) ? esc_url( $c['item']->url ) : '#';
					$chips .= '<a class="rkb-chip" href="' . $curl . '">' . esc_html( $c['item']->title ) . '</a>';
				}
				$chips .= '</span>';
			}
			$tiles .= '<a class="rkb-tile ' . $width . ( $img ? ' has-img' : '' ) . '" href="' . $url . '">'
				. $imgtag
				. ( $nums ? '<span class="rkb-num">' . esc_html( $num ) . '</span>' : '' )
				. '<span class="rkb-corner">' . $arrow . '</span>'
				. '<span class="rkb-tbody"><h3>' . $title . '</h3>' . $chips . '</span>'
				. '</a>';
		}

		$logo = ( ! empty( $s['logo']['url'] ) ) ? '<img class="rkb-logo" src="' . esc_url( $s['logo']['url'] ) . '" alt="">' : '<span></span>';
		$foot = '';
		foreach ( array( 'foot_email' => 'mailto:', 'foot_phone' => 'tel:', 'foot_location' => '' ) as $k => $pre ) {
			if ( empty( $s[ $k ] ) ) { continue; }
			$v = esc_html( $s[ $k ] );
			if ( $pre ) { $foot .= '<a href="' . esc_attr( $pre . preg_replace( '/\s+/', '', $s[ $k ] ) ) . '">' . $v . '</a>'; }
			else { $foot .= '<span>' . $v . '</span>'; }
		}

		echo '<div class="rkb-root">';
		echo '<button class="rkb-trigger" type="button" data-rkb-open="' . esc_attr( $uid ) . '" aria-haspopup="dialog"><span class="rkb-bars"></span> ' . esc_html( isset( $s['trigger_label'] ) ? $s['trigger_label'] : 'Menu' ) . '</button>';
		echo '<div class="rkb-overlay" id="' . esc_attr( $uid ) . '" data-rkb-overlay role="dialog" aria-modal="true" aria-label="Menu" hidden>';
		echo '<div class="rkb-bar">' . $logo . '<button class="rkb-close" type="button" data-rkb-close>' . esc_html( isset( $s['close_label'] ) ? $s['close_label'] : 'Close' ) . ' ✕</button></div>';
		echo '<div class="rkb-grid">' . $tiles . '</div>';
		if ( $foot ) { echo '<div class="rkb-foot">' . $foot . '</div>'; }
		echo '</div></div>';
	}
}
