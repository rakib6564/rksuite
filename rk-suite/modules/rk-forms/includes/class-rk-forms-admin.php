<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Forms_Admin — builder + submissions inbox, rendered inside the shared RK
 * admin shell (contextual sidebar + rk-ui.css components).
 */
class RK_Forms_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'rk-forms';
	const SLUG_SUBS = 'rk-forms-submissions';

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_rk_forms_save',       array( $this, 'do_save' ) );
		add_action( 'admin_post_rk_forms_delete',     array( $this, 'do_delete' ) );
		add_action( 'admin_post_rk_forms_sub_delete', array( $this, 'do_sub_delete' ) );
		add_action( 'admin_post_rk_forms_export',     array( $this, 'do_export' ) );
	}

	public function menu() {
		add_menu_page( 'RK Forms', 'RK Forms', self::CAP, self::SLUG, array( $this, 'screen_forms' ), 'dashicons-feedback', 59 );
		add_submenu_page( self::SLUG, 'Forms', 'Forms', self::CAP, self::SLUG, array( $this, 'screen_forms' ) );
		add_submenu_page( self::SLUG, 'Submissions', 'Submissions', self::CAP, self::SLUG_SUBS, array( $this, 'screen_subs' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rk-forms' ) ) { return; }
		$css = RK_FORMS_DIR . 'assets/admin.css';
		wp_enqueue_style( 'rk-forms-admin', RK_FORMS_URL . 'assets/admin.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_FORMS_VERSION );
	}

	/* ---------------- shared shell ---------------- */
	private function head() {
		echo '<div class="wrap rk-forms-wrap rk-has-rail">';
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); }
		echo '<main class="rk-main">';
	}
	private function foot() { echo '</main></div>'; }
	private function notice() {
		if ( empty( $_GET['rk_msg'] ) ) { return; }
		$m = sanitize_key( $_GET['rk_msg'] );
		$map = array( 'saved' => 'Form saved.', 'deleted' => 'Deleted.', 'sub_deleted' => 'Submission deleted.' );
		if ( isset( $map[ $m ] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $m ] ) . '</p></div>'; }
		if ( 'dslerr' === $m ) {
			$e = get_transient( 'rk_forms_dsl_err' ); delete_transient( 'rk_forms_dsl_err' );
			echo '<div class="notice notice-error"><p><strong>Field errors:</strong><br>' . esc_html( is_array( $e ) ? implode( "\n", $e ) : (string) $e ) . '</p></div>';
		}
	}

	/* ---------------- Forms: list + editor ---------------- */

	public function screen_forms() {
		if ( ! current_user_can( self::CAP ) ) { return; }
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		if ( 'new' === $action || 'edit' === $action ) { $this->form_editor(); return; }
		$this->form_list();
	}

	private function form_list() {
		$this->head();
		$this->notice();
		$add = admin_url( 'admin.php?page=' . self::SLUG . '&action=new' );
		echo '<div class="rk-page-head"><div><h1>Forms</h1><p class="rk-sub">Build public forms and collect submissions in your inbox.</p></div>';
		echo '<a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Form</a></div>';

		$forms = RK_Forms_DB::all_forms();
		if ( ! $forms ) {
			echo '<div class="rk-empty"><p class="rk-empty-title">No forms yet</p><p class="rk-empty-sub">Create your first form to start collecting submissions.</p><a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Form</a></div>';
			$this->foot(); return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Title</th><th>Shortcode</th><th>Status</th><th>Submissions</th><th></th></tr></thead><tbody>';
		global $wpdb; $st = RK_Forms_DB::submissions_table();
		foreach ( $forms as $f ) {
			$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$st} WHERE form_id = %d", $f->id ) );
			$unread = RK_Forms_DB::unread_count( $f->id );
			$edit   = admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&id=' . $f->id );
			$subs   = admin_url( 'admin.php?page=' . self::SLUG_SUBS . '&form=' . $f->id );
			$del    = wp_nonce_url( admin_url( 'admin-post.php?action=rk_forms_delete&id=' . $f->id ), 'rk_forms_delete_' . $f->id );
			$badge  = 'published' === $f->status ? 'rk-modstate-on' : 'rk-modstate-off';
			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $edit ) . '">' . esc_html( $f->title ) . '</a></strong><br><span class="rk-sub">/' . esc_html( $f->slug ) . '</span></td>';
			echo '<td><code>[rk_form id="' . (int) $f->id . '"]</code></td>';
			echo '<td><span class="rk-modstate ' . esc_attr( $badge ) . '">' . esc_html( ucfirst( $f->status ) ) . '</span></td>';
			echo '<td><a href="' . esc_url( $subs ) . '">' . $count . '</a>' . ( $unread ? ' <span class="rk-form-unread">' . $unread . ' new</span>' : '' ) . '</td>';
			echo '<td class="rk-right"><a href="' . esc_url( $edit ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $subs ) . '">Inbox</a> &nbsp;·&nbsp; <a href="' . esc_url( $del ) . '" class="rk-danger" onclick="return confirm(\'Delete this form and all its submissions?\')">Delete</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		$this->foot();
	}

	private function form_editor() {
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$form = $id ? RK_Forms_DB::get_form( $id ) : null;
		$back = admin_url( 'admin.php?page=' . self::SLUG );
		$v = function ( $k, $d = '' ) use ( $form ) { return $form && isset( $form->$k ) ? $form->$k : $d; };

		$this->head();
		$this->notice();
		echo '<div class="pk-panel"><h3>' . ( $form ? 'Edit form — ' . esc_html( $form->title ) : 'Add form' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form-admin">';
		echo '<input type="hidden" name="action" value="rk_forms_save" />';
		echo '<input type="hidden" name="id" value="' . (int) $id . '" />';
		wp_nonce_field( 'rk_forms_save' );

		echo '<div class="rk-form-section"><h4>Basics</h4>';
		$this->row( 'Title', '<input type="text" name="title" value="' . esc_attr( $v( 'title' ) ) . '" required style="width:100%;max-width:520px;" />' );
		$this->row( 'Slug', '<input type="text" name="slug" value="' . esc_attr( $v( 'slug' ) ) . '" placeholder="auto from title" style="max-width:320px;" /> <span class="rk-form-hint">Used by the shortcode and URL.</span>' );
		$this->row( 'Description', '<textarea name="description" rows="2" style="width:100%;max-width:520px;">' . esc_textarea( $v( 'description' ) ) . '</textarea>' );
		$status = $v( 'status', 'draft' );
		$sel = '<select name="status">';
		foreach ( array( 'draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived' ) as $k => $lab ) { $sel .= '<option value="' . $k . '" ' . selected( $status, $k, false ) . '>' . $lab . '</option>'; }
		$sel .= '</select>';
		$this->row( 'Status', $sel . ' <span class="rk-form-hint">Only <strong>Published</strong> forms accept submissions.</span>' );
		echo '</div>';

		echo '<div class="rk-form-section"><h4>Fields</h4>';
		echo '<p class="rk-form-hint">One field per line: <code>type|name|label|required|placeholder|options</code>. Types: text, email, tel, url, number, date, time, textarea, select, radio, checkbox, checkboxes, heading, hidden. Options are comma-separated (select/radio/checkboxes).</p>';
		$dsl = $form ? RK_Forms_Fields::to_dsl( RK_Forms_Fields::decode( $form->fields_json ) ) : "text|name|Your name|required\nemail|email|Email address|required\ntextarea|message|Message|required";
		echo '<textarea name="fields_dsl" rows="10" class="rk-code" style="width:100%;font-family:monospace;">' . esc_textarea( $dsl ) . '</textarea>';
		echo '</div>';

		echo '<div class="rk-form-section"><h4>After submit</h4>';
		$this->row( 'Submit button label', '<input type="text" name="submit_label" value="' . esc_attr( $v( 'submit_label', 'Submit' ) ) . '" style="max-width:260px;" />' );
		$this->row( 'Success message', '<textarea name="success_message" rows="2" style="width:100%;max-width:520px;">' . esc_textarea( $v( 'success_message' ) ) . '</textarea>' );
		$this->row( 'Redirect URL', '<input type="url" name="redirect_url" value="' . esc_attr( $v( 'redirect_url' ) ) . '" placeholder="https://… (optional)" style="width:100%;max-width:520px;" />' );
		echo '</div>';

		echo '<div class="rk-form-section"><h4>Notifications</h4>';
		$this->row( 'Notify email', '<input type="text" name="notify_email" value="' . esc_attr( $v( 'notify_email' ) ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" style="max-width:360px;" /> <span class="rk-form-hint">Where new submissions are emailed.</span>' );
		$cs = $v( 'confirm_submitter', 0 );
		$this->row( 'Confirmation to submitter', '<label><input type="checkbox" name="confirm_submitter" value="1" ' . checked( $cs, 1, false ) . ' /> Send a confirmation email to the person who filled the form</label>' );
		$this->row( 'Confirmation subject', '<input type="text" name="confirm_subject" value="' . esc_attr( $v( 'confirm_subject' ) ) . '" style="width:100%;max-width:520px;" />' );
		$this->row( 'Confirmation body', '<textarea name="confirm_body" rows="3" style="width:100%;max-width:520px;">' . esc_textarea( $v( 'confirm_body' ) ) . '</textarea><span class="rk-form-hint">Placeholders: {ref}, {title}.</span>' );
		echo '</div>';

		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><a class="button rk-savebar-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a><span class="rk-savebar-spacer"></span>';
		submit_button( $form ? 'Update form' : 'Create form', 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">Cancel</a></div></div>';
		echo '</form></div>';
		$this->foot();
	}

	private function row( $label, $html ) {
		echo '<div class="rk-form-row-a"><label class="rk-form-label-a">' . esc_html( $label ) . '</label><div class="rk-form-field-a">' . $html . '</div></div>';
	}

	public function do_save() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_forms_save' );
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$parsed = RK_Forms_Fields::parse_dsl( isset( $_POST['fields_dsl'] ) ? wp_unslash( $_POST['fields_dsl'] ) : '' );
		if ( ! empty( $parsed['errors'] ) ) {
			set_transient( 'rk_forms_dsl_err', $parsed['errors'], 60 );
			$redir = admin_url( 'admin.php?page=' . self::SLUG . '&action=' . ( $id ? 'edit&id=' . $id : 'new' ) . '&rk_msg=dslerr' );
			wp_safe_redirect( $redir ); exit;
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug  = isset( $_POST['slug'] ) && '' !== trim( (string) $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title( $title );
		$slug  = RK_Forms_DB::unique_slug( $slug ?: 'form', $id );

		$data = array(
			'title'             => $title,
			'slug'              => $slug,
			'description'       => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'fields_json'       => wp_json_encode( $parsed['fields'] ),
			'submit_label'      => isset( $_POST['submit_label'] ) ? sanitize_text_field( wp_unslash( $_POST['submit_label'] ) ) : 'Submit',
			'success_message'   => isset( $_POST['success_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['success_message'] ) ) : '',
			'redirect_url'      => isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '',
			'notify_email'      => isset( $_POST['notify_email'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_email'] ) ) : '',
			'confirm_submitter' => empty( $_POST['confirm_submitter'] ) ? 0 : 1,
			'confirm_subject'   => isset( $_POST['confirm_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_subject'] ) ) : '',
			'confirm_body'      => isset( $_POST['confirm_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['confirm_body'] ) ) : '',
			'status'            => in_array( ( isset( $_POST['status'] ) ? $_POST['status'] : '' ), array( 'draft', 'published', 'archived' ), true ) ? $_POST['status'] : 'draft',
		);
		$id = RK_Forms_DB::save_form( $id, $data );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&id=' . $id . '&rk_msg=saved' ) ); exit;
	}

	public function do_delete() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_forms_delete_' . $id );
		RK_Forms_DB::delete_form( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&rk_msg=deleted' ) ); exit;
	}

	/* ---------------- Submissions inbox ---------------- */

	public function screen_subs() {
		if ( ! current_user_can( self::CAP ) ) { return; }
		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
		if ( $view ) { $this->sub_detail( $view ); return; }
		$this->sub_list();
	}

	private function sub_list() {
		$form_id = isset( $_GET['form'] ) ? (int) $_GET['form'] : 0;
		$unread  = ! empty( $_GET['unread'] );
		$this->head();
		$this->notice();

		echo '<div class="rk-page-head"><div><h1>Submissions</h1><p class="rk-sub">Everything people have sent through your forms.</p></div>';
		if ( $form_id ) {
			$exp = wp_nonce_url( admin_url( 'admin-post.php?action=rk_forms_export&form=' . $form_id ), 'rk_forms_export_' . $form_id );
			echo '<a class="button" href="' . esc_url( $exp ) . '">Export CSV</a>';
		}
		echo '</div>';

		// Filter by form.
		echo '<form method="get" class="rk-form-filter"><input type="hidden" name="page" value="' . esc_attr( self::SLUG_SUBS ) . '" />';
		echo '<select name="form" onchange="this.form.submit()"><option value="0">All forms</option>';
		foreach ( RK_Forms_DB::all_forms() as $f ) { echo '<option value="' . (int) $f->id . '" ' . selected( $form_id, $f->id, false ) . '>' . esc_html( $f->title ) . '</option>'; }
		echo '</select> ';
		echo '<label><input type="checkbox" name="unread" value="1" ' . checked( $unread, true, false ) . ' onchange="this.form.submit()" /> Unread only</label>';
		echo '</form>';

		$rows = RK_Forms_DB::submissions( $form_id, $unread );
		if ( ! $rows ) { echo '<div class="rk-empty"><p class="rk-empty-title">No submissions yet</p></div>'; $this->foot(); return; }

		$titles = array();
		foreach ( RK_Forms_DB::all_forms() as $f ) { $titles[ $f->id ] = $f->title; }

		echo '<table class="widefat striped"><thead><tr><th>Ref</th><th>Form</th><th>From</th><th>Received</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$open = admin_url( 'admin.php?page=' . self::SLUG_SUBS . '&view=' . $r->id );
			$new  = $r->read_at ? '' : ' <span class="rk-form-unread">new</span>';
			echo '<tr class="' . ( $r->read_at ? '' : 'rk-sub-unread' ) . '">';
			echo '<td><a href="' . esc_url( $open ) . '"><code>' . esc_html( $r->ref ) . '</code></a>' . $new . '</td>';
			echo '<td>' . esc_html( isset( $titles[ $r->form_id ] ) ? $titles[ $r->form_id ] : ( '#' . $r->form_id ) ) . '</td>';
			echo '<td>' . esc_html( $r->submitter_email ? $r->submitter_email : '—' ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'M j, Y g:i a', $r->created_at ) ) . '</td>';
			echo '<td class="rk-right"><a href="' . esc_url( $open ) . '">Open</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		$this->foot();
	}

	private function sub_detail( $id ) {
		$s = RK_Forms_DB::get_submission( $id );
		if ( ! $s ) { $this->head(); echo '<p>Submission not found.</p>'; $this->foot(); return; }
		RK_Forms_DB::mark_read( $id );
		$form   = RK_Forms_DB::get_form( $s->form_id );
		$fields = $form ? RK_Forms_Fields::decode( $form->fields_json ) : array();
		$data   = json_decode( (string) $s->data_json, true );
		$data   = is_array( $data ) ? $data : array();
		$rows   = RK_Forms_Public::summary_rows( $fields, $data );
		$back   = admin_url( 'admin.php?page=' . self::SLUG_SUBS . ( $s->form_id ? '&form=' . $s->form_id : '' ) );
		$del    = wp_nonce_url( admin_url( 'admin-post.php?action=rk_forms_sub_delete&id=' . $s->id ), 'rk_forms_sub_delete_' . $s->id );

		$this->head();
		echo '<a class="rk-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back to submissions</a>';
		echo '<div class="pk-panel"><h3>Submission ' . esc_html( $s->ref ) . '</h3>';
		echo '<table class="widefat rk-kv"><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><th style="width:220px;">' . esc_html( $r['label'] ) . '</th><td>' . nl2br( esc_html( $r['value'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h4 style="margin-top:20px;">Meta</h4><table class="widefat rk-kv"><tbody>';
		echo '<tr><th>Form</th><td>' . esc_html( $form ? $form->title : ( '#' . $s->form_id ) ) . '</td></tr>';
		echo '<tr><th>Received</th><td>' . esc_html( mysql2date( 'M j, Y g:i a', $s->created_at ) ) . '</td></tr>';
		echo '<tr><th>Email</th><td>' . esc_html( $s->submitter_email ? $s->submitter_email : '—' ) . '</td></tr>';
		echo '<tr><th>IP</th><td>' . esc_html( $s->ip ) . '</td></tr>';
		echo '<tr><th>Admin email sent</th><td>' . ( $s->email_sent ? 'Yes' : ( 'No' . ( $s->email_error ? ' — ' . esc_html( $s->email_error ) : '' ) ) ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p style="margin-top:18px;"><a class="button rk-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete this submission?\')">Delete submission</a></p>';
		echo '</div>';
		$this->foot();
	}

	public function do_sub_delete() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_forms_sub_delete_' . $id );
		$s = RK_Forms_DB::get_submission( $id );
		$form_id = $s ? (int) $s->form_id : 0;
		RK_Forms_DB::delete_submission( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_SUBS . ( $form_id ? '&form=' . $form_id : '' ) . '&rk_msg=sub_deleted' ) ); exit;
	}

	/** Neutralize spreadsheet formula injection in a CSV cell. */
	private static function csv_safe( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) { $v = "'" . $v; }
		return $v;
	}

	public function do_export() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'nope' ); }
		$form_id = isset( $_GET['form'] ) ? (int) $_GET['form'] : 0;
		check_admin_referer( 'rk_forms_export_' . $form_id );
		$form   = RK_Forms_DB::get_form( $form_id );
		$fields = $form ? RK_Forms_Fields::decode( $form->fields_json ) : array();
		$cols   = array();
		foreach ( $fields as $f ) { if ( ! in_array( ( isset( $f['type'] ) ? $f['type'] : '' ), array( 'heading' ), true ) ) { $cols[ $f['name'] ] = isset( $f['label'] ) ? $f['label'] : $f['name']; } }

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( ( $form ? $form->slug : 'form' ) . '-submissions.csv' ) . '"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_merge( array( 'Ref', 'Received', 'Email' ), array_values( $cols ) ) );
		foreach ( RK_Forms_DB::submissions( $form_id, false, 100000 ) as $r ) {
			$d = json_decode( (string) $r->data_json, true ); $d = is_array( $d ) ? $d : array();
			$line = array( self::csv_safe( $r->ref ), $r->created_at, self::csv_safe( $r->submitter_email ) );
			foreach ( array_keys( $cols ) as $k ) {
				$v = isset( $d[ $k ] ) ? $d[ $k ] : '';
				if ( is_array( $v ) ) { $v = implode( ', ', $v ); }
				$line[] = self::csv_safe( $v );
			}
			fputcsv( $out, $line );
		}
		fclose( $out );
		exit;
	}
}
