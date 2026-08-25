<?php
/**
 * RK_Library_Admin — backend manager: browse items by category, import bundles,
 * export, delete, add categories. Deliberately compact.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Library_Admin {

	const SLUG = 'rk-library';

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		foreach ( array( 'rk_library_import', 'rk_library_export', 'rk_library_delete', 'rk_library_addcat', 'rk_library_example' ) as $a ) {
			add_action( 'admin_post_' . $a, array( $this, str_replace( 'rk_library_', 'do_', $a ) ) );
		}
	}

	public function menu() {
		add_menu_page( 'RK Library', 'RK Library', 'manage_options', self::SLUG, array( $this, 'screen' ), 'dashicons-screenoptions', 62 );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) { return; }
		$css = RK_LIBRARY_DIR . 'assets/admin.css';
		wp_enqueue_style( 'rk-library-admin', RK_LIBRARY_URL . 'assets/admin.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_LIBRARY_VERSION );
	}

	public function screen() {
		$items = RK_Library_Store::all();
		$cats  = RK_Library_Store::categories();
		echo '<div class="wrap rk-lib-wrap rk-has-rail">';
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); } else {
		echo '<aside class="rk-rail">';
		echo '<div class="rk-rail-brand"><div class="pk-logo">RK</div><div><div class="pk-brand">RK Library <span class="pk-ver">v' . esc_html( RK_LIBRARY_VERSION ) . '</span></div><div class="pk-tag">Template library</div></div></div>';
		echo '<nav class="rk-subnav rk-rail-nav"><a class="rk-subnav-item is-active" href="' . esc_url( admin_url( 'admin.php?page=rk-library' ) ) . '">Library</a></nav>';
		echo '</aside>';
		}
		echo '<main class="rk-main">';
		$this->notice();

		echo '<div class="rk-lib-cols">';

		/* Import / export */
		echo '<div class="rk-lib-card"><h3>Import a bundle</h3>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_library_import" />';
		wp_nonce_field( 'rk_library_import' );
		echo '<p><input type="file" name="bundle" accept="application/json,.json" required /></p>';
		echo '<p><label>Default category if items lack one<br><select name="category"><option value="">— none —</option>';
		foreach ( $cats as $c ) { echo '<option>' . esc_html( $c ) . '</option>'; }
		echo '</select></label></p>';
		echo '<button class="button button-primary">Import</button> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rk_library_example' ), 'rk_library_example' ) ) . '">Download example bundle</a>';
		echo '</form></div>';

		/* Categories */
		echo '<div class="rk-lib-card"><h3>Categories</h3>';
		echo '<p class="rk-muted">' . esc_html( implode( ', ', $cats ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-lib-inline">';
		echo '<input type="hidden" name="action" value="rk_library_addcat" />';
		wp_nonce_field( 'rk_library_addcat' );
		echo '<input type="text" name="category" placeholder="New category" required /> <button class="button">Add</button>';
		echo '</form>';
		if ( $items ) {
			echo '<p style="margin-top:12px"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rk_library_export' ), 'rk_library_export' ) ) . '">Export all as bundle</a></p>';
		}
		echo '</div>';

		echo '</div>'; // cols

		/* Items */
		echo '<div class="rk-lib-page-head"><h3>Templates in your library (' . count( $items ) . ')</h3></div>';
		if ( ! $items ) {
			echo '<div class="rk-lib-empty">Nothing yet. Import a bundle above, or download the example bundle to see the format.</div>';
			echo '</main></div>'; return;
		}
		echo '<div class="rk-lib-grid">';
		foreach ( $items as $p ) {
			$thumb = RK_Library_Store::thumb_of( $p->ID );
			$del   = wp_nonce_url( admin_url( 'admin-post.php?action=rk_library_delete&id=' . $p->ID ), 'rk_library_delete_' . $p->ID );
			echo '<div class="rk-lib-item">';
			echo '<div class="rk-lib-thumb">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<span>' . esc_html( substr( $p->post_title, 0, 1 ) ) . '</span>' ) . '</div>';
			echo '<div class="rk-lib-item-meta"><strong>' . esc_html( $p->post_title ) . '</strong><span>' . esc_html( RK_Library_Store::cat_of( $p->ID ) ) . ' · ' . esc_html( RK_Library_Store::type_of( $p->ID ) ) . '</span></div>';
			echo '<a class="rk-lib-del" href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete this template?\')">Delete</a>';
			echo '</div>';
		}
		echo '</div>';
		echo '</main></div>';
	}

	private function notice() {
		if ( empty( $_GET['rk_msg'] ) ) { return; }
		$m = sanitize_key( $_GET['rk_msg'] );
		$rep = get_transient( 'rk_library_report' ); delete_transient( 'rk_library_report' );
		$map = array(
			'imported' => $rep ? $rep : 'Imported.',
			'deleted'  => 'Deleted.',
			'addedcat' => 'Category added.',
			'err'      => get_transient( 'rk_library_err' ) ? get_transient( 'rk_library_err' ) : 'Something went wrong.',
		);
		if ( isset( $map[ $m ] ) ) {
			$type = ( 'err' === $m ) ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $map[ $m ] ) . '</p></div>';
			if ( 'err' === $m ) { delete_transient( 'rk_library_err' ); }
		}
	}

	/* ---- handlers ---- */

	public function do_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_library_import' );
		if ( empty( $_FILES['bundle'] ) || ! is_uploaded_file( $_FILES['bundle']['tmp_name'] ) ) {
			set_transient( 'rk_library_err', 'No file uploaded.', 60 ); $this->back( 'err' );
		}
		$b = json_decode( (string) file_get_contents( $_FILES['bundle']['tmp_name'] ), true );
		if ( ! is_array( $b ) ) { set_transient( 'rk_library_err', 'That file is not valid JSON.', 60 ); $this->back( 'err' ); }
		$cat = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$r = RK_Library_Porter::import( $b, $cat );
		set_transient( 'rk_library_report', $r['msg'], 60 );
		$this->back( 'imported' );
	}

	public function do_export() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_library_export' );
		RK_Library_Porter::download( RK_Library_Porter::export(), 'rk-library-bundle.json' );
	}

	public function do_example() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_library_example' );
		RK_Library_Porter::download( RK_Library_Porter::example_bundle(), 'rk-library-example.json' );
	}

	public function do_delete() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_library_delete_' . $id );
		RK_Library_Store::delete( $id );
		$this->back( 'deleted' );
	}

	public function do_addcat() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_library_addcat' );
		if ( ! empty( $_POST['category'] ) ) { RK_Library_Store::add_category( sanitize_text_field( wp_unslash( $_POST['category'] ) ) ); }
		$this->back( 'addedcat' );
	}

	private function back( $msg ) { wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&rk_msg=' . $msg ) ); exit; }
}
