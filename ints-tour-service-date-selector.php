<?php
/**
 * Plugin Name:       INTS Tour Service Date Selector
 * Description:       Adds service booking capabilities (date and time selection) for tourism products and theme parks in WooCommerce.
 * Version:           1.0.0
 * Author:            Imran Bajwa
 * Author URI:        https://profiles.wordpress.org/imbajwa/
 * Text Domain:       ints-tour-service-date-selector
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 5.0
 * WC tested up to:   9.4
 *
 * @package INTSDS
 */

declare( strict_types=1 );

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'INTSDS_VERSION', '1.0.0' );
define( 'INTSDS_PLUGIN_FILE', __FILE__ );
define( 'INTSDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INTSDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INTSDS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare WooCommerce feature compatibility.
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	function (): void {
		// Check WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function (): void {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__(
						'INTS Tour Service Date Selector requires WooCommerce to be installed and active.',
						'ints-tour-service-date-selector'
					);
					echo '</p></div>';
				}
			);
			return;
		}

		// Autoload classes.
		spl_autoload_register(
			function ( string $class_name ): void {
				// Only handle our namespace.
				if ( strpos( $class_name, 'INTSDS\\' ) !== 0 ) {
					return;
				}

				$relative = substr( $class_name, strlen( 'INTSDS\\' ) );
				$parts    = explode( '\\', $relative );

				// Map namespace segments to directories.
				$dir_map = array(
					'Admin'    => 'admin',
					'Frontend' => 'frontend',
				);

				$file_name = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

				if ( ! empty( $parts ) && isset( $dir_map[ $parts[0] ] ) ) {
					$file = INTSDS_PLUGIN_DIR . $dir_map[ $parts[0] ] . '/' . $file_name;
				} else {
					$file = INTSDS_PLUGIN_DIR . 'includes/' . $file_name;
				}

				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		);

		// Boot the plugin.
		\INTSDS\Plugin::instance();
	}
);
