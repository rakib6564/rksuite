<?php
/**
 * PHPUnit bootstrap for RK Suite unit tests.
 *
 * These are fast, dependency-free unit tests: instead of loading a full
 * WordPress, they define just the handful of WP functions the units under test
 * touch. This keeps the suite runnable with nothing but `composer install`.
 *
 * @package RK_Suite\Tests
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RK_SUITE_TEST', true );

require __DIR__ . '/stubs.php';

// Autoload composer (PHPUnit).
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require __DIR__ . '/../vendor/autoload.php';
}
