<?php
/**
 * RK_Core — bootstrap. Wires the CPT builder, taxonomy builder and field engine,
 * and registers everything the user has defined on the `init` hook.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Core {

	private static $instance = null;

	/** @var RK_CPT_Builder */
	public $cpts;
	/** @var RK_Taxonomy_Builder */
	public $taxonomies;
	/** @var RK_Field_Engine */
	public $fields;
	/** @var RK_Relations */
	public $relations;
	/** @var RK_Core_Admin */
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() {
		$this->cpts       = new RK_CPT_Builder();
		$this->taxonomies = new RK_Taxonomy_Builder();
		$this->fields     = new RK_Field_Engine();
		$this->relations  = new RK_Relations();
		$this->admin      = new RK_Core_Admin( $this->cpts, $this->taxonomies, $this->fields );

		// Register user-defined content early so it exists everywhere.
		add_action( 'init', array( $this->cpts, 'register_all' ), 5 );
		add_action( 'init', array( $this->taxonomies, 'register_all' ), 6 );
		add_action( 'init', array( 'RK_Core_Listings', 'register' ), 7 );
		add_action( 'elementor/widgets/register', array( 'RK_Core_Listing_Widget', 'register' ) );
		add_action( 'elementor/elements/categories_registered', array( 'RK_Core_Listing_Widget', 'category' ) );
		$this->fields->hooks();
		$this->relations->hooks();
		RK_Core_Dynamic_Tags::instance()->hooks();
		$this->admin->hooks();
		add_action( 'admin_init', array( __CLASS__, 'maybe_install_db' ) );
		add_shortcode( 'rk_listing', array( 'RK_Core_Listings', 'shortcode' ) );
	}

	/** Create/upgrade the relations table once per version. */
	public static function maybe_install_db() {
		if ( get_option( 'rk_core_db_version' ) !== RK_CORE_VERSION ) {
			RK_Relations::install_table();
			update_option( 'rk_core_db_version', RK_CORE_VERSION );
		}
	}

	public static function activate() {
		// Flush rewrite rules so any stored CPTs get pretty permalinks.
		RK_CPT_Builder::register_all_static();
		RK_Taxonomy_Builder::register_all_static();
		RK_Relations::install_table();
		update_option( 'rk_core_db_version', RK_CORE_VERSION );
		flush_rewrite_rules();
	}
}
