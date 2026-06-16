<?php
/**
 * Booking fields template.
 *
 * Variables available:
 *   $product      WC_Product    The current product.
 *   $product_id   int           Product ID.
 *   $service_type string        Current service type.
 *   $schedule     array         Normalised weekly schedule.
 *
 * @package TSDS
 */

defined( 'ABSPATH' ) || exit;

use TSDS\Helper;

$is_variable = $product->is_type( 'variable' );

// For variable products, we always render the wrapper so JS can populate it.
// The initial visibility is controlled by JS.
$wrapper_class = 'tsds-booking-wrapper';

if ( Helper::SERVICE_OPEN_DATED === $service_type && ! $is_variable ) {
    return;
}

$show_date = in_array( $service_type, array( Helper::SERVICE_DATE_ONLY, Helper::SERVICE_DATE_TIME ), true );
$show_time = ( Helper::SERVICE_DATE_TIME === $service_type );

$date_input_placeholder = ( Helper::SERVICE_DATE_TIME === $service_type )
    ? __( 'Select date and time', 'tour-service-date-selector' )
    : __( 'Select date', 'tour-service-date-selector' );

// For variable products, start hidden; JS will reveal as needed.
$wrapper_style = ( $is_variable && Helper::SERVICE_OPEN_DATED === $service_type )
    ? ' style="display:none;"'
    : '';
?>
<div
    class="<?php echo esc_attr( $wrapper_class ); ?>"
    data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
    data-service-type="<?php echo esc_attr( $service_type ); ?>"
    <?php echo $wrapper_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    role="group"
    aria-label="<?php echo esc_attr__( 'Booking Details', 'tour-service-date-selector' ); ?>"
>
    <?php // Nonce field for add-to-cart security. ?>
    <input
        type="hidden"
        name="tsds_nonce"
        value="<?php echo esc_attr( wp_create_nonce( 'tsds_add_to_cart' ) ); ?>"
    />

    <div class="tsds-booking-content" id="tsds-booking-content">
        <?php if ( $show_date || $is_variable ) : ?>
        <div
            class="tsds-field-group tsds-date-field-group"
            <?php echo ( ! $show_date && $is_variable ) ? 'style="display:none;"' : ''; // phpcs:ignore ?>
        >
            <label class="tsds-label" for="tsds-calendar">
                <?php esc_html_e( 'Select Date', 'tour-service-date-selector' ); ?>
                <span class="tsds-required" aria-hidden="true">*</span>
            </label>

            <input
                type="text"
                id="tsds-calendar"
                class="tsds-date-input"
                value=""
                placeholder="<?php echo esc_attr( $date_input_placeholder ); ?>"
                autocomplete="off"
                readonly
                aria-required="true"
                aria-describedby="tsds-date-error"
                aria-label="<?php echo esc_attr( $date_input_placeholder ); ?>"
            />

            <p class="tsds-selected-date" id="tsds-selected-date" aria-live="polite" style="display:none;"></p>

            <input
                type="hidden"
                id="tsds-booking-date"
                name="tsds_booking_date"
                value=""
                aria-required="true"
                aria-label="<?php esc_attr_e( 'Booking date', 'tour-service-date-selector' ); ?>"
            />

            <div
                class="tsds-error tsds-date-error"
                id="tsds-date-error"
                role="alert"
                aria-live="polite"
                style="display:none;"
            ></div>
        </div>
        <?php endif; ?>

        <?php if ( $show_time || $is_variable ) : ?>
        <div
            class="tsds-field-group tsds-time-field-group"
            id="tsds-time-field-group"
            <?php echo ( ! $show_time && $is_variable ) ? 'style="display:none;"' : ''; // phpcs:ignore ?>
        >
            <label class="tsds-label" for="tsds-booking-time">
                <?php esc_html_e( 'Select Time', 'tour-service-date-selector' ); ?>
                <span class="tsds-required" aria-hidden="true">*</span>
            </label>

            <select
                id="tsds-booking-time"
                name="tsds_booking_time"
                class="tsds-time-select"
                aria-required="true"
                aria-describedby="tsds-time-error"
            >
                <option value="">
                    <?php esc_html_e( '— Select time —', 'tour-service-date-selector' ); ?>
                </option>
            </select>

            <div
                class="tsds-error tsds-time-error"
                id="tsds-time-error"
                role="alert"
                aria-live="polite"
                style="display:none;"
            ></div>
        </div>
        <?php endif; ?>
    </div><!-- .tsds-booking-content -->
</div><!-- .tsds-booking-wrapper -->