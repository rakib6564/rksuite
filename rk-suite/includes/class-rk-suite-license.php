<?php
/**
 * RK_Suite_License — one key for the whole bundle. Validated locally in dev
 * (stub) or against a server via the `rk_suite_validate_license` filter. The
 * resulting tier (free|pro|agency) gates Pro/Agency modules.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Suite_License {

	const OPTION = 'rk_suite_license';

	public static function defaults() {
		return array( 'key' => '', 'status' => 'inactive', 'tier' => 'free', 'name' => '', 'checked' => 0 );
	}

	public function data() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public function get( $k ) { $d = $this->data(); return isset( $d[ $k ] ) ? $d[ $k ] : null; }
	public function save( $patch ) { update_option( self::OPTION, array_merge( $this->data(), $patch ) ); }
	public function is_active() { return 'active' === $this->get( 'status' ); }

	/** Whether license enforcement is on. OFF by default = self-hosted full suite. */
	public static function hardening_on() {
		return defined( 'RK_SUITE_LICENSE_HARDENING' ) && RK_SUITE_LICENSE_HARDENING;
	}

	public function tier() {
		// Single-suite, self-hosted model: without enforcement the whole suite is
		// fully unlocked with no separate key. Enforcement is opt-in (for anyone
		// who later distributes the plugin) and never derives a tier from input.
		if ( ! self::hardening_on() ) { return 'agency'; }
		$d = $this->data();
		if ( 'active' !== $d['status'] ) { return 'free'; }
		$t = $d['tier'];
		return in_array( $t, array( 'free', 'pro', 'agency' ), true ) ? $t : 'free';
	}

	public static function tier_rank( $tier ) {
		$r = array( 'free' => 0, 'pro' => 1, 'agency' => 2 );
		return isset( $r[ $tier ] ) ? $r[ $tier ] : 0;
	}

	/** Does the current tier unlock a module tier? */
	public function unlocks( $module_tier ) {
		return self::tier_rank( $this->tier() ) >= self::tier_rank( $module_tier );
	}

	public function hooks() {
		add_action( 'admin_post_rk_suite_license', array( $this, 'handle_form' ) );
	}

	/** Validate a key. Filterable for a real server; stub otherwise. */
	public function validate( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) { return new WP_Error( 'empty', 'Enter a license key.' ); }

		// A real key server / custom validator always wins if wired.
		$res = apply_filters( 'rk_suite_validate_license', null, $key );
		if ( is_array( $res ) ) { return $res; }
		if ( is_wp_error( $res ) ) { return $res; }

		if ( self::hardening_on() ) {
			// Enforced mode (for distribution): require a real validator or an
			// explicit dev override. A tier is NEVER derived from the key text.
			$dev = ( defined( 'RK_SUITE_DEV_LICENSE' ) && RK_SUITE_DEV_LICENSE )
				|| ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) );
			if ( ! $dev ) {
				return new WP_Error(
					'no_license_server',
					'License enforcement is on but no validator is wired. Connect the "rk_suite_validate_license" filter to your key server, or define RK_SUITE_DEV_LICENSE for local testing.'
				);
			}
			return array( 'valid' => true, 'tier' => 'agency', 'name' => 'Dev license' );
		}

		// Default self-hosted model: the suite is licensed as one unit — no
		// separate key required. Activation just confirms full access.
		return array( 'valid' => true, 'tier' => 'agency', 'name' => 'RK Suite (self-hosted)' );
	}

	public function handle_form() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
		check_admin_referer( 'rk_suite_license' );
		$redirect = admin_url( 'admin.php?page=rk-suite-license' );

		if ( isset( $_POST['deactivate'] ) ) {
			$this->save( array( 'status' => 'inactive', 'tier' => 'free' ) );
			wp_safe_redirect( add_query_arg( 'rk_msg', 'deactivated', $redirect ) ); exit;
		}
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$res = $this->validate( $key );
		if ( is_wp_error( $res ) || empty( $res['valid'] ) ) {
			$this->save( array( 'key' => $key, 'status' => 'invalid', 'tier' => 'free', 'checked' => time() ) );
			if ( is_wp_error( $res ) ) { set_transient( 'rk_suite_lic_err', $res->get_error_message(), 60 ); }
			wp_safe_redirect( add_query_arg( 'rk_msg', 'invalid', $redirect ) ); exit;
		}
		$this->save( array(
			'key' => $key, 'status' => 'active',
			'tier' => in_array( $res['tier'], array( 'free', 'pro', 'agency' ), true ) ? $res['tier'] : 'free',
			'name' => isset( $res['name'] ) ? $res['name'] : '', 'checked' => time(),
		) );
		wp_safe_redirect( add_query_arg( 'rk_msg', 'activated', $redirect ) ); exit;
	}

	public function render_screen() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$d = $this->data();
		RK_Suite_Admin::head( 'rk-suite-license', $this );
		$msg = isset( $_GET['rk_msg'] ) ? sanitize_key( $_GET['rk_msg'] ) : '';
		if ( 'activated' === $msg ) { echo '<div class="notice notice-success"><p>License activated. Tier: <strong>' . esc_html( $this->tier() ) . '</strong>.</p></div>'; }
		if ( 'deactivated' === $msg ) { echo '<div class="notice notice-info"><p>License deactivated.</p></div>'; }
		if ( 'invalid' === $msg ) { $e = get_transient( 'rk_suite_lic_err' ); delete_transient( 'rk_suite_lic_err' ); echo '<div class="notice notice-error"><p>' . esc_html( $e ? $e : 'License key was rejected.' ) . '</p></div>'; }

		echo '<div class="pk-panel"><h3>License</h3>';
		if ( self::hardening_on() ) {
			echo '<div class="notice notice-warning inline"><p><strong>License enforcement is on.</strong> Features stay locked until a key validates via the <code>rk_suite_validate_license</code> filter (or <code>RK_SUITE_DEV_LICENSE</code> for local testing). A tier is never derived from the key text.</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p><strong>Fully licensed.</strong> Your RK Suite is licensed as one unit — no separate key required, and every Pro/Agency feature is unlocked on this site. (To gate features for distribution, define <code>RK_SUITE_LICENSE_HARDENING</code>.)</p></div>';
		}
		$active_display = $this->is_active() || ! self::hardening_on();
		echo '<p>Status: ' . ( $active_display ? '<strong style="color:#0f9d6b;">Active</strong> — tier <strong>' . esc_html( $this->tier() ) . '</strong>' : '<strong style="color:#c07f1a;">' . esc_html( ucfirst( $d['status'] ) ) . '</strong>' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rk_suite_license" />';
		wp_nonce_field( 'rk_suite_license' );
		echo '<p><input type="text" name="license_key" class="regular-text" value="' . esc_attr( $d['key'] ) . '" placeholder="RK-XXXX-XXXX" style="min-width:320px;" /></p>';
		echo '<div class="rk-savebar">';
		submit_button( $this->is_active() ? 'Re-check' : 'Activate', 'primary', 'submit', false );
		if ( $this->is_active() ) { echo ' '; submit_button( 'Deactivate', 'secondary', 'deactivate', false ); }
		echo '</div></form></div>';
		RK_Suite_Admin::foot();
	}
}
