<?php
/**
 * Validation class.
 *
 * @package INTSDS
 */

declare( strict_types=1 );

namespace INTSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Validation
 *
 * Handles all server-side booking field validation.
 */
class Validation {

	/**
	 * Validate booking data for a product.
	 *
	 * Returns true on success, WP_Error on failure.
	 *
	 * @param int      $product_id   Product ID.
	 * @param int|null $variation_id Optional variation ID.
	 * @param string   $date         Submitted date.
	 * @param string   $time         Submitted time.
	 * @return true|\WP_Error
	 */
	public static function validate(
		int $product_id,
		?int $variation_id,
		string $date,
		string $time
	) {
		$service_type = Helper::get_service_type( $product_id, $variation_id );

		// Open Dated: nothing to validate.
		if ( Helper::SERVICE_OPEN_DATED === $service_type ) {
			return true;
		}

		// Sanitize inputs.
		$clean_date = Helper::sanitize_date( $date );
		$clean_time = $time ? Helper::sanitize_time( $time ) : '';

		// Date required for both service types.
		if ( empty( $clean_date ) ) {
			return new \WP_Error(
				'intsds_missing_date',
				Helper::get_date_error()
			);
		}

		// Verify date is not in the past.
		if ( $clean_date < gmdate( 'Y-m-d' ) ) {
			return new \WP_Error(
				'intsds_past_date',
				__( 'Please select a future date for your booking.', 'ints-tour-service-date-selector' )
			);
		}

		$schedule = Helper::get_schedule( $product_id, $variation_id );

		// Check weekday availability.
		if ( ! Helper::is_date_available( $clean_date, $schedule ) ) {
			return new \WP_Error(
				'intsds_unavailable_date',
				__( 'The selected date is not available. Please choose a different date.', 'ints-tour-service-date-selector' )
			);
		}

		// Time required for date+time service type.
		if ( Helper::SERVICE_DATE_TIME === $service_type ) {
			if ( empty( $clean_time ) ) {
				return new \WP_Error(
					'intsds_missing_time',
					__( 'Please select a booking time before adding to cart.', 'ints-tour-service-date-selector' )
				);
			}

			if ( ! Helper::is_time_available( $clean_date, $clean_time, $schedule ) ) {
				return new \WP_Error(
					'intsds_unavailable_time',
					__( 'The selected time is not available for the chosen date. Please select a valid time.', 'ints-tour-service-date-selector' )
				);
			}
		}

		return true;
	}

	/**
	 * Extract and validate booking data from POST data for a product.
	 *
	 * @param int      $product_id   Product ID.
	 * @param int|null $variation_id Optional variation ID.
	 * @return array{valid:bool,date:string,time:string,error:string}
	 */
	public static function validate_from_post( int $product_id, ?int $variation_id ): array {
		// Nonce is verified in Cart::validate_add_to_cart(); values are sanitized via Helper::sanitize_date()/sanitize_time().
		$date = isset( $_POST['intsds_booking_date'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? Helper::sanitize_date( wp_unslash( (string) $_POST['intsds_booking_date'] ) ) // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		$time = isset( $_POST['intsds_booking_time'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? Helper::sanitize_time( wp_unslash( (string) $_POST['intsds_booking_time'] ) ) // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		$result = self::validate( $product_id, $variation_id, $date, $time );

		if ( is_wp_error( $result ) ) {
			return array(
				'valid' => false,
				'date'  => '',
				'time'  => '',
				'error' => $result->get_error_message(),
			);
		}

		return array(
			'valid' => true,
			'date'  => $date,
			'time'  => $time,
			'error' => '',
		);
	}

	/**
	 * Validate cart item booking data (used in checkout validation).
	 *
	 * @param array<string,mixed> $cart_item Cart item array.
	 * @return true|\WP_Error
	 */
	public static function validate_cart_item( array $cart_item ) {
		$product_id   = (int) ( $cart_item['product_id'] ?? 0 );
		$variation_id = (int) ( $cart_item['variation_id'] ?? 0 ) ?: null;
		$service_type = Helper::get_service_type( $product_id, $variation_id );

		if ( Helper::SERVICE_OPEN_DATED === $service_type ) {
			return true;
		}

		$date = (string) ( $cart_item[ Helper::CART_DATE_KEY ] ?? '' );
		$time = (string) ( $cart_item[ Helper::CART_TIME_KEY ] ?? '' );

		return self::validate( $product_id, $variation_id, $date, $time );
	}
}
