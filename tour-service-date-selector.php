<?php
/**
 * Plugin Name:       Tour Service Date Selector
 * Plugin URI:        https://example.com/tour-service-date-selector
 * Description:       Adds service booking capabilities (date and time selection) for tourism products and theme parks in WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Imran Bajwa
 * Author URI:        https://intservicesllc.com
 * Company:           INT SERVICES LLC
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tour-service-date-selector
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package TSDS
 */

declare( strict_types=1 );

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'TSDS_VERSION', '1.0.0' );
define( 'TSDS_PLUGIN_FILE', __FILE__ );
define( 'TSDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSDS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
						'Tour Service Date Selector requires WooCommerce to be installed and active.',
						'tour-service-date-selector'
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
				if ( strpos( $class_name, 'TSDS\\' ) !== 0 ) {
					return;
				}

				$relative = substr( $class_name, strlen( 'TSDS\\' ) );
				$parts    = explode( '\\', $relative );

				// Map namespace segments to directories.
				$dir_map = array(
					'Admin'    => 'admin',
					'Frontend' => 'frontend',
				);

				$file_name = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

				if ( ! empty( $parts ) && isset( $dir_map[ $parts[0] ] ) ) {
					$file = TSDS_PLUGIN_DIR . $dir_map[ $parts[0] ] . '/' . $file_name;
				} else {
					$file = TSDS_PLUGIN_DIR . 'includes/' . $file_name;
				}

				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		);

		// Boot the plugin.
		\TSDS\Plugin::instance();
	}
);
