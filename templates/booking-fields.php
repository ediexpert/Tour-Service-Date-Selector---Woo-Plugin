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
 * @package INTSDS
 */

defined( 'ABSPATH' ) || exit;

use INTSDS\Helper;

$intsds_is_variable = $product->is_type( 'variable' );

// For variable products, we always render the wrapper so JS can populate it.
// The initial visibility is controlled by JS.
$intsds_wrapper_class = 'intsds-booking-wrapper';

if ( Helper::SERVICE_OPEN_DATED === $service_type && ! $intsds_is_variable ) {
    return;
}

$intsds_show_date = in_array( $service_type, array( Helper::SERVICE_DATE_ONLY, Helper::SERVICE_DATE_TIME ), true );
$intsds_show_time = ( Helper::SERVICE_DATE_TIME === $service_type );

$intsds_date_input_placeholder = ( Helper::SERVICE_DATE_TIME === $service_type )
    ? __( 'Select date and time', 'ints-tour-service-date-selector' )
    : Helper::get_date_label();

// For variable products, start hidden; JS will reveal as needed.
$intsds_wrapper_style = ( $intsds_is_variable && Helper::SERVICE_OPEN_DATED === $service_type )
    ? ' style="display:none;"'
    : '';
?>
<div
    class="<?php echo esc_attr( $intsds_wrapper_class ); ?>"
    data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
    data-service-type="<?php echo esc_attr( $service_type ); ?>"
    <?php echo $intsds_wrapper_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    role="group"
    aria-label="<?php echo esc_attr__( 'Booking Details', 'ints-tour-service-date-selector' ); ?>"
>
    <?php // Nonce field for add-to-cart security. ?>
    <input
        type="hidden"
        name="intsds_nonce"
        value="<?php echo esc_attr( wp_create_nonce( 'intsds_add_to_cart' ) ); ?>"
    />

    <div class="intsds-booking-content" id="intsds-booking-content">
        <?php if ( $intsds_show_date || $intsds_is_variable ) : ?>
        <div
            class="intsds-field-group intsds-date-field-group"
            <?php echo ( ! $intsds_show_date && $intsds_is_variable ) ? 'style="display:none;"' : ''; // phpcs:ignore ?>
        >
            <label class="intsds-label" for="intsds-calendar">
				<?php echo esc_html( Helper::get_date_label() ); ?>
            </label>

            <input
                type="text"
                id="intsds-calendar"
                class="intsds-date-input"
                value=""
                placeholder="<?php echo esc_attr( $intsds_date_input_placeholder ); ?>"
                autocomplete="off"
                readonly
                aria-required="true"
                aria-describedby="intsds-date-error"
                aria-label="<?php echo esc_attr( $intsds_date_input_placeholder ); ?>"
            />

            <p class="intsds-selected-date" id="intsds-selected-date" aria-live="polite" style="display:none;"></p>

            <input
                type="hidden"
                id="intsds-booking-date"
                name="intsds_booking_date"
                value=""
                aria-required="true"
                aria-label="<?php echo esc_attr( Helper::get_date_label() ); ?>"
            />

            <div
                class="intsds-error intsds-date-error"
                id="intsds-date-error"
                role="alert"
                aria-live="polite"
                style="display:none;"
            ></div>
        </div>
        <?php endif; ?>

        <?php if ( $intsds_show_time || $intsds_is_variable ) : ?>
        <div
            class="intsds-field-group intsds-time-field-group"
            id="intsds-time-field-group"
            <?php echo ( ! $intsds_show_time && $intsds_is_variable ) ? 'style="display:none;"' : ''; // phpcs:ignore ?>
        >
            <label class="intsds-label" for="intsds-booking-time">
                <?php esc_html_e( 'Select Time', 'ints-tour-service-date-selector' ); ?>
                <span class="intsds-required" aria-hidden="true">*</span>
            </label>

            <select
                id="intsds-booking-time"
                name="intsds_booking_time"
                class="intsds-time-select"
                aria-required="true"
                aria-describedby="intsds-time-error"
            >
                <option value="">
                    <?php esc_html_e( '— Select time —', 'ints-tour-service-date-selector' ); ?>
                </option>
            </select>

            <div
                class="intsds-error intsds-time-error"
                id="intsds-time-error"
                role="alert"
                aria-live="polite"
                style="display:none;"
            ></div>
        </div>
        <?php endif; ?>
    </div><!-- .intsds-booking-content -->
</div><!-- .intsds-booking-wrapper -->