<?php
/**
 * RK_Suite_Modules — the catalog of bundled modules and the enable/disable
 * state. Modules are directories under /modules; only enabled ones load.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Suite_Modules {

	const OPTION = 'rk_suite_enabled';

	/**
	 * slug => definition. 'boot' and 'activate' are callables inside the module
	 * that RK Suite invokes; 'menu_slug' is the module's own admin menu (folded
	 * under the RK menu), or '' if it has none.
	 */
	public static function definitions() {
		return array(
			'rk-core' => array(
				'name'      => 'RK Core',
				'tier'      => 'free',
				'desc'      => 'CPT, taxonomy and custom-field engine. The shared foundation other modules build on.',
				'class'     => 'RK_Core',
				'dir'       => 'rk-core',
				'main'      => 'rk-core.php',
				'boot'      => 'rk_core_boot',
				'activate'  => array( 'RK_Core', 'activate' ),
				'menu_slug' => 'rk-core',
				'depends'   => array(),
			),
			'rk-migrate' => array(
				'name'      => 'RK Migrate',
				'tier'      => 'free',
				'desc'      => 'Round-trip Elementor site export / import, media re-link, rollback snapshots and Site Doctor.',
				'class'     => 'RK_Migrate_Settings',
				'dir'       => 'rk-migrate',
				'main'      => 'rk-migrate.php',
				'boot'      => 'rk_migrate_boot',
				'activate'  => 'rk_migrate_activate',
				'menu_slug' => 'rk-migrate',
				'depends'   => array(),
			),
			'rk-theme' => array(
				'name'      => 'RK Theme',
				'tier'      => 'free',
				'desc'      => 'Standalone header & footer builder with its own display conditions and self-contained import/export. Design in Elementor; RK handles where it shows.',
				'class'     => 'RK_Theme',
				'dir'       => 'rk-theme',
				'main'      => 'rk-theme.php',
				'boot'      => 'rk_theme_boot',
				'activate'  => 'rk_theme_activate',
				'menu_slug' => 'rk-theme',
				'depends'   => array(),
			),
			'rk-seo' => array(
				'name'      => 'RK SEO',
				'tier'      => 'free',
				'desc'      => 'Zero-config auto-pilot SEO: meta tags, JSON-LD schema, dynamic XML sitemaps, breadcrumbs, smart 301 redirects and 404 monitoring.',
				'class'     => 'RK\\SEO\\Plugin',
				'dir'       => 'rk-seo',
				'main'      => 'rk-seo.php',
				'boot'      => 'rk_seo_boot',
				'activate'  => 'rk_seo_activate',
				'menu_slug' => 'rk-seo',
				'depends'   => array(),
			),
			'rk-library' => array(
				'name'      => 'RK Library',
				'tier'      => 'free',
				'desc'      => 'A branded, categorized template library inside the Elementor editor. Import design bundles on the backend; pick &amp; insert them from a custom modal like Elementor\'s own library.',
				'class'     => 'RK_Library',
				'dir'       => 'rk-library',
				'main'      => 'rk-library.php',
				'boot'      => 'rk_library_boot',
				'activate'  => 'rk_library_activate',
				'menu_slug' => 'rk-library',
				'depends'   => array(),
			),
			'rk-forms' => array(
				'name'      => 'RK Forms',
				'tier'      => 'free',
				'desc'      => 'Public form builder: visual/DSL builder, conditional logic, submissions inbox, email notifications, CSV export, and an Elementor \'RK Form\' widget.',
				'class'     => 'RK_Forms',
				'dir'       => 'rk-forms',
				'main'      => 'rk-forms.php',
				'boot'      => 'rk_forms_boot',
				'activate'  => 'rk_forms_activate',
				'menu_slug' => 'rk-forms',
				'depends'   => array(),
			),
			'rk-elements' => array(
				'name'      => 'RK Elements',
				'tier'      => 'pro',
				'desc'      => 'Elementor widget library — listing grid, carousel, filter grid, counter, pricing, testimonial and more.',
				'class'     => 'RK_Elements',
				'dir'       => 'rk-elements',
				'main'      => 'rk-elements.php',
				'boot'      => 'rk_elements_boot',
				'activate'  => '',
				'menu_slug' => '',
				'depends'   => array( 'rk-core' ),
			),
			'rk-visual' => array(
				'name'      => 'RK Visual Edit',
				'tier'      => 'free',
				'desc'      => 'Advanced on-page visual editing: click text to edit in place with a rich-text toolbar, edit marked regions inside HTML widgets, swap images and edit link URLs — with per-edit undo and role-based access.',
				'class'     => 'RK_Visual',
				'dir'       => 'rk-visual',
				'main'      => 'rk-visual.php',
				'boot'      => 'rk_visual_boot',
				'activate'  => 'rk_visual_activate',
				'menu_slug' => 'rk-visual',
				'depends'   => array(),
			),
			'rk-api' => array(
				'name'      => 'RK API',
				'tier'      => 'free',
				'desc'      => 'A REST management API (rk/v1) for external tools and the RK Suite MCP server: create, duplicate, copy, edit and SEO-manage Elementor pages, plus media, menus, modules and site health. App-password or API-key auth.',
				'class'     => 'RK_API',
				'dir'       => 'rk-api',
				'main'      => 'rk-api.php',
				'boot'      => 'rk_api_boot',
				'activate'  => 'rk_api_activate',
				'menu_slug' => 'rk-api',
				'depends'   => array(),
			),
		);
	}

	/** Default state: only RK Core enabled. */
	public static function default_map() {
		return array( 'rk-core' => 1 );
	}

	public static function enabled_map() {
		$m = get_option( self::OPTION, null );
		if ( null === $m || ! is_array( $m ) ) { return self::default_map(); }
		return $m;
	}

	public static function is_enabled( $slug ) {
		$m = self::enabled_map();
		return ! empty( $m[ $slug ] );
	}

	public static function set_enabled( $slug, $on ) {
		$m = self::enabled_map();
		if ( $on ) { $m[ $slug ] = 1; } else { unset( $m[ $slug ] ); }
		update_option( self::OPTION, $m );
	}

	public static function get( $slug ) {
		$defs = self::definitions();
		return isset( $defs[ $slug ] ) ? $defs[ $slug ] : null;
	}
}
