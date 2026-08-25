<?php
/**
 * @package RK_Suite\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'RK_ADMIN_EMAIL_STUB' ) ) {
	define( 'RK_ADMIN_EMAIL_STUB', 'admin@example.com' );
}
// admin_email lookup used by store_config.
$GLOBALS['rk_test_options']['admin_email'] = RK_ADMIN_EMAIL_STUB;

require_once __DIR__ . '/../modules/rk-elements/includes/class-rk-elements-contact.php';

/**
 * RK_Elements_Contact::store_config() must give a stable opaque key and refuse
 * to trust a bad recipient (falls back to the site admin), so the widget cannot
 * be turned into an open mail relay.
 */
final class ContactConfigTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rk_test_options']['rk_elements_cf_configs'] = array();
		$GLOBALS['rk_test_options']['admin_email'] = RK_ADMIN_EMAIL_STUB;
	}

	public function test_key_is_stable_for_same_widget_id() {
		$a = RK_Elements_Contact::store_config( 'abc123', $this->cfg( 'team@example.com' ) );
		$b = RK_Elements_Contact::store_config( 'abc123', $this->cfg( 'team@example.com' ) );
		$this->assertSame( $a, $b );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{20}$/', $a );
	}

	public function test_invalid_recipient_falls_back_to_admin() {
		$key  = RK_Elements_Contact::store_config( 'wid9', $this->cfg( 'not-an-email' ) );
		$all  = get_option( 'rk_elements_cf_configs' );
		$this->assertSame( RK_ADMIN_EMAIL_STUB, $all[ $key ]['notify'] );
	}

	public function test_valid_recipient_is_kept() {
		$key = RK_Elements_Contact::store_config( 'wid9', $this->cfg( 'team@example.com' ) );
		$all = get_option( 'rk_elements_cf_configs' );
		$this->assertSame( 'team@example.com', $all[ $key ]['notify'] );
	}

	private function cfg( $notify ) {
		return array( 'notify' => $notify, 'subject' => 'Hi', 'autoresponder' => false, 'ar_body' => '', 'success' => 'ok' );
	}
}
