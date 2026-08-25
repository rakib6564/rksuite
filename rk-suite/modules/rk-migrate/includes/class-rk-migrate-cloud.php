<?php
/**
 * RK_Migrate_Cloud — client for an optional RK Migrate cloud bundle store.
 *
 * The cloud backend itself is an external service (out of scope for the plugin),
 * so this ships as a fully-wired REST client that talks to whatever endpoint is
 * configured in Settings, plus filter hooks so a self-hosted or third-party
 * backend can be dropped in. With no endpoint configured it stays dormant.
 *
 * Endpoint contract (JSON, Bearer auth):
 *   GET  {endpoint}/bundles            -> { bundles:[ {id,project,updated,size} ] }
 *   GET  {endpoint}/bundles/{id}       -> { file, bundle(base64) }
 *   POST {endpoint}/bundles            -> { id }              (body: {project, bundle})
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Prevent direct file access.

class RK_Migrate_Cloud {

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {}

	public static function configured() {
		$s = RK_Migrate_Settings::instance();
		return $s->can_use( 'cloud' ) && '' !== trim( (string) $s->get( 'cloud_endpoint' ) );
	}

	private static function req( $method, $route, $body = null ) {
		$s = RK_Migrate_Settings::instance();
		$args = array(
			'method'  => $method,
			'timeout' => 120,
			'headers' => array(
				'Authorization' => 'Bearer ' . $s->get( 'cloud_token' ),
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$url  = trailingslashit( $s->get( 'cloud_endpoint' ) ) . ltrim( $route, '/' );
		$resp = apply_filters( 'rk_migrate_cloud_request', null, $method, $url, $body );
		if ( null === $resp ) { $resp = wp_remote_request( $url, $args ); }
		if ( is_wp_error( $resp ) ) { return $resp; }
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( wp_remote_retrieve_response_code( $resp ) >= 300 ) {
			return new WP_Error( 'cloud', isset( $data['message'] ) ? $data['message'] : 'Cloud error.' );
		}
		return $data;
	}

	public static function list_bundles() {
		if ( ! self::configured() ) { return new WP_Error( 'unconfigured', 'Cloud storage is not configured.' ); }
		$r = self::req( 'GET', 'bundles' );
		if ( is_wp_error( $r ) ) { return $r; }
		return isset( $r['bundles'] ) ? $r['bundles'] : array();
	}

	/** Upload a local zip to the cloud. */
	public static function upload( $zip_path, $project ) {
		if ( ! self::configured() ) { return new WP_Error( 'unconfigured', 'Cloud storage is not configured.' ); }
		return self::req( 'POST', 'bundles', array( 'project' => $project, 'bundle' => base64_encode( file_get_contents( $zip_path ) ) ) );
	}

	/** Download a cloud bundle into the local library; returns library slug. */
	public static function download_to_library( $id ) {
		if ( ! self::configured() ) { return new WP_Error( 'unconfigured', 'Cloud storage is not configured.' ); }
		$r = self::req( 'GET', 'bundles/' . rawurlencode( $id ) );
		if ( is_wp_error( $r ) ) { return $r; }
		if ( empty( $r['bundle'] ) ) { return new WP_Error( 'empty', 'Cloud returned no bundle.' ); }
		$tmp = trailingslashit( RK_MIGRATE_EXPORT_DIR ) . 'cloud-' . sanitize_file_name( $id ) . '.zip';
		if ( ! file_exists( RK_MIGRATE_EXPORT_DIR ) ) { wp_mkdir_p( RK_MIGRATE_EXPORT_DIR ); }
		file_put_contents( $tmp, base64_decode( $r['bundle'] ) );
		$slug = RK_Migrate_Library::store_zip( $tmp, isset( $r['project'] ) ? $r['project'] : 'cloud' );
		@unlink( $tmp );
		return $slug;
	}
}
