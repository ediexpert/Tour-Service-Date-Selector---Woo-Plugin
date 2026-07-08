<?php
/**
 * Main Plugin class.
 *
 * @package INTSDS
 */

declare( strict_types=1 );

namespace INTSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Bootstraps and wires all plugin components.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get or create the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Initialise all subsystems.
	 */
	private function init(): void {
		// Translations for plugins hosted on WordPress.org are loaded automatically
		// since WordPress 4.6; no manual load_plugin_textdomain() call is needed.

		// Admin.
		if ( is_admin() ) {
			( new Admin\Settings() )->register();
			( new Admin\Product_Tab() )->register();
			( new Admin\Product_Fields() )->register();
			( new Admin\Product_Save() )->register();
			( new Admin\Variation_Fields() )->register();
		}

		// Frontend.
		( new Frontend\Display() )->register();
		( new Frontend\Variable_Product() )->register();

		// Assets.
		( new Assets() )->register();

		// Cart.
		( new Cart() )->register();

		// Order meta.
		( new Order_Meta() )->register();

		// Compatibility declarations.
		( new Compatibility() )->register();
	}
}
