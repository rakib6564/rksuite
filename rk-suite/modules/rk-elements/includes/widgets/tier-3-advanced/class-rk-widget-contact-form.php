<?php
/**
 * RK_Widget_Contact_Form — a self-contained contact form widget.
 *
 * Configured entirely inside Elementor (no RK Forms row required): pick which
 * fields to show, style them, and set where the notification goes. On submit it
 * validates server-side, runs spam guards (honeypot + timing), emails the admin
 * (and an optional autoresponder to the sender), and fires the
 * `rk_elements_contact_submit` action so other code can capture the entry.
 *
 * @package RK_Elements
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'RK_Widget_Base' ) ) { return; }

class RK_Widget_Contact_Form extends RK_Widget_Base {

	public function get_name()     { return 'rk-contact-form'; }
	public function get_title()    { return 'RK Contact Form'; }
	public function get_icon()     { return 'eicon-form-horizontal'; }
	public function get_keywords() { return array( 'contact', 'form', 'email', 'message', 'rk' ); }

	public function get_style_depends()  { return array( 'rk-elements', 'rk-contact' ); }
	public function get_script_depends() { return array( 'rk-elements', 'rk-contact' ); }

	protected function register_controls() {

		/* CONTENT — fields */
		$this->start_controls_section( 'sec_fields', array(
			'label' => 'Fields',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'show_name', array( 'label' => 'Name field', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'show_phone', array( 'label' => 'Phone field', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'show_subject', array( 'label' => 'Subject field', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'require_message', array( 'label' => 'Message required', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'consent_text', array( 'label' => 'Consent checkbox text', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true, 'placeholder' => 'I agree to be contacted (leave empty to hide)' ) );
		$this->end_controls_section();

		/* CONTENT — labels & copy */
		$this->start_controls_section( 'sec_labels', array(
			'label' => 'Labels & Text',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'label_name',    array( 'label' => 'Name label',    'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Your name' ) );
		$this->add_control( 'label_email',   array( 'label' => 'Email label',   'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Email address' ) );
		$this->add_control( 'label_phone',   array( 'label' => 'Phone label',   'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Phone' ) );
		$this->add_control( 'label_subject', array( 'label' => 'Subject label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Subject' ) );
		$this->add_control( 'label_message', array( 'label' => 'Message label',  'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Message' ) );
		$this->add_control( 'button_text',   array( 'label' => 'Button text',    'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Send message' ) );
		$this->add_control( 'success_message', array(
			'label'   => 'Success message',
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => 'Thanks — your message has been sent. We will be in touch soon.',
		) );
		$this->end_controls_section();

		/* CONTENT — notifications */
		$this->start_controls_section( 'sec_notify', array(
			'label' => 'Notifications',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'notify_email', array( 'label' => 'Send submissions to', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true, 'placeholder' => get_option( 'admin_email' ), 'description' => 'Leave empty to use the site admin email.' ) );
		$this->add_control( 'email_subject', array( 'label' => 'Notification subject', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'New contact form submission' ) );
		$this->add_control( 'autoresponder', array( 'label' => 'Send confirmation to sender', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'autoresponder_body', array( 'label' => 'Confirmation message', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Thanks for reaching out — we have received your message and will reply shortly.', 'condition' => array( 'autoresponder' => 'yes' ) ) );
		$this->end_controls_section();

		/* STYLE — fields */
		$this->start_controls_section( 'sec_style_fields', array(
			'label' => 'Fields',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		if ( class_exists( '\Elementor\Group_Control_Typography' ) ) {
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'field_typo', 'selector' => '{{WRAPPER}} .rk-cf-input, {{WRAPPER}} .rk-cf-textarea' ) );
		}
		$this->add_control( 'field_text_color', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-cf-input, {{WRAPPER}} .rk-cf-textarea' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'field_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-cf-input, {{WRAPPER}} .rk-cf-textarea' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'field_label_color', array( 'label' => 'Label color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-cf-label' => 'color:{{VALUE}};' ) ) );
		if ( class_exists( '\Elementor\Group_Control_Border' ) ) {
			$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'field_border', 'selector' => '{{WRAPPER}} .rk-cf-input, {{WRAPPER}} .rk-cf-textarea' ) );
		}
		$this->add_control( 'field_radius', array( 'label' => 'Field radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'default' => array( 'size' => 8 ), 'selectors' => array( '{{WRAPPER}} .rk-cf-input, {{WRAPPER}} .rk-cf-textarea' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'field_gap', array( 'label' => 'Row gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'default' => array( 'size' => 16 ), 'selectors' => array( '{{WRAPPER}} .rk-cf' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		/* STYLE — button */
		$this->start_controls_section( 'sec_style_btn', array(
			'label' => 'Button',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'btn_color', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array( '{{WRAPPER}} .rk-cf-submit' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'btn_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#2563eb', 'selectors' => array( '{{WRAPPER}} .rk-cf-submit' => 'background:{{VALUE}};' ) ) );
		$this->add_control( 'btn_bg_hover', array( 'label' => 'Background (hover)', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .rk-cf-submit:hover' => 'background:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'btn_align', array(
			'label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE,
			'options' => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
				'stretch'    => array( 'title' => 'Full', 'icon' => 'eicon-text-align-justify' ),
			),
			'default' => 'flex-start',
			'selectors' => array( '{{WRAPPER}} .rk-cf-foot' => 'justify-content:{{VALUE}};' ),
		) );
		$this->add_responsive_control( 'btn_padding', array( 'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .rk-cf-submit' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	/** Render one field row (all values escaped here). */
	private function field_row( $name, $label, $type, $required ) {
		$id   = 'rk-cf-' . $name . '-' . wp_rand( 1000, 9999 );
		$req  = $required ? ' <span class="rk-cf-req">*</span>' : '';
		$aria = $required ? ' aria-required="true" required' : '';
		$out  = '<div class="rk-cf-field rk-cf-field--' . esc_attr( $name ) . '">';
		$out .= '<label class="rk-cf-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $req . '</label>';
		if ( 'textarea' === $type ) {
			$out .= '<textarea class="rk-cf-textarea" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="5"' . $aria . '></textarea>';
		} else {
			$out .= '<input class="rk-cf-input" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $aria . ' />';
		}
		$out .= '<span class="rk-cf-error" hidden></span></div>';
		return $out;
	}

	protected function rk_render() {
		$s = $this->get_settings_for_display();

		if ( class_exists( 'RK_Elements' ) && method_exists( 'RK_Elements', 'instance' ) ) {
			RK_Elements::instance()->enqueue_contact_now();
		}

		$nonce = wp_create_nonce( 'rk_contact_submit' );

		// Store mail config server-side; the markup carries only an opaque key,
		// so the recipient address is neither exposed nor client-controllable.
		$cfg_key = '';
		if ( class_exists( 'RK_Elements_Contact' ) ) {
			$cfg_key = RK_Elements_Contact::store_config( $this->get_id(), array(
				'notify'        => isset( $s['notify_email'] ) ? $s['notify_email'] : '',
				'subject'       => isset( $s['email_subject'] ) ? $s['email_subject'] : '',
				'autoresponder' => ( 'yes' === ( isset( $s['autoresponder'] ) ? $s['autoresponder'] : '' ) ),
				'ar_body'       => isset( $s['autoresponder_body'] ) ? $s['autoresponder_body'] : '',
				'success'       => isset( $s['success_message'] ) ? $s['success_message'] : '',
			) );
		}

		echo '<form class="rk-cf" method="post" novalidate data-rk-contact="1" data-rk-nonce="' . esc_attr( $nonce ) . '" data-rk-cfg="' . esc_attr( $cfg_key ) . '">';

		if ( 'yes' === ( isset( $s['show_name'] ) ? $s['show_name'] : 'yes' ) ) {
			echo $this->field_row( 'name', isset( $s['label_name'] ) ? $s['label_name'] : 'Your name', 'text', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_row().
		}
		echo $this->field_row( 'email', isset( $s['label_email'] ) ? $s['label_email'] : 'Email address', 'email', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_row().
		if ( 'yes' === ( isset( $s['show_phone'] ) ? $s['show_phone'] : '' ) ) {
			echo $this->field_row( 'phone', isset( $s['label_phone'] ) ? $s['label_phone'] : 'Phone', 'tel', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_row().
		}
		if ( 'yes' === ( isset( $s['show_subject'] ) ? $s['show_subject'] : '' ) ) {
			echo $this->field_row( 'subject', isset( $s['label_subject'] ) ? $s['label_subject'] : 'Subject', 'text', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_row().
		}
		echo $this->field_row( 'message', isset( $s['label_message'] ) ? $s['label_message'] : 'Message', 'textarea', ( 'yes' === ( isset( $s['require_message'] ) ? $s['require_message'] : 'yes' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in field_row().

		if ( ! empty( $s['consent_text'] ) ) {
			echo '<label class="rk-cf-consent"><input type="checkbox" name="consent" value="1" required /> <span>' . esc_html( $s['consent_text'] ) . '</span></label>';
		}

		echo '<div class="rk-cf-hp" aria-hidden="true" style="position:absolute;left:-9999px;height:1px;overflow:hidden;"><label>Leave empty<input type="text" name="rk_hp" tabindex="-1" autocomplete="off" /></label></div>';
		echo '<input type="hidden" name="rk_ts" value="' . esc_attr( time() ) . '" />';
		echo '<div class="rk-cf-foot"><button type="submit" class="rk-cf-submit">' . esc_html( isset( $s['button_text'] ) ? $s['button_text'] : 'Send message' ) . '</button></div>';
		echo '<div class="rk-cf-msg" role="status" aria-live="polite" hidden></div>';
		echo '</form>';
	}
}
