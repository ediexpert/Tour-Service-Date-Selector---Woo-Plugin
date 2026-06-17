<?php
/**
 * Cart handler class.
 *
 * @package TSDS
 */

declare( strict_types=1 );

namespace TSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cart
 *
 * Handles cart item data storage, display, and validation.
 */
class Cart {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Add to cart validation.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );

		// Store booking data in cart item.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );

		// Display booking data in cart and checkout.
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

		// Checkout validation.
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
	}

	/**
	 * Validate booking fields on add-to-cart.
	 *
	 * @param bool $passed      Whether validation passed.
	 * @param int  $product_id  Product ID.
	 * @param int  $quantity    Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( bool $passed, int $product_id, int $quantity ): bool {
		unset( $quantity );

		$variation_id = isset( $_POST['variation_id'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? (int) wp_unslash( (string) $_POST['variation_id'] ) // phpcs:ignore WordPress.Security.NonceVerification
			: 0;

		$variation_id_or_null = $variation_id > 0 ? $variation_id : null;

		$nonce = isset( $_POST['tsds_nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_text_field( wp_unslash( (string) $_POST['tsds_nonce'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: '';

		// Verify nonce.
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'tsds_add_to_cart' ) ) {

			// Allow nonce bypass only for truly open-dated selection,
			// including variation-level service type overrides.
			$service_type = Helper::get_service_type( $product_id, $variation_id_or_null );
			if ( Helper::SERVICE_OPEN_DATED === $service_type ) {
				return $passed;
			}

			// For other service types, require nonce.
			// This blocks direct/scripted submissions without nonce.
			wc_add_notice(
				__( 'Security check failed. Please refresh the page and try again.', 'tour-service-date-selector' ),
				'error'
			);
			return false;
		}

		$result = Validation::validate_from_post( $product_id, $variation_id_or_null );

		if ( ! $result['valid'] ) {
			wc_add_notice( $result['error'], 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Store booking data in cart item data.
	 *
	 * @param array<string,mixed> $cart_item_data Existing cart item data.
	 * @param int                 $product_id     Product ID.
	 * @param int                 $variation_id   Variation ID.
	 * @return array<string,mixed>
	 */
	public function add_cart_item_data(
		array $cart_item_data,
		int $product_id,
		int $variation_id
	): array {
		$variation_id_or_null = $variation_id > 0 ? $variation_id : null;
		$service_type         = Helper::get_service_type( $product_id, $variation_id_or_null );

		if ( Helper::SERVICE_OPEN_DATED === $service_type ) {
			return $cart_item_data;
		}

		// Nonce already verified in validate_add_to_cart; safe to read POST here.
		// phpcs:disable WordPress.Security.NonceVerification
		$date = isset( $_POST['tsds_booking_date'] )
			? Helper::sanitize_date( wp_unslash( (string) $_POST['tsds_booking_date'] ) )
			: '';

		$time = isset( $_POST['tsds_booking_time'] )
			? Helper::sanitize_time( wp_unslash( (string) $_POST['tsds_booking_time'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification

		if ( $date ) {
			$cart_item_data[ Helper::CART_DATE_KEY ] = $date;
		}

		if ( $time && Helper::SERVICE_DATE_TIME === $service_type ) {
			$cart_item_data[ Helper::CART_TIME_KEY ] = $time;
		}

		// Unique key ensures separate cart items for different dates/times.
		$cart_item_data['tsds_unique_key'] = md5( $product_id . $variation_id . $date . $time );

		return $cart_item_data;
	}

	/**
	 * Display booking data in cart/checkout line items.
	 *
	 * @param array<int,array{name:string,value:string}> $item_data  Existing item data.
	 * @param array<string,mixed>                        $cart_item  Cart item.
	 * @return array<int,array{name:string,value:string}>
	 */
	public function get_item_data( array $item_data, array $cart_item ): array {
		if ( ! empty( $cart_item[ Helper::CART_DATE_KEY ] ) ) {
			$display_date = Helper::format_booking_date_for_display(
				(string) $cart_item[ Helper::CART_DATE_KEY ],
				Helper::get_date_format()
			);

			$item_data[] = array(
				'name'  => Helper::get_date_label(),
				'value' => esc_html( $display_date ),
			);
		}

		if ( ! empty( $cart_item[ Helper::CART_TIME_KEY ] ) ) {
			$item_data[] = array(
				'name'  => __( 'Time', 'tour-service-date-selector' ),
				'value' => esc_html( $cart_item[ Helper::CART_TIME_KEY ] ),
			);
		}

		return $item_data;
	}

	/**
	 * Validate all cart items before order creation (checkout validation).
	 */
	public function check_cart_items(): void {
		if ( ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$result = Validation::validate_cart_item( $cart_item );

			if ( is_wp_error( $result ) ) {
				$product = $cart_item['data'] ?? null;
				$name    = $product instanceof \WC_Product ? $product->get_name() : '';

				wc_add_notice(
					sprintf(
						/* translators: 1: product name 2: error message */
						__( 'Booking error for &ldquo;%1$s&rdquo;: %2$s', 'tour-service-date-selector' ),
						esc_html( $name ),
						esc_html( $result->get_error_message() )
					),
					'error'
				);
			}
		}
	}
}
