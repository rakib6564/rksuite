<?php
/**
 * RK_Theme_Visibility — JetEngine-style dynamic visibility for Elementor.
 * Adds a "RK Dynamic Visibility" section to every element (widget, section,
 * column, container) and shows/hides it on the front end by meta field, user
 * login state, or role. Uses the same Elementor hooks JetEngine uses.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Visibility {

	public static function init() {
		$cb = array( __CLASS__, 'controls' );
		add_action( 'elementor/element/common/_section_style/after_section_end', $cb, 10, 2 );
		add_action( 'elementor/element/section/section_advanced/after_section_end', $cb, 10, 2 );
		add_action( 'elementor/element/column/section_advanced/after_section_end', $cb, 10, 2 );
		add_action( 'elementor/element/container/section_layout/after_section_end', $cb, 10, 2 );
		foreach ( array( 'widget', 'section', 'column', 'container' ) as $el ) {
			add_filter( 'elementor/frontend/' . $el . '/should_render', array( __CLASS__, 'check' ), 10, 2 );
		}
	}

	public static function controls( $element, $section_id = '' ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) ) { return; }
		$cm = '\Elementor\Controls_Manager';
		$element->start_controls_section( 'rk_dv_section', array(
			'label' => 'RK Dynamic Visibility',
			'tab'   => $cm::TAB_ADVANCED,
		) );
		$element->add_control( 'rk_dv_enable', array( 'label' => 'Enable', 'type' => $cm::SWITCHER, 'return_value' => 'yes' ) );
		$cond = array( 'rk_dv_enable' => 'yes' );
		$element->add_control( 'rk_dv_type', array(
			'label' => 'Action', 'type' => $cm::SELECT, 'default' => 'show',
			'options' => array( 'show' => 'Show if condition met', 'hide' => 'Hide if condition met' ),
			'condition' => $cond,
		) );
		$element->add_control( 'rk_dv_user', array(
			'label' => 'User', 'type' => $cm::SELECT, 'default' => 'any',
			'options' => array( 'any' => 'Any', 'logged_in' => 'Logged in', 'logged_out' => 'Logged out' ),
			'condition' => $cond,
		) );
		$element->add_control( 'rk_dv_role', array(
			'label' => 'Role (optional)', 'type' => $cm::TEXT, 'placeholder' => 'administrator',
			'condition' => array( 'rk_dv_enable' => 'yes', 'rk_dv_user' => 'logged_in' ),
		) );
		$element->add_control( 'rk_dv_field', array(
			'label' => 'Meta field key (optional)', 'type' => $cm::TEXT, 'placeholder' => 'e.g. featured',
			'condition' => $cond,
		) );
		$element->add_control( 'rk_dv_op', array(
			'label' => 'Condition', 'type' => $cm::SELECT, 'default' => 'filled',
			'options' => array( 'filled' => 'Is filled', 'empty' => 'Is empty', 'is' => 'Equals', 'is_not' => 'Not equals', 'gt' => 'Greater than', 'lt' => 'Less than' ),
			'condition' => array( 'rk_dv_enable' => 'yes', 'rk_dv_field!' => '' ),
		) );
		$element->add_control( 'rk_dv_value', array(
			'label' => 'Value', 'type' => $cm::TEXT,
			'condition' => array( 'rk_dv_enable' => 'yes', 'rk_dv_field!' => '' ),
		) );
		$element->end_controls_section();
	}

	public static function check( $should, $element = null ) {
		if ( ! $should || ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) ) { return $should; }
		$s = $element->get_settings_for_display();
		if ( empty( $s['rk_dv_enable'] ) || 'yes' !== $s['rk_dv_enable'] ) { return $should; }
		$met     = self::condition_met( $s );
		$type    = isset( $s['rk_dv_type'] ) ? $s['rk_dv_type'] : 'show';
		$visible = ( 'hide' === $type ) ? ! $met : $met;
		return $visible ? $should : false;
	}

	private static function condition_met( $s ) {
		$user = isset( $s['rk_dv_user'] ) ? $s['rk_dv_user'] : 'any';
		if ( 'logged_in' === $user ) {
			if ( ! is_user_logged_in() ) { return false; }
			$role = trim( (string) ( isset( $s['rk_dv_role'] ) ? $s['rk_dv_role'] : '' ) );
			if ( '' !== $role && ! in_array( $role, (array) wp_get_current_user()->roles, true ) ) { return false; }
		} elseif ( 'logged_out' === $user ) {
			if ( is_user_logged_in() ) { return false; }
		}
		$field = trim( (string) ( isset( $s['rk_dv_field'] ) ? $s['rk_dv_field'] : '' ) );
		if ( '' === $field ) { return true; }
		$val    = get_post_meta( (int) get_the_ID(), $field, true );
		$cur    = is_array( $val ) ? implode( ',', array_map( 'strval', $val ) ) : (string) $val;
		$target = (string) ( isset( $s['rk_dv_value'] ) ? $s['rk_dv_value'] : '' );
		switch ( isset( $s['rk_dv_op'] ) ? $s['rk_dv_op'] : 'filled' ) {
			case 'filled': return '' !== $cur;
			case 'empty':  return '' === $cur;
			case 'is':     return $cur === $target;
			case 'is_not': return $cur !== $target;
			case 'gt':     return is_numeric( $cur ) && is_numeric( $target ) && (float) $cur > (float) $target;
			case 'lt':     return is_numeric( $cur ) && is_numeric( $target ) && (float) $cur < (float) $target;
		}
		return true;
	}
}
