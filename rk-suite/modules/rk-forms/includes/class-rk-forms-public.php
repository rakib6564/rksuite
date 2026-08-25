<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Forms_Public — renders a form on the front end and processes AJAX
 * submissions (validate → store → email admin + optional confirmation).
 */
class RK_Forms_Public {

	/** Build the public HTML for a form row. */
	public static function render_form( $form, $args = array() ) {
		if ( ! $form || 'published' !== $form->status ) {
			return current_user_can( 'manage_options' )
				? '<div class="rk-form-notice">This form is not published yet.</div>'
				: '';
		}
		$fields = RK_Forms_Fields::decode( $form->fields_json );
		if ( empty( $fields ) ) { return '<div class="rk-form-notice">This form has no fields.</div>'; }

		$uid    = 'rkf-' . $form->id . '-' . wp_generate_password( 4, false, false );
		$nonce  = wp_create_nonce( 'rk_form_submit_' . $form->id );

		$out  = '<div class="rk-form-wrap" data-rk-form-id="' . esc_attr( $form->id ) . '">';
		$out .= '<form class="rk-form" id="' . esc_attr( $uid ) . '" method="post" novalidate '
			. 'data-rk-form="' . esc_attr( $form->id ) . '" data-rk-nonce="' . esc_attr( $nonce ) . '"'
			. ( $form->redirect_url ? ' data-rk-redirect="' . esc_url( $form->redirect_url ) . '"' : '' ) . '>';

		if ( $form->title && empty( $args['hide_title'] ) ) { $out .= '<h2 class="rk-form-title">' . esc_html( $form->title ) . '</h2>'; }
		if ( $form->description ) { $out .= '<p class="rk-form-desc">' . esc_html( $form->description ) . '</p>'; }

		$out .= '<div class="rk-form-fields">';
		foreach ( $fields as $f ) { $out .= RK_Forms_Fields::render_field( $f, $uid ); }
		$out .= '</div>';

		// Honeypot (spam guard) + timing token.
		$out .= '<div class="rk-form-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;height:1px;overflow:hidden;">'
			. '<label>Leave this field empty<input type="text" name="rk_hp" tabindex="-1" autocomplete="off" /></label></div>';
		$out .= '<input type="hidden" name="rk_ts" value="' . esc_attr( time() ) . '" />';

		$out .= '<div class="rk-form-foot"><button type="submit" class="rk-form-submit">' . esc_html( $form->submit_label ? $form->submit_label : 'Submit' ) . '</button>'
			. '<span class="rk-form-spinner" hidden></span></div>';
		$out .= '<div class="rk-form-msg" role="status" aria-live="polite" hidden></div>';
		$out .= '</form></div>';
		return $out;
	}

	/** AJAX: process a submission. */
	public static function ajax_submit() {
		$form_id = isset( $_POST['rk_form_id'] ) ? (int) $_POST['rk_form_id'] : 0;
		if ( ! $form_id || ! check_ajax_referer( 'rk_form_submit_' . $form_id, 'rk_nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh and try again.' ) );
		}
		$form = RK_Forms_DB::get_form( $form_id );
		if ( ! $form || 'published' !== $form->status ) { wp_send_json_error( array( 'message' => 'Form not available.' ) ); }

		// Spam guard: honeypot + submit-too-fast.
		if ( ! empty( $_POST['rk_hp'] ) ) { wp_send_json_error( array( 'message' => 'Submission blocked.' ) ); }
		$ts = isset( $_POST['rk_ts'] ) ? (int) $_POST['rk_ts'] : 0;
		if ( $ts && ( time() - $ts ) < 2 ) { wp_send_json_error( array( 'message' => 'Please take a moment before submitting.' ) ); }

		$fields = RK_Forms_Fields::decode( $form->fields_json );
		$input  = self::collect_input( $fields );
		$res    = RK_Forms_Fields::validate( $fields, $input );

		if ( ! empty( $res['errors'] ) ) {
			wp_send_json_error( array( 'message' => 'Please fix the highlighted fields.', 'fields' => $res['errors'] ) );
		}
		$data = $res['data'];

		// Persist.
		$submitter = self::first_email( $fields, $data );
		$ref = strtoupper( wp_generate_password( 8, false, false ) );
		$sid = RK_Forms_DB::insert_submission( array(
			'form_id'         => $form_id,
			'ref'             => $ref,
			'data_json'       => wp_json_encode( $data ),
			'submitter_email' => $submitter,
			'ip'              => self::client_ip(),
			'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
		) );

		// Notify admin + optional submitter confirmation.
		self::send_emails( $form, $fields, $data, $ref, $sid, $submitter );

		/**
		 * Fires after a valid RK Forms submission is stored.
		 * @param int   $sid   Submission id.
		 * @param array $data  Sanitized field data.
		 * @param object $form Form row.
		 */
		do_action( 'rk_forms_submission', $sid, $data, $form );

		$out = array( 'message' => $form->success_message ? $form->success_message : 'Thanks — your submission was received.' );
		if ( $form->redirect_url ) { $out['redirect'] = esc_url_raw( $form->redirect_url ); }
		wp_send_json_success( $out );
	}

	/** Pull only the known field names out of $_POST (unslashed). */
	private static function collect_input( $fields ) {
		$input = array();
		foreach ( (array) $fields as $f ) {
			$name = isset( $f['name'] ) ? $f['name'] : '';
			if ( '' === $name ) { continue; }
			if ( isset( $_POST[ $name ] ) ) {
				$input[ $name ] = is_array( $_POST[ $name ] )
					? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $name ] ) )
					: wp_unslash( $_POST[ $name ] ); // per-type sanitize happens in validate()
			}
		}
		return $input;
	}

	private static function first_email( $fields, $data ) {
		foreach ( (array) $fields as $f ) {
			if ( 'email' === ( isset( $f['type'] ) ? $f['type'] : '' ) && ! empty( $data[ $f['name'] ] ) ) {
				return sanitize_email( $data[ $f['name'] ] );
			}
		}
		return '';
	}

	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( $ip, 0, 60 );
	}

	/** Build a readable field=>value summary for emails. */
	public static function summary_rows( $fields, $data ) {
		$rows = array();
		foreach ( (array) $fields as $f ) {
			$name = isset( $f['name'] ) ? $f['name'] : '';
			if ( '' === $name || in_array( ( isset( $f['type'] ) ? $f['type'] : '' ), array( 'heading', 'hidden' ), true ) ) { continue; }
			if ( ! array_key_exists( $name, $data ) ) { continue; }
			$v = $data[ $name ];
			if ( is_array( $v ) ) { $v = implode( ', ', $v ); }
			if ( 'checkbox' === $f['type'] ) { $v = $v ? 'Yes' : 'No'; }
			$rows[] = array( 'label' => isset( $f['label'] ) ? $f['label'] : $name, 'value' => (string) $v );
		}
		return $rows;
	}

	private static function send_emails( $form, $fields, $data, $ref, $sid, $submitter ) {
		$rows = self::summary_rows( $fields, $data );
		$lines = array();
		foreach ( $rows as $r ) { $lines[] = $r['label'] . ': ' . $r['value']; }
		$body_txt = implode( "\n", $lines );

		$to = $form->notify_email ? $form->notify_email : get_option( 'admin_email' );
		if ( $to ) {
			$subject = sprintf( '[%s] New submission: %s (#%s)', get_bloginfo( 'name' ), $form->title, $ref );
			$ok = wp_mail( $to, $subject, "New submission on \"{$form->title}\".\n\n{$body_txt}\n\nRef: {$ref}" );
			global $wpdb;
			$wpdb->update( RK_Forms_DB::submissions_table(), array( 'email_sent' => $ok ? 1 : 0, 'email_error' => $ok ? null : 'wp_mail returned false' ), array( 'id' => (int) $sid ) );
		}

		if ( $form->confirm_submitter && $submitter && is_email( $submitter ) ) {
			$subj = $form->confirm_subject ? $form->confirm_subject : ( 'We received your ' . $form->title );
			$msg  = $form->confirm_body ? $form->confirm_body : "Thanks — we've received your submission and will be in touch.";
			$msg  = str_replace( array( '{ref}', '{title}' ), array( $ref, $form->title ), $msg );
			wp_mail( $submitter, $subj, $msg . "\n\n---\n" . $body_txt );
		}
	}
}
