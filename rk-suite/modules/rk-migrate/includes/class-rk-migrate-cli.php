<?php
/**
 * RK_Migrate_CLI — WP-CLI commands for scripted deploys / CI-CD.
 *
 *   wp rk-migrate export --output=bundle.zip [--media] [--no-menus]
 *   wp rk-migrate import <bundle.zip|library-slug> [--dry-run] [--media] [--from=URL --to=URL]
 *   wp rk-migrate list-library
 *   wp rk-migrate rollback <snapshot-token> [--post=ID]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'WP_CLI' ) ) { return; }

class RK_Migrate_CLI {

	/**
	 * Export the whole site to a bundle zip.
	 *
	 * ## OPTIONS
	 * [--output=<path>] : Destination zip path. Default: ./rk-migrate-export.zip
	 * [--media]         : Bundle referenced media files into the zip.
	 * [--no-menus]      : Skip exporting menus.
	 * [--no-kit]        : Skip exporting global colors/fonts.
	 */
	public function export( $args, $assoc ) {
		$inv = RK_Migrate_Exporter::inventory();
		$exporter = new RK_Migrate_Exporter();
		$path = $exporter->build( array(
			'project'      => get_bloginfo( 'name' ),
			'page_ids'     => wp_list_pluck( $inv['pages'], 'id' ),
			'post_ids'     => wp_list_pluck( $inv['posts'], 'id' ),
			'cpt_ids'      => wp_list_pluck( $inv['cpts'], 'id' ),
			'template_ids' => wp_list_pluck( $inv['templates'], 'id' ),
			'include_menus'      => ! isset( $assoc['no-menus'] ),
			'include_global_kit' => ! isset( $assoc['no-kit'] ),
			'include_media'      => isset( $assoc['media'] ),
		) );
		if ( is_wp_error( $path ) ) { WP_CLI::error( $path->get_error_message() ); }
		$dest = isset( $assoc['output'] ) ? $assoc['output'] : getcwd() . '/rk-migrate-export.zip';
		copy( $path, $dest );
		WP_CLI::success( "Exported to {$dest}" );
	}

	/**
	 * Import a bundle (zip path or library slug).
	 *
	 * ## OPTIONS
	 * <bundle> : Path to a .zip, or a library slug.
	 * [--dry-run] : Report only, change nothing.
	 * [--media]   : Sideload + re-link remote media.
	 * [--from=<url>] : Source URL to rewrite from.
	 * [--to=<url>]   : Destination URL to rewrite to.
	 * [--no-front]   : Do not set the front page.
	 */
	public function import( $args, $assoc ) {
		list( $bundle ) = $args;
		$base = '';
		if ( is_file( $bundle ) && 'zip' === strtolower( pathinfo( $bundle, PATHINFO_EXTENSION ) ) ) {
			$slug = RK_Migrate_Library::store_zip( $bundle, 'cli' );
			if ( is_wp_error( $slug ) ) { WP_CLI::error( $slug->get_error_message() ); }
			$base = RK_Migrate_Library::path( $slug );
		} else {
			$base = RK_Migrate_Library::path( $bundle );
		}
		if ( ! $base ) { WP_CLI::error( 'Bundle not found.' ); }

		$opts = array(
			'dry'          => isset( $assoc['dry-run'] ),
			'set_front'    => ! isset( $assoc['no-front'] ),
			'assign_parts' => true,
			'build_menus'  => true,
			'media_relink' => isset( $assoc['media'] ),
		);
		if ( ! empty( $assoc['from'] ) && ! empty( $assoc['to'] ) ) {
			$opts['replace_rules'] = array( array( 'find' => untrailingslashit( $assoc['from'] ), 'replace' => untrailingslashit( $assoc['to'] ) ) );
		}
		$importer = new RK_Migrate_Importer( $base );
		if ( ! isset( $assoc['dry-run'] ) ) {
			RK_Migrate_History::instance()->snapshot( $importer->affected_post_ids(), 'CLI pre-import' );
		}
		foreach ( $importer->run( $opts ) as $line ) { WP_CLI::log( $line ); }
		$c = $importer->counts();
		WP_CLI::success( sprintf( 'Done: %d created, %d updated, %d errors.', $c['created'], $c['updated'], $c['errors'] ) );
	}

	/** List stored library bundles. */
	public function list_library() {
		$rows = array();
		foreach ( RK_Migrate_Library::all() as $b ) {
			$rows[] = array( 'slug' => $b['slug'], 'project' => $b['project'], 'pages' => $b['pages'], 'exported' => $b['time'] );
		}
		if ( ! $rows ) { WP_CLI::log( 'Library is empty.' ); return; }
		WP_CLI\Utils\format_items( 'table', $rows, array( 'slug', 'project', 'pages', 'exported' ) );
	}

	/**
	 * Roll back from a snapshot token.
	 *
	 * ## OPTIONS
	 * <token> : Snapshot token.
	 * [--post=<id>] : Restore only this post id.
	 */
	public function rollback( $args, $assoc ) {
		list( $token ) = $args;
		$post = isset( $assoc['post'] ) ? (int) $assoc['post'] : 0;
		foreach ( RK_Migrate_History::instance()->restore( $token, $post ) as $line ) { WP_CLI::log( $line ); }
		WP_CLI::success( 'Rollback finished.' );
	}
}
