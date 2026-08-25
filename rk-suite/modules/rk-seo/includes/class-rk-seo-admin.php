<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin — deliberately slim. Auto features need no settings; this screen only
 * exposes what genuinely requires human input: manual redirects, the 404 log,
 * and header/footer code. One page, tabbed.
 */
class Admin {

	const SLUG = 'rk-seo';

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_rk_seo_add_redirect', array( $this, 'do_add_redirect' ) );
		add_action( 'admin_post_rk_seo_del_redirect', array( $this, 'do_del_redirect' ) );
		add_action( 'admin_post_rk_seo_del_404', array( $this, 'do_del_404' ) );
		add_action( 'admin_post_rk_seo_save_code', array( $this, 'do_save_code' ) );
		add_action( 'admin_post_rk_seo_import', array( $this, 'do_import' ) );
		add_action( 'admin_post_rk_seo_save_appearance', array( $this, 'do_save_appearance' ) );
		add_action( 'admin_post_rk_seo_save_crawl', array( $this, 'do_save_crawl' ) );
		add_action( 'admin_post_rk_seo_rebuild_ix', array( $this, 'do_rebuild_ix' ) );
	}

	public function menu() {
		add_menu_page( 'RK SEO', 'RK SEO', 'manage_options', self::SLUG, array( $this, 'screen' ), 'dashicons-search', 61 );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) { return; }
		$css = RK_SEO_DIR . 'assets/admin.css';
		wp_enqueue_style( 'rk-seo-admin', RK_SEO_URL . 'assets/admin.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_SEO_VERSION );
	}

	private function tab() { return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; }

	public function screen() {
		$tab  = $this->tab();
		$tabs = array( 'overview' => 'Overview', 'appearance' => 'Search Appearance', 'crawl' => 'Crawl', 'integrations' => 'Integrations', 'redirects' => 'Redirects', 'notfound' => '404 Log', 'code' => 'Header &amp; Footer Code', 'import' => 'Import' );
		echo '<div class="wrap rk-seo-wrap rk-has-rail">';
		if ( class_exists( '\RK_Suite_Admin' ) ) {
			\RK_Suite_Admin::render_sidebar();
		} else {
			echo '<aside class="rk-rail">';
			echo '<div class="rk-rail-brand"><div class="pk-logo">RK</div><div><div class="pk-brand">RK SEO <span class="pk-ver">v' . esc_html( RK_SEO_VERSION ) . '</span></div><div class="pk-tag">Auto-pilot SEO</div></div></div>';
			echo '<nav class="rk-subnav rk-rail-nav">';
			foreach ( $tabs as $k => $label ) {
				$active = ( $k === $tab ) ? ' is-active' : '';
				echo '<a class="rk-subnav-item' . $active . '" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $k ) ) . '">' . $label . '</a>';
			}
			echo '</nav></aside>';
		}
		echo '<main class="rk-main">';
		$this->notice();
		// Horizontal tab bar — always visible (the shared rail lists modules, not
		// these per-screen tabs), so Search Appearance / Crawl are reachable.
		echo '<nav class="rk-seo-tabs">';
		foreach ( $tabs as $k => $label ) {
			$active = ( $k === $tab ) ? ' is-active' : '';
			echo '<a class="rk-seo-tab' . $active . '" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $k ) ) . '">' . $label . '</a>';
		}
		echo '</nav>';
		echo '<style>.rk-seo-tabs{display:flex;flex-wrap:wrap;gap:2px;border-bottom:1px solid var(--line,#e8eaf1);margin:0 0 18px;}.rk-seo-tab{padding:9px 14px;font-size:13px;font-weight:600;color:var(--muted,#6b7183);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;}.rk-seo-tab:hover{color:var(--ink,#151823);}.rk-seo-tab.is-active{color:var(--rk,#3a55d9);border-bottom-color:var(--rk,#3a55d9);}</style>';
		if ( 'appearance' === $tab )     { $this->tab_appearance(); }
		elseif ( 'crawl' === $tab )      { $this->tab_crawl(); }
		elseif ( 'integrations' === $tab ) { $this->tab_integrations(); }
		elseif ( 'redirects' === $tab )  { $this->tab_redirects(); }
		elseif ( 'notfound' === $tab )   { $this->tab_404(); }
		elseif ( 'code' === $tab )       { $this->tab_code(); }
		elseif ( 'import' === $tab )     { $this->tab_import(); }
		else                             { $this->tab_overview(); }
		echo '</main></div>';
	}

	private function notice() {
		if ( empty( $_GET['rk_msg'] ) ) { return; }
		$m = sanitize_key( $_GET['rk_msg'] );
		if ( 'imported' === $m ) {
			$r = get_transient( 'rk_seo_import_report' ); delete_transient( 'rk_seo_import_report' );
			$txt = $r ? sprintf( 'Import complete — %d field(s) across %d post(s) (scanned %d).', (int) $r['fields'], (int) $r['posts'], (int) $r['scanned'] ) : 'Import complete.';
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $txt ) . '</p></div>'; return;
		}
		$map = array( 'added' => 'Redirect added.', 'deleted' => 'Deleted.', 'saved' => 'Saved.' );
		if ( isset( $map[ $m ] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $m ] ) . '</p></div>'; }
	}

	private function tab_overview() {
		$foreign = Helpers::foreign_seo_active();
		echo '<div class="rk-seo-cards">';
		$this->card( 'Meta tags', 'Titles, descriptions, robots, Open Graph &amp; Twitter — generated per page.', ! $foreign );
		$this->card( 'JSON-LD schema', 'Organization, WebSite, WebPage, Article &amp; Breadcrumbs, all linked.', ! $foreign );
		$this->card( 'XML sitemap', '<a href="' . esc_url( home_url( '/sitemap.xml' ) ) . '" target="_blank" rel="noopener">/sitemap.xml</a> — posts, images &amp; news, rebuilt automatically.', true );
		$this->card( 'Breadcrumbs', 'Use <code>[rk_breadcrumbs]</code> or <code>&lt;?php rk_breadcrumbs(); ?&gt;</code> in your theme.', true );
		$this->card( 'Smart redirects', 'Slug changes become 301s automatically; canonical host enforced.', true );
		$this->card( '404 monitor', 'Broken links are logged for review under the 404 Log tab.', true );
		echo '</div>';
		if ( $foreign ) {
			echo '<div class="notice notice-warning"><p><strong>Another SEO plugin is active.</strong> RK SEO has paused its meta &amp; schema output to avoid duplicate tags. Sitemap, redirects and 404 monitoring stay on. Deactivate the other SEO plugin to let RK SEO take over fully.</p></div>';
		}
	}

	private function card( $title, $desc, $on ) {
		echo '<div class="rk-seo-card"><div class="rk-seo-card-h"><strong>' . $title . '</strong><span class="rk-seo-pill ' . ( $on ? 'on' : 'off' ) . '">' . ( $on ? 'Active' : 'Paused' ) . '</span></div><p>' . $desc . '</p></div>';
	}

	private function tab_redirects() {
		global $wpdb; $t = Redirects::tables();
		$rows = $wpdb->get_results( "SELECT * FROM {$t['redirects']} ORDER BY id DESC LIMIT 500" );
		echo '<h2>Manual redirect</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-seo-inline">';
		echo '<input type="hidden" name="action" value="rk_seo_add_redirect" />';
		wp_nonce_field( 'rk_seo_add_redirect' );
		echo '<input type="text" name="source" placeholder="/old-path" required /> → <input type="url" name="target" placeholder="https://example.com/new" required /> <button class="button button-primary">Add 301</button>';
		echo '</form>';
		echo '<h2>Active redirects</h2>';
		if ( ! $rows ) { echo '<p>No redirects yet. Slug changes will appear here automatically.</p>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=rk_seo_del_redirect&id=' . $r->id ), 'rk_seo_del_redirect_' . $r->id );
			echo '<tr><td><code>' . esc_html( $r->source ) . '</code></td><td>' . esc_html( $r->target ) . '</td><td>' . (int) $r->type . '</td><td>' . (int) $r->hits . '</td><td><a class="button-link-delete" href="' . esc_url( $del ) . '">Delete</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_404() {
		global $wpdb; $t = Redirects::tables();
		$rows = $wpdb->get_results( "SELECT * FROM {$t['notfound']} ORDER BY hits DESC, last_seen DESC LIMIT 500" );
		echo '<h2>404 errors</h2>';
		if ( ! $rows ) { echo '<p class="rk-muted"><span class="rk-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-4.5"/></svg></span> No 404s logged yet — all links resolve.</p>'; return; }
		echo '<p class="rk-muted">Turn a frequent 404 into a redirect from the Redirects tab.</p>';
		echo '<table class="widefat striped"><thead><tr><th>URL</th><th>Hits</th><th>Last seen</th><th>Referer</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=rk_seo_del_404&id=' . $r->id ), 'rk_seo_del_404_' . $r->id );
			echo '<tr><td><code>' . esc_html( $r->url ) . '</code></td><td>' . (int) $r->hits . '</td><td>' . esc_html( $r->last_seen ) . '</td><td>' . esc_html( $r->referer ) . '</td><td><a class="button-link-delete" href="' . esc_url( $del ) . '">Clear</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_code() {
		$o = Tools::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_seo_save_code" />';
		wp_nonce_field( 'rk_seo_save_code' );
		echo '<h2>Header code <span class="rk-muted">(before &lt;/head&gt;)</span></h2>';
		echo '<textarea name="head_code" rows="6" class="large-text code" placeholder="Google Analytics, verification tags…">' . esc_textarea( $o['head_code'] ) . '</textarea>';
		echo '<h2>Footer code <span class="rk-muted">(before &lt;/body&gt;)</span></h2>';
		echo '<textarea name="footer_code" rows="6" class="large-text code" placeholder="Chat widgets, pixels…">' . esc_textarea( $o['footer_code'] ) . '</textarea>';
		echo '<p><label><input type="checkbox" name="rss_protect" value="1" ' . checked( ! empty( $o['rss_protect'] ), true, false ) . ' /> Add a back-link to RSS items (anti-scraping)</label></p>';
		echo '<p><button class="button button-primary">Save</button></p>';
		echo '</form>';
	}

	private function tab_import() {
		$avail = Importer::available();
		echo '<h2>Import from another SEO plugin</h2>';
		echo '<p class="rk-muted">Copies per-post SEO titles, descriptions, noindex flags, canonicals and social images into RK SEO. Non-destructive — it never overwrites values you already set and leaves the source data untouched, so you can switch back if needed.</p>';
		if ( ! $avail ) {
			echo '<div class="notice notice-info inline"><p>No Yoast SEO or Rank Math data was found on this site.</p></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-seo-inline">';
		echo '<input type="hidden" name="action" value="rk_seo_import" />';
		wp_nonce_field( 'rk_seo_import' );
		echo '<select name="source">';
		foreach ( $avail as $k => $label ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>'; }
		echo '</select> <button class="button button-primary" onclick="return confirm(\'Import SEO data now?\')">Run import</button>';
		echo '</form>';
		echo '<p class="rk-muted">Tip: after importing, deactivate the old SEO plugin so RK SEO can take over output.</p>';
	}

	public function do_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_import' );

		// Accept source on the first hit; subsequent continue-hits pass both.
		$src_in = isset( $_REQUEST['source'] ) ? sanitize_key( wp_unslash( $_REQUEST['source'] ) ) : 'yoast';
		$source = in_array( $src_in, array( 'yoast', 'rankmath' ), true ) ? $src_in : 'yoast';
		$offset = isset( $_REQUEST['offset'] ) ? max( 0, (int) $_REQUEST['offset'] ) : 0;

		// On the first hit, snapshot the total and reset the running report.
		if ( 0 === $offset ) {
			$total = Importer::total( $source );
			set_transient( 'rk_seo_import_job', array( 'source' => $source, 'total' => $total, 'posts' => 0, 'fields' => 0 ), 15 * MINUTE_IN_SECONDS );
		}
		$job = get_transient( 'rk_seo_import_job' );
		if ( ! is_array( $job ) ) { $job = array( 'source' => $source, 'total' => Importer::total( $source ), 'posts' => 0, 'fields' => 0 ); }

		$slice = Importer::run( $source, $offset, Importer::BATCH );
		$job['posts']  += (int) $slice['posts'];
		$job['fields'] += (int) $slice['fields'];
		$done_now       = $offset + (int) $slice['processed'];
		set_transient( 'rk_seo_import_job', $job, 15 * MINUTE_IN_SECONDS );

		// More to do (a full batch came back and we're still under the total)?
		if ( (int) $slice['processed'] >= Importer::BATCH && $done_now < (int) $job['total'] ) {
			$next = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'rk_seo_import', 'source' => $source, 'offset' => $done_now ),
					admin_url( 'admin-post.php' )
				),
				'rk_seo_import'
			);
			$this->render_import_progress( $done_now, (int) $job['total'], $next );
			exit;
		}

		// Finished.
		set_transient( 'rk_seo_import_report', array( 'posts' => $job['posts'], 'fields' => $job['fields'], 'scanned' => (int) $job['total'] ), 60 );
		delete_transient( 'rk_seo_import_job' );
		$this->back( 'import', 'imported' );
	}

	/** Minimal "importing…" interstitial that auto-continues to the next batch. */
	private function render_import_progress( $done, $total, $next_url ) {
		$pct = $total > 0 ? min( 100, (int) round( $done / $total * 100 ) ) : 100;
		nocache_headers();
		echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . esc_url( $next_url ) . '">';
		echo '<title>Importing…</title>';
		echo '<div style="font:15px/1.6 -apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:12vh auto;text-align:center;color:#1f2430;">';
		echo '<h2 style="margin:0 0 8px;">Importing SEO data…</h2>';
		echo '<p style="color:#6b7183;margin:0 0 16px;">Processed ' . esc_html( number_format_i18n( $done ) ) . ' of ' . esc_html( number_format_i18n( $total ) ) . ' posts. This page continues automatically — please keep it open.</p>';
		echo '<div style="height:10px;background:#eceef5;border-radius:6px;overflow:hidden;"><div style="height:100%;width:' . esc_attr( $pct ) . '%;background:#3a55d9;"></div></div>';
		echo '<p style="margin-top:14px;"><a href="' . esc_url( $next_url ) . '">Continue now</a> if it does not advance.</p>';
		echo '</div>';
	}

	/* ---- handlers ---- */

	public function do_add_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_add_redirect' );
		$src = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$dst = isset( $_POST['target'] ) ? esc_url_raw( wp_unslash( $_POST['target'] ) ) : '';
		if ( $src && $dst ) { ( new Redirects() )->add_rule( $src, $dst, 301 ); }
		$this->back( 'redirects', 'added' );
	}
	public function do_del_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_seo_del_redirect_' . $id );
		( new Redirects() )->delete_rule( $id );
		$this->back( 'redirects', 'deleted' );
	}
	public function do_del_404() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_seo_del_404_' . $id );
		global $wpdb; $t = Redirects::tables();
		$wpdb->delete( $t['notfound'], array( 'id' => $id ) );
		$this->back( 'notfound', 'deleted' );
	}
	public function do_save_code() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_save_code' );
		update_option( Tools::OPTION, array(
			'head_code'   => isset( $_POST['head_code'] ) ? wp_unslash( $_POST['head_code'] ) : '',
			'footer_code' => isset( $_POST['footer_code'] ) ? wp_unslash( $_POST['footer_code'] ) : '',
			'rss_protect' => isset( $_POST['rss_protect'] ) ? 1 : 0,
		) );
		$this->back( 'code', 'saved' );
	}
	/* ---------------- Search Appearance ---------------- */

	private function tab_appearance() {
		$sep = \RK\SEO\Search_Appearance::sep();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_seo_save_appearance" />';
		wp_nonce_field( 'rk_seo_save_appearance' );

		echo '<h2>Title separator</h2><p class="rk-muted">Shown wherever a template uses <code>%%sep%%</code>.</p><p>';
		foreach ( \RK\SEO\Search_Appearance::separators() as $val => $glyph ) {
			echo '<label style="display:inline-block;margin:0 14px 8px 0;font-size:18px;"><input type="radio" name="sep" value="' . esc_attr( $val ) . '" ' . checked( $sep, $val, false ) . ' /> ' . esc_html( $glyph ) . '</label>';
		}
		echo '</p>';

		echo '<p class="rk-muted">Available variables: ';
		foreach ( \RK\SEO\Variables::help_list() as $label => $tok ) { echo '<code title="' . esc_attr( $label ) . '">' . esc_html( $tok ) . '</code> '; }
		echo '</p>';

		echo '<h2>Content types</h2>';
		foreach ( \RK\SEO\Search_Appearance::post_types() as $pt => $obj ) {
			$c = \RK\SEO\Search_Appearance::pt( $pt );
			echo '<div class="rk-sa-block"><h3>' . esc_html( $obj->labels->name ) . ' <code>' . esc_html( $pt ) . '</code></h3>';
			$this->tpl_row( "pt[$pt][title]", 'SEO title', $c['title'] );
			$this->tpl_row( "pt[$pt][desc]", 'Meta description', $c['desc'] );
			echo '<p><label><input type="checkbox" name="pt[' . esc_attr( $pt ) . '][noindex]" value="1" ' . checked( ! empty( $c['noindex'] ), true, false ) . ' /> Set these to <strong>noindex</strong> (hide from search)</label></p>';
			echo '</div>';
		}

		echo '<h2>Taxonomies</h2>';
		foreach ( \RK\SEO\Search_Appearance::taxonomies() as $tx => $obj ) {
			$c = \RK\SEO\Search_Appearance::tax( $tx );
			echo '<div class="rk-sa-block"><h3>' . esc_html( $obj->labels->name ) . ' <code>' . esc_html( $tx ) . '</code></h3>';
			$this->tpl_row( "tax[$tx][title]", 'SEO title', $c['title'] );
			$this->tpl_row( "tax[$tx][desc]", 'Meta description', $c['desc'] );
			echo '<p><label><input type="checkbox" name="tax[' . esc_attr( $tx ) . '][noindex]" value="1" ' . checked( ! empty( $c['noindex'] ), true, false ) . ' /> noindex</label></p>';
			echo '</div>';
		}

		echo '<h2>Archives &amp; special pages</h2>';
		$au = \RK\SEO\Search_Appearance::archive( 'author' );
		$dt = \RK\SEO\Search_Appearance::archive( 'date' );
		$se = \RK\SEO\Search_Appearance::archive( 'search' );
		$nf = \RK\SEO\Search_Appearance::archive( '404' );
		$hm = \RK\SEO\Search_Appearance::archive( 'home' );
		echo '<div class="rk-sa-block"><h3>Homepage</h3>';
		$this->tpl_row( 'archive[home][title]', 'SEO title', $hm['title'] );
		$this->tpl_row( 'archive[home][desc]', 'Meta description', isset( $hm['desc'] ) ? $hm['desc'] : '' );
		echo '</div>';
		echo '<div class="rk-sa-block"><h3>Author archives</h3>';
		echo '<p><label><input type="checkbox" name="archive[author][enabled]" value="1" ' . checked( ! empty( $au['enabled'] ), true, false ) . ' /> Enable author archives</label> &nbsp; <label><input type="checkbox" name="archive[author][noindex]" value="1" ' . checked( ! empty( $au['noindex'] ), true, false ) . ' /> noindex</label></p>';
		$this->tpl_row( 'archive[author][title]', 'SEO title', $au['title'] );
		echo '</div>';
		echo '<div class="rk-sa-block"><h3>Date archives</h3>';
		echo '<p><label><input type="checkbox" name="archive[date][enabled]" value="1" ' . checked( ! empty( $dt['enabled'] ), true, false ) . ' /> Enable date archives</label> &nbsp; <label><input type="checkbox" name="archive[date][noindex]" value="1" ' . checked( ! empty( $dt['noindex'] ), true, false ) . ' /> noindex</label></p>';
		$this->tpl_row( 'archive[date][title]', 'SEO title', $dt['title'] );
		echo '</div>';
		echo '<div class="rk-sa-block"><h3>Search results</h3>';
		$this->tpl_row( 'archive[search][title]', 'SEO title', $se['title'] );
		echo '</div>';
		echo '<div class="rk-sa-block"><h3>404 page</h3>';
		$this->tpl_row( 'archive[404][title]', 'SEO title', $nf['title'] );
		echo '</div>';

		echo '<p><button class="button button-primary">Save Search Appearance</button></p></form>';
		echo '<style>.rk-sa-block{border:1px solid var(--line,#e8eaf1);border-radius:10px;padding:10px 16px;margin:10px 0;background:#fff;}.rk-sa-block h3{margin:6px 0;font-size:14px;}.rk-sa-row{margin:8px 0;}.rk-sa-row label{display:block;font-weight:600;font-size:12.5px;margin-bottom:4px;}</style>';
	}

	private function tpl_row( $name, $label, $value ) {
		echo '<div class="rk-sa-row"><label>' . esc_html( $label ) . '</label>';
		echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="large-text code" /></div>';
	}

	public function do_save_appearance() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_save_appearance' );
		$out = array();
		$sep = isset( $_POST['sep'] ) ? sanitize_text_field( wp_unslash( $_POST['sep'] ) ) : '-';
		$out['sep'] = array_key_exists( $sep, \RK\SEO\Search_Appearance::separators() ) ? $sep : '-';

		$clean_tpl = function ( $v ) { return trim( wp_strip_all_tags( (string) wp_unslash( $v ) ) ); };

		foreach ( array( 'pt', 'tax' ) as $group ) {
			if ( ! empty( $_POST[ $group ] ) && is_array( $_POST[ $group ] ) ) {
				foreach ( wp_unslash( $_POST[ $group ] ) as $k => $vals ) {
					$key = sanitize_key( $k );
					$out[ $group ][ $key ] = array(
						'title'   => $clean_tpl( isset( $vals['title'] ) ? $vals['title'] : '' ),
						'desc'    => $clean_tpl( isset( $vals['desc'] ) ? $vals['desc'] : '' ),
						'noindex' => ! empty( $vals['noindex'] ) ? 1 : 0,
					);
				}
			}
		}
		if ( ! empty( $_POST['archive'] ) && is_array( $_POST['archive'] ) ) {
			foreach ( wp_unslash( $_POST['archive'] ) as $k => $vals ) {
				$key = sanitize_key( $k );
				$row = array();
				if ( isset( $vals['title'] ) ) { $row['title'] = $clean_tpl( $vals['title'] ); }
				if ( isset( $vals['desc'] ) )  { $row['desc']  = $clean_tpl( $vals['desc'] ); }
				$row['enabled'] = ! empty( $vals['enabled'] ) ? 1 : 0;
				$row['noindex'] = ! empty( $vals['noindex'] ) ? 1 : 0;
				$out['archive'][ $key ] = $row;
			}
		}
		\RK\SEO\Search_Appearance::update( $out );
		do_action( 'rk_seo_appearance_saved' ); // invalidate the indexables cache.
		$this->back( 'appearance', 'saved' );
	}

	/* ---------------- Crawl + Indexables ---------------- */

	private function tab_crawl() {
		$o = \RK\SEO\Crawl::all();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_seo_save_crawl" />';
		wp_nonce_field( 'rk_seo_save_crawl' );
		echo '<h2>Crawl optimization</h2><p class="rk-muted">Strip default WordPress head/header output that adds no SEO value.</p>';
		foreach ( \RK\SEO\Crawl::options() as $k => $meta ) {
			echo '<p><label><input type="checkbox" name="crawl[' . esc_attr( $k ) . ']" value="1" ' . checked( ! empty( $o[ $k ] ), true, false ) . ' /> <strong>' . esc_html( $meta[0] ) . '</strong></label><br><span class="rk-muted" style="margin-left:24px;">' . esc_html( $meta[1] ) . '</span></p>';
		}
		echo '<p><button class="button button-primary">Save Crawl settings</button></p></form>';

		echo '<hr><h2>Indexables cache</h2>';
		$on   = \RK\SEO\Indexables::enabled();
		$rows = \RK\SEO\Indexables::count_rows();
		echo '<p class="rk-muted">Caches each post\'s computed title/description/robots/canonical in an indexed table, read back as one row on the front end instead of recomputing every request. Rebuilds itself as pages are saved or viewed.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
		echo '<input type="hidden" name="action" value="rk_seo_save_crawl" /><input type="hidden" name="only_ix" value="1" />';
		wp_nonce_field( 'rk_seo_save_crawl' );
		echo '<p><label><input type="checkbox" name="ix_on" value="1" ' . checked( $on, true, false ) . ' /> Enable the indexables cache</label> &nbsp; <span class="rk-muted">' . intval( $rows ) . ' cached</span> <button class="button">Save</button></p>';
		echo '</form> ';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
		echo '<input type="hidden" name="action" value="rk_seo_rebuild_ix" />';
		wp_nonce_field( 'rk_seo_rebuild_ix' );
		echo '<button class="button">Clear &amp; rebuild cache</button></form>';
	}

	public function do_save_crawl() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_save_crawl' );
		// The indexables toggle posts through this same handler.
		if ( isset( $_POST['only_ix'] ) ) {
			update_option( \RK\SEO\Indexables::OPTION_ON, empty( $_POST['ix_on'] ) ? '0' : '1' );
			$this->back( 'crawl', 'saved' );
		}
		$out = array();
		$in  = ! empty( $_POST['crawl'] ) && is_array( $_POST['crawl'] ) ? wp_unslash( $_POST['crawl'] ) : array();
		foreach ( array_keys( \RK\SEO\Crawl::options() ) as $k ) { $out[ $k ] = ! empty( $in[ $k ] ) ? 1 : 0; }
		update_option( \RK\SEO\Crawl::OPTION, $out );
		$this->back( 'crawl', 'saved' );
	}

	private function tab_integrations() {
		$i = \RK\SEO\Integrations::all();
		$post = esc_url( admin_url( 'admin-post.php' ) );

		echo '<h2>Google Search Console</h2>';
		echo '<p class="rk-muted">Connect GSC to see your top queries. You provide a Google Cloud OAuth client (Google does not allow shipping shared secrets).</p>';
		echo '<form method="post" action="' . $post . '">';
		echo '<input type="hidden" name="action" value="rk_seo_save_integrations" />';
		wp_nonce_field( 'rk_seo_integrations' );
		echo '<p><label>Client ID<br><input type="text" name="gsc_client_id" class="large-text code" value="' . esc_attr( $i['gsc_client_id'] ) . '" /></label></p>';
		echo '<p><label>Client secret<br><input type="password" name="gsc_client_secret" class="large-text code" value="' . esc_attr( $i['gsc_client_secret'] ) . '" /></label></p>';
		echo '<p><label>Property URL<br><input type="url" name="gsc_site" class="large-text code" value="' . esc_attr( $i['gsc_site'] ) . '" /></label></p>';
		echo '<p class="rk-muted">Add this <strong>Authorized redirect URI</strong> to your Google OAuth client:<br><code>' . esc_html( \RK\SEO\Integrations::gsc_redirect_uri() ) . '</code></p>';
		echo '<p><button class="button button-primary">Save credentials</button></p></form>';

		if ( $i['gsc_client_id'] ) {
			if ( ! empty( $i['gsc_connected'] ) ) {
				echo '<p><span class="rk-badge" style="background:#ecfdf3;color:#027a48;padding:2px 8px;border-radius:6px;">Connected</span></p>';
				$rows = \RK\SEO\Integrations::gsc_top_queries( 10 );
				if ( is_wp_error( $rows ) ) { echo '<p class="rk-muted">' . esc_html( $rows->get_error_message() ) . '</p>'; }
				elseif ( $rows ) {
					echo '<table class="widefat striped" style="max-width:640px;"><thead><tr><th>Query</th><th>Clicks</th><th>Impr.</th><th>CTR</th><th>Pos.</th></tr></thead><tbody>';
					foreach ( $rows as $r ) {
						echo '<tr><td>' . esc_html( $r['keys'][0] ) . '</td><td>' . intval( $r['clicks'] ) . '</td><td>' . intval( $r['impressions'] ) . '</td><td>' . esc_html( round( $r['ctr'] * 100, 1 ) ) . '%</td><td>' . esc_html( round( $r['position'], 1 ) ) . '</td></tr>';
					}
					echo '</tbody></table>';
				} else { echo '<p class="rk-muted">No query data yet.</p>'; }
			} else {
				$connect = wp_nonce_url( admin_url( 'admin-post.php?action=rk_seo_gsc_connect' ), 'rk_seo_gsc_connect' );
				echo '<p><a class="button button-primary" href="' . esc_url( $connect ) . '">Connect Google Search Console</a></p>';
			}
		}

		echo '<hr><h2>Semrush</h2>';
		echo '<p class="rk-muted">Paste your Semrush API key (paid plans) to pull your domain\'s rank and organic metrics.</p>';
		echo '<form method="post" action="' . $post . '">';
		echo '<input type="hidden" name="action" value="rk_seo_save_integrations" />';
		wp_nonce_field( 'rk_seo_integrations' );
		echo '<p><input type="text" name="gsc_client_id" value="' . esc_attr( $i['gsc_client_id'] ) . '" hidden />';
		echo '<input type="password" name="gsc_client_secret" value="' . esc_attr( $i['gsc_client_secret'] ) . '" hidden />';
		echo '<input type="url" name="gsc_site" value="' . esc_attr( $i['gsc_site'] ) . '" hidden />';
		echo '<label>API key<br><input type="password" name="semrush_key" class="large-text code" value="' . esc_attr( $i['semrush_key'] ) . '" /></label></p>';
		echo '<p><button class="button button-primary">Save key</button> <button type="button" class="button" id="rk-semrush-test" data-nonce="' . esc_attr( wp_create_nonce( 'rk_seo_integrations' ) ) . '">Test connection</button> <span id="rk-semrush-out" class="rk-muted"></span></p></form>';
		echo "<script>document.getElementById('rk-semrush-test').addEventListener('click',function(){var b=this,o=document.getElementById('rk-semrush-out');o.textContent='Testing…';var d=new URLSearchParams({action:'rk_seo_semrush_test',nonce:b.dataset.nonce});fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:d}).then(function(r){return r.json();}).then(function(j){o.textContent=j.success?('OK: '+j.data.raw):('Error: '+(j.data&&j.data.message||'failed'));});});</script>";

		echo '<hr><h2>Google Site Kit</h2>';
		if ( \RK\SEO\Integrations::sitekit_active() ) {
			echo '<p><span class="rk-badge" style="background:#ecfdf3;color:#027a48;padding:2px 8px;border-radius:6px;">Detected</span> Site Kit is active — use its own dashboard for Analytics/AdSense; RK SEO stands clear to avoid duplicate tags.</p>';
		} else {
			echo '<p class="rk-muted">Site Kit is not installed. Install Google Site Kit if you want Analytics/AdSense in wp-admin; RK SEO will not conflict with it.</p>';
		}
	}

	public function do_rebuild_ix() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_rebuild_ix' );
		\RK\SEO\Indexables::clear();
		$this->back( 'crawl', 'saved' );
	}

	private function back( $tab, $msg ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab . '&rk_msg=' . $msg ) ); exit;
	}
}
