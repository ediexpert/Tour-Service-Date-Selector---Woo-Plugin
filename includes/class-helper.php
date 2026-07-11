<?php
/**
 * Helper utility class.
 *
 * @package INTSDS
 */

declare( strict_types=1 );

namespace INTSDS;

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
	public const META_SERVICE_TYPE   = '_intsds_service_type';
	public const META_SCHEDULE       = '_intsds_weekly_schedule';
	public const META_TIMEZONE       = '_intsds_timezone';
	public const META_CUTOFF         = '_intsds_cutoff';
	public const META_CUTOFF_DAYS    = '_intsds_cutoff_days';
	public const META_CUTOFF_HOURS   = '_intsds_cutoff_hours';
	public const META_CUTOFF_MINUTES = '_intsds_cutoff_minutes';
	public const CART_DATE_KEY       = '_intsds_booking_date';
	public const CART_TIME_KEY       = '_intsds_booking_time';
	public const OPTION_DATE_FORMAT  = 'intsds_date_format';
	public const DEFAULT_DATE_FORMAT = 'F j, Y';

	/**
	 * Booking cutoff reference options.
	 *
	 * Determines which schedule time (per weekday) closes bookings for the
	 * current day, evaluated in the product timezone.
	 */
	public const CUTOFF_NONE  = 'none';
	public const CUTOFF_START = 'start';
	public const CUTOFF_END   = 'end';

	/**
	 * Option keys for configurable UI labels.
	 *
	 * The default label/error strings are translatable — see default_date_label()
	 * and default_date_error() — so they are not stored as constants.
	 */
	public const OPTION_DATE_LABEL   = 'intsds_date_label';
	public const OPTION_DATE_ERROR   = 'intsds_date_error';
	public const OPTION_DELETE_DATA_ON_UNINSTALL  = 'intsds_delete_data_on_uninstall';
	public const DEFAULT_DELETE_DATA_ON_UNINSTALL = 'no';

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
		);
	}

	/**
	 * Return human-readable service type labels.
	 *
	 * @return array<string,string>
	 */
	public static function service_type_labels(): array {
		return array(
			self::SERVICE_OPEN_DATED => __( 'Open Dated', 'ints-tour-service-date-selector' ),
			self::SERVICE_DATE_ONLY  => __( 'Just date, no time', 'ints-tour-service-date-selector' ),
		);
	}

	/**
	 * Return the booking cutoff options (value => label).
	 *
	 * @return array<string,string>
	 */
	public static function cutoff_options(): array {
		return array(
			self::CUTOFF_NONE  => __( 'No time cutoff', 'ints-tour-service-date-selector' ),
			self::CUTOFF_START => __( 'Close the day once its Start Time passes', 'ints-tour-service-date-selector' ),
			self::CUTOFF_END   => __( 'Close the day once its End Time passes', 'ints-tour-service-date-selector' ),
		);
	}

	/**
	 * Return supported booking date format options.
	 *
	 * @return array<string,string>
	 */
	public static function date_format_labels(): array {
		return array(
			'F j, Y' => __( 'June 16, 2026 (F j, Y)', 'ints-tour-service-date-selector' ),
			'j F Y'  => __( '16 June 2026 (j F Y)', 'ints-tour-service-date-selector' ),
			'Y-m-d'  => __( '2026-06-16 (Y-m-d)', 'ints-tour-service-date-selector' ),
			'd-m-Y'  => __( '16-06-2026 (d-m-Y)', 'ints-tour-service-date-selector' ),
			'd/m/Y'  => __( '16/06/2026 (d/m/Y)', 'ints-tour-service-date-selector' ),
			'm/d/Y'  => __( '06/16/2026 (m/d/Y)', 'ints-tour-service-date-selector' ),
		);
	}

	/**
	 * Sanitize a date format key against the allowlist.
	 *
	 * @param string $format Raw format.
	 * @return string
	 */
	public static function sanitize_date_format( string $format ): string {
		$clean   = sanitize_text_field( $format );
		$formats = array_keys( self::date_format_labels() );

		if ( in_array( $clean, $formats, true ) ) {
			return $clean;
		}

		return self::DEFAULT_DATE_FORMAT;
	}

	/**
	 * Get booking date display format from central plugin settings.
	 *
	 * @return string
	 */
	public static function get_date_format(): string {
		$format = get_option( self::OPTION_DATE_FORMAT, self::DEFAULT_DATE_FORMAT );
		return self::sanitize_date_format( (string) $format );
	}

	/**
	 * Sanitize a plain text label / message value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_label( string $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize yes/no option values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_yes_no( string $value ): string {
		$clean = strtolower( sanitize_text_field( $value ) );
		return in_array( $clean, array( 'yes', 'no' ), true ) ? $clean : 'no';
	}

	/**
	 * Whether plugin data should be deleted during uninstall.
	 *
	 * @return bool
	 */
	public static function should_delete_data_on_uninstall(): bool {
		$value = get_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, self::DEFAULT_DELETE_DATA_ON_UNINSTALL );
		return 'yes' === self::sanitize_yes_no( (string) $value );
	}

	/**
	 * Get the configurable date field label.
	 *
	 * @return string
	 */
	public static function get_date_label(): string {
		$label = get_option( self::OPTION_DATE_LABEL, '' );
		$clean = self::sanitize_label( is_string( $label ) ? $label : '' );
		return '' !== $clean ? $clean : self::default_date_label();
	}

	/**
	 * Translatable default for the date field label.
	 *
	 * @return string
	 */
	public static function default_date_label(): string {
		return __( 'Select Date', 'ints-tour-service-date-selector' );
	}

	/**
	 * Get the configurable date validation error message.
	 *
	 * @return string
	 */
	public static function get_date_error(): string {
		$error = get_option( self::OPTION_DATE_ERROR, '' );
		$clean = self::sanitize_label( is_string( $error ) ? $error : '' );
		return '' !== $clean ? $clean : self::default_date_error();
	}

	/**
	 * Translatable default for the date validation error message.
	 *
	 * @return string
	 */
	public static function default_date_error(): string {
		return __( 'Please select a date.', 'ints-tour-service-date-selector' );
	}

	/**
	 * Format a stored Y-m-d booking date for display.
	 *
	 * @param string $date   Date in Y-m-d.
	 * @param string $format Display format.
	 * @return string
	 */
	public static function format_booking_date_for_display( string $date, string $format ): string {
		$clean_date = self::sanitize_date( $date );
		if ( '' === $clean_date ) {
			return $date;
		}

		$timestamp = strtotime( $clean_date . ' 00:00:00' );
		if ( false === $timestamp ) {
			return $clean_date;
		}

		return wp_date( self::sanitize_date_format( $format ), $timestamp );
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
	public static function get_weekday_index( string $date ) {
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

	/**
	 * Get the configured timezone string for a product.
	 *
	 * Timezone is a product-level setting. Falls back to the site timezone.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_timezone_string( int $product_id ): string {
		$tz = get_post_meta( $product_id, self::META_TIMEZONE, true );
		if ( is_string( $tz ) && '' !== $tz ) {
			return $tz;
		}
		return wp_timezone_string();
	}

	/**
	 * Get the configured booking cutoff reference for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string One of CUTOFF_NONE, CUTOFF_START, CUTOFF_END.
	 */
	public static function get_cutoff( int $product_id ): string {
		$cutoff = get_post_meta( $product_id, self::META_CUTOFF, true );
		if ( in_array( $cutoff, array( self::CUTOFF_START, self::CUTOFF_END ), true ) ) {
			return $cutoff;
		}
		return self::CUTOFF_NONE;
	}

	/**
	 * Get the cutoff lead-time components (advance notice) for a product.
	 *
	 * Hours are clamped to 0-23 and minutes to 0-59; use days for longer spans.
	 *
	 * @param int $product_id Product ID.
	 * @return array{days:int,hours:int,minutes:int}
	 */
	public static function get_cutoff_lead( int $product_id ): array {
		return array(
			'days'    => max( 0, (int) get_post_meta( $product_id, self::META_CUTOFF_DAYS, true ) ),
			'hours'   => min( 23, max( 0, (int) get_post_meta( $product_id, self::META_CUTOFF_HOURS, true ) ) ),
			'minutes' => min( 59, max( 0, (int) get_post_meta( $product_id, self::META_CUTOFF_MINUTES, true ) ) ),
		);
	}

	/**
	 * Total cutoff lead time in minutes (for the frontend calendar).
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_cutoff_lead_minutes( int $product_id ): int {
		$lead = self::get_cutoff_lead( $product_id );
		return $lead['days'] * 1440 + $lead['hours'] * 60 + $lead['minutes'];
	}

	/**
	 * Resolve a timezone string to a DateTimeZone object.
	 *
	 * Accepts IANA identifiers (e.g. "Asia/Dubai") and the manual "UTC+X"
	 * offsets produced by wp_timezone_choice(). Falls back to the site timezone.
	 *
	 * @param string $tz Timezone string.
	 * @return \DateTimeZone
	 */
	public static function timezone_object( string $tz ): \DateTimeZone {
		try {
			if ( in_array( $tz, timezone_identifiers_list(), true ) ) {
				return new \DateTimeZone( $tz );
			}
			if ( preg_match( '/^UTC([+-])(\d+)(?:\.(\d+))?$/', $tz, $m ) ) {
				$hours   = (int) $m[2];
				$minutes = isset( $m[3] ) ? (int) round( (float) ( '0.' . $m[3] ) * 60 ) : 0;
				return new \DateTimeZone( sprintf( '%s%02d:%02d', $m[1], $hours, $minutes ) );
			}
		} catch ( \Exception $e ) {
			// Fall through to the site timezone.
		}
		return wp_timezone();
	}

	/**
	 * Current date and time-of-day in the given timezone.
	 *
	 * @param string                  $tz  Timezone string.
	 * @param \DateTimeImmutable|null  $now Optional instant to evaluate (defaults to now). For testing.
	 * @return array{date:string,minutes:int} Y-m-d date and minutes since midnight.
	 */
	public static function now_in_timezone( string $tz, ?\DateTimeImmutable $now = null ): array {
		$zone = self::timezone_object( $tz );
		$now  = $now instanceof \DateTimeImmutable ? $now->setTimezone( $zone ) : new \DateTimeImmutable( 'now', $zone );
		return array(
			'date'    => $now->format( 'Y-m-d' ),
			'minutes' => (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' ),
		);
	}

	/**
	 * Convert an "H:i" time string to minutes since midnight.
	 *
	 * @param string $time Time string.
	 * @return int|null Minutes since midnight, or null if malformed.
	 */
	public static function time_to_minutes( string $time ): ?int {
		if ( preg_match( '/^(\d{2}):(\d{2})$/', $time, $m ) ) {
			return (int) $m[1] * 60 + (int) $m[2];
		}
		return null;
	}

	/**
	 * Whether a booking date is unavailable because it is in the past or has
	 * passed the configured advance-notice cutoff, evaluated in the product
	 * timezone.
	 *
	 * The cutoff deadline for a date D is:
	 *   ( D at its Start/End time ) minus ( days, hours, minutes ).
	 * The date is unavailable once "now" reaches that deadline.
	 *
	 * @param string                                                    $date     Selected date (Y-m-d).
	 * @param array<string,array{enabled:bool,start:string,end:string}> $schedule Normalised schedule.
	 * @param string                                                    $tz       Timezone string.
	 * @param string                                                    $cutoff   Cutoff reference (none|start|end).
	 * @param int                                                       $days     Lead days.
	 * @param int                                                       $hours    Lead hours.
	 * @param int                                                       $minutes  Lead minutes.
	 * @param \DateTimeImmutable|null                                   $now      Optional instant to evaluate (defaults to now). For testing.
	 * @return bool
	 */
	public static function is_past_cutoff( string $date, array $schedule, string $tz, string $cutoff, int $days = 0, int $hours = 0, int $minutes = 0, ?\DateTimeImmutable $now = null ): bool {
		$zone = self::timezone_object( $tz );
		$now  = $now instanceof \DateTimeImmutable ? $now->setTimezone( $zone ) : new \DateTimeImmutable( 'now', $zone );

		// Any date before "today" in the product timezone is unavailable.
		if ( $date < $now->format( 'Y-m-d' ) ) {
			return true;
		}

		// No time cutoff configured — only past dates are blocked.
		if ( self::CUTOFF_NONE !== $cutoff ) {
			$index = self::get_weekday_index( $date );
			if ( false === $index ) {
				return false;
			}
			$day_slug = self::$weekdays[ $index ];
			$ref      = ( self::CUTOFF_START === $cutoff )
				? ( $schedule[ $day_slug ]['start'] ?? '' )
				: ( $schedule[ $day_slug ]['end'] ?? '' );

			if ( null !== self::time_to_minutes( (string) $ref ) ) {
				try {
					$ref_dt   = new \DateTimeImmutable( $date . ' ' . $ref . ':00', $zone );
					$interval = new \DateInterval(
						sprintf( 'P%dDT%dH%dM', max( 0, $days ), max( 0, $hours ), max( 0, $minutes ) )
					);
					$deadline = $ref_dt->sub( $interval );
					if ( $now >= $deadline ) {
						return true;
					}
				} catch ( \Exception $e ) {
					return false;
				}
			}
		}

		return false;
	}
}
