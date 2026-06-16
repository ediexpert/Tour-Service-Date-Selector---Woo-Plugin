<?php
/**
 * Admin Product Fields class.
 *
 * @package TSDS\Admin
 */

declare( strict_types=1 );

namespace TSDS\Admin;

defined( 'ABSPATH' ) || exit;

use TSDS\Helper;

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

		echo '<div id="tsds_tour_service_data" class="panel woocommerce_options_panel tsds-admin-panel">';

		// Nonce field.
		wp_nonce_field( 'tsds_save_product_' . $product_id, 'tsds_product_nonce' );

		echo '<div class="options_group">';

		// Service type dropdown.
		woocommerce_wp_select(
			array(
				'id'      => 'tsds_service_type',
				'name'    => 'tsds_service_type',
				'label'   => __( 'Service Type', 'tour-service-date-selector' ),
				'value'   => $service_type,
				'options' => Helper::service_type_labels(),
				'desc_tip' => true,
				'description' => __( 'Choose how this product handles booking dates and times.', 'tour-service-date-selector' ),
			)
		);

		echo '</div>'; // .options_group

		// Weekly schedule section — shown/hidden by JS.
		$hidden = ( Helper::SERVICE_OPEN_DATED === $service_type ) ? ' style="display:none;"' : '';
		echo '<div id="tsds-weekly-schedule"' . $hidden . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<h4 class="tsds-schedule-heading">';
		esc_html_e( 'Weekly Schedule', 'tour-service-date-selector' );
		echo '</h4>';

		echo '<p class="tsds-schedule-description">';
		esc_html_e( 'Configure which days of the week are available for booking and their operating hours.', 'tour-service-date-selector' );
		echo '</p>';

		echo '<table class="tsds-schedule-table widefat">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Day', 'tour-service-date-selector' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'tour-service-date-selector' ) . '</th>';
		echo '<th>' . esc_html__( 'Start Time', 'tour-service-date-selector' ) . '</th>';
		echo '<th>' . esc_html__( 'End Time', 'tour-service-date-selector' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		$day_labels = array(
			'sunday'    => __( 'Sunday', 'tour-service-date-selector' ),
			'monday'    => __( 'Monday', 'tour-service-date-selector' ),
			'tuesday'   => __( 'Tuesday', 'tour-service-date-selector' ),
			'wednesday' => __( 'Wednesday', 'tour-service-date-selector' ),
			'thursday'  => __( 'Thursday', 'tour-service-date-selector' ),
			'friday'    => __( 'Friday', 'tour-service-date-selector' ),
			'saturday'  => __( 'Saturday', 'tour-service-date-selector' ),
		);

		foreach ( Helper::$weekdays as $day_slug ) {
			$day_data = $schedule[ $day_slug ] ?? array(
				'enabled' => false,
				'start'   => '09:00',
				'end'     => '17:00',
			);

			$enabled = ! empty( $day_data['enabled'] );
			$start   = esc_attr( $day_data['start'] ?? '09:00' );
			$end     = esc_attr( $day_data['end'] ?? '17:00' );
			$label   = $day_labels[ $day_slug ] ?? ucfirst( $day_slug );

			echo '<tr class="tsds-schedule-row">';
			echo '<td class="tsds-schedule-day">' . esc_html( $label ) . '</td>';

			// Enabled checkbox.
			echo '<td class="tsds-schedule-enabled">';
			echo '<input type="checkbox"'
				. ' id="tsds_schedule_' . esc_attr( $day_slug ) . '_enabled"'
				. ' name="tsds_schedule[' . esc_attr( $day_slug ) . '][enabled]"'
				. ' value="1"'
				. ( $enabled ? ' checked="checked"' : '' )
				. ' class="tsds-day-enabled-cb" />';
			echo '</td>';

			// Start time.
			echo '<td class="tsds-schedule-start">';
			echo '<input type="time"'
				. ' id="tsds_schedule_' . esc_attr( $day_slug ) . '_start"'
				. ' name="tsds_schedule[' . esc_attr( $day_slug ) . '][start]"'
				. ' value="' . $start . '"'
				. ' class="tsds-time-input"'
				. ' step="900" />';
			echo '</td>';

			// End time.
			echo '<td class="tsds-schedule-end">';
			echo '<input type="time"'
				. ' id="tsds_schedule_' . esc_attr( $day_slug ) . '_end"'
				. ' name="tsds_schedule[' . esc_attr( $day_slug ) . '][end]"'
				. ' value="' . $end . '"'
				. ' class="tsds-time-input"'
				. ' step="900" />';
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>'; // #tsds-weekly-schedule

		echo '</div>'; // .panel
	}
}
