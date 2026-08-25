<?php
/**
 * RK_Migrate_Admin — tabbed admin UI orchestrating every module.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Admin {

	const SLUG = 'rk-migrate';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_rk_migrate_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_rk_migrate_run', array( $this, 'handle_run' ) ); // non-JS fallback
		add_action( 'admin_post_rk_migrate_library', array( $this, 'handle_library' ) );
		add_action( 'admin_post_rk_migrate_remote', array( $this, 'handle_remote' ) );
		add_action( 'admin_post_rk_migrate_settings', array( $this, 'handle_settings' ) );
		add_action( 'admin_post_rk_migrate_localize', array( $this, 'handle_localize' ) );
		add_action( 'admin_post_rk_migrate_marketplace', array( $this, 'handle_marketplace' ) );
		add_action( 'admin_post_rk_migrate_cloud', array( $this, 'handle_cloud' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	private function s() { return RK_Migrate_Settings::instance(); }

	public function menu() {
		$cap = $this->s()->get( 'cap_view_log', 'manage_options' );
		add_menu_page(
			$this->s()->brand_name() . ' Deploy',
			$this->s()->brand_name(),
			$cap,
			self::SLUG,
			array( $this, 'screen' ),
			'dashicons-database-import',
			65
		);
		// Sidebar submenu (flyout) — one item per tab, like other plugins.
		$labels = array(
			'import'   => 'Import',
			'export'   => 'Export',
			'library'  => 'Library',
			'history'  => 'History & Rollback',
			'doctor'   => 'Site Doctor',
			'builder'  => 'Manifest Builder',
			'remote'   => 'Remote',
			'market'   => 'Marketplace',
			'settings' => 'Settings',
			'help'     => 'Help & Docs',
		);
		foreach ( $labels as $key => $label ) {
			if ( 'import' === $key ) {
				// First item shares the parent slug so it renders the screen and renames the duplicate.
				add_submenu_page( self::SLUG, $this->s()->brand_name() . ' — Import', $label, $cap, self::SLUG, array( $this, 'screen' ) );
			} else {
				// Direct link to the same screen with the tab pre-selected.
				add_submenu_page( self::SLUG, $this->s()->brand_name() . ' — ' . $label, $label, $cap, 'admin.php?page=' . self::SLUG . '&tab=' . $key );
			}
		}
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) ) { return; }
		wp_enqueue_script( 'jquery' );
	}

	private function tabs() {
		return array(
			'import'    => 'Import',
			'export'    => 'Export',
			'library'   => 'Library',
			'history'   => 'History &amp; Rollback',
			'doctor'    => 'Site Doctor',
			'media'     => 'Media',
			'builder'   => 'Manifest Builder',
			'remote'    => 'Remote',
			'market'    => 'Marketplace',
			'settings'  => 'Settings',
			'help'      => 'Help &amp; Docs',
		);
	}

	private function current_tab() {
		$t = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'import';
		return array_key_exists( $t, $this->tabs() ) ? $t : 'import';
	}

	private function tab_url( $tab ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab );
	}

	private function active_base() {
		$uploaded = get_option( 'rk_migrate_active_bundle', '' );
		if ( $uploaded && file_exists( trailingslashit( $uploaded ) . 'manifest.json' ) ) { return $uploaded; }
		if ( file_exists( RK_MIGRATE_BUNDLED_DATA . 'manifest.json' ) ) { return RK_MIGRATE_BUNDLED_DATA; }
		return '';
	}

	private function source_label() {
		$uploaded = get_option( 'rk_migrate_active_bundle', '' );
		if ( $uploaded && file_exists( trailingslashit( $uploaded ) . 'manifest.json' ) ) { return 'Uploaded/selected bundle: ' . basename( $uploaded ); }
		// no sample data ships with RK Migrate
		return 'None — upload a bundle or pick one from the Library';
	}

	public function screen() {
		if ( ! $this->s()->current_user_can( 'view_log' ) ) { wp_die( 'Insufficient permissions.' ); }
		$tab  = $this->current_tab();
		$tier = $this->s()->get( 'tier' );
		$has_elementor = class_exists( '\Elementor\Plugin' );
		$this->print_css();
		echo '<div class="wrap rk-migrate-wrap rk-has-rail">';

		// ---- left rail ----
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); } else {
		echo '<aside class="pk-rail">';
		echo '<div class="pk-rail-brand"><div class="pk-logo">P</div><div class="pk-rail-id"><div class="pk-brand">' . esc_html( $this->s()->brand_name() ) . '</div><div class="pk-rail-sub">v' . esc_html( RK_MIGRATE_VERSION ) . ' &middot; Migration Kit</div></div></div>';
		echo '<nav class="pk-railnav">';
		foreach ( $this->tabs() as $key => $label ) {
			$cls = ( $key === $tab ) ? ' pk-railitem-on' : '';
			echo '<a href="' . esc_url( $this->tab_url( $key ) ) . '" class="pk-railitem' . $cls . '">' . $this->icon( $key ) . '<span>' . wp_kses_post( $label ) . '</span></a>';
		}
		echo '</nav>';
		echo '<div class="pk-rail-foot">';
		echo $has_elementor
			? '<span class="pk-status pk-status-ok"><span class="pk-dot"></span>Elementor detected</span>'
			: '<span class="pk-status pk-status-off"><span class="pk-dot"></span>Elementor inactive</span>';
		echo '<span class="pk-tier pk-tier-' . esc_attr( $tier ) . '">' . esc_html( ucfirst( $tier ) ) . ' tier</span>';
		echo '<div class="pk-rail-credit">' . wp_kses_post( $this->s()->credit_html() ) . '</div>';
		echo '</div></aside>';
		}

		// ---- main column ----
		echo '<main class="rk-main">';
		list( $title, $sub ) = $this->tab_meta( $tab );
		echo '<div class="pk-pagehead"><div class="pk-pagehead-text"><h1>' . $this->icon( $tab ) . wp_kses_post( $title ) . '</h1><p>' . esc_html( $sub ) . '</p></div>';
		echo '<a class="pk-btn-ghost" href="' . esc_url( $this->tab_url( 'help' ) ) . '">' . $this->icon( 'help' ) . ' Docs</a></div>';

		if ( ! $has_elementor ) {
			echo '<div class="pk-alert pk-alert-warn">' . $this->icon( 'alert' ) . '<div><strong>Elementor is not active.</strong> Activate it (and Elementor Pro for header/footer auto-assign) for full functionality.</div></div>';
		}

		echo '<div class="pk-panel">';
		switch ( $tab ) {
			case 'export':   $this->tab_export(); break;
			case 'library':  $this->tab_library(); break;
			case 'history':  $this->tab_history(); break;
			case 'doctor':   $this->tab_doctor(); break;
			case 'media':    $this->tab_media(); break;
			case 'builder':  $this->tab_builder(); break;
			case 'remote':   $this->tab_remote(); break;
			case 'market':   $this->tab_market(); break;
			case 'settings': $this->tab_settings(); break;
			case 'help':     $this->tab_help(); break;
			default:         $this->tab_import(); break;
		}
		echo '</div>'; // .pk-panel
		echo '</main></div>';
		$this->print_js();
	}

	/** Per-tab page header title + subtitle. */
	private function tab_meta( $tab ) {
		$map = array(
			'import'   => array( 'Import', 'Upload a bundle and deploy it to this site.' ),
			'export'   => array( 'Export', 'Package this site into a portable bundle.' ),
			'library'  => array( 'Library', 'Stored master bundles you can reuse.' ),
			'history'  => array( 'History &amp; Rollback', 'Audit every run and restore snapshots.' ),
			'doctor'   => array( 'Site Doctor', 'Scan, fix colors/fonts, convert sections, audit links &amp; SEO.' ),
			'media'    => array( 'Media', 'Pull remote images local for faster, cacheable loads.' ),
			'builder'  => array( 'Manifest Builder', 'Edit a bundle&rsquo;s manifest visually.' ),
			'remote'   => array( 'Remote', 'Push and pull bundles between sites.' ),
			'market'   => array( 'Marketplace', 'Install full-site starter bundles.' ),
			'settings' => array( 'Settings', 'Tier, access, integrations, and notifications.' ),
			'help'     => array( 'Help &amp; Docs', 'Quick start, references, and FAQ.' ),
		);
		return isset( $map[ $tab ] ) ? $map[ $tab ] : array( 'RK Migrate', '' );
	}

	/** Inline stroke-SVG icon by name. */
	private function icon( $name ) {
		$p = array(
			'import'   => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M5 21h14"/>',
			'export'   => '<path d="M12 21V9"/><path d="M7 13l5-5 5 5"/><path d="M5 3h14"/>',
			'library'  => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
			'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/>',
			'builder'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
			'remote'   => '<path d="M4 8h13l-3-3"/><path d="M20 16H7l3 3"/>',
			'market'   => '<path d="M12 3l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.9 6.8 19l1-5.8L3.6 9.2l5.8-.9z"/>',
			'settings' => '<path d="M4 6h10"/><path d="M18 6h2"/><path d="M4 12h2"/><path d="M10 12h10"/><path d="M4 18h7"/><path d="M15 18h5"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="13" cy="18" r="2"/>',
			'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .9-1 1.7"/><path d="M12 16.5h.01"/>',
			'doctor'   => '<path d="M3 12h4l2 5 4-14 2 7h6"/>',
			'media'    => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="1.6"/><path d="m3 17 5-4 4 3 3-2 6 5"/>',
			'palette'  => '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2a10 10 0 1 0 0 20c1.1 0 2-.9 2-2 0-1-1-1.5-1-2.5 0-.8.7-1.5 1.5-1.5H17a5 5 0 0 0 5-5c0-5-4.5-9-10-9z"/>',
			'type'     => '<path d="M4 7V5h16v2"/><path d="M9 19h6"/><path d="M12 5v14"/>',
			'corners'  => '<path d="M4 13V9a5 5 0 0 1 5-5h4"/><rect x="11" y="11" width="9" height="9" rx="3"/>',
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'alert'    => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
			// stat icons
			'layers'   => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
			'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
			'refresh'  => '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
			'blocks'   => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>',
			'star'     => '<path d="M12 3l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.9 6.8 19l1-5.8L3.6 9.2l5.8-.9z"/>',
			// settings section icons
			'tier'     => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
			'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
			'bell'     => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
			'sparkles' => '<path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8z"/>',
			'cloud'    => '<path d="M7 18a4 4 0 0 1 0-8 5 5 0 0 1 9.6-1A4 4 0 0 1 17 18z"/>',
		);
		$path = isset( $p[ $name ] ) ? $p[ $name ] : '<circle cx="12" cy="12" r="9"/>';
		return '<svg class="pk-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
	}

	/* =================== IMPORT TAB =================== */
	private function tab_import() {
		$base = $this->active_base();
		echo '<h3>1. Project source</h3>';
		echo '<p>Active source: <strong>' . esc_html( $this->source_label() ) . '</strong></p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_migrate_upload" />';
		wp_nonce_field( 'rk_migrate_upload' );
		echo '<p>Upload a project bundle (<code>.zip</code> with <code>manifest.json</code> + JSON files), or a third-party <strong>Elementor template kit</strong> (Envato / Creativemox) — RK Migrate auto-detects and converts it. Encrypted <code>.epenc</code> bundles supported.</p>';
		echo '<input type="file" name="bundle" accept=".zip,.epenc" required /> ';
		echo '<input type="text" name="decrypt_pw" placeholder="password (if encrypted)" /> ';
		submit_button( 'Upload &amp; Select', 'secondary', 'submit', false );
		echo '</form>';
		$uploaded = get_option( 'rk_migrate_active_bundle', '' );
		if ( $uploaded ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px;"><input type="hidden" name="action" value="rk_migrate_upload" /><input type="hidden" name="clear" value="1" />';
			wp_nonce_field( 'rk_migrate_upload' );
			submit_button( 'Clear active bundle', 'link-delete', 'submit', false );
			echo '</form>';
		}

		if ( ! $base ) { echo '<div class="notice notice-error inline"><p>No valid project source. Upload or select a bundle.</p></div>'; return; }
		$importer = new RK_Migrate_Importer( $base );
		$m = $importer->get_manifest();
		if ( ! $m ) { echo '<div class="notice notice-error inline"><p>manifest.json missing or invalid.</p></div>'; return; }

		$plan = $importer->plan();
		$creates = count( array_filter( $plan, function ( $r ) { return 'CREATE' === $r['action']; } ) );
		$updates = count( $plan ) - $creates;
		$parts   = count( $m['theme_parts'] ) + count( $m['fragments'] );
		echo '<h3>2. Plan — ' . esc_html( $m['project'] ) . '</h3>';
		echo '<div class="pk-stats">';
		echo $this->stat_card( count( $plan ), 'Total items', 'layers' );
		echo $this->stat_card( $creates, 'To create', 'plus', 'green' );
		echo $this->stat_card( $updates, 'To update', 'refresh', 'amber' );
		echo $this->stat_card( $parts, 'Parts &amp; fragments', 'blocks' );
		echo $this->stat_card( count( $m['menus'] ) + ( empty( $m['global_kit'] ) ? 0 : 1 ), 'Menus / kit', 'star' );
		echo '</div>';
		$policies = array( 'overwrite' => 'Overwrite', 'skip' => 'Skip', 'new' => 'New slug', 'merge' => 'Merge' );
		echo '<table class="widefat striped"><thead><tr><th>Slug</th><th>Title</th><th>Type</th><th>Action</th><th>On conflict</th></tr></thead><tbody>';
		foreach ( $plan as $r ) {
			$badge = 'CREATE' === $r['action'] ? '<span style="color:#0f9d6b;font-weight:600;">CREATE</span>' : '<span style="color:#c07f1a;font-weight:600;">UPDATE #' . intval( $r['id'] ) . '</span>';
			$cell = '<span class="pk-muted">—</span>';
			if ( 'UPDATE' === $r['action'] ) {
				$cell = '<select class="pk-conflict" data-slug="' . esc_attr( $r['slug'] ) . '">';
				foreach ( $policies as $k => $lbl ) { $cell .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>'; }
				$cell .= '</select>';
			}
			echo '<tr><td><code>' . esc_html( $r['slug'] ) . '</code></td><td>' . esc_html( $r['title'] ) . '</td><td>' . esc_html( $r['type'] ) . '</td><td>' . $badge . '</td><td>' . $cell . '</td></tr>';
		}
		echo '</tbody></table>';

		// Required plugins detected in the bundle
		$deps = RK_Migrate_Scanner::bundle_dependencies( $base );
		if ( ! empty( $deps ) ) {
			echo '<h4>Required plugins <span class="pk-h4-note">detected in this bundle\'s widgets</span></h4>';
			echo '<div class="pk-deps">';
			foreach ( $deps as $label => $n ) {
				echo '<span class="pk-dep">' . $this->icon( 'blocks' ) . esc_html( $label ) . ' <span class="pk-dep-n">' . intval( $n ) . '</span></span>';
			}
			echo '</div><p class="pk-section-desc">Make sure these are installed &amp; active on this site, or those widgets will render empty after import.</p>';
		}

		if ( ! $this->s()->current_user_can( 'import' ) ) { echo '<p><em>You have view-only access; importing is restricted.</em></p>'; return; }

		$this->section( '3. Run', 'Choose options, optionally rewrite text/URLs, then run a live import.' );
		echo '<form id="rk-migrate-import-form">';
		$media = $this->s()->can_use( 'media' );
		$rb    = $this->s()->can_use( 'rollback' );
		echo '<div class="pk-toggles">';
		echo $this->toggle( 'set_front', 'Set manifest front page as site front page', true );
		echo $this->toggle( 'assign_parts', 'Auto-assign header/footer site-wide', true, 'Requires Elementor Pro.' );
		echo $this->toggle( 'build_menus', 'Build menus from manifest', true );
		echo $this->toggle( 'media_relink', 'Sideload &amp; re-link remote media', false, 'Pull images into this site and rewrite URLs.', '1', ! $media, $media ? '' : 'Pro' );
		echo $this->toggle( 'snapshot', 'Take rollback snapshot before run', $rb, 'Lets you undo this import from History.', '1', ! $rb, $rb ? '' : 'Pro' );
		echo $this->toggle( 'dry_run', 'Dry run', false, 'Report only — change nothing.' );
		echo '</div>';

		// conflict default
		echo '<h4>On conflict <span class="pk-h4-note">when a page slug already exists (override per page in the plan above)</span></h4>';
		echo '<div class="pk-inline"><select id="rk-migrate-conflict-default">';
		foreach ( array( 'overwrite' => 'Overwrite existing', 'skip' => 'Skip (leave existing)', 'new' => 'Create under a new slug', 'merge' => 'Merge (append sections)' ) as $k => $lbl ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>';
		}
		echo '</select><span class="pk-muted">applies to all UPDATE rows unless overridden</span></div>';

		// find & replace
		echo '<h4>Find &amp; Replace <span class="pk-h4-note">applied to every JSON before write</span></h4>';
		echo '<table class="widefat" id="rk-migrate-replace" style="max-width:720px;"><thead><tr><th>Find</th><th>Replace</th><th style="width:64px;">Regex</th><th style="width:80px;"></th></tr></thead><tbody>';
		echo $this->replace_row();
		echo '</tbody></table><p><button type="button" class="button" id="rk-migrate-add-replace">+ Add rule</button></p>';

		echo '<h4>Staging → Production URL rewrite <span class="pk-h4-note">optional</span></h4>';
		echo '<div class="pk-inline" style="max-width:680px;"><input type="url" name="from_url" placeholder="https://staging.site.com" style="flex:1;" /><span class="pk-arrow">&#8594;</span><input type="url" name="to_url" placeholder="https://live.site.com" style="flex:1;" /></div>';

		echo '<div class="pk-savebar"><button type="submit" class="button button-primary button-hero" id="rk-migrate-run-btn">Run Import</button><span class="pk-savebar-hint">Tip: enable Dry run first to preview.</span></div>';
		echo '</form>';

		echo '<div id="rk-migrate-progress" style="display:none;max-width:900px;margin-top:16px;">';
		echo '<div style="background:#e2e8f0;border-radius:6px;height:14px;overflow:hidden;"><div id="rk-migrate-bar" style="background:#5b7cfa;height:100%;width:0;transition:width .2s;"></div></div>';
		echo '<p id="rk-migrate-status" style="margin:8px 0;font-weight:600;"></p>';
		echo '<pre id="rk-migrate-log" style="background:#0c0e14;color:#cbd5e1;padding:14px;border-radius:8px;max-height:340px;overflow:auto;font-size:12px;line-height:1.5;"></pre>';
		echo '</div>';
	}

	private function replace_row() {
		return '<tr><td><input type="text" class="ep-find" style="width:100%;" placeholder="(555) 123-4567" /></td>'
			. '<td><input type="text" class="ep-replace" style="width:100%;" placeholder="(770) 555-0000" /></td>'
			. '<td style="text-align:center;"><input type="checkbox" class="ep-regex" /></td>'
			. '<td><button type="button" class="button-link ep-remove" style="color:#b32d2e;">remove</button></td></tr>';
	}

	/** Reference-style stat card with an SVG icon. */
	private function stat_card( $num, $label, $iconname = 'layers', $accent = '' ) {
		$cls = $accent ? ' pk-stat-' . $accent : '';
		return '<div class="pk-stat' . $cls . '">'
			. '<div class="pk-stat-icon">' . $this->icon( $iconname ) . '</div>'
			. '<div class="pk-stat-num">' . esc_html( $num ) . '</div>'
			. '<div class="pk-stat-label"><span></span>' . wp_kses_post( $label ) . '</div></div>';
	}

	/* =================== EXPORT TAB =================== */
	private function tab_export() {
		if ( ! $this->s()->current_user_can( 'export' ) ) { echo '<p>You do not have export permission.</p>'; return; }
		$inv = RK_Migrate_Exporter::inventory();
		$this->section( 'Full / selective export', 'Pick what to include, then export a portable bundle you can re-import on any site.' );
		echo '<form id="rk-migrate-export-form">';
		echo '<div class="pk-field" style="max-width:420px;"><label class="pk-field-label">Project name</label><input type="text" name="project" value="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="width:100%;" /></div>';

		$this->checkbox_list( 'Pages', 'page_ids', $inv['pages'] );
		$this->checkbox_list( 'Posts', 'post_ids', $inv['posts'] );
		$this->checkbox_list( 'Custom post types', 'cpt_ids', $inv['cpts'] );
		$this->checkbox_list( 'Templates (header/footer/sections)', 'template_ids', $inv['templates'], true );

		$this->section( 'Options', '' );
		$media = $this->s()->can_use( 'media' );
		$enc   = $this->s()->can_use( 'encryption' );
		echo '<div class="pk-toggles">';
		echo $this->toggle( 'include_menus', 'Include menus', true );
		echo $this->toggle( 'include_global_kit', 'Include global colors &amp; fonts', true );
		echo $this->toggle( 'include_media', 'Bundle media files', false, 'Larger zip, but images travel with the bundle.', '1', ! $media, $media ? '' : 'Pro' );
		echo '</div>';
		echo '<div class="pk-field" style="max-width:360px;margin-top:8px;"><label class="pk-field-label">Encrypt with password ' . ( $enc ? '' : '<span class="pk-pill-pro">Agency</span>' ) . '</label><input type="text" name="encrypt_pw" placeholder="' . ( $enc ? 'Optional — leave blank for none' : 'Agency feature' ) . '" ' . disabled( ! $enc, true, false ) . ' style="width:100%;" /></div>';

		echo '<div class="pk-savebar"><button type="submit" class="button button-primary button-hero" id="rk-migrate-export-btn">Export Bundle</button><span class="pk-savebar-hint">Downloads a .zip you can import on any site.</span></div>';
		echo '</form><div id="rk-migrate-export-result" style="margin-top:14px;"></div>';
	}

	private function checkbox_list( $label, $name, $items, $is_tpl = false ) {
		echo '<details class="pk-acc" open><summary><span class="pk-acc-title">' . esc_html( $label ) . ' <span class="pk-acc-count">' . count( $items ) . '</span></span>';
		if ( $items ) { echo '<span class="pk-acc-actions"><button type="button" class="button-link ep-checkall" data-name="' . esc_attr( $name ) . '">Select all</button><span class="pk-sep">&middot;</span><button type="button" class="button-link ep-checknone" data-name="' . esc_attr( $name ) . '">Clear</button></span>'; }
		echo '</summary>';
		if ( ! $items ) { echo '<p class="pk-empty">None found.</p></details>'; return; }
		echo '<div class="pk-checklist">';
		foreach ( $items as $it ) {
			$chip = $is_tpl ? esc_html( $it['type'] ) : '/' . esc_html( $it['slug'] ) . '/';
			echo '<label class="pk-check"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . intval( $it['id'] ) . '" checked />'
				. '<span class="pk-check-title" title="' . esc_attr( $it['title'] ) . '">' . esc_html( $it['title'] ) . '</span>'
				. '<span class="pk-check-chip" title="' . esc_attr( $chip ) . '">' . esc_html( $chip ) . '</span></label>';
		}
		echo '</div></details>';
	}

	/* =================== LIBRARY TAB =================== */
	private function tab_library() {
		echo '<h3>Bundle Library</h3><p>Store master templates here and activate one without re-uploading.</p>';
		$bundles = RK_Migrate_Library::all();
		if ( ! $bundles ) { echo '<p style="color:#888;">No stored bundles yet. Export a bundle (Export tab) and "Save to library", or push one in via Remote.</p>'; }
		else {
			echo '<table class="widefat striped" style="max-width:880px;"><thead><tr><th>Project</th><th>Pages</th><th>Exported</th><th>Actions</th></tr></thead><tbody>';
			foreach ( $bundles as $b ) {
				echo '<tr><td>' . esc_html( $b['project'] ) . '<br><code style="color:#888;">' . esc_html( $b['slug'] ) . '</code></td><td>' . intval( $b['pages'] ) . '</td><td>' . esc_html( $b['time'] ) . '</td><td>';
				echo $this->mini_form( 'rk_migrate_library', array( 'do' => 'activate', 'slug' => $b['slug'] ), 'Activate', 'primary' );
				echo ' ' . $this->mini_form( 'rk_migrate_library', array( 'do' => 'delete', 'slug' => $b['slug'] ), 'Delete', 'link-delete' );
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<h4 style="margin-top:18px;">Add to library</h4>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rk_migrate_library" /><input type="hidden" name="do" value="store" />';
		wp_nonce_field( 'rk_migrate_library' );
		echo '<input type="file" name="bundle" accept=".zip" required /> ';
		submit_button( 'Store bundle', 'secondary', 'submit', false );
		echo '</form>';
	}

	/* =================== HISTORY / ROLLBACK TAB =================== */
	private function tab_history() {
		echo '<h3>Import History</h3>';
		$rows = RK_Migrate_History::instance()->recent( 50 );
		if ( ! $rows ) { echo '<p style="color:#888;">No runs recorded yet.</p>'; }
		else {
			echo '<table class="widefat striped" style="max-width:920px;"><thead><tr><th>When (UTC)</th><th>Type</th><th>Bundle</th><th>Created</th><th>Updated</th><th>Errors</th><th>Rollback</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$rb = $r->snapshot ? ( '<code>' . esc_html( $r->snapshot ) . '</code>' ) : '<span style="color:#aaa;">—</span>';
				echo '<tr><td>' . esc_html( $r->run_at ) . '</td><td>' . esc_html( $r->type ) . '</td><td>' . esc_html( $r->bundle ) . '</td><td>' . intval( $r->created ) . '</td><td>' . intval( $r->updated ) . '</td><td>' . ( $r->errors ? '<span style="color:#b32d2e;">' . intval( $r->errors ) . '</span>' : '0' ) . '</td><td>' . $rb . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		$this->section( 'Rollback snapshots', 'Restore the whole snapshot, or expand it to roll back individual pages.' );
		if ( ! $this->s()->can_use( 'rollback' ) ) { echo '<p class="pk-muted">Rollback is a Pro feature.</p>'; return; }
		$snaps = RK_Migrate_History::instance()->list_snapshots();
		if ( ! $snaps ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No snapshots yet. Enable “Take snapshot” before an import.</div>'; return; }
		foreach ( $snaps as $s ) {
			$posts = RK_Migrate_History::instance()->snapshot_posts( $s['token'] );
			echo '<details class="pk-acc"><summary><span class="pk-acc-title">' . esc_html( $s['label'] ? $s['label'] : $s['token'] ) . ' <span class="pk-acc-count">' . intval( $s['count'] ) . '</span></span>';
			echo '<span class="pk-acc-actions"><span class="pk-muted">' . esc_html( $s['time'] ) . '</span><button type="button" class="button button-primary ep-rollback" data-snap="' . esc_attr( $s['token'] ) . '">Restore all</button></span></summary>';
			echo '<div class="pk-checklist">';
			foreach ( $posts as $p ) {
				echo '<div class="pk-check"><span class="pk-check-title">' . esc_html( $p['title'] ) . ' <span class="pk-muted">#' . intval( $p['pid'] ) . '</span></span>'
					. '<button type="button" class="button btn-sm ep-rollback" data-snap="' . esc_attr( $s['token'] ) . '" data-post="' . intval( $p['pid'] ) . '">Restore</button></div>';
			}
			echo '</div></details>';
		}
		echo '<pre id="rk-migrate-rollback-log" class="pk-log" style="display:none;"></pre>';
	}

	/* =================== MANIFEST BUILDER TAB =================== */
	private function tab_builder() {
		if ( ! $this->s()->can_use( 'manifest_ui' ) ) { echo '<p style="color:#888;">The Manifest Builder is a Pro feature.</p>'; return; }
		$base = $this->active_base();
		echo '<h3>Manifest Builder</h3><p>Edit the active bundle\'s manifest visually and save it as a new library bundle — no hand-editing JSON.</p>';
		if ( ! $base ) { echo '<div class="notice notice-warning inline"><p>Select a source on the Import tab first.</p></div>'; return; }
		$importer = new RK_Migrate_Importer( $base );
		$m = $importer->get_manifest();
		if ( ! $m ) { echo '<p>Invalid manifest.</p>'; return; }
		$files = array();
		foreach ( glob( trailingslashit( $base ) . '*.json' ) as $f ) { if ( basename( $f ) !== 'manifest.json' ) { $files[] = basename( $f ); } }

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="rk-migrate-builder-form">';
		echo '<input type="hidden" name="action" value="rk_migrate_settings" /><input type="hidden" name="do" value="save_manifest" />';
		wp_nonce_field( 'rk_migrate_settings' );
		echo '<p><label>Project name <input type="text" name="b_project" value="' . esc_attr( $m['project'] ) . '" style="width:340px;" /></label></p>';
		echo '<table class="widefat striped" id="rk-migrate-builder" style="max-width:980px;"><thead><tr><th>File</th><th>Slug</th><th>Title</th><th>Type</th><th>Front?</th><th>SEO title</th><th></th></tr></thead><tbody>';
		$idx = 0;
		foreach ( $m['pages'] as $p ) { echo $this->builder_row( $idx++, $p, $files ); }
		echo '</tbody></table>';
		echo '<p><button type="button" class="button" id="rk-migrate-add-page">+ Add page</button></p>';
		echo '<p style="margin-top:12px;"><button type="submit" class="button button-primary">Save as new library bundle</button></p>';
		echo '<template id="rk-migrate-builder-template">' . $this->builder_row( '__I__', array(), $files ) . '</template>';
		echo '</form>';
	}

	private function builder_row( $i, $p, $files ) {
		$sel = function ( $cur ) use ( $files ) {
			$o = '<option value=""></option>';
			foreach ( $files as $f ) { $o .= '<option value="' . esc_attr( $f ) . '"' . selected( $cur, $f, false ) . '>' . esc_html( $f ) . '</option>'; }
			return $o;
		};
		$g = function ( $k, $d = '' ) use ( $p ) { return isset( $p[ $k ] ) ? $p[ $k ] : $d; };
		return '<tr>'
			. '<td><select name="pages[' . $i . '][file]" style="width:100%;">' . $sel( $g( 'file' ) ) . '</select></td>'
			. '<td><input name="pages[' . $i . '][slug]" value="' . esc_attr( $g( 'slug' ) ) . '" style="width:100%;" /></td>'
			. '<td><input name="pages[' . $i . '][title]" value="' . esc_attr( $g( 'title' ) ) . '" style="width:100%;" /></td>'
			. '<td><input name="pages[' . $i . '][post_type]" value="' . esc_attr( $g( 'post_type', 'page' ) ) . '" style="width:80px;" /></td>'
			. '<td style="text-align:center;"><input type="checkbox" name="pages[' . $i . '][is_front_page]" value="1" ' . checked( ! empty( $p['is_front_page'] ), true, false ) . ' /></td>'
			. '<td><input name="pages[' . $i . '][seo_title]" value="' . esc_attr( $g( 'seo_title' ) ) . '" style="width:100%;" /></td>'
			. '<td><button type="button" class="button-link ep-remove" style="color:#b32d2e;">×</button></td></tr>';
	}

	/* =================== REMOTE TAB =================== */
	private function tab_remote() {
		if ( ! $this->s()->can_use( 'remote' ) ) { echo '<p style="color:#888;">Remote push/pull is an Agency feature.</p>'; return; }
		echo '<h3>Remote Push / Pull</h3>';
		echo '<p>This site\'s inbound API is <strong>' . ( $this->s()->get( 'remote_enabled' ) ? '<span style="color:#276749;">enabled</span>' : '<span style="color:#b32d2e;">disabled</span> (enable in Settings)' ) . '</strong>. Endpoint: <code>' . esc_html( home_url( '/wp-json/rk-migrate/v1/' ) ) . '</code></p>';

		echo '<h4>Push active/selected bundle to a remote site</h4>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rk_migrate_remote" /><input type="hidden" name="do" value="push" />';
		wp_nonce_field( 'rk_migrate_remote' );
		echo '<p><input type="url" name="remote_url" placeholder="https://client-site.com" style="width:340px;" required /> <input type="text" name="remote_token" placeholder="remote token" style="width:240px;" required /></p>';
		echo '<p><label>Source: <select name="source"><option value="active">Active source (Import tab)</option>';
		foreach ( RK_Migrate_Library::all() as $b ) { echo '<option value="lib:' . esc_attr( $b['slug'] ) . '">Library: ' . esc_html( $b['project'] ) . '</option>'; }
		echo '</select></label></p>';
		submit_button( 'Push to remote', 'primary', 'submit', false );
		echo '</form>';

		echo '<h4 style="margin-top:18px;">Pull a remote site into the library</h4>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rk_migrate_remote" /><input type="hidden" name="do" value="pull" />';
		wp_nonce_field( 'rk_migrate_remote' );
		echo '<p><input type="url" name="remote_url" placeholder="https://source-site.com" style="width:340px;" required /> <input type="text" name="remote_token" placeholder="remote token" style="width:240px;" required /></p>';
		echo '<p><label><input type="checkbox" name="include_media" value="1" /> Include media</label></p>';
		submit_button( 'Pull into library', 'secondary', 'submit', false );
		echo '</form>';

		echo '<h4 style="margin-top:18px;">Shared component sync</h4>';
		$tpls = get_posts( array( 'post_type' => 'elementor_library', 'numberposts' => -1, 'post_status' => 'any' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rk_migrate_remote" /><input type="hidden" name="do" value="sync" />';
		wp_nonce_field( 'rk_migrate_remote' );
		echo '<p><label>Component: <select name="template_id">';
		foreach ( $tpls as $t ) { echo '<option value="' . intval( $t->ID ) . '">' . esc_html( $t->post_title ) . '</option>'; }
		echo '</select></label></p>';
		echo '<p>Targets (one <code>url|token</code> per line):<br><textarea name="targets" rows="3" style="width:560px;" placeholder="https://client1.com|TOKEN1&#10;https://client2.com|TOKEN2"></textarea></p>';
		submit_button( 'Sync component to targets', 'secondary', 'submit', false );
		echo '</form>';
	}

	/* =================== MARKETPLACE TAB =================== */
	private function tab_market() {
		$this->section( 'Template Marketplace', 'Browse and one-click install full-site bundles into your Library.' );
		$cloud_ok = RK_Migrate_Cloud::configured();
		echo '<p class="pk-cloud-note">' . ( $cloud_ok ? '<span class="pk-status pk-status-ok"><span class="pk-dot"></span>Cloud connected</span>' : 'Cloud storage not configured · <a href="' . esc_url( $this->tab_url( 'settings' ) ) . '">set it up</a>' ) . '</p>';
		echo '<div class="pk-cards">';
		foreach ( RK_Migrate_Marketplace::catalog() as $item ) {
			$free = ( strtolower( $item['price'] ) === 'free' );
			echo '<div class="pk-mcard">';
			echo '<div class="pk-mcard-top"><div class="pk-mcard-title">' . esc_html( $item['title'] ) . '</div>';
			echo '<span class="pk-badge ' . ( $free ? 'pk-badge-free' : 'pk-badge-paid' ) . '">' . esc_html( $item['price'] ) . '</span></div>';
			echo '<div class="pk-mcard-meta">' . esc_html( $item['category'] ) . ' &middot; ' . intval( $item['pages'] ) . ' pages</div>';
			echo '<div class="pk-mcard-desc">' . esc_html( $item['desc'] ) . '</div>';
			echo '<div class="pk-mcard-foot">' . $this->mini_form( 'rk_migrate_marketplace', array( 'item' => $item['id'] ), 'Install', 'primary' ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		if ( $cloud_ok ) {
			echo '<h4 style="margin-top:22px;">Your cloud bundles</h4>';
			$list = RK_Migrate_Cloud::list_bundles();
			if ( is_wp_error( $list ) ) { echo '<p style="color:#b32d2e;">' . esc_html( $list->get_error_message() ) . '</p>'; }
			elseif ( ! $list ) { echo '<p style="color:#888;">No cloud bundles.</p>'; }
			else {
				echo '<table class="widefat striped" style="max-width:680px;"><thead><tr><th>Project</th><th>Updated</th><th></th></tr></thead><tbody>';
				foreach ( $list as $b ) {
					echo '<tr><td>' . esc_html( $b['project'] ?? $b['id'] ) . '</td><td>' . esc_html( $b['updated'] ?? '' ) . '</td><td>' . $this->mini_form( 'rk_migrate_cloud', array( 'do' => 'download', 'id' => $b['id'] ), 'Download to library', 'secondary' ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		}
	}

	/* =================== SETTINGS TAB =================== */
	private function tab_settings() {
		$s = $this->s();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rk_migrate_settings" /><input type="hidden" name="do" value="save" />';
		wp_nonce_field( 'rk_migrate_settings' );

		echo '<div class="pk-setgrid">';

		// ---- License tier (segmented) ----
		$this->set_open( 'tier', 'License tier', 'Self-hosted build — choose which tier of features to expose in the UI.' );
		echo '<div class="pk-seg">';
		foreach ( array( 'free' => 'Free / Core', 'pro' => 'Pro', 'agency' => 'Agency · everything' ) as $k => $v ) {
			$on = ( $s->get( 'tier' ) === $k );
			echo '<label class="pk-seg-item' . ( $on ? ' pk-seg-on' : '' ) . '"><input type="radio" name="tier" value="' . esc_attr( $k ) . '" ' . checked( $on, true, false ) . ' />' . esc_html( $v ) . '</label>';
		}
		echo '</div>';
		$this->set_close();

		// ---- Role-based access (grid) ----
		$this->set_open( 'shield', 'Access control', 'Pick the minimum WordPress role allowed to perform each action.' );
		$caps = array( 'manage_options' => 'Administrator', 'edit_pages' => 'Editor and up', 'publish_posts' => 'Author and up', 'read' => 'Any logged-in user' );
		echo '<div class="pk-grid2">';
		foreach ( array( 'cap_import' => 'Import', 'cap_export' => 'Export', 'cap_rollback' => 'Rollback', 'cap_view_log' => 'View log &amp; history' ) as $field => $label ) {
			echo '<div class="pk-field"><label class="pk-field-label">' . wp_kses_post( $label ) . '</label><select name="' . esc_attr( $field ) . '">';
			foreach ( $caps as $cap => $cl ) { echo '<option value="' . esc_attr( $cap ) . '"' . selected( $s->get( $field ), $cap, false ) . '>' . esc_html( $cl ) . '</option>'; }
			echo '</select></div>';
		}
		echo '</div>';
		$this->set_close();

		// ---- Webhooks ----
		$this->set_open( 'bell', 'Webhook notifications', 'Send a POST to Slack, Discord, or any URL when an import finishes.' );
		echo '<div class="pk-field"><label class="pk-field-label">Webhook URL</label>';
		echo '<input type="url" name="webhook_url" value="' . esc_attr( $s->get( 'webhook_url' ) ) . '" placeholder="https://hooks.slack.com/…" style="width:100%;" /></div>';
		$on = (array) $s->get( 'webhook_on' );
		echo '<div class="pk-toggles">';
		echo $this->toggle( 'webhook_on[]', 'Notify on success', in_array( 'import_done', $on, true ), '', 'import_done' );
		echo $this->toggle( 'webhook_on[]', 'Notify on failure', in_array( 'import_fail', $on, true ), '', 'import_fail' );
		echo '</div>';
		$this->set_close();

		// ---- Remote API ----
		$this->set_open( 'link', 'Remote API', 'Pair two sites with a shared token to push/pull bundles over the REST API.' );
		echo $this->toggle( 'remote_enabled', 'Enable inbound push/pull API', $s->get( 'remote_enabled' ), 'Off by default. Turn on only when pairing sites.' );
		echo '<div class="pk-field"><label class="pk-field-label">Connection token</label>';
		echo '<div class="pk-inline"><input type="text" name="remote_token" value="' . esc_attr( $s->get( 'remote_token' ) ) . '" style="flex:1;" placeholder="Generate or paste a token" /><button type="button" class="button" id="rk-migrate-gen-token">Generate</button></div>';
		echo '<div class="pk-field-desc">Share this token with the paired site. Keep it secret.</div></div>';
		echo $this->toggle( 'remote_allow_pull', 'Allow this site to be pulled', $s->get( 'remote_allow_pull' ) );
		$this->set_close();

		// ---- AI ----
		$this->set_open( 'sparkles', 'AI content swap', 'Optional. Uses your own OpenAI-compatible endpoint — leave the key blank to disable. No traffic flows through RK Migrate.' );
		echo '<div class="pk-field"><label class="pk-field-label">API key</label><input type="password" name="ai_api_key" value="' . esc_attr( $s->get( 'ai_api_key' ) ) . '" style="width:100%;" placeholder="sk-…" /></div>';
		echo '<div class="pk-grid2">';
		echo '<div class="pk-field"><label class="pk-field-label">Model</label><input type="text" name="ai_model" value="' . esc_attr( $s->get( 'ai_model' ) ) . '" style="width:100%;" /></div>';
		echo '<div class="pk-field"><label class="pk-field-label">Endpoint</label><input type="url" name="ai_endpoint" value="' . esc_attr( $s->get( 'ai_endpoint' ) ) . '" style="width:100%;" /></div>';
		echo '</div>';
		$this->set_close();

		// ---- Cloud ----
		$this->set_open( 'cloud', 'Cloud storage', 'Optional. Connect a cloud bundle backend to store and share bundles across installs.' );
		echo '<div class="pk-grid2">';
		echo '<div class="pk-field"><label class="pk-field-label">Endpoint</label><input type="url" name="cloud_endpoint" value="' . esc_attr( $s->get( 'cloud_endpoint' ) ) . '" style="width:100%;" /></div>';
		echo '<div class="pk-field"><label class="pk-field-label">Token</label><input type="text" name="cloud_token" value="' . esc_attr( $s->get( 'cloud_token' ) ) . '" style="width:100%;" /></div>';
		echo '</div>';
		$this->set_close();

		echo '</div>'; // .pk-setgrid

		echo '<div class="pk-savebar"><button type="submit" class="button button-primary button-hero">Save settings</button><span class="pk-savebar-hint">Changes apply immediately after saving.</span></div>';
		echo '</form>';
	}

	/** Open a 2-column setting card (icon+title+desc left, controls right). */
	private function set_open( $icon, $title, $desc ) {
		echo '<section class="pk-setcard"><div class="pk-setcard-head"><span class="pk-setcard-ico">' . $this->icon( $icon ) . '</span>'
			. '<div><h3>' . wp_kses_post( $title ) . '</h3><p>' . wp_kses_post( $desc ) . '</p></div></div>'
			. '<div class="pk-setcard-body">';
	}
	private function set_close() { echo '</div></section>'; }

	/** Section header with title + description. */
	private function section( $title, $desc = '' ) {
		echo '<div class="pk-section"><h3>' . wp_kses_post( $title ) . '</h3>';
		if ( $desc ) { echo '<p class="pk-section-desc">' . wp_kses_post( $desc ) . '</p>'; }
		echo '</div>';
	}

	/** Toggle switch (styled checkbox). */
	private function toggle( $name, $label, $checked, $desc = '', $value = '1', $disabled = false, $badge = '' ) {
		$h  = '<label class="pk-toggle' . ( $disabled ? ' pk-toggle-disabled' : '' ) . '">';
		$h .= '<input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . checked( (bool) $checked, true, false ) . ' ' . disabled( (bool) $disabled, true, false ) . ' />';
		$h .= '<span class="pk-toggle-track"><span class="pk-toggle-thumb"></span></span>';
		$h .= '<span class="pk-toggle-text">' . wp_kses_post( $label );
		if ( $badge ) { $h .= ' <span class="pk-pill-pro">' . esc_html( $badge ) . '</span>'; }
		if ( $desc ) { $h .= '<span class="pk-toggle-desc">' . wp_kses_post( $desc ) . '</span>'; }
		$h .= '</span></label>';
		return $h;
	}

	/* =================== SITE DOCTOR TAB =================== */
	private function doctor_view() {
		$v = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'overview';
		return in_array( $v, array( 'overview', 'colors', 'fonts', 'links', 'audit', 'corners', 'convert' ), true ) ? $v : 'overview';
	}

	private function doctor_subnav( $view ) {
		$items = array( 'overview' => 'Overview', 'colors' => 'Colors', 'fonts' => 'Fonts', 'corners' => 'Corners', 'links' => 'Links', 'audit' => 'SEO audit', 'convert' => 'Containers' );
		echo '<div class="pk-subnav">';
		foreach ( $items as $k => $lbl ) {
			$cls = ( $k === $view ) ? ' pk-subnav-on' : '';
			echo '<a class="pk-subnav-item' . $cls . '" href="' . esc_url( add_query_arg( 'view', $k, $this->tab_url( 'doctor' ) ) ) . '">' . esc_html( $lbl ) . '</a>';
		}
		echo '</div>';
	}

	private function tab_doctor() {
		if ( isset( $_GET['rescan'] ) ) { RK_Migrate_Scanner::clear_cache(); }
		$scan = RK_Migrate_Scanner::scan();
		$view = $this->doctor_view();
		$this->doctor_subnav( $view );
		$rescan_url = esc_url( add_query_arg( array( 'view' => $view, 'rescan' => 1 ), $this->tab_url( 'doctor' ) ) );
		$cap_note = '';
		if ( isset( $scan['scanned_count'], $scan['total_built'] ) && $scan['total_built'] > $scan['scanned_count'] ) {
			$cap_note = ' &middot; <span class="pk-muted" title="Scan is capped for performance; filter rk_migrate_scan_max_posts to change.">scanned newest ' . intval( $scan['scanned_count'] ) . ' of ' . intval( $scan['total_built'] ) . '</span>';
		}
		echo '<p class="pk-scanmeta">' . $this->icon( 'history' ) . ' Last scan ' . esc_html( $scan['time'] ) . ' &middot; ' . intval( $scan['posts'] ) . ' pages scanned' . $cap_note . ' &middot; <a href="' . $rescan_url . '">Re-scan now</a></p>';
		echo '<div class="pk-doctor-scroll">';
		switch ( $view ) {
			case 'colors':  $this->doctor_colors( $scan ); break;
			case 'fonts':   $this->doctor_fonts( $scan ); break;
			case 'links':   $this->doctor_links( $scan ); break;
			case 'audit':   $this->doctor_audit( $scan ); break;
			case 'corners': $this->doctor_corners( $scan ); break;
			case 'convert': $this->doctor_convert(); break;
			default:        $this->doctor_overview( $scan ); break;
		}
		echo '</div>';
		echo '<pre id="pk-doctor-log" class="pk-log" style="display:none;"></pre>';
	}

	private function doctor_overview( $scan ) {
		$t = $scan['totals'];
		echo '<div class="pk-stats">';
		echo $this->stat_card( $t['hardcoded_colors'], 'Hardcoded colors', 'palette', $t['hardcoded_colors'] ? 'amber' : 'green' );
		echo $this->stat_card( $t['links'], 'Links found', 'link' );
		echo $this->stat_card( $t['links_empty'], 'Empty / # links', 'link', $t['links_empty'] ? 'amber' : 'green' );
		echo $this->stat_card( $t['images_no_alt'], 'Images missing alt', 'blocks', $t['images_no_alt'] ? 'amber' : 'green' );
		echo $this->stat_card( $t['pages_no_h1'], 'Pages without H1', 'type', $t['pages_no_h1'] ? 'amber' : 'green' );
		echo $this->stat_card( $scan['sections']['pages_legacy'], 'Pages on sections', 'builder', $scan['sections']['pages_legacy'] ? 'amber' : 'green' );
		echo $this->stat_card( count( $scan['radius'] ), 'Corner-radius values', 'corners' );
		echo '</div>';
		echo '<div class="pk-callout"><strong>How to use:</strong> open <a href="' . esc_url( add_query_arg( 'view', 'colors', $this->tab_url( 'doctor' ) ) ) . '">Colors</a> to bind hardcoded colors to your global kit (then a single global change updates them all), <a href="' . esc_url( add_query_arg( 'view', 'convert', $this->tab_url( 'doctor' ) ) ) . '">Containers</a> to modernise old Section layouts, and <a href="' . esc_url( add_query_arg( 'view', 'audit', $this->tab_url( 'doctor' ) ) ) . '">SEO audit</a> for headings, links &amp; alt text. Every fix takes a rollback snapshot first.</div>';
	}

	private function doctor_colors( $scan ) {
		$globals = RK_Migrate_Scanner::global_colors();
		$can = $this->s()->current_user_can( 'import' );
		if ( empty( $scan['colors'] ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No hardcoded colors found - every element already uses the global kit.</div>'; return; }
		echo '<p class="pk-section-desc">Colors set manually on widgets - including backgrounds, overlays, borders, box-shadows and repeater items. <strong>Bind</strong> top-level controls to a global swatch (global changes then apply), or <strong>Replace</strong> a value everywhere (works for nested colors too).</p>';
		if ( ! $globals ) { echo '<div class="pk-alert pk-alert-warn">' . $this->icon( 'alert' ) . '<div>No global colors defined in your Elementor kit yet. Add some in <em>Elementor &rarr; Site Settings &rarr; Global Colors</em> to enable binding.</div></div>'; }
		echo '<table class="widefat striped"><thead><tr><th>Color</th><th>Uses</th><th>Bindable</th><th>Pages</th><th>Bind to global</th><th>Replace value</th></tr></thead><tbody>';
		foreach ( $scan['colors'] as $norm => $c ) {
			$bindable = isset( $c['bindable'] ) ? (int) $c['bindable'] : 0;
			$pretty = RK_Migrate_Scanner::pretty_color( $c['value'] );
			echo '<tr><td class="pk-color-cell"><span class="pk-swatch" style="background:' . esc_attr( $c['value'] ) . '"></span><code class="pk-color-code" title="' . esc_attr( $pretty ) . '">' . esc_html( $pretty ) . '</code></td>';
			$cssn = isset( $c['css'] ) ? (int) $c['css'] : 0;
			echo '<td>' . intval( $c['count'] ) . ( $cssn ? ' <span class="pk-muted" title="' . $cssn . ' inside Custom CSS">(' . $cssn . ' css)</span>' : '' ) . '</td><td>' . $bindable . '</td><td>' . count( $c['pages'] ) . '</td>';
			// bind cell
			echo '<td>';
			if ( $can && $globals && $bindable > 0 ) {
				echo '<div class="pk-inline pk-reclaim" data-hex="' . esc_attr( $norm ) . '"><select class="pk-reclaim-sel">';
				foreach ( $globals as $g ) { echo '<option value="' . esc_attr( $g['id'] ) . '">' . esc_html( $g['title'] ) . ' - ' . esc_html( $g['color'] ) . '</option>'; }
				echo '</select><button type="button" class="button button-primary pk-reclaim-btn" data-kind="color">Bind</button></div>';
			} else { echo '<span class="pk-muted">' . ( $bindable ? '-' : 'nested only' ) . '</span>'; }
			echo '</td>';
			// replace cell
			echo '<td>';
			if ( $can ) {
				echo '<div class="pk-inline pk-repl" data-hex="' . esc_attr( $norm ) . '"><input type="text" class="pk-repl-to" value="' . esc_attr( RK_Migrate_Scanner::pretty_color( $norm ) ) . '" /><button type="button" class="button pk-repl-btn">Replace</button></div>';
			} else { echo '<span class="pk-muted">-</span>'; }
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function doctor_fonts( $scan ) {
		$globals = RK_Migrate_Scanner::global_fonts();
		$can = $this->s()->current_user_can( 'import' );
		if ( empty( $scan['fonts'] ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No manual fonts found — typography uses the global kit.</div>'; return; }
		echo '<p class="pk-section-desc">Font families set manually on widgets. Bind them to a global typography token so global font changes apply everywhere.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Font family</th><th>Uses</th><th>Pages</th><th>Bind to global</th></tr></thead><tbody>';
		foreach ( $scan['fonts'] as $fam => $info ) {
			echo '<tr><td><strong>' . esc_html( $fam ) . '</strong></td><td>' . intval( $info['count'] ) . '</td><td>' . count( $info['pages'] ) . '</td><td>';
			if ( $can && $globals ) {
				echo '<div class="pk-inline pk-reclaim" data-family="' . esc_attr( $fam ) . '"><select class="pk-reclaim-sel">';
				foreach ( $globals as $g ) { echo '<option value="' . esc_attr( $g['id'] ) . '">' . esc_html( $g['title'] ) . '</option>'; }
				echo '</select><button type="button" class="button button-primary pk-reclaim-btn" data-kind="font">Bind</button></div>';
			} else { echo '<span class="pk-muted">—</span>'; }
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function doctor_corners( $scan ) {
		$can = $this->s()->current_user_can( 'import' );
		if ( empty( $scan['radius'] ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No corner-radius values found.</div>'; return; }
		echo '<p class="pk-section-desc">Every distinct border-radius (corner rounding) currently in use. Normalise them all to one value for a consistent look - applies to elements that already have a radius, with a rollback snapshot.</p>';
		if ( $can ) {
			echo '<div class="pk-inline" style="margin:0 0 16px;"><label class="pk-field-label" style="margin:0;">Set all corners to</label><input type="number" id="pk-radius-px" value="8" min="0" style="width:80px;" /><span class="pk-muted">px</span><button type="button" class="button button-primary" id="pk-radius-btn">Apply to all</button></div>';
		}
		echo '<table class="widefat striped"><thead><tr><th>Radius</th><th>Uses</th><th>Pages</th><th>Preview</th></tr></thead><tbody>';
		foreach ( $scan['radius'] as $sig => $info ) {
			$px = (int) preg_replace( '/[^0-9].*$/', '', $sig );
			echo '<tr><td><code>' . esc_html( $sig ) . '</code></td><td>' . intval( $info['count'] ) . '</td><td>' . count( $info['pages'] ) . '</td>';
			echo '<td><span class="pk-radius-demo" style="border-radius:' . esc_attr( min( $px, 40 ) ) . 'px;"></span></td></tr>';
		}
		echo '</tbody></table>';
	}

	private function doctor_links( $scan ) {
		if ( empty( $scan['links'] ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No links found in widgets.</div>'; return; }
		echo '<p><button type="button" class="button" id="pk-linkcheck-btn">' . $this->icon( 'refresh' ) . ' Check links (HTTP)</button> <span id="pk-linkcheck-status" class="pk-muted"></span></p>';
		echo '<p class="pk-section-desc" style="margin-top:0;">Edit a link\'s URL or text right here, then <strong>Save</strong> — it writes straight into the page\'s Elementor data (a re-scan refreshes the Type). Element IDs come from the latest scan, so Re-scan first if Save is disabled.</p>';
		echo '<div class="pk-linkbar" style="display:flex;gap:12px;align-items:center;margin:0 0 12px;"><button type="button" id="pk-linksaveall" class="button button-primary">Save All Changes</button><span id="pk-linksaveall-status" class="pk-muted"></span></div>';
		echo '<table class="widefat striped" id="pk-linktable"><thead><tr><th style="width:70px;">Status</th><th>Link (URL)</th><th>Text</th><th style="width:80px;">Type</th><th>Page</th><th style="width:110px;">Edit</th></tr></thead><tbody>';
		foreach ( $scan['links'] as $l ) {
			$eid = isset( $l['eid'] ) ? $l['eid'] : '';
			$badge = 'empty' === $l['kind'] ? '<span class="pk-badge pk-badge-warn">empty</span>' : ( 'external' === $l['kind'] ? '<span class="pk-badge pk-badge-paid">external</span>' : '<span class="pk-badge pk-badge-free">internal</span>' );
			$u = ( '' === $l['url'] || '#' === $l['url'] ) ? '' : esc_attr( ( 0 === strpos( $l['url'], '/' ) ) ? home_url( $l['url'] ) : $l['url'] );
			echo '<tr data-pid="' . intval( $l['pid'] ) . '" data-eid="' . esc_attr( $eid ) . '">';
			echo '<td class="pk-linkstatus" data-url="' . $u . '"><span class="pk-muted">—</span></td>';
			echo '<td><input type="text" class="pk-link-url" value="' . esc_attr( '#' === $l['url'] ? '' : $l['url'] ) . '" placeholder="https://… or /path" style="width:100%;" readonly /></td>';
			echo '<td><input type="text" class="pk-link-text" value="' . esc_attr( $l['text'] ) . '" placeholder="link text" style="width:100%;" readonly /></td>';
			echo '<td>' . $badge . '</td>';
			echo '<td><a href="' . esc_url( $l['edit'] ) . '" target="_blank">' . esc_html( $l['title'] ) . '</a></td>';
			echo '<td class="pk-link-actions">';
			if ( $eid ) {
				echo '<button type="button" class="button-link pk-link-edit" title="Edit"><span class="dashicons dashicons-edit"></span></button> ';
				echo '<button type="button" class="button button-small pk-link-save" style="display:none;">Save</button>';
			} else {
				echo '<span class="pk-muted" title="Re-scan to enable">—</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function doctor_audit( $scan ) {
		echo '<h4>Per-page SEO</h4>';
		if ( empty( $scan['seo'] ) ) {
			echo '<div class="pk-callout">No pages scanned yet.</div>';
		} else {
			$flagged = 0;
			foreach ( $scan['seo'] as $r ) { if ( ! empty( $r['flags'] ) ) { $flagged++; } }
			echo '<p class="pk-section-desc" style="margin-top:0;">Meta title/description, indexability, OG image, H1 &amp; word count per page. <strong>' . intval( $flagged ) . '</strong> page(s) flagged.</p>';
			echo '<table class="widefat striped"><thead><tr><th>Page</th><th>Meta title</th><th>Meta description</th><th>Words</th><th>Index</th><th>Flags</th><th></th></tr></thead><tbody>';
			foreach ( $scan['seo'] as $r ) {
				$mt = '' !== $r['meta_title'] ? esc_html( $r['meta_title'] ) : '<em class="pk-muted">(uses page title)</em>';
				if ( '' !== $r['meta_desc'] ) {
					$d = $r['meta_desc'];
					if ( function_exists( 'mb_strlen' ) && mb_strlen( $d ) > 90 ) { $d = mb_substr( $d, 0, 90 ) . '…'; }
					$md = esc_html( $d );
				} else {
					$md = '<span class="pk-badge pk-badge-warn">missing</span>';
				}
				$idx = $r['noindex'] ? '<span class="pk-badge pk-badge-warn">Noindex</span>' : '<span class="pk-badge pk-badge-free">Index</span>';
				$fl = '';
				foreach ( $r['flags'] as $ff ) { $fl .= '<span class="pk-badge pk-badge-warn">' . esc_html( $ff ) . '</span> '; }
				if ( '' === $fl ) { $fl = '<span class="pk-badge pk-badge-free">OK</span>'; }
				echo '<tr><td><strong>' . esc_html( $r['title'] ) . '</strong><br><span class="pk-muted">/' . esc_html( $r['slug'] ) . '</span></td><td>' . $mt . '</td><td>' . $md . '</td><td>' . intval( $r['words'] ) . '</td><td>' . $idx . '</td><td>' . $fl . '</td><td><a class="button" href="' . esc_url( $r['edit'] ) . '" target="_blank">Edit</a></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h4 style="margin-top:22px;">Heading structure</h4>';
		if ( empty( $scan['headings'] ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No heading issues — every page has one H1 and a clean order.</div>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Page</th><th>H1s</th><th>Issues</th><th></th></tr></thead><tbody>';
			foreach ( $scan['headings'] as $h ) {
				echo '<tr><td>' . esc_html( $h['title'] ) . '</td><td>' . intval( $h['h1'] ) . '</td><td>';
				foreach ( $h['issues'] as $i ) { echo '<span class="pk-badge pk-badge-warn">' . esc_html( $i ) . '</span> '; }
				echo '</td><td><a class="button" href="' . esc_url( $h['edit'] ) . '" target="_blank">Edit</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<h4 style="margin-top:22px;">Heading tree (H1&ndash;H6)</h4>';
		if ( empty( $scan['heading_tree'] ) ) {
			echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No headings found in page content.</div>';
		} else {
			echo '<p class="pk-section-desc" style="margin-top:0;">Every heading on every page, with its exact text and level. Empty headings are flagged.</p>';
			foreach ( $scan['heading_tree'] as $p ) {
				$by = array( 1 => array(), 2 => array(), 3 => array(), 4 => array(), 5 => array(), 6 => array() );
				foreach ( $p['items'] as $it ) { $lv = (int) $it['level']; if ( isset( $by[ $lv ] ) ) { $by[ $lv ][] = $it['text']; } }
				echo '<details class="pk-htree"><summary><strong>' . esc_html( $p['title'] ) . '</strong> <a href="' . esc_url( $p['edit'] ) . '" target="_blank" class="pk-htree-edit">edit</a></summary><div class="pk-htree-body">';
				if ( empty( $by[1] ) ) {
					echo '<div class="pk-htree-h1 pk-htree-warn">H1: <em>No H1 found</em></div>';
				} else {
					foreach ( $by[1] as $t ) { echo '<div class="pk-htree-h1">H1: &ldquo;' . esc_html( '' !== $t ? $t : 'empty' ) . '&rdquo;</div>'; }
				}
				for ( $lv = 2; $lv <= 6; $lv++ ) {
					if ( empty( $by[ $lv ] ) ) { continue; }
					echo '<div class="pk-htree-group"><span class="pk-htree-lvl">H' . $lv . ' (Found ' . count( $by[ $lv ] ) . '):</span><ol>';
					foreach ( $by[ $lv ] as $t ) { echo '<li>H' . $lv . ': &ldquo;' . esc_html( '' !== $t ? $t : 'empty' ) . '&rdquo;</li>'; }
					echo '</ol></div>';
				}
				echo '</div></details>';
			}
		}
		echo '<h4 style="margin-top:22px;">Images missing alt text</h4>';
		if ( empty( $scan['images'] ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' All images have alt text.</div>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Page</th><th>Image</th></tr></thead><tbody>';
			foreach ( $scan['images'] as $im ) {
				echo '<tr><td><a href="' . esc_url( $im['edit'] ) . '" target="_blank">' . esc_html( $im['title'] ) . '</a></td><td><code>' . esc_html( wp_basename( $im['img'] ) ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	private function doctor_convert() {
		$pages = RK_Migrate_Scanner::legacy_pages();
		$can = $this->s()->current_user_can( 'import' );
		echo '<p class="pk-section-desc">Convert legacy Section/Column layouts to modern flex Containers. Best-effort mapping of widths, gaps, padding &amp; backgrounds — always preview, then verify in Elementor. Each convert takes a rollback snapshot.</p>';
		if ( empty( $pages ) ) { echo '<div class="pk-callout pk-callout-ok">' . $this->icon( 'check' ) . ' No legacy sections found — all pages already use containers.</div>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>Page</th><th>Sections</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $pages as $p ) {
			echo '<tr data-pid="' . intval( $p['pid'] ) . '"><td>' . esc_html( $p['title'] ) . '</td><td>' . intval( $p['sections'] ) . '</td><td>';
			echo '<a class="button" href="' . esc_url( $p['edit'] ) . '" target="_blank">Open</a> ';
			if ( $can ) {
				echo '<button type="button" class="button pk-convert-btn" data-dry="1">Preview</button> ';
				echo '<button type="button" class="button button-primary pk-convert-btn" data-dry="0">Convert</button>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* =================== HELP & DOCS TAB =================== */
	private function tab_help() {
		$u = function ( $t ) { return esc_url( $this->tab_url( $t ) ); };
		echo '<div class="pk-doc">';

		// quick start
		$this->section( 'Quick start', 'Two actions power everything: export a site to a bundle, import a bundle to a site.' );
		echo '<div class="pk-steps">';
		echo '<div class="pk-step"><div class="pk-step-n">1</div><div><strong>Export a source site</strong><p>On the <a href="' . $u( 'export' ) . '">Export</a> tab, leave everything selected and click <em>Export Bundle</em> to download a portable <code>.zip</code>.</p></div></div>';
		echo '<div class="pk-step"><div class="pk-step-n">2</div><div><strong>Upload to the new site</strong><p>On the <a href="' . $u( 'import' ) . '">Import</a> tab, upload that <code>.zip</code>. RK Migrate shows a plan of what will be created vs. updated.</p></div></div>';
		echo '<div class="pk-step"><div class="pk-step-n">3</div><div><strong>Adjust &amp; run</strong><p>Add Find &amp; Replace rules (phone, brand, URLs), tick <em>media re-link</em> and <em>snapshot</em>, then <em>Run Import</em> and watch the live log.</p></div></div>';
		echo '</div>';
		echo '<div class="pk-callout"><strong>Tip:</strong> Always do a <em>Dry run</em> first — it reports what would happen and changes nothing. Then run for real with <em>snapshot</em> on so you can roll back.</div>';

		// features
		$this->section( 'What each tab does', '' );
		echo '<div class="pk-deflist">';
		$rows = array(
			array( 'Import', 'import', 'Upload or select a bundle, preview the plan, apply find/replace + URL rewrite, and run a live, resumable import.' ),
			array( 'Export', 'export', 'Scan the site and download a bundle. Pick everything or just specific pages/posts/CPTs/templates. Optionally bundle media and encrypt.' ),
			array( 'Library', 'library', 'Store master templates inside the install and activate one without re-uploading.' ),
			array( 'History &amp; Rollback', 'history', 'Audit log of every run, plus pre-import snapshots you can restore page-by-page or all at once.' ),
			array( 'Site Doctor', 'doctor', 'Scan the whole site: bind hardcoded colors/fonts to the global kit, convert legacy sections to containers, and audit links, headings &amp; alt text.' ),
			array( 'Manifest Builder', 'builder', 'Edit a bundle&rsquo;s page list visually and save it as a new Library bundle — no hand-editing JSON.' ),
			array( 'Remote', 'remote', 'Pair two sites with a token to push a bundle straight into another site or pull a site down as a bundle.' ),
			array( 'Marketplace', 'market', 'Browse and one-click install full-site starter bundles into your Library.' ),
			array( 'Settings', 'settings', 'Tier, role-based access, webhooks, remote token, AI key, and cloud storage.' ),
		);
		foreach ( $rows as $r ) {
			echo '<div class="pk-def"><div class="pk-def-term"><a href="' . $u( $r[1] ) . '">' . wp_kses_post( $r[0] ) . '</a></div><div class="pk-def-desc">' . wp_kses_post( $r[2] ) . '</div></div>';
		}
		echo '</div>';

		// manifest
		$this->section( 'Manifest reference', 'Every bundle is driven by a single <code>manifest.json</code>. Minimal shape:' );
		echo '<pre class="pk-code">{
  "project": "Acme Co",
  "global_kit": "global-kit.json",
  "replace": [
    { "find": "(555) 123-4567", "replace": "(770) 555-0000" }
  ],
  "pages": [
    { "file": "home.json", "slug": "home", "title": "Acme — Home",
      "is_front_page": true, "seo_title": "Acme | Widgets" }
  ],
  "theme_parts": [ { "file": "header.json", "part": "header", "condition": "include/general" } ],
  "menus": [ { "name": "Primary", "location": "primary",
              "items": [ { "slug": "home", "label": "Home" } ] } ]
}</pre>';

		// cli
		$this->section( 'WP-CLI', 'For scripted deploys and CI/CD:' );
		echo '<pre class="pk-code">wp rk-migrate export --output=site.zip --media
wp rk-migrate import site.zip --from=https://staging.x --to=https://live.x --dry-run
wp rk-migrate list-library
wp rk-migrate rollback &lt;snapshot-token&gt;</pre>';

		// faq
		$this->section( 'FAQ', '' );
		echo '<div class="pk-faq">';
		$faqs = array(
			array( 'Will re-running create duplicates?', 'No. Pages match by slug and templates by title, so re-running updates in place. Menus are rebuilt cleanly each run.' ),
			array( 'Are my images migrated?', 'Only if you tick <em>media re-link</em> on import (or bundle media on export). Otherwise image URLs are kept as-is.' ),
			array( 'How do I undo an import?', 'Keep <em>snapshot</em> on before running, then restore from <a href="' . esc_url( $this->tab_url( 'history' ) ) . '">History &amp; Rollback</a>.' ),
			array( 'Is the Remote API safe?', 'It is off by default and token-gated. Enable it only while pairing sites and keep the token secret.' ),
		);
		foreach ( $faqs as $f ) {
			echo '<details class="pk-faq-item"><summary>' . wp_kses_post( $f[0] ) . '</summary><div class="pk-faq-a">' . wp_kses_post( $f[1] ) . '</div></details>';
		}
		echo '</div>';

		echo '<div class="pk-callout pk-callout-muted">Full written docs ship as <code>DOCUMENTATION.md</code> inside the plugin folder. Built by ' . wp_kses_post( $this->s()->credit_html() ) . '.</div>';
		echo '</div>';
	}

	private function mini_form( $action, $fields, $label, $type = 'secondary' ) {
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		foreach ( $fields as $k => $v ) { $h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '" />'; }
		$h .= wp_nonce_field( $action, '_wpnonce', true, false );
		$cls = 'link-delete' === $type ? 'button-link delete' : 'button button-' . $type;
		$h .= '<button type="submit" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
		return $h;
	}

	/* =================== HANDLERS =================== */
	public function handle_upload() {
		if ( ! $this->s()->current_user_can( 'import' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_upload' );
		if ( ! empty( $_POST['clear'] ) ) { update_option( 'rk_migrate_active_bundle', '' ); $this->redirect( 'cleared' ); }
		if ( empty( $_FILES['bundle']['name'] ) ) { $this->redirect( 'nofile' ); }
		$f = $_FILES['bundle'];
		if ( ! empty( $f['error'] ) ) { $this->redirect( 'uploaderr' ); }
		$ext = strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'zip', 'epenc' ), true ) ) { $this->redirect( 'notzip' ); }

		if ( ! file_exists( RK_MIGRATE_UPLOAD_DIR ) ) { wp_mkdir_p( RK_MIGRATE_UPLOAD_DIR ); }
		if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) { $this->redirect( 'nofile' ); }
		$src = $f['tmp_name'];
		if ( 'epenc' === $ext ) {
			$pw = isset( $_POST['decrypt_pw'] ) ? wp_unslash( $_POST['decrypt_pw'] ) : '';
			$dec = RK_MIGRATE_UPLOAD_DIR . 'dec-' . gmdate( 'Ymd-His' ) . '.zip';
			move_uploaded_file( $f['tmp_name'], $dec . '.epenc' );
			$out = RK_Migrate_Library::decrypt_file( $dec . '.epenc', $pw );
			@unlink( $dec . '.epenc' );
			if ( is_wp_error( $out ) ) { $this->redirect( 'decryptfail' ); }
			$src = $out;
		}

		$slug_dir = RK_MIGRATE_UPLOAD_DIR . 'bundle-' . gmdate( 'Ymd-His' ) . '/';
		wp_mkdir_p( $slug_dir );
		if ( ! function_exists( 'unzip_file' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
		WP_Filesystem();
		$unzipped = unzip_file( $src, $slug_dir );
		if ( is_wp_error( $unzipped ) ) { $this->redirect( 'unzipfail' ); }
		$base = $this->find_manifest_dir( $slug_dir );
		if ( ! $base ) { $this->redirect( 'nomanifest' ); }
		$kit = RK_Migrate_Kit::maybe_convert( $base ); // auto-convert third-party template kits
		update_option( 'rk_migrate_active_bundle', untrailingslashit( $base ) );
		if ( is_array( $kit ) ) {
			set_transient( 'rk_migrate_kit_note', sprintf( '“%s” detected as a template kit and converted — %d templates mapped.', $kit['title'], $kit['count'] ), 60 );
		}
		$this->redirect( 'uploaded' );
	}

	private function find_manifest_dir( $dir ) {
		if ( file_exists( trailingslashit( $dir ) . 'manifest.json' ) ) { return trailingslashit( $dir ); }
		foreach ( glob( trailingslashit( $dir ) . '*', GLOB_ONLYDIR ) as $sub ) {
			if ( file_exists( trailingslashit( $sub ) . 'manifest.json' ) ) { return trailingslashit( $sub ); }
		}
		return '';
	}

	/** Non-JS fallback synchronous import. */
	public function handle_run() {
		if ( ! $this->s()->current_user_can( 'import' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_run' );
		$base = $this->active_base();
		if ( ! $base ) { $this->redirect( 'nosource' ); }
		$importer = new RK_Migrate_Importer( $base );
		$report = $importer->run( array(
			'dry' => ! empty( $_POST['dry_run'] ), 'set_front' => ! empty( $_POST['set_front'] ),
			'assign_parts' => ! empty( $_POST['assign_parts'] ), 'build_menus' => ! empty( $_POST['build_menus'] ),
		) );
		set_transient( 'rk_migrate_report', $report, 180 );
		$this->redirect( 'done' );
	}

	public function handle_library() {
		if ( ! $this->s()->current_user_can( 'import' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_library' );
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		if ( 'activate' === $do ) {
			$path = RK_Migrate_Library::path( sanitize_text_field( wp_unslash( $_POST['slug'] ) ) );
			if ( $path ) { update_option( 'rk_migrate_active_bundle', untrailingslashit( $path ) ); $this->redirect( 'lib_active', 'library' ); }
			$this->redirect( 'nomanifest', 'library' );
		} elseif ( 'delete' === $do ) {
			RK_Migrate_Library::delete( sanitize_text_field( wp_unslash( $_POST['slug'] ) ) );
			$this->redirect( 'lib_deleted', 'library' );
		} elseif ( 'store' === $do ) {
			if ( empty( $_FILES['bundle']['tmp_name'] ) || ! is_uploaded_file( $_FILES['bundle']['tmp_name'] ) ) { $this->redirect( 'nofile', 'library' ); }
			$slug = RK_Migrate_Library::store_zip( $_FILES['bundle']['tmp_name'], pathinfo( $_FILES['bundle']['name'], PATHINFO_FILENAME ) );
			$this->redirect( is_wp_error( $slug ) ? 'nomanifest' : 'lib_stored', 'library' );
		}
		$this->redirect( 'done', 'library' );
	}

	public function handle_remote() {
		if ( ! $this->s()->can_use( 'remote' ) || ! $this->s()->current_user_can( 'export' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_remote' );
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$url = isset( $_POST['remote_url'] ) ? esc_url_raw( wp_unslash( $_POST['remote_url'] ) ) : '';
		$token = isset( $_POST['remote_token'] ) ? sanitize_text_field( wp_unslash( $_POST['remote_token'] ) ) : '';

		if ( 'push' === $do ) {
			$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'active';
			$base = ( 0 === strpos( $source, 'lib:' ) ) ? RK_Migrate_Library::path( substr( $source, 4 ) ) : $this->active_base();
			if ( ! $base ) { $this->redirect( 'nosource', 'remote' ); }
			$zip = $this->zip_dir( $base );
			$res = RK_Migrate_Remote::push_to( $url, $token, $zip, array( 'assign_parts' => true, 'build_menus' => true, 'media_relink' => true ) );
			@unlink( $zip );
			set_transient( 'rk_migrate_report', is_wp_error( $res ) ? array( 'Push failed: ' . $res->get_error_message() ) : array( 'Push OK', wp_json_encode( $res['counts'] ?? array() ) ), 180 );
			$this->redirect( 'done', 'remote' );
		} elseif ( 'pull' === $do ) {
			$path = RK_Migrate_Remote::pull_from( $url, $token, ! empty( $_POST['include_media'] ) );
			if ( is_wp_error( $path ) ) { set_transient( 'rk_migrate_report', array( 'Pull failed: ' . $path->get_error_message() ), 180 ); $this->redirect( 'done', 'remote' ); }
			$slug = RK_Migrate_Library::store_zip( $path, 'pulled' );
			set_transient( 'rk_migrate_report', array( 'Pulled into library: ' . ( is_wp_error( $slug ) ? $slug->get_error_message() : $slug ) ), 180 );
			$this->redirect( 'done', 'remote' );
		} elseif ( 'sync' === $do ) {
			$targets = array();
			foreach ( preg_split( '/\r?\n/', wp_unslash( $_POST['targets'] ?? '' ) ) as $line ) {
				$line = trim( $line ); if ( ! $line ) { continue; }
				list( $u, $t ) = array_pad( explode( '|', $line, 2 ), 2, '' );
				if ( $u && $t ) { $targets[] = array( 'url' => esc_url_raw( $u ), 'token' => trim( $t ) ); }
			}
			$res = RK_Migrate_Remote::sync_component( (int) $_POST['template_id'], $targets );
			set_transient( 'rk_migrate_report', is_wp_error( $res ) ? array( $res->get_error_message() ) : array( 'Synced to ' . count( $targets ) . ' site(s).' ), 180 );
			$this->redirect( 'done', 'remote' );
		}
		$this->redirect( 'done', 'remote' );
	}

	/** Zip a bundle directory into a temp zip for remote push. */
	private function zip_dir( $base ) {
		$path = trailingslashit( RK_MIGRATE_EXPORT_DIR ) . 'push-' . gmdate( 'Ymd-His' ) . '.zip';
		if ( ! file_exists( RK_MIGRATE_EXPORT_DIR ) ) { wp_mkdir_p( RK_MIGRATE_EXPORT_DIR ); }
		$zip = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( glob( trailingslashit( $base ) . '*' ) as $f ) { if ( is_file( $f ) ) { $zip->addFile( $f, basename( $f ) ); } }
		$zip->close();
		return $path;
	}

	public function handle_marketplace() {
		if ( ! $this->s()->current_user_can( 'import' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_marketplace' );
		$slug = RK_Migrate_Marketplace::install( sanitize_text_field( wp_unslash( $_POST['item'] ?? '' ) ) );
		set_transient( 'rk_migrate_report', array( is_wp_error( $slug ) ? ( 'Install failed: ' . $slug->get_error_message() ) : ( 'Installed to library: ' . $slug ) ), 180 );
		$this->redirect( 'done', 'market' );
	}

	public function handle_cloud() {
		if ( ! $this->s()->current_user_can( 'import' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_cloud' );
		if ( 'download' === ( $_POST['do'] ?? '' ) ) {
			$slug = RK_Migrate_Cloud::download_to_library( sanitize_text_field( wp_unslash( $_POST['id'] ) ) );
			set_transient( 'rk_migrate_report', array( is_wp_error( $slug ) ? $slug->get_error_message() : ( 'Downloaded: ' . $slug ) ), 180 );
		}
		$this->redirect( 'done', 'market' );
	}

	public function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_settings' );
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : 'save';

		if ( 'save_manifest' === $do ) {
			$base = $this->active_base();
			$pages = array();
			foreach ( (array) ( $_POST['pages'] ?? array() ) as $p ) {
				if ( empty( $p['file'] ) && empty( $p['slug'] ) ) { continue; }
				$row = array(
					'file'  => basename( sanitize_text_field( $p['file'] ?? '' ) ),
					'slug'  => sanitize_title( $p['slug'] ?? '' ),
					'title' => sanitize_text_field( $p['title'] ?? '' ),
					'post_type' => sanitize_key( $p['post_type'] ?? 'page' ),
				);
				if ( ! empty( $p['is_front_page'] ) ) { $row['is_front_page'] = true; }
				if ( ! empty( $p['seo_title'] ) ) { $row['seo_title'] = sanitize_text_field( $p['seo_title'] ); }
				$pages[] = $row;
			}
			$manifest = array( 'project' => sanitize_text_field( $_POST['b_project'] ?? 'Built' ), 'pages' => $pages );
			$source_slug = ( $base && 0 === strpos( $base, RK_MIGRATE_LIBRARY_DIR ) ) ? basename( dirname( trailingslashit( $base ) . 'x' ) ) : '';
			$slug = RK_Migrate_Manifest_Builder::save( $manifest, $manifest['project'], '' );
			// copy referenced files from active base
			$dir = RK_Migrate_Library::path( $slug );
			if ( $base && $dir ) { foreach ( glob( trailingslashit( $base ) . '*.json' ) as $f ) { if ( basename( $f ) !== 'manifest.json' ) { @copy( $f, trailingslashit( $dir ) . basename( $f ) ); } } }
			set_transient( 'rk_migrate_report', array( 'Manifest saved to library: ' . $slug ), 180 );
			$this->redirect( 'done', 'library' );
		}

		$cur = $this->s();
		$checkbox = function ( $k ) { return ! empty( $_POST[ $k ] ) ? 1 : 0; };
		$cur->update( array(
			'tier'            => in_array( $_POST['tier'] ?? 'agency', array( 'free', 'pro', 'agency' ), true ) ? $_POST['tier'] : 'agency',
			'cap_import'      => sanitize_text_field( $_POST['cap_import'] ?? 'manage_options' ),
			'cap_export'      => sanitize_text_field( $_POST['cap_export'] ?? 'manage_options' ),
			'cap_rollback'    => sanitize_text_field( $_POST['cap_rollback'] ?? 'manage_options' ),
			'cap_view_log'    => sanitize_text_field( $_POST['cap_view_log'] ?? 'manage_options' ),
			'webhook_url'     => esc_url_raw( $_POST['webhook_url'] ?? '' ),
			'webhook_on'      => array_map( 'sanitize_key', (array) ( $_POST['webhook_on'] ?? array() ) ),
			'remote_enabled'  => $checkbox( 'remote_enabled' ),
			'remote_token'    => sanitize_text_field( $_POST['remote_token'] ?? '' ),
			'remote_allow_pull' => $checkbox( 'remote_allow_pull' ),
			'ai_api_key'      => sanitize_text_field( $_POST['ai_api_key'] ?? '' ),
			'ai_model'        => sanitize_text_field( $_POST['ai_model'] ?? '' ),
			'ai_endpoint'     => esc_url_raw( $_POST['ai_endpoint'] ?? '' ),
			'cloud_endpoint'  => esc_url_raw( $_POST['cloud_endpoint'] ?? '' ),
			'cloud_token'     => sanitize_text_field( $_POST['cloud_token'] ?? '' ),
		) );
		$this->redirect( 'saved', 'settings' );
	}

	private function redirect( $code, $tab = 'import' ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab . '&rk-migrate=' . $code ) );
		exit;
	}

	public function notices() {
		if ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] || ! isset( $_GET['rk-migrate'] ) ) { return; }
		$code = sanitize_key( $_GET['rk-migrate'] );
		$map = array(
			'uploaded' => array( 'success', 'Bundle uploaded and selected.' ),
			'cleared'  => array( 'success', 'Uploaded bundle cleared.' ),
			'saved'    => array( 'success', 'Settings saved.' ),
			'lib_active' => array( 'success', 'Library bundle activated as source.' ),
			'lib_deleted' => array( 'success', 'Library bundle deleted.' ),
			'lib_stored' => array( 'success', 'Bundle stored in library.' ),
			'nofile'   => array( 'error', 'No file selected.' ),
			'notzip'   => array( 'error', 'Please upload a .zip or .epenc bundle.' ),
			'uploaderr'=> array( 'error', 'Upload failed (size limit?).' ),
			'unzipfail'=> array( 'error', 'Could not unzip the bundle.' ),
			'decryptfail' => array( 'error', 'Decryption failed (wrong password?).' ),
			'nomanifest'=> array( 'error', 'No manifest.json found.' ),
			'nosource' => array( 'error', 'No valid project source.' ),
		);
		if ( isset( $map[ $code ] ) ) { echo '<div class="notice notice-' . esc_attr( $map[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $code ][1] ) . '</p></div>'; }
		$kitnote = get_transient( 'rk_migrate_kit_note' );
		if ( $kitnote ) { echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $kitnote ) . '</p></div>'; delete_transient( 'rk_migrate_kit_note' ); }
		if ( 'done' === $code ) {
			$report = get_transient( 'rk_migrate_report' );
			if ( $report ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>Done.</strong></p><ol style="margin-left:18px;">';
				foreach ( $report as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; }
				echo '</ol></div>';
				delete_transient( 'rk_migrate_report' );
			}
		}
	}

	/* =================== PREMIUM UI STYLES =================== */
	private function print_css() {
		?>
<style>
:root{
  --pk-accent:#3a55d9; --pk-accent-d:#2c44b8; --pk-accent2:#5a70e6;
  --pk-grad:linear-gradient(135deg,#3a55d9 0%,#5a70e6 100%);
  --pk-ink:#1b2733; --pk-muted:#7a8794; --pk-faint:#9aa6b2;
  --pk-card:#ffffff; --pk-border:#ecf0f2; --pk-border2:#e1e7ea;
  --pk-soft:#f4f7f8; --pk-tint:rgba(0,150,135,.09);
  --pk-green:#0f9d6b; --pk-amber:#c07f1a; --pk-red:#dc4b53;
  --pk-shadow:0 1px 2px rgba(16,24,40,.04),0 16px 32px -18px rgba(16,24,40,.22);
  --pk-shadow-sm:0 1px 2px rgba(16,24,40,.05),0 6px 16px -10px rgba(16,24,40,.18);
}
.rk-migrate-wrap{ max-width:1280px!important; margin:16px 16px 64px 0; color:var(--pk-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased; }
.rk-migrate-wrap *{ box-sizing:border-box; }
.rk-migrate-wrap a{ color:var(--pk-accent); }
.rk-migrate-wrap a:not(.pk-railitem):not(.pk-subnav-item):not(.pk-nav-item):not(.button):not(.pk-btn-ghost):hover{ color:var(--pk-accent-d); }
.rk-migrate-wrap .button:focus,.rk-migrate-wrap .button:hover{ color:var(--pk-accent-d); }
.rk-migrate-wrap .button-primary:hover,.rk-migrate-wrap .button-hero:hover,.rk-migrate-wrap .button-primary:focus,.rk-migrate-wrap .button-hero:focus{ color:#fff!important; }
.pk-btn-ghost:hover,.pk-btn-ghost:focus{ color:var(--pk-accent-d)!important; }
.pk-railitem:focus{ color:var(--pk-ink); }
.pk-railitem-on:hover,.pk-railitem-on:focus{ color:#fff!important; }

/* eyebrow label (reference style) */
.pk-eyebrow{ display:flex; align-items:center; gap:9px; font-size:11px; font-weight:700;
  letter-spacing:.13em; text-transform:uppercase; color:var(--pk-faint); margin-bottom:5px; }
.pk-eyebrow::before{ content:""; width:20px; height:2px; border-radius:2px; background:var(--pk-accent); }

/* hero */
.pk-hero{ display:flex; align-items:center; gap:18px;
  background:linear-gradient(110deg,#eef1fd 0%,#ffffff 46%),var(--pk-card);
  border:1px solid var(--pk-border); border-radius:18px; padding:22px 28px;
  box-shadow:var(--pk-shadow); position:relative; overflow:hidden; }
.pk-hero::after{ content:""; position:absolute; top:0; right:0; width:30%; height:100%;
  background-image:radial-gradient(rgba(0,150,135,.08) 1.1px,transparent 1.1px);
  background-size:18px 18px; -webkit-mask-image:linear-gradient(90deg,transparent,#000 80%);
  mask-image:linear-gradient(90deg,transparent,#000 80%); pointer-events:none; }
.pk-hero-left{ display:flex; align-items:center; gap:16px; position:relative; z-index:1; }
.pk-logo{ width:54px; height:54px; border-radius:15px; background:var(--pk-grad); color:#fff;
  display:flex; align-items:center; justify-content:center; font-weight:800; font-size:27px;
  letter-spacing:-1px; box-shadow:0 8px 18px -6px rgba(0,150,135,.5); flex:none; }
.pk-brand{ font-size:25px; font-weight:800; letter-spacing:-.6px; line-height:1.05; }
.pk-ver{ font-size:11px; font-weight:700; color:var(--pk-accent); background:var(--pk-tint);
  border-radius:6px; padding:3px 8px; margin-left:7px; vertical-align:middle; letter-spacing:0; }
.pk-tag{ color:var(--pk-muted); font-size:13px; margin-top:4px; }
.pk-tag a{ color:var(--pk-accent); text-decoration:none; font-weight:600; }
.pk-tag a:hover{ text-decoration:underline; }
.pk-hero-right{ margin-left:auto; display:flex; flex-direction:column; align-items:flex-end; gap:7px; position:relative; z-index:2; }
.pk-status{ display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:600;
  padding:6px 12px; border-radius:999px; }
.pk-status .pk-dot{ width:7px; height:7px; border-radius:50%; }
.pk-status-ok{ color:var(--pk-green); background:rgba(15,157,107,.1); }
.pk-status-ok .pk-dot{ background:var(--pk-green); box-shadow:0 0 0 3px rgba(15,157,107,.18); }
.pk-status-off{ color:var(--pk-red); background:rgba(220,75,83,.1); }
.pk-status-off .pk-dot{ background:var(--pk-red); }
.pk-tier{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
  padding:6px 13px; border-radius:999px; white-space:nowrap; }
.pk-tier-free{ color:var(--pk-green); background:rgba(15,157,107,.1); }
.pk-tier-pro{ color:var(--pk-accent); background:var(--pk-tint); }
.pk-tier-agency{ color:var(--pk-amber); background:rgba(192,127,26,.12); }

/* alerts */
.pk-alert{ border-radius:13px; padding:13px 17px; margin:16px 0 0; font-size:13.5px; border:1px solid; }
.pk-alert-warn{ background:#fff8ea; border-color:#f3e2b3; color:#7a5a12; }

/* nav pills */
.pk-nav{ display:flex; flex-wrap:wrap; gap:5px; margin:16px 0 0; padding:6px;
  background:var(--pk-card); border:1px solid var(--pk-border); border-radius:15px; box-shadow:var(--pk-shadow-sm); }
.pk-nav-item{ text-decoration:none; color:var(--pk-muted); font-weight:600; font-size:13.5px;
  padding:9px 17px; border-radius:10px; transition:all .15s; white-space:nowrap; }
.pk-nav-item:hover{ color:var(--pk-ink); background:var(--pk-soft); }
.pk-nav-item.pk-nav-active{ color:#fff; background:var(--pk-grad); box-shadow:0 6px 14px -4px rgba(0,150,135,.5); }

/* panel */
.pk-panel{ background:var(--pk-card); border:1px solid var(--pk-border); border-radius:18px;
  padding:30px 34px 34px; margin-top:16px; box-shadow:var(--pk-shadow); }
.rk-migrate-wrap h3{ font-size:16px; font-weight:700; letter-spacing:-.2px; margin:30px 0 14px;
  padding-bottom:0; border:none; display:flex; align-items:center; gap:10px; }
.rk-migrate-wrap h3::before{ content:""; width:4px; height:18px; border-radius:3px; background:var(--pk-grad); }
.rk-migrate-wrap h3:first-child{ margin-top:0; }
.rk-migrate-wrap h4{ font-size:13.5px; font-weight:700; color:var(--pk-ink); margin:22px 0 9px; }
.rk-migrate-wrap p{ font-size:13.5px; color:var(--pk-ink); line-height:1.6; }
.rk-migrate-wrap code{ background:var(--pk-soft); color:var(--pk-accent-d); border:1px solid var(--pk-border);
  border-radius:6px; padding:1px 7px; font-size:12px; }

/* stat cards (reference style) */
.pk-stats{ display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin:6px 0 8px; }
.pk-stat{ position:relative; overflow:hidden; background:var(--pk-card); border:1px solid var(--pk-border);
  border-radius:16px; padding:18px 18px 16px; box-shadow:var(--pk-shadow-sm); }
.pk-stat-icon{ width:38px; height:38px; border-radius:11px; background:var(--pk-tint); color:var(--pk-accent);
  display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:14px; }
.pk-stat-num{ font-size:30px; font-weight:800; letter-spacing:-1px; line-height:1; }
.pk-stat-label{ display:flex; align-items:center; gap:7px; font-size:11px; font-weight:700; letter-spacing:.08em;
  text-transform:uppercase; color:var(--pk-faint); margin-top:8px; }
.pk-stat-label span{ width:14px; height:2px; border-radius:2px; background:var(--pk-accent); flex:none; }
.pk-stat-ghost{ position:absolute; right:-14px; bottom:-20px; font-size:96px; line-height:1;
  color:var(--pk-soft); z-index:0; pointer-events:none; }
.pk-stat>*{ position:relative; z-index:1; }
.pk-stat-ghost{ z-index:0; }
.pk-stat-green .pk-stat-icon{ color:var(--pk-green); background:rgba(15,157,107,.1); }
.pk-stat-green .pk-stat-label span{ background:var(--pk-green); }
.pk-stat-amber .pk-stat-icon{ color:var(--pk-amber); background:rgba(192,127,26,.12); }
.pk-stat-amber .pk-stat-label span{ background:var(--pk-amber); }

/* forms */
.rk-migrate-wrap input[type=text],.rk-migrate-wrap input[type=url],.rk-migrate-wrap input[type=password],
.rk-migrate-wrap input[type=file],.rk-migrate-wrap select,.rk-migrate-wrap textarea{
  border:1px solid var(--pk-border2); border-radius:8px; padding:8px 12px; font-size:13px;
  background:#fff; color:var(--pk-ink); box-shadow:none; transition:border .15s,box-shadow .15s; min-height:36px; }
.rk-migrate-wrap input[type=text]:focus,.rk-migrate-wrap input[type=url]:focus,.rk-migrate-wrap input[type=password]:focus,
.rk-migrate-wrap select:focus,.rk-migrate-wrap textarea:focus{
  border-color:var(--pk-accent); box-shadow:0 0 0 3px rgba(0,150,135,.16); outline:none; }
.rk-migrate-wrap label{ font-size:13.5px; }
.rk-migrate-wrap form p{ margin:10px 0; }
.rk-migrate-wrap input[type=checkbox]{ border-radius:5px; border:1.5px solid var(--pk-border2); width:18px; height:18px; margin-top:-2px; }
.rk-migrate-wrap input[type=checkbox]:checked{ background:var(--pk-accent); border-color:var(--pk-accent); }
.rk-migrate-wrap input[type=checkbox]:checked::before{ color:#fff; }

/* buttons */
.rk-migrate-wrap .button{ border-radius:8px; border:1px solid var(--pk-border2); background:#fff; color:var(--pk-ink);
  font-weight:600; font-size:13px; min-height:36px; padding:8px 16px; line-height:1.4; transition:all .15s; box-shadow:none; display:inline-flex; align-items:center; gap:6px; }
.rk-migrate-wrap .button:hover{ background:var(--pk-soft); border-color:var(--pk-accent); color:var(--pk-accent-d); }
.rk-migrate-wrap .button-primary,.rk-migrate-wrap .button-hero{ background:var(--pk-grad)!important; border:none!important;
  color:#fff!important; box-shadow:0 8px 18px -6px rgba(0,150,135,.55); text-shadow:none; }
.rk-migrate-wrap .button-primary:hover,.rk-migrate-wrap .button-hero:hover{ transform:translateY(-1px);
  box-shadow:0 11px 22px -6px rgba(0,150,135,.6); color:#fff!important; }
.rk-migrate-wrap .button-hero{ padding:8px 20px!important; font-size:13px!important; min-height:36px; border-radius:8px; }
.rk-migrate-wrap .button-link.delete,.rk-migrate-wrap .link-delete{ color:var(--pk-red); box-shadow:none; border:none; background:none; font-weight:600; }
.rk-migrate-wrap .button-link.delete:hover{ color:#b3343c; background:none; }
.rk-migrate-wrap .button-link{ color:var(--pk-accent); }

/* tables — full width within card */
.rk-migrate-wrap .widefat{ width:100%!important; max-width:100%!important; border:1px solid var(--pk-border);
  border-radius:13px; overflow:hidden; border-collapse:separate; border-spacing:0; box-shadow:none; margin:6px 0 4px; }
.rk-migrate-wrap .widefat thead th{ background:var(--pk-soft); color:var(--pk-faint); font-weight:700;
  font-size:11px; text-transform:uppercase; letter-spacing:.05em; border:none; padding:12px 15px; }
.rk-migrate-wrap .widefat tbody td{ border-top:1px solid var(--pk-border); padding:12px 15px; font-size:13px; vertical-align:middle; }
.rk-migrate-wrap .widefat tbody tr:first-child td{ border-top:none; }
.rk-migrate-wrap .widefat tbody tr:hover td{ background:#f6f7fb; }
.rk-migrate-wrap .widefat.striped tbody tr:nth-child(odd) td{ background:#fcfdfd; }
.rk-migrate-wrap .widefat.striped tbody tr:hover td{ background:#f5f7fe; }

/* details / accordions */
.rk-migrate-wrap details{ border:1px solid var(--pk-border)!important; border-radius:13px!important; background:#fff; padding:12px 14px; }
.rk-migrate-wrap details[open]{ box-shadow:var(--pk-shadow-sm); }
.rk-migrate-wrap summary{ font-weight:700; font-size:13.5px; cursor:pointer; }

/* inline notices */
.rk-migrate-wrap .notice{ border-radius:11px; border-left-width:4px; box-shadow:none; margin:14px 0; }
.rk-migrate-wrap .notice.inline{ background:#fff; }

/* progress + log */
#rk-migrate-progress{ background:var(--pk-soft); border:1px solid var(--pk-border); border-radius:15px; padding:20px; max-width:100%!important; }
#rk-migrate-bar{ background:var(--pk-grad)!important; }
#rk-migrate-log,#rk-migrate-rollback-log{ border-radius:11px!important; }

/* ===== upgraded components ===== */
/* soften primary button shadow */
.rk-migrate-wrap .button-primary,.rk-migrate-wrap .button-hero{ box-shadow:0 4px 12px -4px rgba(0,150,135,.45)!important; }
.rk-migrate-wrap .button-primary:hover,.rk-migrate-wrap .button-hero:hover{ box-shadow:0 7px 16px -5px rgba(0,150,135,.5)!important; }

/* section header + description */
.pk-section{ margin:28px 0 14px; }
.pk-section:first-child{ margin-top:0; }
.pk-section h3{ margin:0 0 4px; }
.pk-section-desc{ color:var(--pk-muted); font-size:13px; margin:0; max-width:680px; line-height:1.6; }

/* documentation link in hero */
.pk-doclink{ font-size:12.5px; font-weight:600; color:var(--pk-accent)!important; text-decoration:none; }
.pk-doclink:hover{ text-decoration:underline; }

/* segmented control */
.pk-seg{ display:inline-flex; background:var(--pk-soft); border:1px solid var(--pk-border); border-radius:12px; padding:4px; gap:4px; }
.pk-seg-item{ position:relative; font-size:13px; font-weight:600; color:var(--pk-muted); padding:8px 18px; border-radius:9px; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pk-seg-item input{ position:absolute; opacity:0; pointer-events:none; }
.pk-seg-item:hover{ color:var(--pk-ink); }
.pk-seg-item.pk-seg-on{ background:#fff; color:var(--pk-accent-d); box-shadow:0 1px 3px rgba(16,24,40,.12); }

/* field rows */
.pk-field{ margin:0 0 16px; }
.pk-field-label{ display:block; font-weight:600; font-size:12.5px; color:var(--pk-ink); margin-bottom:6px; }
.pk-field-desc{ color:var(--pk-muted); font-size:12px; margin-top:6px; line-height:1.5; }
.pk-grid2{ display:grid; grid-template-columns:1fr 1fr; gap:18px 26px; max-width:620px; }
.pk-grid2 .pk-field{ margin:0; }
.pk-inline{ display:flex; gap:8px; align-items:center; }

/* toggle switch */
.pk-toggle{ display:flex; align-items:flex-start; gap:11px; margin:11px 0; cursor:pointer; max-width:620px; }
.pk-toggle input{ position:absolute; opacity:0; width:0; height:0; }
.pk-toggle-track{ flex:none; width:38px; height:22px; border-radius:999px; background:#cdd5db; position:relative; transition:background .18s; margin-top:1px; }
.pk-toggle-thumb{ position:absolute; top:2px; left:2px; width:18px; height:18px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(16,24,40,.3); transition:transform .18s; }
.pk-toggle input:checked + .pk-toggle-track{ background:var(--pk-accent); }
.pk-toggle input:checked + .pk-toggle-track .pk-toggle-thumb{ transform:translateX(16px); }
.pk-toggle input:focus + .pk-toggle-track{ box-shadow:0 0 0 3px rgba(0,150,135,.2); }
.pk-toggle-text{ font-size:13.5px; font-weight:500; color:var(--pk-ink); line-height:1.4; }
.pk-toggle-desc{ display:block; font-weight:400; font-size:12px; color:var(--pk-muted); margin-top:2px; }

/* save bar */
.pk-savebar{ display:flex; align-items:center; gap:14px; margin-top:26px; padding-top:20px; border-top:1px solid var(--pk-border); }
.pk-savebar-hint{ color:var(--pk-muted); font-size:12.5px; }

/* marketplace cards */
.pk-cloud-note{ font-size:12.5px; color:var(--pk-muted); margin:0 0 16px; }
.pk-cards{ display:grid; grid-template-columns:repeat(auto-fill,minmax(248px,1fr)); gap:16px; }
.pk-mcard{ display:flex; flex-direction:column; border:1px solid var(--pk-border); border-radius:15px; padding:18px; background:#fff; box-shadow:var(--pk-shadow-sm); transition:transform .15s,box-shadow .15s; }
.pk-mcard:hover{ transform:translateY(-2px); box-shadow:var(--pk-shadow); }
.pk-mcard-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
.pk-mcard-title{ font-weight:700; font-size:15px; letter-spacing:-.2px; }
.pk-mcard-meta{ color:var(--pk-accent-d); font-size:12px; font-weight:600; margin:5px 0 9px; }
.pk-mcard-desc{ color:var(--pk-muted); font-size:12.5px; line-height:1.55; flex:1; min-height:54px; }
.pk-mcard-foot{ margin-top:14px; }
.pk-mcard-foot .button{ width:100%; text-align:center; }
.pk-badge{ font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; white-space:nowrap; }
.pk-badge-free{ color:var(--pk-green); background:rgba(15,157,107,.12); }
.pk-badge-paid{ color:var(--pk-accent-d); background:var(--pk-tint); }

/* documentation */
.pk-doc{ max-width:820px; }
.pk-steps{ display:flex; flex-direction:column; gap:14px; margin:6px 0 4px; }
.pk-step{ display:flex; gap:14px; align-items:flex-start; background:var(--pk-soft); border:1px solid var(--pk-border); border-radius:13px; padding:16px 18px; }
.pk-step-n{ flex:none; width:30px; height:30px; border-radius:50%; background:var(--pk-grad); color:#fff; font-weight:800; font-size:14px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px -3px rgba(0,150,135,.5); }
.pk-step strong{ font-size:14px; }
.pk-step p{ margin:3px 0 0; color:var(--pk-muted); font-size:13px; }
.pk-callout{ background:var(--pk-tint); border:1px solid rgba(0,150,135,.22); border-radius:12px; padding:13px 16px; margin:16px 0; font-size:13px; color:var(--pk-ink); line-height:1.55; }
.pk-callout-muted{ background:var(--pk-soft); border-color:var(--pk-border); color:var(--pk-muted); }
.pk-deflist{ display:grid; gap:2px; }
.pk-def{ display:grid; grid-template-columns:180px 1fr; gap:18px; padding:12px 0; border-bottom:1px solid var(--pk-border); }
.pk-def:last-child{ border-bottom:none; }
.pk-def-term a{ font-weight:700; font-size:13.5px; text-decoration:none; }
.pk-def-desc{ color:var(--pk-muted); font-size:13px; line-height:1.55; }
.pk-code{ background:#0e1726; color:#cbd5e1; border-radius:12px; padding:16px 18px; font-size:12.5px; line-height:1.6; overflow:auto; margin:8px 0 4px; font-family:"SFMono-Regular",Menlo,Consolas,monospace; }
.pk-faq{ display:grid; gap:8px; }
.pk-faq-item{ border:1px solid var(--pk-border); border-radius:12px; padding:0; background:#fff; }
.pk-faq-item summary{ padding:14px 16px; }
.pk-faq-a{ padding:0 16px 14px; color:var(--pk-muted); font-size:13px; line-height:1.6; }
.pk-doc a{ color:var(--pk-accent); }

.pk-deps{ display:flex; flex-wrap:wrap; gap:8px; margin:4px 0 4px; }
.pk-dep{ display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--pk-ink); background:var(--pk-soft); border:1px solid var(--pk-border); border-radius:999px; padding:6px 12px; }
.pk-dep .pk-ico{ width:15px; height:15px; color:var(--pk-accent); }
.pk-dep-n{ color:var(--pk-accent-d); background:var(--pk-tint); border-radius:999px; padding:0 7px; font-size:11px; }
.pk-conflict{ min-width:120px; }

/* selective-export accordion + checklist */
.pk-acc{ border:1px solid var(--pk-border)!important; border-radius:13px!important; padding:0!important; margin:0 0 14px; background:#fff; overflow:hidden; }
.pk-acc[open]{ box-shadow:var(--pk-shadow-sm); }
.pk-acc>summary{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 16px; list-style:none; cursor:pointer; font-weight:700; font-size:13.5px; }
.pk-acc>summary::-webkit-details-marker{ display:none; }
.pk-acc-title{ display:flex; align-items:center; gap:9px; }
.pk-acc-count{ font-size:11px; font-weight:700; color:var(--pk-accent-d); background:var(--pk-tint); border-radius:999px; padding:2px 9px; min-width:24px; text-align:center; }
.pk-acc-actions{ display:flex; align-items:center; gap:9px; font-size:12px; }
.pk-sep{ color:var(--pk-border2); }
.pk-checklist{ display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:2px 12px; padding:4px 10px 12px; border-top:1px solid var(--pk-border); }
.pk-check{ display:flex; align-items:center; gap:10px; min-width:0; padding:8px 10px; border-radius:9px; font-size:13px; cursor:pointer; }
.pk-check:hover{ background:var(--pk-soft); }
.pk-check input{ flex:none; margin:0; }
.pk-check-title{ font-weight:500; color:var(--pk-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.pk-check-chip{ margin-left:auto; flex:none; max-width:46%; color:var(--pk-faint); font-size:11px; font-family:"SFMono-Regular",Menlo,Consolas,monospace; background:var(--pk-soft); border:1px solid var(--pk-border); border-radius:6px; padding:1px 7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pk-empty{ color:var(--pk-muted); padding:12px 16px; font-size:13px; margin:0; border-top:1px solid var(--pk-border); }

/* toggle grid + helpers */
.pk-toggles{ display:grid; grid-template-columns:repeat(auto-fit,minmax(330px,1fr)); gap:2px 32px; max-width:940px; margin:6px 0 4px; }
.pk-toggles .pk-toggle{ margin:0; padding:10px 0; }
.pk-pill-pro{ font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--pk-amber); background:rgba(192,127,26,.13); border-radius:5px; padding:1px 6px; margin-left:4px; }
.pk-toggle-disabled{ opacity:.55; cursor:not-allowed; }
.pk-h4-note{ font-weight:400; font-size:12px; color:var(--pk-muted); margin-left:6px; }
.pk-arrow{ color:var(--pk-muted); font-weight:700; flex:none; }

/* ===== app shell: rail + main ===== */
.pk-app{ display:flex; gap:18px; align-items:flex-start; }
.pk-rail{ position:sticky; top:32px; flex:none; width:232px; background:var(--pk-card); border:1px solid var(--pk-border); border-radius:18px; box-shadow:var(--pk-shadow); padding:18px 14px; display:flex; flex-direction:column; gap:16px; }
.pk-rail-brand{ display:flex; align-items:center; gap:12px; padding:4px 6px 15px; border-bottom:1px solid var(--pk-border); }
.pk-rail-brand .pk-logo{ width:42px; height:42px; border-radius:12px; font-size:21px; box-shadow:0 6px 14px -5px rgba(0,150,135,.5); }
.pk-rail-id .pk-brand{ font-size:17px; font-weight:800; letter-spacing:-.4px; line-height:1; }
.pk-rail-sub{ font-size:10.5px; color:var(--pk-muted); margin-top:3px; letter-spacing:.01em; }
.pk-railnav{ display:flex; flex-direction:column; gap:3px; }
.pk-railitem{ display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:11px; font-size:13.5px; font-weight:600; color:var(--pk-muted); text-decoration:none; transition:background .14s,color .14s; }
.pk-railitem:hover{ background:var(--pk-soft); color:var(--pk-ink); }
.pk-railitem-on{ background:var(--pk-grad); color:#fff!important; box-shadow:0 7px 15px -6px rgba(0,150,135,.6); }
.pk-railitem .pk-ico{ width:18px; height:18px; }
.pk-rail-foot{ margin-top:auto; padding-top:15px; border-top:1px solid var(--pk-border); display:flex; flex-direction:column; align-items:flex-start; gap:9px; }
.pk-rail-credit{ font-size:12px; color:var(--pk-muted); }
.pk-rail-credit a{ color:var(--pk-accent); text-decoration:none; font-weight:600; }

.pk-main{ flex:1; min-width:0; }
.pk-pagehead{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:4px 2px 16px; }
.pk-pagehead h1{ display:flex; align-items:center; gap:10px; font-size:21px; font-weight:800; letter-spacing:-.5px; margin:0; padding:0; color:var(--pk-ink); }
.pk-pagehead h1 .pk-ico{ width:22px; height:22px; color:var(--pk-accent); }
.pk-pagehead p{ margin:5px 0 0; color:var(--pk-muted); font-size:13px; }
.pk-btn-ghost{ display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--pk-accent)!important; text-decoration:none; padding:8px 14px; border:1px solid var(--pk-border2); border-radius:10px; background:#fff; white-space:nowrap; }
.pk-btn-ghost:hover{ background:var(--pk-soft); border-color:var(--pk-accent); }
.pk-btn-ghost .pk-ico{ width:16px; height:16px; }

/* icon base */
.pk-ico{ width:20px; height:20px; display:inline-block; vertical-align:middle; flex:none; }

/* alert with icon */
.pk-alert{ display:flex; align-items:flex-start; gap:10px; }
.pk-alert .pk-ico{ color:#b88a16; margin-top:1px; width:18px; height:18px; }

/* setting cards (2-col) */
.pk-setgrid{ display:flex; flex-direction:column; gap:14px; }
.pk-setcard{ display:grid; grid-template-columns:268px 1fr; gap:30px; background:var(--pk-card); border:1px solid var(--pk-border); border-radius:15px; padding:22px 24px; box-shadow:var(--pk-shadow-sm); }
.pk-setcard-head{ display:flex; gap:12px; }
.pk-setcard-ico{ flex:none; width:38px; height:38px; border-radius:11px; background:var(--pk-tint); color:var(--pk-accent); display:flex; align-items:center; justify-content:center; }
.pk-setcard-head h3{ margin:0; padding:0; border:none; font-size:14.5px; font-weight:700; display:block; }
.pk-setcard-head h3::before{ display:none; }
.pk-setcard-head p{ margin:4px 0 0; color:var(--pk-muted); font-size:12.5px; line-height:1.55; }
.pk-setcard-body{ min-width:0; }
.pk-setcard-body .pk-field:last-child{ margin-bottom:0; }
.pk-setcard-body .pk-grid2{ max-width:none; }
.pk-setcard-body .pk-toggles{ max-width:none; margin:0; }
.pk-stat-icon .pk-ico{ width:18px; height:18px; }

/* ===== Site Doctor ===== */
.pk-subnav{ display:inline-flex; gap:4px; background:var(--pk-soft); border:1px solid var(--pk-border); border-radius:12px; padding:4px; margin-bottom:14px; flex-wrap:wrap; }
.pk-subnav-item{ font-size:13px; font-weight:600; color:var(--pk-muted); text-decoration:none; padding:7px 14px; border-radius:9px; }
.pk-subnav-item:hover{ color:var(--pk-ink); }
.pk-subnav-on{ background:#fff; color:var(--pk-accent-d); box-shadow:0 1px 3px rgba(16,24,40,.12); }
.pk-scanmeta{ display:flex; align-items:center; gap:7px; font-size:12.5px; color:var(--pk-muted); margin:0 0 16px; }
.pk-scanmeta .pk-ico{ width:15px; height:15px; }
.pk-scanmeta a{ color:var(--pk-accent); font-weight:600; text-decoration:none; }
.pk-swatch{ display:inline-block; width:20px; height:20px; border-radius:6px; border:1px solid rgba(0,0,0,.15); vertical-align:middle; margin-right:9px; }
.pk-log{ background:#0e1726; color:#cbd5e1; border-radius:11px; padding:14px 16px; font-size:12px; line-height:1.55; max-height:260px; overflow:auto; margin:16px 0 0; font-family:"SFMono-Regular",Menlo,Consolas,monospace; }
.pk-htree{ border:1px solid var(--pk-border); border-radius:11px; margin:8px 0; background:#fff; }
.pk-htree>summary{ cursor:pointer; padding:11px 15px; font-size:13.5px; list-style:none; }
.pk-htree>summary::-webkit-details-marker{ display:none; }
.pk-htree>summary::before{ content:'\25B8'; color:var(--pk-muted); margin-right:8px; }
.pk-htree[open]>summary::before{ content:'\25BE'; }
.pk-htree-edit{ font-size:12px; color:var(--pk-accent)!important; margin-left:8px; }
.pk-htree-body{ padding:4px 18px 14px 34px; border-top:1px solid var(--pk-border); }
.pk-htree-h1{ font-size:14px; font-weight:600; margin:8px 0; color:var(--pk-ink); }
.pk-htree-warn{ color:var(--pk-amber); }
.pk-htree-group{ margin:10px 0; }
.pk-htree-lvl{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--pk-faint); }
.pk-htree-group ol{ margin:4px 0 0 20px; }
.pk-htree-group li{ font-size:13px; color:#334; padding:1px 0; }
#pk-linktable input[readonly]{ background:#f6f8f9; color:#555; border-color:#e3e8ea; cursor:default; }
#pk-linktable input.pk-editing{ background:#fff; color:var(--pk-ink); border-color:var(--pk-accent); box-shadow:0 0 0 3px rgba(0,150,135,.12); }
.pk-link-edit .dashicons{ color:var(--pk-accent); font-size:18px; width:18px; height:18px; vertical-align:middle; }
.pk-muted{ color:var(--pk-faint); }
.pk-reclaim{ gap:8px; }
.pk-reclaim-sel{ min-width:180px; }
.pk-badge-warn{ color:var(--pk-amber); background:rgba(192,127,26,.13); }
.pk-badge-err{ color:var(--pk-red); background:rgba(220,75,83,.13); }
.pk-callout-ok{ background:rgba(15,157,107,.08); border-color:rgba(15,157,107,.25); color:#24358a; display:flex; align-items:center; gap:9px; }
.pk-callout-ok .pk-ico{ width:18px; height:18px; color:var(--pk-green); }
.pk-radius-demo{ display:inline-block; width:34px; height:22px; background:var(--pk-tint); border:2px solid var(--pk-accent); }
.pk-repl-to{ font-family:"SFMono-Regular",Menlo,Consolas,monospace; }
/* Site Doctor: keep wide tables inside a scroll pane instead of blowing out the page */
.rk-migrate-wrap .pk-doctor-scroll{ max-width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; border-radius:12px; }
.rk-migrate-wrap .pk-doctor-scroll > table.widefat{ min-width:760px; }
.rk-main{ min-width:0; max-width:100%; }
/* Site Doctor colors table — keep long rgba/hex values from breaking rows */
.pk-color-cell{ max-width:340px; }
.pk-color-cell .pk-swatch{ flex:0 0 auto; }
.pk-color-code{ display:inline-block; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; font-size:12.5px; background:#f3f4f6; padding:2px 8px; border-radius:6px; }
.pk-repl{ gap:8px; flex-wrap:nowrap; }
.pk-repl-to{ flex:1 1 auto; min-width:120px; max-width:180px; }
table.widefat td{ vertical-align:middle; }
#pk-linktable td{ word-break:break-all; }
#pk-linktable .pk-linkurl{ max-width:360px; }

/* responsive */
@media (max-width:1100px){
  .pk-app{ flex-direction:column; }
  .pk-rail{ position:static; width:100%; flex-direction:column; }
  .pk-railnav{ flex-direction:row; flex-wrap:wrap; }
  .pk-railitem{ padding:8px 12px; }
  .pk-setcard{ grid-template-columns:1fr; gap:16px; }
}
@media (max-width:782px){
  .rk-migrate-wrap{ margin:12px 8px 50px; }
  .pk-grid2,.pk-def{ grid-template-columns:1fr; }
  .pk-panel{ padding:22px 18px; }
}

.rk-migrate-wrap .pk-tier{ display:inline-block; }
</style>
		<?php
	}

	/* =================== JS =================== */
	private function print_js() {
		$nonce = wp_create_nonce( 'rk_migrate_ajax' );
		$rest = rest_url( 'rk-migrate/v1/' );
		$rnonce = wp_create_nonce( 'wp_rest' );
		$ajax  = admin_url( 'admin-ajax.php' );
		?>
<script>
(function($){
	var EP = { nonce: '<?php echo esc_js( $nonce ); ?>', ajax: '<?php echo esc_js( $ajax ); ?>', rest: '<?php echo esc_url_raw( $rest ); ?>', rnonce: '<?php echo esc_js( $rnonce ); ?>' };

	// ----- find & replace rows -----
	$(document).on('change', '#rk-migrate-conflict-default', function(){
		var v=$(this).val(); $('.pk-conflict').val(v);
	});
	$(document).on('click', '#rk-migrate-add-replace', function(){
		$('#rk-migrate-replace tbody').append(<?php echo wp_json_encode( $this->replace_row() ); ?>);
	});
	$(document).on('click', '.ep-remove', function(){ $(this).closest('tr').remove(); });

	function collectReplace(){
		var rules = [];
		$('#rk-migrate-replace tbody tr').each(function(){
			var f = $(this).find('.ep-find').val();
			if(!f) return;
			rules.push({ find:f, replace:$(this).find('.ep-replace').val()||'', regex: $(this).find('.ep-regex').is(':checked')?1:0 });
		});
		return rules;
	}

	// ----- chunked import -----
	$('#rk-migrate-import-form').on('submit', function(e){
		e.preventDefault();
		var form = this;
		var base = {
			set_front: form.set_front && form.set_front.checked ? 1:0,
			assign_parts: form.assign_parts && form.assign_parts.checked ? 1:0,
			build_menus: form.build_menus && form.build_menus.checked ? 1:0,
			media_relink: form.media_relink && form.media_relink.checked ? 1:0,
			snapshot: form.snapshot && form.snapshot.checked ? 1:0,
			dry_run: form.dry_run && form.dry_run.checked ? 1:0,
			from_url: form.from_url ? form.from_url.value : '',
			to_url: form.to_url ? form.to_url.value : ''
		};
		var rules = collectReplace();
		$('#rk-migrate-run-btn').prop('disabled', true).text('Running…');
		$('#rk-migrate-progress').show();
		$('#rk-migrate-log').text(''); $('#rk-migrate-bar').css('width','0%');
		$('#rk-migrate-status').text('Starting…');

		var post = $.extend({ action:'rk_migrate_import_start', nonce:EP.nonce }, base);
		post.replace = rules;
		post.conflict_default = $('#rk-migrate-conflict-default').val() || 'overwrite';
		post.conflict = {};
		$('.pk-conflict').each(function(){ post.conflict[$(this).data('slug')] = $(this).val(); });
		$.post(EP.ajax, post, function(res){
			if(!res.success){ fail(res.data && res.data.message); return; }
			var token = res.data.token, total = res.data.total, i = 0;
			if(res.data.snapshot){ log('Snapshot: '+res.data.snapshot); }
			(function step(){
				$.post(EP.ajax, { action:'rk_migrate_import_step', nonce:EP.nonce, token:token, index:i }, function(r){
					if(!r.success){ fail(r.data && r.data.message); return; }
					(r.data.lines||[]).forEach(log);
					var pct = Math.round(((i+1)/total)*100);
					$('#rk-migrate-bar').css('width', pct+'%');
					$('#rk-migrate-status').text('Step '+(i+1)+'/'+total+' — '+(r.data.label||'')+'  ('+pct+'%)');
					if(r.data.done){
						var c = r.data.counts||{};
						var extra=''; if(c.skipped){extra+=', '+c.skipped+' skipped';} if(c.merged){extra+=', '+c.merged+' merged';}
						$('#rk-migrate-status').text('Finished — '+ (c.created||0)+' created, '+(c.updated||0)+' updated'+extra+', '+(c.errors||0)+' errors.');
						$('#rk-migrate-run-btn').prop('disabled',false).text('Run Import');
					} else { i++; step(); }
				}).fail(function(){ fail('Network error.'); });
			})();
		}).fail(function(){ fail('Network error.'); });

		function log(line){ var el=document.getElementById('rk-migrate-log'); el.textContent += line+'\n'; el.scrollTop = el.scrollHeight; }
		function fail(msg){ $('#rk-migrate-status').text('Error: '+(msg||'unknown')); $('#rk-migrate-run-btn').prop('disabled',false).text('Run Import'); }
	});

	// ----- export -----
	$('#rk-migrate-export-form').on('submit', function(e){
		e.preventDefault();
		$('#rk-migrate-export-btn').prop('disabled',true).text('Exporting…');
		var data = $(this).serializeArray();
		data.push({name:'action', value:'rk_migrate_export'});
		data.push({name:'nonce', value:EP.nonce});
		$.post(EP.ajax, data, function(res){
			$('#rk-migrate-export-btn').prop('disabled',false).text('Export Bundle');
			if(!res.success){ $('#rk-migrate-export-result').html('<div class="notice notice-error inline"><p>'+(res.data&&res.data.message||'Failed')+'</p></div>'); return; }
			$('#rk-migrate-export-result').html('<div class="notice notice-success inline"><p>Bundle ready: <a href="'+res.data.url+'" download><strong>'+res.data.file+'</strong></a></p></div>');
		}).fail(function(){ $('#rk-migrate-export-btn').prop('disabled',false).text('Export Bundle'); });
	});
	$(document).on('click', '.ep-checkall', function(e){
		e.preventDefault(); var n=$(this).data('name'); $('input[name="'+n+'[]"]').prop('checked', true);
	});
	$(document).on('click', '.ep-checknone', function(e){
		e.preventDefault(); var n=$(this).data('name'); $('input[name="'+n+'[]"]').prop('checked', false);
	});

	// ----- rollback (whole snapshot or a single page) -----
	$(document).on('click', '.ep-rollback', function(e){
		e.preventDefault();
		var btn=$(this), snap=btn.data('snap'), post=btn.data('post')||0, label=btn.text();
		var msg = post ? 'Restore this one page from the snapshot?' : 'Restore ALL pages from this snapshot?';
		if(!confirm(msg)) return;
		btn.prop('disabled',true).text('Restoring…');
		$('#rk-migrate-rollback-log').show();
		var data={ action:'rk_migrate_rollback', nonce:EP.nonce, snapshot:snap };
		if(post){ data.post_id = post; }
		$.post(EP.ajax, data, function(res){
			btn.prop('disabled',false).text(label);
			var lines = (res.success && res.data.lines) || ['Failed'];
			var el=document.getElementById('rk-migrate-rollback-log');
			el.textContent += lines.join('\n') + '\n';
			el.scrollTop = el.scrollHeight;
		}).fail(function(){ btn.prop('disabled',false).text(label); });
	});

	// ----- manifest builder add row -----
	$(document).on('click', '#rk-migrate-add-page', function(){
		var tpl = document.getElementById('rk-migrate-builder-template').innerHTML;
		var idx = $('#rk-migrate-builder tbody tr').length;
		$('#rk-migrate-builder tbody').append(tpl.replace(/__I__/g, idx));
	});

	// ----- token generator -----
	$(document).on('click', '#rk-migrate-gen-token', function(){
		var t=''; var c='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		for(var i=0;i<40;i++){ t+=c.charAt(Math.floor(Math.random()*c.length)); }
		$('input[name=remote_token]').val(t);
	});

	// ----- Site Doctor: reclaim color/font -----
	function dlog(line){ var el=document.getElementById('pk-doctor-log'); if(!el) return; el.style.display='block'; el.textContent += line+'\n'; el.scrollTop=el.scrollHeight; }
	$(document).on('click', '.pk-reclaim-btn', function(){
		var box=$(this).closest('.pk-reclaim'), kind=$(this).data('kind'), gid=box.find('.pk-reclaim-sel').val(), btn=$(this);
		var data={ action:(kind==='font'?'rk_migrate_reclaim_font':'rk_migrate_reclaim_color'), nonce:EP.nonce, global:gid };
		if(kind==='font'){ data.family=box.data('family'); } else { data.hex=box.data('hex'); }
		btn.prop('disabled',true).text('Binding…');
		$.post(EP.ajax, data, function(res){
			btn.prop('disabled',false).text('Bind');
			if(!res.success){ dlog('Error: '+(res.data&&res.data.message||'failed')); return; }
			(res.data.lines||[]).forEach(dlog);
			if(res.data.snapshot){ dlog('Snapshot: '+res.data.snapshot+' (rollback in History)'); }
			box.closest('tr').css('opacity',.5);
		}).fail(function(){ btn.prop('disabled',false).text('Bind'); dlog('Network error.'); });
	});

	// ----- Site Doctor: convert -----
	$(document).on('click', '.pk-convert-btn', function(){
		var row=$(this).closest('tr'), pid=row.data('pid'), dry=$(this).data('dry'), btn=$(this);
		if(!dry && !confirm('Convert this page to containers? A rollback snapshot will be taken first.')) return;
		btn.prop('disabled',true).text(dry?'Previewing…':'Converting…');
		var note=row.find('.pk-convert-note');
		if(!note.length){ note=$('<div class="pk-convert-note" style="margin-top:8px;font-size:12.5px;"></div>').appendTo(row.find('td:last')); }
		note.html('<span class="pk-muted">Working…</span>');
		$.post(EP.ajax, { action:'rk_migrate_convert', nonce:EP.nonce, post_id:pid, dry:dry }, function(res){
			btn.prop('disabled',false).text(dry?'Preview':'Convert');
			if(!res.success){ note.html('<span style="color:#b32d2e;">Error: '+(res.data&&res.data.message||'failed')+'</span>'); return; }
			var msg=(res.data.lines||[]).join(' ');
			note.html((dry?'👁 <strong>Preview:</strong> ':'✓ <strong>Converted.</strong> ')+$('<span>').text(msg).html()+(dry?' <em>(nothing changed yet — click Convert to apply)</em>':''));
			(res.data.lines||[]).forEach(dlog);
		}).fail(function(){ btn.prop('disabled',false).text(dry?'Preview':'Convert'); note.html('<span style="color:#b32d2e;">Network error.</span>'); });
	});

	// ----- Site Doctor: link fixer (pencil edit, per-row save, save all) -----
	$(document).on('click', '.pk-link-edit', function(){
		var row=$(this).closest('tr');
		row.find('.pk-link-url, .pk-link-text').prop('readonly', false).addClass('pk-editing');
		row.find('.pk-link-save').show();
		row.find('.pk-link-url').focus();
	});
	$(document).on('input', '.pk-link-url, .pk-link-text', function(){ $(this).closest('tr').attr('data-dirty','1'); });
	$(document).on('click', '.pk-link-save', function(){
		var row=$(this).closest('tr'), btn=$(this);
		btn.prop('disabled',true).text('Saving…');
		$.post(EP.ajax, { action:'rk_migrate_edit_link', nonce:EP.nonce, pid:row.data('pid'), eid:row.data('eid'), url:row.find('.pk-link-url').val(), text:row.find('.pk-link-text').val() }, function(res){
			btn.prop('disabled',false);
			if(res.success){ btn.text('Saved ✓'); row.removeAttr('data-dirty').find('.pk-link-url,.pk-link-text').prop('readonly',true).removeClass('pk-editing'); setTimeout(function(){ btn.text('Save').hide(); }, 1400); }
			else { btn.text('Save'); alert((res.data&&res.data.message)||'Save failed.'); }
		}).fail(function(){ btn.prop('disabled',false).text('Save'); alert('Network error.'); });
	});
	$(document).on('click', '#pk-linksaveall', function(){
		var items=[], status=$('#pk-linksaveall-status');
		$('#pk-linktable tr[data-dirty="1"]').each(function(){ var r=$(this); if(!r.data('eid')) return; items.push({ pid:r.data('pid'), eid:String(r.data('eid')), url:r.find('.pk-link-url').val(), text:r.find('.pk-link-text').val() }); });
		if(!items.length){ status.text('No changes to save.'); return; }
		var btn=$(this).prop('disabled',true).text('Saving…');
		fetch(EP.rest+'update-links', { method:'POST', headers:{ 'X-WP-Nonce':EP.rnonce, 'Content-Type':'application/json' }, body:JSON.stringify({ items:items }) })
			.then(function(r){ return r.json(); })
			.then(function(res){
				btn.prop('disabled',false).text('Save All Changes');
				status.text('Saved '+(res.saved||0)+' change(s)'+(res.errors&&res.errors.length?', '+res.errors.length+' failed':'')+'.');
				$('#pk-linktable tr[data-dirty="1"]').removeAttr('data-dirty').find('.pk-link-url,.pk-link-text').prop('readonly',true).removeClass('pk-editing');
				$('#pk-linktable .pk-link-save').hide();
			})
			.catch(function(){ btn.prop('disabled',false).text('Save All Changes'); status.text('Network error.'); });
	});

	// ----- Site Doctor: replace color value -----
	$(document).on('click', '.pk-repl-btn', function(){
		var box=$(this).closest('.pk-repl'), from=box.data('hex'), to=box.find('.pk-repl-to').val(), btn=$(this);
		if(!to){ return; }
		btn.prop('disabled',true).text('Replacing...');
		$.post(EP.ajax, { action:'rk_migrate_replace_color', nonce:EP.nonce, from:from, to:to }, function(res){
			btn.prop('disabled',false).text('Replace');
			if(!res.success){ dlog('Error: '+(res.data&&res.data.message||'failed')); return; }
			(res.data.lines||[]).forEach(dlog);
			if(res.data.snapshot){ dlog('Snapshot: '+res.data.snapshot+' (rollback in History)'); }
		}).fail(function(){ btn.prop('disabled',false).text('Replace'); dlog('Network error.'); });
	});

	// ----- Site Doctor: set corner radius -----
	$(document).on('click', '#pk-radius-btn', function(){
		var px=$('#pk-radius-px').val(), btn=$(this);
		if(!confirm('Set every existing corner radius to '+px+'px? A snapshot will be taken first.')) return;
		btn.prop('disabled',true).text('Applying...');
		$.post(EP.ajax, { action:'rk_migrate_set_radius', nonce:EP.nonce, px:px }, function(res){
			btn.prop('disabled',false).text('Apply to all');
			if(!res.success){ dlog('Error: '+(res.data&&res.data.message||'failed')); return; }
			(res.data.lines||[]).forEach(dlog);
			if(res.data.snapshot){ dlog('Snapshot: '+res.data.snapshot+' (rollback in History)'); }
		}).fail(function(){ btn.prop('disabled',false).text('Apply to all'); dlog('Network error.'); });
	});

	// ----- Site Doctor: link checker -----
	$(document).on('click', '#pk-linkcheck-btn', function(){
		var btn=$(this); btn.prop('disabled',true);
		var cells={}; $('.pk-linkstatus').each(function(){ var u=$(this).data('url'); if(u){ (cells[u]=cells[u]||[]).push(this); $(this).html('<span class="pk-muted">…</span>'); } });
		(function step(offset){
			$.post(EP.ajax, { action:'rk_migrate_linkcheck', nonce:EP.nonce, offset:offset }, function(res){
				if(!res.success){ btn.prop('disabled',false); return; }
				(res.data.results||[]).forEach(function(r){
					(cells[r.url]||[]).forEach(function(td){
						td.innerHTML = r.code===0 ? '<span class="pk-badge pk-badge-err">error</span>' : ('<span class="pk-badge '+(r.ok?'pk-badge-free':'pk-badge-err')+'">'+r.code+'</span>');
					});
				});
				$('#pk-linkcheck-status').text('Checked '+Math.min(res.data.next,res.data.total)+' / '+res.data.total);
				if(res.data.done){ btn.prop('disabled',false); } else { step(res.data.next); }
			}).fail(function(){ btn.prop('disabled',false); });
		})(0);
	});
})(jQuery);
</script>
		<?php
	}

	/* ---------------- Media: localize remote images ---------------- */
	private function tab_media() {
		if ( ! current_user_can( 'manage_options' ) ) { echo '<p>You do not have permission.</p>'; return; }
		$found = RK_Migrate_Localize::scan();
		$total = 0; foreach ( $found as $n ) { $total += 1; }
		$hosts = array();
		foreach ( array_keys( $found ) as $u ) { $h = wp_parse_url( $u, PHP_URL_HOST ); if ( $h ) { $hosts[ $h ] = 1; } }

		$this->section( 'Localize remote images', 'Images still served from another domain (common after importing a template kit) load slowly and can&rsquo;t be cached by your CDN. This copies them into your Media Library and rewrites every reference.' );

		$rep = get_transient( 'rk_migrate_localize_report' );
		if ( $rep ) {
			delete_transient( 'rk_migrate_localize_report' );
			echo '<div class="notice notice-success" style="margin:0 0 14px;"><p>Downloaded <strong>' . (int) $rep['downloaded'] . '</strong> image(s), updated <strong>' . (int) $rep['rewritten'] . '</strong> post(s)'
				. ( $rep['failed'] ? ', <strong>' . (int) $rep['failed'] . '</strong> failed' : '' )
				. '. <strong>' . (int) $rep['remaining'] . '</strong> remote image(s) still remaining.</p></div>';
		}

		if ( ! $total ) {
			echo '<p style="color:#12b76a;font-weight:600;">No remote images found — everything is served locally. </p>';
			return;
		}

		echo '<div class="pk-card" style="padding:16px 18px;">';
		echo '<p style="margin:0 0 6px;"><strong>' . (int) $total . '</strong> remote image URL(s) found across your content.</p>';
		echo '<p class="pk-muted" style="margin:0 0 12px;">Hosts: <code>' . esc_html( implode( ', ', array_keys( $hosts ) ) ) . '</code></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_migrate_localize" />';
		wp_nonce_field( 'rk_migrate_localize' );
		echo '<button class="button button-primary">Localize up to 15 now</button> ';
		echo '<span class="pk-muted" style="font-size:12px;">Run repeatedly until it reaches zero (batched to avoid timeouts).</span>';
		echo '</form></div>';
	}

	public function handle_localize() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_migrate_localize' );
		@set_time_limit( 120 );
		$report = RK_Migrate_Localize::run( 15 );
		set_transient( 'rk_migrate_localize_report', $report, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=media' ) ); exit;
	}

}
