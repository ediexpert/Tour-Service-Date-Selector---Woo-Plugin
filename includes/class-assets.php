<?php
/**
 * Assets class.
 *
 * @package TSDS
 */

declare( strict_types=1 );

namespace TSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 *
 * Manages enqueueing of scripts and styles.
 */
class Assets {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Enqueue frontend assets on single product pages only when needed.
	 */
	public function enqueue_frontend(): void {
		// Only on single product pages, and not in Elementor editor.
		if ( ! is_product() || $this->is_elementor_editor() ) {
			return;
		}

		$product = wc_get_product();
		if ( ! $product ) {
			return;
		}

		// Check if the product (or any of its variations) requires booking fields.
		if ( ! $this->product_needs_booking_assets( $product ) ) {
			return;
		}

		// Flatpickr CSS.
		wp_enqueue_style(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);

		// Plugin frontend CSS.
		wp_enqueue_style(
			'tsds-frontend',
			TSDS_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'flatpickr' ),
			TSDS_VERSION
		);

		// Flatpickr JS.
		wp_enqueue_script(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr',
			array(),
			'4.6.13',
			true
		);

		// Plugin frontend JS.
		wp_enqueue_script(
			'tsds-frontend',
			TSDS_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'flatpickr', 'jquery' ),
			TSDS_VERSION,
			true
		);

		// Localise script data.
		wp_localize_script(
			'tsds-frontend',
			'tsdsData',
			$this->get_frontend_script_data( $product )
		);
	}

	/**
	 * Build the data array for wp_localize_script.
	 *
	 * @param \WC_Product $product The product.
	 * @return array<string,mixed>
	 */
	private function get_frontend_script_data( \WC_Product $product ): array {
		$product_id   = $product->get_id();
		$service_type = Helper::get_service_type( $product_id );
		$schedule     = Helper::get_schedule( $product_id );

		$data = array(
			'productId'       => $product_id,
			'productType'     => $product->get_type(),
			'serviceType'     => $service_type,
			'displayDateFormat' => Helper::get_date_format(),
			'schedule'        => Helper::schedule_for_js( $schedule ),
			'disabledWeekdays'=> Helper::disabled_weekday_indices( $schedule ),
			'nonce'           => wp_create_nonce( 'tsds_add_to_cart' ),
			'i18n'            => array(
				'selectDate'       => Helper::get_date_error(),
				'selectDateTime'   => __( 'Please select a date and time.', 'tour-service-date-selector' ),
				'selectTime'       => __( 'Please select a time.', 'tour-service-date-selector' ),
				'invalidDate'      => __( 'The selected date is not available.', 'tour-service-date-selector' ),
				'invalidTime'      => __( 'The selected time is not available for this date.', 'tour-service-date-selector' ),
				'dateLabel'        => Helper::get_date_label(),
				'timeLabel'        => __( 'Select Time', 'tour-service-date-selector' ),
				'selectedDate'     => __( 'Selected Date:', 'tour-service-date-selector' ),
				'openBooking'      => __( 'Choose date and time', 'tour-service-date-selector' ),
				'closeBooking'     => __( 'Hide date and time', 'tour-service-date-selector' ),
			),
			// Variations data will be populated inline for variable products.
			'variations'      => array(),
		);

		// For variable products, include per-variation data.
		if ( $product->is_type( 'variable' ) ) {
			$data['variations'] = $this->get_variations_data( $product );
		}

		return $data;
	}

	/**
	 * Build variations data array for JS.
	 *
	 * @param \WC_Product_Variable $product Variable product.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_variations_data( \WC_Product_Variable $product ): array {
		$out      = array();
		$children = $product->get_children();
		$parent   = $product->get_id();

		foreach ( $children as $variation_id ) {
			$service_type = Helper::get_service_type( $parent, $variation_id );
			$schedule     = Helper::get_schedule( $parent, $variation_id );

			$out[ $variation_id ] = array(
				'serviceType'      => $service_type,
				'schedule'         => Helper::schedule_for_js( $schedule ),
				'disabledWeekdays' => Helper::disabled_weekday_indices( $schedule ),
			);
		}

		return $out;
	}

	/**
	 * Enqueue admin assets on the product edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'tsds-admin',
			TSDS_PLUGIN_URL . 'assets/css/admin.css',
			array( 'woocommerce_admin_styles' ),
			TSDS_VERSION
		);

		wp_enqueue_script(
			'tsds-admin',
			TSDS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'woocommerce_admin' ),
			TSDS_VERSION,
			true
		);

		wp_localize_script(
			'tsds-admin',
			'tsdsAdmin',
			array(
				'weekdays' => array(
					__( 'Sunday', 'tour-service-date-selector' ),
					__( 'Monday', 'tour-service-date-selector' ),
					__( 'Tuesday', 'tour-service-date-selector' ),
					__( 'Wednesday', 'tour-service-date-selector' ),
					__( 'Thursday', 'tour-service-date-selector' ),
					__( 'Friday', 'tour-service-date-selector' ),
					__( 'Saturday', 'tour-service-date-selector' ),
				),
			)
		);
	}

	/**
	 * Check whether a product requires booking assets to be loaded.
	 *
	 * @param \WC_Product $product Product instance.
	 * @return bool
	 */
	private function product_needs_booking_assets( \WC_Product $product ): bool {
		$product_id   = $product->get_id();
		$service_type = Helper::get_service_type( $product_id );

		if ( Helper::SERVICE_OPEN_DATED !== $service_type ) {
			return true;
		}

		// For variable products, check if any variation overrides.
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$vtype = get_post_meta( $variation_id, Helper::META_SERVICE_TYPE, true );
				if ( $vtype && Helper::SERVICE_OPEN_DATED !== $vtype ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Detect if we are in the Elementor editor context.
	 *
	 * @return bool
	 */
	private function is_elementor_editor(): bool {
		return (
			isset( $_GET['elementor-preview'] ) || // phpcs:ignore WordPress.Security.NonceVerification
			( function_exists( '\Elementor\Plugin::$instance' ) &&
				isset( \Elementor\Plugin::$instance->editor ) &&
				\Elementor\Plugin::$instance->editor->is_edit_mode() )
		);
	}
}
