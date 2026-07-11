<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the plugin classes under test in isolation (no WordPress runtime).
 * WordPress functions are stubbed per-test with Brain Monkey.
 *
 * @package INTSDS
 */

// The class files guard on ABSPATH; define it so they don't exit on include.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Load the units under test directly (the plugin autoloader needs WP; we don't).
require dirname( __DIR__ ) . '/includes/class-helper.php';
