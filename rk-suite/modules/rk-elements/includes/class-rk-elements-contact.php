<?php
/**
 * RK_Elements_Contact — server side for the RK Contact Form widget.
 *
 * Handles the AJAX submission for `rk-contact-form`: nonce + spam guards,
 * server-side validation, admin notification, optional autoresponder, and the
 * `rk_elements_contact_submit` action hook.
 *
 * The notification recipient and message copy are NEVER trusted from the
 * request — each widget stores its mail config server-side (keyed by an opaque
 * id emitted in the markup), so a forged submission cannot turn the site into
 * an open mail relay or harvest the admin address from page source.
 *
 * @package RK_Elements
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Prevent direct file access.

class RK_Elements_Contact {

	const NONCE  = 'rk_contact_submit';
	const OPTION = 'rk_elements_cf_configs';

	/** Wire the AJAX endpoints (logged-in and anonymous). */
	public static function init() {
		add_action( 'wp_ajax_rk_contact_submit', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_rk_contact_submit', array( __CLASS__, 'ajax_submit' ) );
	}

	/**
	 * Persist a widget's mail config and return its opaque lookup key.
	 * Writes only when the stored value actually changed (keeps front-end
	 * renders from hammering the options table on every page view).
	 *
	 * @param string $widget_id Elementor element id.
	 * @param array  $cfg       { notify, subject, autoresponder, ar_body, success }.
	 * @return string Opaque key emitted into the markup.
	 */
	public static function store_config( $widget_id, $cfg ) {
		$key  = substr( hash_hmac( 'sha256', (string) $widget_id, wp_salt( 'nonce' ) ), 0, 20 );
		$all  = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) { $all = array(); }
		$norm = array(
			'notify'        => is_email( $cfg['notify'] ) ? $cfg['notify'] : get_option( 'admin_email' ),
			'subject'       => (string) $cfg['subject'],
			'autoresponder' => ! empty( $cfg['autoresponder'] ),
			'ar_body'       => (string) $cfg['ar_body'],
			'success'       => (string) $cfg['success'],
		);
		if ( ! isset( $all[ $key ] ) || $all[ $key ] !== $norm ) {
			$all[ $key ] = $norm;
			update_option( self::OPTION, $all, false );
		}
		return $key;
	}

	/** Look up a stored config by key, or null. */
	private static function get_config( $key ) {
		$all = get_option( self::OPTION, array() );
		return ( is_array( $all ) && isset( $all[ $key ] ) ) ? $all[ $key ] : null;
	}

	/** Process one submission. */
	public static function ajax_submit() {
		if ( ! check_ajax_referer( self::NONCE, 'rk_nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh and try again.' ) );
		}

		// Spam guards: honeypot + submit-too-fast.
		if ( ! empty( $_POST['rk_hp'] ) ) {
			wp_send_json_error( array( 'message' => 'Submission blocked.' ) );
		}
		$ts = isset( $_POST['rk_ts'] ) ? (int) $_POST['rk_ts'] : 0;
		if ( $ts && ( time() - $ts ) < 2 ) {
			wp_send_json_error( array( 'message' => 'Please take a moment before submitting.' ) );
		}

		// Authoritative mail config comes from the server, keyed by opaque id.
		$key = isset( $_POST['cfg'] ) ? preg_replace( '/[^a-f0-9]/', '', wp_unslash( $_POST['cfg'] ) ) : '';
		$cfg = $key ? self::get_config( $key ) : null;
		if ( ! $cfg ) {
			wp_send_json_error( array( 'message' => 'This form is no longer available. Please reload the page.' ) );
		}

		// Collect + sanitize visitor inputs.
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		// Validate.
		$errors = array();
		if ( '' === $email || ! is_email( $email ) ) { $errors['email'] = 'Please enter a valid email address.'; }
		if ( '' === trim( $message ) ) { $errors['message'] = 'Please enter a message.'; }
		if ( $errors ) {
			wp_send_json_error( array( 'message' => 'Please fix the highlighted fields.', 'fields' => $errors ) );
		}

		$ref  = strtoupper( wp_generate_password( 8, false, false ) );
		$data = array( 'name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subject, 'message' => $message, 'ref' => $ref );

		// Admin notification.
		$lines = array();
		if ( $name )    { $lines[] = 'Name: ' . $name; }
		$lines[] = 'Email: ' . $email;
		if ( $phone )   { $lines[] = 'Phone: ' . $phone; }
		if ( $subject ) { $lines[] = 'Subject: ' . $subject; }
		$lines[] = '';
		$lines[] = 'Message:';
		$lines[] = $message;
		$lines[] = '';
		$lines[] = 'Ref: ' . $ref . ' — sent from ' . home_url( '/' );
		$body = implode( "\n", $lines );

		$headers = array();
		if ( $email ) {
			$from_name = $name ? $name : $email;
			$headers[] = 'Reply-To: ' . $from_name . ' <' . $email . '>';
		}
		$subj_line = $cfg['subject'] ? $cfg['subject'] : 'New contact form submission';
		$sent = wp_mail( $cfg['notify'], '[' . get_bloginfo( 'name' ) . '] ' . $subj_line . ' (#' . $ref . ')', $body, $headers );

		// Optional autoresponder to the sender.
		if ( ! empty( $cfg['autoresponder'] ) && $email && is_email( $email ) ) {
			$ar = $cfg['ar_body'] ? $cfg['ar_body'] : 'Thanks — we received your message.';
			wp_mail( $email, 'Thanks for contacting ' . get_bloginfo( 'name' ), $ar . "\n\n---\n" . $message );
		}

		/**
		 * Fires after a valid RK Contact Form submission.
		 *
		 * @param array $data Sanitized submission { name,email,phone,subject,message,ref }.
		 * @param bool  $sent Whether wp_mail() accepted the admin notification.
		 */
		do_action( 'rk_elements_contact_submit', $data, (bool) $sent );

		wp_send_json_success( array( 'message' => $cfg['success'] ? $cfg['success'] : 'Thanks — your message has been sent.' ) );
	}
}
