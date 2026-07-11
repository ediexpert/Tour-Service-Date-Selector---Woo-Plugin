<?php
/**
 * Admin Product Save class.
 *
 * @package INTSDS\Admin
 */

declare( strict_types=1 );

namespace INTSDS\Admin;

defined( 'ABSPATH' ) || exit;

use INTSDS\Helper;

/**
 * Class Product_Save
 *
 * Saves Tour Service Settings from the product edit screen.
 */
class Product_Save {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Save product meta.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save( int $product_id ): void {
		// Capability check.
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		// Nonce verification.
		if ( ! isset( $_POST['intsds_product_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['intsds_product_nonce'] ) ),
				'intsds_save_product_' . $product_id
			)
		) {
			return;
		}

		// Service type.
		$service_type = isset( $_POST['intsds_service_type'] )
			? sanitize_text_field( wp_unslash( $_POST['intsds_service_type'] ) )
			: Helper::SERVICE_OPEN_DATED;

		if ( ! in_array( $service_type, Helper::service_types(), true ) ) {
			$service_type = Helper::SERVICE_OPEN_DATED;
		}

		update_post_meta( $product_id, Helper::META_SERVICE_TYPE, $service_type );

		// Timezone (product-level). Accept IANA identifiers and wp_timezone_choice()
		// manual "UTC+X" offsets; fall back to the site timezone for anything else.
		$timezone_raw = isset( $_POST['intsds_timezone'] )
			? sanitize_text_field( wp_unslash( $_POST['intsds_timezone'] ) )
			: '';
		$valid_tz     = in_array( $timezone_raw, timezone_identifiers_list(), true )
			|| preg_match( '/^UTC([+-])(\d+)(?:\.\d+)?$/', $timezone_raw );
		update_post_meta( $product_id, Helper::META_TIMEZONE, $valid_tz ? $timezone_raw : wp_timezone_string() );

		// Booking cutoff reference.
		$cutoff = isset( $_POST['intsds_cutoff'] )
			? sanitize_text_field( wp_unslash( $_POST['intsds_cutoff'] ) )
			: Helper::CUTOFF_NONE;
		if ( ! array_key_exists( $cutoff, Helper::cutoff_options() ) ) {
			$cutoff = Helper::CUTOFF_NONE;
		}
		update_post_meta( $product_id, Helper::META_CUTOFF, $cutoff );

		// Cutoff lead time (advance notice). Hours 0-23, minutes 0-59, days 0+.
		$cutoff_days    = isset( $_POST['intsds_cutoff_days'] ) ? absint( wp_unslash( $_POST['intsds_cutoff_days'] ) ) : 0;
		$cutoff_hours   = isset( $_POST['intsds_cutoff_hours'] ) ? min( 23, absint( wp_unslash( $_POST['intsds_cutoff_hours'] ) ) ) : 0;
		$cutoff_minutes = isset( $_POST['intsds_cutoff_minutes'] ) ? min( 59, absint( wp_unslash( $_POST['intsds_cutoff_minutes'] ) ) ) : 0;
		update_post_meta( $product_id, Helper::META_CUTOFF_DAYS, $cutoff_days );
		update_post_meta( $product_id, Helper::META_CUTOFF_HOURS, $cutoff_hours );
		update_post_meta( $product_id, Helper::META_CUTOFF_MINUTES, $cutoff_minutes );

		// Weekly schedule.
		$schedule_raw = isset( $_POST['intsds_schedule'] ) && is_array( $_POST['intsds_schedule'] )
			? $_POST['intsds_schedule'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: array();

		$schedule = $this->sanitize_schedule( $schedule_raw );
		update_post_meta( $product_id, Helper::META_SCHEDULE, $schedule );
	}

	/**
	 * Sanitize the weekly schedule input array.
	 *
	 * @param array<string,mixed> $raw Raw schedule from POST.
	 * @return array<string,array{enabled:bool,start:string,end:string}>
	 */
	private function sanitize_schedule( array $raw ): array {
		$schedule = array();

		foreach ( Helper::$weekdays as $day ) {
			$day_data = is_array( $raw[ $day ] ?? null ) ? $raw[ $day ] : array();

			$enabled = ! empty( $day_data['enabled'] );
			$start   = isset( $day_data['start'] )
				? $this->sanitize_time( wp_unslash( (string) $day_data['start'] ) )
				: '09:00';
			$end     = isset( $day_data['end'] )
				? $this->sanitize_time( wp_unslash( (string) $day_data['end'] ) )
				: '17:00';

			$schedule[ $day ] = array(
				'enabled' => $enabled,
				'start'   => $start ?: '09:00',
				'end'     => $end ?: '17:00',
			);
		}

		return $schedule;
	}

	/**
	 * Sanitize a time string to H:i format.
	 *
	 * @param string $time Raw time string.
	 * @return string
	 */
	private function sanitize_time( string $time ): string {
		$clean = sanitize_text_field( $time );
		if ( preg_match( '/^\d{2}:\d{2}$/', $clean ) ) {
			return $clean;
		}
		return '';
	}
}
