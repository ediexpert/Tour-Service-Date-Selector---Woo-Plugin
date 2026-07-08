<?php
/**
 * Frontend Variable Product class.
 *
 * @package INTSDS\Frontend
 */

declare( strict_types=1 );

namespace INTSDS\Frontend;

defined( 'ABSPATH' ) || exit;

use INTSDS\Helper;

/**
 * Class Variable_Product
 *
 * Handles dynamic booking field updates for variable products
 * via WooCommerce variation events.
 */
class Variable_Product {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// No server-side hooks needed for variable product dynamics.
		// All handling is done in frontend.js via WooCommerce variation events.
		// This class is reserved for future server-side augmentation.
	}
}
