<?php
/**
 * RK_Suite_Admin — the unified "RK" menu, the Modules manager (enable/disable
 * toggles, tier-gated, dependency-aware) and the folding of each enabled
 * module's own admin menu under RK.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Suite_Admin {

	const SLUG = 'rk-suite';

	/** @var RK_Suite_License */
	private $license;
	/** @var array Ordered module groups for the sidebar accordion. */
	private $groups = array();
	private $globals = 2;

	public function __construct( $license ) { $this->license = $license; }

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'merge_menus' ), 999 );
		add_action( 'admin_post_rk_suite_toggle', array( $this, 'toggle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'sidebar_assets' ), 100 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_notices', array( $this, 'error_notices' ) );
		add_action( 'in_admin_header', array( $this, 'clean_notices' ), 1 );
	}

	public function menu() {
		add_menu_page( 'RK Suite', 'RK', 'manage_options', self::SLUG, array( $this, 'screen_dashboard' ), self::icon(), 58 );
		add_submenu_page( self::SLUG, 'Dashboard', 'Dashboard', 'manage_options', self::SLUG, array( $this, 'screen_dashboard' ) );
		add_submenu_page( self::SLUG, 'Modules', 'Modules', 'manage_options', 'rk-suite-modules', array( $this, 'screen_modules' ) );
		add_submenu_page( self::SLUG, 'License', 'License', 'manage_options', 'rk-suite-license', array( $this->license, 'render_screen' ) );
	}

	/**
	 * Fold each enabled module's own top-level menu under the RK menu, and record
	 * ordered groups so the sidebar can show category headers + collapse every
	 * group except the one you're in.
	 */
	public function merge_menus() {
		global $submenu;
		// Suite's own items (Modules, License) come first and are never grouped.
		$this->globals = isset( $submenu[ self::SLUG ] ) ? count( $submenu[ self::SLUG ] ) : 0;

		foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
			if ( ! RK_Suite_Modules::is_enabled( $slug ) ) { continue; }
			if ( empty( $def['menu_slug'] ) ) { continue; }
			$ms = $def['menu_slug'];

			$subs = isset( $submenu[ $ms ] ) && is_array( $submenu[ $ms ] ) ? $submenu[ $ms ] : array();
			remove_menu_page( $ms );

			$count = 0;
			if ( empty( $subs ) ) {
				add_submenu_page( self::SLUG, $def['name'], $def['name'], 'manage_options', 'admin.php?page=' . $ms );
				$count = 1;
			} else {
				$seen = array();
				foreach ( $subs as $sub ) {
					$title = isset( $sub[0] ) ? wp_strip_all_tags( $sub[0] ) : '';
					$cap   = isset( $sub[1] ) ? $sub[1] : 'manage_options';
					$page  = isset( $sub[2] ) ? $sub[2] : '';
					if ( '' === $page || isset( $seen[ $page ] ) ) { continue; }
					$seen[ $page ] = true;
					$url = ( false !== strpos( $page, '.php' ) || false !== strpos( $page, '://' ) ) ? $page : 'admin.php?page=' . $page;
					add_submenu_page( self::SLUG, $def['name'] . ' — ' . $title, esc_html( $title ), $cap, $url );
					$count++;
				}
			}
			if ( $count > 0 ) { $this->groups[] = array( 'module' => $slug, 'label' => $def['name'], 'count' => $count ); }
		}
	}

	/** Which module (if any) the current screen belongs to. */
	private function active_module() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page ) { return ''; }
		$best = '';
		foreach ( $this->groups as $g ) {
			if ( $page === $g['module'] || 0 === strpos( $page, $g['module'] . '-' ) || 0 === strpos( $page, $g['module'] ) ) {
				if ( strlen( $g['module'] ) > strlen( $best ) ) { $best = $g['module']; }
			}
		}
		return $best;
	}

	/** Sidebar accordion assets — loaded on every admin page (menu is global). */
	public function sidebar_assets() {
		$css = RK_SUITE_DIR . 'assets/rk-menu.css';
		$js  = RK_SUITE_DIR . 'assets/rk-menu.js';
		wp_enqueue_style( 'rk-menu', RK_SUITE_URL . 'assets/rk-menu.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_SUITE_VERSION );
		wp_enqueue_script( 'rk-menu', RK_SUITE_URL . 'assets/rk-menu.js', array(), file_exists( $js ) ? filemtime( $js ) : RK_SUITE_VERSION, true );
		wp_localize_script( 'rk-menu', 'RKMENU', array(
			'globals' => (int) $this->globals,
			'groups'  => array_values( $this->groups ),
			'active'  => $this->active_module(),
		) );
	}

	/** True on any RK admin screen (all our pages use ?page=rk-…). */
	private function is_rk_screen( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return ( false !== strpos( (string) $hook, 'rk-' ) ) || ( 0 === strpos( $page, 'rk-' ) );
	}

	public function body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 === strpos( $page, 'rk-' ) ) { $classes .= ' rk-admin'; }
		return $classes;
	}

	public function assets( $hook ) {
		if ( ! $this->is_rk_screen( $hook ) ) { return; }
		// Shared premium UI kit — loaded on EVERY RK screen, last so it wins.
		$css = RK_SUITE_DIR . 'assets/rk-ui.css';
		$js  = RK_SUITE_DIR . 'assets/rk-ui.js';
		wp_enqueue_style( 'rk-ui', RK_SUITE_URL . 'assets/rk-ui.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_SUITE_VERSION );
		wp_enqueue_script( 'rk-ui', RK_SUITE_URL . 'assets/rk-ui.js', array(), file_exists( $js ) ? filemtime( $js ) : RK_SUITE_VERSION, true );
		if ( false !== strpos( (string) $hook, 'rk-suite' ) ) {
			wp_enqueue_style( 'rk-suite-admin', RK_SUITE_URL . 'assets/admin.css', array( 'rk-ui' ), RK_SUITE_VERSION );
		}
	}

	/** On RK screens, remove third-party nag notices (Elementor promo, update
	 *  nags, etc.) for a clean UI. Our own suite error notice is re-added. */
	public function clean_notices() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'rk-' ) ) { return; }
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		add_action( 'admin_notices', array( $this, 'error_notices' ) );
	}

	public function error_notices() {
		$errors = RK_Suite::errors();
		if ( empty( $errors ) ) { return; }
		foreach ( $errors as $slug => $msg ) {
			echo '<div class="notice notice-error"><p><strong>RK Suite:</strong> module <code>' . esc_html( $slug ) . '</code> could not load: ' . esc_html( $msg ) . '</p></div>';
		}
	}

	/* ---------------- shared shell ---------------- */

	public static function head( $current, $license ) {
		$tabs = array( self::SLUG => 'Modules', 'rk-suite-license' => 'License' );
		$tier = $license ? $license->tier() : 'free';
		echo '<div class="wrap rk-suite-wrap rk-has-rail">';
		self::render_sidebar();
		echo '<main class="rk-main">';
	}

	public static function foot() { echo '</main></div>'; }

	/** Small dashicon per module group. */
	private static function group_icon( $slug ) {
		$map = array(
			'rk-core' => 'dashicons-database', 'rk-migrate' => 'dashicons-migrate',
			'rk-theme' => 'dashicons-cover-image', 'rk-seo' => 'dashicons-search',
			'rk-library' => 'dashicons-screenoptions', 'rk-elements' => 'dashicons-screenoptions',
			'rk-forms' => 'dashicons-feedback',
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : 'dashicons-admin-generic';
	}

	/** page + tab signature for a submenu url/slug. */
	private static function sig( $slugurl ) {
		$q = ( false !== strpos( $slugurl, '?' ) ) ? substr( $slugurl, strpos( $slugurl, '?' ) + 1 ) : 'page=' . $slugurl;
		parse_str( $q, $a );
		return ( isset( $a['page'] ) ? $a['page'] : '' ) . '|' . ( isset( $a['tab'] ) ? $a['tab'] : '' );
	}

	private static function href( $slugurl ) {
		if ( false !== strpos( $slugurl, '://' ) ) { return $slugurl; }
		if ( false !== strpos( $slugurl, '.php' ) ) { return admin_url( $slugurl ); }
		return admin_url( 'admin.php?page=' . $slugurl );
	}

	/**
	 * The shared RK contextual sidebar — full nav tree of every enabled module
	 * and its sub-pages, present on every RK admin screen. Groups collapse; the
	 * group containing the current page is open; a collapse toggle shrinks it to
	 * an icon rail with hover tooltips.
	 */
	public static function render_sidebar() {
		global $submenu;
		$all  = isset( $submenu[ self::SLUG ] ) && is_array( $submenu[ self::SLUG ] ) ? $submenu[ self::SLUG ] : array();
		$cur  = ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ) . '|' . ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' );
		$cur_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		echo '<aside class="rk-rail rk-rail-full" data-rk-rail>';
		echo '<div class="rk-rail-head">';
		echo '<div class="rk-rail-brand"><div class="pk-logo">RK</div><div class="rk-rail-id"><div class="pk-brand">RK Suite</div><div class="pk-tag">Elementor toolkit</div></div></div>';
		echo '<button class="rk-rail-collapse" type="button" aria-label="Collapse sidebar"><span class="dashicons dashicons-arrow-left-alt2"></span></button>';
		echo '</div>';

		echo '<nav class="rk-rail-nav">';

		/* Global suite items */
		$globals = array(
			self::SLUG           => array( 'Dashboard', 'dashicons-dashboard' ),
			'rk-suite-modules'   => array( 'Modules', 'dashicons-grid-view' ),
			'rk-suite-license'   => array( 'License', 'dashicons-admin-network' ),
		);
		foreach ( $globals as $slug => $meta ) {
			$active = ( self::sig( $slug ) === $cur ) ? ' is-active' : '';
			echo '<a class="rk-rail-link' . $active . '" href="' . esc_url( self::href( $slug ) ) . '" data-tip="' . esc_attr( $meta[0] ) . '"><span class="dashicons ' . esc_attr( $meta[1] ) . '"></span><span class="rk-rail-txt">' . esc_html( $meta[0] ) . '</span></a>';
		}

		/* Module groups */
		foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
			if ( ! RK_Suite_Modules::is_enabled( $slug ) || empty( $def['menu_slug'] ) ) { continue; }
			$ms = $def['menu_slug'];
			$children = array();
			$seen = array();
			foreach ( $all as $sub ) {
				$url  = isset( $sub[2] ) ? $sub[2] : '';
				$page = self::sig( $url );
				$pg   = strstr( $page . '|', '|', true );
				if ( $pg === $ms || 0 === strpos( $pg, $ms . '-' ) ) {
					if ( isset( $seen[ $url ] ) ) { continue; }
					$seen[ $url ] = 1;
					$children[] = array( 'title' => isset( $sub[0] ) ? wp_strip_all_tags( $sub[0] ) : '', 'url' => $url );
				}
			}
			if ( ! $children ) { continue; }
			$group_open = ( '' !== $cur_page && 0 === strpos( $cur_page, $ms ) );
			echo '<div class="rk-rail-group' . ( $group_open ? ' is-open' : '' ) . '">';
			echo '<button class="rk-rail-grouphead" type="button" data-tip="' . esc_attr( $def['name'] ) . '"><span class="dashicons ' . esc_attr( self::group_icon( $slug ) ) . '"></span><span class="rk-rail-txt">' . esc_html( $def['name'] ) . '</span><i class="rk-rail-caret dashicons dashicons-arrow-down-alt2"></i></button>';
			echo '<div class="rk-rail-sub">';
			foreach ( $children as $c ) {
				$active = ( self::sig( $c['url'] ) === $cur ) ? ' is-active' : '';
				echo '<a class="rk-rail-sublink' . $active . '" href="' . esc_url( self::href( $c['url'] ) ) . '">' . esc_html( $c['title'] ) . '</a>';
			}
			echo '</div></div>';
		}
		echo '</nav>';
		echo '<div class="rk-rail-foot"><span class="pk-status pk-status-ok"><span class="pk-dot"></span> v' . esc_html( RK_SUITE_VERSION ) . '</span></div>';
		echo '</aside>';
	}

	/* ---------------- modules screen ---------------- */

	public function screen_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		self::head( self::SLUG, $this->license );
		$this->notice();

		$defs   = RK_Suite_Modules::definitions();
		$total  = count( $defs );
		$active = 0;
		foreach ( $defs as $s => $d ) { if ( RK_Suite_Modules::is_enabled( $s ) ) { $active++; } }
		$cpts   = count( (array) get_option( 'rk_core_cpts', array() ) );
		$taxes  = count( (array) get_option( 'rk_core_taxonomies', array() ) );
		$groups = count( (array) get_option( 'rk_core_field_groups', array() ) );
		$rels   = count( (array) get_option( 'rk_core_relations', array() ) );
		$queries= count( (array) get_option( 'rk_core_queries', array() ) );
		$tpls   = 0;
		if ( post_type_exists( 'rk_template' ) ) { $c = wp_count_posts( 'rk_template' ); $tpls = (int) $c->publish + (int) $c->draft; }
		$tier   = $this->license->tier();

		$u      = wp_get_current_user();
		$name   = $u ? ( $u->first_name ? $u->first_name : $u->display_name ) : '';
		$h      = (int) current_time( 'G' );
		$greet  = $h < 12 ? 'Good morning' : ( $h < 17 ? 'Good afternoon' : 'Good evening' );
		$errors = RK_Suite::errors();
		$ok     = empty( $errors );

		echo '<div class="rk-hero">';
		echo '<div class="rk-hero-main">';
		echo '<span class="rk-hero-eyebrow"><i></i> OVERVIEW</span>';
		echo '<h1 class="rk-hero-title">' . esc_html( $greet ) . ( $name ? ', <span>' . esc_html( $name ) . '</span>' : '' ) . '.</h1>';
		echo '<p class="rk-hero-sub">Here&rsquo;s a quick snapshot of your RK Suite today.</p>';
		echo '</div>';
		echo '<div class="rk-hero-side">';
		echo $ok
			? '<span class="rk-hero-status is-ok"><span class="rk-dot"></span> All systems operational</span>'
			: '<span class="rk-hero-status is-warn"><span class="rk-dot"></span> ' . count( $errors ) . ' module(s) need attention</span>';
		echo '<span class="rk-hero-time">' . esc_html( date_i18n( 'D · j M Y' ) ) . ' · <strong>' . esc_html( date_i18n( 'g:i a' ) ) . '</strong></span>';
		echo '</div></div>';

		echo '<div class="rk-kpi-grid">';
		$this->kpi( 'dashicons-grid-view',     $active . ' / ' . $total, 'Modules active',  ( $total - $active ) . ' inactive' );
		$this->kpi( 'dashicons-admin-network', ucfirst( $tier ),         'License tier',    $this->license->is_active() ? 'Active' : 'Free plan' );
		$this->kpi( 'dashicons-database',      $cpts,                    'Post types',      'Custom types' );
		$this->kpi( 'dashicons-category',      $taxes,                   'Taxonomies',      'Classification' );
		$this->kpi( 'dashicons-list-view',     $groups,                  'Field groups',    'Meta boxes' );
		$this->kpi( 'dashicons-cover-image',   $tpls,                    'Theme templates', 'Headers & footers' );
		$this->kpi( 'dashicons-networking',    $rels,                    'Relations',       'Object links' );
		$this->kpi( 'dashicons-filter',        $queries,                 'Saved queries',   'Reusable queries' );
		echo '</div>';

		echo '<div class="rk-dash-cols">';

		echo '<div class="rk-dash-card"><div class="rk-dash-card-head"><h3>Modules</h3><a class="button" href="' . esc_url( admin_url( 'admin.php?page=rk-suite-modules' ) ) . '">Manage</a></div>';
		echo '<ul class="rk-modlist">';
		foreach ( $defs as $slug => $def ) {
			$on  = RK_Suite_Modules::is_enabled( $slug );
			$ico = self::group_icon( ! empty( $def['menu_slug'] ) ? $def['menu_slug'] : $slug );
			echo '<li><span class="rk-modlist-name"><span class="dashicons ' . esc_attr( $ico ) . '"></span> ' . esc_html( $def['name'] ) . '</span><span class="rk-modstate rk-modstate-' . ( $on ? 'on' : 'off' ) . '">' . ( $on ? '● Enabled' : '○ Disabled' ) . '</span></li>';
		}
		echo '</ul></div>';

		echo '<div class="rk-dash-card"><div class="rk-dash-card-head"><h3>Quick actions</h3></div>';
		echo '<div class="rk-quicklinks">';
		foreach ( $defs as $slug => $def ) {
			if ( RK_Suite_Modules::is_enabled( $slug ) && ! empty( $def['menu_slug'] ) ) {
				echo '<a class="rk-quicklink" href="' . esc_url( admin_url( 'admin.php?page=' . $def['menu_slug'] ) ) . '"><span class="dashicons ' . esc_attr( self::group_icon( $def['menu_slug'] ) ) . '"></span> ' . esc_html( $def['name'] ) . '</a>';
			}
		}
		echo '<a class="rk-quicklink" href="' . esc_url( admin_url( 'admin.php?page=rk-suite-license' ) ) . '"><span class="dashicons dashicons-admin-network"></span> License &amp; activation</a>';
		echo '</div></div>';

		echo '</div>';
		self::foot();
	}

	private function kpi( $icon, $val, $label, $sub = '' ) {
		echo '<div class="rk-kpi">';
		echo '<div class="rk-kpi-top"><span class="rk-kpi-ico dashicons ' . esc_attr( $icon ) . '"></span><span class="rk-kpi-val">' . esc_html( $val ) . '</span></div>';
		echo '<div class="rk-kpi-meta"><span class="rk-kpi-label"><i></i> ' . esc_html( $label ) . '</span>';
		if ( '' !== $sub ) { echo '<span class="rk-kpi-sub">' . esc_html( $sub ) . '</span>'; }
		echo '</div>';
		echo '<span class="rk-kpi-watermark dashicons ' . esc_attr( $icon ) . '"></span>';
		echo '</div>';
	}

	public function screen_modules() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		self::head( self::SLUG, $this->license );
		$this->notice();

		echo '<div class="pk-panel"><h3>Modules</h3>';
		echo '<p class="description" style="margin:0 0 14px;">Enable only what you need. Disabled modules never load. Your license tier is <strong>' . esc_html( $this->license->tier() ) . '</strong>.</p>';

		$errors = RK_Suite::errors();
		echo '<div class="rk-modgrid">';
		foreach ( RK_Suite_Modules::definitions() as $slug => $def ) {
			$enabled  = RK_Suite_Modules::is_enabled( $slug );
			$unlocked = $this->license->unlocks( $def['tier'] );
			$errored  = isset( $errors[ $slug ] );

			echo '<div class="rk-modcard' . ( $enabled ? ' is-on' : '' ) . '">';
			echo '<div class="rk-modcard-head"><h4>' . esc_html( $def['name'] ) . '</h4><span class="rk-badge-tier rk-badge-' . esc_attr( $def['tier'] ) . '">' . esc_html( ucfirst( $def['tier'] ) ) . '</span></div>';
			echo '<p class="rk-moddesc">' . esc_html( $def['desc'] ) . '</p>';
			if ( ! empty( $def['depends'] ) ) {
				echo '<p class="rk-moddep">Needs: ' . esc_html( implode( ', ', $def['depends'] ) ) . '</p>';
			}
			echo '<div class="rk-modcard-foot">';
			if ( $errored ) {
				echo '<span class="rk-modstate rk-modstate-err">Load error</span>';
			} elseif ( $enabled ) {
				echo '<span class="rk-modstate rk-modstate-on">● Enabled</span>';
			} else {
				echo '<span class="rk-modstate rk-modstate-off">○ Disabled</span>';
			}
			echo $this->toggle_button( $slug, $enabled, $unlocked, $def['tier'] );
			echo '</div></div>';
		}
		echo '</div></div>';
		self::foot();
	}

	private function toggle_button( $slug, $enabled, $unlocked, $tier ) {
		if ( ! $unlocked && ! $enabled ) {
			return '<span class="rk-modlock">Requires ' . esc_html( ucfirst( $tier ) ) . '</span>';
		}
		$enable = $enabled ? '0' : '1';
		$label  = $enabled ? 'Disable' : 'Enable';
		$style  = $enabled ? 'secondary' : 'primary';
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=rk_suite_toggle&slug=' . rawurlencode( $slug ) . '&enable=' . $enable ),
			'rk_suite_toggle_' . $slug
		);
		return '<a class="button button-' . esc_attr( $style ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	public function toggle() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
		$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
		check_admin_referer( 'rk_suite_toggle_' . $slug );
		$def = RK_Suite_Modules::get( $slug );
		if ( ! $def ) { $this->redirect( 'unknown' ); }

		$enable = ! empty( $_GET['enable'] );

		if ( $enable ) {
			// tier gate
			if ( ! $this->license->unlocks( $def['tier'] ) ) { $this->redirect( 'locked' ); }
			// auto-enable hard dependencies first
			foreach ( (array) $def['depends'] as $dep ) {
				if ( ! RK_Suite_Modules::is_enabled( $dep ) ) {
					RK_Suite::activate_module( $dep );
					RK_Suite_Modules::set_enabled( $dep, true );
				}
			}
			RK_Suite::activate_module( $slug );
			RK_Suite_Modules::set_enabled( $slug, true );
			$this->redirect( 'enabled' );
		}

		// disabling: warn if a still-enabled module depends on this one
		foreach ( RK_Suite_Modules::definitions() as $s2 => $d2 ) {
			if ( $s2 === $slug ) { continue; }
			if ( RK_Suite_Modules::is_enabled( $s2 ) && in_array( $slug, (array) $d2['depends'], true ) ) {
				RK_Suite_Modules::set_enabled( $slug, false );
				flush_rewrite_rules();
				$this->redirect( 'disabled_dep' );
			}
		}
		RK_Suite_Modules::set_enabled( $slug, false );
		flush_rewrite_rules();
		$this->redirect( 'disabled' );
	}

	private function redirect( $code ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&rk_msg=' . $code ) );
		exit;
	}

	private function notice() {
		$code = isset( $_GET['rk_msg'] ) ? sanitize_key( $_GET['rk_msg'] ) : '';
		if ( '' === $code ) { return; }
		$map = array(
			'enabled'       => array( 'success', 'Module enabled.' ),
			'disabled'      => array( 'success', 'Module disabled.' ),
			'disabled_dep'  => array( 'warning', 'Module disabled. Another enabled module depends on it and may now run in reduced mode.' ),
			'locked'        => array( 'error', 'Your license tier does not include that module.' ),
			'unknown'       => array( 'error', 'Unknown module.' ),
		);
		if ( isset( $map[ $code ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $code ][1] ) . '</p></div>';
		}
	}

	private static function icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a0a5aa" d="M3 3h3v6l4-6h3.5l-4.6 6.6L16 17h-3.6l-4-6v6H3z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
