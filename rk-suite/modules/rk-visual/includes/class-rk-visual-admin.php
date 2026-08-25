<?php
/**
 * RK_Visual_Admin — settings screen for RK Visual Edit (folded under the RK menu).
 *
 * @package RK_Visual
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Visual_Admin {

	const SLUG = 'rk-visual';
	const CAP  = 'manage_options';

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_rk_visual_save_settings', array( $this, 'save' ) );
	}

	public function menu() {
		add_menu_page( 'RK Visual Edit', 'RK Visual Edit', self::CAP, self::SLUG, array( $this, 'screen' ), 'dashicons-edit', 63 );
	}

	public function save() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Nope.' ); }
		check_admin_referer( 'rk_visual_settings' );
		$vals = isset( $_POST['rkv'] ) && is_array( $_POST['rkv'] ) ? wp_unslash( $_POST['rkv'] ) : array();
		RK_Visual_Settings::update( $vals );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'rk_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function screen() {
		if ( ! current_user_can( self::CAP ) ) { return; }
		$s = RK_Visual_Settings::all();
		echo '<div class="wrap rk-visual-wrap rk-has-rail">';
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); }
		echo '<main class="rk-main">';

		if ( isset( $_GET['rk_msg'] ) && 'saved' === $_GET['rk_msg'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}

		echo '<div class="rk-page-head"><h3>RK Visual Edit</h3></div>';
		echo '<p class="rk-muted" style="margin-top:-6px;max-width:680px;">Edit page text, HTML-widget regions, images and links directly on the front end — no Elementor editor round-trip. Configure who can use it and which advanced tools are on.</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-card" style="max-width:680px;padding:20px;border:1px solid var(--line,#e8eaf1);border-radius:12px;background:#fff;margin-top:12px;">';
		echo '<input type="hidden" name="action" value="rk_visual_save_settings" />';
		wp_nonce_field( 'rk_visual_settings' );

		// Master switch.
		$this->toggle_row( 'enabled_front', 'Enable on the front end', 'Turn the on-page editor on for allowed users. Uncheck to pause it without disabling the whole module.', $s['enabled_front'] );

		// Capability.
		echo '<div class="rkv-field"><label class="rkv-label" for="rkv-cap">Who can edit</label>';
		echo '<select id="rkv-cap" name="rkv[cap]">';
		foreach ( RK_Visual_Settings::cap_choices() as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $s['cap'], $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="rkv-help">The minimum role allowed to use on-page editing. Per-post edit permission is still checked.</p></div>';

		echo '<hr style="border:none;border-top:1px solid var(--line,#e8eaf1);margin:18px 0;">';
		echo '<h4 style="margin:0 0 4px;">Advanced tools</h4>';
		$this->toggle_row( 'rich', 'Rich-text toolbar', 'Bold, italic, underline and link buttons while editing text.', $s['rich'] );
		$this->toggle_row( 'html_regions', 'Editable HTML-widget regions', 'Lets you edit text wrapped in <code>&lt;span data-rk-edit="key"&gt;…&lt;/span&gt;</code> inside an HTML widget, leaving the rest of the code untouched.', $s['html_regions'] );
		$this->toggle_row( 'history', 'Undo &amp; edit history', 'Keep a per-page history so you can revert the last inline change.', $s['history'] );
		$this->toggle_row( 'media', 'Image &amp; link editing', 'Swap an image from the media library and edit a button/link URL inline.', $s['media'] );

		echo '<div class="rk-savebar" style="margin-top:20px;"><button type="submit" class="button button-primary">Save settings</button><span class="rk-savebar-hint" style="margin-left:12px;color:var(--muted,#6b7183);">Changes apply immediately.</span></div>';
		echo '</form>';

		echo '<p class="rk-muted" style="max-width:680px;margin-top:14px;font-size:12.5px;">Tip: open any page on the front end while logged in — an <strong>Edit visually</strong> button appears in the admin bar.</p>';

		echo '</main></div>';

		// Minimal, self-contained styles for the settings rows.
		echo '<style>
		.rk-visual-wrap .rkv-field{ margin:14px 0; }
		.rk-visual-wrap .rkv-label{ display:block; font-weight:600; margin-bottom:6px; }
		.rk-visual-wrap .rkv-help{ margin:6px 0 0; color:var(--muted,#6b7183); font-size:12.5px; }
		.rk-visual-wrap .rkv-toggle{ display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid var(--line,#eef0f6); }
		.rk-visual-wrap .rkv-toggle:last-of-type{ border-bottom:none; }
		.rk-visual-wrap .rkv-toggle input{ margin-top:2px; }
		.rk-visual-wrap .rkv-toggle .rkv-t-main{ font-weight:600; }
		.rk-visual-wrap .rkv-toggle .rkv-t-desc{ color:var(--muted,#6b7183); font-size:12.5px; margin-top:2px; }
		</style>';
	}

	/** Render a checkbox toggle row. */
	private function toggle_row( $key, $title, $desc, $on ) {
		echo '<label class="rkv-toggle"><input type="checkbox" name="rkv[' . esc_attr( $key ) . ']" value="1"' . checked( $on, 1, false ) . ' />';
		echo '<span><span class="rkv-t-main">' . wp_kses_post( $title ) . '</span><span class="rkv-t-desc">' . wp_kses_post( $desc ) . '</span></span></label>';
	}
}
