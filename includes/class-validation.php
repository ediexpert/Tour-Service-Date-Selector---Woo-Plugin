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

		$schedule = Helper::get_schedule( $product_id, $variation_id );
		$timezone = Helper::get_timezone_string( $product_id );
		$cutoff   = Helper::get_cutoff( $product_id );
		$lead     = Helper::get_cutoff_lead( $product_id );

		// Check weekday availability.
		if ( ! Helper::is_date_available( $clean_date, $schedule ) ) {
			return new \WP_Error(
				'intsds_unavailable_date',
				__( 'The selected date is not available. Please choose a different date.', 'ints-tour-service-date-selector' )
			);
		}

		// Reject past dates and dates whose advance-notice cutoff has passed,
		// evaluated in the product timezone.
		if ( Helper::is_past_cutoff( $clean_date, $schedule, $timezone, $cutoff, $lead['days'], $lead['hours'], $lead['minutes'] ) ) {
			$now = Helper::now_in_timezone( $timezone );
			$message = ( $clean_date < $now['date'] )
				? __( 'Please select a future date for your booking.', 'ints-tour-service-date-selector' )
				: __( 'Bookings for the selected date have closed. Please choose another date.', 'ints-tour-service-date-selector' );
			return new \WP_Error( 'intsds_past_date', $message );
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
