<?php
/**
 * Admin Variation Fields class.
 *
 * @package TSDS\Admin
 */

declare( strict_types=1 );

namespace TSDS\Admin;

defined( 'ABSPATH' ) || exit;

use TSDS\Helper;

/**
 * Class Variation_Fields
 *
 * Adds Tour Service Settings fields to each product variation.
 */
class Variation_Fields {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action(
			'woocommerce_product_after_variable_attributes',
			array( $this, 'render_variation_fields' ),
			10,
			3
		);

		add_action(
			'woocommerce_save_product_variation',
			array( $this, 'save_variation' ),
			10,
			2
		);
	}

	/**
	 * Render Tour Service Settings fields for a variation.
	 *
	 * @param int      $loop           Variation loop index.
	 * @param array    $variation_data Variation data.
	 * @param \WP_Post $variation      Variation post object.
	 */
	public function render_variation_fields(
		int $loop,
		array $variation_data,
		\WP_Post $variation
	): void {
		$variation_id = $variation->ID;
		$service_type = get_post_meta( $variation_id, Helper::META_SERVICE_TYPE, true ) ?: '';
		$schedule_raw = get_post_meta( $variation_id, Helper::META_SCHEDULE, true );
		$schedule     = Helper::normalize_schedule( is_array( $schedule_raw ) ? $schedule_raw : array() );

		// Nonce per variation.
		wp_nonce_field(
			'tsds_save_variation_' . $variation_id,
			'tsds_variation_nonce_' . $variation_id
		);
		?>
		<div class="tsds-variation-settings woocommerce_options_panel">
			<h4 class="tsds-variation-heading">
				<?php esc_html_e( 'Tour Service Settings (Variation)', 'tour-service-date-selector' ); ?>
			</h4>
			<p class="tsds-variation-description">
				<?php esc_html_e( 'Leave blank to inherit parent product settings.', 'tour-service-date-selector' ); ?>
			</p>

			<p class="form-row form-row-full">
				<label for="tsds_variation_service_type_<?php echo esc_attr( $loop ); ?>">
					<?php esc_html_e( 'Service Type', 'tour-service-date-selector' ); ?>
				</label>
				<select
					id="tsds_variation_service_type_<?php echo esc_attr( $loop ); ?>"
					name="tsds_variation_service_type[<?php echo esc_attr( $loop ); ?>]"
					class="tsds-variation-service-type"
					data-variation-id="<?php echo esc_attr( $variation_id ); ?>"
				>
					<option value="">
						<?php esc_html_e( '— Inherit from parent —', 'tour-service-date-selector' ); ?>
					</option>
					<?php foreach ( Helper::service_type_labels() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"
							<?php selected( $service_type, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php
			// Weekly schedule table for variation.
			$schedule_hidden = ( '' === $service_type || Helper::SERVICE_OPEN_DATED === $service_type )
				? ' style="display:none;"' : '';
			?>
			<div class="tsds-variation-schedule"<?php echo $schedule_hidden; // phpcs:ignore ?>>
				<h5 class="tsds-schedule-heading">
					<?php esc_html_e( 'Weekly Schedule', 'tour-service-date-selector' ); ?>
				</h5>
				<table class="tsds-schedule-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Day', 'tour-service-date-selector' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'tour-service-date-selector' ); ?></th>
							<th><?php esc_html_e( 'Start Time', 'tour-service-date-selector' ); ?></th>
							<th><?php esc_html_e( 'End Time', 'tour-service-date-selector' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					$day_labels = array(
						'sunday'    => __( 'Sunday', 'tour-service-date-selector' ),
						'monday'    => __( 'Monday', 'tour-service-date-selector' ),
						'tuesday'   => __( 'Tuesday', 'tour-service-date-selector' ),
						'wednesday' => __( 'Wednesday', 'tour-service-date-selector' ),
						'thursday'  => __( 'Thursday', 'tour-service-date-selector' ),
						'friday'    => __( 'Friday', 'tour-service-date-selector' ),
						'saturday'  => __( 'Saturday', 'tour-service-date-selector' ),
					);

					foreach ( Helper::$weekdays as $day_slug ) :
						$day_data = $schedule[ $day_slug ] ?? array(
							'enabled' => false,
							'start'   => '09:00',
							'end'     => '17:00',
						);
						$enabled  = ! empty( $day_data['enabled'] );
						$start    = esc_attr( $day_data['start'] ?? '09:00' );
						$end      = esc_attr( $day_data['end'] ?? '17:00' );
						$label    = $day_labels[ $day_slug ] ?? ucfirst( $day_slug );
						?>
						<tr class="tsds-schedule-row">
							<td class="tsds-schedule-day"><?php echo esc_html( $label ); ?></td>
							<td class="tsds-schedule-enabled">
								<input type="checkbox"
									name="tsds_variation_schedule[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $day_slug ); ?>][enabled]"
									value="1"
									<?php checked( $enabled ); ?>
									class="tsds-day-enabled-cb" />
							</td>
							<td class="tsds-schedule-start">
								<input type="time"
									name="tsds_variation_schedule[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $day_slug ); ?>][start]"
									value="<?php echo $start; ?>"
									class="tsds-time-input"
									step="900" />
							</td>
							<td class="tsds-schedule-end">
								<input type="time"
									name="tsds_variation_schedule[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $day_slug ); ?>][end]"
									value="<?php echo $end; ?>"
									class="tsds-time-input"
									step="900" />
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- .tsds-variation-schedule -->
		</div><!-- .tsds-variation-settings -->
		<?php
	}

	/**
	 * Save variation Tour Service Settings.
	 *
	 * @param int $variation_id Variation post ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation( int $variation_id, int $loop ): void {
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		$nonce_key = 'tsds_variation_nonce_' . $variation_id;
		if ( ! isset( $_POST[ $nonce_key ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ),
				'tsds_save_variation_' . $variation_id
			)
		) {
			return;
		}

		// Service type.
		$type_raw = $_POST['tsds_variation_service_type'][ $loop ] // phpcs:ignore WordPress.Security.NonceVerification
			?? '';
		$service_type = sanitize_text_field( wp_unslash( (string) $type_raw ) );

		// Allow empty (inherit) or a valid service type.
		if ( $service_type && ! in_array( $service_type, Helper::service_types(), true ) ) {
			$service_type = '';
		}

		if ( $service_type ) {
			update_post_meta( $variation_id, Helper::META_SERVICE_TYPE, $service_type );
		} else {
			delete_post_meta( $variation_id, Helper::META_SERVICE_TYPE );
		}

		// Weekly schedule.
		$schedule_raw_all = $_POST['tsds_variation_schedule'] ?? array(); // phpcs:ignore WordPress.Security.NonceVerification
		$schedule_raw     = is_array( $schedule_raw_all ) && isset( $schedule_raw_all[ $loop ] )
			? (array) $schedule_raw_all[ $loop ]
			: array();

		if ( ! empty( $schedule_raw ) ) {
			$schedule = $this->sanitize_schedule( $schedule_raw );
			update_post_meta( $variation_id, Helper::META_SCHEDULE, $schedule );
		}
	}

	/**
	 * Sanitize the weekly schedule input.
	 *
	 * @param array<string,mixed> $raw Raw input.
	 * @return array<string,array{enabled:bool,start:string,end:string}>
	 */
	private function sanitize_schedule( array $raw ): array {
		$schedule = array();

		foreach ( Helper::$weekdays as $day ) {
			$day_data = is_array( $raw[ $day ] ?? null ) ? $raw[ $day ] : array();
			$enabled  = ! empty( $day_data['enabled'] );
			$start    = sanitize_text_field( wp_unslash( (string) ( $day_data['start'] ?? '09:00' ) ) );
			$end      = sanitize_text_field( wp_unslash( (string) ( $day_data['end'] ?? '17:00' ) ) );

			if ( ! preg_match( '/^\d{2}:\d{2}$/', $start ) ) {
				$start = '09:00';
			}
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $end ) ) {
				$end = '17:00';
			}

			$schedule[ $day ] = array(
				'enabled' => $enabled,
				'start'   => $start,
				'end'     => $end,
			);
		}

		return $schedule;
	}
}
