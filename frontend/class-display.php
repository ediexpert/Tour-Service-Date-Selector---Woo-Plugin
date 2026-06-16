<?php
/**
 * Frontend Display class.
 *
 * @package TSDS\Frontend
 */

declare( strict_types=1 );

namespace TSDS\Frontend;

defined( 'ABSPATH' ) || exit;

use TSDS\Helper;

/**
 * Class Display
 *
 * Renders the booking fields on the single product page.
 */
class Display {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_booking_fields' ) );
	}

	/**
	 * Render booking fields before the add-to-cart button.
	 */
	public function render_booking_fields(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$product_id   = $product->get_id();
		$service_type = Helper::get_service_type( $product_id );
		$is_variable  = $product->is_type( 'variable' );

		// For variable products, always render the wrapper (JS will show/hide).
		// For simple products, skip if open-dated.
		if ( ! $is_variable && Helper::SERVICE_OPEN_DATED === $service_type ) {
			return;
		}

		$schedule = Helper::get_schedule( $product_id );

		// Load the template.
		$template = TSDS_PLUGIN_DIR . 'templates/booking-fields.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
