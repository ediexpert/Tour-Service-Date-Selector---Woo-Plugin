<?php
/**
 * Admin Product Tab class.
 *
 * @package TSDS\Admin
 */

declare( strict_types=1 );

namespace TSDS\Admin;

defined( 'ABSPATH' ) || exit;

use TSDS\Helper;

/**
 * Class Product_Tab
 *
 * Registers the "Tour Service Settings" tab in WooCommerce product data.
 */
class Product_Tab {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
	}

	/**
	 * Add the Tour Service Settings tab.
	 *
	 * @param array<string,array<string,mixed>> $tabs Existing tabs.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['tsds_tour_service'] = array(
			'label'    => __( 'Tour Service Settings', 'tour-service-date-selector' ),
			'target'   => 'tsds_tour_service_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 60,
		);

		return $tabs;
	}
}
