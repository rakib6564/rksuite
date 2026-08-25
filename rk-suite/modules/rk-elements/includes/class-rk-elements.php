<?php
/**
 * RK_Elements — the widget loader.
 *
 * Registers the "RK Elements" Elementor category and every widget, using the
 * modern (Elementor 3.5+) registration API with a fallback for older versions.
 * If Elementor is not active, it shows an admin notice and does nothing else —
 * the plugin never fatals.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Elements {

	private static $instance = null;
	private $registered = false;

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() {
		// Register Elementor hooks unconditionally — they simply never fire if
		// Elementor is not present, and this avoids a load-order race where
		// \Elementor\Plugin is not yet defined at plugins_loaded time.
		add_action( 'elementor/elements/categories_registered', array( $this, 'category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );          // 3.5+
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) ); // legacy
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_menu_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_contact_assets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'register_contact_assets' ) );
		self::boot_contact();
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'register_menu_assets' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'editor_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice_no_elementor' ) );
	}

	/** Show a notice only when Elementor is genuinely inactive (checked late). */
	public function maybe_notice_no_elementor() {
		if ( class_exists( '\Elementor\Plugin' ) ) { return; }
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, array( 'plugins', 'dashboard' ), true ) ) { return; }
		echo '<div class="notice notice-warning"><p><strong>RK Elements</strong> needs <strong>Elementor</strong> to be active. Its widgets stay dormant until Elementor is enabled.</p></div>';
	}

	public function category( $manager ) {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_category' ) ) { return; }
		try {
			$manager->add_category( 'rk-elements', array(
				'title' => 'RK Elements',
				'icon'  => 'eicon-flash',
			) );
		} catch ( \Throwable $e ) {
			rk_suite_log( '[RK Elements] category registration failed: ' . $e->getMessage() );
		}
	}

	/** Widget map: relative file => class name. */
	public static function widget_map() {
		return array(
			'widgets/tier-1-layout/class-rk-widget-listing-grid.php'   => 'RK_Widget_Listing_Grid',
			'widgets/tier-1-layout/class-rk-widget-post-carousel.php'  => 'RK_Widget_Post_Carousel',
			'widgets/tier-1-layout/class-rk-widget-filter-grid.php'    => 'RK_Widget_Filter_Grid',
			'widgets/tier-2-content/class-rk-widget-heading.php'       => 'RK_Widget_Heading',
			'widgets/tier-2-content/class-rk-widget-counter.php'       => 'RK_Widget_Counter',
			'widgets/tier-2-content/class-rk-widget-progress.php'      => 'RK_Widget_Progress',
			'widgets/tier-2-content/class-rk-widget-testimonial.php'   => 'RK_Widget_Testimonial',
			'widgets/tier-2-content/class-rk-widget-pricing.php'       => 'RK_Widget_Pricing',
			'widgets/tier-2-content/class-rk-widget-team.php'          => 'RK_Widget_Team',
			'widgets/tier-2-content/class-rk-widget-accordion.php'     => 'RK_Widget_Accordion',
			'widgets/tier-2-content/class-rk-widget-nav-menu.php'     => 'RK_Widget_Nav_Menu',
			'widgets/tier-2-content/class-rk-widget-bento-menu.php'   => 'RK_Widget_Bento_Menu',
			'widgets/tier-3-advanced/class-rk-widget-download-button.php'   => 'RK_Widget_Download_Button',
			'widgets/tier-3-advanced/class-rk-widget-steps.php'            => 'RK_Widget_Steps',
			'widgets/tier-3-advanced/class-rk-widget-tabs.php'             => 'RK_Widget_Tabs',
			'widgets/tier-3-advanced/class-rk-widget-before-after.php'     => 'RK_Widget_Before_After',
			'widgets/tier-3-advanced/class-rk-widget-two-color-heading.php'=> 'RK_Widget_Two_Color_Heading',
			'widgets/tier-3-advanced/class-rk-widget-flip-card.php'        => 'RK_Widget_Flip_Card',
			'widgets/tier-3-advanced/class-rk-widget-link-box.php'         => 'RK_Widget_Link_Box',
			'widgets/tier-3-advanced/class-rk-widget-buttons.php'          => 'RK_Widget_Buttons',
			'widgets/tier-3-advanced/class-rk-widget-contact-form.php'     => 'RK_Widget_Contact_Form',
		);
	}

	private function load_classes() {
		require_once RK_ELEMENTS_DIR . 'includes/class-rk-widget-controls.php';
		require_once RK_ELEMENTS_DIR . 'includes/class-rk-widget-base.php';
		foreach ( self::widget_map() as $file => $class ) {
			$path = RK_ELEMENTS_DIR . 'includes/' . $file;
			if ( file_exists( $path ) ) { require_once $path; }
		}
	}

	/**
	 * Register widgets. Called from either the modern hook (passes the manager)
	 * or the legacy hook (no arg). Guarded so it runs once.
	 */
	public function register_widgets( $manager = null ) {
		if ( $this->registered ) { return; }

		if ( ! $manager && class_exists( '\Elementor\Plugin' ) ) {
			$manager = \Elementor\Plugin::instance()->widgets_manager;
		}
		if ( ! $manager || ! is_object( $manager ) ) { return; }

		// Load the widget classes defensively — a failure here must not fatal.
		try {
			$this->load_classes();
		} catch ( \Throwable $e ) {
			rk_suite_log( '[RK Elements] failed loading widget classes: ' . $e->getMessage() );
			$this->registered = true;
			return;
		}

		foreach ( self::widget_map() as $file => $class ) {
			if ( ! class_exists( $class ) ) { continue; }
			// Isolate each widget: one bad widget can't take down the site or the
			// rest of the library. Runtime errors are logged and skipped.
			try {
				$widget = new $class();
				if ( method_exists( $manager, 'register' ) ) {
					$manager->register( $widget );
				} elseif ( method_exists( $manager, 'register_widget_type' ) ) {
					$manager->register_widget_type( $widget );
				}
			} catch ( \Throwable $e ) {
				rk_suite_log( '[RK Elements] widget ' . $class . ' failed to register: ' . $e->getMessage() );
			}
		}
		$this->registered = true;
	}

	public function frontend_assets() {
		// Register only — pulled in per-widget via RK_Widget_Base::get_*_depends(),
		// so non-RK pages stay free of this CSS/JS.
		wp_register_style( 'rk-elements', RK_ELEMENTS_URL . 'assets/frontend.css', array(), RK_ELEMENTS_VERSION );
		wp_register_script( 'rk-elements', RK_ELEMENTS_URL . 'assets/frontend.js', array( 'jquery' ), RK_ELEMENTS_VERSION, true );
	}

	/** Load + init the self-contained Contact Form AJAX handler (once). */
	private static function boot_contact() {
		static $done = false;
		if ( $done ) { return; }
		$done = true;
		$path = RK_ELEMENTS_DIR . 'includes/class-rk-elements-contact.php';
		if ( file_exists( $path ) ) {
			require_once $path;
			if ( class_exists( 'RK_Elements_Contact' ) ) { RK_Elements_Contact::init(); }
		}
	}

	/**
	 * Register (not enqueue) the Contact Form assets, plus the localized
	 * ajaxurl the front-end script needs. Pulled in per-widget via the widget's
	 * get_*_depends(), so only pages with the widget load them.
	 */
	public function register_contact_assets() {
		wp_register_style( 'rk-contact', RK_ELEMENTS_URL . 'assets/contact.css', array(), RK_ELEMENTS_VERSION );
		wp_register_script( 'rk-contact', RK_ELEMENTS_URL . 'assets/contact.js', array(), RK_ELEMENTS_VERSION, true );
		wp_localize_script( 'rk-contact', 'RKContact', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

	/** Ensure the Contact Form assets are enqueued even under Elementor pre-render. */
	public function enqueue_contact_now() {
		if ( ! wp_style_is( 'rk-contact', 'registered' ) ) { $this->register_contact_assets(); }
		wp_enqueue_style( 'rk-contact' );
		wp_enqueue_script( 'rk-contact' );
	}

	/**
	 * Register (do not enqueue) the menu-widget assets so Elementor pulls them
	 * in ONLY on pages that actually use RK Nav Menu / RK Bento Menu — via each
	 * widget's get_style_depends() / get_script_depends(). Keeps every other
	 * page free of menu CSS/JS for best load performance.
	 */
	public function register_menu_assets() {
		wp_register_style( 'rk-menu', RK_ELEMENTS_URL . 'assets/menu.css', array(), RK_ELEMENTS_VERSION );
		wp_register_script( 'rk-menu', RK_ELEMENTS_URL . 'assets/menu.js', array(), RK_ELEMENTS_VERSION, true );
	}

	public function editor_assets() {
		wp_enqueue_style( 'rk-elements-editor', RK_ELEMENTS_URL . 'assets/editor.css', array(), RK_ELEMENTS_VERSION );
	}
}
