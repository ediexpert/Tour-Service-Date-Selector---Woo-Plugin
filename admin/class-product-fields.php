<?php
/**
 * Admin Product Fields class.
 *
 * @package INTSDS\Admin
 */

declare( strict_types=1 );

namespace INTSDS\Admin;

defined( 'ABSPATH' ) || exit;

use INTSDS\Helper;

/**
 * Class Product_Fields
 *
 * Renders the Tour Service Settings tab panel HTML.
 */
class Product_Fields {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
	}

	/**
	 * Render the tab panel content.
	 */
	public function render_panel(): void {
		global $post;

		$product_id   = (int) $post->ID;
		$service_type = Helper::get_service_type( $product_id );
		$schedule     = Helper::get_schedule( $product_id );
		$timezone     = Helper::get_timezone_string( $product_id );
		$cutoff       = Helper::get_cutoff( $product_id );

		echo '<div id="intsds_tour_service_data" class="panel woocommerce_options_panel intsds-admin-panel">';

		// Nonce field.
		wp_nonce_field( 'intsds_save_product_' . $product_id, 'intsds_product_nonce' );

		echo '<div class="options_group">';

		// Service type dropdown.
		woocommerce_wp_select(
			array(
				'id'      => 'intsds_service_type',
				'name'    => 'intsds_service_type',
				'label'   => __( 'Service Type', 'ints-tour-service-date-selector' ),
				'value'   => $service_type,
				'options' => Helper::service_type_labels(),
				'desc_tip' => true,
				'description' => __( 'Choose how this product handles booking dates and times.', 'ints-tour-service-date-selector' ),
			)
		);

		echo '</div>'; // .options_group

		// Weekly schedule section — shown/hidden by JS.
		$hidden = ( Helper::SERVICE_OPEN_DATED === $service_type ) ? ' style="display:none;"' : '';
		echo '<div id="intsds-weekly-schedule"' . $hidden . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Timezone + booking cutoff (product-level).
		echo '<div class="options_group intsds-availability-group">';

		echo '<p class="form-field intsds-timezone-field">';
		echo '<label for="intsds_timezone">' . esc_html__( 'Timezone', 'ints-tour-service-date-selector' ) . '</label>';
		echo '<select id="intsds_timezone" name="intsds_timezone" class="select short">';
		// wp_timezone_choice() returns an escaped <option>/<optgroup> list built from the IANA database.
		echo wp_timezone_choice( $timezone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</select>';
		echo '<span class="description">' . esc_html__( 'Timezone used to evaluate date availability and the daily cutoff.', 'ints-tour-service-date-selector' ) . '</span>';
		echo '</p>';

		woocommerce_wp_select(
			array(
				'id'          => 'intsds_cutoff',
				'name'        => 'intsds_cutoff',
				'label'       => __( 'Booking Cutoff', 'ints-tour-service-date-selector' ),
				'value'       => $cutoff,
				'options'     => Helper::cutoff_options(),
				'desc_tip'    => true,
				'description' => __( 'Choose which schedule time (Start or End) a date is measured against. The lead time below sets how far before that time booking closes.', 'ints-tour-service-date-selector' ),
			)
		);

		// Advance-notice lead time (Days / Hours / Minutes before the reference time).
		$lead   = Helper::get_cutoff_lead( $product_id );
		$hidden_lead = ( Helper::CUTOFF_NONE === $cutoff ) ? ' style="display:none;"' : '';
		echo '<div class="intsds-cutoff-lead"' . $hidden_lead . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<p class="form-field intsds-cutoff-lead__intro">';
		esc_html_e( 'Lead time before the reference time when a date stops accepting bookings. Example: reference End Time 16:00 with 2 hours closes that day at 14:00; 3 days requires booking at least 3 days ahead.', 'ints-tour-service-date-selector' );
		echo '</p>';

		woocommerce_wp_text_input(
			array(
				'id'                => 'intsds_cutoff_days',
				'name'              => 'intsds_cutoff_days',
				'label'             => __( 'Cutoff lead — Days', 'ints-tour-service-date-selector' ),
				'value'             => (string) $lead['days'],
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => 'intsds_cutoff_hours',
				'name'              => 'intsds_cutoff_hours',
				'label'             => __( 'Cutoff lead — Hours', 'ints-tour-service-date-selector' ),
				'value'             => (string) $lead['hours'],
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '23',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => 'intsds_cutoff_minutes',
				'name'              => 'intsds_cutoff_minutes',
				'label'             => __( 'Cutoff lead — Minutes', 'ints-tour-service-date-selector' ),
				'value'             => (string) $lead['minutes'],
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '59',
					'step' => '1',
				),
			)
		);

		echo '</div>'; // .intsds-cutoff-lead

		echo '</div>'; // .intsds-availability-group

		echo '<h4 class="intsds-schedule-heading">';
		esc_html_e( 'Weekly Schedule', 'ints-tour-service-date-selector' );
		echo '</h4>';

		echo '<p class="intsds-schedule-description">';
		esc_html_e( 'Configure which days of the week are available for booking and their operating hours.', 'ints-tour-service-date-selector' );
		echo '</p>';

		echo '<table class="intsds-schedule-table widefat">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Day', 'ints-tour-service-date-selector' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'ints-tour-service-date-selector' ) . '</th>';
		echo '<th>' . esc_html__( 'Start Time', 'ints-tour-service-date-selector' ) . '</th>';
		echo '<th>' . esc_html__( 'End Time', 'ints-tour-service-date-selector' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		$day_labels = array(
			'sunday'    => __( 'Sunday', 'ints-tour-service-date-selector' ),
			'monday'    => __( 'Monday', 'ints-tour-service-date-selector' ),
			'tuesday'   => __( 'Tuesday', 'ints-tour-service-date-selector' ),
			'wednesday' => __( 'Wednesday', 'ints-tour-service-date-selector' ),
			'thursday'  => __( 'Thursday', 'ints-tour-service-date-selector' ),
			'friday'    => __( 'Friday', 'ints-tour-service-date-selector' ),
			'saturday'  => __( 'Saturday', 'ints-tour-service-date-selector' ),
		);

		foreach ( Helper::$weekdays as $day_slug ) {
			$day_data = $schedule[ $day_slug ] ?? array(
				'enabled' => false,
				'start'   => '09:00',
				'end'     => '17:00',
			);

			$enabled = ! empty( $day_data['enabled'] );
			$start   = $day_data['start'] ?? '09:00';
			$end     = $day_data['end'] ?? '17:00';
			$label   = $day_labels[ $day_slug ] ?? ucfirst( $day_slug );

			echo '<tr class="intsds-schedule-row">';
			echo '<td class="intsds-schedule-day">' . esc_html( $label ) . '</td>';

			// Enabled checkbox.
			echo '<td class="intsds-schedule-enabled">';
			echo '<input type="checkbox"'
				. ' id="intsds_schedule_' . esc_attr( $day_slug ) . '_enabled"'
				. ' name="intsds_schedule[' . esc_attr( $day_slug ) . '][enabled]"'
				. ' value="1"'
				. ( $enabled ? ' checked="checked"' : '' )
				. ' class="intsds-day-enabled-cb" />';
			echo '</td>';

			// Start time.
			echo '<td class="intsds-schedule-start">';
			echo '<input type="time"'
				. ' id="intsds_schedule_' . esc_attr( $day_slug ) . '_start"'
				. ' name="intsds_schedule[' . esc_attr( $day_slug ) . '][start]"'
				. ' value="' . esc_attr( $start ) . '"'
				. ' class="intsds-time-input"'
				. ' step="900" />';
			echo '</td>';

			// End time.
			echo '<td class="intsds-schedule-end">';
			echo '<input type="time"'
				. ' id="intsds_schedule_' . esc_attr( $day_slug ) . '_end"'
				. ' name="intsds_schedule[' . esc_attr( $day_slug ) . '][end]"'
				. ' value="' . esc_attr( $end ) . '"'
				. ' class="intsds-time-input"'
				. ' step="900" />';
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>'; // #intsds-weekly-schedule

		echo '</div>'; // .panel
	}
}
