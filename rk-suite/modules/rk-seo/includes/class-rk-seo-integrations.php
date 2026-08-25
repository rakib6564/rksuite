<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Integrations — Google Search Console, Semrush and Site Kit connectors.
 *
 * Credentials are user-supplied (this cannot ship shared API secrets). The GSC
 * OAuth flow is functional once you register an OAuth client in Google Cloud and
 * paste the client id/secret + the redirect URI shown on the settings screen.
 * Semrush works with a paid API key. Site Kit is auto-detected if installed.
 *
 * @package RK_SEO
 */
class Integrations {

	const OPTION = 'rk_seo_integrations';

	public function hooks() {
		add_action( 'admin_post_rk_seo_save_integrations', array( $this, 'save' ) );
		add_action( 'admin_post_rk_seo_gsc_connect', array( $this, 'gsc_connect' ) );
		add_action( 'admin_post_rk_seo_gsc_callback', array( $this, 'gsc_callback' ) );
		add_action( 'wp_ajax_rk_seo_semrush_test', array( $this, 'semrush_test' ) );
	}

	public static function all() {
		$o = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $o ) ? $o : array(), array(
			'gsc_client_id' => '', 'gsc_client_secret' => '', 'gsc_site' => home_url( '/' ),
			'gsc_refresh' => '', 'gsc_connected' => 0, 'semrush_key' => '',
		) );
	}

	public static function get( $k ) { $a = self::all(); return isset( $a[ $k ] ) ? $a[ $k ] : ''; }
	private static function set( $patch ) { update_option( self::OPTION, array_merge( self::all(), $patch ) ); }

	/** The redirect URI the user must register in Google Cloud. */
	public static function gsc_redirect_uri() {
		return admin_url( 'admin-post.php?action=rk_seo_gsc_callback' );
	}

	public static function sitekit_active() {
		return function_exists( 'is_plugin_active' )
			? is_plugin_active( 'google-site-kit/google-site-kit.php' )
			: in_array( 'google-site-kit/google-site-kit.php', (array) get_option( 'active_plugins', array() ), true );
	}

	/* ---------------- settings save ---------------- */

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_integrations' );
		$in = wp_unslash( $_POST );
		self::set( array(
			'gsc_client_id'     => sanitize_text_field( isset( $in['gsc_client_id'] ) ? $in['gsc_client_id'] : '' ),
			'gsc_client_secret' => sanitize_text_field( isset( $in['gsc_client_secret'] ) ? $in['gsc_client_secret'] : '' ),
			'gsc_site'          => esc_url_raw( isset( $in['gsc_site'] ) ? $in['gsc_site'] : home_url( '/' ) ),
			'semrush_key'       => sanitize_text_field( isset( $in['semrush_key'] ) ? $in['semrush_key'] : '' ),
		) );
		wp_safe_redirect( admin_url( 'admin.php?page=rk-seo&tab=integrations&rk_msg=saved' ) ); exit;
	}

	/* ---------------- Google Search Console OAuth ---------------- */

	public function gsc_connect() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'rk_seo_gsc_connect' );
		$cid = self::get( 'gsc_client_id' );
		if ( ! $cid ) { wp_safe_redirect( admin_url( 'admin.php?page=rk-seo&tab=integrations&rk_msg=nocreds' ) ); exit; }
		$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
			'client_id'     => $cid,
			'redirect_uri'  => self::gsc_redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => wp_create_nonce( 'rk_seo_gsc_state' ),
		) );
		wp_redirect( $url ); exit; // external Google endpoint (intentional).
	}

	public function gsc_callback() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $state, 'rk_seo_gsc_state' ) ) { wp_die( 'Invalid state.' ); }
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! $code ) { wp_safe_redirect( admin_url( 'admin.php?page=rk-seo&tab=integrations&rk_msg=gscfail' ) ); exit; }

		$resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 20,
			'body'    => array(
				'code'          => $code,
				'client_id'     => self::get( 'gsc_client_id' ),
				'client_secret' => self::get( 'gsc_client_secret' ),
				'redirect_uri'  => self::gsc_redirect_uri(),
				'grant_type'    => 'authorization_code',
			),
		) );
		$body = is_wp_error( $resp ) ? array() : json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $body['refresh_token'] ) ) {
			self::set( array( 'gsc_refresh' => $body['refresh_token'], 'gsc_connected' => 1 ) );
			wp_safe_redirect( admin_url( 'admin.php?page=rk-seo&tab=integrations&rk_msg=gscok' ) ); exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rk-seo&tab=integrations&rk_msg=gscfail' ) ); exit;
	}

	/** Exchange the stored refresh token for a short-lived access token. */
	private static function gsc_access_token() {
		$refresh = self::get( 'gsc_refresh' );
		if ( ! $refresh ) { return ''; }
		$cache = get_transient( 'rk_seo_gsc_at' );
		if ( $cache ) { return $cache; }
		$resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 20,
			'body'    => array(
				'client_id'     => self::get( 'gsc_client_id' ),
				'client_secret' => self::get( 'gsc_client_secret' ),
				'refresh_token' => $refresh,
				'grant_type'    => 'refresh_token',
			),
		) );
		$body = is_wp_error( $resp ) ? array() : json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $body['access_token'] ) ) {
			set_transient( 'rk_seo_gsc_at', $body['access_token'], max( 60, (int) ( $body['expires_in'] ?? 3500 ) - 60 ) );
			return $body['access_token'];
		}
		return '';
	}

	/** Top search queries from GSC for the last 28 days (array or WP_Error). */
	public static function gsc_top_queries( $rows = 10 ) {
		$token = self::gsc_access_token();
		if ( ! $token ) { return new \WP_Error( 'noauth', 'Google Search Console is not connected.' ); }
		$site = self::get( 'gsc_site' );
		$resp = wp_remote_post(
			'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'startDate'  => gmdate( 'Y-m-d', time() - 28 * DAY_IN_SECONDS ),
					'endDate'    => gmdate( 'Y-m-d' ),
					'dimensions' => array( 'query' ),
					'rowLimit'   => (int) $rows,
				) ),
			)
		);
		if ( is_wp_error( $resp ) ) { return $resp; }
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return isset( $body['rows'] ) ? $body['rows'] : array();
	}

	/* ---------------- Semrush ---------------- */

	public function semrush_test() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'nope' ) ); }
		check_ajax_referer( 'rk_seo_integrations', 'nonce' );
		$key = self::get( 'semrush_key' );
		if ( ! $key ) { wp_send_json_error( array( 'message' => 'Add your Semrush API key first.' ) ); }
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$resp = wp_remote_get( add_query_arg( array(
			'type'        => 'domain_rank',
			'key'         => $key,
			'domain'      => $host,
			'database'    => 'us',
			'export_columns' => 'Rk,Or,Ot,Oc',
		), 'https://api.semrush.com/' ), array( 'timeout' => 20 ) );
		if ( is_wp_error( $resp ) ) { wp_send_json_error( array( 'message' => $resp->get_error_message() ) ); }
		$body = trim( wp_remote_retrieve_body( $resp ) );
		if ( '' === $body || false !== stripos( $body, 'ERROR' ) ) {
			wp_send_json_error( array( 'message' => 'Semrush said: ' . esc_html( $body ? $body : 'empty response' ) ) );
		}
		wp_send_json_success( array( 'raw' => $body ) );
	}
}
