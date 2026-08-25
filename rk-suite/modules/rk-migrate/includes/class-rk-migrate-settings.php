<?php
/**
 * RK_Migrate_Settings — central options store: license tier, role-based access,
 * webhooks, remote tokens, AI provider config.
 *
 * Brand & author are fixed: RK Migrate by Rakib Hasan (rakibhasaan.com).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Settings {

	const OPTION = 'rk_migrate_settings';
	const BRAND  = 'RK Migrate';
	const TAGLINE = 'The Elementor Migration Kit';
	const AUTHOR = 'Rakib Hasan';
	const AUTHOR_URL = 'https://rakibhasaan.com';

	private static $instance = null;
	private $data;

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() {
		$this->data = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function defaults() {
		return array(
			'tier'             => 'agency',          // free | pro | agency (self-hosted: all unlocked)
			'cap_import'       => 'manage_options',
			'cap_export'       => 'manage_options',
			'cap_rollback'     => 'manage_options',
			'cap_view_log'     => 'manage_options',
			'webhook_url'      => '',
			'webhook_on'       => array( 'import_done', 'import_fail' ),
			'remote_enabled'   => 0,
			'remote_token'     => '',
			'remote_allow_pull'=> 1,
			'ai_provider'      => 'openai',
			'ai_api_key'       => '',
			'ai_model'         => 'gpt-4o-mini',
			'ai_endpoint'      => 'https://api.openai.com/v1/chat/completions',
			'cloud_endpoint'   => '',
			'cloud_token'      => '',
		);
	}

	public static function seed_defaults() {
		$existing = get_option( self::OPTION, null );
		if ( null === $existing ) { add_option( self::OPTION, self::defaults() ); }
	}

	public function get( $key, $fallback = null ) {
		if ( isset( $this->data[ $key ] ) ) { return $this->data[ $key ]; }
		return $fallback;
	}

	public function all() { return $this->data; }

	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
		update_option( self::OPTION, $this->data );
	}

	public function update( array $patch ) {
		$this->data = array_merge( $this->data, $patch );
		update_option( self::OPTION, $this->data );
	}

	/* ---------- tier gating ---------- */

	public static function feature_tier( $feature ) {
		$pro = array( 'media', 'cpt', 'global_kit', 'rollback', 'manifest_ui', 'staging_push', 'woo', 'library', 'ai' );
		$agency = array( 'remote', 'shared_sync', 'roles', 'webhooks', 'encryption', 'cloud', 'marketplace', 'cli' );
		if ( in_array( $feature, $agency, true ) ) { return 'agency'; }
		if ( in_array( $feature, $pro, true ) ) { return 'pro'; }
		return 'free';
	}

	public function can_use( $feature ) {
		$rank = array( 'free' => 0, 'pro' => 1, 'agency' => 2 );
		$have = isset( $rank[ $this->get( 'tier' ) ] ) ? $rank[ $this->get( 'tier' ) ] : 0;
		$need = $rank[ self::feature_tier( $feature ) ];
		return $have >= $need;
	}

	/* ---------- role-based access ---------- */
	public function current_user_can( $action ) {
		$map = array(
			'import'   => $this->get( 'cap_import' ),
			'export'   => $this->get( 'cap_export' ),
			'rollback' => $this->get( 'cap_rollback' ),
			'view_log' => $this->get( 'cap_view_log' ),
		);
		$cap = isset( $map[ $action ] ) ? $map[ $action ] : 'manage_options';
		return current_user_can( $cap );
	}

	/* ---------- fixed brand / author ---------- */
	public function brand_name() { return self::BRAND; }
	public function tagline()    { return self::TAGLINE; }
	public function credit_html() {
		return 'by <a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener">' . esc_html( self::AUTHOR ) . '</a>';
	}

	/* ---------- webhook dispatch ---------- */
	public function fire_webhook( $event, array $payload ) {
		$url = trim( (string) $this->get( 'webhook_url' ) );
		if ( ! $url ) { return; }
		$on = (array) $this->get( 'webhook_on', array() );
		if ( ! in_array( $event, $on, true ) ) { return; }
		$body = wp_json_encode( array_merge( array(
			'event'   => $event,
			'site'    => home_url(),
			'time'    => gmdate( 'c' ),
			'plugin'  => self::BRAND,
		), $payload ) );
		wp_remote_post( $url, array(
			'timeout'  => 8,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => $body,
		) );
	}
}
