<?php
/**
 * @package RK_Suite\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * The JSON-LD flags RK SEO uses must prevent a `</script>` breakout when a field
 * value contains hostile markup.
 */
final class SchemaEscapeTest extends TestCase {

	const FLAGS = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;

	public function test_script_tag_cannot_break_out() {
		$data = array( 'name' => 'Acme </script><script>alert(1)</script>' );
		$json = json_encode( $data, self::FLAGS );
		$this->assertStringNotContainsString( '</script>', $json );
		$this->assertStringNotContainsString( '<', $json );
		$this->assertStringNotContainsString( '>', $json );
	}

	public function test_ampersand_and_quotes_are_escaped() {
		$data = array( 'name' => 'Tom & Jerry "Co"' );
		$json = json_encode( $data, self::FLAGS );
		$this->assertStringNotContainsString( '&', $json );
		$this->assertStringContainsString( '&', $json );
	}
}
