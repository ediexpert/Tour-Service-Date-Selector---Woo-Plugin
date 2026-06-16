<?php
/**
 * Helper utility class.
 *
 * @package TSDS
 */

declare( strict_types=1 );

namespace TSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Helper
 *
 * Static utility methods shared across the plugin.
 */
class Helper {

	/**
	 * Service type constants.
	 */
	public const SERVICE_OPEN_DATED  = 'open_dated';
	public const SERVICE_DATE_ONLY   = 'date_only';
	public const SERVICE_DATE_TIME   = 'date_time';

	/**
	 * Meta key constants.
	 */
	public const META_SERVICE_TYPE   = '_tsds_service_type';
	public const META_SCHEDULE       = '_tsds_weekly_schedule';
	public const CART_DATE_KEY       = '_tsds_booking_date';
	public const CART_TIME_KEY       = '_tsds_booking_time';

	/**
	 * Ordered weekday slugs.
	 *
	 * @var string[]
	 */
	public static array $weekdays = array(
		'sunday',
		'monday',
		'tuesday',
		'wednesday',
		'thursday',
		'friday',
		'saturday',
	);

	/**
	 * Return all valid service type keys.
	 *
	 * @return string[]
	 */
	public static function service_types(): array {
		return array(
			self::SERVICE_OPEN_DATED,
			self::SERVICE_DATE_ONLY,
			self::SERVICE_DATE_TIME,
		);
	}

	/**
	 * Return human-readable service type labels.
	 *
	 * @return array<string,string>
	 */
	public static function service_type_labels(): array {
		return array(
			self::SERVICE_OPEN_DATED => __( 'Open Dated', 'tour-service-date-selector' ),
			self::SERVICE_DATE_ONLY  => __( 'Just date, no time', 'tour-service-date-selector' ),
			self::SERVICE_DATE_TIME  => __( 'Date and time', 'tour-service-date-selector' ),
		);
	}

	/**
	 * Get service type for a product, with variation fallback.
	 *
	 * Priority: variation → parent → default (open_dated).
	 *
	 * @param int      $product_id     Product ID.
	 * @param int|null $variation_id   Optional variation ID.
	 * @return string
	 */
	public static function get_service_type( int $product_id, ?int $variation_id = null ): string {
		if ( $variation_id ) {
			$variation_type = get_post_meta( $variation_id, self::META_SERVICE_TYPE, true );
			if ( $variation_type && in_array( $variation_type, self::service_types(), true ) ) {
				return $variation_type;
			}
		}

		$type = get_post_meta( $product_id, self::META_SERVICE_TYPE, true );
		if ( $type && in_array( $type, self::service_types(), true ) ) {
			return $type;
		}

		return self::SERVICE_OPEN_DATED;
	}

	/**
	 * Get weekly schedule for a product, with variation fallback.
	 *
	 * @param int      $product_id    Product ID.
	 * @param int|null $variation_id  Optional variation ID.
	 * @return array<string,array{enabled:bool,start:string,end:string}>
	 */
	public static function get_schedule( int $product_id, ?int $variation_id = null ): array {
		$raw = null;

		if ( $variation_id ) {
			$raw = get_post_meta( $variation_id, self::META_SCHEDULE, true );
		}

		if ( ! $raw ) {
			$raw = get_post_meta( $product_id, self::META_SCHEDULE, true );
		}

		return self::normalize_schedule( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Normalize schedule data, filling defaults for missing days.
	 *
	 * @param array<string,mixed> $raw Raw schedule array from meta.
	 * @return array<string,array{enabled:bool,start:string,end:string}>
	 */
	public static function normalize_schedule( array $raw ): array {
		$schedule = array();

		foreach ( self::$weekdays as $day ) {
			$schedule[ $day ] = array(
				'enabled' => ! empty( $raw[ $day ]['enabled'] ),
				'start'   => isset( $raw[ $day ]['start'] ) ? sanitize_text_field( $raw[ $day ]['start'] ) : '09:00',
				'end'     => isset( $raw[ $day ]['end'] ) ? sanitize_text_field( $raw[ $day ]['end'] ) : '17:00',
			);
		}

		return $schedule;
	}

	/**
	 * Get the weekday index (0=Sun) from a Y-m-d date string.
	 *
	 * @param string $date Date string in Y-m-d format.
	 * @return int|false
	 */
	public static function get_weekday_index( string $date ): int|false {
		$ts = strtotime( $date );
		if ( false === $ts ) {
			return false;
		}
		return (int) gmdate( 'w', $ts );
	}

	/**
	 * Validate that a given date is an enabled weekday in the schedule.
	 *
	 * @param string                                                    $date     Date in Y-m-d.
	 * @param array<string,array{enabled:bool,start:string,end:string}> $schedule Normalised schedule.
	 * @return bool
	 */
	public static function is_date_available( string $date, array $schedule ): bool {
		$index = self::get_weekday_index( $date );
		if ( false === $index ) {
			return false;
		}
		$day_slug = self::$weekdays[ $index ];
		return ! empty( $schedule[ $day_slug ]['enabled'] );
	}

	/**
	 * Validate that a given time falls within the schedule for a given date.
	 *
	 * @param string                                                    $date     Date in Y-m-d.
	 * @param string                                                    $time     Time in H:i.
	 * @param array<string,array{enabled:bool,start:string,end:string}> $schedule Normalised schedule.
	 * @return bool
	 */
	public static function is_time_available( string $date, string $time, array $schedule ): bool {
		if ( ! self::is_date_available( $date, $schedule ) ) {
			return false;
		}

		$index    = self::get_weekday_index( $date );
		$day_slug = self::$weekdays[ (int) $index ];
		$start    = $schedule[ $day_slug ]['start'] ?? '00:00';
		$end      = $schedule[ $day_slug ]['end'] ?? '23:59';

		return ( $time >= $start && $time <= $end );
	}

	/**
	 * Sanitize a date string to Y-m-d or return empty string.
	 *
	 * @param string $date Raw date input.
	 * @return string
	 */
	public static function sanitize_date( string $date ): string {
		$clean = sanitize_text_field( wp_unslash( $date ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $clean ) && false !== strtotime( $clean ) ) {
			return $clean;
		}
		return '';
	}

	/**
	 * Sanitize a time string to H:i or return empty string.
	 *
	 * @param string $time Raw time input.
	 * @return string
	 */
	public static function sanitize_time( string $time ): string {
		$clean = sanitize_text_field( wp_unslash( $time ) );
		if ( preg_match( '/^\d{2}:\d{2}$/', $clean ) ) {
			return $clean;
		}
		return '';
	}

	/**
	 * Build the schedule data array suitable for JSON encoding (for frontend).
	 *
	 * @param array<string,array{enabled:bool,start:string,end:string}> $schedule Normalised schedule.
	 * @return array<string,mixed>
	 */
	public static function schedule_for_js( array $schedule ): array {
		$out = array();
		foreach ( self::$weekdays as $index => $day ) {
			$out[] = array(
				'index'   => $index,  // 0 = Sunday.
				'day'     => $day,
				'enabled' => ! empty( $schedule[ $day ]['enabled'] ),
				'start'   => $schedule[ $day ]['start'] ?? '09:00',
				'end'     => $schedule[ $day ]['end'] ?? '17:00',
			);
		}
		return $out;
	}

	/**
	 * Return disabled weekday indices (0-6) for a schedule.
	 *
	 * Used to feed Flatpickr's disable option.
	 *
	 * @param array<string,array{enabled:bool,start:string,end:string}> $schedule Normalised schedule.
	 * @return int[]
	 */
	public static function disabled_weekday_indices( array $schedule ): array {
		$disabled = array();
		foreach ( self::$weekdays as $index => $day ) {
			if ( empty( $schedule[ $day ]['enabled'] ) ) {
				$disabled[] = $index;
			}
		}
		return $disabled;
	}
}
