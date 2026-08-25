<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * RK_Forms — module boot. Wires the shortcode, AJAX submit, front-end assets,
 * the admin UI, and (when Elementor is present) the RK Form widget.
 */
class RK_Forms {

	private static $instance = null;
	public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }

	private function __construct() {
		// DB upgrade check.
		add_action( 'admin_init', function () {
			if ( get_option( 'rk_forms_db_version' ) !== RK_Forms_DB::DB_VERSION ) { RK_Forms_DB::install(); }
		} );

		// Front end.
		add_shortcode( 'rk_form', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_rk_form_submit', array( 'RK_Forms_Public', 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_rk_form_submit', array( 'RK_Forms_Public', 'ajax_submit' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

		// Admin.
		if ( is_admin() ) { RK_Forms_Admin::instance(); }

		// Elementor widget.
		add_action( 'elementor/widgets/register', array( 'RK_Forms_Widget', 'register' ) );
		add_action( 'elementor/elements/categories_registered', array( 'RK_Forms_Widget', 'category' ) );
	}

	/** [rk_form id="1"] or [rk_form slug="contact"]. */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'slug' => '' ), $atts, 'rk_form' );
		$form = $atts['id'] ? RK_Forms_DB::get_form( (int) $atts['id'] )
			: ( $atts['slug'] ? RK_Forms_DB::get_form_by_slug( $atts['slug'] ) : null );
		if ( ! $form ) { return ''; }
		$this->need_assets = true;
		$this->enqueue_now();
		return RK_Forms_Public::render_form( $form );
	}

	public $need_assets = false;

	public function assets() {
		// Registered here; enqueued on demand by shortcode/widget.
		wp_register_style( 'rk-forms', RK_FORMS_URL . 'assets/forms.css', array(), RK_FORMS_VERSION );
		wp_register_script( 'rk-forms', RK_FORMS_URL . 'assets/forms.js', array(), RK_FORMS_VERSION, true );
		wp_localize_script( 'rk-forms', 'RKForms', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
	}

	public function enqueue_now() {
		if ( ! wp_style_is( 'rk-forms', 'registered' ) ) { $this->assets(); }
		wp_enqueue_style( 'rk-forms' );
		wp_enqueue_script( 'rk-forms' );
	}
}
