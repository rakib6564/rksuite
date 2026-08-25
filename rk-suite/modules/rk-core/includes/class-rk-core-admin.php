<?php
/**
 * RK_Core_Admin — JetEngine-style admin. Each area (Post Types, Taxonomies,
 * Field Groups, Relations, Queries) is its own WordPress submenu page (left
 * sub-nav) with a list view and dedicated Add / Edit pages; the post-type and
 * relation/query editors use tabbed sections.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core_Admin {

	const SLUG     = 'rk-core';
	const SLUG_TAX = 'rk-core-taxonomies';
	const SLUG_FLD = 'rk-core-fields';
	const SLUG_REL = 'rk-core-relations';
	const SLUG_QRY = 'rk-core-queries';
	const SLUG_SITE = 'rk-core-site';
	const SLUG_IE   = 'rk-core-tools';
	const SLUG_LIST = 'rk-core-listings';

	private $cpts;
	private $taxes;
	private $fields;

	public function __construct( $cpts, $taxes, $fields ) {
		$this->cpts   = $cpts;
		$this->taxes  = $taxes;
		$this->fields = $fields;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		foreach ( array(
			'rk_core_save_cpt', 'rk_core_delete_cpt', 'rk_core_save_tax', 'rk_core_delete_tax',
			'rk_core_save_group', 'rk_core_delete_group', 'rk_core_save_rel', 'rk_core_delete_rel',
			'rk_core_save_qry', 'rk_core_delete_qry', 'rk_core_save_site',
			'rk_core_export', 'rk_core_import', 'rk_core_starter', 'rk_core_example', 'rk_core_jet_listing_delete', 'rk_core_jet_debug',
			'rk_core_listing_create', 'rk_core_listing_delete', 'rk_core_listing_duplicate', 'rk_core_listing_export', 'rk_core_listing_import',
		) as $a ) {
			add_action( 'admin_post_' . $a, array( $this, str_replace( 'rk_core_', 'do_', $a ) ) );
		}
	}

	public function menu() {
		add_menu_page( 'RK Core', 'RK Core', 'manage_options', self::SLUG, array( $this, 'screen_cpts' ), 'dashicons-database', 59 );
		add_submenu_page( self::SLUG, 'Post Types', 'Post Types', 'manage_options', self::SLUG, array( $this, 'screen_cpts' ) );
		add_submenu_page( self::SLUG, 'Taxonomies', 'Taxonomies', 'manage_options', self::SLUG_TAX, array( $this, 'screen_taxes' ) );
		add_submenu_page( self::SLUG, 'Meta Boxes', 'Meta Boxes', 'manage_options', self::SLUG_FLD, array( $this, 'screen_fields' ) );
		add_submenu_page( self::SLUG, 'Relations', 'Relations', 'manage_options', self::SLUG_REL, array( $this, 'screen_relations' ) );
		add_submenu_page( self::SLUG, 'Queries', 'Queries', 'manage_options', self::SLUG_QRY, array( $this, 'screen_queries' ) );
		add_submenu_page( self::SLUG, 'Listings', 'Listings', 'manage_options', self::SLUG_LIST, array( $this, 'screen_listings' ) );
		add_submenu_page( self::SLUG, 'Site Settings', 'Site Settings', 'manage_options', self::SLUG_SITE, array( $this, 'screen_site' ) );
		add_submenu_page( self::SLUG, 'Import / Export', 'Import / Export', 'manage_options', self::SLUG_IE, array( $this, 'screen_tools' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rk-core' ) ) { return; }
		$css = RK_CORE_DIR . 'assets/admin.css';
		$js  = RK_CORE_DIR . 'assets/builder.js';
		wp_enqueue_style( 'rk-core-admin', RK_CORE_URL . 'assets/admin.css', array(), file_exists( $css ) ? filemtime( $css ) : RK_CORE_VERSION );
		wp_enqueue_script( 'rk-core-builder', RK_CORE_URL . 'assets/builder.js', array( 'jquery', 'jquery-ui-sortable' ), file_exists( $js ) ? filemtime( $js ) : RK_CORE_VERSION, true );
		if ( false !== strpos( (string) $hook, 'rk-core-site' ) ) { wp_enqueue_media(); }
	}

	/* ---------------- shell ---------------- */

	/** Section registry: slug => [ title, subtitle, dashicon ]. */
	private function sections() {
		return array(
			self::SLUG     => array( 'Post Types', 'Custom post types', 'dashicons-database' ),
			self::SLUG_TAX => array( 'Taxonomies', 'Custom taxonomies', 'dashicons-tag' ),
			self::SLUG_FLD => array( 'Meta Boxes', 'Custom fields', 'dashicons-feedback' ),
			self::SLUG_REL => array( 'Relations', 'Link content together', 'dashicons-networking' ),
			self::SLUG_QRY => array( 'Queries', 'Reusable queries', 'dashicons-filter' ),
			self::SLUG_SITE => array( 'Site Settings', 'Identity, colours &amp; fonts', 'dashicons-admin-appearance' ),
			self::SLUG_IE   => array( 'Import / Export', 'Models, starters &amp; AI prompts', 'dashicons-migrate' ),
		);
	}

	private function head( $current ) {
		$sections = $this->sections();
		$linked   = class_exists( 'RK_Hub' );

		echo '<div class="wrap rk-core-wrap rk-has-rail">';
		if ( class_exists( 'RK_Suite_Admin' ) ) { RK_Suite_Admin::render_sidebar(); } else {
		echo '<aside class="rk-rail">';
		echo '<div class="rk-rail-brand"><div class="pk-logo">RK</div><div><div class="pk-brand">RK Core <span class="pk-ver">v' . esc_html( RK_CORE_VERSION ) . '</span></div><div class="pk-tag">CPT, fields &amp; queries</div></div></div>';
		echo '<nav class="rk-subnav rk-rail-nav">';
		foreach ( $sections as $slug => $meta ) {
			$active = ( $current === $slug ) ? ' is-active' : '';
			echo '<a class="rk-subnav-item' . $active . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="dashicons ' . esc_attr( $meta[2] ) . '"></span> ' . esc_html( $meta[0] ) . '</a>';
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
		$report = get_transient( 'rk_core_ie_report' );
		$rmsg   = $report ? 'Imported: ' . esc_html( implode( ', ', (array) $report ) ) . '.' : 'Done.';
		$map = array(
			'saved'     => array( 'success', 'Saved.' ),
			'deleted'   => array( 'success', 'Deleted.' ),
			'imported'  => array( 'success', $rmsg ),
			'installed' => array( 'success', $rmsg ),
			'jetdeleted' => array( 'success', 'JetEngine listing deleted.' ),
			'err'       => array( 'error', get_transient( 'rk_core_err' ) ? get_transient( 'rk_core_err' ) : 'Could not save.' ),
		);
		if ( isset( $map[ $code ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $code ][0] ) . ' is-dismissible"><p>' . wp_kses_post( $map[ $code ][1] ) . '</p></div>';
			if ( 'err' === $code ) { delete_transient( 'rk_core_err' ); }
			if ( 'imported' === $code || 'installed' === $code ) { delete_transient( 'rk_core_ie_report' ); }
		}
	}

	private function page_head( $title, $add_url = '', $add_label = '' ) {
		echo '<div class="rk-page-head"><h3>' . esc_html( $title ) . '</h3>';
		if ( $add_url ) { echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">+ ' . esc_html( $add_label ) . '</a>'; }
		echo '</div>';
	}

	private function back_link( $url, $label ) {
		echo '<a class="rk-back" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> ' . esc_html( $label ) . '</a>';
	}

	private function all_ui_post_types() {
		$out = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $slug => $obj ) {
			if ( in_array( $slug, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ), true ) ) { continue; }
			$out[ $slug ] = $obj->labels->singular_name . ' (' . $slug . ')';
		}
		return $out;
	}

	private function yn( $on ) {
		return $on ? '<span class="rk-badge rk-badge-yes">Yes</span>' : '<span class="rk-badge rk-badge-no">No</span>';
	}

	private function row_open( $label, $for = '', $req = false ) {
		echo '<div class="rk-form-row"><label class="rk-form-label"' . ( $for ? ' for="' . esc_attr( $for ) . '"' : '' ) . '>' . esc_html( $label ) . ( $req ? ' <span class="req">*</span>' : '' ) . '</label><div class="rk-form-field">';
	}
	private function row_close( $hint = '' ) {
		if ( '' !== $hint ) { echo '<p class="rk-form-hint">' . wp_kses( $hint, array( 'code' => array() ) ) . '</p>'; }
		echo '</div></div>';
	}

	private function toggle_row( $name, $label, $desc, $checked ) {
		echo '<div class="rk-toggle-row"><div class="rk-toggle-info"><span class="rk-toggle-name">' . esc_html( $label ) . '</span>';
		if ( $desc ) { echo '<span class="rk-toggle-desc">' . esc_html( $desc ) . '</span>'; }
		echo '</div><label class="rk-switch rk-switch-lg"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $checked, true, false ) . ' /><span class="rk-switch-track"><span class="rk-switch-thumb"></span></span></label></div>';
	}

	private function after( $res, $page ) {
		if ( is_wp_error( $res ) ) {
			set_transient( 'rk_core_err', $res->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&add=1&rk_msg=err' ) );
		} else {
			if ( in_array( $page, array( self::SLUG, self::SLUG_TAX ), true ) ) { flush_rewrite_rules(); }
			wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&rk_msg=saved' ) );
		}
		exit;
	}

	/* ================= Post Types ================= */

	public function screen_cpts() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_GET['edit'] ) ) { $this->cpt_form( RK_CPT_Builder::get( sanitize_key( $_GET['edit'] ) ) ); return; }
		if ( isset( $_GET['add'] ) )  { $this->cpt_form( null ); return; }
		$this->cpt_list();
	}

	private function cpt_list() {
		$this->head( self::SLUG );
		$add = admin_url( 'admin.php?page=' . self::SLUG . '&add=1' );
		echo '<div class="pk-panel">';
		$this->page_head( 'Post types', $add, 'Add Post Type' );
		$all = RK_CPT_Builder::all();
		if ( empty( $all ) ) {
			echo '<div class="rk-empty"><div class="rk-empty-icon"><span class="dashicons dashicons-database"></span></div><p class="rk-empty-title">No custom post types yet</p><p class="rk-empty-sub">Create your first post type to start modelling content.</p><a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Post Type</a></div>';
		} else {
			echo '<table class="widefat striped rk-list"><thead><tr><th>Label</th><th>Key</th><th>Public</th><th>Archive</th><th>REST</th><th class="rk-right">Actions</th></tr></thead><tbody>';
			foreach ( $all as $def ) {
				$e = admin_url( 'admin.php?page=' . self::SLUG . '&edit=' . $def['slug'] );
				$d = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_delete_cpt&slug=' . $def['slug'] ), 'rk_core_delete_cpt_' . $def['slug'] );
				echo '<tr><td><strong>' . esc_html( $def['plural'] ) . '</strong><br><span class="rk-sub">' . esc_html( $def['singular'] ) . '</span></td><td><code>' . esc_html( $def['slug'] ) . '</code></td><td>' . $this->yn( ! empty( $def['public'] ) ) . '</td><td>' . $this->yn( ! empty( $def['has_archive'] ) ) . '</td><td>' . $this->yn( ! empty( $def['show_in_rest'] ) ) . '</td><td class="rk-right"><a href="' . esc_url( $e ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $d ) . '" onclick="return confirm(\'Delete this post type?\')" class="rk-danger">Delete</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		$this->foot();
	}

	private function cpt_form( $edit ) {
		$this->head( self::SLUG );
		$back = admin_url( 'admin.php?page=' . self::SLUG );
		echo '<div class="pk-panel">';
		echo '<h3>' . ( $edit ? 'Edit post type — ' . esc_html( $edit['plural'] ) : 'Add post type' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form rk-tabbed">';
		echo '<input type="hidden" name="action" value="rk_core_save_cpt" />';
		wp_nonce_field( 'rk_core_save_cpt' );
		$v = function ( $k, $d = '' ) use ( $edit ) { return $edit && isset( $edit[ $k ] ) ? $edit[ $k ] : $d; };

		echo '<nav class="rk-tabs"><a class="rk-tab is-active" data-tab="general">General</a><a class="rk-tab" data-tab="display">Display</a><a class="rk-tab" data-tab="supports">Supports</a><a class="rk-tab" data-tab="advanced">Advanced</a></nav>';

		echo '<div class="rk-tab-panel" data-panel="general">';
		$this->row_open( 'Key', 'rk_slug', true );
		echo '<input type="text" id="rk_slug" name="slug" value="' . esc_attr( $v( 'slug' ) ) . '" ' . ( $edit ? 'readonly' : '' ) . ' required maxlength="20" placeholder="book" />';
		$this->row_close( 'Lowercase, &le;20 chars. Cannot change after creation.' );
		$this->row_open( 'Singular label', 'rk_singular' );
		echo '<input type="text" id="rk_singular" name="singular" value="' . esc_attr( $v( 'singular' ) ) . '" placeholder="Book" />'; $this->row_close();
		$this->row_open( 'Plural label', 'rk_plural' );
		echo '<input type="text" id="rk_plural" name="plural" value="' . esc_attr( $v( 'plural' ) ) . '" placeholder="Books" />'; $this->row_close();
		echo '</div>';

		echo '<div class="rk-tab-panel" data-panel="display" hidden>';
		$this->row_open( 'Menu icon', 'rk_icon' );
		echo '<input type="text" id="rk_icon" name="menu_icon" value="' . esc_attr( $v( 'menu_icon', 'dashicons-admin-post' ) ) . '" />';
		$this->row_close( 'A <code>dashicons-*</code> class.' );
		$this->row_open( 'Rewrite slug', 'rk_rewrite' );
		echo '<input type="text" id="rk_rewrite" name="rewrite_slug" value="' . esc_attr( $v( 'rewrite_slug' ) ) . '" placeholder="(defaults to key)" />'; $this->row_close();
		echo '</div>';

		$sup = $edit && isset( $edit['supports'] ) ? $edit['supports'] : array( 'title', 'editor', 'thumbnail' );
		echo '<div class="rk-tab-panel" data-panel="supports" hidden>';
		$this->row_open( 'Editor features' );
		echo '<div class="rk-checks">';
		foreach ( array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'page-attributes', 'comments' ) as $sopt ) {
			echo '<label class="rk-check"><input type="checkbox" name="supports[]" value="' . esc_attr( $sopt ) . '" ' . checked( in_array( $sopt, $sup, true ), true, false ) . ' /> ' . esc_html( $sopt ) . '</label>';
		}
		echo '</div>'; $this->row_close();
		echo '</div>';

		echo '<div class="rk-tab-panel" data-panel="advanced" hidden><div class="rk-toggles">';
		$this->toggle_row( 'public', 'Public', 'Controls how the type is visible to authors and readers', ! empty( $v( 'public', 1 ) ) );
		$this->toggle_row( 'show_in_rest', 'Show in REST / Gutenberg', 'Expose to the REST API and the block editor', ! empty( $v( 'show_in_rest', 1 ) ) );
		$this->toggle_row( 'has_archive', 'Has archive', 'Enables a post type archive page', ! empty( $v( 'has_archive' ) ) );
		$this->toggle_row( 'hierarchical', 'Hierarchical', 'Behaves like Pages (parent/child) instead of Posts', ! empty( $v( 'hierarchical' ) ) );
		$this->toggle_row( 'exclude_from_search', 'Exclude from search', 'Hide these posts from front-end search results', ! empty( $v( 'exclude_from_search' ) ) );
		$this->toggle_row( 'publicly_queryable', 'Publicly queryable', 'Allow front-end queries (single/archive templates)', ! empty( $v( 'publicly_queryable', 1 ) ) );
		$this->toggle_row( 'show_ui', 'Show admin UI', 'Generate the default admin screens for this type', ! empty( $v( 'show_ui', 1 ) ) );
		$this->toggle_row( 'show_in_menu', 'Show in admin menu', 'Show this type in the wp-admin sidebar', ! empty( $v( 'show_in_menu', 1 ) ) );
		$this->toggle_row( 'show_in_nav_menus', 'Show in nav menus', 'Make available in Appearance → Menus', ! empty( $v( 'show_in_nav_menus', 1 ) ) );
		echo '</div></div>';

		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><a class="button rk-savebar-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a><span class="rk-savebar-spacer"></span>';
		submit_button( $edit ? 'Update post type' : 'Create post type', 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">Cancel</a></div></div>';
		echo '</form></div>';
		$this->foot();
	}

	public function do_save_cpt() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_save_cpt' );
		$this->after( RK_CPT_Builder::save( wp_unslash( $_POST ) ), self::SLUG );
	}
	public function do_delete_cpt() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
		check_admin_referer( 'rk_core_delete_cpt_' . $slug );
		RK_CPT_Builder::delete( $slug ); flush_rewrite_rules();
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&rk_msg=deleted' ) ); exit;
	}

	/* ================= Taxonomies ================= */

	public function screen_taxes() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_GET['edit'] ) ) { $this->tax_form( RK_Taxonomy_Builder::get( sanitize_key( $_GET['edit'] ) ) ); return; }
		if ( isset( $_GET['add'] ) )  { $this->tax_form( null ); return; }
		$this->tax_list();
	}

	private function tax_list() {
		$this->head( self::SLUG_TAX );
		$add = admin_url( 'admin.php?page=' . self::SLUG_TAX . '&add=1' );
		echo '<div class="pk-panel">';
		$this->page_head( 'Taxonomies', $add, 'Add Taxonomy' );
		$all = RK_Taxonomy_Builder::all();
		if ( empty( $all ) ) {
			echo '<div class="rk-empty"><div class="rk-empty-icon"><span class="dashicons dashicons-tag"></span></div><p class="rk-empty-title">No custom taxonomies yet</p><p class="rk-empty-sub">Taxonomies group and classify your content.</p><a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Taxonomy</a></div>';
		} else {
			echo '<table class="widefat striped rk-list"><thead><tr><th>Label</th><th>Key</th><th>Attached to</th><th>Hierarchical</th><th class="rk-right">Actions</th></tr></thead><tbody>';
			foreach ( $all as $def ) {
				$e = admin_url( 'admin.php?page=' . self::SLUG_TAX . '&edit=' . $def['slug'] );
				$d = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_delete_tax&slug=' . $def['slug'] ), 'rk_core_delete_tax_' . $def['slug'] );
				$pills = '';
				foreach ( (array) $def['object_types'] as $o ) { $pills .= '<span class="rk-type-pill">' . esc_html( $o ) . '</span> '; }
				echo '<tr><td><strong>' . esc_html( $def['plural'] ) . '</strong><br><span class="rk-sub">' . esc_html( $def['singular'] ) . '</span></td><td><code>' . esc_html( $def['slug'] ) . '</code></td><td>' . ( $pills ? $pills : '—' ) . '</td><td>' . $this->yn( ! empty( $def['hierarchical'] ) ) . '</td><td class="rk-right"><a href="' . esc_url( $e ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $d ) . '" class="rk-danger">Delete</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		$this->foot();
	}

	private function tax_form( $edit ) {
		$this->head( self::SLUG_TAX );
		$back = admin_url( 'admin.php?page=' . self::SLUG_TAX );
		echo '<div class="pk-panel">';
		echo '<h3>' . ( $edit ? 'Edit taxonomy — ' . esc_html( $edit['plural'] ) : 'Add taxonomy' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form">';
		echo '<input type="hidden" name="action" value="rk_core_save_tax" />';
		wp_nonce_field( 'rk_core_save_tax' );
		$v = function ( $k, $d = '' ) use ( $edit ) { return $edit && isset( $edit[ $k ] ) ? $edit[ $k ] : $d; };
		echo '<div class="rk-form-section"><h4>Identity</h4>';
		$this->row_open( 'Key', 'rk_tslug', true );
		echo '<input type="text" id="rk_tslug" name="slug" value="' . esc_attr( $v( 'slug' ) ) . '" ' . ( $edit ? 'readonly' : '' ) . ' required maxlength="32" placeholder="genre" />'; $this->row_close();
		$this->row_open( 'Singular', 'rk_ts' ); echo '<input type="text" id="rk_ts" name="singular" value="' . esc_attr( $v( 'singular' ) ) . '" placeholder="Genre" />'; $this->row_close();
		$this->row_open( 'Plural', 'rk_tp' ); echo '<input type="text" id="rk_tp" name="plural" value="' . esc_attr( $v( 'plural' ) ) . '" placeholder="Genres" />'; $this->row_close();
		echo '</div>';
		$objs = $edit && isset( $edit['object_types'] ) ? $edit['object_types'] : array();
		echo '<div class="rk-form-section"><h4>Attach to</h4>';
		$this->row_open( 'Post types' );
		echo '<div class="rk-checks">';
		foreach ( $this->all_ui_post_types() as $slug => $label ) {
			echo '<label class="rk-check"><input type="checkbox" name="object_types[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $objs, true ), true, false ) . ' /> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>'; $this->row_close();
		echo '</div>';
		echo '<div class="rk-form-section"><h4>Behaviour</h4>';
		$this->row_open( 'Options' );
		echo '<div class="rk-checks rk-checks-stack">';
		echo '<label class="rk-check"><input type="checkbox" name="hierarchical" value="1" ' . checked( ! empty( $v( 'hierarchical', 1 ) ), true, false ) . ' /> Hierarchical (like Categories)</label>';
		echo '<label class="rk-check"><input type="checkbox" name="public" value="1" ' . checked( ! empty( $v( 'public', 1 ) ), true, false ) . ' /> Public</label>';
		echo '<label class="rk-check"><input type="checkbox" name="show_in_rest" value="1" ' . checked( ! empty( $v( 'show_in_rest', 1 ) ), true, false ) . ' /> Show in REST</label>';
		echo '</div>'; $this->row_close();
		echo '</div>';
		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><a class="button rk-savebar-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a><span class="rk-savebar-spacer"></span>';
		submit_button( $edit ? 'Update taxonomy' : 'Create taxonomy', 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">Cancel</a></div></div>';
		echo '</form></div>';
		$this->foot();
	}

	public function do_save_tax() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_save_tax' );
		$this->after( RK_Taxonomy_Builder::save( wp_unslash( $_POST ) ), self::SLUG_TAX );
	}
	public function do_delete_tax() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
		check_admin_referer( 'rk_core_delete_tax_' . $slug );
		RK_Taxonomy_Builder::delete( $slug );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_TAX . '&rk_msg=deleted' ) ); exit;
	}

	/* ================= Field Groups ================= */

	public function screen_fields() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$edit = isset( $_GET['edit'] ) ? RK_Field_Engine::get_group( sanitize_key( $_GET['edit'] ) ) : null;
		if ( ! isset( $_GET['edit'] ) && ! isset( $_GET['add'] ) ) {
			$this->head( self::SLUG_FLD );
			$add = admin_url( 'admin.php?page=' . self::SLUG_FLD . '&add=1' );
			echo '<div class="pk-panel">';
			$this->page_head( 'Meta boxes', $add, 'Add Meta Box' );
			$all = RK_Field_Engine::all_groups();
			if ( empty( $all ) ) {
				echo '<div class="rk-empty"><div class="rk-empty-icon"><span class="dashicons dashicons-list-view"></span></div><p class="rk-empty-title">No meta boxes yet</p><p class="rk-empty-sub">Meta boxes attach custom fields to your post types.</p><a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Field Group</a></div>';
			} else {
				echo '<table class="widefat striped rk-list"><thead><tr><th>Group</th><th>Fields</th><th>Post types</th><th class="rk-right">Actions</th></tr></thead><tbody>';
				foreach ( $all as $g ) {
					$e = admin_url( 'admin.php?page=' . self::SLUG_FLD . '&edit=' . $g['id'] );
					$d = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_delete_group&id=' . $g['id'] ), 'rk_core_delete_group_' . $g['id'] );
					$pills = ''; foreach ( $g['post_types'] as $o ) { $pills .= '<span class="rk-type-pill">' . esc_html( $o ) . '</span> '; }
					echo '<tr><td><strong>' . esc_html( $g['title'] ) . '</strong></td><td>' . count( $g['fields'] ) . '</td><td>' . $pills . '</td><td class="rk-right"><a href="' . esc_url( $e ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $d ) . '" class="rk-danger">Delete</a></td></tr>';
				}
				echo '</tbody></table>';
			}
			echo '</div>'; $this->foot(); return;
		}

		$this->head( self::SLUG_FLD );
		$back = admin_url( 'admin.php?page=' . self::SLUG_FLD );
		$types = RK_Core_Field_Types::registry();
		$gid = $edit ? $edit['id'] : ''; $title = $edit ? $edit['title'] : '';
		$gpts = $edit ? $edit['post_types'] : array(); $gflds = $edit ? $edit['fields'] : array();
		echo '<div class="pk-panel">';
		echo '<h3>' . ( $edit ? 'Edit meta box — ' . esc_html( $title ) : 'New meta box' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form" style="max-width:none;">';
		echo '<input type="hidden" name="action" value="rk_core_save_group" /><input type="hidden" name="id" value="' . esc_attr( $gid ) . '" />';
		wp_nonce_field( 'rk_core_save_group' );
		echo '<div class="rk-form-section"><h4>Group</h4>';
		$this->row_open( 'Title', 'rk_gtitle', true ); echo '<input type="text" id="rk_gtitle" name="title" value="' . esc_attr( $title ) . '" required />'; $this->row_close();
		$this->row_open( 'Show on post types' );
		echo '<div class="rk-checks">';
		foreach ( $this->all_ui_post_types() as $slug => $label ) {
			echo '<label class="rk-check"><input type="checkbox" name="post_types[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $gpts, true ), true, false ) . ' /> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>'; $this->row_close();
		echo '</div>';
		echo '<div class="rk-form-section"><h4>Fields</h4>';
		echo '<p class="rk-form-hint" style="margin:0 0 14px;">Add fields below. Click a card to expand its settings. Drag the handle to reorder.</p>';
		echo '<div class="rk-fcards" id="rk-fcards">';
		$rows = ! empty( $gflds ) ? $gflds : array();
		$i = 0; foreach ( $rows as $f ) { $this->field_card( $i, $f, $types ); $i++; }
		echo '</div>';
		echo '<p class="rk-fcards-empty" id="rk-fcards-empty"' . ( empty( $rows ) ? '' : ' hidden' ) . '>No fields yet. Click <strong>Add Field</strong> to create one.</p>';
		echo '<p><button type="button" class="button button-primary" id="rk-add-field">+ Add Field</button></p>';
		echo '</div>';
		echo '<script type="text/html" id="rk-fieldrow-tpl">'; $this->field_card( '__i__', array(), $types ); echo '</script>';
		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><a class="button rk-savebar-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a><span class="rk-savebar-spacer"></span>';
		submit_button( $edit ? 'Update meta box' : 'Create meta box', 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">Cancel</a></div></div>';
		echo '</form></div>';
		$this->foot();
	}

	private function field_card( $i, $f, $types ) {
		$key = isset( $f['key'] ) ? $f['key'] : ''; $label = isset( $f['label'] ) ? $f['label'] : '';
		$type = isset( $f['type'] ) ? $f['type'] : 'text'; $instr = isset( $f['instructions'] ) ? $f['instructions'] : '';
		$choices = isset( $f['choices'] ) ? $f['choices'] : ''; $ptype = isset( $f['post_type'] ) ? $f['post_type'] : '';
		$cond = '';
		if ( ! empty( $f['condition'] ) && is_array( $f['condition'] ) ) { $cond = $f['condition']['field'] . ' ' . $f['condition']['op'] . ' ' . $f['condition']['value']; }
		$subtext = '';
		if ( isset( $f['subfields'] ) && is_array( $f['subfields'] ) ) {
			$lines = array(); foreach ( $f['subfields'] as $sf ) { $lines[] = $sf['key'] . ':' . ( isset( $sf['label'] ) ? $sf['label'] : $sf['key'] ) . ':' . ( isset( $sf['type'] ) ? $sf['type'] : 'text' ); }
			$subtext = implode( "\n", $lines );
		}
		$b = 'fields[' . $i . ']';
		$title = '' !== $label ? $label : 'New field';

		echo '<div class="rk-fcard" data-index="' . esc_attr( $i ) . '">';
		echo '<div class="rk-fcard-head">';
		echo '<span class="rk-fdrag dashicons dashicons-menu" title="Drag to reorder"></span>';
		echo '<button type="button" class="rk-fcard-toggle"><span class="rk-fcaret dashicons dashicons-arrow-right-alt2"></span></button>';
		echo '<span class="rk-fcard-titles"><span class="rk-fcard-title">' . esc_html( $title ) . '</span> <code class="rk-fcard-key">' . esc_html( '' !== $key ? $key : 'field_key' ) . '</code> <span class="rk-fcard-type">' . esc_html( $type ) . '</span></span>';
		echo '<span class="rk-fcard-actions"><button type="button" class="rk-fdup dashicons dashicons-admin-page" title="Duplicate"></button><button type="button" class="rk-fdel dashicons dashicons-trash" title="Delete"></button></span>';
		echo '</div>';

		echo '<div class="rk-fcard-body" hidden>';
		echo '<div class="rk-fcard-grid">';
		echo '<label class="rk-fc"><span>Label</span><input type="text" class="rk-fc-label" name="' . esc_attr( $b ) . '[label]" value="' . esc_attr( $label ) . '" placeholder="Field label" /></label>';
		echo '<label class="rk-fc"><span>Key</span><input type="text" class="rk-fc-key" name="' . esc_attr( $b ) . '[key]" value="' . esc_attr( $key ) . '" placeholder="field_key" /></label>';
		echo '<label class="rk-fc"><span>Type</span><select class="rk-fc-type" name="' . esc_attr( $b ) . '[type]">';
		foreach ( $types as $tkey => $tdef ) { echo '<option value="' . esc_attr( $tkey ) . '" ' . selected( $type, $tkey, false ) . '>' . esc_html( $tdef['label'] ) . '</option>'; }
		echo '</select></label>';
		echo '<label class="rk-fc"><span>Instructions</span><input type="text" name="' . esc_attr( $b ) . '[instructions]" value="' . esc_attr( $instr ) . '" placeholder="Optional help text" /></label>';
		echo '<label class="rk-fc rk-fc-wide"><span>Choices <em>(select / radio / checklist — <code>value:Label</code> per line)</em></span><textarea name="' . esc_attr( $b ) . '[choices]" rows="3" placeholder="pro:Pro&#10;free:Free">' . esc_textarea( $choices ) . '</textarea></label>';
		echo '<label class="rk-fc"><span>Relation post type</span><input type="text" name="' . esc_attr( $b ) . '[post_type]" value="' . esc_attr( $ptype ) . '" placeholder="e.g. book" /></label>';
		echo '<label class="rk-fc"><span>Show if <em>(e.g. <code>plan == pro</code>)</em></span><input type="text" name="' . esc_attr( $b ) . '[condition_text]" value="' . esc_attr( $cond ) . '" placeholder="otherfield == value" /></label>';
		echo '<label class="rk-fc rk-fc-wide"><span>Repeater sub-fields <em>(<code>key:Label:text</code> per line)</em></span><textarea name="' . esc_attr( $b ) . '[subfields_text]" rows="2" placeholder="title:Title:text&#10;url:URL:text">' . esc_textarea( $subtext ) . '</textarea></label>';
		echo '</div></div>';
		echo '</div>';
	}

	public function do_save_group() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_save_group' );
		$input = wp_unslash( $_POST );
		if ( isset( $input['fields'] ) && is_array( $input['fields'] ) ) { $input['fields'] = array_values( $input['fields'] ); }
		$this->after( RK_Field_Engine::save_group( $input ), self::SLUG_FLD );
	}
	public function do_delete_group() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? sanitize_key( $_GET['id'] ) : '';
		check_admin_referer( 'rk_core_delete_group_' . $id );
		RK_Field_Engine::delete_group( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_FLD . '&rk_msg=deleted' ) ); exit;
	}

	/* ================= Relations ================= */

	public function screen_relations() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_GET['edit'] ) ) { $this->rel_form( RK_Relations::get( sanitize_key( $_GET['edit'] ) ) ); return; }
		if ( isset( $_GET['add'] ) )  { $this->rel_form( null ); return; }
		$this->head( self::SLUG_REL );
		$add = admin_url( 'admin.php?page=' . self::SLUG_REL . '&add=1' );
		echo '<div class="pk-panel">';
		$this->page_head( 'Relations', $add, 'Add Relation' );
		$all = RK_Relations::all();
		if ( empty( $all ) ) {
			echo '<div class="rk-empty"><div class="rk-empty-icon"><span class="dashicons dashicons-networking"></span></div><p class="rk-empty-title">No relations yet</p><p class="rk-empty-sub">Relations link posts to posts or users (one-to-one, one-to-many, many-to-many).</p><a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Relation</a></div>';
		} else {
			echo '<table class="widefat striped rk-list"><thead><tr><th>Name</th><th>From</th><th>To</th><th>Type</th><th class="rk-right">Actions</th></tr></thead><tbody>';
			foreach ( $all as $r ) {
				$e = admin_url( 'admin.php?page=' . self::SLUG_REL . '&edit=' . $r['id'] );
				$d = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_delete_rel&id=' . $r['id'] ), 'rk_core_delete_rel_' . $r['id'] );
				echo '<tr><td><strong>' . esc_html( $r['name'] ) . '</strong></td><td><code>' . esc_html( $r['from_object'] ) . '</code></td><td><code>' . esc_html( $r['to_object'] ) . '</code></td><td>' . esc_html( str_replace( '_', '-', $r['rel_type'] ) ) . '</td><td class="rk-right"><a href="' . esc_url( $e ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $d ) . '" class="rk-danger">Delete</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>'; $this->foot();
	}

	private function rel_form( $edit ) {
		$this->head( self::SLUG_REL );
		$back = admin_url( 'admin.php?page=' . self::SLUG_REL );
		$objs = RK_Relations::object_choices();
		echo '<div class="pk-panel"><h3>' . ( $edit ? 'Edit relation — ' . esc_html( $edit['name'] ) : 'Add relation' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form">';
		echo '<input type="hidden" name="action" value="rk_core_save_rel" /><input type="hidden" name="id" value="' . esc_attr( $edit ? $edit['id'] : '' ) . '" />';
		wp_nonce_field( 'rk_core_save_rel' );
		$v = function ( $k, $d = '' ) use ( $edit ) { return $edit && isset( $edit[ $k ] ) ? $edit[ $k ] : $d; };
		echo '<div class="rk-form-section"><h4>Relation</h4>';
		$this->row_open( 'Name', 'rk_rname', true ); echo '<input type="text" id="rk_rname" name="name" value="' . esc_attr( $v( 'name' ) ) . '" required placeholder="Projects to Team" />'; $this->row_close();
		$this->row_open( 'From (parent)' );
		echo '<select name="from_object">'; foreach ( $objs as $k => $lab ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $v( 'from_object', 'post' ), $k, false ) . '>' . esc_html( $lab ) . '</option>'; } echo '</select>';
		$this->row_close();
		$this->row_open( 'To (child)' );
		echo '<select name="to_object">'; foreach ( $objs as $k => $lab ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $v( 'to_object', 'post' ), $k, false ) . '>' . esc_html( $lab ) . '</option>'; } echo '</select>';
		$this->row_close();
		$this->row_open( 'Type' );
		$rt = $v( 'rel_type', 'many_to_many' );
		echo '<select name="rel_type">';
		foreach ( array( 'one_to_one' => 'One to one', 'one_to_many' => 'One to many', 'many_to_many' => 'Many to many' ) as $k => $lab ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $rt, $k, false ) . '>' . esc_html( $lab ) . '</option>'; }
		echo '</select>';
		$this->row_close( 'A linking box appears on the parent post editor.' );
		echo '</div>';
		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><a class="button rk-savebar-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a><span class="rk-savebar-spacer"></span>';
		submit_button( $edit ? 'Update relation' : 'Create relation', 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">Cancel</a></div></div>';
		echo '</form></div>';
		$this->foot();
	}

	public function do_save_rel() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_save_rel' );
		$this->after( RK_Relations::save( wp_unslash( $_POST ) ), self::SLUG_REL );
	}
	public function do_delete_rel() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? sanitize_key( $_GET['id'] ) : '';
		check_admin_referer( 'rk_core_delete_rel_' . $id );
		RK_Relations::delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_REL . '&rk_msg=deleted' ) ); exit;
	}

	/* ================= Queries ================= */

	public function screen_queries() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_GET['edit'] ) ) { $this->qry_form( RK_Query_Builder::get( sanitize_key( $_GET['edit'] ) ) ); return; }
		if ( isset( $_GET['add'] ) )  { $this->qry_form( null ); return; }
		$this->head( self::SLUG_QRY );
		$add = admin_url( 'admin.php?page=' . self::SLUG_QRY . '&add=1' );
		echo '<div class="pk-panel">';
		$this->page_head( 'Queries', $add, 'Add Query' );
		$all = RK_Query_Builder::all();
		if ( empty( $all ) ) {
			echo '<div class="rk-empty"><div class="rk-empty-icon"><span class="dashicons dashicons-filter"></span></div><p class="rk-empty-title">No queries yet</p><p class="rk-empty-sub">Build reusable queries (posts, terms, users) that widgets can consume.</p><a class="button button-primary" href="' . esc_url( $add ) . '">+ Add Query</a></div>';
		} else {
			echo '<table class="widefat striped rk-list"><thead><tr><th>Name</th><th>Source</th><th>Target</th><th>Number</th><th class="rk-right">Actions</th></tr></thead><tbody>';
			foreach ( $all as $q ) {
				$e = admin_url( 'admin.php?page=' . self::SLUG_QRY . '&edit=' . $q['id'] );
				$d = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_delete_qry&id=' . $q['id'] ), 'rk_core_delete_qry_' . $q['id'] );
				$target = 'posts' === $q['source'] ? $q['post_type'] : ( 'terms' === $q['source'] ? $q['taxonomy'] : 'users' );
				echo '<tr><td><strong>' . esc_html( $q['name'] ) . '</strong><br><span class="rk-sub">ID: ' . esc_html( $q['id'] ) . '</span></td><td>' . esc_html( $q['source'] ) . '</td><td><code>' . esc_html( $target ) . '</code></td><td>' . intval( $q['number'] ) . '</td><td class="rk-right"><a href="' . esc_url( $e ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $d ) . '" class="rk-danger">Delete</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>'; $this->foot();
	}

	private function qry_form( $edit ) {
		$this->head( self::SLUG_QRY );
		$back = admin_url( 'admin.php?page=' . self::SLUG_QRY );
		echo '<div class="pk-panel"><h3>' . ( $edit ? 'Edit query — ' . esc_html( $edit['name'] ) : 'Add query' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form">';
		echo '<input type="hidden" name="action" value="rk_core_save_qry" /><input type="hidden" name="id" value="' . esc_attr( $edit ? $edit['id'] : '' ) . '" />';
		wp_nonce_field( 'rk_core_save_qry' );
		$v = function ( $k, $d = '' ) use ( $edit ) { return $edit && isset( $edit[ $k ] ) ? $edit[ $k ] : $d; };
		echo '<div class="rk-form-section"><h4>Query</h4>';
		$this->row_open( 'Name', 'rk_qname', true ); echo '<input type="text" id="rk_qname" name="name" value="' . esc_attr( $v( 'name' ) ) . '" required placeholder="Latest books" />'; $this->row_close();
		$this->row_open( 'Source' );
		$src = $v( 'source', 'posts' );
		echo '<select name="source">'; foreach ( array( 'posts' => 'Posts', 'terms' => 'Terms', 'users' => 'Users' ) as $k => $lab ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $src, $k, false ) . '>' . esc_html( $lab ) . '</option>'; } echo '</select>';
		$this->row_close();
		$this->row_open( 'Post type' ); echo '<input type="text" name="post_type" value="' . esc_attr( $v( 'post_type', 'post' ) ) . '" placeholder="post" />'; $this->row_close( 'Used when source is Posts.' );
		$this->row_open( 'Taxonomy' ); echo '<input type="text" name="taxonomy" value="' . esc_attr( $v( 'taxonomy' ) ) . '" placeholder="category" />'; $this->row_close( 'Terms source, or Posts tax filter.' );
		$this->row_open( 'Term slug' ); echo '<input type="text" name="term" value="' . esc_attr( $v( 'term' ) ) . '" placeholder="(optional)" />'; $this->row_close();
		echo '</div>';
		echo '<div class="rk-form-section"><h4>Filter &amp; order</h4>';
		$this->row_open( 'Meta key' ); echo '<input type="text" name="meta_key" value="' . esc_attr( $v( 'meta_key' ) ) . '" />'; $this->row_close();
		$this->row_open( 'Meta value' ); echo '<input type="text" name="meta_value" value="' . esc_attr( $v( 'meta_value' ) ) . '" />'; $this->row_close();
		$this->row_open( 'Number' ); echo '<input type="number" name="number" value="' . esc_attr( $v( 'number', 10 ) ) . '" min="1" style="max-width:120px;" />'; $this->row_close();
		$this->row_open( 'Order by' );
		echo '<select name="orderby">'; foreach ( array( 'date' => 'Date', 'title' => 'Title', 'menu_order' => 'Menu order', 'rand' => 'Random', 'meta_value' => 'Meta value' ) as $k => $lab ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $v( 'orderby', 'date' ), $k, false ) . '>' . esc_html( $lab ) . '</option>'; } echo '</select> ';
		echo '<select name="order">'; foreach ( array( 'DESC' => 'Descending', 'ASC' => 'Ascending' ) as $k => $lab ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $v( 'order', 'DESC' ), $k, false ) . '>' . esc_html( $lab ) . '</option>'; } echo '</select>';
		$this->row_close();
		echo '</div>';
		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><a class="button rk-savebar-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span> Back</a><span class="rk-savebar-spacer"></span>';
		submit_button( $edit ? 'Update query' : 'Create query', 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">Cancel</a></div></div>';
		echo '</form></div>';
		$this->foot();
	}

	public function do_save_qry() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_save_qry' );
		$this->after( RK_Query_Builder::save( wp_unslash( $_POST ) ), self::SLUG_QRY );
	}
	public function do_delete_qry() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? sanitize_key( $_GET['id'] ) : '';
		check_admin_referer( 'rk_core_delete_qry_' . $id );
		RK_Query_Builder::delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_QRY . '&rk_msg=deleted' ) ); exit;
	}

	/* ================= Site Settings ================= */

	public function screen_site() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$this->head( self::SLUG_SITE );
		$has_el = class_exists( '\Elementor\Plugin' );
		$kit    = $this->kit_settings();
		$logo   = (int) get_theme_mod( 'custom_logo' );
		$icon   = (int) get_option( 'site_icon' );

		echo '<div class="pk-panel"><h3>Site Settings</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-form" style="max-width:820px;">';
		echo '<input type="hidden" name="action" value="rk_core_save_site" />';
		wp_nonce_field( 'rk_core_save_site' );

		echo '<div class="rk-form-section"><h4>Site Identity</h4>';
		$this->row_open( 'Site title', 'rk_blogname' ); echo '<input type="text" id="rk_blogname" name="blogname" value="' . esc_attr( get_option( 'blogname' ) ) . '" />'; $this->row_close();
		$this->row_open( 'Tagline', 'rk_tagline' ); echo '<input type="text" id="rk_tagline" name="blogdescription" value="' . esc_attr( get_option( 'blogdescription' ) ) . '" />'; $this->row_close();
		$this->row_open( 'Logo' ); echo $this->media_field( 'custom_logo', $logo, 'Choose logo' ); $this->row_close( 'Used by the Site Logo widget in Elementor headers.' );
		$this->row_open( 'Site icon' ); echo $this->media_field( 'site_icon', $icon, 'Choose icon' ); $this->row_close( 'The browser tab favicon.' );
		echo '</div>';

		if ( $has_el && ! empty( $kit ) ) {
			echo '<div class="rk-form-section"><h4>Global Colors</h4>';
			foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
				if ( empty( $kit[ $bucket ] ) || ! is_array( $kit[ $bucket ] ) ) { continue; }
				foreach ( $kit[ $bucket ] as $c ) {
					if ( empty( $c['_id'] ) ) { continue; }
					$label = isset( $c['title'] ) ? $c['title'] : $c['_id'];
					$val   = isset( $c['color'] ) ? $c['color'] : '';
					$hex   = preg_match( '/^#[0-9a-fA-F]{6}$/', $val ) ? $val : '#000000';
					$this->row_open( $label );
					echo '<span class="rk-colorwrap"><input type="color" class="rk-colorpick" value="' . esc_attr( $hex ) . '" /><input type="text" class="rk-colortext" name="colors[' . esc_attr( $bucket ) . '][' . esc_attr( $c['_id'] ) . ']" value="' . esc_attr( $val ) . '" style="width:140px;" /></span>';
					$this->row_close();
				}
			}
			echo '</div>';

			echo '<div class="rk-form-section"><h4>Global Fonts</h4>';
			foreach ( array( 'system_typography', 'custom_typography' ) as $bucket ) {
				if ( empty( $kit[ $bucket ] ) || ! is_array( $kit[ $bucket ] ) ) { continue; }
				foreach ( $kit[ $bucket ] as $t ) {
					if ( empty( $t['_id'] ) ) { continue; }
					$label = isset( $t['title'] ) ? $t['title'] : $t['_id'];
					$val   = isset( $t['typography_font_family'] ) ? $t['typography_font_family'] : '';
					$this->row_open( $label );
					echo '<input type="text" name="fonts[' . esc_attr( $bucket ) . '][' . esc_attr( $t['_id'] ) . ']" value="' . esc_attr( $val ) . '" placeholder="e.g. Playfair Display" />';
					$this->row_close();
				}
			}
			echo '</div>';

			echo '<div class="rk-form-section"><h4>Layout</h4>';
			$cw = isset( $kit['container_width']['size'] ) ? (int) $kit['container_width']['size'] : 1140;
			$br = isset( $kit['button_border_radius']['top'] ) ? (int) $kit['button_border_radius']['top'] : 0;
			$this->row_open( 'Content width (px)' ); echo '<input type="number" name="container_width" value="' . esc_attr( $cw ) . '" style="max-width:120px;" />'; $this->row_close();
			$this->row_open( 'Button radius (px)' ); echo '<input type="number" name="button_radius" value="' . esc_attr( $br ) . '" style="max-width:120px;" />'; $this->row_close();
			echo '</div>';
		} else {
			echo '<div class="rk-form-section"><p class="description">Elementor is not active, so global colours, fonts and layout are unavailable. Site Identity above still applies.</p></div>';
		}

		echo '<div class="rk-savebar"><div class="rk-savebar-inner"><span class="rk-savebar-spacer"></span>';
		submit_button( 'Save site settings', 'primary', 'submit', false );
		echo '</div></div></form></div>';
		$this->foot();
	}

	private function media_field( $name, $id, $label ) {
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		$out  = '<div class="rk-mediawrap">';
		$out .= '<input type="hidden" class="rk-media-id" name="' . esc_attr( $name ) . '" value="' . esc_attr( $id ) . '" />';
		$out .= '<div class="rk-media-preview">' . ( $src ? '<img src="' . esc_url( $src ) . '" />' : '' ) . '</div>';
		$out .= '<button type="button" class="button rk-media-pick">' . esc_html( $label ) . '</button> <button type="button" class="button rk-media-clear">Remove</button>';
		$out .= '</div>';
		return $out;
	}

	public function do_save_site() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_save_site' );
		update_option( 'blogname', sanitize_text_field( isset( $_POST['blogname'] ) ? wp_unslash( $_POST['blogname'] ) : '' ) );
		update_option( 'blogdescription', sanitize_text_field( isset( $_POST['blogdescription'] ) ? wp_unslash( $_POST['blogdescription'] ) : '' ) );
		$logo = isset( $_POST['custom_logo'] ) ? (int) $_POST['custom_logo'] : 0;
		if ( $logo ) { set_theme_mod( 'custom_logo', $logo ); } else { remove_theme_mod( 'custom_logo' ); }
		$icon = isset( $_POST['site_icon'] ) ? (int) $_POST['site_icon'] : 0;
		if ( $icon ) { update_option( 'site_icon', $icon ); } else { delete_option( 'site_icon' ); }

		$kit_id = $this->active_kit_id();
		if ( $kit_id && class_exists( '\Elementor\Plugin' ) ) {
			$ks = $this->kit_settings();
			$colors = ( isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ) ? wp_unslash( $_POST['colors'] ) : array();
			foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
				if ( empty( $ks[ $bucket ] ) || empty( $colors[ $bucket ] ) ) { continue; }
				foreach ( $ks[ $bucket ] as &$c ) {
					if ( isset( $c['_id'] ) && isset( $colors[ $bucket ][ $c['_id'] ] ) ) { $c['color'] = $this->san_color( $colors[ $bucket ][ $c['_id'] ] ); }
				}
				unset( $c );
			}
			$fonts = ( isset( $_POST['fonts'] ) && is_array( $_POST['fonts'] ) ) ? wp_unslash( $_POST['fonts'] ) : array();
			foreach ( array( 'system_typography', 'custom_typography' ) as $bucket ) {
				if ( empty( $ks[ $bucket ] ) || empty( $fonts[ $bucket ] ) ) { continue; }
				foreach ( $ks[ $bucket ] as &$t ) {
					if ( isset( $t['_id'] ) && isset( $fonts[ $bucket ][ $t['_id'] ] ) ) { $t['typography_font_family'] = sanitize_text_field( $fonts[ $bucket ][ $t['_id'] ] ); }
				}
				unset( $t );
			}
			if ( isset( $_POST['container_width'] ) ) { $ks['container_width'] = array( 'unit' => 'px', 'size' => (int) $_POST['container_width'] ); }
			if ( isset( $_POST['button_radius'] ) ) { $r = (string) (int) $_POST['button_radius']; $ks['button_border_radius'] = array( 'unit' => 'px', 'top' => $r, 'right' => $r, 'bottom' => $r, 'left' => $r, 'isLinked' => true ); }
			update_post_meta( $kit_id, '_elementor_page_settings', $ks );
			$this->regen_kit_css( $kit_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_SITE . '&rk_msg=saved' ) ); exit;
	}

	private function active_kit_id() { return (int) get_option( 'elementor_active_kit' ); }
	private function kit_settings() { $id = $this->active_kit_id(); if ( ! $id ) { return array(); } $s = get_post_meta( $id, '_elementor_page_settings', true ); return is_array( $s ) ? $s : array(); }
	private function san_color( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v ) ) { return $v; }
		if ( preg_match( '/^rgba?\([\d.,\s]+\)$/i', $v ) ) { return $v; }
		return '';
	}
	private function regen_kit_css( $kit_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) { return; }
		try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
		try { if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) { \Elementor\Core\Files\CSS\Post::create( $kit_id )->update(); } } catch ( \Throwable $e ) {}
	}


	/* ================= Import / Export ================= */

	public function screen_tools() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$this->head( self::SLUG_IE );
		$cpts     = class_exists( 'RK_CPT_Builder' ) ? RK_CPT_Builder::all() : array();
		$starters = RK_Core_Porter::starters();
		$prompts  = $this->ai_prompts();

		echo '<div class="rk-tools-grid">';

		/* Export */
		echo '<div class="rk-card rk-tool-card">';
		echo '<div class="rk-card-head"><span class="dashicons dashicons-download"></span><div><h3>Export model</h3><p class="rk-muted">Download post types with their taxonomies, meta boxes, relations &amp; queries.</p></div></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-tool-body">';
		echo '<input type="hidden" name="action" value="rk_core_export" />';
		wp_nonce_field( 'rk_core_export' );
		echo '<label class="rk-radio"><input type="radio" name="scope" value="all" checked /> Everything</label>';
		if ( $cpts ) {
			echo '<label class="rk-radio"><input type="radio" name="scope" value="pick" /> Selected post types only</label>';
			echo '<div class="rk-check-list">';
			foreach ( $cpts as $c ) {
				echo '<label class="rk-check"><input type="checkbox" name="slugs[]" value="' . esc_attr( $c['slug'] ) . '" /> ' . esc_html( isset( $c['plural'] ) ? $c['plural'] : $c['slug'] ) . ' <code>' . esc_html( $c['slug'] ) . '</code></label>';
			}
			echo '</div>';
		} else {
			echo '<p class="rk-muted">No post types yet — “Everything” exports an empty model you can use as a template.</p>';
		}
		echo '<label class="rk-check" style="margin-top:6px;"><input type="checkbox" name="include_posts" value="1" /> <strong>Include published post content</strong> — posts, meta &amp; featured images (larger file)</label>';
		if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() ) {
			$je = RK_Core_JetEngine::list_post_types();
			if ( $je ) {
				echo '<div class="rk-je-block"><p class="rk-je-head"><span class="dashicons dashicons-migrate"></span> JetEngine post types <span class="rk-badge rk-badge-free">detected</span></p>';
				echo '<p class="rk-muted" style="margin:0 0 8px;">Selected JetEngine types are mapped into the RK Core schema on export, so they import back natively.</p>';
				echo '<div class="rk-check-list">';
				foreach ( $je as $j ) {
					echo '<label class="rk-check"><input type="checkbox" name="jet_slugs[]" value="' . esc_attr( $j['slug'] ) . '" /> <span class="rk-je-tag">JetEngine</span> ' . esc_html( $j['label'] ) . ' <code>' . esc_html( $j['slug'] ) . '</code></label>';
				}
				echo '</div>';
				$dbg = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_jet_debug' ), 'rk_core_jet_debug' );
				echo '<p style="margin:8px 0 0;"><a class="rk-muted" href="' . esc_url( $dbg ) . '" style="font-size:12px;">Not mapping right? Download raw JetEngine field data (debug)</a></p>';
				echo '</div>';
			}
		}
		if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() ) {
			$jl = RK_Core_JetEngine::list_listings();
			echo '<div class="rk-je-block"><p class="rk-je-head"><span class="dashicons dashicons-layout"></span> JetEngine listing templates</p>';
			if ( $jl ) {
				echo '<div class="rk-check-list">';
				foreach ( $jl as $l ) {
					echo '<label class="rk-check"><input type="checkbox" name="jet_listing_ids[]" value="' . (int) $l['id'] . '" /> <span class="rk-je-tag">Listing</span> ' . esc_html( $l['title'] ) . ' <code>' . esc_html( $l['source'] ) . '</code></label>';
				}
				echo '</div>';
			} else {
				echo '<p class="rk-muted" style="margin:0 0 6px;">No JetEngine listing templates found.</p>';
			}
			echo '<label class="rk-check" style="margin-top:6px;"><input type="checkbox" name="include_blueprints" value="1" /> Include 4 example blueprints (Meta / Repeater / Relationship / Query)</label>';
			echo '</div>';
		}
		echo '<button class="button button-primary">Download .json</button>';
		echo '</form></div>';

		/* Import */
		echo '<div class="rk-card rk-tool-card">';
		echo '<div class="rk-card-head"><span class="dashicons dashicons-upload"></span><div><h3>Import model</h3><p class="rk-muted">Upload an RK Core model file. Matching items are updated; new ones are added.</p></div></div>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-tool-body">';
		echo '<input type="hidden" name="action" value="rk_core_import" />';
		wp_nonce_field( 'rk_core_import' );
		echo '<input type="file" name="model" accept="application/json,.json" required />';
		echo '<button class="button button-primary">Import</button>';
		echo '</form></div>';

		echo '</div>'; // /grid

		/* JetEngine listings management */
		if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() ) {
			$jl = RK_Core_JetEngine::list_listings();
			if ( $jl ) {
				echo '<div class="rk-page-head"><h3>JetEngine listing templates</h3></div>';
				echo '<p class="rk-muted rk-mb">Manage existing JetEngine listing templates. Export them above; delete here.</p>';
				echo '<table class="widefat striped" style="max-width:720px;"><thead><tr><th>Listing</th><th>Source</th><th></th></tr></thead><tbody>';
				foreach ( $jl as $l ) {
					$del = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_jet_listing_delete&id=' . (int) $l['id'] ), 'rk_core_jet_listing_delete_' . (int) $l['id'] );
					echo '<tr><td><strong>' . esc_html( $l['title'] ) . '</strong></td><td><code>' . esc_html( $l['source'] ) . '</code></td><td class="rk-right"><a class="rk-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete this listing template?\')">Delete</a></td></tr>';
				}
				echo '</tbody></table>';
			}
		}

		/* Starters */
		echo '<div class="rk-page-head"><h3>Pre-built starters</h3></div>';
		echo '<p class="rk-muted rk-mb">One click installs a ready-made post type with its taxonomy and detail fields. Safe to run — it merges, never wipes.</p>';
		echo '<div class="rk-starter-grid">';
		foreach ( $starters as $id => $st ) {
			echo '<div class="rk-card rk-starter-card">';
			echo '<div class="rk-starter-ico"><span class="dashicons ' . esc_attr( $st['icon'] ) . '"></span></div>';
			echo '<h4>' . esc_html( $st['name'] ) . '</h4>';
			echo '<p class="rk-muted">' . esc_html( $st['desc'] ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="rk_core_starter" />';
			echo '<input type="hidden" name="starter" value="' . esc_attr( $id ) . '" />';
			wp_nonce_field( 'rk_core_starter_' . $id );
			echo '<button class="button">Install</button>';
			echo '</form></div>';
		}
		echo '</div>';

		/* AI prompts */
		echo '<div class="rk-page-head"><h3>AI prompt templates</h3></div>';
		echo '<p class="rk-muted rk-mb">Copy a prompt, paste it into any AI (ChatGPT, Claude, Gemini), fill in your requirement, and <strong>attach the example file below</strong> so the AI returns a ready-to-import model file.</p>';
		echo '<p class="rk-mb"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_example' ), 'rk_core_example' ) ) . '"><span class="dashicons dashicons-media-code" style="vertical-align:middle"></span> Download example model file</a></p>';
		echo '<div class="rk-prompt-grid">';
		$i = 0;
		foreach ( $prompts as $p ) {
			$pid = 'rkp' . ( $i++ );
			echo '<div class="rk-card rk-prompt-card">';
			echo '<div class="rk-prompt-head"><h4>' . esc_html( $p['title'] ) . '</h4><button type="button" class="button rk-copy" data-copy="#' . esc_attr( $pid ) . '">Copy</button></div>';
			echo '<p class="rk-muted">' . esc_html( $p['desc'] ) . '</p>';
			echo '<textarea id="' . esc_attr( $pid ) . '" class="rk-prompt-text" readonly rows="8">' . esc_textarea( $p['body'] ) . '</textarea>';
			echo '</div>';
		}
		echo '</div>';

		$this->foot();
	}

	/** Copy-ready AI prompt templates. */
	private function ai_prompts() {
		$fmt = "Output ONLY a JSON file in this exact \"rk-core-model\" format (same structure as the attached example.json), no explanation:\n{\n  \"format\": \"rk-core-model\",\n  \"post_types\": [ { \"slug\":\"\", \"singular\":\"\", \"plural\":\"\", \"public\":true, \"has_archive\":true, \"show_in_rest\":true, \"supports\":[\"title\",\"editor\",\"thumbnail\"], \"menu_icon\":\"dashicons-...\" } ],\n  \"taxonomies\": [ { \"slug\":\"\", \"singular\":\"\", \"plural\":\"\", \"object_types\":[\"<post_type_slug>\"], \"hierarchical\":true, \"public\":true, \"show_in_rest\":true } ],\n  \"meta_boxes\": [ { \"id\":\"grp_x\", \"title\":\"\", \"post_types\":[\"<post_type_slug>\"], \"fields\":[ { \"key\":\"\", \"label\":\"\", \"type\":\"text\" } ] } ],\n  \"relations\": [ { \"id\":\"rel_x\", \"name\":\"\", \"from_object\":\"<post_type_a>\", \"to_object\":\"<post_type_b>\", \"rel_type\":\"many_to_many\" } ],\n  \"queries\": [ { \"id\":\"qry_x\", \"name\":\"\", \"post_type\":\"<post_type_slug>\", \"source\":\"posts\", \"number\":10, \"orderby\":\"date\", \"order\":\"DESC\" } ]\n}\nField \"type\" is one of: text, textarea, wysiwyg, number, date, time, email, url, select, radio, checkbox, switcher, color, image, gallery, relation, checklist, oembed, icon, repeater. For select/radio use \"choices\":\"value:Label\\nvalue2:Label2\".";

		return array(
			array(
				'title' => 'Post type + meta box fields',
				'desc'  => 'One post type with a list of custom fields.',
				'body'  => "I want a WordPress custom post type. Create ONE post type named [PLURAL NAME] (e.g. Properties) with these detail fields:\n- [field label] : [text/number/date/select/url/media/switcher]\n- [field label] : [type]\n(add as many as you need)\n\n" . $fmt,
			),
			array(
				'title' => 'Post type + meta box + taxonomies',
				'desc'  => 'A post type with custom fields and one or more taxonomies.',
				'body'  => "Create ONE post type named [PLURAL NAME] with:\nTaxonomies (categories to group them):\n- [taxonomy name] (hierarchical: yes/no)\n\nDetail fields:\n- [field label] : [type]\n- [field label] : [type]\n\n" . $fmt,
			),
			array(
				'title' => 'Relation between two post types',
				'desc'  => 'Two post types linked together (e.g. Agents ↔ Properties).',
				'body'  => "Create TWO post types [POST TYPE A] and [POST TYPE B], each with a few relevant detail fields, and a relation linking them:\n- Relation: [A] to [B], type: one_to_many OR many_to_many\n\n" . $fmt,
			),
			array(
				'title' => 'Custom saved queries',
				'desc'  => 'Reusable queries (e.g. featured, newest, by category).',
				'body'  => "For an existing post type with slug [POST TYPE SLUG], create these reusable saved queries:\n- [query name] : e.g. newest 6, ordered by date DESC\n- [query name] : e.g. featured only (meta_key featured = 1)\n- [query name] : e.g. by a taxonomy term\n\n" . $fmt,
			),
		);
	}

	/* -------- handlers -------- */

	/* ================= Listings (native listing templates) ================= */

	public function screen_listings() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$this->head( self::SLUG_LIST );
		$this->notice();

		echo '<div class="rk-page-head"><div><h3>Listings</h3><p class="rk-muted" style="margin:2px 0 0;">Reusable listing templates. Build the layout in Elementor, then loop it anywhere with the <strong>RK Listing</strong> widget.</p></div></div>';

		/* Add new + import */
		echo '<div class="rk-tools-grid">';

		echo '<div class="rk-card rk-tool-card"><div class="rk-card-head"><span class="dashicons dashicons-plus-alt"></span><div><h3>New listing</h3><p class="rk-muted">Create a template and choose what it loops.</p></div></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-tool-body">';
		echo '<input type="hidden" name="action" value="rk_core_listing_create" />';
		wp_nonce_field( 'rk_core_listing_create' );
		echo '<input type="text" name="title" placeholder="Listing title" required style="width:100%;" />';
		$src = '<select name="source">';
		foreach ( RK_Core_Listings::sources() as $k => $lab ) { $src .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $lab ) . '</option>'; }
		$src .= '</select>';
		echo '<p>' . $src . '</p>';
		$pts = class_exists( 'RK_CPT_Builder' ) ? RK_CPT_Builder::all() : array();
		echo '<select name="post_type"><option value="post">Posts</option><option value="page">Pages</option>';
		foreach ( $pts as $c ) { echo '<option value="' . esc_attr( $c['slug'] ) . '">' . esc_html( isset( $c['plural'] ) ? $c['plural'] : $c['slug'] ) . '</option>'; }
		echo '</select>';
		echo '<p><label>Columns <input type="number" name="columns" value="3" min="1" max="6" style="width:64px;" /></label> &nbsp; <label>Items <input type="number" name="count" value="6" min="1" style="width:72px;" /></label></p>';
		echo '<button class="button button-primary">Create &amp; edit in Elementor</button>';
		echo '</form></div>';

		echo '<div class="rk-card rk-tool-card"><div class="rk-card-head"><span class="dashicons dashicons-upload"></span><div><h3>Import listing</h3><p class="rk-muted">Upload a listing .json exported from RK Core.</p></div></div>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rk-tool-body">';
		echo '<input type="hidden" name="action" value="rk_core_listing_import" />';
		wp_nonce_field( 'rk_core_listing_import' );
		echo '<input type="file" name="listing" accept="application/json,.json" required />';
		echo '<button class="button">Import</button>';
		echo '</form></div>';

		echo '</div>'; // grid

		$rows = RK_Core_Listings::all();
		echo '<div class="rk-page-head" style="margin-top:20px;"><h3>Your listings</h3></div>';
		if ( ! $rows ) {
			echo '<div class="rk-empty"><p class="rk-empty-title">No listings yet</p><p class="rk-empty-sub">Create one above to get started.</p></div>';
			$this->foot(); return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Title</th><th>Source</th><th>Post type</th><th>Status</th><th></th></tr></thead><tbody>';
		$srcmap = RK_Core_Listings::sources();
		foreach ( $rows as $p ) {
			$s    = RK_Core_Listings::settings_of( $p->ID );
			$edit = RK_Core_Listings::edit_url( $p->ID );
			$dup  = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_listing_duplicate&id=' . $p->ID ), 'rk_core_listing_duplicate_' . $p->ID );
			$exp  = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_listing_export&id=' . $p->ID ), 'rk_core_listing_export_' . $p->ID );
			$del  = wp_nonce_url( admin_url( 'admin-post.php?action=rk_core_listing_delete&id=' . $p->ID ), 'rk_core_listing_delete_' . $p->ID );
			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $edit ) . '">' . esc_html( $p->post_title ) . '</a></strong><br><span class="rk-sub">[rk_listing id="' . (int) $p->ID . '"] &nbsp;·&nbsp; ' . (int) $s['columns'] . ' cols</span></td>';
			echo '<td>' . esc_html( isset( $srcmap[ $s['source'] ] ) ? $srcmap[ $s['source'] ] : $s['source'] ) . '</td>';
			echo '<td><code>' . esc_html( $s['post_type'] ? $s['post_type'] : '—' ) . '</code></td>';
			echo '<td><span class="rk-modstate ' . ( 'publish' === $p->post_status ? 'rk-modstate-on' : 'rk-modstate-off' ) . '">' . esc_html( ucfirst( $p->post_status ) ) . '</span></td>';
			echo '<td class="rk-right"><a href="' . esc_url( $edit ) . '">Edit</a> &nbsp;·&nbsp; <a href="' . esc_url( $dup ) . '">Duplicate</a> &nbsp;·&nbsp; <a href="' . esc_url( $exp ) . '">Export</a> &nbsp;·&nbsp; <a class="rk-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete this listing?\')">Delete</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		$this->foot();
	}

	public function do_listing_create() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_listing_create' );
		$id = RK_Core_Listings::create(
			isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Listing',
			array(
				'source'    => isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : 'posts',
				'post_type' => isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post',
				'columns'   => isset( $_POST['columns'] ) ? (int) $_POST['columns'] : 3,
				'count'     => isset( $_POST['count'] ) ? (int) $_POST['count'] : 6,
			)
		);
		if ( $id ) { wp_safe_redirect( RK_Core_Listings::edit_url( $id ) ); exit; }
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_LIST . '&rk_msg=err' ) ); exit;
	}

	public function do_listing_delete() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_core_listing_delete_' . $id );
		RK_Core_Listings::delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_LIST . '&rk_msg=deleted' ) ); exit;
	}

	public function do_listing_duplicate() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_core_listing_duplicate_' . $id );
		RK_Core_Listings::duplicate( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_LIST . '&rk_msg=saved' ) ); exit;
	}

	public function do_listing_export() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_core_listing_export_' . $id );
		$data = RK_Core_Listings::export_one( $id );
		if ( ! $data ) { wp_die( 'Not found.' ); }
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="rk-listing-' . $id . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function do_listing_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_listing_import' );
		if ( empty( $_FILES['listing'] ) || ! isset( $_FILES['listing']['tmp_name'] ) || ! is_uploaded_file( $_FILES['listing']['tmp_name'] ) ) {
			set_transient( 'rk_core_err', 'No file uploaded.', 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_LIST . '&rk_msg=err' ) ); exit;
		}
		$raw = file_get_contents( $_FILES['listing']['tmp_name'] );
		$d   = json_decode( (string) $raw, true );
		$id  = is_array( $d ) ? RK_Core_Listings::import_one( $d ) : 0;
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_LIST . '&rk_msg=' . ( $id ? 'saved' : 'err' ) ) ); exit;
	}

	public function do_export() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_export' );
		$scope = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : 'all';
		$bits  = array();
		if ( 'pick' === $scope ) {
			$slugs  = ! empty( $_POST['slugs'] ) && is_array( $_POST['slugs'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['slugs'] ) ) : array();
			$bundle = RK_Core_Porter::collect_for( $slugs );
			$bits   = array_slice( $slugs, 0, 3 );
		} else {
			$bundle = RK_Core_Porter::collect_all();
			$bits   = array( 'all' );
		}
		// JetEngine → RK Core mapping (additive; native export untouched).
		if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() && ! empty( $_POST['jet_slugs'] ) && is_array( $_POST['jet_slugs'] ) ) {
			$jslugs = array_map( 'sanitize_key', wp_unslash( $_POST['jet_slugs'] ) );
			$bundle = RK_Core_Porter::merge_bundle( $bundle, RK_Core_JetEngine::build( $jslugs ) );
			$bits[] = 'jet';
		}
		// Full content portability — published posts + meta + media.
		if ( ! empty( $_POST['include_posts'] ) ) {
			$content_slugs = array();
			if ( 'pick' === $scope ) {
				$content_slugs = isset( $slugs ) ? $slugs : array();
			} else {
				foreach ( ( class_exists( 'RK_CPT_Builder' ) ? RK_CPT_Builder::all() : array() ) as $c ) { if ( ! empty( $c['slug'] ) ) { $content_slugs[] = $c['slug']; } }
			}
			if ( class_exists( 'RK_Core_JetEngine' ) && ! empty( $_POST['jet_slugs'] ) && is_array( $_POST['jet_slugs'] ) ) {
				$content_slugs = array_merge( $content_slugs, array_map( 'sanitize_key', wp_unslash( $_POST['jet_slugs'] ) ) );
			}
			$content_slugs = array_values( array_unique( array_filter( $content_slugs ) ) );
			if ( $content_slugs ) {
				$pd = RK_Core_Porter::collect_posts_data( $content_slugs );
				if ( $pd ) { $bundle['posts_data'] = $pd; $bits[] = 'content'; }
			}
		}
		// JetEngine Listing templates (+ optional blueprints).
		if ( class_exists( 'RK_Core_JetEngine' ) && RK_Core_JetEngine::is_active() ) {
			$listings = array();
			if ( ! empty( $_POST['jet_listing_ids'] ) && is_array( $_POST['jet_listing_ids'] ) ) {
				$listings = RK_Core_JetEngine::export_listings( array_map( 'intval', wp_unslash( $_POST['jet_listing_ids'] ) ) );
			}
			if ( ! empty( $_POST['include_blueprints'] ) ) { $listings = array_merge( $listings, RK_Core_JetEngine::blueprints() ); }
			if ( $listings ) { $bundle['jet_engine_listings'] = array_values( $listings ); $bits[] = 'listings'; }
		}
		$bits = array_values( array_filter( $bits ) );
		$name = 'rk-model-' . ( $bits ? implode( '-', array_slice( $bits, 0, 3 ) ) : 'all' ) . '.json';
		RK_Core_Porter::download( $bundle, $name );
	}

	public function do_jet_debug() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_jet_debug' );
		$dump = class_exists( 'RK_Core_JetEngine' ) ? RK_Core_JetEngine::debug_dump() : array( 'error' => 'JetEngine mapper unavailable' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jetengine-raw-debug.json"' );
		echo wp_json_encode( $dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function do_jet_listing_delete() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'rk_core_jet_listing_delete_' . $id );
		if ( class_exists( 'RK_Core_JetEngine' ) ) { RK_Core_JetEngine::delete_listing( $id ); }
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=jetdeleted' ) ); exit;
	}

	public function do_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_import' );
		if ( empty( $_FILES['model'] ) || ! isset( $_FILES['model']['tmp_name'] ) || ! is_uploaded_file( $_FILES['model']['tmp_name'] ) ) {
			set_transient( 'rk_core_err', 'No file uploaded.', 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=err' ) ); exit;
		}
		$raw = file_get_contents( $_FILES['model']['tmp_name'] );
		$b   = json_decode( (string) $raw, true );
		if ( ! is_array( $b ) ) {
			set_transient( 'rk_core_err', 'That file is not valid JSON.', 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=err' ) ); exit;
		}
		$report = RK_Core_Porter::import( $b );
		set_transient( 'rk_core_ie_report', $report, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=imported' ) ); exit;
	}

	public function do_starter() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$id = isset( $_POST['starter'] ) ? sanitize_key( $_POST['starter'] ) : '';
		check_admin_referer( 'rk_core_starter_' . $id );
		$report = RK_Core_Porter::install_starter( $id );
		set_transient( 'rk_core_ie_report', $report, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG_IE . '&rk_msg=installed' ) ); exit;
	}

	public function do_example() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_core_example' );
		RK_Core_Porter::download( RK_Core_Porter::example_bundle(), 'rk-core-example.json' );
	}

}
