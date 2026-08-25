<?php
/**
 * @package RK_Suite\Tests
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../modules/rk-migrate/includes/class-rk-migrate-media.php';

/**
 * RK_Migrate_Media::url_host_allowed() must block private / loopback / link-local
 * targets (SSRF guard) while permitting public hosts.
 */
final class HostGuardTest extends TestCase {

	private function allowed( $url ) {
		$m = new ReflectionMethod( 'RK_Migrate_Media', 'url_host_allowed' );
		$m->setAccessible( true );
		return $m->invoke( null, $url );
	}

	public function test_blocks_loopback_and_private_ranges() {
		$this->assertFalse( $this->allowed( 'http://127.0.0.1/x.png' ) );
		$this->assertFalse( $this->allowed( 'http://10.0.0.5/x.png' ) );
		$this->assertFalse( $this->allowed( 'http://192.168.1.10/x.png' ) );
		$this->assertFalse( $this->allowed( 'http://169.254.169.254/latest/meta-data' ) );
	}

	public function test_blocks_malformed_urls() {
		$this->assertFalse( $this->allowed( 'not-a-url' ) );
		$this->assertFalse( $this->allowed( '' ) );
	}

	public function test_allows_public_ip() {
		$this->assertTrue( $this->allowed( 'http://93.184.216.34/img.png' ) ); // public literal IP.
	}
}
