<?php
/**
 * RK_Theme_Admin — Headers / Footers / Import-Export screens for the RK Theme
 * builder. Same visual language as the other RK modules.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Theme_Admin {

	const SLUG    = 'rk-theme';
	const SLUG_HF = 'rk-theme-footers';
	const SLUG_TPL = 'rk-theme-templates';
	const SLUG_IE = 'rk-theme-tools';
	const SLUG_ED = 'rk-theme-edit';

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		foreach ( array( 'rk_theme_add', 'rk_theme_conditions', 'rk_theme_behavior', 'rk_theme_delete', 'rk_theme_export', 'rk_theme_import' ) as $a ) {
			add_action( 'admin_post_' . $a, array( $this, str_replace( 'rk_theme_', 'do_', $a ) ) );
		}
	}

	public function menu() {
		add_menu_page( 'RK Theme', 'RK Theme', 'manage_options', self::SLUG, array( $this, 'screen_headers' ), 'dashicons-cover-image', 60 );
		add_submenu_page( self::SLUG, 'Headers', 'Headers', 'manage_options', self::SLUG, array( $this, 'screen_headers' ) );
		add_submenu_page( self::SLUG, 'Footers', 'Footers', 'manage_options', self::SLUG_HF, array( $this, 'screen_footers' ) );
		add_submenu_page( self::SLUG, 'Theme Templates', 'Theme Templates', 'manage_options', self::SLUG_TPL, array( $this, 'screen_templates' ) );
		add_submenu_page( self::SLUG, 'Import / Export', 'Import / Export', 'manage_options', self::SLUG_IE, array( $this, 'screen_tools' ) );
		add_submenu_page( '', 'Edit template', 'Edit template', 'manage_options', self::SLUG_ED, array( $this, 'screen_edit' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rk-theme' ) ) { return; }
		$css = RK_THEME_DIR . 'assets/admin.css';
		wp_enqueue_style( 'rk-theme-admin', RK_THEME_URL . 'assets/admin.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_THEME_VERSION );
	}

	/* ---------------- shell ---------------- */

	private function sections() {
		return array(
			self::SLUG    => array( 'Headers', 'dashicons-arrow-up-alt' ),
			self::SLUG_HF => array( 'Footers', 'dashicons-arrow-down-alt' ),
			self::SLUG_IE => array( 'Import / Export', 'dashicons-migrate' ),
		);
	}

	private function head( $current, $title, $sub ) {
		$linked = class_exists( 'RK_Suite' );
		echo '<div class="wrap rk-theme-wrap rk-has-rail">';
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); } else {
		echo '<aside class="rk-rail">';
		echo '<div class="rk-rail-brand"><div class="pk-logo">RK</div><div><div class="pk-brand">RK Theme <span class="pk-ver">v' . esc_html( RK_THEME_VERSION ) . '</span></div><div class="pk-tag">' . esc_html( $sub ) . '</div></div></div>';
		echo '<nav class="rk-subnav rk-rail-nav">';
		foreach ( $this->sections() as $slug => $meta ) {
			$active = ( $current === $slug ) ? ' is-active' : '';
			echo '<a class="rk-subnav-item' . $active . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="dashicons ' . esc_attr( $meta[1] ) . '"></span> ' . esc_html( $meta[0] ) . '</a>';
		}
		echo '</nav>';
		echo '<div class="rk-rail-foot">' . ( $linked ? '<span class="pk-status pk-status-ok"><span class="pk-dot"></span> RK Suite</span>' : '<span class="pk-status pk-status-off"><span class="pk-dot"></span> Standalone</span>' ) . '</div>';
		echo '</aside>';
		}
		echo '<main class="rk-main">';
		$this->notice();
	}

	private function foot() { echo '</main></div>'; }

	private function notice() {
		if ( ! isset( $_GET['rk_msg'] ) ) { return; }
		$code = sanitize_key( $_GET['rk_msg'] );
		$rep  = get_transient( 'rk_theme_report' );
		$map  = array(
			'added'     => array( 'success', 'Template created.' ),
			'saved'     => array( 'success', 'Display conditions saved.' ),
			'deleted'   => array( 'success', 'Template deleted.' ),
			'imported'  => array( 'success', $rep ? esc_html( implode( ', ', (array) $rep ) ) . '.' : 'Imported.' ),
			'err'       => array( 'error', get_transient( 'rk_theme_err' ) ? get_transient( 'rk_theme_err' ) : 'Something went wrong.' ),
		);
		if ( isset( $map[ $code ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $code ][1] ) . '</p></div>';
			if ( 'err' === $code ) { delete_transient( 'rk_theme_err' ); }
			if ( 'imported' === $code ) { delete_transient( 'rk_theme_report' ); }
		}
		$theme = wp_get_theme();
		if ( 'hello-elementor' !== $theme->get_template() && ! function_exists( 'hello_elementor_setup' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Heads up:</strong> your active theme is not Hello Elementor. RK headers/footers are injected, but your theme’s own header/footer may still show — disable them in your theme settings for a clean replace.</p></div>';
		}
	}

	/* ---------------- list screens ---------------- */

	public function screen_headers() { $this->render_list( 'header', self::SLUG, 'Headers', 'Header templates' ); }
	public function screen_footers() { $this->render_list( 'footer', self::SLUG_HF, 'Footers', 'Footer templates' ); }

	public function screen_templates() {
		$this->head( self::SLUG_TPL, 'RK Theme', 'Single, archive, search & 404 templates' );
		echo '<div class="rk-page-head"><h3>Theme templates</h3></div>';
		echo '<p class="rk-muted rk-mb">Build a body template in Elementor (use RK dynamic fields for the current post), then set where it applies under Conditions.</p>';
		echo '<p>';
		foreach ( RK_Theme_Store::body_types() as $t => $lab ) {
			$add = wp_nonce_url( admin_url( 'admin-post.php?action=rk_theme_add&type=' . $t ), 'rk_theme_add' );
			echo '<a class="button button-primary" style="margin:0 6px 6px 0;" href="' . esc_url( $add ) . '">+ New ' . esc_html( $lab ) . '</a>';
		}
		echo '</p>';

		$items = array();
		foreach ( array_keys( RK_Theme_Store::body_types() ) as $t ) { $items = array_merge( $items, RK_Theme_Store::all( $t ) ); }
		if ( ! $items ) {
			echo '<div class="rk-empty"><span class="dashicons dashicons-media-document"></span><p>No theme templates yet.</p></div>';
			$this->foot(); return;
		}
		$types = RK_Theme_Store::all_types();
		$labels = RK_Theme_Conditions::catalog();
		echo '<table class="widefat rk-table"><thead><tr><th>Name</th><th>Type</th><th>Displays on</th><th>Status</th><th class="rk-col-act">Actions</th></tr></thead><tbody>';
		foreach ( $items as $p ) {
			$t     = RK_Theme_Store::type_of( $p->ID );
			$conds = RK_Theme_Store::conditions_of( $p->ID );
			$names = array();
			foreach ( $conds as $c ) { $names[] = isset( $labels[ $c ] ) ? $labels[ $c ] : $c; }
			$edit_el  = RK_Theme_Store::edit_url( $p->ID );
			$cond_url = admin_url( 'admin.php?page=' . self::SLUG_ED . '&id=' . $p->ID );
			$exp = wp_nonce_url( admin_url( 'admin-post.php?action=rk_theme_export&id=' . $p->ID ), 'rk_theme_export_' . $p->ID );
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=rk_theme_delete&id=' . $p->ID ), 'rk_theme_delete_' . $p->ID );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $p->post_title ) . '</strong></td>';
			echo '<td><code>' . esc_html( isset( $types[ $t ] ) ? $types[ $t ] : $t ) . '</code></td>';
			echo '<td>' . esc_html( implode( ', ', $names ) ) . '</td>';
			echo '<td>' . ( 'publish' === $p->post_status ? '<span class="rk-badge rk-badge-ok">Live</span>' : '<span class="rk-badge">Draft</span>' ) . '</td>';
			echo '<td class="rk-col-act"><a class="button button-primary" href="' . esc_url( $edit_el ) . '">Edit</a> <a class="button" href="' . esc_url( $cond_url ) . '">Conditions</a> <a class="button" href="' . esc_url( $exp ) . '">Export</a> <a class="button rk-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete this template?\')">Delete</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		$this->foot();
	}

	private function render_list( $type, $slug, $title, $sub ) {
		$this->head( $slug, 'RK Theme', $sub );
		$add = wp_nonce_url( admin_url( 'admin-post.php?action=rk_theme_add&type=' . $type ), 'rk_theme_add' );
		echo '<div class="rk-page-head"><h3>' . esc_html( $title ) . '</h3><a class="button button-primary" href="' . esc_url( $add ) . '">+ New ' . esc_html( ucfirst( $type ) ) . '</a></div>';

		$items = RK_Theme_Store::all( $type );
		if ( ! $items ) {
			echo '<div class="rk-empty"><span class="dashicons ' . ( 'header' === $type ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt' ) . '"></span><p>No ' . esc_html( $type ) . ' templates yet. Create one, design it in Elementor, then set where it appears.</p><p style="margin-top:14px;"><a class="button button-primary" href="' . esc_url( $add ) . '">+ New ' . esc_html( ucfirst( $type ) ) . '</a></p></div>';
			$this->foot(); return;
		}
		echo '<table class="widefat rk-table"><thead><tr><th>Name</th><th>Displays on</th><th>Status</th><th class="rk-col-act">Actions</th></tr></thead><tbody>';
		$labels = RK_Theme_Conditions::catalog();
		foreach ( $items as $p ) {
			$conds = RK_Theme_Store::conditions_of( $p->ID );
			$names = array();
			foreach ( $conds as $c ) { $names[] = isset( $labels[ $c ] ) ? $labels[ $c ] : $c; }
			$edit_el = RK_Theme_Store::edit_url( $p->ID );
			$cond_url = admin_url( 'admin.php?page=' . self::SLUG_ED . '&id=' . $p->ID );
			$exp = wp_nonce_url( admin_url( 'admin-post.php?action=rk_theme_export&id=' . $p->ID ), 'rk_theme_export_' . $p->ID );
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=rk_theme_delete&id=' . $p->ID ), 'rk_theme_delete_' . $p->ID );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $p->post_title ) . '</strong></td>';
			echo '<td>' . esc_html( implode( ', ', $names ) ) . '</td>';
			echo '<td>' . ( 'publish' === $p->post_status ? '<span class="rk-badge rk-badge-ok">Live</span>' : '<span class="rk-badge">Draft</span>' ) . '</td>';
			echo '<td class="rk-col-act">';
			echo '<a class="button button-primary" href="' . esc_url( $edit_el ) . '">Edit</a> ';
			echo '<a class="button" href="' . esc_url( $cond_url ) . '">Conditions</a> ';
			echo '<a class="button" href="' . esc_url( $exp ) . '">Export</a> ';
			echo '<a class="button rk-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete this template?\')">Delete</a>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		$this->foot();
	}

	/* ---------------- conditions editor ---------------- */

	public function screen_edit() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$p  = $id ? get_post( $id ) : null;
		$this->head( '', 'RK Theme', 'Display conditions' );
		if ( ! $p || RK_Theme_Store::CPT !== $p->post_type ) { echo '<div class="notice notice-error"><p>Template not found.</p></div>'; $this->foot(); return; }
		$type    = RK_Theme_Store::type_of( $id );
		$current = RK_Theme_Store::conditions_of( $id );
		$body    = array_key_exists( $type, RK_Theme_Store::body_types() );
		$back    = admin_url( 'admin.php?page=' . ( $body ? self::SLUG_TPL : ( 'footer' === $type ? self::SLUG_HF : self::SLUG ) ) );
		echo '<a class="rk-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a>';
		echo '<div class="rk-page-head"><h3>Where does “' . esc_html( $p->post_title ) . '” appear?</h3><a class="button button-primary" href="' . esc_url( RK_Theme_Store::edit_url( $id ) ) . '">Edit design</a></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-card rk-cond-form">';
		echo '<input type="hidden" name="action" value="rk_theme_conditions" />';
		echo '<input type="hidden" name="id" value="' . (int) $id . '" />';
		wp_nonce_field( 'rk_theme_conditions_' . $id );
		echo '<p class="rk-muted">Tick every place this ' . esc_html( $type ) . ' should show. If more than one template matches a page, the most specific one wins.</p>';
		echo '<div class="rk-cond-grid">';
		foreach ( RK_Theme_Conditions::catalog() as $val => $label ) {
			$ck = in_array( $val, $current, true ) ? ' checked' : '';
			echo '<label class="rk-check"><input type="checkbox" name="conditions[]" value="' . esc_attr( $val ) . '"' . $ck . ' /> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
		echo '<p><button class="button button-primary">Save conditions</button></p>';
		echo '</form>';

		if ( 'header' === $type ) { $this->header_behavior_card( $id ); }

		$this->foot();
	}

	/** Sticky / transparent / shrink options for a header template. */
	private function header_behavior_card( $id ) {
		$b = RK_Theme_Store::behavior_of( $id );
		echo '<div class="rk-page-head" style="margin-top:26px"><h3>Header behavior</h3></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-card rk-cond-form">';
		echo '<input type="hidden" name="action" value="rk_theme_behavior" />';
		echo '<input type="hidden" name="id" value="' . (int) $id . '" />';
		wp_nonce_field( 'rk_theme_behavior_' . $id );
		echo '<p class="rk-muted">These run with a tiny script that loads only on pages using this header — zero weight elsewhere.</p>';
		echo '<div class="rk-cond-grid">';
		$this->toggle( 'sticky', 'Sticky on scroll', $b['sticky'] );
		$this->toggle( 'shrink', 'Shrink logo &amp; padding on scroll', $b['shrink'] );
		$this->toggle( 'transparent', 'Transparent over hero (top of page)', $b['transparent'] );
		$this->toggle( 'shadow', 'Shadow once stuck', $b['shadow'] );
		echo '</div>';
		echo '<div class="rk-field-row">';
		echo '<label>Becomes solid after (px scrolled)<br><input type="number" name="offset" value="' . (int) $b['offset'] . '" min="0" max="1000" /></label>';
		echo '<label>Stuck background<br><input type="text" name="stuck_bg" value="' . esc_attr( $b['stuck_bg'] ) . '" placeholder="#ffffff" /></label>';
		echo '<label>Shrunk logo max height (px)<br><input type="number" name="logo_shrink" value="' . (int) $b['logo_shrink'] . '" min="10" max="200" /></label>';
		echo '</div>';
		echo '<p><button class="button button-primary">Save header behavior</button></p>';
		echo '</form>';
	}

	private function toggle( $name, $label, $on ) {
		echo '<label class="rk-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . ( $on ? ' checked' : '' ) . ' /> ' . $label . '</label>';
	}

	/* ---------------- import / export ---------------- */

	public function screen_tools() {
		$this->head( self::SLUG_IE, 'RK Theme', 'Move headers &amp; footers between sites' );
		$all = RK_Theme_Store::all();
		echo '<div class="rk-tools-grid">';

		echo '<div class="rk-card rk-tool-card"><div class="rk-card-head"><span class="dashicons dashicons-download"></span><div><h3>Export templates</h3><p class="rk-muted">Each export is self-contained — design, settings &amp; display conditions travel together.</p></div></div>';
		if ( $all ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-tool-body">';
			echo '<input type="hidden" name="action" value="rk_theme_export" />';
			wp_nonce_field( 'rk_theme_export_bulk' );
			echo '<div class="rk-check-list">';
			foreach ( $all as $p ) {
				echo '<label class="rk-check"><input type="checkbox" name="ids[]" value="' . (int) $p->ID . '" checked /> ' . esc_html( $p->post_title ) . ' <code>' . esc_html( RK_Theme_Store::type_of( $p->ID ) ) . '</code></label>';
			}
			echo '</div><button class="button button-primary">Download .json</button></form>';
		} else {
			echo '<p class="rk-muted">No templates to export yet.</p>';
		}
		echo '</div>';

		echo '<div class="rk-card rk-tool-card"><div class="rk-card-head"><span class="dashicons dashicons-upload"></span><div><h3>Import templates</h3><p class="rk-muted">Upload an RK Theme file. New header/footer templates are created ready to display.</p></div></div>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-tool-body">';
		echo '<input type="hidden" name="action" value="rk_theme_import" />';
		wp_nonce_field( 'rk_theme_import' );
		echo '<input type="file" name="bundle" accept="application/json,.json" required />';
		echo '<button class="button button-primary">Import</button></form></div>';

		echo '</div>';
		$this->foot();
	}

	/* ---------------- handlers ---------------- */

	public function do_add() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_theme_add' );
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'header';
		$id = RK_Theme_Store::create( ucfirst( $type ), $type );
		if ( $id ) { wp_safe_redirect( RK_Theme_Store::edit_url( $id ) ); exit; }
		set_transient( 'rk_theme_err', 'Could not create the template.', 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&rk_msg=err' ) ); exit;
	}

	public function do_conditions() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_admin_referer( 'rk_theme_conditions_' . $id );
		$conds = ( isset( $_POST['conditions'] ) && is_array( $_POST['conditions'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['conditions'] ) ) : array();
		RK_Theme_Store::set_conditions( $id, $conds );
		$type = RK_Theme_Store::type_of( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . ( 'footer' === $type ? self::SLUG_HF : self::SLUG ) . '&rk_msg=saved' ) ); exit;
	}

	public function do_behavior() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_admin_referer( 'rk_theme_behavior_' . $id );
		RK_Theme_Store::set_behavior( $id, array(
			'sticky'      => isset( $_POST['sticky'] ),
			'shrink'      => isset( $_POST['shrink'] ),
			'transparent' => isset( $_POST['transparent'] ),
			'shadow'      => isset( $_POST['shadow'] ),
			'offset'      => isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 60,
			'stuck_bg'    => isset( $_POST['stuck_bg'] ) ? sanitize_text_field( wp_unslash( $_POST['stuck_bg'] ) ) : '#ffffff',
			'logo_shrink' => isset( $_POST['logo_shrink'] ) ? (int) $_POST['logo_shrink'] : 40,
		) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_ED . '&id=' . $id . '&rk_msg=saved' ) ); exit;
	}

	public function do_delete() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_theme_delete_' . $id );
		$type = RK_Theme_Store::type_of( $id );
		RK_Theme_Store::delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . ( 'footer' === $type ? self::SLUG_HF : self::SLUG ) . '&rk_msg=deleted' ) ); exit;
	}

	public function do_export() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		if ( isset( $_GET['id'] ) ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'rk_theme_export_' . $id );
			$ids = array( $id );
			$name = 'rk-theme-' . $id . '.json';
		} else {
			check_admin_referer( 'rk_theme_export_bulk' );
			$ids = ( isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ) ? array_map( 'intval', $_POST['ids'] ) : array();
			$name = 'rk-theme-templates.json';
		}
		if ( ! $ids ) {
			set_transient( 'rk_theme_err', 'Nothing selected to export.', 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=err' ) ); exit;
		}
		RK_Theme_Porter::download( RK_Theme_Porter::export( $ids ), $name );
	}

	public function do_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_theme_import' );
		if ( empty( $_FILES['bundle'] ) || ! isset( $_FILES['bundle']['tmp_name'] ) || ! is_uploaded_file( $_FILES['bundle']['tmp_name'] ) ) {
			set_transient( 'rk_theme_err', 'No file uploaded.', 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=err' ) ); exit;
		}
		$b = json_decode( (string) file_get_contents( $_FILES['bundle']['tmp_name'] ), true );
		if ( ! is_array( $b ) ) {
			set_transient( 'rk_theme_err', 'That file is not valid JSON.', 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=err' ) ); exit;
		}
		$report = RK_Theme_Porter::import( $b );
		set_transient( 'rk_theme_report', $report, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=imported' ) ); exit;
	}
}
