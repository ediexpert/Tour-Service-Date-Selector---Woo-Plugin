<?php
/**
 * Main Plugin class.
 *
 * @package TSDS
 */

declare( strict_types=1 );

namespace TSDS;

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
		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin.
		if ( is_admin() ) {
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

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'tour-service-date-selector',
			false,
			dirname( TSDS_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
